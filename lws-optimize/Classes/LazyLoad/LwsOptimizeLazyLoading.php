<?php

namespace Lws\Classes\LazyLoad;

class LwsOptimizeLazyLoading
{
    public static function startActionsImage()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'lws_optimize_manage_media_image_lazyload_js']);
        add_filter('the_content', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_images']);
        add_filter('wp_filter_content_tags', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_images']);
        add_filter('post_thumbnail_html', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_images']);
        add_action('template_redirect', [__CLASS__, 'lws_optimize_start_output_buffer_for_ll']);
    }

    public static function startActionsIframe()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'lws_optimize_manage_media_iframe_video_lazyload_js']);
        add_filter('the_content', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos']);
        add_filter('wp_filter_content_tags', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos']);
        add_filter('post_thumbnail_html', [__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos']);
        add_action('template_redirect', [__CLASS__, 'lws_optimize_start_output_buffer_for_ll_iframe_video']);
    }

    public static function lws_optimize_manage_media_image_lazyload_js()
    {
        wp_enqueue_script('lws-optimize-lazyload', LWS_OP_URL . 'js/lws_op_lazyload.js', array(), null, true);
    }

    /**
     * For most pages and content, this function is enough to replace all src="" by data-src=""
     * which will cause the website not to load the images. Coupled with a JS function
     * controlling which image to load (when they are above the fold), images not needed are not loaded at the start
     *
     * Additionally, check for classes and srcs excluded by the user, removing them from the lazy-loading
     */
    public static function lws_optimize_add_lazy_loading_attributes_to_images($content)
    {
        $optimize_options = $GLOBALS['lws_optimize']->optimize_options;

        $lazyload_options = $optimize_options['lazyload'] ?? [];
        $exclude_classes = $lazyload_options['exclusions']['css_classes'] ?? [];
        $exclude_filenames = $lazyload_options['exclusions']['img_iframe'] ?? [];

        // Add "rev-slidebg" to exclude_classes if not already present (RevSlider)
        if (!in_array('rev-slidebg', $exclude_classes)) {
            $exclude_classes[] = 'rev-slidebg';
        }

        // Regular expression to find all <img> tags in the content
        $content = preg_replace_callback(
            '/<img(?![^>]*data-src)([^>]+?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',

            function ($matches) use ($exclude_classes, $exclude_filenames) {
                $img_tag = $matches[0];
                $attributes = $matches[1] . $matches[4];
                $src = $matches[3];

                // Skip if data-src is already present
                if (strpos($img_tag, 'data-src') !== false) {
                    return $img_tag;
                }

                // Check excluded classes
                foreach ($exclude_classes as $class) {
                    if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes)) {
                        return $img_tag;
                    }
                }

                // Check excluded filenames
                foreach ($exclude_filenames as $filename) {
                    if (empty($filename)) {
                        continue;
                    }
                    if (strpos($src, $filename) !== false) {
                        return $img_tag;
                    }
                }

                // Get width and height if not present in attributes
                if (preg_match('/\bwidth=[\'"](\d+)[\'"]/', $attributes, $width_match)) {
                    $width = intval($width_match[1]);
                    if (!preg_match('/\bheight=[\'"][^\'"]*[\'"]/', $attributes)) {
                        if (strpos($src, '/wp-content/') === 0) {
                            $image_path = ABSPATH . $src;
                        } else {
                            $image_path = str_replace(site_url(), ABSPATH, $src);
                        }
                        if (file_exists($image_path)) {
                            $size = getimagesize($image_path);
                            if ($size) {
                                $ratio = $size[1] / $size[0];
                                $height = round($width * $ratio);
                                $attributes .= ' height="' . $height . '"';
                            }
                        }
                    }
                }

                // Append lazy load class
                $attributes = preg_match('/class=["\']([^"\']*)["\']/', $attributes)
                    ? preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lws-optimize-lazyload"', $attributes)
                    : $attributes . ' class="lws-optimize-lazyload"';

                // Return modified tag
                return '<img' . $attributes . ' data-src=' . $matches[2] . $src . $matches[2] . '>';
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
    public static function lws_optimize_start_output_buffer_for_ll()
    {
        if (is_front_page() || is_home()) {
            ob_start([__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_images']);
        }
    }


    public static function lws_optimize_manage_media_iframe_video_lazyload_js()
    {
        wp_enqueue_script('lws-optimize-lazyload', LWS_OP_URL . 'js/lws_op_lazyload.js', array(), null, true);
    }

    /**
     * For most pages and content, this function is enough to replace all src="" by data-src=""
     * which will cause the website not to load the images. Coupled with a JS function
     * controlling which image to load (when they are above the fold), images not needed are not loaded at the start
     *
     * Additionally, check for classes and srcs excluded by the user, removing them from the lazy-loading
     */
    public static function lws_optimize_add_lazy_loading_attributes_to_iframe_videos($content)
    {

        $optimize_options = $GLOBALS['lws_optimize']->optimize_options;

        $lazyload_options = $optimize_options['lazyload'] ?? [];
        $exclude_classes = $lazyload_options['exclusions']['css_classes'] ?? [];
        $exclude_filenames = $lazyload_options['exclusions']['img_iframe'] ?? [];

        // Modify <iframe> tags
        $content = preg_replace_callback(
            '/<iframe(?![^>]*data-src)([^>]+?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',
            function ($matches) use ($exclude_classes, $exclude_filenames) {
                $iframe_tag = $matches[0];
                $attributes = $matches[1] . $matches[4];
                $src = $matches[3];


                // Skip if data-src is already present
                if (strpos($attributes, 'data-src') !== false) {
                    return $iframe_tag;
                }

                // Check for excluded classes
                foreach ($exclude_classes as $class) {
                    if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes)) {
                        return $iframe_tag;
                    }
                }

                // Check for excluded filenames
                foreach ($exclude_filenames as $filename) {
                    if (strpos($src, $filename) !== false) {
                        return $iframe_tag;
                    }
                }

                // Append "lws-optimize-lazyload" to existing class or add it if class attribute doesn't exist
                $attributes = preg_match('/class=["\']([^"\']*)["\']/', $attributes)
                    ? preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lws-optimize-lazyload"', $attributes)
                    : $attributes . ' class="lws-optimize-lazyload"';

                // Modify <iframe> tag
                return '<iframe' . $attributes . ' data-src=' . $matches[2] . $src . $matches[2] . '>';
            },
            $content
        );

        // Modify <video> tags
        $content = preg_replace_callback(
            '/<video(?![^>]*data-src)([^>]*?)>/i',
            function ($matches) use ($exclude_classes) {
                $video_tag = $matches[0];
                $attributes = $matches[1];

                // Check for excluded classes
                foreach ($exclude_classes as $class) {
                    if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes)) {
                        return $video_tag;
                    }
                }

                // Append "lws-optimize-lazyload" to existing class or add it if class attribute doesn't exist
                $attributes = preg_match('/class=["\']([^"\']*)["\']/', $attributes)
                    ? preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lws-optimize-lazyload"', $attributes)
                    : $attributes . ' class="lws-optimize-lazyload"';

                // Modify <video> tag to add lazyload class
                return '<video' . $attributes . '>';
            },
            $content
        );

        // Modify <source> tags inside <video>
        $content = preg_replace_callback(
            '/<source(?![^>]*data-src)([^>]*?)\s+src=([\'"])([^\'"]+)\2([^>]*?)>/i',
            function ($matches) use ($exclude_filenames) {
                $source_tag = $matches[0];
                $attributes = $matches[1] . $matches[4];
                $src = $matches[3];

                // Check for excluded filenames
                foreach ($exclude_filenames as $filename) {
                    if (strpos($src, $filename) !== false) {
                        return $source_tag;
                    }
                }

                // Modify <source> tag to add data-src
                return '<source' . $attributes . ' data-src=' . $matches[2] . $src . $matches[2] . '>';
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
    public static function lws_optimize_start_output_buffer_for_ll_iframe_video()
    {
        if (is_front_page() || is_home()) {
            ob_start([__CLASS__, 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos']);
        }
    }
}
