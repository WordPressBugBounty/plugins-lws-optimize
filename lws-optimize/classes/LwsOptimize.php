<?php

class LwsOptimize
{
    public $optimize_options;
    public $lwsOptimizeCache;
    public $state;
    public $lwsImageOptimization;

    public function __construct()
    {

        define('LWSOP_OBJECTCACHE_PATH', WP_CONTENT_DIR . '/object-cache.php');

        // Get all the options for LWSOptimize. If none are found (first start, erased from DB), recreate the array
        $this->optimize_options = get_option('lws_optimize_config_array', null);
        if ($this->optimize_options === null) {
            $this->optimize_options = [
                'filebased_cache' => ['state' => "true", "preload" => "true", "preload_amount" => "5"],
                'autopurge' => ['state' => "true"],
                'cache_logged_user' => ['state' => "true"],
            ];
            update_option('lws_optimize_config_array', $this->optimize_options);

            if (!wp_next_scheduled("lws_optimize_start_filebased_preload") && $this->lwsop_check_option('filebased_cache')['state'] === "true") {
                wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
            }
        }

        // If Memcached is activated but there is no object-cache.php, add it back
        if ($this->lwsop_check_option('memcached')['state'] === "true") {
            // Deactivate Memcached if Redis is activated
            if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                $this->optimize_options['memcached']['state'] = "false";
            } else {
                if (class_exists('Memcached')) {
                    $memcached = new Memcached();
                    if (empty($memcached->getServerList())) {
                        $memcached->addServer('localhost', 11211);
                    }

                    if ($memcached->getVersion() === false) {
                        $this->optimize_options['memcached']['state'] = "false";
                        if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                            unlink(LWSOP_OBJECTCACHE_PATH);
                        }
                    } else {
                        if (!file_exists(LWSOP_OBJECTCACHE_PATH)) {
                            file_put_contents(LWSOP_OBJECTCACHE_PATH, file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
                        }
                    }
                } else {
                    $this->optimize_options['memcached']['state'] = "false";
                    if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                        var_dump("no_class");
                        unlink(LWSOP_OBJECTCACHE_PATH);
                    }
                }
            }
        } else {
            if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                unlink(LWSOP_OBJECTCACHE_PATH);
            }
        }

        // Whether the plugin should work or not. While deactivated, only PageSpeed works
        $this->state = get_option('lws_optimize_offline', false);

        if (!$this->state) {
            if ($this->lwsop_check_option('gzip_compression')['state'] === "true") {
                $gzip_ok = false;
                if (is_file(ABSPATH . '.htaccess')) {
                    $f = file(ABSPATH . '.htaccess');
                    foreach ($f as $line) {
                        if (trim($line) == '#LWS OPTIMIZE - GZIP COMPRESSION') {
                            $gzip_ok = true;
                            break;
                        }
                    }
                }

                if (!$gzip_ok) {
                    exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - GZIP COMPRESSION/,/#END LWS OPTIMIZE - GZIP COMPRESSION/ d' '" . escapeshellcmd(ABSPATH) . "/.htaccess'", $eOut, $eCode);
                    $htaccess = ABSPATH . "/.htaccess";

                    $hta = '';
                    $hta .= "<IfModule mod_deflate.c>\n";
                    $hta .= "SetOutputFilter DEFLATE\n";


                    // Add all the types of files to compress
                    $hta .= "<IfModule mod_filter.c>\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/javascript\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/json\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/rss+xml\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/xml\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/atom+xml\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/vnd.ms-fontobject\n";
                    $hta .= "AddOutputFilterByType DEFLATE application/x-font-ttf\n";
                    $hta .= "AddOutputFilterByType DEFLATE font/opentype\n";
                    $hta .= "AddOutputFilterByType DEFLATE text/plain\n";
                    $hta .= "AddOutputFilterByType DEFLATE text/pxml\n";
                    $hta .= "AddOutputFilterByType DEFLATE text/html\n";
                    $hta .= "AddOutputFilterByType DEFLATE text/css\n";
                    $hta .= "AddOutputFilterByType DEFLATE text/x-component\n";
                    $hta .= "AddOutputFilterByType DEFLATE image/svg+xml\n";
                    $hta .= "AddOutputFilterByType DEFLATE image/x-icon\n";
                    $hta .= "</IfModule>\n";
                    $hta .= "</IfModule>\n";

                    if ($hta != '') {
                        $hta =
                            "#LWS OPTIMIZE - GZIP COMPRESSION\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n $hta #END LWS OPTIMIZE - GZIP COMPRESSION\n";

                        if (is_file($htaccess)) {
                            $hta .= file_get_contents($htaccess);
                        }

                        if (($f = fopen($htaccess, 'w+')) !== false) {
                            if (!fwrite($f, $hta)) {
                                fclose($f);
                                error_log(json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | GZIP | .htaccess file is not writtable")));
                            } else {
                                fclose($f);
                            }
                        } else {
                            error_log(json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | GZIP | .htaccess file is not openable")));
                        }
                    }
                }
            }

            // If the lazyloading of images has been activated on the website
            if ($this->lwsop_check_option('image_lazyload')['state'] === "true") {

                add_action('wp_enqueue_scripts', 'lws_optimize_manage_media_image_lazyload_js');
                function lws_optimize_manage_media_image_lazyload_js()
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
                function lws_optimize_add_lazy_loading_attributes_to_images($content)
                {

                    $optimize_options = get_option('lws_optimize_config_array', []);

                    $lazyload_options = $optimize_options['lazyload'] ?? [];
                    $exclude_classes = $lazyload_options['exclusions']['css_classes'] ?? [];
                    $exclude_filenames = $lazyload_options['exclusions']['img_iframe'] ?? [];

                    // Regular expression to find all <img> tags in the content
                    $content = preg_replace_callback(
                        '/<img([^>]+?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',

                        function ($matches) use ($exclude_classes, $exclude_filenames) {
                            $img_tag = $matches[0];
                            $attributes = $matches[1] . $matches[4]; // All attributes of <img> tag
                            $src = $matches[3]; // The image source URL

                            // Skip if data-src is already present
                            if ( strpos($attributes, 'data-src') !== false ) {
                                return $img_tag;
                            }

                            // Check if the image has any of the excluded classes using regex
                            foreach ($exclude_classes as $class) {
                                if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes)) {
                                    return $img_tag; // Skip modification if excluded class is found
                                }
                            }

                            // Check if the image source filename matches any excluded filenames
                            foreach ($exclude_filenames as $filename) {
                                if (empty($filename)) {
                                    continue;
                                }
                                if (strpos($src, $filename) !== false) {
                                    return $img_tag; // Skip modification if excluded filename is found
                                }
                            }

                            // Modify the <img> tag: replace src with data-src and add lazyload class
                            return '<img' . $matches[1] . 'data-src=' . $matches[2] . $src . $matches[2] . $matches[4] . ' class="lws-optimize-lazyload">';
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
                function lws_optimize_start_output_buffer_for_ll()
                {
                    if (is_front_page() || is_home()) {
                        ob_start('lws_optimize_add_lazy_loading_attributes_to_images');
                    }
                }

                add_filter('the_content', 'lws_optimize_add_lazy_loading_attributes_to_images');
                add_filter('wp_filter_content_tags', 'lws_optimize_add_lazy_loading_attributes_to_images');
                add_filter('post_thumbnail_html', 'lws_optimize_add_lazy_loading_attributes_to_images');
                add_action('template_redirect', 'lws_optimize_start_output_buffer_for_ll');
            } else {
                function lws_optimize_add_native_lazy_loading_to_images($content)
                {
                    // Add loading="lazy" to all <img> tags that don't already have it
                    $content = preg_replace_callback(
                        '/<img(?![^>]*loading=[\'"]lazy[\'"])([^>]*)>/i',
                        function ($matches) {
                            return '<img loading="lazy"' . $matches[1] . '>';
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
                function lws_optimize_start_output_buffer_for_native_ll()
                {
                    if (is_front_page() || is_home()) {
                        ob_start('lws_optimize_add_native_lazy_loading_to_images');
                    }
                }

                add_filter('the_content', 'lws_optimize_add_native_lazy_loading_to_images');
                add_filter('wp_filter_content_tags', 'lws_optimize_add_native_lazy_loading_to_images');
                add_filter('post_thumbnail_html', 'lws_optimize_add_native_lazy_loading_to_images');
                add_action('template_redirect', 'lws_optimize_start_output_buffer_for_native_ll');
            }

            // If the lazyloading of images has been activated on the website
            if ($this->lwsop_check_option('iframe_video_lazyload')['state'] === "true") {

                add_action('wp_enqueue_scripts', 'lws_optimize_manage_media_iframe_video_lazyload_js');
                function lws_optimize_manage_media_iframe_video_lazyload_js()
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
                function lws_optimize_add_lazy_loading_attributes_to_iframe_videos($content)
                {

                    $optimize_options = get_option('lws_optimize_config_array', []);

                    $lazyload_options = $optimize_options['lazyload'] ?? [];
                    $exclude_classes = $lazyload_options['exclusions']['css_classes'] ?? [];
                    $exclude_filenames = $lazyload_options['exclusions']['img_iframe'] ?? [];

                    // Modify <iframe> tags
                    $content = preg_replace_callback(
                        '/<iframe([^>]+?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',
                        function ( $matches ) use ( $exclude_classes, $exclude_filenames ) {
                            $iframe_tag = $matches[0];
                            $attributes = $matches[1] . $matches[4];
                            $src = $matches[3];


                            // Skip if data-src is already present
                            if ( strpos($attributes, 'data-src') !== false ) {
                                return $iframe_tag;
                            }

                            // Check for excluded classes
                            foreach ( $exclude_classes as $class ) {
                                if ( preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes) ) {
                                    return $iframe_tag;
                                }
                            }

                            // Check for excluded filenames
                            foreach ( $exclude_filenames as $filename ) {
                                if ( strpos( $src, $filename ) !== false ) {
                                    return $iframe_tag;
                                }
                            }

                            // Modify <iframe> tag
                            return '<iframe' . $matches[1] . 'data-src=' . $matches[2] . $src . $matches[2] . $matches[4] . ' class="lazyload"></iframe>';
                        },
                        $content
                    );

                    // Modify <video> tags
                    $content = preg_replace_callback(
                        '/<video([^>]*?)>/i',
                        function ( $matches ) use ( $exclude_classes ) {
                            $video_tag = $matches[0];
                            $attributes = $matches[1];

                            // Skip if data-src is already present in <video>
                            if ( strpos($attributes, 'data-src') !== false ) {
                                return $video_tag;
                            }

                            // Check for excluded classes
                            foreach ( $exclude_classes as $class ) {
                                if ( preg_match('/\b' . preg_quote($class, '/') . '\b/', $attributes) ) {
                                    return $video_tag;
                                }
                            }

                            // Modify <video> tag to add lazyload class
                            return '<video' . $attributes . ' class="lazyload">';
                        },
                        $content
                    );

                    // Modify <source> tags inside <video> tags
                    $content = preg_replace_callback(
                        '/<video[^>]*>(.*?)<\/video>/is',
                        function ( $matches ) use ( $exclude_filenames ) {
                            $video_content = $matches[1];

                            // Replace src with data-src in each <source> tag within <video>
                            $video_content = preg_replace_callback(
                                '/<source([^>]+?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',
                                function ( $source_matches ) use ( $exclude_filenames ) {
                                    $source_tag = $source_matches[0];
                                    $attributes = $source_matches[1] . $source_matches[4];
                                    $src = $source_matches[3];

                                    // Skip if data-src is already present
                                    if ( strpos($attributes, 'data-src') !== false ) {
                                        return $source_tag;
                                    }

                                    // Check for excluded filenames
                                    foreach ( $exclude_filenames as $filename ) {
                                        if ( strpos( $src, $filename ) !== false ) {
                                            return $source_tag;
                                        }
                                    }

                                    // Modify <source> tag
                                    return '<source' . $source_matches[1] . 'data-src=' . $source_matches[2] . $src . $source_matches[2] . $source_matches[4] . '>';
                                },
                                $video_content
                            );

                            // Recreate the <video> tag with modified <source> tags
                            return '<video' . $matches[1] . ' class="lazyload">' . $video_content . '</video>';
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
                function lws_optimize_start_output_buffer_for_ll_iframe_video()
                {
                    if (is_front_page() || is_home()) {
                        ob_start('lws_optimize_add_lazy_loading_attributes_to_iframe_videos');
                    }
                }

                add_filter('the_content', 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos');
                add_filter('wp_filter_content_tags', 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos');
                add_filter('post_thumbnail_html', 'lws_optimize_add_lazy_loading_attributes_to_iframe_videos');
                add_action('template_redirect', 'lws_optimize_start_output_buffer_for_ll_iframe_video');
            } else {
                function lws_optimize_add_native_lazy_loading_to_iframe_video($content)
                {

                    // Modify <iframe> tags to add loading="lazy"
                    $content = preg_replace_callback(
                        '/<iframe([^>]*?)src=([\'"])([^\'"]+)\2([^>]*?)>/i',
                        function ( $matches ) {
                            $iframe_tag = $matches[0];
                            $attributes = $matches[1] . $matches[4];
                            $src = $matches[3];

                            // Skip if data-src is already present
                            if ( strpos($attributes, 'data-src') !== false ) {
                                return $iframe_tag;
                            }

                            // Add loading="lazy" to <iframe> tag if not excluded
                            return '<iframe' . $matches[1] . 'src=' . $matches[2] . $src . $matches[2] . $matches[4] . ' loading="lazy">';
                        },
                        $content
                    );

                    // Modify <video> tags to add loading="lazy"
                    $content = preg_replace_callback(
                        '/<video([^>]*?)>/i',
                        function ( $matches ) {
                            $video_tag = $matches[0];
                            $attributes = $matches[1];

                            // Skip if data-src is already present in <video>
                            if ( strpos($attributes, 'data-src') !== false ) {
                                return $video_tag;
                            }

                            // Add loading="lazy" to <video> tag if not excluded
                            return '<video' . $attributes . ' loading="lazy">';
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
                function lws_optimize_start_output_buffer_for_native_ll_iframe_video()
                {
                    if (is_front_page() || is_home()) {
                        ob_start('lws_optimize_add_native_lazy_loading_to_iframe_video');
                    }
                }

                add_filter('the_content', 'lws_optimize_add_native_lazy_loading_to_iframe_video');
                add_filter('wp_filter_content_tags', 'lws_optimize_add_native_lazy_loading_to_iframe_video');
                add_filter('post_thumbnail_html', 'lws_optimize_add_native_lazy_loading_to_iframe_video');
                add_action('template_redirect', 'lws_optimize_start_output_buffer_for_native_ll_iframe_video');
            }

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });

            if (!function_exists("is_user_logged_in")) {
                include_once ABSPATH . "/wp-includes/pluggable.php";
            }

            if (is_admin_bar_showing()) {
                add_action('admin_bar_menu', [$this, 'lws_optimize_admin_bar'], 300);
                add_action('admin_footer', [$this, 'lws_optimize_admin_footer_scripts'], 300);
                add_action('wp_footer', [$this, 'lws_optimize_wp_footer_scripts'], 300);

                // Customize the CSS of the admin_bar
                add_action('admin_head', [$this, 'lwsop_admin_bar_change_css']);
                add_action('wp_head', [$this, 'lwsop_admin_bar_change_css']);
            }

            add_filter('lws_optimize_clear_filebased_cache', [$this, 'lws_optimize_clean_filebased_cache']);
            add_action('lws_optimize_start_filebased_preload', [$this, 'lws_optimize_start_filebased_preload']);

            if ($this->lwsop_check_option("maintenance_db")['state'] == "true" && !wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_schedule_event(time(), 'weekly', 'lws_optimize_maintenance_db_weekly');
            }

            // Add new schedules time for crons
            add_filter('cron_schedules', 'lws_optimize_timestamp_crons');
            function lws_optimize_timestamp_crons($schedules)
            {
                $schedules['lws_three_minutes'] = array(
                    'interval' => 120,
                    'display' => __('Every 2 Minutes')
                );

                return $schedules;
            }

            // If the auto-convert feature is activated, then prepare the hooks
            if ($this->lwsop_check_option("auto_update")['state'] == "true") {
                require_once dirname(__DIR__) . "/classes/ImageOptimization.php";
                $this->lwsImageOptimization = new Lws_Optimize_ImageOptimization(true);
            } else {
                require_once dirname(__DIR__) . "/classes/ImageOptimization.php";
                $this->lwsImageOptimization = new Lws_Optimize_ImageOptimization(false);
            }

            // Optimize all images to the designed MIME-Type
            add_filter('lws_optimize_convert_media_cron', [$this, 'lws_optimize_convert_media_cron']);

            // Stop or Revert the convertion of all medias
            add_filter('wp_ajax_lwsop_stop_convertion', [$this, 'lws_optimize_stop_convertion']);
            add_filter('wp_ajax_lwsop_stop_deconvertion', [$this, 'lws_optimize_stop_deconvertion']);
            add_action("wp_ajax_lws_optimize_revert_convertion", [$this, "lws_optimize_revert_convertion"]);
            add_action("lwsop_revertOptimization", [$this, "lwsop_revertOptimization"]);

            // Launch the weekly DB cleanup
            add_action("lws_optimize_maintenance_db_weekly", [$this, "lws_optimize_create_maintenance_db_options"]);
            add_action("wp_ajax_lws_optimize_set_maintenance_db_options", [$this, "lws_optimize_set_maintenance_db_options"]);
            add_action("wp_ajax_lws_optimize_get_maintenance_db_options", [$this, "lws_optimize_manage_maintenance_get"]);

            add_action('wp_ajax_lwsop_change_optimize_configuration', [$this, "lwsop_get_setup_optimize"]);

            include_once "FileCache.php";
            $this->lwsOptimizeCache = new FileCache($this);
        }

        add_action('init', [$this, "lws_optimize_init"]);
        add_action("wp_ajax_lws_optimize_do_pagespeed", [$this, "lwsop_do_pagespeed_test"]);

        // If the autopurge has been activated, add hooks that will clear specific cache on specific actions
        if (!$this->state && $this->lwsop_check_option("autopurge")['state'] == "true") {
            // // Remove the cache for the page where the modified comment is located
            add_action('wp_insert_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
            add_action('deleted_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
            add_action('trashed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
            add_action('spammed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
            add_action('unspammed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
            add_action('untrashed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);

            // // Remove the cache on the page currently getting modified/deleted
            add_action('wp_insert_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
            add_action('edit_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
            add_action('save_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);

            add_action('deleted_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
            add_action('trashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
            add_action('spammed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
            add_action('unspammed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
            add_action('untrashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);

            add_action('woocommerce_add_to_cart', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
            add_action('woocommerce_cart_item_removed', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
            add_action('woocommerce_cart_item_restored', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
            add_action('woocommerce_after_cart_item_quantity_update', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
        }

        if (is_admin()) {
            // Only add thoses actions if the plugin is activated. It means that even if users modify the page content, actions will not launch
            if (!$this->state) {
                // Change configuration state for the differents element of LWSOptimize
                add_action("wp_ajax_lws_optimize_checkboxes_action", [$this, "lws_optimize_manage_config"]);
                add_action("wp_ajax_lws_optimize_exclusions_changes_action", [$this, "lws_optimize_manage_exclusions"]);
                add_action("wp_ajax_lws_optimize_exclusions_media_changes_action", [$this, "lws_optimize_manage_exclusions_media"]);
                add_action("wp_ajax_lws_optimize_fetch_exclusions_action", [$this, "lws_optimize_fetch_exclusions"]);
                // Activate the "preload" option for the file-based cache
                add_action("wp_ajax_lwsop_start_preload_fb", [$this, "lwsop_preload_fb"]);
                add_action("wp_ajax_lwsop_change_preload_amount", [$this, "lwsop_change_preload_amount"]);

                add_action("wp_ajax_lwsop_convert_all_images", [$this, "lwsop_convert_all_media"]);

                // Fetch an array containing every URLs that should get purged each time an autopurge starts
                add_action("wp_ajax_lwsop_get_specified_url", [$this, "lwsop_specified_urls_fb"]);
                // Update the specified-URLs array
                add_action("wp_ajax_lwsop_save_specified_url", [$this, "lwsop_save_specified_urls_fb"]);
                // Fetch an array containing every URLs that should not be cached
                add_action("wp_ajax_lwsop_get_excluded_url", [$this, "lwsop_exclude_urls_fb"]);
                // Update the excluded-URLs array
                add_action("wp_ajax_lwsop_save_excluded_url", [$this, "lwsop_save_urls_fb"]);

                // Get or set the URLs that should get preloaded on the website
                add_action("wp_ajax_lws_optimize_add_url_to_preload", [$this, "lwsop_get_url_preload"]);
                add_action("wp_ajax_lws_optimize_set_url_to_preload", [$this, "lwsop_set_url_preload"]);

                // Get or set the URLs to the fonts that should get preloaded on the website
                add_action("wp_ajax_lws_optimize_add_font_to_preload", [$this, "lwsop_get_url_preload_font"]);
                add_action("wp_ajax_lws_optimize_set_url_to_preload_font", [$this, "lwsop_set_url_preload_font"]);

                // Reload the stats of the filebased cache
                add_action("wp_ajax_lwsop_reload_stats", [$this, "lwsop_reload_stats"]);

                // Get when the next database maintenance will happen
                add_action("wp_ajax_lws_optimize_get_database_cleaning_time", [$this, "lws_optimize_get_database_cleaning_time"]);

                // Activate or deactivate the auto-convertion of upload medias
                add_action("wp_ajax_lwsop_autoconvert_all_images_activate", [$this, "lwsop_start_autoconvert_media"]);
                add_action("wp_ajax_lwsop_stop_autoconvertion", [$this, "lwsop_stop_autoconvertion"]);

                add_action("wp_ajax_lwsop_check_convert_images_update", [$this, "lwsop_check_convert_images_update"]);
            }

            if (!$this->state && isset($this->lwsop_check_option('filebased_cache')['data']['preload']) && $this->lwsop_check_option('filebased_cache')['data']['preload'] === "true") {
                add_action("wp_ajax_lwsop_check_preload_update", [$this, "lwsop_check_preload_update"]);
            }

            add_action("wp_ajax_lws_clear_fb_cache", [$this, "lws_optimize_clear_cache"]);
            add_action("wp_ajax_lws_clear_html_fb_cache", [$this, "lws_optimize_clear_htmlcache"]);
            add_action("wp_ajax_lws_clear_style_fb_cache", [$this, "lws_optimize_clear_stylecache"]);
            add_action("wp_ajax_lws_clear_currentpage_fb_cache", [$this, "lws_optimize_clear_currentcache"]);


            add_action("wp_ajax_lws_optimize_fb_cache_change_status", [$this, "lws_optimize_set_fb_status"]);
            add_action("wp_ajax_lws_optimize_fb_cache_change_cache_time", [$this, "lws_optimize_set_fb_timer"]);
        } else {
            // If LWSOptimize is off or the cache has been deactivated, do not start the caching process
            if (!$this->state && $this->lwsop_check_option('filebased_cache')['state'] === "true") {
                $this->lwsOptimizeCache->lwsop_launch_cache();
            }
        }
    }

    /**
     * Initial setup of the plugin ; execute all basic actions
     */
    public function lws_optimize_init()
    {        
        load_plugin_textdomain('lws-optimize', false, dirname(LWS_OP_BASENAME) . '/languages');

        if (! function_exists('wp_crop_image')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Schedule the cache cleanout again if it has been deleted
        // If the plugin is OFF or the filecached is deactivated, unregister the WPCron
        if (isset($this->optimize_options['filebased_cache']['timer']) && !$this->state) {
            if (!wp_next_scheduled('lws_optimize_clear_filebased_cache') && $this->optimize_options['filebased_cache']['timer'] != 0) {
                wp_schedule_event(time(), $this->optimize_options['filebased_cache']['timer'], 'lws_optimize_clear_filebased_cache');
            }
        } elseif ($this->state || $this->lwsop_check_option('filebased_cache')['state'] === "false") {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
        }
    }

    public function lwsop_admin_bar_change_css() { 
        if ( is_admin_bar_showing() ) : ?>
           <style type="text/css">
                ul#wp-admin-bar-lwsop-manage-cache-default div.ab-item {
                    cursor: pointer;
                }
              /* add your style here */
           </style>
     
        <?php endif;
    }

    /**
     * Add the "LWSOptimize" adminbar when connected
     */
    public function lws_optimize_admin_bar(WP_Admin_Bar $wP_Admin_Bar)
    {
        $wP_Admin_Bar->add_menu(
            [
                'id' => "lwsop-manage-cache",
                'parent' => null,
                'href' => admin_url("admin.php?page=lws-op-config"),
                'title' => __('LWSOptimize', 'lws-optimize')
            ]
        );
        $wP_Admin_Bar->add_menu(
            [
                'id' => "lwsop-clear-cache",
                'parent' => "lwsop-manage-cache",
                'title' => __('Clear all cache', 'lws-optimize')
            ]
        );
        $wP_Admin_Bar->add_menu(
            [
                'id' => "lwsop-clear-htmlcache",
                'parent' => "lwsop-manage-cache",
                'title' => __('Clear all HTML cache files', 'lws-optimize')
            ]
        );
        $wP_Admin_Bar->add_menu(
            [
                'id' => "lwsop-clear-subcache",
                'parent' => "lwsop-manage-cache",
                'title' => __('Clear all JS and CSS cache files', 'lws-optimize')
            ]
        );

        if (!is_admin()) {
            $wP_Admin_Bar->add_menu(
                [
                    'id' => "lwsop-clear-current-cache",
                    'parent' => "lwsop-manage-cache",
                    'title' => __('Clear current page cache files', 'lws-optimize')
                ]
            );
        }
    }

    public function lws_optimize_admin_footer_scripts()
    { ?>
        <script>
            if (document.getElementById("wp-admin-bar-lwsop-clear-cache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-cache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: "POST",
                        dataType: 'json',
                        timeout: 120000,
                        context: document.body,
                        data: {
                            action: "lws_clear_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
            if (document.getElementById("wp-admin-bar-lwsop-clear-subcache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-subcache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: "POST",
                        dataType: 'json',
                        timeout: 120000,
                        context: document.body,
                        data: {
                            action: "lws_clear_style_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_style_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
            if (document.getElementById("wp-admin-bar-lwsop-clear-htmlcache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-htmlcache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: ajaxurl,
                        dataType: 'json',
                        type: "POST",
                        timeout: 120000,
                        context: document.body,
                        data: {
                            action: "lws_clear_html_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_html_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
        </script>
    <?php
    }

    public function lws_optimize_wp_footer_scripts()
    { ?>
        <script>
            if (document.getElementById("wp-admin-bar-lwsop-clear-cache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-cache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 30000,
                        context: document.body,
                        data: {
                            action: "lws_clear_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
            if (document.getElementById("wp-admin-bar-lwsop-clear-subcache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-subcache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 30000,
                        context: document.body,
                        data: {
                            action: "lws_clear_style_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_style_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
            if (document.getElementById("wp-admin-bar-lwsop-clear-htmlcache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-htmlcache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        dataType: 'json',
                        type: "POST",
                        timeout: 30000,
                        context: document.body,
                        data: {
                            action: "lws_clear_html_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_html_fb_caching')); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
            if (document.getElementById("wp-admin-bar-lwsop-clear-current-cache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-current-cache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        dataType: 'json',
                        type: "POST",
                        timeout: 30000,
                        context: document.body,
                        data: {
                            action: "lws_clear_currentpage_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_currentpage_fb_caching')); ?>',
                            request_uri: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    window.location.reload();
                                    break;
                                default:
                                    alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            console.log(error);
                        }
                    });
                });
            }
        </script>
<?php
    }

    public function lwsop_get_url_preload()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_files', '_ajax_nonce');
        if (!isset($_POST['action'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the exclusions
        $preloads = isset($this->optimize_options['preload_css']['links']) ? $this->optimize_options['preload_css']['links'] : array();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $preloads, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    public function lwsop_set_url_preload()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_files_set', '_ajax_nonce');
        if (isset($_POST['data'])) {
            $urls = array();

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }

            $old = $this->optimize_options;
            $this->optimize_options['preload_css']['links'] = $urls;

            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            }
            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            // If correctly added and updated
            if ($return) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            } else {
                wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
            }
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }


    public function lwsop_get_url_preload_font()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_fonts', '_ajax_nonce');
        if (!isset($_POST['action'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the exclusions
        $preloads = isset($this->optimize_options['preload_font']['links']) ? $this->optimize_options['preload_font']['links'] : array();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $preloads, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    public function lwsop_set_url_preload_font()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_fonts_set', '_ajax_nonce');
        if (isset($_POST['data'])) {
            $urls = array();

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }

            $old = $this->optimize_options;
            $this->optimize_options['preload_font']['links'] = $urls;

            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            }
            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            // If correctly added and updated
            if ($return) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            } else {
                wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
            }
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }

    public function lwsop_reload_stats()
    {
        $stats = $this->lwsop_recalculate_stats("get");

        $stats['desktop']['size'] = $this->lwsOpSizeConvert($stats['desktop']['size'] ?? 0);
        $stats['mobile']['size'] = $this->lwsOpSizeConvert($stats['mobile']['size'] ?? 0);
        $stats['css']['size'] = $this->lwsOpSizeConvert($stats['css']['size'] ?? 0);
        $stats['js']['size'] = $this->lwsOpSizeConvert($stats['js']['size'] ?? 0);

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $stats)));
    }

    public function purge_specified_url()
    {
        $specified = $this->optimize_options['filebased_cache']['specified'] ?? [];
        foreach ($specified as $url) {
            if ($url == null) {
                continue;
            }
            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($url);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            lwsop_clear_cache_cloudflare("purge", array($url));
        }
    }

    public function fetch_url_sitemap($url, $data)
    {
        $sitemap_content = file_get_contents($url);
        // Check if content is retrieved
        if ($sitemap_content !== false) {
            // Load the XML content into SimpleXML
            $sitemap = simplexml_load_string($sitemap_content);
            if ($sitemap == null) {
                return $data;
            }

            foreach ($sitemap->url as $url) {
                $data[] = (string)$url->loc;
            }
            foreach ($sitemap->sitemap as $entry) {
                // If the file end in ".xml", then it is most likely a sitemap link
                if (array_pop(explode('.', $entry->loc)) == "xml") {
                    $data = $this->fetch_url_sitemap((string)$entry->loc, $data);
                }
            }
        }

        return $data;
    }

    public function lwsop_preload_fb()
    {
        check_ajax_referer('update_fb_preload', '_ajax_nonce');

        if (isset($_POST['action']) && isset($_POST['state'])) {
            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;

            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['preload'] = sanitize_text_field($_POST['state']);
            $this->optimize_options['filebased_cache']['preload_amount'] =  $amount;
            $this->optimize_options['filebased_cache']['preload_done'] =  0;

            $sitemap = get_sitemap_url("index");
            $urls = $this->fetch_url_sitemap($sitemap, []);

            $this->optimize_options['filebased_cache']['preload_quantity'] = count($urls);

            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache'])));
            }

            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            if ($return) {
                if (sanitize_text_field($_POST['state'] == "false") || wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                    wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache'])));
                }
                
                wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache'])));
            }
        }
        wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
    }

    public function lwsop_change_preload_amount()
    {
        check_ajax_referer('update_fb_preload_amount', '_ajax_nonce');

        if (isset($_POST['action'])) {
            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;

            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['preload_amount'] =  $amount;
            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => "DONE")));
            }

            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            if ($return) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => "DONE")));
            } else {
                wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
            }
        }
        wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
    }

    public function lwsop_do_pagespeed_test()
    {
        check_ajax_referer('lwsop_doing_pagespeed_nonce', '_ajax_nonce');
        $url = $_POST['url'] ?? null;
        $type = $_POST['type'] ?? null;
        $date = time();


        if ($url === null || $type === null) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $config_array = get_option('lws_optimize_pagespeed_history', array());
        $last_test = array_reverse($config_array)[0]['date'] ?? 0;


        if ($last_test = strtotime($last_test) && time() - $last_test < 180) {
            wp_die(json_encode(array('code' => "TOO_RECENT", 'data' => 180 - ($date - $last_test)), JSON_PRETTY_PRINT));
        }

        $url = esc_url($url);
        $type = sanitize_text_field($type);

        $response = wp_remote_get("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$url&key=AIzaSyD8yyUZIGg3pGYgFOzJR1NsVztAf8dQUFQ&strategy=$type", ['timeout' => 45, 'sslverify' => false]);
        if (is_wp_error($response)) {
            wp_die(json_encode(array('code' => "ERROR_PAGESPEED", 'data' => $response), JSON_PRETTY_PRINT));
        }

        $response = json_decode($response['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
        }

        $performance = $response['lighthouseResult']['categories']['performance']['score'] ?? null;
        $speedMetric = $response['lighthouseResult']['audits']['speed-index']['displayValue'] ?? null;
        $speedMetricValue = $response['lighthouseResult']['audits']['speed-index']['numericValue'] ?? null;
        $speedMetricUnit = $response['lighthouseResult']['audits']['speed-index']['numericUnit'] ?? null;


        $scores = [
            'performance' => $performance,
            'speed' => str_replace("/\s/g", "", $speedMetric),
            'speed_milli' => $speedMetricValue,
            'speed_unit' => $speedMetricUnit
        ];

        $new_pagespeed = ['date' =>  date("d M Y, H:i", $date) . " GMT+0", 'url' => $url, 'type' => $type, 'scores' => $scores];
        $config_array[] = $new_pagespeed;
        update_option('lws_optimize_pagespeed_history', $config_array);

        $history = array_slice($config_array, -10);
        $history = array_reverse($history);


        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $scores, 'history' => $history), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_set_fb_status()
    {
        check_ajax_referer('change_filebased_cache_status_nonce', '_ajax_nonce');
        if (!isset($_POST['timer']) || !isset($_POST['state'])) {
            wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url())));
        }

        $timer = sanitize_text_field($_POST['timer']);
        $state = sanitize_text_field($_POST['state']);

        if ($state !== "false" && $state !== "true") {
            $state = "false";
        }

        $temp_array = $this->optimize_options;

        $this->optimize_options['filebased_cache']['exceptions'] = $this->optimize_options['filebased_cache']['exceptions'] ?? [];
        $config_element = $this->optimize_options['filebased_cache']['state'] = $state;
        $this->optimize_options['filebased_cache']['timer'] = $timer;

        // Just return if the array did not change
        if ($temp_array == $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element)), JSON_PRETTY_PRINT);
        }

        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
        }
    }

    /**
     * Change the value of the file-based cache timer. Will automatically launch a WP-Cron at the defined $timer to clear the cache
     */
    public function lws_optimize_set_fb_timer()
    {
        check_ajax_referer('change_filebased_cache_timer_nonce', '_ajax_nonce');
        if (!isset($_POST['timer'])) {
            wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url())));
        }

        $timer = sanitize_text_field($_POST['timer']);
        if (empty($timer)) {
            if (empty($GLOBALS['lws_optimize_cache_timestamps']) || array_key_first($GLOBALS['lws_optimize_cache_timestamps']) === null) {
                $timer = "daily";
            } else {
                $timer = $GLOBALS['lws_optimize_cache_timestamps'][array_key_first($GLOBALS['lws_optimize_cache_timestamps'])][0];
            }
        }

        $fb_options = $this->lwsop_check_option('filebased_cache');
        if ($fb_options['state'] === "false") {
            $this->optimize_options['filebased_cache']['state'] = "false";
        }
        if (isset($this->optimize_options['filebased_cache']['timer']) && $this->optimize_options['filebased_cache']['timer'] === $timer) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $timer)), JSON_PRETTY_PRINT);
        }

        $this->optimize_options['filebased_cache']['timer'] = $timer;
        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        if ($return) {
            // Remove the old event and schedule a new one with the new timer
            if (wp_next_scheduled('lws_optimize_clear_filebased_cache')) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
            }

            // Never start cron if timer is defined as zero (infinite)
            if ($timer != 0) {
                wp_schedule_event(time(), $timer, 'lws_optimize_clear_filebased_cache');
            }

            wp_die(json_encode(array('code' => "SUCCESS", "data" => $timer)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $timer)), JSON_PRETTY_PRINT);
        }
    }

    /**
     * Set the 'state' of each action defined by the ID "lws_optimize_*_check" as such :
     * [name]['state'] = "true"/"false"
     */
    public function lws_optimize_manage_config()
    {
        check_ajax_referer('nonce_lws_optimize_checkboxes_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        $id = sanitize_text_field($_POST['data']['type']);
        $state = sanitize_text_field($_POST['data']['state']);

        if ($state !== "false" && $state !== "true") {
            $state = "false";
        }

        if (preg_match('/lws_optimize_(.*?)_check/', $id, $match) !== 1) {
            wp_die(json_encode(array('code' => "UNKNOWN_ID", "data" => $id)), JSON_PRETTY_PRINT);
        }

        // The $element to update
        $element = $match[1];

        // In case it is the dynamic cache, we need to check which type (cpanel/lws) it is and whether it CAN be activated
        if ($element == "dynamic_cache") {
            $fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLE'] ?? null;
            $lwscache_status = $_SERVER['lwscache'] ?? null;

            if ($lwscache_status == "Off") {
                $lwscache_status = false;
            } elseif ($lwscache_status == "On") {
                $lwscache_status = true;
            }

            if ($fastest_cache_status == "0") {
                $fastest_cache_status = false;
            } elseif ($fastest_cache_status == "1") {
                $fastest_cache_status = true;
            }


            if ($lwscache_status === null && $fastest_cache_status === null) {
                wp_die(json_encode(array('code' => "INCOMPATIBLE", "data" => "LWSCache is incompatible with this hosting. Use LWS.")), JSON_PRETTY_PRINT);
            }

            if (!$lwscache_status && !$fastest_cache_status) {
                wp_die(json_encode(array('code' => "PANEL_CACHE_OFF", "data" => "LWSCache is not activated on LWSPanel.")), JSON_PRETTY_PRINT);
            } elseif (!$fastest_cache_status && !$lwscache_status) {
                wp_die(json_encode(array('code' => "CPANEL_CACHE_OFF", "data" => "LWSCache is not activated on cPanel.")), JSON_PRETTY_PRINT);
            }
        }

        if ($element == "maintenance_db") {
            if (wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
            }
            if ($state == "true") {
                wp_schedule_event(time() + 604800, 'weekly', 'lws_optimize_maintenance_db_weekly');
            }
        }

        if ($element == "memcached") {
            if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                $this->optimize_options[$element]['state'] = "false";
                $return = update_option('lws_optimize_config_array', $this->optimize_options);
                wp_die(json_encode(array('code' => "REDIS_ALREADY_HERE", 'data' => "FAILURE", 'state' => "unknown")));
            }
            if (class_exists('Memcached')) {
                $memcached = new Memcached();
                if (empty($memcached->getServerList())) {
                    $memcached->addServer('localhost', 11211);
                }

                if ($memcached->getVersion() === false) {
                    if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                        unlink(LWSOP_OBJECTCACHE_PATH);
                    }
                    wp_die(json_encode(array('code' => "MEMCACHE_NOT_WORK", 'data' => "FAILURE", 'state' => "unknown")));
                }

                file_put_contents(LWSOP_OBJECTCACHE_PATH, file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
            } else {
                if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                    unlink(LWSOP_OBJECTCACHE_PATH);
                }
                wp_die(json_encode(array('code' => "MEMCACHE_NOT_FOUND", 'data' => "FAILURE", 'state' => "unknown")));
            }
        }

        if ($element == "gzip_compression") {
            if ($state == "true") {
                exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - GZIP COMPRESSION/,/#END LWS OPTIMIZE - GZIP COMPRESSION/ d' '" . escapeshellcmd(ABSPATH) . "/.htaccess'", $eOut, $eCode);
                $htaccess = ABSPATH . "/.htaccess";

                $hta = '';
                $hta .= "<IfModule mod_deflate.c>\n";
                $hta .= "SetOutputFilter DEFLATE\n";


                // Add all the types of files to compress
                $hta .= "<IfModule mod_filter.c>\n";
                $hta .= "AddOutputFilterByType DEFLATE application/javascript\n";
                $hta .= "AddOutputFilterByType DEFLATE application/json\n";
                $hta .= "AddOutputFilterByType DEFLATE application/rss+xml\n";
                $hta .= "AddOutputFilterByType DEFLATE application/xml\n";
                $hta .= "AddOutputFilterByType DEFLATE application/atom+xml\n";
                $hta .= "AddOutputFilterByType DEFLATE application/vnd.ms-fontobject\n";
                $hta .= "AddOutputFilterByType DEFLATE application/x-font-ttf\n";
                $hta .= "AddOutputFilterByType DEFLATE font/opentype\n";
                $hta .= "AddOutputFilterByType DEFLATE text/plain\n";
                $hta .= "AddOutputFilterByType DEFLATE text/pxml\n";
                $hta .= "AddOutputFilterByType DEFLATE text/html\n";
                $hta .= "AddOutputFilterByType DEFLATE text/css\n";
                $hta .= "AddOutputFilterByType DEFLATE text/x-component\n";
                $hta .= "AddOutputFilterByType DEFLATE image/svg+xml\n";
                $hta .= "AddOutputFilterByType DEFLATE image/x-icon\n";
                $hta .= "</IfModule>\n";

                $hta .= "</IfModule>\n";

                if ($hta != '') {
                    $hta =
                        "#LWS OPTIMIZE - GZIP COMPRESSION\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n $hta #END LWS OPTIMIZE - GZIP COMPRESSION\n";

                    if (is_file($htaccess)) {
                        $hta .= file_get_contents($htaccess);
                    }

                    if (($f = fopen($htaccess, 'w+')) !== false) {
                        if (!fwrite($f, $hta)) {
                            fclose($f);
                            error_log(json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | GZIP | .htaccess file is not writtable")));
                        } else {
                            fclose($f);
                        }
                    } else {
                        error_log(json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | GZIP | .htaccess file is not openable")));
                    }
                }
            } else {
                exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - GZIP COMPRESSION/,/#END LWS OPTIMIZE - GZIP COMPRESSION/ d' '" . escapeshellcmd(ABSPATH) . "/.htaccess'", $eOut, $eCode);
                if ($eCode != 0) {
                    error_log(json_encode(array('code' => 'ERR_SED', 'data' => "LWSOptimize | GZIP | An error occured when using sed in .htaccess")));
                }
            }
        }

        $temp_array = $this->optimize_options;

        // We change the state of the $element in the config
        // If no config is present for the $element, it will be added
        $config_element = $this->optimize_options[$element]['state'] = $state;

        // Just return if the array did not change
        if ($temp_array == $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'type' => $element)), JSON_PRETTY_PRINT);
        }
        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'type' => $element)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options, 'type' => $element)), JSON_PRETTY_PRINT);
        }
    }

    /**
     * Add exclusions to the given action
     */
    public function lws_optimize_manage_exclusions()
    {
        check_ajax_referer('nonce_lws_optimize_exclusions_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the ID for the currently open modal and get the action to modify
        $data = $_POST['data'];
        $id = null;
        foreach ($data as $var) {
            if ($var['name'] == "lwsoptimize_exclude_url_id") {
                $id = sanitize_text_field($var['value']);
                break;
            }
        }

        // No ID ? Cannot proceed
        if (!isset($id)) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the specific action from the ID
        if (preg_match('/lws_optimize_(.*?)_exclusion/', $id, $match) !== 1) {
            wp_die(json_encode(array('code' => "UNKNOWN_ID", "data" => $id)), JSON_PRETTY_PRINT);
        }

        $exclusions = array();

        // The $element to update
        $element = $match[1];
        // All configs for LWS Optimize
        $old = $this->optimize_options;

        // Get all exclusions
        foreach ($data as $var) {
            if ($var['name'] == "lwsoptimize_exclude_url") {
                if (trim($var['value']) == '') {
                    continue;
                }
                $exclusions[] = sanitize_text_field($var['value']);
            }
        }

        // Add the exclusions for the $element ; each is a URL (e.g. : my-website.fr/wp-content/plugins/...)
        // If no config is present for the $element, it will be added
        $config_element = $this->optimize_options[$element]['exclusions'] = $exclusions;

        if ($old === $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
        }
        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
        }
        wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
    }

    public function lws_optimize_manage_exclusions_media()
    {
        check_ajax_referer('nonce_lws_optimize_exclusions_media_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the ID for the currently open modal and get the action to modify
        $data = $_POST['data'];
        foreach ($data as $var) {
            if ($var['name'] == "lwsoptimize_exclude_url_id_media") {
                $id = sanitize_text_field($var['value']);
                break;
            } else {
                $id = null;
            }
        }

        // No ID ? Cannot proceed
        if (!isset($id)) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the specific action from the ID
        if (preg_match('/lws_optimize_(.*?)_exclusion_button/', $id, $match) !== 1) {
            wp_die(json_encode(array('code' => "UNKNOWN_ID", "data" => $id)), JSON_PRETTY_PRINT);
        }

        $exclusions = array();

        // The $element to update
        $element = $match[1];
        // All configs for LWS Optimize
        $old = $this->optimize_options;

        // Get all exclusions
        foreach ($data as $var) {
            switch ($var['name']) {
                case 'lwsoptimize_exclude_url':
                    if (trim($var['value']) == '') {
                        break;
                    }
                    $exclusions['css_classes'][] = sanitize_text_field($var['value']);
                    break;
                case 'lwsoptimize_gravatar':
                    $exclusions['media_types']['gravatar'] = true;
                    break;
                case 'lwsoptimize_thumbnails':
                    $exclusions['media_types']['thumbnails'] = true;
                    break;
                case 'lwsoptimize_responsive':
                    $exclusions['media_types']['responsive'] = true;
                    break;
                case 'lwsoptimize_iframe':
                    $exclusions['media_types']['iframe'] = true;
                    break;
                case 'lwsoptimize_mobile':
                    $exclusions['media_types']['mobile'] = true;
                    break;
                case 'lwsoptimize_video':
                    $exclusions['media_types']['video'] = true;
                    break;
                case 'lwsoptimize_excluded_iframes_img':
                    $tmp = $var['value'];
                    $tmp = explode(PHP_EOL, $tmp);
                    foreach ($tmp as $value) {
                        $exclusions['img_iframe'][] = trim(sanitize_text_field($value));
                    }
                    break;
                default:
                    break;
            }
        }

        // Add the exclusions for the $element ; each is a URL (e.g. : my-website.fr/wp-content/plugins/...)
        // If no config is present for the $element, it will be added
        $config_element = $this->optimize_options[$element]['exclusions'] = $exclusions;

        if ($old === $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
        }
        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
        }
    }

    public function lws_optimize_fetch_exclusions()
    {
        check_ajax_referer('nonce_lws_optimize_fetch_exclusions', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        $id = sanitize_text_field($_POST['data']['type']);

        if (preg_match('/lws_optimize_(.*?)_exclusion/', $id, $match) !== 1) {
            wp_die(json_encode(array('code' => "UNKNOWN_ID", "data" => $id)), JSON_PRETTY_PRINT);
        }

        // The $element to update
        $element = $match[1];
        // All configs for LWS Optimize

        // Get the exclusions
        $exclusions = isset($this->optimize_options[$element]['exclusions']) ? $this->optimize_options[$element]['exclusions'] : array();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $exclusions, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Clear the file-based cache completely
     */
    public function lws_optimize_clear_cache()
    {
        check_ajax_referer('clear_fb_caching', '_ajax_nonce');
        apply_filters("lws_optimize_clear_filebased_cache", false);

        $this->lwsop_recalculate_stats("all");
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_stylecache()
    {
        check_ajax_referer('clear_style_fb_caching', '_ajax_nonce');
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-js"));
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-css"));

        $this->lwsop_recalculate_stats("style");
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_htmlcache()
    {
        check_ajax_referer('clear_html_fb_caching', '_ajax_nonce');
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache"));
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-mobile"));

        $this->lwsop_recalculate_stats("html");
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_currentcache()
    {
        check_ajax_referer('clear_currentpage_fb_caching', '_ajax_nonce');

        // Get the request_uri of the current URL to remove
        // If not found, do not delete anything
        $uri = esc_url($_POST['request_uri']) ?? false;
        if ($uri === false) {
            wp_die(json_encode(array('code' => 'ERROR', 'data' => "/"), JSON_PRETTY_PRINT));
        }

        $amount = 0;
        $size = 0;

        // Get the PATH to the files
        $cache_dir = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);
        if ($cache_dir !== false && is_dir($cache_dir)) {
            if ($cache_dir == $this->lwsop_get_content_directory("cache") || $cache_dir == $this->lwsop_get_content_directory("cache-mobile")) {
                $files = glob($cache_dir . '/index_*');

                $amount = count($files);

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $size += filesize($file);
                        unlink($file);
                    }
                }
            } else {
                apply_filters("lws_optimize_clear_filebased_cache", $cache_dir);
            }
        }

        $is_mobile = wp_is_mobile();
        // Update the stats after the end
        $this->lwsop_recalculate_stats("minus", ['file' => $amount, 'size' => $size], $is_mobile);
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    /**
     * Clean the given directory. If no directory is given, remove /cache/lwsoptimize/
     */
    public function lws_optimize_clean_filebased_cache($directory = false)
    {
        do_action('rt_lws_cache_purge_all');

        if (!function_exists("lws_optimize_delete_directory")) {
            function lws_optimize_delete_directory($dir, $class_this)
            {
                if (!file_exists($dir)) {
                    return false;
                }

                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    if (is_dir("$dir/$file")) {
                        lws_optimize_delete_directory("$dir/$file", $class_this);
                    } else {
                        $size = filesize("$dir/$file");
                        @unlink("$dir/$file");
                        if (file_exists("$dir/$file")) {
                            return false;
                        }
                        $is_mobile = wp_is_mobile();
                        // Update the stats
                        $class_this->lwsop_recalculate_stats("minus", ['file' => 1, 'size' => $size], $is_mobile);
                    }
                }

                rmdir($dir);
                return !file_exists($dir);
            }
        }

        if ($directory) {
            $directory = esc_url($directory);
            if (is_dir($directory)) {
                lws_optimize_delete_directory($directory, $this);
            }
        } else {
            lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
        }

        if ($array = get_option('lws_optimize_config_array', false)) {
            $array['filebased_cache']['preload_done'] =  0;
            update_option('lws_optimize_config_array', $array);

            if (isset($array['filebased_cache']['preload']) && $array['filebased_cache']['preload'] == "true") {
                if (!wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                } else {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
            }
        }

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        lwsop_clear_cache_cloudflare("purge", array($url));
    }

    /**
     * Preload the file-based cache. Get all URLs from the sitemap and cache each of them
     */
    public function lws_optimize_start_filebased_preload()
    {
        $lws_filebased = new FileCache($GLOBALS['lws_optimize']);

        $sitemap = get_sitemap_url("index");
        $urls = $this->fetch_url_sitemap($sitemap, []);

        if ($array = get_option('lws_optimize_config_array', false)) {
            $max_try = intval($array['filebased_cache']['preload_amount'] ?? 5);
            $current_try = 0;

            $done = $array['filebased_cache']['preload_done'] ?? 0;

            $first_run = $done == 0 ? true : false;

            $array['filebased_cache']['preload_quantity'] = count($urls);

            error_log(json_encode($urls));

            foreach ($urls as $url) {
                // Don't do more than $max_try
                if ($current_try < $max_try) {
                    $parsed_url = parse_url($url);
                    $parsed_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';

                    $path = $lws_filebased->lwsop_set_cachedir($parsed_url);

                    if (!$path) {
                        continue;
                    }

                    $file_exists = glob($path . "index*") ?? [];

                    if (!empty($file_exists)) {
                        if ($first_run) {
                            $done++;
                        }
                    } else {
                        $response = wp_remote_get($url, array(
                            'user-agent' => "LWSOptimize Preloading cURL",
                            'timeout' => 30,
                            'sslverify' => false,
                            'headers' => array("Cache-Control" => "no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0")
                        ));

                        if (!$response || is_wp_error($response)) {
                            error_log($response->get_error_message() . " - ");
                        } else {
                            $file_exists = glob($path . "index*") ?? [];

                            if (!empty($file_exists)) {
                                $done++;
                                $current_try++;
                            }
                        }
                    }
                }
            }

            if ($current_try == 0 || $done == count($urls)) {
                $array['filebased_cache']['preload_ongoing'] = "false";
            } else {
                $array['filebased_cache']['preload_ongoing'] = "true";
            }

            $array['filebased_cache']['preload_done'] = $done;

            update_option('lws_optimize_config_array', $array);
        }
    }

    public function lwsop_specified_urls_fb()
    {
        check_ajax_referer('lwsop_get_specified_url_nonce', '_ajax_nonce');
        if (isset($this->optimize_options['filebased_cache']) && isset($this->optimize_options['filebased_cache']['specified'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache']['specified'], 'domain' => site_url()), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => array(), 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
    }

    public function lwsop_save_specified_urls_fb()
    {
        // Add all URLs to an array, but ignore empty URLs
        // If all fields are empty, remove the option from DB
        check_ajax_referer('lwsop_save_specified_nonce', '_ajax_nonce');
        if (isset($_POST['data'])) {
            $urls = array();

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }


            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['specified'] = $urls;

            if ($this->optimize_options == $old) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => $urls, 'domain' => site_url()), JSON_PRETTY_PRINT));
            }

            if (update_site_option('lws_optimize_config_array', $this->optimize_options)) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => $urls, 'domain' => site_url()), JSON_PRETTY_PRINT));
            } else {
                wp_die(json_encode(array('code' => "FAILED", 'data' => $urls, 'domain' => site_url()), JSON_PRETTY_PRINT));
            }
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }

    public function lwsop_exclude_urls_fb()
    {
        check_ajax_referer('lwsop_get_excluded_nonce', '_ajax_nonce');
        if (isset($this->optimize_options['filebased_cache']) && isset($this->optimize_options['filebased_cache']['exclusions'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache']['exclusions'], 'domain' => site_url()), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => array(), 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
    }

    public function lwsop_save_urls_fb()
    {
        // Add all URLs to an array, but ignore empty URLs
        // If all fields are empty, remove the option from DB
        check_ajax_referer('lwsop_save_excluded_nonce', '_ajax_nonce');

        if (isset($_POST['data'])) {
            $urls = array();

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }

            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['exclusions'] = $urls;

            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            }
            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            // If correctly added and updated
            if ($return) {
                wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
            } else {
                wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
            }
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }

    /**
     * Check if the given $option is set. If it is active, return the data if it exists.
     * Example : {filebased_cache} => ["state" => "true", "data" => ["timer" => "lws_daily", ...]]
     *
     * @param string $option The option to test
     * @return array ['state' => "true"/"false", 'data' => array]
     */
    public function lwsop_check_option(string $option)
    {
        try {
            if (empty($option) || $option === null) {
                return ['state' => "false", 'data' => []];
            }

            $option = sanitize_text_field($option);
            if (isset($this->optimize_options[$option]) && isset($this->optimize_options[$option]['state'])) {
                $array = $this->optimize_options[$option];
                $state = $array['state'];
                unset($array['state']);
                $data = $array;

                return ['state' => $state, 'data' => $data];
            }
        } catch (Exception $e) {
            error_log("LwsOptimize.php::lwsop_check_option | " . $e);
        }
        return ['state' => "false", 'data' => []];
    }

    // To get the fastest cache possible, the class is loaded outside of a hook,
    // meaning a few WP functions are not loaded and need to be manually added

    /**
     * A simple copy of 'is_plugin_active' from WordPress
     */
    public function lwsop_plugin_active($plugin)
    {
        return in_array($plugin, (array) get_option('active_plugins', array()), true) || $this->lwsop_plugin_active_for_network($plugin);
    }

    /**
     * A simple copy of 'is_plugin_active_for_network' from WordPress
     */
    public function lwsop_plugin_active_for_network($plugin)
    {
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[$plugin])) {
            return true;
        }

        return false;
    }

    /**
     * Return the PATH to the wp-content directory or, if $path is defined correctly,
     * return the PATH to the cached file. Modify the PATH if some plugins are activated.
     *
     * Adapted from WPFastestCache for the plugin part and the idea of using RegEx
     *
     * @param string $path PATH, from wp-content, to the cache file. Trailling slash not necessary
     * @return string PATH to the given file or to wp-content if $path if empty
     */
    public function lwsop_get_content_directory($path = false)
    {
        if ($path && preg_match("/(cache|cache-mobile|cache-css|cache-js)/", $path)) {
            // Add additional subdirectories to the PATH depending on the plugins installed
            $additional = "";
            if ($this->lwsop_plugin_active("sitepress-multilingual-cms/sitepress.php")) {
                switch (apply_filters('wpml_setting', false, 'language_negotiation_type')) {
                    case 2:
                        $my_home_url = apply_filters('wpml_home_url', get_option('home'));
                        $my_home_url = preg_replace("/https?\:\/\//i", "", $my_home_url);
                        $my_home_url = trim($my_home_url, "/");

                        $additional = $my_home_url;
                        break;
                    case 1:
                        $my_current_lang = apply_filters('wpml_current_language', null);
                        if ($my_current_lang) {
                            $additional = $my_current_lang;
                        }
                        break;
                    default:
                        break;
                }
            }

            if ($this->lwsop_plugin_active('multiple-domain-mapping-on-single-site/multidomainmapping.php') || $this->lwsop_plugin_active('multiple-domain/multiple-domain.php') || is_multisite()) {
                $additional = $_SERVER['HTTP_HOST'];
            }

            if ($this->lwsop_plugin_active('polylang/polylang.php')) {
                $polylang_settings = get_option("polylang");
                if (isset($polylang_settings["force_lang"]) && ($polylang_settings["force_lang"] == 2 || $polylang_settings["force_lang"] == 3)) {
                    $additional = $_SERVER['HTTP_HOST'];
                }
            }

            if (!empty($additional)) {
                $additional = rtrim($additional) . "/";
            }
            return WP_CONTENT_DIR . ("/cache/lwsoptimize/$additional" . $path);
        }

        return WP_CONTENT_DIR;
    }

    /**
     * Recalculate the stats of the cache from scratch
     */
    public function lwsop_recalculate_stats($type = "get", $data = ['css' => ['file' => 0, 'size' => 0], 'js' => ['file' => 0, 'size' => 0], 'html' => ['file' => 0, 'size' => 0]], $is_mobile = false)
    {

        $stats = get_option('lws_optimize_cache_statistics', [
            'desktop' => ['amount' => 0, 'size' => 0],
            'mobile' => ['amount' => 0, 'size' => 0],
            'css' => ['amount' => 0, 'size' => 0],
            'js' => ['amount' => 0, 'size' => 0],
        ]);

        switch ($type) {
            case "get":
                break;
            case 'all':
                $stats = [
                    'desktop' => ['amount' => 0, 'size' => 0],
                    'mobile' => ['amount' => 0, 'size' => 0],
                    'css' => ['amount' => 0, 'size' => 0],
                    'js' => ['amount' => 0, 'size' => 0],
                ];
                break;
            case 'plus':
                $stats['css']['amount'] += $data['css']['file'];
                $stats['css']['size'] += $data['css']['size'];

                $stats['js']['amount'] += $data['js']['file'];
                $stats['js']['size'] += $data['js']['size'];

                if ($is_mobile) {
                    $stats['mobile']['amount'] += $data['html']['file'];
                    $stats['mobile']['size'] += $data['html']['size'];
                } else {
                    $stats['desktop']['amount'] += $data['html']['file'];
                    $stats['desktop']['size'] += $data['html']['size'];
                }
                break;
            case 'minus':
                if ($is_mobile) {
                    $stats['mobile']['amount'] -= $data['file'] ?? 0;
                    $stats['mobile']['size'] -= $data['size'] ?? 0;
                } else {
                    $stats['desktop']['amount'] -= $data['file'] ?? 0;
                    $stats['desktop']['size'] -= $data['size'] ?? 0;
                }
                break;
            case 'style':
                $stats['css']['amount'] = 0;
                $stats['css']['size'] = 0;
                $stats['js']['amount'] = 0;
                $stats['js']['size'] = 0;
                break;
            case 'html':
                $stats['desktop']['amount'] = 0;
                $stats['desktop']['size'] = 0;
                $stats['mobile']['amount'] = 0;
                $stats['mobile']['size'] = 0;
                break;
            default:
                break;
        }

        update_option('lws_optimize_cache_statistics', $stats);
        return $stats;
    }

    public function lwsOpSizeConvert($size)
    {
        $unit = array(__('b', 'lws-optimize'), __('K', 'lws-optimize'), __('M', 'lws-optimize'), __('G', 'lws-optimize'), __('T', 'lws-optimize'), __('P', 'lws-optimize'));
        if ($size <= 0) {
            return '0 ' . $unit[1];
        }
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . '' . $unit[$i];
    }

    /**
     * Clear cache whenever a new comment is posted
     */
    public function lws_optimize_clear_cache_on_comment($comment_id, $comment)
    {
        if (!$this->state) {
            $post_id = $comment->comment_post_ID;

            $uri = get_page_uri($post_id);
            $uri = get_site_url(null, $uri);
            $uri = parse_url($uri)['path'];

            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);
            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            lwsop_clear_cache_cloudflare("purge", array($url));
            $this->purge_specified_url();
        }
    }

    /**
     * Clear cache whenever a post is modified
     */
    public function lwsop_remove_cache_post_change($post_id, $post)
    {

        // If WooCommerce is active, then remove the shop cache when adding/modifying new products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = wc_get_page_id('shop');
            $uri = get_permalink($shop_id);
            $uri = parse_url($uri)['path'];
            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);
        }

        $uri = get_permalink($post_id);
        $uri = parse_url($uri)['path'];
        $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

        apply_filters("lws_optimize_clear_filebased_cache", $file);

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        lwsop_clear_cache_cloudflare("purge", array($url));
        $this->purge_specified_url();
    }

    /**
     * Clear cache whenever a post status is changed
     */
    public function lwsop_remove_cache_post_change_specific($post_id, $status)
    {
        $post = get_post($post_id);

        // If WooCommerce is active, then remove the shop cache when removing products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = wc_get_page_id('shop');

            $uri = get_permalink($shop_id);
            $uri = parse_url($uri)['path'];

            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);
        }

        $uri = get_permalink($post_id);
        $uri = parse_url($uri)['path'];

        $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

        apply_filters("lws_optimize_clear_filebased_cache", $file);

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        lwsop_clear_cache_cloudflare("purge", array($url));
        $this->purge_specified_url();
    }

    /**
     * WooCommerce-specific actions ; Remove the cache for the checkout page and the cart page when the later is modified
     */
    public function lwsop_remove_fb_cache_on_cart_update()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $cart_id = wc_get_page_id('cart');

            $uri = get_permalink($cart_id);
            $uri = parse_url($uri)['path'];

            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $checkout_id = wc_get_page_id('checkout');

            $uri = get_permalink($checkout_id);
            $uri = parse_url($uri)['path'];

            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            lwsop_clear_cache_cloudflare("purge", array($url));
            $this->purge_specified_url();
        }
    }

    // Fetch options for maintaining DB
    public function lws_optimize_manage_maintenance_get()
    {
        check_ajax_referer('lwsop_get_maintenance_db_nonce', '_ajax_nonce');

        if (!isset($this->optimize_options['maintenance_db']) || !isset($this->optimize_options['maintenance_db']['options'])) {
            $this->optimize_options['maintenance_db']['options'] = array(
                'myisam' => false,
                'drafts' => false,
                'revisions' => false,
                'deleted_posts' => false,
                'spam_posts' => false,
                'deleted_comments' => false,
                'expired_transients' => false
            );
            update_option('lws_optimize_config_array', $this->optimize_options);
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $this->optimize_options['maintenance_db']['options'], 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    // Update the DB options array    
    public function lws_optimize_set_maintenance_db_options()
    {
        // Add all URLs to an array, but ignore empty URLs
        // If all fields are empty, remove the option from DB
        check_ajax_referer('lwsop_set_maintenance_db_nonce', '_ajax_nonce');
        if (!isset($_POST['formdata'])) {
            $_POST['formdata'] = [];
        }
        $options = array();

        foreach ($_POST['formdata'] as $data) {
            $value = sanitize_text_field($data);
            if ($value == "" || empty($value)) {
                continue;
            }
            $options[] = $value;
        }

        $old = $this->optimize_options;
        $this->optimize_options['maintenance_db']['options'] = $options;

        if ($old === $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $options)), JSON_PRETTY_PRINT);
        }
        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            if (wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
            }
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $options)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
        }
    }

    public function lws_optimize_create_maintenance_db_options()
    {
        global $wpdb;
        $this->optimize_options = get_option('lws_optimize_config_array', array());

        $config_options = $this->optimize_options['maintenance_db']['options'];
        foreach ($config_options as $options) {
            switch ($options) {
                case 'myisam':
                    $results = $wpdb->get_results("SELECT table_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '{$wpdb->prefix}%' AND ENGINE = 'MyISAM' AND TABLE_SCHEMA = '" . $wpdb->dbname . "';");
                    foreach ($results as $result) {
                        $rows_affected = $wpdb->query($wpdb->prepare("OPTIMIZE TABLE %s", $result->table_name));
                        if ($rows_affected === false) {
                            error_log("lws-optimize.php::create_maintenance_db_options | The table {$result->table_name} has not been OPTIMIZED");
                        }
                    }
                    break;
                case 'drafts':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_status = 'draft';");
                    // Remove drafts
                    break;
                case 'revisions':
                    $wpdb->query("DELETE FROM `{$wpdb->prefix}posts` WHERE post_type = 'revision';");
                    // Remove revisions
                    break;
                case 'deleted_posts':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_status = 'trash' && (post_type = 'post' OR post_type = 'page');");
                    // Remove trashed posts/page
                    break;
                case 'spam_comments':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}comments WHERE comment_approved = 'spam';");
                    // remove spam comments
                    break;
                case 'deleted_comments':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}comments WHERE comment_approved = 'trash';");
                    // remove deleted comments
                    break;
                case 'expired_transients':
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%_transient_timeout_%' AND option_value < ?;", [time()]);
                    $wpdb->query($query);
                    // remove expired transients
                    break;
                default:
                    break;
            }
        }
    }

    public function lws_optimize_get_database_cleaning_time()
    {
        check_ajax_referer('lws_optimize_get_database_cleaning_nonce', '_ajax_nonce');
        $next = wp_next_scheduled('lws_optimize_maintenance_db_weekly') ?? false;
        if (!$next) {
            $next = "-";
        } else {
            $next = get_date_from_gmt(date('Y-m-d H:i:s', intval($next)), 'Y-m-d H:i:s');
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $next)), JSON_PRETTY_PRINT);
    }

    public function lwsop_get_setup_optimize()
    {
        check_ajax_referer('lwsop_change_optimize_configuration_nonce', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the value to change the plugin configuration
        $data = $_POST['data'];
        $value = false;
        foreach ($data as $var) {
            if ($var['name'] == "lwsop_configuration[]") {
                $value = sanitize_text_field($var['value']);
                break;
            }
        }

        // No value ? Cannot proceed
        if (!isset($value) || !$value) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        switch ($value) {
            case 'recommended':
                $value = "basic";
                break;
            case 'advanced':
                $value = "advanced";
                break;
            case 'complete':
                $value = "full";
                break;
            default:
                $value = "basic";
                break;
        }


        $this->lwsop_auto_setup_optimize($value);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => "")), JSON_PRETTY_PRINT);
    }

    public function lwsop_auto_setup_optimize($type = "basic")
    {
        $options = get_option('lws_optimize_config_array', []);
        switch ($type) {
            case 'basic': // recommended only
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = "5";
                $options['filebased_cache']['timer'] = "lws_thrice_monthly";
                $options['combine_css']['state'] = "true";
                $options['combine_js']['state'] = "true";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['minify_html']['state'] = "true";
                $options['autopurge']['state'] = "true";
                $options['memcached']['state'] = "false";
                $options['gzip_compression']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "false";
                $options['maintenance_db']['options'] = [];
                $options['preload_css']['state'] = "false";
                $options['preload_font']['state'] = "false";
                $options['deactivate_emoji']['state'] = "false";
                $options['eliminate_requests']['state'] = "false";
                $options['cache_mobile_user']['state'] = "false";
                $options['dynamic_cache']['state'] = "true";

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
                wp_schedule_event(time(), 'lws_thrice_monthly', 'lws_optimize_clear_filebased_cache');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
                break;
            case 'advanced':
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = "7";
                $options['filebased_cache']['timer'] = "lws_thrice_monthly";
                $options['combine_css']['state'] = "true";
                $options['combine_js']['state'] = "true";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['minify_html']['state'] = "true";
                $options['autopurge']['state'] = "true";
                $options['memcached']['state'] = "false";
                $options['gzip_compression']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "false";
                $options['maintenance_db']['options'] = ["myisam", "spam_comments", "expired_transients"];
                $options['preload_css']['state'] = "true";
                $options['preload_font']['state'] = "true";
                $options['deactivate_emoji']['state'] = "false";
                $options['eliminate_requests']['state'] = "false";
                $options['cache_mobile_user']['state'] = "false";
                $options['dynamic_cache']['state'] = "true";

                update_option('lws_optimize_config_array', $options);
                break;
            case 'full':
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = "8";
                $options['filebased_cache']['timer'] = "lws_biyearly";
                $options['combine_css']['state'] = "true";
                $options['combine_js']['state'] = "true";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['minify_html']['state'] = "true";
                $options['autopurge']['state'] = "true";
                $options['memcached']['state'] = "false";
                $options['gzip_compression']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "false";
                $options['maintenance_db']['options'] = ["myisam", "spam_comments", "expired_transients", "drafts", "revisions", "deleted_posts", "deleted_comments"];
                $options['preload_css']['state'] = "true";
                $options['preload_font']['state'] = "true";
                $options['deactivate_emoji']['state'] = "true";
                $options['eliminate_requests']['state'] = "true";
                $options['cache_mobile_user']['state'] = "true";
                $options['dynamic_cache']['state'] = "true";

                update_option('lws_optimize_config_array', $options);
                break;
            default:
                break;
        }

        apply_filters("lws_optimize_clear_filebased_cache", false);
    }

    public function lwsop_convert_all_media()
    {
        check_ajax_referer('lwsop_convert_all_images_nonce', '_ajax_nonce');

        // Fetch data from the form
        $type = $_POST['data']['type'] != null ? sanitize_text_field($_POST['data']['type']) : "webp";
        $quality = $_POST['data']['quality'] != null ? intval($_POST['data']['quality']) : 75;
        $keepcopy = $_POST['data']['keepcopy'] != null ? sanitize_text_field($_POST['data']['keepcopy']) : "true";
        $exceptions = $_POST['data']['exceptions'] != null ? sanitize_textarea_field($_POST['data']['exceptions']) : '';
        $amount_per_run = $_POST['data']['amount_per_patch'] != null ? intval($_POST['data']['amount_per_patch']) : 10;
        $mimetypes = $_POST['data']['mimetypes'] != null ? $_POST['data']['mimetypes'] : ['jpeg', 'jpg', 'png', 'webp', 'avif'];

        $authorized_types = [];
        foreach ($mimetypes as $mimetype) {
            $authorized_types[] = sanitize_text_field($mimetype);
        }
        // To support both way to write JPG
        if (in_array("jpg", $authorized_types) && !in_array("jpeg", $authorized_types)) {
            $authorized_types[] = "jpeg";
        }
        if (in_array("jpeg", $authorized_types) && !in_array("jpg", $authorized_types)) {
            $authorized_types[] = "jpg";
        }

        // Get the string containing exceptions and transform it into an array
        $exceptions = explode(',', $exceptions);

        // Fetch and store all necessary infos to proceed with the conversion
        $data = [
            'convert_type' => $type,
            'keep_copy' => $keepcopy,
            'quality' => $quality,
            'exceptions' => $exceptions,
            'amount_per_run' => $amount_per_run,
            'convertible_mimetype' => $authorized_types,
        ];

        // Add the data to the database for future access by the cron
        update_option('lws_optimize_all_media_convertion', $data);

        $current_data = $this->lwsop_update_current_media_convertion_database();

        // Update the stats about the current convertion
        update_option('lws_optimize_current_convertion_stats', ['type' => $type, 'original' => $current_data['max'], 'converted' => $current_data['converted']]);

        $scheduled = false;
        // Deactivate the deconvertion if activated before converting anything
        wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");
        if (!wp_next_scheduled('lws_optimize_convert_media_cron')) {
            $scheduled = wp_schedule_event(time() + 3, 'lws_three_minutes', 'lws_optimize_convert_media_cron');
        } else {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
            $scheduled = wp_schedule_event(time() + 3, 'lws_three_minutes', 'lws_optimize_convert_media_cron');
        }

        if ($scheduled) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILED", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
        }
    }

    /**
     * Convert a certain amount (between 1 and 15) of images to the desired format.
     * The function does not check much else, whether or not anything got converted is not checked
     */
    public function lws_optimize_convert_media_cron()
    {
        // Fetch data from the databse and check for validity
        $media_data = get_option('lws_optimize_all_media_convertion', []);

        if (empty($media_data)) {
            wp_die(json_encode(array('code' => "NO_DATA", "data" => "", 'domain' => site_url())), JSON_PRETTY_PRINT);
        }

        // Launch the convertion
        $response = $this->lwsImageOptimization->convert_all_medias(
            $media_data['quality'] ?? 75,
            $media_data['amount_per_run'] ?? 10,
        );

        // If no attachments got converted, stop the cron here
        $response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $response['data'];
            $done = $response['evolution'] ?? 0;

            // Nothing got converted, stop the cron
            if ($done == 0) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
            }

            // Update the data array with info on what has been done and what is left
            $options = [
                'latest_time' => time(),
                'data' => [
                    'max' => $data['max'] ?? 0,
                    'to_convert' => $data['to_convert'] ?? 0,
                    'converted' => $data['converted'] ?? 0,
                    'left' => $data['left'] ?? 0,
                    'convert_type' => $media_data['convert_type'] ?? '-'
                ]
            ];
            update_option('lws_optimize_current_media_convertion', $options);
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Remove the cron for the convertion of all medias, stopping the process
     */
    public function lws_optimize_stop_convertion()
    {
        check_ajax_referer('lwsop_stop_convertion_nonce', '_ajax_nonce');
        wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');

        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
    }


    /**
     * Remove the cron for the restoration of all converted medias, stopping the process
     */
    public function lws_optimize_stop_deconvertion()
    {
        check_ajax_referer('lwsop_stop_deconvertion_nonce', '_ajax_nonce');
        wp_unschedule_event(wp_next_scheduled('lwsop_revertOptimization'), 'lwsop_revertOptimization');

        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Revert all original (and saved) images to normal
     */
    public function lws_optimize_revert_convertion()
    {
        check_ajax_referer('lwsop_revert_convertion_nonce', '_ajax_nonce');

        // Get the latest data on the convertion
        $latest_convertion = $this->lwsop_update_current_media_convertion_database();

        $next_scheduled_all_convert = wp_next_scheduled('lws_optimize_convert_media_cron');
        $next_scheduled_deconvert = wp_next_scheduled('lwsop_revertOptimization');

        $next = $next_scheduled_all_convert ? get_date_from_gmt(date('Y-m-d H:i:s', intval($next_scheduled_all_convert)), 'Y-m-d H:i:s') : "-";
        $next_deconvert = $next_scheduled_deconvert ? get_date_from_gmt(date('Y-m-d H:i:s', intval($next_scheduled_deconvert)), 'Y-m-d H:i:s') : "-";

        $max = intval($latest_convertion['max'] ?? 0);
        $left = intval($latest_convertion['left'] ?? 0);
        $done = intval($latest_convertion['converted'] ?? 0);
        $type = $latest_convertion['type'] ?? '-';

        $images = get_option('lws_optimize_images_convertion', []);

        $data = [
            'status' => $next_scheduled_all_convert ? true : false,
            'status_revert' => wp_next_scheduled('lwsop_revertOptimization') ? true : false,
            'next' => htmlentities($next),
            'next_deconvert' => htmlentities($next_deconvert),
            'max' => intval($max) ?? 0,
            'left' => intval($left) ?? 0,
            'done' => intval($done) ?? 0,
            'listing' => $images,
            'convert_type' => htmlentities($type)
        ];


        // Also deactivate the convertion before starting the deconvertion, to prevent errors
        wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
        wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");
        wp_schedule_event(time(), "lws_minute", "lwsop_revertOptimization");

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    public function lwsop_revertOptimization()
    {
        $return = $this->lwsImageOptimization->revertOptimization();
        $response = json_decode($return, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $response['data'];

            // Nothing got converted, stop the cron
            if (empty($data)) {
                wp_unschedule_event(wp_next_scheduled('lwsop_revertOptimization'), 'lwsop_revertOptimization');
            }
        }
    }

    /**
     * Activate the auto-convert-on-upload feature for medias (images) and store the data for how to
     * convert the images
     */
    public function lwsop_start_autoconvert_media()
    {
        check_ajax_referer('lwsop_convert_all_images_on_upload_nonce', '_ajax_nonce');

        $type = $_POST['data']['type'] != null ? sanitize_text_field($_POST['data']['type']) : "webp";
        $quality = $_POST['data']['quality'] != null ? intval($_POST['data']['quality']) : 75;
        $mimetypes = $_POST['data']['mimetypes'] != null ? $_POST['data']['mimetypes'] : [];


        $authorized_types = [];
        foreach ($mimetypes as $mimetype) {
            $authorized_types[] = sanitize_text_field($mimetype);
        }


        $data = [
            'state' => "true",
            'convert_type' => $type,
            'quality' => $quality,
            'convertible_mimetype' => $authorized_types
        ];

        $old = $this->optimize_options;
        $this->optimize_options['auto_update'] = $data;

        if ($this->optimize_options == $old) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $data, 'domain' => site_url()), JSON_PRETTY_PRINT));
        }

        update_option('lws_optimize_config_array', $this->optimize_options);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Stop the auto-convert-on-upload feature
     */
    public function lwsop_stop_autoconvertion()
    {
        check_ajax_referer('lwsop_stop_autoconvertion_nonce', '_ajax_nonce');

        $old = $this->optimize_options;
        $this->optimize_options['auto_update'] = [
            'state' => "false"
        ];

        if ($this->optimize_options == $old) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => "Done", 'domain' => site_url()), JSON_PRETTY_PRINT));
        }

        update_option('lws_optimize_config_array', $this->optimize_options);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Check and return the state of the preloading
     */
    public function lwsop_check_preload_update()
    {
        check_ajax_referer('lwsop_check_for_update_preload_nonce', '_ajax_nonce');

        $next = wp_next_scheduled('lws_optimize_start_filebased_preload') ?? null;
        if ($next != null) {
            $next = get_date_from_gmt(date('Y-m-d H:i:s', $next), 'Y-m-d H:i:s');
        }
        $data = [
            'quantity' => $this->optimize_options['filebased_cache']['preload_quantity'] ?? null,
            'done' => $this->optimize_options['filebased_cache']['preload_done'] ?? null,
            'ongoing' => $this->optimize_options['filebased_cache']['preload_ongoing'] ?? null,
            'next' => $next ?? null
        ];

        if ($data['quantity'] === null || $data['done'] === null || $data['ongoing'] === null || $data['next'] === null) {
            wp_die(json_encode(array('code' => "ERROR", "data" => $data, 'message' => "Failed to get some of the datas", 'domain' => site_url())), JSON_PRETTY_PRINT);
        }


        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Update the database with the latest data about the current state of convertion
     */
    public function lwsop_update_current_media_convertion_database() {
        $data = get_option('lws_optimize_all_media_convertion');

        $type = $data['convert_type'];
        $keepcopy = $data['keep_copy'];
        $exceptions = $data['exceptions'];
        $authorized_types = $data['convertible_mimetype'];

        $all_medias_to_convert =  get_option('lws_optimize_images_convertion', []);
        
        $original = 0;
        $converted = 0;

        // Get all images
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => null,
            'post_parent' => null
        );
        $attachments = get_posts($args);

        // Go through each image, verify its status and update the array
        // Also fetch $data on how many images are $converted and how many are still $original
        foreach ($attachments as $attachment) {
            $mimetype = $attachment->post_mime_type;
            $extension = explode('/', $mimetype)[1];

            $inlist_attachment = $all_medias_to_convert[$attachment->ID] ?? null;
            $name = $attachment->post_name;

            // Get the URL and PATH of the image
            $attachment_url = wp_get_attachment_url($attachment->ID);
            $attachment_path = get_attached_file($attachment->ID);

            // Remove the current extension and replace it with the one to convert into
            $attachment_url_converted = explode('.', $attachment_url);
            array_pop($attachment_url_converted);
            $attachment_url_converted = implode('.', $attachment_url_converted) . ".$type";

            $attachment_path_converted = explode('.', $attachment_path);
            array_pop($attachment_path_converted);
            $attachment_path_converted = implode('.', $attachment_path_converted) . ".$type";

            // If the attachment is not in our array
            if ($inlist_attachment == null) {

                // Only process images which mimetype is supported
                if (!in_array($extension, $authorized_types)) {
                    continue;
                }

                // Do not bother with this attachment if the file does not exists
                if (!file_exists($attachment_path)) {
                    continue;
                }

                // Do not convert images that are too big (more than 400Ko) to AVIF
                if ($type == "avif" && (filesize($attachment_path)  / 1000) > 402) {
                    continue;
                }

                // The image has been excluded and should not be converted
                if (in_array("$name.$mimetype", $exceptions)) {
                    continue;
                }

                // The image is already in the right type but is not in the array :
                // Either it is an image not converted by us or it has not been converted
                // It could also be because the array has been deleted nut in that case we can't do anything about it
                // as we have no info on the original image
                if ($extension == $type) {
                    continue;
                }

                // We add it to the array with every info we might need to convert later
                $all_medias_to_convert[$attachment->ID] = [
                    'ID' => $attachment->ID,
                    'name' => $name,
                    'original_url' => $attachment_url,
                    'original_path' => $attachment_path,
                    'original_mime' => $mimetype,
                    'original_extension' => $extension,
                    'url' => $attachment_url_converted,
                    'path' => $attachment_path_converted,
                    'mime' => "image/$type",
                    'extension' => $type,
                    'to_keep' => $keepcopy,
                    'converted' => false,
                    'date_convertion' => false
                ];
            } else {
                // The attachment is already in our array, we just need to check whether it still exists and whether or not it is converted

                // Remove the attachment from the listing if it does not exists anymore (and should still exists)
                if ( (!file_exists($attachment_path) && $inlist_attachment['to_keep'] == "true" ) || ($inlist_attachment['to_keep'] == "false" && !file_exists($all_medias_to_convert['path'])) ) {
                    unset($all_medias_to_convert[$attachment->ID]);
                    continue;
                }
                
                // Do not count the image as "to convert" but do not remove it either
                if (isset($all_medias_to_convert[$attachment->ID]['error_on_convertion'])) {
                    continue;
                }

                // The attachment is already in the $type MIME, we count it as $converted
                if ($mimetype == "image/$type") {
                    $converted++;
                } else {
                    // The image should no longer be converted
                    if (!in_array($extension, $authorized_types)) {
                        unset($all_medias_to_convert[$attachment->ID]);
                        continue;
                    }

                    // The image has been excluded and should not be converted anymore
                    if (in_array("$name.$extension", $exceptions)) {
                        unset($all_medias_to_convert[$attachment->ID]);
                        continue;
                    }

                    // Do not convert images that are too big (more than 400Ko) to AVIF
                    if ($type == "avif" && (filesize($attachment_path)  / 1000) > 402) {
                        unset($all_medias_to_convert[$attachment->ID]);
                        continue;
                    }

                    // Update the data of the image to reflect the present
                    $all_medias_to_convert[$attachment->ID] = [
                        'ID' => $attachment->ID,
                        'name' => $name,
                        'original_url' => $attachment_url,
                        'original_path' => $attachment_path,
                        'original_mime' => $mimetype,
                        'original_extension' => $extension,
                        'url' => $attachment_url_converted,
                        'path' => $attachment_path_converted,
                        'mime' => "image/$type",
                        'to_keep' => $keepcopy,
                        'converted' => false,
                        'date_convertion' => false
                    ];
                }
                $original++;
            }
        }

        update_option('lws_optimize_images_convertion', $all_medias_to_convert);

        // Update the stats about the current convertion
        update_option('lws_optimize_current_convertion_stats', ['type' => $type, 'original' => $original, 'converted' => $converted]);

        return [
            'max' => $original,
            'to_convert' => $original,
            'converted' => $converted,
            'left' => $original - $converted,
            'type' => $type
        ];
    }

    public function lwsop_check_convert_images_update()
    {
        check_ajax_referer('lwsop_check_for_update_convert_image_nonce', '_ajax_nonce');
        // Get the latest data on the convertion
        $latest_convertion = $this->lwsop_update_current_media_convertion_database();

        $next_scheduled_all_convert = wp_next_scheduled('lws_optimize_convert_media_cron');
        $next_scheduled_deconvert = wp_next_scheduled('lwsop_revertOptimization');

        $next = $next_scheduled_all_convert ? get_date_from_gmt(date('Y-m-d H:i:s', intval($next_scheduled_all_convert)), 'Y-m-d H:i:s') : "-";
        $next_deconvert = $next_scheduled_deconvert ? get_date_from_gmt(date('Y-m-d H:i:s', intval($next_scheduled_deconvert)), 'Y-m-d H:i:s') : "-";

        $max = intval($latest_convertion['max'] ?? 0);
        $left = intval($latest_convertion['left'] ?? 0);
        $done = intval($latest_convertion['converted'] ?? 0);
        $type = $latest_convertion['type'] ?? '-';

        $images = get_option('lws_optimize_images_convertion', []);

        $data = [
            'status' => $next_scheduled_all_convert ? true : false,
            'status_revert' => wp_next_scheduled('lwsop_revertOptimization') ? true : false,
            'next' => htmlentities($next),
            'next_deconvert' => htmlentities($next_deconvert),
            'max' => intval($max) ?? 0,
            'left' => intval($left) ?? 0,
            'done' => intval($done) ?? 0,
            'listing' => $images,
            'convert_type' => htmlentities($type)
        ];

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }
}
