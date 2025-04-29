<?php

namespace Lws\Classes\Images;

class LwsOptimizeImageFrontManager
{
    public static function startImageWidth()
    {
        add_filter('the_content', [__CLASS__, 'lws_optimize_add_back_sizes_images'], 8);
        add_filter('wp_filter_content_tags', [__CLASS__, 'lws_optimize_add_back_sizes_images'], 8);
        add_filter('post_thumbnail_html', [__CLASS__, 'lws_optimize_add_back_sizes_images'], 8);
        add_action('template_redirect', [__CLASS__, 'lws_optimize_start_output_buffer_for_images'], 8);
    }

    public static function lws_optimize_add_back_sizes_images($content)
    {
        // Regular expression to find all <img> tags in the content
        $content = preg_replace_callback(
            '/<img\s*(([^>]*?)src=([\'"])([^\'"]+)\3([^>]*?))>/i',
            function ($matches) {
                $attributes = $matches[1];
                $src = $matches[4];
                $img_tag = '<img ' . $attributes . '>';

                // If both width and height are already present, no need to modify
                if (
                    preg_match('/\bwidth=[\'"][^\'"]*[\'"]/', $attributes) &&
                    preg_match('/\bheight=[\'"][^\'"]*[\'"]/', $attributes)
                ) {
                    return $img_tag;
                }

                // Determine the local path of the image
                if (strpos($src, '/wp-content/') === 0) {
                    // Relative path starting with /wp-content/
                    $image_path = ABSPATH . substr($src, 1); // Remove leading slash
                } elseif (strpos($src, 'http') === 0) {
                    // Absolute URL
                    $site_url = site_url();
                    if (strpos($src, $site_url) === 0) {
                        // URL from same site
                        $image_path = str_replace($site_url, rtrim(ABSPATH, '/'), $src);
                    } else {
                        // External URL - can't process
                        return $img_tag;
                    }
                } else {
                    // Other relative path
                    $image_path = ABSPATH . ltrim($src, '/');
                }

                // Get image dimensions
                if (file_exists($image_path) && $size = getimagesize($image_path)) {
                    $width = $size[0];
                    $height = $size[1];

                    // Add width if not present
                    if (!preg_match('/\bwidth=[\'"][^\'"]*[\'"]/', $attributes)) {
                        $attributes .= ' width="' . $width . '"';
                    }

                    // Add height if not present
                    if (!preg_match('/\bheight=[\'"][^\'"]*[\'"]/', $attributes)) {
                        $attributes .= ' height="' . $height . '"';
                    }

                    return '<img ' . $attributes . '>';
                }

                // Return original tag if we couldn't modify it
                return $img_tag;
            },
            $content
        );

        return $content;
    }

    /**
     * On the homepage of most WordPress websites, the other function will not work natively
     * as images are loaded differently, generally by the theme.
     * As such we are forced to use ob_start to get and replace images
     */
    public static function lws_optimize_start_output_buffer_for_images()
    {
        if (is_front_page() || is_home()) {
            ob_start([__CLASS__, 'lws_optimize_add_back_sizes_images']);
        }
    }
}
