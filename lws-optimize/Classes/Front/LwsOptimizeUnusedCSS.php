<?php

namespace Lws\Classes\Front;

/**
 * Removal of unused CSS through the LWS service.
 *
 * The service is never called while a page renders. The render only swaps in
 * results already on disk, and queues the URL (LwsOptimizeCSSJobQueue) when
 * there are none; generate() then builds them in the background, and the
 * queue purges the cached page so that the next render applies them.
 *
 * What is "unused" depends on the page, so results are stored per page, in
 * uploads/lwsoptimize/cleaned/<md5(url)>/: one <md5(original css)>.css per
 * stylesheet or inline block, plus a marker file dating the generation.
 */
class LwsOptimizeUnusedCSS
{
    const API_URL = 'https://unusedcss.lwspanel.com/generate-unused-css';

    // Results older than this are regenerated
    const MAX_AGE = 7 * DAY_IN_SECONDS;

    // Inline blocks smaller than this are not worth a call to the service
    const MIN_INLINE_SIZE = 1024;

    const MARKER = '.generated';

    private $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Swaps the stylesheets and inline styles of the page for their cleaned
     * versions, once the background job has produced them. No network call.
     */
    public function applyCleanedCSS()
    {
        $url = self::current_page_url();
        if ($url === null) {
            return $this->content;
        }

        $dir = self::results_dir($url);
        $generated = @filemtime($dir . self::MARKER);
        if ($generated === false || $generated < time() - self::MAX_AGE) {
            LwsOptimizeCSSJobQueue::enqueue('ucss', $url);
            return $this->content;
        }

        $dir_url = self::results_url($url);
        foreach (self::css_blocks($this->content) as $block) {
            $file = md5($block['css']) . '.css';
            if (!is_file($dir . $file)) {
                continue;
            }

            if ($block['href'] !== null) {
                $new_tag = str_replace($block['href'], $dir_url . $file, $block['tag']);
            } else {
                $cleaned = @file_get_contents($dir . $file);
                if ($cleaned === false) {
                    continue;
                }
                $new_tag = '<style' . $block['attrs'] . '>' . $cleaned . '</style>';
            }

            $this->content = str_replace($block['tag'], $new_tag, $this->content);
        }

        return $this->content;
    }

    /**
     * Background job (LwsOptimizeCSSJobQueue): has the service clean each
     * stylesheet and inline block of $url, and stores the results.
     *
     * @return bool|null true if stored, false on failure, null if the feature is off
     */
    public static function generate($url)
    {
        $options = get_option('lws_optimize_config_array', []);
        if (($options['remove_css']['state'] ?? 'false') !== 'true') {
            return null;
        }

        // This parameter bypasses the page cache and the optimization pipeline:
        // the page comes back unmodified, and rendering it queues nothing
        $response = wp_remote_get(add_query_arg('do_not_cache_lwsoptimize', '1', $url), ['timeout' => 15]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        $dir  = self::results_dir($url);
        if ($html === '' || !wp_mkdir_p($dir)) {
            return false;
        }

        $seen = [];
        $kept = [];
        foreach (self::css_blocks($html) as $block) {
            $hash = md5($block['css']);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            // The cleaned stylesheet moves to the uploads, its relative URLs must not break
            $css = $block['href'] !== null ? self::absolutize_urls($block['css'], $block['href']) : $block['css'];

            $cleaned = self::clean($html, $css);
            if ($cleaned === null) {
                // The service is down: the queue retries this page after the pause
                return false;
            }
            // Renders may read these files while a regeneration rewrites them
            if ($cleaned !== '' && $GLOBALS['lws_optimize']->lwsop_atomic_write($dir . $hash . '.css', $cleaned)) {
                $kept[$hash] = true;
            }
        }

        // Drop the results of styles the page no longer has
        foreach (glob($dir . '*.css') ?: [] as $file) {
            if (!isset($kept[basename($file, '.css')])) {
                wp_delete_file($file);
            }
        }

        touch($dir . self::MARKER);
        return true;
    }

    /**
     * Asks the service which part of $css the page $html uses.
     *
     * @return string|null cleaned CSS ('' if none came back), null if the service failed
     */
    private static function clean($html, $css)
    {
        $body = wp_json_encode(['html' => $html, 'css' => $css], JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            return '';
        }

        $response = wp_remote_post(self::API_URL, [
            'body'    => $body,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 20,
        ]);
        if (!LwsOptimizeCSSJobQueue::service_ok($response)) {
            return null;
        }

        $result  = json_decode(wp_remote_retrieve_body($response), true);
        $cleaned = is_array($result) ? ($result['cleanedCss'] ?? '') : '';
        if (!is_string($cleaned) || $cleaned === '') {
            return '';
        }

        // The service may answer with a link to the cleaned file
        if (filter_var($cleaned, FILTER_VALIDATE_URL)) {
            $file = wp_safe_remote_get($cleaned, ['timeout' => 10]);
            $cleaned = (!is_wp_error($file) && wp_remote_retrieve_response_code($file) === 200) ? wp_remote_retrieve_body($file) : '';
        }

        // Inline blocks are written back inside <style>: never let them close it
        return str_ireplace('</style', '<\/style', $cleaned);
    }

    /**
     * Stylesheets of the site and large enough inline blocks found in $html,
     * with their CSS. Stylesheets are read from disk, so a block is identified
     * by its content whatever query string its URL carries.
     */
    private static function css_blocks($html)
    {
        $blocks = [];

        preg_match_all("/<link[^>]*rel=['\"]stylesheet['\"][^>]*>/is", $html, $links);
        foreach ($links[0] as $tag) {
            if (!preg_match('/href=[\'"]([^\'"]+)[\'"]/i', $tag, $href)) {
                continue;
            }
            $path = self::local_css_path($href[1]);
            $css  = $path !== null ? @file_get_contents($path) : false;
            if (!empty($css)) {
                $blocks[] = ['tag' => $tag, 'href' => $href[1], 'css' => $css];
            }
        }

        preg_match_all("/<style([^>]*)>(.*?)<\/style>/is", $html, $styles, PREG_SET_ORDER);
        foreach ($styles as $style) {
            if (strlen($style[2]) >= self::MIN_INLINE_SIZE && strpos($style[1], 'lwsop-critical-css') === false) {
                $blocks[] = ['tag' => $style[0], 'attrs' => $style[1], 'href' => null, 'css' => $style[2]];
            }
        }

        return $blocks;
    }

    /**
     * Local file behind a stylesheet URL of the site, or null if it is not a
     * .css file inside the WordPress installation.
     */
    private static function local_css_path($href)
    {
        // Compared without the scheme: from the cron (no HTTPS request), site_url()
        // answers http:// while the page links its stylesheets in https://
        $url  = preg_replace('#^https?:#i', '', html_entity_decode(str_replace('\/', '/', $href)));
        $site = preg_replace('#^https?:#i', '', get_site_url()) . '/';
        if (strpos($url, $site) !== 0) {
            return null;
        }

        // Same traversal guard as LwsOptimizeCSSManager::minify_css()
        $real = realpath(ABSPATH . substr(explode('?', $url)[0], strlen($site)));
        if ($real === false || strpos($real, realpath(ABSPATH)) !== 0 || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'css') {
            return null;
        }

        return $real;
    }

    /**
     * Rewrites the relative url() of a stylesheet as absolute URLs, based on
     * the stylesheet's own URL.
     */
    private static function absolutize_urls($css, $href)
    {
        $url    = explode('?', html_entity_decode(str_replace('\/', '/', $href)))[0];
        $parts  = wp_parse_url($url);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $dir    = substr($url, 0, strrpos($url, '/') + 1);

        return preg_replace_callback(
            '/url\(\s*([\'"]?)(?!data:|https?:|\/\/|#)([^\'")]+)\1\s*\)/i',
            function ($m) use ($origin, $dir) {
                $path = trim($m[2]);
                return 'url(' . $m[1] . (strpos($path, '/') === 0 ? $origin : $dir) . $path . $m[1] . ')';
            },
            $css
        );
    }

    /**
     * URL the results of the current page are stored under, or null when the
     * page must be left alone: logged-in renders and query strings can change
     * the markup, and must not get the results of another page.
     */
    private static function current_page_url()
    {
        if (is_user_logged_in()) {
            return null;
        }

        // The preload's ?nocache= is already gone: LwsOptimize::lwsop_handle_preload_nocache()
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        return wp_parse_url($uri, PHP_URL_QUERY) ? null : LwsOptimizeCriticalCSSManager::current_url();
    }

    private static function results_dir($url)
    {
        return wp_upload_dir(null, false)['basedir'] . '/lwsoptimize/cleaned/' . md5($url) . '/';
    }

    private static function results_url($url)
    {
        return wp_upload_dir(null, false)['baseurl'] . '/lwsoptimize/cleaned/' . md5($url) . '/';
    }
}
