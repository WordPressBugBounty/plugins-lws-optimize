<?php

namespace Lws\Classes\Front;

use MatthiasMullie\Minify;

/**
 * Manage the minification and combination of CSS files.
 * Mostly a fork of WPFC. The main difference come from the way files are modified, by using Matthias Mullie library
 */
class LwsOptimizeCSSManager
{
    // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_touch -- this entire class rewrites <link> tags already present in the rendered HTML output buffer (combining/minifying/deferring them); that's output post-processing, not asset registration, so it cannot go through wp_enqueue_style(). It also writes the combined CSS files to the cache directory on the front-end request hot path, so WP_Filesystem is not appropriate.
    private $content;
    private $content_directory;
    private $preloadable_urls;
    private $preloadable_urls_fonts;
    private $media_convertion;
    private $minify = false;

    public $files = ['file' => 0, 'size' => 0];

    public function __construct($content, array $preloadable = [], array $preloadable_fonts = [], $media_convertion = [], $minify_before_combine = false)
    {
        // Get the page content and the PATH to the cache directory as well as creating it if needed
        $this->content = $content;
        $this->content_directory = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-css/");
        $this->preloadable_urls = $preloadable;
        $this->preloadable_urls_fonts = $preloadable_fonts;
        $this->media_convertion = $media_convertion;

        $this->minify = $minify_before_combine;

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
            // Without this, combined/minified CSS only inherits the site-wide
            // mod_expires rule (no "public"/CDN-Cache-Control), so the LWS CDN
            // treats every request as a MISS. See lwsop_write_static_assets_cdn_htaccess().
            $GLOBALS['lws_optimize']->lwsop_write_static_assets_cdn_htaccess($GLOBALS['lws_optimize']->lwsop_get_cache_cdn_date());
        }
    }

    /**
     * Byte ranges of every <noscript> block in $snapshot.
     *
     * Tags inside <noscript> are a no-JS fallback: rewriting them is pointless (the browser
     * ignores the block whenever JS is on) and actively harmful, because a duplicated href
     * would then be seen twice by the combiner. Ranges are always computed from the same
     * pre-mutation snapshot the element list came from, so the offsets stay valid even though
     * $this->content is rewritten as we go.
     */
    private function get_noscript_ranges($snapshot)
    {
        $ranges = [];
        if (preg_match_all('#<noscript\b[^>]*>.*?</noscript>#is', $snapshot, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $ranges[] = [$match[1], $match[1] + strlen($match[0])];
            }
        }
        return $ranges;
    }

    private function is_in_noscript($offset, array $ranges)
    {
        foreach ($ranges as $range) {
            if ($offset >= $range[0] && $offset < $range[1]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a <link> is loaded off the critical path via the media="print" + onload swap
     * (this plugin's own Critical CSS, SureCookie, and most other async-CSS implementations).
     *
     * Such a tag must never be rebuilt from scratch: dropping the onload leaves it stuck on
     * media="print", so the stylesheet downloads but is never applied to the screen.
     *
     * Keyed on onload rather than media="print" so that a genuine print-only stylesheet (no
     * onload) stays combinable into a normal print batch.
     */
    private function is_async_stylesheet($element)
    {
        return (bool) preg_match('/\bonload\s*=/i', $element);
    }

    /**
     * Swap only the href value of an existing tag, preserving every other attribute
     * (onload, id, media, crossorigin, integrity, data-*, ...).
     *
     * A callback is used instead of a replacement string so that $ or backslashes in the URL
     * are not interpreted as backreferences.
     */
    private function replace_href($element, $new_url)
    {
        return preg_replace_callback(
            '/(\bhref\s*=\s*)([\'"])[^\'"]*\2/i',
            function ($matches) use ($new_url) {
                return $matches[1] . $matches[2] . $new_url . $matches[2];
            },
            $element,
            1
        );
    }

    /**
     * Build the <link> for a combined file. Async batches are re-emitted with the same
     * media="print" + onload + <noscript> shape they had before combining, mirroring
     * LwsOptimizeCriticalCSSManager::async_load_non_critical_css().
     */
    private function build_combined_link($url, $media, $is_async)
    {
        if (!$is_async) {
            return "<link rel='stylesheet' href='$url' media='$media'>";
        }

        return "<link rel='stylesheet' href='$url' media='print' onload=\"this.media='all';this.onload=null;\">"
            . "<noscript><link rel='stylesheet' href='$url' media='all'></noscript>";
    }

    /**
     * Combine the pending batch and return the markup that should replace it, or null when
     * there is nothing to emit. Files that could not be combined are re-emitted individually,
     * keeping the async wrapper when the batch was async.
     */
    private function build_batch_markup(array $links, $media, $is_async)
    {
        if (empty($links)) {
            return null;
        }

        $file_url = $this->combine_current_css($links);
        if (empty($file_url['final_url']) || $file_url['final_url'] === false) {
            return null;
        }

        $markup = '';
        foreach ($file_url['problematic'] as $problem_file) {
            $markup .= $this->build_combined_link($problem_file, $media, $is_async) . "\n";
        }

        return $markup . $this->build_combined_link($file_url['final_url'], $media, $is_async);
    }

    /**
     * Combine all <link> tags into fewer files to speed up loading times and reducing the weight of the page
     */
    public function combine_css_update()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> and <style> tags. Offsets are captured so that tags sitting inside a
        // <noscript> fallback can be skipped; both the element list and the <noscript> ranges
        // are derived from this same snapshot, taken before any rewriting happens.
        $snapshot = $this->content;
        $noscript_ranges = $this->get_noscript_ranges($snapshot);
        preg_match_all("/(<link\s*[^>]*+>|<style\s*.*?<\/style>)/xs", $snapshot, $matches, PREG_OFFSET_CAPTURE);

        $current_links = [];
        $current_media = false;
        $current_async = false;
        $last_removed_href = null; // href of the last link whose placeholder comment was inserted

        $elements = $matches[0];
        // Loop through each tag
        foreach ($elements as $entry) {
            $element = $entry[0];
            $offset = $entry[1];

            // Never touch the no-JS fallback; it is invisible whenever JS is enabled and its
            // href usually duplicates the async tag right before it.
            if ($this->is_in_noscript($offset, $noscript_ranges)) {
                continue;
            }

            // If it is a <link>, get the attributes and proceed with the verifications
            // If the <link> is to be combined, add it to the current array
            // Once we reach an incompatible <link> or a <style>, we combine the <link> and empty the array to start again with another batch of <link>
            if (substr($element, 0, 5) == "<link") {
                preg_match("/media\=[\'\"]([^\'\"]+)[\'\"]/", $element, $media);
                preg_match("/href\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/type\=[\'\"]([^\'\"]+)[\'\"]/", $element, $type);

                $media[1] = $media[1] ?? "all";
                $href[1] = $href[1] ?? "";
                $rel[1] = $rel[1] ?? "";
                $type[1] = $type[1] ?? "";

                $media = trim($media[1]);
                $href = trim($href[1]);
                $rel = trim($rel[1]);
                $type = trim($type[1]);


                $is_async = $this->is_async_stylesheet($element);

                if ($rel !== "stylesheet" || $this->check_for_exclusion($href, "combine")) {
                    // Flush the pending batch in front of this link, which stays untouched.
                    $markup = $this->build_batch_markup($current_links, $current_media, $current_async);
                    if ($markup !== null) {
                        $this->content = str_replace($element, "$markup\n$element", $this->content);
                    }

                    $current_links = [];
                    $current_media = false;
                    $current_async = false;
                    continue;
                }

                // Stylesheets sharing a batch key get combined together. Async stylesheets are
                // batched separately from render-blocking ones: they all carry media="print" but
                // must be re-emitted with the onload swap, which a plain print batch must not get.
                if ($current_media === false) {
                    $current_media = $media;
                    $current_async = $is_async;
                }

                // If the link belongs to the current batch, add it to the array
                if ($media == $current_media && $is_async === $current_async) {
                    $current_links[] = $href;
                    $this->content = str_replace($element, "<!-- Removed $href-->", $this->content);
                    $last_removed_href = $href;
                } else {
                    // The batch key changed: flush what we have *before* this link, then start a
                    // new batch with it. This link is a member of the next batch, so it is
                    // replaced by a placeholder just like any other batched link - overwriting it
                    // outright would drop its stylesheet from the page entirely.
                    $markup = $this->build_batch_markup($current_links, $current_media, $current_async);

                    $this->content = str_replace(
                        $element,
                        $markup !== null ? "$markup\n<!-- Removed $href-->" : "<!-- Removed $href-->",
                        $this->content
                    );
                    $last_removed_href = $href;

                    // Empty the array and add in the current <link> being observed
                    $current_links = [$href];
                    $current_media = $media;
                    $current_async = $is_async;
                }
            }
            // In case of a <style>, we add it the current <link> to the DOM before the style and empty the array
            elseif (substr($element, 0, 6) == "<style") {

                $markup = $this->build_batch_markup($current_links, $current_media, $current_async);
                if ($markup !== null) {
                    $this->content = str_replace($element, "$markup\n$element", $this->content);
                }

                $current_links = [];
                $current_media = false;
                $current_async = false;
            }
        }

        // Flush whatever is left once every tag has been seen. This runs after the loop rather
        // than on its last iteration, so a trailing skipped tag (e.g. a <noscript> fallback)
        // cannot swallow the final batch.
        $markup = $this->build_batch_markup($current_links, $current_media, $current_async);
        if ($markup !== null && $last_removed_href !== null) {
            $this->content = str_replace(
                "<!-- Removed $last_removed_href-->",
                "<!-- Removed $last_removed_href -->\n$markup",
                $this->content
            );
        }

        return ['html' => $this->content, 'files' => $this->files];
    }

    public function combine_current_css(array $links)
    {
        $problematic_files = [];

        if (empty($links)) {
            return ['final_url' => '', 'problematic' => []];
        }

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }

        if (is_dir($this->content_directory)) {
            $minify = new Minify\CSS();

            $name = "";

            // Track files that caused circular reference errors
            $problematic_files = [];
            $retry_needed = false;

            do {
                $retry_needed = false;
                $minify = new Minify\CSS();
                $name = "";

                // Add each CSS file to the minifier
                foreach ($links as $link) {

                    // Skip files that caused circular reference errors
                    if (in_array($link, $problematic_files)) {
                        continue;
                    }

                    $file_path = $link;

                    // Check if this is an external URL first (before any path conversions)
                    $is_external = (strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0 || strpos($link, '//') === 0);

                    // Handle protocol-relative URLs
                    if (strpos($link, '//') === 0) {
                        $file_path = (is_ssl() ? 'https:' : 'http:') . $link;
                        $is_external = true;
                    }

                    // Only check if it's external and not from our own site
                    if ($is_external && strpos($link, get_site_url()) === false) {
                        // This is an external CDN or remote CSS file
                        $remote_url = $file_path;

                        // Try to fetch the remote content using WordPress HTTP API as fallback
                        $content = false;

                        // First try file_get_contents
                        if (ini_get('allow_url_fopen')) {
                            $content = @file_get_contents($remote_url);
                        }

                        // Fallback to WordPress HTTP API if file_get_contents failed or is disabled
                        if ($content === false || empty($content)) {
                            $response = wp_remote_get($remote_url, array(
                                'timeout' => 10,
                                'sslverify' => true
                            ));

                            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                                $content = wp_remote_retrieve_body($response);
                            }
                        }

                        // Validate we got actual CSS content, not just the URL echoed back
                        if ($content !== false && !empty($content) && strlen($content) > strlen($remote_url) && strpos($content, '{') !== false) {
                            // Successfully fetched content - verify it looks like CSS.
                            // Always add via the minifier so it handles @import / url() path resolution
                            // even in the combine-only (non-minify) path.
                            $name = base_convert(crc32($name . $link), 20, 36);
                            $minify->add($content);
                        } else {
                            // If we can't fetch the remote file, add it to problematic files
                            $problematic_files[] = $link;
                            $retry_needed = true;
                            $debug_info = $content !== false ? ' (got ' . strlen($content) . ' bytes)' : ' (failed to fetch)';
                            $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: Could not fetch valid remote CSS file: ' . $remote_url . $debug_info);
                            continue;
                        }
                    } else {
                        // Local file - convert URL to file path
                        $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                        $file_path = explode("?", $file_path)[0];

                        // Guard against path traversal / arbitrary file read
                        $real_file_path = realpath($file_path);
                        if ($real_file_path === false || strpos($real_file_path, realpath(ABSPATH)) !== 0 || strtolower(pathinfo($real_file_path, PATHINFO_EXTENSION)) !== 'css') {
                            continue;
                        }
                        $file_path = $real_file_path;

                        if (file_exists($file_path)) {
                            // Always add via MatthiasMullie so relative url() / @import paths are
                            // rewritten correctly regardless of whether minification is active.
                            $minify->add($file_path);
                            $name = base_convert(crc32($name . $link), 20, 36);
                        }
                    }
                }

                if (empty($name)) {
                    continue;
                }

                $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-css/$name.min.css");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                // Do not add into cache if the file already exists
                $add_cache = false;
                if (!file_exists($path)) {
                    $add_cache = true;
                    // Ensure the directory exists before creating the file
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    if (!file_exists($path)) {
                        touch($path);
                    }
                } else {
                    // File already exists with identical content (CRC32 hash name) — skip regeneration to preserve Last-Modified
                    return ['final_url' => $path_url, 'problematic' => $problematic_files];
                }

                // Combine (and optionally minify) all files into one, saved in $path.
                // Always run through MatthiasMullie so url() / @import paths are resolved
                // correctly regardless of whether the minify flag is set.
                try {
                    if ($minify->minify($path) && file_exists($path)) {
                        $file_contents = file_get_contents($path);
                        foreach ($this->media_convertion as $media_element) {
                            $file_contents = str_replace($media_element['original'], $media_element['new'], $file_contents);
                        }
                        file_put_contents($path, $file_contents);

                        if ($add_cache) {
                            $this->files['file'] += 1;
                            $this->files['size'] += filesize($path) ?? 0;
                        }

                        return ['final_url' => $path_url, 'problematic' => $problematic_files];
                    } else {
                        return ['final_url' => false, 'problematic' => $problematic_files];
                    }
                } catch (\MatthiasMullie\Minify\Exceptions\FileImportException $e) {
                    // Log the error
                    $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize CSS Circular Reference: ' . $e->getMessage());

                    // Extract the problematic file name from the error message
                    if (preg_match('/Failed to import file "([^"]+)"/', $e->getMessage(), $matches)) {
                        $problem_file = $matches[1];

                        // Find which link corresponds to this file
                        foreach ($links as $link) {
                            $file_path = str_replace(get_site_url() . "/", ABSPATH, $link);
                            $file_path = explode("?", $file_path)[0];

                            if (strpos($problem_file, $file_path) !== false || strpos($file_path, $problem_file) !== false) {
                                $problematic_files[] = $link;
                                $retry_needed = true;
                                $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: Removed problematic CSS file from combination: ' . $link);
                                break;
                            }
                        }

                        // If we couldn't identify the exact file, add a more generic pattern
                        if (!$retry_needed && preg_match('/([^\/]+\.css)/', $problem_file, $css_matches)) {
                            $css_file = $css_matches[1];
                            foreach ($links as $link) {
                                if (strpos($link, $css_file) !== false) {
                                    $problematic_files[] = $link;
                                    $retry_needed = true;
                                    $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: Removed problematic CSS file from combination (pattern match): ' . $link);
                                    break;
                                }
                            }
                        }
                    }

                    // If we've already excluded all files, stop retrying
                    if (count($problematic_files) >= count($links)) {
                        $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: All CSS files caused circular references, aborting combination.');
                        return ['final_url' => '', 'problematic' => $problematic_files];
                    }

                    // If no files were identified as problematic in this iteration, exit the loop
                    if (!$retry_needed) {
                        $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: Could not identify problematic CSS file, aborting combination.');
                        return ['final_url' => '', 'problematic' => $problematic_files];
                    }
                } catch (\Exception $e) {
                    $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize CSS Error: ' . $e->getMessage());
                    return ['final_url' => '', 'problematic' => $problematic_files];

                }
            } while ($retry_needed && count($problematic_files) < count($links));

            return ['final_url' => '', 'problematic' => $problematic_files];
        }
        return ['final_url' => '', 'problematic' => []];
    }

    /**
     * Minify all CSS links found in the $this->content page and return the page with the changes
     */
    public function minify_css()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> tags. Offsets are captured so that tags inside a <noscript> fallback
        // can be skipped; both lists come from the same snapshot, taken before any rewriting.
        $snapshot = $this->content;
        $noscript_ranges = $this->get_noscript_ranges($snapshot);
        preg_match_all("/<link\s*[^>]*+>/xs", $snapshot, $matches, PREG_OFFSET_CAPTURE);

        $elements = $matches[0];
        // Loop through the <link>, get their attributes and verify if we have to minify them
        // Then we minify it and replace the old URL by the minified one
        foreach ($elements as $entry) {
            $element = $entry[0];

            if ($this->is_in_noscript($entry[1], $noscript_ranges)) {
                continue;
            }

            if (substr($element, 0, 5) == "<link") {

                preg_match("/media\=[\'\"]([^\'\"]+)[\'\"]/", $element, $media);
                preg_match("/href\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/type\=[\'\"]([^\'\"]+)[\'\"]/", $element, $type);

                $media[1] = $media[1] ?? "all";
                $href[1] = $href[1] ?? "";
                $rel[1] = $rel[1] ?? "";
                $type[1] = $type[1] ?? "";

                $media = trim($media[1]);
                $href = trim($href[1]);
                $rel = trim($rel[1]);
                $type = trim($type[1]);

                // Check if file is already minified
                if (preg_match('/(\.min\.css|\.min-[\w\d]+\.css)(\?.*)?$/i', $href)) {
                    continue; // Skip already minified files
                }


                if ($rel !== "stylesheet" || $this->check_for_exclusion($href, "minify")) {
                    continue;
                }

                $name = base_convert(crc32($href), 20, 36);

                if (empty($name)) {
                    continue;
                }

                $file_path = $href;

                // Check if this is an external URL first (before any path conversions)
                $is_external = (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0 || strpos($href, '//') === 0);

                // Handle protocol-relative URLs
                if (strpos($href, '//') === 0) {
                    $file_path = (is_ssl() ? 'https:' : 'http:') . $href;
                    $is_external = true;
                }

                $css_content = null;

                // Only check if it's external and not from our own site
                if ($is_external && strpos($href, get_site_url()) === false) {
                    // This is an external CDN or remote CSS file
                    $remote_url = $file_path;

                    // Try to fetch the remote content using WordPress HTTP API as fallback
                    $content = false;

                    // First try file_get_contents
                    if (ini_get('allow_url_fopen')) {
                        $content = @file_get_contents($remote_url);
                    }

                    // Fallback to WordPress HTTP API if file_get_contents failed or is disabled
                    if ($content === false || empty($content)) {
                        $response = wp_remote_get($remote_url, array(
                            'timeout' => 10,
                            'sslverify' => true
                        ));

                        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                            $content = wp_remote_retrieve_body($response);
                        }
                    }

                    // Validate we got actual CSS content
                    if ($content === false || empty($content) || strlen($content) <= strlen($remote_url) || strpos($content, '{') === false) {
                        $debug_info = $content !== false ? ' (got ' . strlen($content) . ' bytes)' : ' (failed to fetch)';
                        $GLOBALS['lws_optimize']->lwsop_debug_log('LwsOptimize: Could not fetch valid remote CSS file for minification: ' . $remote_url . $debug_info);
                        continue;
                    }

                    $css_content = $content;
                } else {
                    // Local file - convert URL to file path
                    $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                    $file_path = explode("?", $file_path)[0];

                    // Guard against path traversal / arbitrary file read
                    $real_file_path = realpath($file_path);
                    if ($real_file_path === false || strpos($real_file_path, realpath(ABSPATH)) !== 0 || strtolower(pathinfo($real_file_path, PATHINFO_EXTENSION)) !== 'css') {
                        continue;
                    }
                    $file_path = $real_file_path;

                    if (!file_exists($file_path)) {
                        continue;
                    }
                }

                $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-css/$name.min.css");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                // Do not add into cache if the file already exists
                $add_cache = false;
                if (!file_exists($path)) {
                    $add_cache = true;
                    // Ensure the directory exists before creating the file
                    $dir = dirname($path);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    if (!file_exists($path)) {
                        touch($path);
                    }
                } else {
                    // File already exists — skip regeneration but still swap the link to the cached URL.
                    // Only the href is rewritten, so onload/id/media and any other attribute survive.
                    $newLink = $this->replace_href($element, $path_url);
                    $this->content = str_replace($element, $newLink, $this->content);
                    continue;
                }

                if ($add_cache) {
                    // Create minifier with content or file path
                    if ($css_content !== null) {
                        // External CSS - use content
                        $minify = new Minify\CSS();
                        $minify->add($css_content);
                    } else {
                        // Local file - use file path
                        $minify = new Minify\CSS($file_path);
                    }

                    if ($minify->minify($path)) {
                        $file_contents = file_get_contents($path);
                        foreach ($this->media_convertion as $media_element) {
                            $file_contents = str_replace($media_element['original'], $media_element['new'], $file_contents);
                        }
                        file_put_contents($path, $file_contents);

                        $this->files['file'] += 1;
                        $this->files['size'] += filesize($path) ?? 0;

                        // Point the existing link at the minified file. Only the href is
                        // rewritten so that every other attribute survives — in particular the
                        // onload of an async media="print" stylesheet, without which the file
                        // would stay print-only and never apply to the screen.
                        $newLink = $this->replace_href($element, $path_url);
                        $this->content = str_replace($element, $newLink, $this->content);
                    }
                }
            }
        }

        return ['html' => $this->content, 'files' => $this->files];
    }

    /**
     * Add rel="preload" to the <link>
     */
    public function preload_css()
    {
        // Get all <link> tags
        preg_match_all("/<link\s*[^>]*+>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link> and replace the rel="stylesheet" by rel="preload" as="style"
        foreach ($elements as $element) {
            if (substr($element, 0, 5) == "<link") {
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/href\=[\'\"]([^\'\"]+)[\'\"]/", $element, $src);

                $rel = $rel[1] ?? "";
                $rel = trim($rel);

                $src = $src[1] ?? "";
                $src = trim($src);

                if ($rel !== "stylesheet"/* || $this->check_for_exclusion($href, "preload")*/) {
                    continue;
                }
                // Do not preload if the file has not been stated to be preloaded
                if (!in_array($src, $this->preloadable_urls)) {
                    continue;
                }

                $newLink = preg_replace("/rel\=[\'\"]([^\'\"]+)[\'\"]/", "rel=\"preload stylesheet\" as=\"style\"", $element);
                $this->content = str_replace($element, "$newLink", $this->content);
            }
        }

        return $this->content;
    }

    public function preload_fonts()
    {
        // Get all <link> tags
        preg_match_all("/<link\s*[^>]*+>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link> and replace the rel="stylesheet" by rel="preload" as="style"
        foreach ($elements as $element) {
            if (substr($element, 0, 5) == "<link") {
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/href\=[\'\"]([^\'\"]+)[\'\"]/", $element, $src);

                $rel = $rel[1] ?? "";
                $rel = trim($rel);

                $src = $src[1] ?? "";
                $src = trim($src);

                // Do not preload if the file has not been stated to be preloaded
                if (!in_array($src, $this->preloadable_urls_fonts)) {
                    continue;
                }

                // fonts cannot have "stylesheet" or "image"
                if ($rel == "stylesheet" || $rel == "image") {
                    continue;
                }

                $newLink = preg_replace("/rel\=[\'\"]([^\'\"]+)[\'\"]/", "rel=\"preload\" as=\"font\" crossorigin=\"anonymous\"", $element);
                $this->content = str_replace($element, "$newLink", $this->content);
            }
        }

        return $this->content;
    }

    public function lwsop_check_option(string $option)
    {
        $optimize_options = get_option('lws_optimize_config_array', []);
        try {
            if (empty($option) || $option === null) {
                return ['state' => "false", 'data' => []];
            }

            $option = sanitize_text_field($option);
            if (isset($optimize_options[$option]) && isset($optimize_options[$option]['state'])) {
                $array = $optimize_options[$option];
                $state = $array['state'];
                unset($array['state']);
                $data = $array;

                return ['state' => $state, 'data' => $data];
            }
        } catch (\Exception $e) {
            $GLOBALS['lws_optimize']->lwsop_debug_log("LwsOptimize.php::lwsop_check_option | " . $e);
        }

        return ['state' => "false", 'data' => []];
    }

    /**
     * Compare the given $url of $type (minify/combine) with the exceptions.
     * If there is a match, $url is excluded
     */
    public function check_for_exclusion($url, $type)
    {
        if (empty($type) || empty($url) ||
            preg_match("#\.(woff|woff2|eot|ttf|otf)(\?.*)?$#i", $url) ||
            preg_match("#(/bootstrap[^/]*\.css|/bootstrap/|bootstrap-[^/]*\.css)#i", $url) ||
            preg_match("#(fonts\.googleapis\.com|fonts\.gstatic\.com)#i", $url) || // Google Fonts
            preg_match("#(fontawesome|font-awesome)#i", $url)) { // Font Awesome
            return true;
        }

        // If the file is already minified, do not minify it again
        if ($this->minify && preg_match('/(\.min\.css|\.min-[\w\d]+\.css)(\?.*)?$/i', $url)) {
            return true;
        }

        // Never re-process the plugin's own combined/minified output, regardless of
        // any language/domain subdirectory lwsop_get_content_directory() may insert
        if (preg_match('#/cache-css/[^/]+\.min\.css(\?.*)?$#i', $url)) {
            return true;
        }

        // Automatically exclude URLs from revslider
        if (strpos($url, 'revslider') !== false) {
            return true;
        }

        if ($type == "minify") {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['minify_css']['state']) && $options_combine['minify_css']['state'] == "true" && isset($options_combine['minify_css']['exclusions'])) {
                $minify_css_exclusions = $options_combine['minify_css']['exclusions'];
            } else {
                $minify_css_exclusions = [];
            }

            foreach ($minify_css_exclusions as $exclusion) {
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
                    return true;
                }
            }
        } elseif ($type == "combine") {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['combine_css']['state']) && $options_combine['combine_css']['state'] == "true" && isset($options_combine['combine_css']['exclusions'])) {
                $combine_css_exclusions = $options_combine['combine_css']['exclusions'];
            } else {
                $combine_css_exclusions = [];
            }

            // If the URL was excluded by the user
            foreach ($combine_css_exclusions as $exclusion) {
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
                    return true;
                }
            }

            // If the URL is found in a comment, ignore it as there is no point in processing unused files
            preg_match_all("/(<!--\s*.*?-->)/xs", $this->content, $matches);
            $comments = $matches[0] ? $matches[0] : [];
            $quoted_url = preg_quote($url, '~');
            foreach ($comments as $comment) {
                // Skip the placeholders combine_css_update() writes itself: they embed the href of
                // every link already batched, so a URL appearing twice in the page (typically an
                // async link and its <noscript> twin) would otherwise self-exclude on the second
                // occurrence and be emitted in the wrong place.
                if (preg_match('/^<!--\s*Removed\s/i', $comment)) {
                    continue;
                }

                if (preg_match("~$quoted_url~xs", $comment)) {
                    return true;
                }
            }

            // If the URL is found in a script, ignore it so as not to break the page
            preg_match_all("/(<script\s*.*?<\/script>)/xs", $this->content, $matches);
            $scripts = $matches[0] ? $matches[0] : [];
            foreach ($scripts as $comment) {
                if (preg_match("~$quoted_url~xs", $comment)) {
                    return true;
                }
            }
        } else {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['minify_html']['state']) && $options_combine['minify_html']['state'] == "true" && isset($options_combine['minify_html']['exclusions'])) {
                $combine_html_exclusions = $options_combine['minify_html']['exclusions'];
                foreach ($combine_html_exclusions as $exclusion) {
                    $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                    $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                    if (preg_match("$regex_pattern", $url)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
    // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_touch
}
