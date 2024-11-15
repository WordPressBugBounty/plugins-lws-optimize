<?php

namespace Lws\Classes\Front;

use MatthiasMullie\Minify;


/**
 * Manage the minification and combination of CSS files.
 * Mostly a fork of WPFC. The main difference come from the way files are modified, by using Matthias Mullie library
 */
class LwsOptimizeJSManager
{
    private $content;
    private $content_directory;
    private $excluded_scripts;

    public $files = ['file' => 0, 'size' => 0];

    public function __construct($content)
    {
        // Get the page content and the PATH to the cache directory as well as creating it if needed
        $this->content = $content;
        $this->content_directory = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/");

        $this->_set_excluded();

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }
    }

    /**
     * Combine all <link> tags into fewer files to speed up loading times and reducing the weight of the page
     */
    public function combine_js_update()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> and <style> tags
        preg_match_all("/(<script\s*.*?<\/script>)/xs", $this->content, $matches);

        $current_scripts = [];
        $ids = "";

        $elements = $matches[0];
        // Loop through each tag
        foreach ($elements as $key => $element) {
            if (substr($element, 0, 7) == "<script") {

                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $src);
                preg_match("/id\=[\'\"]([^\'\"]+)[\'\"]/", $element, $id);

                $src = $src[1] ?? "";
                $id = $id[1] ?? "";

                $id = trim($id);
                $src = trim($src);


                if (empty($src) || !str_contains($src, ".js") || $this->check_for_exclusion($src, "combine")) {
                    $file_url = $this->combine_current_js($current_scripts);
                    if (!empty($file_url) && $file_url !== false) {
                        $newLink = "<script id='$ids' type='text/javascript' src='$file_url'></script>";
                        $this->content = str_replace($element, "$newLink $element", $this->content);
                    }

                    $current_scripts = [];
                    $ids = "";
                    continue;
                }

                $current_scripts[] = $src;
                $ids .= " " . $id;
                $this->content = str_replace($element, "<!-- Removed $src-->", $this->content);
            }

            // If we reached the last script, add what is currently in the array to the DOM
            if ($key + 1 == count($elements)) {
                $file_url = $this->combine_current_js($current_scripts);
                if (!empty($file_url) && $file_url !== false) {
                    $newLink = "<script id='$ids' type='text/javascript' src='$file_url'></script>";

                    if (isset($src)) {
                        $this->content = str_replace("$src-->", "$src -->$newLink", $this->content);
                    }
                }
            }
        }

        return ['html' => $this->content, 'files' => $this->files];
    }

    public function endify_scripts()
    {
        preg_match_all("/(<script\s*.*?<\/script>)/xs", $this->content, $matches);

        $elements = $matches[0];

        $this->content = preg_replace("/(<script\s*.*?<\/script>)/xs", '', $this->content);
        $this->content = str_replace('</body>', implode(' ', $elements) . '</body>', $this->content);
        return $this->content;
    }

    public function combine_current_js(array $scripts)
    {
        if (empty($scripts)) {
            return false;
        }

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }

        if (is_dir($this->content_directory)) {
            $minify = new Minify\JS();

            $name = "";

            // Add each CSS file to the minifier
            foreach ($scripts as $script) {

                $file_path = $script;

                $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                $file_path = explode("?", $file_path)[0];

                $name = base_convert(crc32($name . $script), 20, 36);

                $minify->add($file_path);
            }

            if (empty($name)) {
                return false;
            }

            $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/$name.js");
            $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

            // Minify and combine all files into one, saved in $path
            // If it worked, we can prepare the new <link> tag
            if ($minify->minify($path)) {
                $this->files['file'] += 1;
                $this->files['size'] += filesize($path) ?? 0;

                return $path_url;
            }
        }
        return false;
    }

    /**
     * Minify all CSS links found in the $this->content page and return the page with the changes
     */
    public function minify_js()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <link> tags
        preg_match_all("/<script\s*.*?<\/script>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <link>, get their attributes and verify if we have to minify them
        // Then we minify it and replace the old URL by the minified one
        foreach ($elements as $element) {
            if (substr($element, 0, 7) == "<script") {
                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                $href = $href[1] ?? "";
                $href = trim($href);

                if (empty($href)) {
                    continue;
                }

                if ($this->check_for_exclusion($href, "minify")) {
                    continue;
                }

                $name = base_convert(crc32($href), 20, 36);

                if (empty($name)) {
                    return false;
                }

                $file_path = str_replace(get_site_url() . "/", ABSPATH, $href);
                $file_path = explode("?", $file_path)[0];

                $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/$name.js");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                $minify = new Minify\JS($file_path);

                if ($minify->minify($path) && file_exists($path)) {
                    $this->files['file'] += 1;
                    $this->files['size'] += filesize($path) ?? 0;
                    // Create a new link with the newly combined URL and add it to the DOM
                    $newLink = preg_replace("/src\=[\'\"]([^\'\"]+)[\'\"]/", "src='$path_url'", $element);
                    $this->content = str_replace($element, $newLink, $this->content);
                }
            }
        }

        return ['html' => $this->content, 'files' => $this->files];
    }


    private function _set_excluded()
    {
        $tag_start = "";
        $tag_end = "";
        $tags = [];

        // Looping through each character of the $content...
        for ($i = 0; $i < strlen($this->content); $i++) {
            // If we find at the character $i the beginning of a <link> tag, we keep note of the current position
            if (substr($this->content, $i, 15) == "document.write(") {
                $tag_start = $i;
            }

            // If we found a <link> tag and have started to read it...
            if (!empty($tag_start) && is_numeric($tag_start) && $i > $tag_start && substr($this->content, $i, 1) == ")") {
                // If we are at the very end of the <link> tag, we keep note of its position
                // then we fetch the content of the tag and add it to the listing
                $tag_end = $i;
                $text = substr($this->content, $tag_start, ($tag_end - $tag_start) + 1);
                array_push($tags, array("start" => $tag_start, "end" => $tag_end, "text" => $text));

                // Reinitialize the tracking of the tags
                $tag_start = "";
                $tag_end = "";
            }
        }

        foreach (array_reverse($tags) as $excluded) {
            $this->excluded_scripts .= $excluded['text'];
        }
    }

    public function merge_js($name, $content, $value, $last = false)
    {
        // Create the main cache directory if it does not exist yet
        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }

        if (is_dir($this->content_directory)) {
            $minify = new Minify\JS($content);
            $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/$name.js");
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

                $stats['js']['amount'] += 1;
                $stats['js']['size'] += filesize($path);
                update_option('lws_optimize_cache_statistics', $stats);

                $combined_link = "<script src='" . $path_url . "' type=\"text/javascript\"></script>";

                $script_tag = substr($this->content, $value["start"], ($value["end"] - $value["start"] + 1));

                if ($last) {
                    $script_tag = $combined_link . "\n<!-- " . $script_tag . " -->\n";
                } else {
                    $script_tag = $combined_link . "\n" . $script_tag;
                }

                $this->content = substr_replace($this->content, "\n$script_tag\n", $value["start"], ($value["end"] - $value["start"]) + 1);
            }
        }
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
            return ['state' => "false", 'data' => []];
        } catch (\Exception $e) {
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
            return true;
        }

        if (str_contains($url, "/jquery")) {
            return true;
        }

        $httpHost = str_replace("www.", "", $_SERVER["HTTP_HOST"]);

        //<script src="https://server1.opentracker.net/?site=www.site.com"></script>
        if (preg_match("/" . preg_quote($httpHost, "/") . "/i", $url)) {
            if (preg_match("/[\?\=].*" . preg_quote($httpHost, "/") . "/i", $url)) {
                return true;
            }
        } else {
            return true;
        }

        if (preg_match("/document\s*\).ready\s*\(/xs", (@file_get_contents($url) ?? ''), $matches)) {
            return true;
        }

        // If the URL is found in a comment, ignore it as there is no point in processing unused files
        preg_match_all("/(document.write\(\s*[^\)]*+\))/xs", $this->content, $matches);
        $writes = $matches[0] ? $matches[0] : [];
        foreach ($writes as $write) {
            if (preg_match("~$url~xs", $write)) {
                return true;
            }
        }

        if ($type == "minify") {
            $options_minify = $this->lwsop_check_option('minify_js');
            if ($options_minify['state'] == "true" && isset($options_minify['data']['exclusions'])) {
                $minify_js_exclusions = $options_minify['data']['exclusions'];
            } else {
                $minify_js_exclusions = [];
            }

            foreach ($minify_js_exclusions as $exclusion) {
                if (preg_match("~$exclusion~", $url)) {
                    return true;
                }
            }
        } elseif ($type == "combine") {
            $options_combine = $this->lwsop_check_option('combine_js');
            if ($options_combine['state'] == "true" && isset($options_combine['data']['exclusions'])) {
                $combine_js_exclusions = $options_combine['data']['exclusions'];
            } else {
                $combine_js_exclusions = [];
            }

            // If the URL was excluded by the user
            foreach ($combine_js_exclusions as $exclusion) {
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
        } else {
            $options_combine = get_option('lws_optimize_config_array', []);
            if (isset($options_combine['minify_html']['state']) && $options_combine['minify_html']['state'] == "true" && isset($options_combine['minify_html']['exclusions'])) {
                $combine_html_exclusions = $options_combine['minify_html']['exclusions'];
                foreach ($combine_html_exclusions as $exclusion) {
                    if (preg_match("~$exclusion~", $url)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function checkInternal($link)
    {
        $httpHost = str_replace("www.", "", $_SERVER["HTTP_HOST"]);

        if (
            preg_match("/^<script[^\>]+\>/i", $link, $script) && preg_match("/src=[\"\'](.*?)[\"\']/", $script[0], $src)
            && !preg_match("/alexa\.com\/site\_stats/i", $src[1]) && preg_match("/^\/[^\/]/", $src[1])
            && preg_match("/" . preg_quote($httpHost, "/") . "/i", $src[1]) && !preg_match("/[\?\=].*" . preg_quote($httpHost, "/") . "/i", $src[1])
        ) {
            return $src[1];
        }

        return false;
    }
}
