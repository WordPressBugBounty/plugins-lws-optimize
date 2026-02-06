<?php

namespace Lws\Classes\Front;

class LwsOptimizeCriticalCSS
{
    private $content;
    private $apiUrl = 'https://criticalcss.lwspanel.com/generate-critical-css';
    private $all_css = '';

    public function __construct($content)
    {
        $this->content = $content;
        $this->fetchCSS();
    }

    /**
     * Get all CSS from the given content and return them as a single file
     */
    public function fetchCSS() {
        // Initialize for storing combined CSS
        $this->all_css = '';

        // Get all <link> stylesheets and <style> blocks in the order they appear
        preg_match_all("/(<link[^>]*rel=['\"]stylesheet['\"][^>]*>|<style[^>]*>.*?<\/style>)/is", $this->content, $css_elements);

        if (!empty($css_elements[0])) {
            $site_domain = wp_parse_url(site_url(), PHP_URL_HOST);

            foreach ($css_elements[0] as $element) {
                // Handle <style> tags
                if (strpos($element, '<style') !== false) {
                    preg_match("/<style[^>]*>(.*?)<\/style>/is", $element, $style_content);
                    if (!empty($style_content[1])) {
                        $this->all_css .= trim($style_content[1]) . "\n";
                    }
                }
                // Handle <link> tags
                elseif (strpos($element, '<link') !== false) {
                    preg_match("/href=['\"]([^'\"]+)['\"]/",$element, $href);
                    if (!empty($href[1])) {
                        $url = trim($href[1]);

                        // Check if CSS is from the same domain
                        $css_domain = wp_parse_url($url, PHP_URL_HOST);
                        if ($css_domain && $css_domain === $site_domain) {
                            $response = wp_remote_get($url);
                            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                                $this->all_css .= wp_remote_retrieve_body($response) . "\n";
                            }
                        }
                    }
                }
            }
        }

        return $this->all_css;
    }

    public function getCriticalCSS()
    {

        $url = isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']) ?
        (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : site_url();

        $response = wp_remote_post(
            $this->apiUrl,
            [
                'body' => json_encode([
                    'url' => $url,
                    'css' => $this->all_css,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]
        );

        $path_to_critical = false;
        if (!is_wp_error($response) && isset($response['body'])) {
            $result = json_decode($response['body'], true);
            if (!empty($result['criticalCss'])) {
                $path_to_critical = $result['criticalCss'];
                // Create directory if it doesn't exist
                $upload_dir = wp_upload_dir();
                $critical_dir = $upload_dir['basedir'] . '/lwsoptimize/critical/';
                if (!file_exists($critical_dir)) {
                    wp_mkdir_p($critical_dir);
                }

                // Save critical CSS to file
                $critical_file = $critical_dir . 'critical_' . md5($url) . '.css';

                if (filter_var($result['criticalCss'], FILTER_VALIDATE_URL)) {
                    // If criticalCss is a URL, fetch content from it
                    $critical_content = wp_remote_get($result['criticalCss']);
                    if (!is_wp_error($critical_content) && $critical_content['response']['code'] === 200) {
                        file_put_contents($critical_file, wp_remote_retrieve_body($critical_content));
                        $path_to_critical = $critical_file;
                    }
                } else {
                    // If criticalCss contains the actual CSS content
                    file_put_contents($critical_file, $result['criticalCss']);
                    $path_to_critical = $critical_file;
                }
            }
        }

        return $path_to_critical;
    }

    /**
     * Apply critical CSS to the page and defer loading of other CSS
     *
     * @return string Modified HTML content
     */
    public function applyCriticalCSS()
    {
        // Get path to critical CSS file
        $critical_css_path = $this->getCriticalCSS();

        if (!$critical_css_path) {
            return $this->content; // Return original content if critical CSS not found
        }

        // Check if file exists
        if (!file_exists($critical_css_path)) {
            return $this->content; // Return original content if file doesn't exist
        }

        // Get the URL to the critical CSS file
        $upload_dir = wp_upload_dir();
        $critical_css_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $critical_css_path);

        // Prepare critical CSS link
        $critical_css_link = "<link rel='stylesheet' id='lws-critical-css' href='{$critical_css_url}' media='all'>";

        // Extract all CSS links and inline styles
        $deferred_css = [];

        // Find all CSS links
        preg_match_all("/(<link[^>]*rel=['\"]stylesheet['\"][^>]*>)/is", $this->content, $css_links);
        if (!empty($css_links[0])) {
            foreach ($css_links[0] as $link) {
                // Skip if it's our critical CSS
                if (strpos($link, 'lws-critical-css') !== false) {
                    continue;
                }
                $deferred_css[] = $link;
            }
        }

        // Find all style blocks
        preg_match_all("/<style([^>]*)>(.*?)<\/style>/is", $this->content, $styles, PREG_SET_ORDER);
        if (!empty($styles)) {
            foreach ($styles as $style) {
                $deferred_css[] = $style[0];
            }
        }

        // Remove all original CSS links and styles
        $modified_content = preg_replace("/<link[^>]*rel=['\"]stylesheet['\"][^>]*>/is", '', $this->content);
        $modified_content = preg_replace("/<style[^>]*>.*?<\/style>/is", '', $modified_content);

        // Create a script to load deferred CSS after page load
        $deferred_script = "<script>
            (function() {
                var deferredCSS = " . json_encode($deferred_css) . ";

                function loadDeferredCSS() {
                    // Add all CSS back to the page
                    if (deferredCSS && deferredCSS.length) {
                        var fragment = document.createDocumentFragment();
                        var container = document.createElement('div');

                        for (var i = 0; i < deferredCSS.length; i++) {
                            container.innerHTML = deferredCSS[i];
                            while (container.firstChild) {
                                fragment.appendChild(container.firstChild);
                            }
                        }

                        document.head.appendChild(fragment);
                    }
                }

                if (window.addEventListener) {
                    window.addEventListener('load', loadDeferredCSS);
                } else if (window.attachEvent) {
                    window.attachEvent('onload', loadDeferredCSS);
                }
            })();
        </script>";

        // Insert critical CSS link at the beginning of head
        $modified_content = preg_replace("/(<head[^>]*>)/is", "$1" . $critical_css_link, $modified_content);

        // Add deferred loading script before </body>
        $modified_content = str_replace("</body>", $deferred_script . "</body>", $modified_content);

        return $modified_content;
    }
}