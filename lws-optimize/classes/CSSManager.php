<?php

include_once(LWS_OP_DIR . "/vendor/autoload.php");

use MatthiasMullie\Minify;

/**
 * Manage the minification and combination of CSS files. 
 * Mostly a fork of WPFC. The main difference come from the way files are modified, by using Matthias Mullie library
 */
class CSSManager extends LwsOptimize
{
    private $content;
    private $content_directory;
    private $preloadable_urls;
    private $preloadable_urls_fonts;

    public function __construct($content, array $preloadable = [], array $preloadable_fonts = [])
    {
        // Get the page content and the PATH to the cache directory as well as creating it if needed
        $this->content = $content;
        $this->content_directory = $this->lwsop_get_content_directory("cache-css/");
        $this->preloadable_urls = $preloadable;
        $this->preloadable_urls_fonts = $preloadable_fonts;

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }
    }

    /**
     * Combine all <link> tags into fewer files to speed up loading times and reducing the weight of the page
     */
    public function combine_css_update($header = false)
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> and <style> tags
        preg_match_all("/(<link\s*.*?>|<style\s*.*?<\/style>)/xs", $this->content, $matches);

        $header_combined_style = "";
        $current_links = [];
        $current_media = false;

        $elements = $matches[0];
        // Loop through each tag
        foreach ($elements as $key => $element) {
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
                

                if ($rel !== "stylesheet" || $this->check_for_exclusion($href, "combine")) {
                    $file_url = $this->combine_current_css($current_links);
                    if (!empty($file_url) && $file_url !== false) {
                        $newLink = "<link rel='stylesheet' href='$file_url' media='$current_media'>";
                        $this->content = str_replace($element, "$newLink\n$element", $this->content);
                    }
                    

                    $current_links = [];
                    $current_media = false;
                    continue;
                }

                // Stylesheets with the same media will get combined together. We store the link's media as the $current_media if it is empty
                if ($current_media == false) {
                    $current_media = $media;
                }

                // If the link's media is the same as the $current_media, add it to the array
                if ($media == $current_media) {
                    $current_links[] = $href;
                    $this->content = str_replace($element, "<!-- Removed $href-->", $this->content);
                } else {
                    // Combine the links stored
                    $file_url = $this->combine_current_css($current_links);
                    if (!empty($file_url) && $file_url !== false) {
                        // Create a new link with the newly combined URL and add it to the DOM
                        $newLink = "<link rel='stylesheet' href='$file_url' media='$current_media'>";
                        $this->content = str_replace($element, "<!-- Removed (2) $href -->\n$newLink", $this->content);
                    }                    

                    // Empty the array and add in the current <link> being observed
                    $current_links = [];
                    $current_links[] = $href;
                    $current_media = $media;
                }
            }
            // In case of a <style>, we add it the current <link> to the DOM before the style and empty the array
            elseif (substr($element, 0, 6) == "<style") {

                $file_url = $this->combine_current_css($current_links);
                if (!empty($file_url) && $file_url !== false) {
                    $newLink = "<link rel='stylesheet' href='$file_url' media='$current_media'>";
                    $this->content = str_replace($element, "$newLink\n$element", $this->content);
                }                

                $current_links = [];
                $current_media = false;
            }

            // If we reached the last link, add what is currently in the array to the DOM
            if ($key + 1 == count($elements)) {
                // Combine the links stored
                $file_url = $this->combine_current_css($current_links);
                if (!empty($file_url) && $file_url !== false) {
                    // Create a new link with the newly combined URL and add it to the DOM
                    $newLink = "<link rel='stylesheet' href='$file_url' media='$current_media'>";

                    if (isset($href)) {
                        $this->content = str_replace("$href-->", "$href -->\n$newLink", $this->content);
                    }
                }                
            }
        }

        // return json_encode($arrays);
        return $this->content;
    }

    public function combine_current_css(array $links)
    {
        if (empty($links)) {
            return false;
        }

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }

        if (is_dir($this->content_directory)) {
            $minify = new Minify\CSS();

            $name = "";
            $size = 0;
            $amount = 0;

            // Add each CSS file to the minifier
            foreach ($links as $link) {
                $file_path = $link;

                $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                $file_path = explode("?ver", $file_path)[0];

                $name = base_convert(crc32($name . $link), 20, 36);

                if (file_exists($file_path)) {
                    $size += filesize($file_path);
                    $amount += 1;
                }

                $minify->add($file_path);
            }

            if (empty($name)) {
                return false;
            }

            $path = $this->lwsop_get_content_directory("cache-css/$name.css");
            $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

            // Minify and combine all files into one, saved in $path
            // If it worked, we can prepare the new <link> tag
            if ($minify->minify($path)) {
                $stats = get_option('lws_optimize_cache_statistics', [
                    'desktop' => ['amount' => 0, 'size' => 0],
                    'mobile' => ['amount' => 0, 'size' => 0],
                    'css' => ['amount' => 0, 'size' => 0],
                    'js' => ['amount' => 0, 'size' => 0],
                ]);

                $stats['css']['amount'] += $amount;
                $stats['css']['size'] += $size;
                update_option('lws_optimize_cache_statistics', $stats);

                return $path_url;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Minify all CSS links found in the $this->content page and return the page with the changes
     */
    public function minify_css()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> tags
        preg_match_all("/<link\s*.*?>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link>, get their attributes and verify if we have to minify them
        // Then we minify it and replace the old URL by the minified one
        foreach ($elements as $key => $element) {
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

                if ($rel !== "stylesheet" || $this->check_for_exclusion($href, "minify")) {
                    continue;
                }

                $name = base_convert(crc32($href), 20, 36);

                if (empty($name)) {
                    return false;
                }

                $file_path = str_replace(get_site_url() . "/", ABSPATH, $href);
                $file_path = explode("?ver", $file_path)[0];

                $path = $this->lwsop_get_content_directory("cache-css/$name.css");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                $minify = new Minify\CSS($file_path);

                if ($minify->minify($path)) {
                    $stats = get_option('lws_optimize_cache_statistics', [
                        'desktop' => ['amount' => 0, 'size' => 0],
                        'mobile' => ['amount' => 0, 'size' => 0],
                        'css' => ['amount' => 0, 'size' => 0],
                        'js' => ['amount' => 0, 'size' => 0],
                    ]);

                    $stats['css']['amount'] += 1;
                    $stats['css']['size'] += filesize($path);
                    update_option('lws_optimize_cache_statistics', $stats);

                    if (file_exists($path)) {
                        // Create a new link with the newly combined URL and add it to the DOM
                        $newLink = "<link rel='stylesheet' href='$path_url' media='$media'>";
                        $this->content = str_replace($element, $newLink, $this->content);
                    }
                }
            }
        }

        return $this->content;
    }

    /**
     * Add rel="preload" to the <link>
     */
    public function preload_css()
    {
        // Get all <link> tags
        preg_match_all("/<link\s*.*?>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link> and replace the rel="stylesheet" by rel="preload" as="style"
        foreach ($elements as $key => $element) {
            if (substr($element, 0, 5) == "<link") {
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $src);

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
        preg_match_all("/<link\s*.*?>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link> and replace the rel="stylesheet" by rel="preload" as="style"
        foreach ($elements as $key => $element) {
            if (substr($element, 0, 5) == "<link") {
                preg_match("/rel\=[\'\"]([^\'\"]+)[\'\"]/", $element, $rel);
                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $src);

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
            if (empty($option) || $option === NULL) {
                return ['state' => "false", 'data' => []];
            }

            $option = sanitize_text_field($option);
            if (isset($optimize_options[$option])) {
                if (isset($optimize_options[$option]['state'])) {
                    $array = $optimize_options[$option];
                    $state = $array['state'];
                    unset($array['state']);
                    $data = $array;

                    return ['state' => $state, 'data' => $data];
                }
            }
            return ['state' => "false", 'data' => []];
        } catch (Exception $e) {
            error_log("LwsOptimize.php::lwsop_check_option | " . $e);
            return ['state' => "false", 'data' => []];
        }
    }

    /**
     * Compare the given $url of $type (minify/combine) with the exceptions. 
     * If there is a match, $url is excluded
     */
    public function check_for_exclusion($url, $type)
    {
        if (empty($type)) {
            return false;
        }


        if (preg_match("/fonts/", $url)) {
            return true;
        }


        if (preg_match("/bootstrap/", $url)) {
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
                if (preg_match("~$exclusion~xs", $url)) {
                    return true;
                }
            }

            return false;
        } else if ($type == "combine") {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['combine_css']['state']) && $options_combine['combine_css']['state'] == "true" && isset($options_combine['combine_css']['exclusions'])) {
                $combine_css_exclusions = $options_combine['combine_css']['exclusions'];
            } else {
                $combine_css_exclusions = [];
            }

            // If the URL was excluded by the user
            foreach ($combine_css_exclusions as $exclusion) {
                if (preg_match("~$exclusion~xs", $url)) {
                    return true;
                }
            }

            // If the URL is found in a comment, ignore it as there is no point in processing unused files
            preg_match_all("/(<!--\s*.*?-->)/xs", $this->content, $matches);
            $comments = $matches[0] ? $matches[0] : [];
            foreach ($comments as $comment) {
                if (preg_match("~$url~xs", $comment)) {
                    return true;
                }
            }

            // If the URL is found in a script, ignore it so as not to break the page
            preg_match_all("/(<script\s*.*?<\/script>)/xs", $this->content, $matches);
            $scripts = $matches[0] ? $matches[0] : [];
            foreach ($scripts as $comment) {
                if (preg_match("~$url~xs", $comment)) {
                    return true;
                }
            }


            return false;
        } else {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['minify_html']['state']) && $options_combine['minify_html']['state'] == "true" && isset($options_combine['minify_html']['exclusions'])) {
                $combine_html_exclusions = $options_combine['minify_html']['exclusions'];
            } else {
                return false;
            }

            foreach ($combine_html_exclusions as $exclusion) {
                if (preg_match("~$exclusion~", $url)) {
                    return true;
                }
            }

            return false;
        }
    }
}
