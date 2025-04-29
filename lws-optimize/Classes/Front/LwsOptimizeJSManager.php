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

        // Get all <script> tags
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

                // Check if script ID contains 'bootstrap' or 'jquery' and ignore
                if (!empty($id) && (stripos($id, 'bootstrap') !== false || stripos($id, 'jquery') !== false)) {
                    $file_result = $this->combine_current_js($current_scripts);
                    if (!empty($file_result['final_url']) && $file_result['final_url'] !== false) {
                        $newLink = "<script id='$ids' type='text/javascript' src='{$file_result['final_url']}'></script>";

                        // Handle problematic files
                        $old_scripts = '';
                        foreach ($file_result['problematic'] as $problem_file) {
                            $old_scripts .= "<script type='text/javascript' src='$problem_file'></script>\n";
                        }

                        $this->content = str_replace($element, "$old_scripts$newLink $element", $this->content);
                    }

                    $current_scripts = [];
                    $ids = "";
                    continue;
                }

                if (empty($src) || !str_contains($src, ".js") || $this->check_for_exclusion($src, "combine")) {
                    $file_result = $this->combine_current_js($current_scripts);
                    if (!empty($file_result['final_url']) && $file_result['final_url'] !== false) {
                        $newLink = "<script id='$ids' type='text/javascript' src='{$file_result['final_url']}'></script>";

                        // Handle problematic files
                        $old_scripts = '';
                        foreach ($file_result['problematic'] as $problem_file) {
                            $old_scripts .= "<script type='text/javascript' src='$problem_file'></script>\n";
                        }

                        $this->content = str_replace($element, "$old_scripts$newLink $element", $this->content);
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
                $file_result = $this->combine_current_js($current_scripts);
                if (!empty($file_result['final_url']) && $file_result['final_url'] !== false) {
                    $newLink = "<script id='$ids' type='text/javascript' src='{$file_result['final_url']}'></script>";

                    // Handle problematic files
                    $old_scripts = '';
                    foreach ($file_result['problematic'] as $problem_file) {
                        $old_scripts .= "<script type='text/javascript' src='$problem_file'></script>\n";
                    }

                    if (isset($src)) {
                        $this->content = str_replace("$src-->", "$src -->$old_scripts$newLink", $this->content);
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
        $problematic_files = [];

        if (empty($scripts)) {
            return ['final_url' => false, 'problematic' => []];
        }

        if (!is_dir($this->content_directory)) {
            mkdir($this->content_directory, 0755, true);
        }

        if (is_dir($this->content_directory)) {
            $retry_needed = false;

            do {
                $retry_needed = false;
                $minify = new Minify\JS();
                $name = "";

                // Add each JS file to the minifier
                foreach ($scripts as $script) {
                    // Skip files that caused issues in previous attempts
                    if (in_array($script, $problematic_files)) {
                        continue;
                    }

                    $file_path = $script;
                    $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                    $file_path = explode("?", $file_path)[0];

                    // If path starts with "//", handle protocol-relative URLs
                    if (substr($file_path, 0, 2) === "//") {
                        $file_path = substr($file_path, 2);
                        // Add http: or https: based on site settings
                        $file_path = (is_ssl() ? 'https:' : 'http:') . '//' . $file_path;
                        $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                    }

                    // Handle remote URLs (like CDN content)
                    if (strpos($file_path, 'http') === 0) {
                        $content = @file_get_contents($file_path);
                        if ($content !== false) {
                            $name = base_convert(crc32($name . $script), 20, 36);
                            $minify->add($content);
                        } else {
                            // If we can't fetch the remote file, add it to problematic files
                            $problematic_files[] = $script;
                            $retry_needed = true;
                            error_log('LwsOptimize: Could not fetch remote JS file: ' . $file_path);
                            continue;
                        }
                    } else {
                        if (file_exists($file_path)) {
                            $minify->add($file_path);
                            $name = base_convert(crc32($name . $script), 20, 36);
                        } else {
                            $problematic_files[] = $script;
                            $retry_needed = true;
                            error_log('LwsOptimize: Could not find JS file: ' . $file_path);
                            continue;
                        }
                    }
                }

                if (empty($name)) {
                    return ['final_url' => false, 'problematic' => $problematic_files];
                }

                $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/$name.js");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                // Do not add into cache if the file already exists
                $add_cache = !file_exists($path);

                // Minify and combine all files into one, saved in $path
                try {
                    if ($minify->minify($path) && file_exists($path)) {
                        if ($add_cache) {
                            $this->files['file'] += 1;
                            $this->files['size'] += filesize($path) ?? 0;
                        }

                        return ['final_url' => $path_url, 'problematic' => $problematic_files];
                    }
                } catch (\Exception $e) {
                    // Log the error
                    error_log('LwsOptimize JS Error: ' . $e->getMessage());

                    // Try to identify problematic file if possible
                    if (preg_match('/Failed to (import|parse) file "([^"]+)"/', $e->getMessage(), $matches)) {
                        $problem_file = $matches[2];

                        // Find which script corresponds to this file
                        foreach ($scripts as $script) {
                            $file_path = str_replace(get_site_url() . "/", ABSPATH, $script);
                            $file_path = explode("?", $file_path)[0];

                            if (strpos($problem_file, $file_path) !== false || strpos($file_path, $problem_file) !== false) {
                                $problematic_files[] = $script;
                                $retry_needed = true;
                                error_log('LwsOptimize: Removed problematic JS file from combination: ' . $script);
                                break;
                            }
                        }

                        // If we couldn't identify the exact file, try with a more generic pattern
                        if (!$retry_needed && preg_match('/([^\/]+\.js)/', $problem_file, $js_matches)) {
                            $js_file = $js_matches[1];
                            foreach ($scripts as $script) {
                                if (strpos($script, $js_file) !== false) {
                                    $problematic_files[] = $script;
                                    $retry_needed = true;
                                    error_log('LwsOptimize: Removed problematic JS file from combination (pattern match): ' . $script);
                                    break;
                                }
                            }
                        }
                    }

                    // If we've already excluded all files, stop retrying
                    if (count($problematic_files) >= count($scripts)) {
                        error_log('LwsOptimize: All JS files caused errors, aborting combination.');
                        return ['final_url' => false, 'problematic' => $problematic_files];
                    }

                    // If no files were identified as problematic in this iteration, exit the loop
                    if (!$retry_needed) {
                        error_log('LwsOptimize: Could not identify problematic JS file, aborting combination.');
                        return ['final_url' => false, 'problematic' => $problematic_files];
                    }
                }
            } while ($retry_needed && count($problematic_files) < count($scripts));
        }

        return ['final_url' => false, 'problematic' => $problematic_files];
    }

    /**
     * Minify all CSS links found in the $this->content page and return the page with the changes
     */
    public function minify_js()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <script> tags
        preg_match_all("/<script\s*.*?<\/script>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <script>, get their attributes and verify if we have to minify them
        // Then we minify it and replace the old URL by the minified one
        foreach ($elements as $element) {
            if (substr($element, 0, 7) == "<script") {
                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                $href = $href[1] ?? "";
                $href = trim($href);

                // Get the script ID if available
                preg_match("/id\=[\'\"]([^\'\"]+)[\'\"]/", $element, $id);
                $script_id = $id[1] ?? "";
                $script_id = trim($script_id);

                // Skip if ID contains bootstrap or jquery
                if (!empty($script_id) && (stripos($script_id, 'bootstrap') !== false || stripos($script_id, 'jquery') !== false)) {
                    continue;
                }

                if (empty($href)) {
                    continue;
                }

                if ($this->check_for_exclusion($href, "minify")) {
                    continue;
                }

                $name = base_convert(crc32($href), 20, 36);

                if (empty($name)) {
                    continue;
                }

                $file_path = $href;
                $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                $file_path = explode("?", $file_path)[0];

                // Handle protocol-relative URLs
                if (substr($file_path, 0, 2) === "//") {
                    $file_path = substr($file_path, 2);
                    // Add http: or https: based on site settings
                    $file_path = (is_ssl() ? 'https:' : 'http:') . '//' . $file_path;
                    $file_path = str_replace(get_site_url() . "/", ABSPATH, $file_path);
                }

                // Handle remote URLs
                if (strpos($file_path, 'http') === 0) {
                    $content = @file_get_contents($file_path);
                    if ($content === false) {
                        error_log('LwsOptimize: Could not fetch remote JS file for minification: ' . $file_path);
                        continue;
                    }

                    $temp_file = tempnam(sys_get_temp_dir(), 'lwsjs_');
                    file_put_contents($temp_file, $content);
                    $file_path = $temp_file;
                } else if (!file_exists($file_path)) {
                    error_log('LwsOptimize: JS file does not exist for minification: ' . $file_path);
                    continue;
                }

                $path = $GLOBALS['lws_optimize']->lwsop_get_content_directory("cache-js/$name.js");
                $path_url = str_replace(ABSPATH, get_site_url() . "/", $path);

                // Do not add into cache if the file already exists
                $add_cache = !file_exists($path);

                try {
                    $minify = new Minify\JS($file_path);

                    if ($minify->minify($path) && file_exists($path)) {
                        if ($add_cache) {
                            $this->files['file'] += 1;
                            $this->files['size'] += filesize($path) ?? 0;
                        }

                        // Create a new script tag with the newly minified URL
                        $newLink = preg_replace("/src\=[\'\"]([^\'\"]+)[\'\"]/", "src='$path_url'", $element);
                        $this->content = str_replace($element, $newLink, $this->content);
                    }

                    // Clean up temp file if it was created for a remote resource
                    if (isset($temp_file) && file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                } catch (\Exception $e) {
                    error_log('LwsOptimize JS Minification Error: ' . $e->getMessage() . ' for file: ' . $href);

                    // Clean up temp file if it was created for a remote resource
                    if (isset($temp_file) && file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                }
            }
        }

        return ['html' => $this->content, 'files' => $this->files];
    }

    /**
     * Add the defer attribute to all <script> tags found in the $this->content page and return the page with the changes
     */
    public function defer_js()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <script> tags
        preg_match_all("/<script\s*.*?<\/script>/xs", $this->content, $matches);

        $elements = $matches[0];
        // Loop through the <script>, get their attributes and verify if we have to defer them
        foreach ($elements as $element) {
            if (substr($element, 0, 7) == "<script") {
                // Check if the script already has defer or async attributes
                if (preg_match("/\s(defer|async)\s|(\s)(defer|async)([\s>])/i", $element)) {
                    continue; // Skip scripts that already have defer or async
                }

                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                $href = $href[1] ?? "";
                $href = trim($href);

                if (empty($href)) {
                    continue;
                }

                // Get the script ID if available
                preg_match("/id\=[\'\"]([^\'\"]+)[\'\"]/", $element, $id);
                $script_id = $id[1] ?? "";
                $script_id = trim($script_id);

                // Skip if ID contains bootstrap or jquery
                if (!empty($script_id) && (stripos($script_id, 'bootstrap') !== false || stripos($script_id, 'jquery') !== false)) {
                    continue;
                }

                if ($this->check_for_exclusion($href, "defer")) {
                    continue;
                }

                // Add defer attribute to the script tag
                $newElement = str_replace("<script", "<script defer", $element);
                $this->content = str_replace($element, $newElement, $this->content);
            }
        }

        return ['html' => $this->content, 'files' => $this->files];
    }

    /**
     * Stop scripts from being launched as long as the user has not made any action
     */
    public function delay_js_execution()
    {
        if (empty($this->content)) {
            return false;
        }

        // Get all <script> tags
        preg_match_all("/<script\s*.*?<\/script>/xs", $this->content, $matches);

        $elements = $matches[0];
        $has_delayed_scripts = false;

        // Array of scripts that should never be delayed
        $wp_core_scripts = [
            'jquery',
            'bootstrap',
        ];

        // Now process all scripts
        foreach ($elements as $element) {
            if (substr($element, 0, 7) == "<script") {
                // Check if it's an external script
                preg_match("/src\=[\'\"]([^\'\"]+)[\'\"]/", $element, $href);
                $src = $href[1] ?? "";
                $src = trim($src);

                // Extract script id if it exists
                $script_id = '';
                preg_match("/id\=[\'\"]([^\'\"]+)[\'\"]/", $element, $id_match);
                if (!empty($id_match[1])) {
                    $script_id = trim($id_match[1]);
                }

                // Skip scripts with IDs containing bootstrap or jquery
                if (!empty($script_id) && (stripos($script_id, 'bootstrap') !== false || stripos($script_id, 'jquery') !== false)) {
                    continue;
                }

                // Get any inline code
                preg_match("/<script[^>]*>(.*?)<\/script>/s", $element, $inline_code);
                $code = !empty($inline_code[1]) ? trim($inline_code[1]) : '';

                // Check if this script is a core script by ID or path that should not be delayed
                $is_core_script = false;
                if (!empty($script_id)) {
                    foreach ($wp_core_scripts as $core_script) {
                        if (strpos($script_id, $core_script) !== false) {
                            $is_core_script = true;
                            break;
                        }
                    }
                }

                // Skip core scripts
                if ($is_core_script) {
                    continue;
                }

                if (!empty($src) && !$this->check_for_exclusion($src, "delay")) {
                    // Handle external scripts with src attribute
                    $new_element = str_replace('src=', 'data-lwsdelay-src=', $element);

                    // Add class for script identification
                    $new_element = preg_match('/class=["\']([^"\']*)["\']/', $new_element)
                        ? preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lws-delay-script"', $new_element)
                        : str_replace('<script', '<script class="lws-delay-script"', $new_element);

                    $this->content = str_replace($element, $new_element, $this->content);
                    $has_delayed_scripts = true;
                }
                // Handle inline scripts without src attribute
                else if (!empty($code) && empty($src)) {
                    // Skip very small scripts or those likely to be configuration
                    if (strlen($code) < 50 && (strpos($code, 'var') === 0 || strpos($code, 'let') === 0 || strpos($code, 'const') === 0)) {
                        continue;
                    }

                    // Skip scripts that define ajax_var variables
                    if (strpos($code, 'ajax_var') !== false) {
                        continue;
                    }

                    // Skip jQuery document ready handlers and window load handlers as they already wait for DOM
                    if (
                        preg_match('/(jQuery|\\$)\\s*\\(\\s*document\\s*\\)\\s*\\.\\s*ready\\s*\\(/i', $code) ||
                        preg_match('/window\\s*\\.\\s*addEventListener\\s*\\(\\s*[\'"]load[\'"]/i', $code)
                    ) {
                        continue;
                    }

                    // Extract the script tag attributes
                    preg_match('/<script([^>]*)>/i', $element, $script_attrs);
                    $script_attributes = $script_attrs[1] ?? '';

                    // Create a new element with the script stored as a data attribute to avoid comment conflicts
                    $encoded_code = base64_encode($code);
                    $new_element = "<script" . $script_attributes . " class=\"lws-delay-script\" data-lwsdelay-inline=\"true\" data-lwsdelay-code=\"" .
                        $encoded_code . "\">/* LWS Delayed Inline Script */</script>";

                    $this->content = str_replace($element, $new_element, $this->content);
                    $has_delayed_scripts = true;
                }
            }
        }

        if (!$has_delayed_scripts) {
            return ['html' => $this->content, 'files' => $this->files];
        }

        // Build the delay script loader with improved sequential loading
        $delay_loader = "<script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function() {
            var scriptsActivated = false;
            var loadingQueue = [];
            var loadingInProgress = false;

            // Process next script in queue
            function processQueue() {
                if (loadingInProgress || loadingQueue.length === 0) {
                    return;
                }

                loadingInProgress = true;
                var nextScript = loadingQueue.shift();

                // Create the actual script element
                var newScript = document.createElement('script');
                // Copy attributes
                for (var i = 0; i < nextScript.attrs.length; i++) {
                    var attr = nextScript.attrs[i];
                    if (attr.name !== 'data-lwsdelay-src' &&
                        attr.name !== 'data-lwsdelay-inline' &&
                        attr.name !== 'data-lwsdelay-code' &&
                        attr.name !== 'class') {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                }

                // Set content or src
                if (nextScript.isInline) {
                    newScript.textContent = nextScript.content;
                    document.head.appendChild(newScript);
                    loadingInProgress = false;
                    setTimeout(processQueue, 0); // Process next immediately
                } else {
                    newScript.onload = newScript.onerror = function() {
                        loadingInProgress = false;
                        setTimeout(processQueue, 0); // Process next after load
                    };
                    newScript.src = nextScript.src;
                    document.head.appendChild(newScript);
                }
            }

            var activateScripts = function() {
                if (scriptsActivated) return;
                scriptsActivated = true;
                document.body.classList.add('scripts-loaded');

                // Queue all scripts
                document.querySelectorAll('.lws-delay-script').forEach(function(script) {
                    if (script.hasAttribute('data-lwsdelay-src')) {
                        // External script
                        loadingQueue.push({
                            isInline: false,
                            src: script.getAttribute('data-lwsdelay-src'),
                            attrs: script.attributes
                        });
                    } else if (script.hasAttribute('data-lwsdelay-inline')) {
                        // Inline script
                        var content = '';
                        if (script.hasAttribute('data-lwsdelay-code')) {
                            try {
                                // Decode the base64 content
                                content = atob(script.getAttribute('data-lwsdelay-code'));
                            } catch (e) {
                                console.error('LWS Optimize: Error decoding delayed script', e);
                            }
                        } else {
                            content = script.textContent;
                        }

                        loadingQueue.push({
                            isInline: true,
                            content: content,
                            attrs: script.attributes
                        });
                    }
                });

                // Start processing queue
                processQueue();
            };

            // Add event listeners with passive option for better performance
            setTimeout(function() {
                ['scroll', 'mousemove', 'touchstart', 'keydown', 'click'].forEach(function(e) {
                    document.addEventListener(e, activateScripts, {passive: true, once: true});
                });
                // Fallback: Load scripts after 5 seconds even without user interaction
                setTimeout(activateScripts, 5000);
            }, 100);
        });
        </script>";

        // Insert the loader script right before the closing </body> tag
        $this->content = str_replace('</body>', $delay_loader . "\n</body>", $this->content);

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
        $optimize_options = $GLOBALS['lws_optimize']->optimize_options;
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

        // Exclude jQuery and Bootstrap scripts
        if (str_contains(strtolower($url), "jquery")) {
            return true;
        }



        // Always exclude lazy load script to prevent conflicts
        if (strpos($url, 'lws_op_lazyload.js') !== false) {
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

        // If the URL is found in document.write(), ignore it
        preg_match_all("/(document.write\(\s*[^\)]*+\))/xs", $this->content, $matches);
        $writes = $matches[0] ? $matches[0] : [];
        foreach ($writes as $write) {
            if (preg_match("~$url~xs", $write)) {
                return true;
            }
        }

        // If the URL is found in a comment (including IE conditional comments), ignore it
        preg_match_all("/<!--(?:\[if[^\]]*\]>)?.*?(?:<!\[endif\])?-->/s", $this->content, $matches);
        $comments = $matches[0] ? $matches[0] : [];
        foreach ($comments as $comment) {
            if (preg_match("~" . preg_quote($url, "~") . "~is", $comment)) {
                return true;
            }
        }

        // Check for scripts inside conditional comments
        preg_match_all("/<!--\s*\[if[^\]]*\]>.*?<!\[endif\]-->/s", $this->content, $ie_matches);
        if (!empty($ie_matches[0])) {
            foreach ($ie_matches[0] as $conditional_block) {
                if (preg_match("~" . preg_quote($url, "~") . "~is", $conditional_block)) {
                    return true;
                }
            }
        }

        // Additional check: if the script is immediately inside an IE conditional comment
        $pos = strpos($this->content, $url);
        if ($pos !== false) {
            $surrounding = substr($this->content, max(0, $pos - 500), 1000);
            if (preg_match("/<!--\s*\[if[^\]]*\]>.*?" . preg_quote($url, '/') . ".*?<!\[endif\]-->/s", $surrounding)) {
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
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
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
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
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
        } elseif ($type == "defer") {
            $options_defer = $this->lwsop_check_option('defer_js');
            if ($options_defer['state'] == "true" && isset($options_defer['data']['exclusions'])) {
                $defer_js_exclusions = $options_defer['data']['exclusions'];
            } else {
                $defer_js_exclusions = [];
            }

            foreach ($defer_js_exclusions as $exclusion) {
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
                    return true;
                }
            }
        } elseif ($type == "delay") {
            $options_delay = $this->lwsop_check_option('delay_js');
            if ($options_delay['state'] == "true" && isset($options_delay['data']['exclusions'])) {
                $delay_js_exclusions = $options_delay['data']['exclusions'];
            } else {
                $delay_js_exclusions = [];
            }

            foreach ($delay_js_exclusions as $exclusion) {
                $pattern = preg_replace('/(?<!\\\)\*/', '.*', $exclusion);
                $regex_pattern = "#^" . str_replace('\.\*', '.*', preg_quote($pattern, '#')) . "$#";

                if (preg_match("$regex_pattern", $url)) {
                    return true;
                }
            }
        } else {
            $options_combine = $GLOBALS['lws_optimize']->optimize_options;
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
