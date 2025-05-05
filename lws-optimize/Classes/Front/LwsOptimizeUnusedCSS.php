<?php

namespace Lws\Classes\Front;

class LwsOptimizeUnusedCSS
{
    private $content;
    private $apiUrl = 'https://unusedcss.lwspanel.com/generate-unused-css';

    public function __construct($content)
    {
        $this->content = $content;
    }

    public function removeUnusedCSS($css)
    {
        $response = wp_remote_post(
            $this->apiUrl,
            [
                'body' => json_encode([
                    'html' => $this->content,
                    'css' => $css,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]
        );

        $path_to_cleaned = false;
        if (!is_wp_error($response) && isset($response['body'])) {
            $result = json_decode($response['body'], true);
            if (!empty($result['cleanedCss'])) {
                $path_to_cleaned = $result['cleanedCss'];
                // Create directory if it doesn't exist
                $upload_dir = wp_upload_dir();
                $cleaned_dir = $upload_dir['basedir'] . '/lwsoptimize/cleaned/';
                if (!file_exists($cleaned_dir)) {
                    wp_mkdir_p($cleaned_dir);
                }

                // Save cleaned CSS to file
                // Generate a unique filename based on CSS content
                $random_string = substr(md5($css), 0, 8);
                $cleaned_file = $cleaned_dir . 'cleaned_' . $random_string . '.css';

                if (filter_var($result['cleanedCss'], FILTER_VALIDATE_URL)) {
                    // If cleanedCss is a URL, fetch content from it
                    $cleaned_content = wp_remote_get($result['cleanedCss']);
                    if (!is_wp_error($cleaned_content) && $cleaned_content['response']['code'] === 200) {
                        file_put_contents($cleaned_file, wp_remote_retrieve_body($cleaned_content));
                        $path_to_cleaned = $cleaned_file;
                    }
                } else {
                    // If cleanedCss contains the actual CSS content
                    file_put_contents($cleaned_file, $result['cleanedCss']);
                    $path_to_cleaned = $cleaned_file;
                }
            }
        }

        return $path_to_cleaned;
    }


    public function applyCleanedCSS()
    {
        // Find all CSS links
        preg_match_all("/(<link[^>]*rel=['\"]stylesheet['\"][^>]*>)/is", $this->content, $css_links);

        if (!empty($css_links[0])) {
            foreach ($css_links[0] as $link) {
                // Skip if it's our critical CSS
                if (strpos($link, 'lws-critical-css') !== false) {
                    continue;
                }

                // Skip external files (not on our domain)
                $site_url = parse_url(site_url(), PHP_URL_HOST);
                if (preg_match('/href=[\'"]([^\'"]+)[\'"]/i', $link, $temp_href)) {
                    $link_host = parse_url($temp_href[1], PHP_URL_HOST);
                    if ($link_host && $link_host !== $site_url) {
                        continue;
                    }
                }

                preg_match('/href=[\'"]([^\'"]+)[\'"]/i', $link, $href_match);

                if (!empty($href_match[1])) {
                    $url = $href_match[1];


                    // Decode URL if it's encoded
                    $decoded_url = str_replace('\/', '/', $url);

                    // Fetch CSS content properly using wp_remote_get
                    $response = wp_remote_get($decoded_url);

                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                        $css = wp_remote_retrieve_body($response);

                        // Check if the CSS is empty
                        if (empty($css)) {
                            continue;
                        }

                        // Handle relative URLs in the CSS
                        $css_dir = dirname($decoded_url) . '/';
                        $site_url_base = site_url();

                        // Convert relative URLs to absolute
                        $css = preg_replace_callback(
                            '/url\s*\(\s*[\'"]?(?!data:|http|https:)([^\'")]+)[\'"]?\s*\)/i',
                            function($matches) use ($css_dir, $site_url_base) {
                                $relative_path = trim($matches[1]);

                                // If it starts with /, it's relative to the root
                                if (strpos($relative_path, '/') === 0) {
                                    return 'url(' . $site_url_base . $relative_path . ')';
                                }

                                // Otherwise, it's relative to the CSS file
                                return 'url(' . $css_dir . $relative_path . ')';
                            },
                            $css
                        );


                        // Process the CSS to remove unused parts
                        $filepath = $this->removeUnusedCSS($css);

                        // Check if a cleaned CSS file was generated
                        if ($filepath !== false) {
                            // Get the URL to the cleaned CSS file
                            $upload_dir = wp_upload_dir();
                            $cleaned_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath);

                            // Create new link with cleaned CSS URL
                            $new_link = str_replace($href_match[1], $cleaned_url, $link);

                            // Replace the old link with the new link in the page content
                            $this->content = str_replace($link, $new_link, $this->content);
                        }
                    }
                }
            }
        }

        // Find all style blocks
        preg_match_all("/<style([^>]*)>(.*?)<\/style>/is", $this->content, $styles, PREG_SET_ORDER);
        if (!empty($styles)) {
            foreach ($styles as $style) {
                // Get style tag content
                $style_content = $style[2];

                // Skip if it's empty
                if (empty($style_content)) {
                    continue;
                }

                // Process the inline CSS to remove unused parts
                $cleaned_style_path = $this->removeUnusedCSS($style_content);

                if ($cleaned_style_path !== false) {
                    // Read the cleaned CSS content
                    $cleaned_content = file_get_contents($cleaned_style_path);

                    // Create a new style tag with the cleaned content
                    $new_style = '<style' . $style[1] . '>' . $cleaned_content . '</style>';
                    // Replace the original style tag with the new one
                    $original_style = $style[0];
                    $this->content = str_replace($original_style, $new_style, $this->content);
                }
            }
        }

        return $this->content;
    }
}