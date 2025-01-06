<?php

namespace Lws\Classes;

use Lws\Classes\Admin\LwsOptimizeManageAdmin;
use Lws\Classes\FileCache\LwsOptimizeAutoPurge;
use Lws\Classes\Images\LwsOptimizeImageOptimization;
use Lws\Classes\LazyLoad\LwsOptimizeLazyLoading;
use Lws\Classes\FileCache\LwsOptimizeFileCache;
use Lws\Classes\FileCache\LwsOptimizeCloudFlare;

class LwsOptimize
{
    public $optimize_options;
    public $lwsOptimizeCache;
    public $state;
    public $lwsImageOptimization;
    public $cloudflare_manager;

    public function __construct()
    {
        $GLOBALS['lws_optimize'] = $this;
        $GLOBALS['lws_optimize_cache_timestamps'] = [
            'lws_daily' => [86400, __('Once a day', 'lws-optimize')],
            'lws_weekly' => [604800, __('Once a week', 'lws-optimize')],
            'lws_monthly' => [2629743, __('Once a month', 'lws-optimize')],
            'lws_thrice_monthly' => [7889232, __('Once every 3 months', 'lws-optimize')],
            'lws_biyearly' => [15778463, __('Once every 6 months', 'lws-optimize')],
            'lws_yearly' => [31556926, __('Once a year', 'lws-optimize')],
            'lws_two_years' => [63113852, __('Once every 2 years', 'lws-optimize')],
            'lws_never' => [0, __('Never expire', 'lws-optimize')],
        ];

        // Get the current state of the plugin ; If deactivated, only PageSpeed works
        $this->state = get_option('lws_optimize_offline', false);

        // Path to the object-cache file (for Memcached)
        define('LWSOP_OBJECTCACHE_PATH', WP_CONTENT_DIR . '/object-cache.php');

        // Get all the options for LWSOptimize. If none are found (first start, erased from DB), recreate the array
        $this->optimize_options = get_option('lws_optimize_config_array', []);
        if (empty($this->optimize_options)) {
            $this->optimize_options = [
                'filebased_cache' => ['state' => "true", "preload" => "true", "preload_amount" => "5", 'timer' => 'lws_thrice_monthly'],
                'autopurge' => ['state' => "true"],
                'cache_logged_user' => ['state' => "true"],
                'htaccess_rules' => ['state' => "true"]
            ];

            $this->lws_optimize_reset_header_htaccess();

            update_option('lws_optimize_config_array', $this->optimize_options);

            // Deactivate the plugin on activation
            update_option('lws_optimize_offline', 'ON');
            delete_option('lws_optimize_preload_is_ongoing');

            $this->lws_optimize_set_cache_htaccess();

            if (!wp_next_scheduled("lws_optimize_start_filebased_preload") && $this->lwsop_check_option('filebased_cache')['state'] === "true") {
                wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
            }
        }

        // If it got installed by the LWS Auto-installer, then proceed to activate it on recommended by default
        $auto_installer_mode = get_option('lws_from_autoinstall_optimize', false);
        if ($auto_installer_mode) {
            $this->lwsop_auto_setup_optimize();
            delete_option("lws_from_autoinstall_optimize");
            delete_option('lws_optimize_offline');
        }

        // Init the FileCache Class
        $this->lwsOptimizeCache = new LwsOptimizeFileCache($this);


        if (!$this->state) {
            // If the plugin was updated...
            add_action('plugins_loaded', [$this, 'lws_optimize_after_update_actions']);

            if (!in_array('lwscache/lwscache.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                if (!class_exists("LWSCache")) {
                    require_once LWS_OP_DIR . 'Classes/cache/class-lws-cache.php';
                }
                $config_array = get_option('lws_optimize_config_array', array(
                    'image_lazyload' => array('state' => true),
                    'bg_image_lazyload' => array('state' => true),
                    'iframe_video_lazyload' => array('state' => true),
                ));

                if (!isset($config_array['cloudflare']['tools']['dynamic_cache']) || $config_array['cloudflare']['tools']['dynamic_cache'] === false) {
                    if (!function_exists("run_lws_cache")) {
                        function run_lws_cache()
                        {
                            global $lws_cache;

                            $lws_cache = new \LWSCache();
                            $lws_cache->run();

                            // Load WP-CLI command.
                            if (defined('WP_CLI') && \WP_CLI) {

                                if (!class_exists("LWSCache_WP_CLI_Command")) {
                                    require_once LWS_OP_DIR . 'Classes/cache/class-lws-cache-wp-cli-command.php';
                                }
                                \WP_CLI::add_command('lws-cache', 'LWSCache_WP_CLI_Command');
                            }
                        }
                        run_lws_cache();
                    }
                }
            }

            // If Memcached is activated but there is no object-cache.php, add it back
            if ($this->lwsop_check_option('memcached')['state'] === "true") {
                // Deactivate Memcached if Redis is activated
                if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                    $this->optimize_options['memcached']['state'] = "false";
                } else {
                    if (class_exists('Memcached')) {
                        $memcached = new \Memcached();
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
                    exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - GZIP COMPRESSION/,/#END LWS OPTIMIZE - GZIP COMPRESSION/ d' '" . escapeshellarg(ABSPATH) . "/.htaccess'", $eOut, $eCode);
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
                LwsOptimizeLazyLoading::startActionsImage();
            }

            // If the lazyloading of images has been activated on the website
            if ($this->lwsop_check_option('iframe_video_lazyload')['state'] === "true") {
                LwsOptimizeLazyLoading::startActionsIframe();
            }

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });

            add_filter('lws_optimize_clear_filebased_cache', [$this, 'lws_optimize_clean_filebased_cache']);
            add_action('lws_optimize_start_filebased_preload', [$this, 'lws_optimize_start_filebased_preload']);

            if ($this->lwsop_check_option("maintenance_db")['state'] == "true" && !wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_schedule_event(time(), 'weekly', 'lws_optimize_maintenance_db_weekly');
            }

            // Add new schedules time for crons
            add_filter('cron_schedules', [$this, 'lws_optimize_timestamp_crons']);

            // If the auto-convert feature is activated, then prepare the hooks
            if ($this->lwsop_check_option("auto_update")['state'] == "true") {
                $this->lwsImageOptimization = new LwsOptimizeImageOptimization(true);
            } else {
                $this->lwsImageOptimization = new LwsOptimizeImageOptimization(false);
            }

            // Activate functions related to CloudFlare
            $this->cloudflare_manager = new LwsOptimizeCloudFlare();
            $this->cloudflare_manager->activate_cloudflare_integration();

            add_action("wp_ajax_lwsop_dump_dynamic_cache", [$this, "lwsop_dump_dynamic_cache"]);
            add_action("wp_ajax_lws_optimize_activate_cleaner", [$this, "lws_optimize_activate_cleaner"]);

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
        }

        add_action('init', [$this, "lws_optimize_init"]);
        add_action("wp_ajax_lws_optimize_do_pagespeed", [$this, "lwsop_do_pagespeed_test"]);

        // If the autopurge has been activated, add hooks that will clear specific cache on specific actions
        if (!$this->state && $this->lwsop_check_option("autopurge")['state'] == "true") {
            $autopurge_manager = new LwsOptimizeAutoPurge();
            $autopurge_manager->start_autopurge();
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

                add_action("wp_ajax_lwsop_regenerate_cache", [$this, "lwsop_regenerate_cache"]);

                add_action("wp_ajax_lwsop_convert_all_images", [$this, "lwsop_convert_all_media"]);

                // Fetch an array containing every URLs that should get purged each time an autopurge starts
                add_action("wp_ajax_lwsop_get_specified_url", [$this, "lwsop_specified_urls_fb"]);
                // Update the specified-URLs array
                add_action("wp_ajax_lwsop_save_specified_url", [$this, "lwsop_save_specified_urls_fb"]);
                // Fetch an array containing every URLs that should not be cached
                add_action("wp_ajax_lwsop_get_excluded_url", [$this, "lwsop_exclude_urls_fb"]);
                add_action("wp_ajax_lwsop_get_excluded_cookies", [$this, "lwsop_exclude_cookies_fb"]);
                // Update the excluded-URLs array
                add_action("wp_ajax_lwsop_save_excluded_url", [$this, "lwsop_save_urls_fb"]);
                add_action("wp_ajax_lwsop_save_excluded_cookies", [$this, "lwsop_save_cookies_fb"]);

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
                add_action("wp_ajax_lwsop_start_autoconvertion", [$this, "lwsop_start_autoconvertion"]);
                add_action("wp_ajax_lwsop_stop_autoconvertion", [$this, "lwsop_stop_autoconvertion"]);

                add_action("wp_ajax_lwsop_check_convert_images_update", [$this, "lwsop_check_convert_images_update"]);
            }

            if (!$this->state && isset($this->lwsop_check_option('filebased_cache')['data']['preload']) && $this->lwsop_check_option('filebased_cache')['data']['preload'] === "true") {
                add_action("wp_ajax_lwsop_check_preload_update", [$this, "lwsop_check_preload_update"]);
            }

            add_action("wp_ajax_lws_clear_fb_cache", [$this, "lws_optimize_clear_cache"]);
            add_action("wp_ajax_lws_clear_opcache", [$this, "lws_clear_opcache"]);
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
        load_textdomain('lws-optimize', LWS_OP_DIR . '/languages/lws-optimize-' . determine_locale() . '.mo');

        // Add all options referring to the WPAdmin page or the AdminBar
        $admin_manager = new LwsOptimizeManageAdmin();
        $admin_manager->manage_options();

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

    public function lws_optimize_after_update_actions() {
        if (get_transient('wp_lwsoptimize_post_update') && !$this->state) {
            // Remove old, unused options
            delete_option('lwsop_do_not_ask_again');
            delete_transient('lwsop_remind_me');
            delete_option('lws_optimize_offline');
            delete_option('lws_opti_memcaching_on');
            delete_option('lwsop_autopurge');
            delete_option('lws_op_deactivated');
            delete_option('lws_op_change_max_width_media');
            delete_option('lws_op_fb_cache');
            delete_option('lws_op_fb_exclude');
            delete_option('lws_op_fb_preload_state');

            delete_option('lws_optimize_preload_is_ongoing');
            global $wpdb;

            // Force deactivate memcached for everyone
            $options = get_option('lws_optimize_config_array', []);
            $options['memcached']['state'] = false;
            update_option('lws_optimize_config_array', $options);

            if (isset($options['htaccess_rules']['state']) && $options['htaccess_rules']['state'] == "true") {
                $this->lws_optimize_set_cache_htaccess();
            }
            $this->lws_optimize_reset_header_htaccess();

            apply_filters("lws_optimize_clear_filebased_cache", false);

            $all_medias_to_convert = get_option('lws_optimize_images_convertion', []);
            $posts = get_posts(['post_type' => array('page', 'post')]);
            foreach ($posts as $post) {
                $content = $post->post_content;
                foreach ($all_medias_to_convert as $image) {
                    $actual = explode('.', $image['original_url']);
                    array_pop($actual);
                    $actual = implode('.', $actual) . "." . $image['extension'];
                    $original = $image['original_url'];

                    if (strpos($content, $actual)) {
                        $content = str_replace($actual, $original, $content);
                    }
                }

                $data = ['post_content' => $content];
                $where = ['ID' => $post->ID];
                $wpdb->update($wpdb->prefix . 'posts', $data, $where);
            }

            delete_transient('wp_lwsoptimize_post_update');
        }
    }

    /**
     * Add a new timestamp for crons
     */
    public function lws_optimize_timestamp_crons($schedules)
    {
        foreach ($GLOBALS['lws_optimize_cache_timestamps'] as $code => $schedule) {
            $schedules[$code] = array(
                'interval' => $schedule[0],
                'display' => $schedule[1]
            );
        }

        foreach ($GLOBALS['lws_optimize_cache_timestamps'] as $code => $schedule) {
            $schedules['lws_three_minutes'] = array(
                'interval' => 120,
                'display' => __('Every 2 Minutes', 'lws-optimize')
            );
        }

        foreach ($GLOBALS['lws_optimize_cache_timestamps'] as $code => $schedule) {
            $schedules['lws_minute'] = array(
                'interval' => 60,
                'display' => __('Every Minutes', 'lws-optimize')
            );
        }

        return $schedules;
    }

    public function lwsop_dump_dynamic_cache()
    {
        check_ajax_referer('lwsop_empty_d_cache_nonce', '_ajax_nonce');
        wp_die($this->lwsop_dump_all_dynamic_caches());
    }

    public function lwsop_dump_all_dynamic_caches()
    {
        global $lws_cache_admin;

        if (!class_exists("LWSCache_Admin")) {
            require_once LWS_OP_DIR . 'Classes/cache/class-lws-cache-admin.php';
        }
        if (!class_exists("Purger")) {
            require_once LWS_OP_DIR . 'Classes/cache/class-purger.php';
        }
        $lws_cache_admin = new \LWSCache_Admin("LWSCache", "1.0");

        // Defines global variables.
        if (!empty($lws_cache_admin->options['cache_method']) && 'enable_redis' === $lws_cache_admin->options['cache_method']) {
            if (class_exists('Redis')) { // Use PHP5-Redis extension if installed.
                if (class_exists('PhpRedis_Purger')) {
                    require_once LWS_OP_DIR . 'Classes/cache/class-phpredis-purger.php';
                }
                $nginx_purger = new \PhpRedis_Purger();
            } else {
                if (class_exists('Predis_Purger')) {
                    require_once LWS_OP_DIR . 'Classes/cache/class-predis-purger.php';
                }
                $nginx_purger = new \Predis_Purger();
            }
        } elseif (
            isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1' && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] == 'varnish'
        ) {
            if (!class_exists("Varnish_Purger")) {
                require_once LWS_OP_DIR . 'Classes/cache/class-varnish-purger.php';
            }
            if (isset($_SERVER['HTTP_X_CDN_INFO']) && $_SERVER['HTTP_X_CDN_INFO'] == "ipxchange") {
                $nginx_purger = new \Varnish_Purger(true);
            } else {
                $nginx_purger = new \Varnish_Purger();
            }
        } elseif (
            isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1' && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] == 'litespeed'
        ) {
            if (!class_exists("Varnish_Purger")) {
                require_once LWS_OP_DIR . 'Classes/cache/class-litespeed-purger.php';
            }
            $nginx_purger = new \Litespeed_Purger();
        } else {
            if (!class_exists("FastCGI_Purger")) {
                require_once LWS_OP_DIR . 'Classes/cache/class-fastcgi-purger.php';
            }
            $nginx_purger = new \FastCGI_Purger();
        }

        $return = $nginx_purger->purge_all();

        return (json_encode(array('code' => "SUCCESS", 'data' => $return), JSON_PRETTY_PRINT));
    }

    public function lwsop_remove_opcache()
    {
        if (function_exists("opcache_reset")) {
            opcache_reset();
        }
        return (json_encode(array('code' => "SUCCESS", 'data' => "Done"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_activate_cleaner()
    {
        check_ajax_referer('lwsop_activate_cleaner_nonce', '_ajax_nonce');
        if (!isset($_POST['state'])) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        if ($_POST['state'] == "true") {
            $plugin = activate_plugin("lws-cleaner/lws-cleaner.php");
            $state = "true";
        } else {
            $plugin = deactivate_plugins("lws-cleaner/lws-cleaner.php");
            $state = "false";
        }

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $plugin, 'state' => $state), JSON_PRETTY_PRINT));
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
                $loc = $entry->loc;
                $tmp_loc = explode('.', $loc);
                if (array_pop($tmp_loc) == "xml") {
                    $data = $this->fetch_url_sitemap((string)$loc, $data);
                }
            }
        }

        return $data;
    }

    public function lwsop_preload_fb()
    {
        check_ajax_referer('update_fb_preload', '_ajax_nonce');

        delete_option('lws_optimize_sitemap_urls');
        delete_option('lws_optimize_preload_is_ongoing');

        if (isset($_POST['action']) && isset($_POST['state'])) {
            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;

            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['preload'] = sanitize_text_field($_POST['state']);
            $this->optimize_options['filebased_cache']['preload_amount'] =  $amount;
            $this->optimize_options['filebased_cache']['preload_done'] =  0;
            $this->optimize_options['filebased_cache']['preload_ongoing'] = sanitize_text_field($_POST['state']);


            $sitemap = get_sitemap_url("index");

            stream_context_set_default( [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $headers = get_headers($sitemap);
            if (substr($headers[0], 9, 3) == 404) {
                $sitemap = home_url('/sitemap_index.xml');
            }

            $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
            $time = $urls['time'] ?? 0;

            // It has been more than an hour since the latest fetch from the sitemap
            if ($time +  3600 < time()) {
                // We get the freshest data
                $urls = $this->fetch_url_sitemap($sitemap, []);
                if (!empty($urls)) {
                    update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
                }
            } else {
                // We get the ones currently saved in base
                $urls = $urls['urls'] ?? [];
            }

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

                if (!wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
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

    // Start regenerating file-based cache (from 0 instead of just adding)
    // Useful if stats are broken for some reasons
    public function lwsop_regenerate_cache() {
        check_ajax_referer('lws_regenerate_nonce_cache_fb', '_ajax_nonce');
        $stats = $this->lwsop_recalculate_stats('regenerate');
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $stats)));

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

        if ($state == "true") {
            $this->lws_optimize_reset_header_htaccess();
        } else {
            exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - EXPIRE HEADER/,/#END LWS OPTIMIZE - EXPIRE HEADER/ d' '" . escapeshellarg(ABSPATH) . "/.htaccess'", $eOut, $eCode);
            if ($eCode != 0) {
                error_log(json_encode(array('code' => 'ERR_SED', 'data' => "LWSOptimize | GZIP | An error occured when using sed in .htaccess")));
            }
        }

        if (isset($this->optimize_options['htaccess_rules']['state']) && $this->optimize_options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
        }

        // Just return if the array did not change
        if ($temp_array == $this->optimize_options) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element)), JSON_PRETTY_PRINT);
        }

        $return = update_option('lws_optimize_config_array', $this->optimize_options);
        // If correctly added and updated
        if ($return) {
            // Remove the dynamic cache when the cache change state
            $this->lwsop_dump_all_dynamic_caches();
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
        }
    }

    public function lws_optimize_set_cache_htaccess() {
        // Get all username of admin users
        $usernames = get_users(array("role" => "administrator", "fields" => array("user_login")));
        $admin_users = [];
        foreach ($usernames as $user) {
            $admin = sanitize_user(wp_unslash($user->user_login), true);
            $admin_users[] = preg_replace("/\s/", "%20", $admin);
        }

        // Get domain name of the current website
        $urlparts = wp_parse_url(home_url());
        $http_host = $urlparts['host'];
        $http_path = $urlparts['path'];

        // Get path to the cache directory
        $path = "cache";
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
            $cache_path = "/cache/lwsoptimize/$additional" . $path;
            $cache_path_mobile = "/cache/lwsoptimize/$additional" . "cache-mobile";
        } else {
            $cache_path = "/cache/lwsoptimize/cache";
            $cache_path_mobile = "/cache/lwsoptimize/cache-mobile";
        }

        // Current date at the time of modification
        $current_date = date("d/m/Y H:i:s", time());

        // Path to .htaccess
        $htaccess = ABSPATH . "/.htaccess";

        // Get the directory (wp-content, by default)
        $wp_content_directory = explode('/', WP_CONTENT_DIR);
        $wp_content_directory = array_pop($wp_content_directory);

        // Remove the htaccess related to caching
        exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - CACHING/,/#END LWS OPTIMIZE - CACHING/ d' '" . escapeshellarg(ABSPATH) . "/.htaccess'", $eOut, $eCode);
        // Content
        $hta = '';

        // Add instructions to load cache file without starting PHP
        $hta .= "#Last Modification: $current_date\n";
        $hta .= "<IfModule mod_rewrite.c>"."\n";
        $hta .= "RewriteEngine On"."\n";
        $hta .= "RewriteBase " . rtrim($http_path, '/') . "/\n";

        // If connected users have their own cache
        if ($this->lwsop_check_option('cache_logged_user')['state'] === "false") {
            $hta .= "## Connected desktop ##\n";
            $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
            $hta .= "RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]\n";
            $hta .= "RewriteCond %{HTTP_USER_AGENT} !^.*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
            $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path$http_path/$1index_2.html -f\n";
            $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path$http_path/$1index_2.html [L]\n\n";

            // If connected users on mobile have their own cache
            if ($this->lwsop_check_option('cache_mobile_user')['state'] === "false") {
                $hta .= "## Connected mobile ##\n";
                $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
                $hta .= "RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]\n";
                $hta .= "RewriteCond %{HTTP_USER_AGENT} .*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
                $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path_mobile$http_path/$1index_2.html -f\n";
                $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path_mobile$http_path/$1index_2.html [L]\n\n";
            }
        }

        // If not connected users on mobile have cache
        if ($this->lwsop_check_option('cache_mobile_user')['state'] === "false") {
            $hta .= "## Anonymous mobile ##\n";
            $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
            $hta .= "RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]\n";
            $hta .= "RewriteCond %{HTTP_USER_AGENT} .*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
            $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path_mobile$http_path/$1index_0.html -f\n";
            $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path_mobile$http_path/$1index_0.html [L]\n\n";
        }

        // Non connected and non-mobile users
        $hta .= "## Anonymous desktop ##\n";
        $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
        $hta .= "RewriteCond %{HTTP:Cookie} !wordpress_logged_in [NC]\n";
        $hta .= "RewriteCond %{HTTP_USER_AGENT} !^.*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
        $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path$http_path/$1index_0.html -f\n";
        $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path$http_path/$1index_0.html [L]\n\n";

        $hta .= "Header set Edge-Cache-Platform 'lwsoptimize' env=REDIRECT_STATUS\n";
        // Remove eTag to fix broken 304 Not Modified
        $hta .= "FileETag None\nHeader unset ETag\n";
        $hta .= "</IfModule>\n";

        $hta = "#LWS OPTIMIZE - CACHING\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n $hta#END LWS OPTIMIZE - CACHING\n\n";

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

    public function lws_optimize_basic_htaccess_conditions($http_host, $admin_users) {
        $hta = '';

        // No redirections for special query strings
        $hta .= "RewriteCond %{QUERY_STRING} !^((gclid|fbclid|y(ad|s)?clid|utm_(source|medium|campaign|content|term)=[^&]+)+)$ [NC]\n";

        // Only if on the right domain
        $hta .= "RewriteCond %{HTTP_HOST} ^$http_host\n";

        // Do not redirect to show cache for admins (at the time of the modification)
        $hta .= "RewriteCond %{HTTP:Cookie} !wordpress_logged_in_[^\=]+\=".implode("|", $admin_users)."\n";

        // Do nothing if preloading
        $hta .= "RewriteCond %{HTTP_USER_AGENT} '!(LWS_Optimize_Preload|LWS_Optimize_Preload_Mobile)' [NC]\n";

        // // Check if HTTPS
        // if(preg_match("/^https:\/\//", home_url())){
        //     $hta .= "RewriteCond %{HTTPS} =on\n";
        // }

        // Not on POST (only GET)
        $hta .= "RewriteCond %{REQUEST_METHOD} !POST"."\n";

        // No redirect if consecutive "/" in request
        $hta .= "RewriteCond %{REQUEST_URI} !(\/){2,}\n";
        $hta .= "RewriteCond %{THE_REQUEST} !(\/){2,}\n";

        if (!$this->lwsop_plugin_active('custom-permalinks/custom-permalinks.php') && $permalink_structure = get_option('permalink_structure')) {
            if(preg_match("/\/$/", $permalink_structure)){
                $hta .= "RewriteCond %{REQUEST_URI} \/$"."\n";
            } else {
                $hta .= "RewriteCond %{REQUEST_URI} ![^\/]+\/$"."\n";
            }
        } else {
            $hta .= "RewriteCond %{REQUEST_URI} ![^\/]+\/$"."\n";
        }

        $hta .= "RewriteCond %{QUERY_STRING} !.+\n";
        $hta .= "RewriteCond %{HTTP:Cookie} !comment_author_"."\n";
        $hta .= 'RewriteCond %{HTTP:Profile} !^[a-z0-9\"]+ [NC]'."\n";

        return $hta;
    }

    /**
     * Set the expiration headers in the .htaccess. Will remove it before adding it back.
     * If the cache is not active or an error occurs, headers won't be added
     */
    function lws_optimize_reset_header_htaccess() {
        $state = $this->optimize_options['filebased_cache']['state'] ?? "false";
        $timer = $this->optimize_options['filebased_cache']['timer'] ?? "lws_thrice_monthly";

        // Path to .htaccess
        $htaccess = ABSPATH . "/.htaccess";

        if ($state != "true") {
            exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - EXPIRE HEADER/,/#END LWS OPTIMIZE - EXPIRE HEADER/ d'" . escapeshellarg($htaccess) . "'", $eOut, $eCode);
            error_log(json_encode(array('code' => 'NOT_ACTIVATED', 'data' => $eOut)));
            return json_encode(array('code' => 'NOT_ACTIVATED', 'data' => $eOut));
        }

        switch ($timer) {
            case 'lws_daily':
                $date = '1 day';
                $cdn_date = "86400";
                break;
            case 'lws_weekly':
                $date = '7 days';
                $cdn_date = "604800";
                break;
            case 'lws_monthly':
                $date = '1 month';
                $cdn_date = "2592000";
                break;
            case 'lws_thrice_monthly':
                $date = '3 months';
                $cdn_date = "7776000";
                break;
            case 'lws_biyearly':
                $date = '6 months';
                $cdn_date = "15552000";
                break;
            case 'lws_yearly':
                $date = '1 year';
                $cdn_date = "31104000";
                break;
            case 'lws_two_years':
                $date = '2 years';
                $cdn_date = "62208000";
                break;
            case 'lws_never':
                $date = '3 years';
                $cdn_date = "93312000";
                break;
            default:
                $date = '3 months';
                $cdn_date = "7776000";
                break;
        }

        // Remove the old htaccess related to HEADER before adding it back updated
        if (!exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - EXPIRE HEADER/,/#END LWS OPTIMIZE - EXPIRE HEADER/ d'" . escapeshellarg($htaccess) . "'", $eOut, $eCode) || !$eCode) {
            error_log(json_encode(array('code' => 'NOT_REMOVED', 'data' => "[$eCode] - " . json_encode($eOut))));
        }


        // Set expiration date for the cache, based on selected value (in the "Cache" tab)
        $hta = "#LWS OPTIMIZE - EXPIRE HEADER\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n
        <IfModule mod_expires.c>
        ExpiresActive On
        AddOutputFilterByType DEFLATE application/json
        ExpiresByType image/jpg \"access $date\"
        ExpiresByType image/jpeg \"access $date\"
        ExpiresByType image/gif \"access $date\"
        ExpiresByType image/png \"access $date\"
        ExpiresByType image/svg \"access $date\"
        ExpiresByType image/x-icon \"access $date\"
        ExpiresByType text/css \"access $date\"
        ExpiresByType application/pdf \"access $date\"
        ExpiresByType application/javascript \"access $date\"
        ExpiresByType application/x-javascript \"access $date\"
        ExpiresByType application/x-shockwave-flash \"access $date\"
        ExpiresByType text/html A0
        ExpiresDefault \"access $date\"
        </IfModule>
        <FilesMatch \"index_[0-2]\.(html|htm)$\">
        <IfModule mod_headers.c>
            Header set Cache-Control \"public, max-age=0, no-cache, must-revalidate\"
            Header set CDN-Cache-Control \"public, maxage=$cdn_date\"
            Header set Pragma \"no-cache\"
            Header set Expires \"Mon, 29 Oct 1923 20:30:00 GMT\"
        </IfModule>
        </FilesMatch>

        #END LWS OPTIMIZE - EXPIRE HEADER\n\n";

        if (is_file($htaccess)) {
            $hta .= file_get_contents($htaccess);
        }

        if (($f = fopen($htaccess, 'w+')) !== false) {
            if (!fwrite($f, $hta)) {
                fclose($f);
                error_log(json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | GZIP | .htaccess file is not writtable")));
                return json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | GZIP | .htaccess file is not writtable"));
            }
            fclose($f);
        } else {
            error_log(json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | GZIP | .htaccess file is not openable")));
            return json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | GZIP | .htaccess file is not openable"));
        }

        return json_encode(array('code' => 'SUCCESS', 'data' => ""));
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

        if ($fb_options['state'] == "true") {
           $this->lws_optimize_reset_header_htaccess();
        } else {
            exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - EXPIRE HEADER/,/#END LWS OPTIMIZE - EXPIRE HEADER/ d' '" . escapeshellcmd(ABSPATH) . "/.htaccess'", $eOut, $eCode);
            if ($eCode != 0) {
                error_log(json_encode(array('code' => 'ERR_SED', 'data' => "LWSOptimize | GZIP | An error occured when using sed in .htaccess")));
            }
        }

        if (isset($this->optimize_options['htaccess_rules']['state']) && $this->optimize_options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
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
        $tab = sanitize_text_field($_POST['data']['tab']);

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
            if ($fastest_cache_status === null) {
                $fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLED'] ?? null;
            }
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
                $this->optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $this->optimize_options);
                wp_die(json_encode(array('code' => "INCOMPATIBLE", "data" => "LWSCache is incompatible with this hosting. Use LWS.")), JSON_PRETTY_PRINT);
            }

            if ($lwscache_status == false && $fastest_cache_status === null) {
                $this->optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $this->optimize_options);
                wp_die(json_encode(array('code' => "PANEL_CACHE_OFF", "data" => "LWSCache is not activated on LWSPanel.")), JSON_PRETTY_PRINT);
            }

            if ($lwscache_status === null && $fastest_cache_status == false) {
                $this->optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $this->optimize_options);
                wp_die(json_encode(array('code' => "CPANEL_CACHE_OFF", "data" => "Varnish is not activated on cPanel.")), JSON_PRETTY_PRINT);
            }
        } elseif ($element == "maintenance_db") {
            if (wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
            }
            if ($state == "true") {
                wp_schedule_event(time() + 604800, 'weekly', 'lws_optimize_maintenance_db_weekly');
            }
        } elseif ($element == "memcached") {
            if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                $this->optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $this->optimize_options);
                wp_die(json_encode(array('code' => "REDIS_ALREADY_HERE", 'data' => "FAILURE", 'state' => "unknown")));
            }
            if (class_exists('Memcached')) {
                $memcached = new \Memcached();
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
        } elseif ($element == "gzip_compression") {
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
        } elseif ($element == "htaccess_rules") {
            if ($state == "false") {
                exec("cd /htdocs/ | sed -i '/#LWS OPTIMIZE - CACHING/,/#END LWS OPTIMIZE - CACHING/ d' '" . escapeshellarg(ABSPATH) . "/.htaccess'", $eOut, $eCode);
            } else {
                $this->lws_optimize_set_cache_htaccess();
            }
        }

        // If the tab where the option comes from is frontend, we clear the cache
        // as those options needs the cache to be emptied to work properly
        if (isset($tab) && $tab == "frontend") {
            apply_filters("lws_optimize_clear_filebased_cache", false);
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

        if ($element == "cache_mobile_user" || $element == "cache_logged_user") {
            if (isset($this->optimize_options['htaccess_rules']['state']) && $this->optimize_options['htaccess_rules']['state'] == "true") {
                $this->lws_optimize_set_cache_htaccess();
            }
        }

        // If correctly added and updated
        if ($return) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'type' => $element)), JSON_PRETTY_PRINT);
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options, 'type' => $element)), JSON_PRETTY_PRINT);
        }
    }


    public function activateVarnishCache(bool $state = true) {
        $array = (explode('/', ABSPATH));
        $directory = implode('/', array($array[0], $array[1], $array[2]));
        $directory .= "/tmp/";
        $latestFile = null;
        $latestTime = 0;

        // Open the directory and read its contents
        if (is_dir($directory)) {
            $files = scandir($directory);

            foreach ($files as $file) {
                // Skip if it's not a file or doesn't start with "fc_token_api"
                if (!is_file($directory . '/' . $file) || strpos($file, 'fc_token_api') !== 0) {
                    continue;
                }

                // Get the file's modification time
                $fileTime = filemtime($directory . '/' . $file);

                // Check if this file is more recent than the current latest file
                if ($fileTime > $latestTime) {
                    $latestFile = $file;
                    $latestTime = $fileTime;
                }
            }
        }

        $api_key = file_get_contents($directory . '/' . $latestFile);
        wp_remote_post(
            "https://127.0.0.1:8443/api/domains/" . $_SERVER['HTTP_HOST'],
            array(
                'method'      => 'PUT',
                'headers'     => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => "application/x-www-form-urlencoded"),
                'body'          => array(
                    'template' => "default",
                    'cache-enabled' => $state,
                    'cache-engine' => 'varnish'
                ),
                'sslverify' => false
            )
        );
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
        delete_option('lws_optimize_sitemap_urls');
        $this->lwsop_recalculate_stats("all");
        delete_option('lws_optimize_preload_is_ongoing');
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_clear_opcache()
    {
        check_ajax_referer('clear_opcache_caching', '_ajax_nonce');
        $this->lwsop_remove_opcache();
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


    public function lws_optimize_delete_directory($dir, $class_this)
    {
        if (!file_exists($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                $this->lws_optimize_delete_directory("$dir/$file", $class_this);
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

    /**
     * Clean the given directory. If no directory is given, remove /cache/lwsoptimize/
     */
    public function lws_optimize_clean_filebased_cache($directory = false)
    {
        if ($directory) {
            $directory = esc_url($directory);
            if (is_dir($directory)) {
                $this->lws_optimize_delete_directory($directory, $this);
            }
        } else {
            $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
            $this->lwsop_recalculate_stats("all");
        }

        if ($array = get_option('lws_optimize_config_array', [])) {
            $array['filebased_cache']['preload_done'] =  0;
            if (isset($array['filebased_cache']['preload']) && $array['filebased_cache']['preload'] == "true") {
                $array['filebased_cache']['preload_ongoing'] = "true";
                if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                }

                if (!wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
            }
        }

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        $this->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
        $this->lwsop_dump_all_dynamic_caches();
        $this->lwsop_remove_opcache();
    }

    /**
     * Preload the file-based cache. Get all URLs from the sitemap and cache each of them
     */
    public function lws_optimize_start_filebased_preload()
    {
        $ongoing = get_option('lws_optimize_preload_is_ongoing', false);
        if ($ongoing) {
            // Do not continue if the cron is ongoing BUT force if it has been ~10m
            if (time() - $ongoing > 36000) {
                delete_option('lws_optimize_preload_is_ongoing');
            } else {
                exit;
            }
        }

        update_option('lws_optimize_preload_is_ongoing', time());

        $lws_filebased = new LwsOptimizeFileCache($GLOBALS['lws_optimize']);

        $sitemap = get_sitemap_url("index");

        stream_context_set_default( [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $headers = get_headers($sitemap);
        if (substr($headers[0], 9, 3) == 404) {
            $sitemap = home_url('/sitemap_index.xml');
        }

        $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $time = $urls['time'] ?? 0;

        // It has been more than an hour since the latest fetch from the sitemap
        if ($time +  3600 < time()) {
            // We get the freshest data
            $urls = $this->fetch_url_sitemap($sitemap, []);
            if (!empty($urls)) {
                update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
            }
        } else {
            // We get the ones currently saved in base
            $urls = $urls['urls'] ?? [];
        }

        if ($array = get_option('lws_optimize_config_array', [])) {
            $max_try = intval($array['filebased_cache']['preload_amount'] ?? 5);
            $current_try = 0;

            $saved_urls = $array['filebased_cache']['saved_urls'] ?? [];

            $done = $array['filebased_cache']['preload_done'] ?? 0;

            $first_run = $done == 0 ? true : false;

            $userAgents = [
                'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36; compatible; LWSOptimizePreload/1.0',
                'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1; compatible; LWSOptimizePreload/1.0'
            ];

            foreach ($urls as $key => $url) {
                // Don't do more than $max_try
                if ($current_try < $max_try) {
                    $query = parse_url($url, PHP_URL_QUERY);

                    // Returns a string if the URL has parameters or NULL if not
                    if ($query) {
                        $url .= '&nocache=' . time();
                    } else {
                        $url .= '?nocache=' . time();
                    }

                    $parsed_url = parse_url($url);
                    $parsed_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';

                    $path = $lws_filebased->lwsop_set_cachedir($parsed_url);
                    $path_mobile = $lws_filebased->lwsop_set_cachedir($parsed_url, true);


                    if (!$path) {
                        // Failed to set a PATH to save the file ; do not cache
                        $saved_urls[$url] = false;
                        unset($urls[$key]);
                        continue;
                    }

                    $file_exists = glob($path . "index*") ?? [];

                    if (!empty($file_exists)) {
                        // If the cache for this file already exists but this is the first run of the cron, consider it done
                        if ($first_run) {
                            $saved_urls[$url] = true;
                            $done++;
                        }
                    } else {
                        foreach ($userAgents as $type => $agent) {
                            // Fetch the page content
                            $context = stream_context_create([
                                'http' => [
                                    'header' => "User-Agent: $agent\r\n" .
                                                "Cache-Control: no-cache, no-store, must-revalidate\r\n" .
                                                "Pragma: no-cache\r\n" .
                                                "Expires: 0\r\n",
                                    'follow_location' => 1
                                ]
                            ]);

                            file_get_contents($url, false, $context);
                            sleep(1);
                        }

                        $file_exists = glob($path . "index*") ?? [];

                        // If the cache has been created, all good
                        // Otherwise, we consider that the file should not be cached (like some WC pages)
                        if (!empty($file_exists)) {
                            $done++;
                            $current_try++;
                            $saved_urls[$url] = true;
                        } else {
                            $saved_urls[$url] = false;
                            unset($urls[$key]);
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
            $array['filebased_cache']['preload_quantity'] = count($urls);
            $array['filebased_cache']['saved_urls'] = $saved_urls;

            update_option('lws_optimize_config_array', $array);
            delete_option('lws_optimize_preload_is_ongoing');
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

            $this->optimize_options['filebased_cache']['specified'] = $urls;

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

    public function lwsop_exclude_cookies_fb()
    {
        check_ajax_referer('lwsop_get_excluded_cookies_nonce', '_ajax_nonce');
        if (isset($this->optimize_options['filebased_cache']) && isset($this->optimize_options['filebased_cache']['exclusions_cookies'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache']['exclusions_cookies'], 'domain' => site_url()), JSON_PRETTY_PRINT));
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

    public function lwsop_save_cookies_fb()
    {
        // Add all Cookies to an array, but ignore empty cookies
        // If all fields are empty, remove the option from DB
        check_ajax_referer('lwsop_save_excluded_cookies_nonce', '_ajax_nonce');

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
            $this->optimize_options['filebased_cache']['exclusions_cookies'] = $urls;

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
        } catch (\Exception $e) {
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
                $css_file = intval($data['css']['file']);
                $css_size = intval($data['css']['size']);

                $js_file = intval($data['js']['file']);
                $js_size = intval($data['js']['size']);

                $html_file = intval($data['html']['file']);
                $html_size = intval($data['html']['size']);

                if (!empty($css_file) && !empty($css_size)) {
                    // Cannot have a negative number
                    if ($css_file < 0) {
                        $css_file = 0;
                    }
                    if ($css_size < 0) {
                        $css_size = 0;
                    }

                    $stats['css']['amount'] += $css_file;
                    $stats['css']['size'] += $css_size;
                }

                if (!empty($js_file) && !empty($js_size)) {
                    // Cannot have a negative number
                    if ($js_file < 0) {
                        $js_file = 0;
                    }
                    if ($js_size < 0) {
                        $js_size = 0;
                    }

                    $stats['js']['amount'] += $js_file;
                    $stats['js']['size'] += $js_size;
                }

                if (!empty($html_file) && !empty($html_size)) {
                    // Cannot have a negative number
                    if ($html_file < 0) {
                        $html_file = 0;
                    }
                    if ($html_size < 0) {
                        $html_size = 0;
                    }

                    if ($is_mobile) {
                        $stats['mobile']['amount'] += $html_file;
                        $stats['mobile']['size'] += $html_size;
                    } else {
                        $stats['desktop']['amount'] += $html_file;
                        $stats['desktop']['size'] += $html_size;
                    }
                }
                break;
            case 'minus':
                $html_file = intval($data['html']['file'] ?? 0);
                $html_size = intval($data['html']['size'] ?? 0);

                if (!empty($html_file) && !empty($html_size)) {
                    // Cannot have a negative number
                    if ($html_file < 0) {
                        $html_file = 0;
                    }
                    if ($html_size < 0) {
                        $html_size = 0;
                    }

                    if ($is_mobile) {
                        $stats['mobile']['amount'] -= $html_file;
                        $stats['mobile']['size'] -= $html_size;
                    } else {
                        $stats['desktop']['amount'] -= $html_file;
                        $stats['desktop']['size'] -= $html_size;
                    }

                    if ($stats['mobile']['amount'] < 0) {
                        $stats['mobile']['amount'] = 0;
                    }
                    if ($stats['mobile']['size'] < 0) {
                        $stats['mobile']['size'] = 0;
                    }

                    if ($stats['desktop']['amount'] < 0) {
                        $stats['desktop']['amount'] = 0;
                    }
                    if ($stats['desktop']['size'] < 0) {
                        $stats['desktop']['size'] = 0;
                    }
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
            case 'regenerate':
                $paths = [
                    'desktop' => $this->lwsop_get_content_directory("cache"),
                    'mobile' => $this->lwsop_get_content_directory("cache-mobile"),
                    'css' => $this->lwsop_get_content_directory("cache-css"),
                    'js' => $this->lwsop_get_content_directory("cache-js")
                ];


                foreach ($paths as $type => $path) {
                    $totalSize = 0;
                    $fileCount = 0;
                    if (is_dir($path)) {
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($path),
                            \RecursiveIteratorIterator::SELF_FIRST
                        );

                        foreach ($iterator as $file) {
                            if ($file->isFile()) {
                                $totalSize += $file->getSize();
                                $fileCount++;
                            }
                        }
                    }

                    $stats[$type] = [
                        'amount' => $fileCount,
                        'size' => $totalSize
                    ];
                }
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
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}posts WHERE post_status = 'draft';");
                    $wpdb->query($query);
                    // Remove drafts
                    break;
                case 'revisions':
                    $query = $wpdb->prepare("DELETE FROM `{$wpdb->prefix}posts` WHERE post_type = 'revision';");
                    $wpdb->query($query);
                    // Remove revisions
                    break;
                case 'deleted_posts':
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}posts WHERE post_status = 'trash' && (post_type = 'post' OR post_type = 'page');");
                    $wpdb->query($query);
                    // Remove trashed posts/page
                    break;
                case 'spam_comments':
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}comments WHERE comment_approved = 'spam';");
                    $wpdb->query($query);
                    // remove spam comments
                    break;
                case 'deleted_comments':
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}comments WHERE comment_approved = 'trash';");
                    $wpdb->query($query);
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
                $options['cache_logged_user']['state'] = "true";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
                wp_schedule_event(time(), 'lws_thrice_monthly', 'lws_optimize_clear_filebased_cache');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
                break;
            case 'advanced':
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = "20";
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
                $options['cache_logged_user']['state'] = "true";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";

                update_option('lws_optimize_config_array', $options);
                break;
            case 'full':
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = "30";
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
                $options['cache_mobile_user']['state'] = "false";
                $options['cache_logged_user']['state'] = "true";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";

                update_option('lws_optimize_config_array', $options);
                break;
            default:
                break;
        }

        $this->lws_optimize_set_cache_htaccess();
        $this->lws_optimize_reset_header_htaccess();

        apply_filters("lws_optimize_clear_filebased_cache", false);
        $this->lwsop_recalculate_stats("all");
    }

    public function lwsop_convert_all_media()
    {
        check_ajax_referer('lwsop_convert_all_images_nonce', '_ajax_nonce');

        // Fetch data from the form
        $quality = $_POST['data']['quality'] != null ? sanitize_text_field($_POST['data']['quality']) : "balanced";
        $keepcopy = $_POST['data']['keepcopy'] != null ? sanitize_text_field($_POST['data']['keepcopy']) : "keep";
        $mimetypes = $_POST['data']['mimetypes'] != null ? $_POST['data']['mimetypes'] : ['jpeg', 'jpg'];
        $size = $_POST['data']['size'] != null ? intval($_POST['data']['size']) : 2560;

        if ($keepcopy != "keep") {
            $keepcopy = "not_keep";
        }

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

        // Fetch and store all necessary infos to proceed with the conversion
        $data = [
            'convertion_format' => "webp",
            'convertion_keeporiginal' => $keepcopy,
            'convertion_quality' => $quality,
            'convertion_amount' => 15,
            'image_format' => $authorized_types,
            'image_maxsize' => $size,
            'ongoing' => true
        ];

        // Add the data to the database for future access by the cron
        update_option('lws_optimize_all_media_convertion', $data);

        $current_data = $this->lwsop_update_current_media_convertion_database();

        // Update the stats about the current convertion
        update_option('lws_optimize_current_convertion_stats', ['type' => "webp", 'original' => $current_data['max'], 'converted' => $current_data['converted']]);

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
            $media_data['convertion_quality'] ?? "balanced",
            $media_data['convertion_amount'] ?? 10,
            $media_data['image_maxsize'] ?? 2560,
        );

        // If no attachments got converted, stop the cron here
        $response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $response['data'];

            // Update the data array with info on what has been done and what is left
            $options = [
                'latest_time' => time(),
                'data' => [
                    'max' => $data['max'] ?? 0,
                    'to_convert' => $data['to_convert'] ?? 0,
                    'converted' => $data['converted'] ?? 0,
                    'left' => $data['left'] ?? 0,
                    'convert_type' => $media_data['convertion_format'] ?? '-'
                ]
            ];
            update_option('lws_optimize_current_media_convertion', $options);

            $stats = $this->lwsop_update_current_media_convertion_database();
            if ($stats['left'] == 0) {
                apply_filters("lws_optimize_clear_filebased_cache", false);
                wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
            }
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Remove the cron for the convertion of all medias, stopping the process
     */
    public function lws_optimize_stop_convertion()
    {
        check_ajax_referer('lwsop_stop_convertion_nonce', '_ajax_nonce');
        $data = get_option('lws_optimize_all_media_convertion');

        $data['ongoing'] = false;
        update_option('lws_optimize_all_media_convertion', $data);

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


    public function lws_optimize_get_revertion_stats()
    {
        $images = get_option('lws_optimize_images_convertion', []);
        $to_unconvert = [];
        foreach ($images as $image) {
            if (!isset($image['converted']) || !isset($image['original_path']) || !isset($image['to_keep'])) {
                continue;
            }

            // If the image is converted, has the original file and is supposed to be kept
            if ($image['converted'] && file_exists($image['original_path']) && $image['to_keep']) {
                $to_unconvert[] = $image;
            }
        }

        return $to_unconvert;
    }

    /**
     * Revert all original (and saved) images to normal
     */
    public function lws_optimize_revert_convertion()
    {
        check_ajax_referer('lwsop_revert_convertion_nonce', '_ajax_nonce');

        // Also deactivate the convertion before starting the deconvertion, to prevent errors
        wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
        wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");

        $images = get_option('lws_optimize_images_convertion', []);
        $to_unconvert = [];
        foreach ($images as $image) {
            // If the original file of the image still exists, the option to keep it is activated and the image is converted or has been converted by our plugin
            if (((isset($image['converted']) && $image['converted']) || (isset($image['previously_converted']) && $image['previously_converted'])) && isset($image['original_path']) && file_exists($image['original_path']) && isset($image['to_keep']) && $image['to_keep']) {
                // We add it for deconvertion
                $to_unconvert[] = $image;
            }
        }

        wp_schedule_event(time(), "lws_minute", "lwsop_revertOptimization");
        wp_die(json_encode(array('code' => "SUCCESS", "data" => count($to_unconvert), 'domain' => site_url())), JSON_PRETTY_PRINT);
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
                // Remove the cache folder
                apply_filters("lws_optimize_clear_filebased_cache", false);
            }
        }
    }

    /**
     * Configurate the autoconvertion
     */
    public function lwsop_start_autoconvert_media()
    {
        check_ajax_referer('lwsop_convert_all_images_on_upload_nonce', '_ajax_nonce');

        // Fetch data from the form
        $quality = $_POST['data']['quality'] != null ? sanitize_text_field($_POST['data']['quality']) : "balanced";
        $mimetypes = $_POST['data']['mimetypes'] != null ? $_POST['data']['mimetypes'] : ['jpeg', 'jpg'];
        $size = $_POST['data']['size'] != null ? intval($_POST['data']['size']) : 2560;

        $authorized_types = [];
        foreach ($mimetypes as $mimetype) {
            $authorized_types[] = sanitize_text_field($mimetype);
        }

        // Fetch and store all necessary infos to proceed with the conversion
        $data = [
            'auto_convertion_format' => "webp",
            'auto_convertion_quality' => $quality,
            'auto_image_format' => $authorized_types,
            'auto_image_maxsize' => $size,
        ];

        $this->optimize_options['auto_update'] = $data;

        update_option('lws_optimize_config_array', $this->optimize_options);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Start the auto-convert-on-upload feature
     */
    public function lwsop_start_autoconvertion()
    {
        check_ajax_referer('lwsop_start_autoconvertion_nonce', '_ajax_nonce');

        $this->optimize_options['auto_update'] = array_merge([
            'auto_convertion_format' => "webp",
            'auto_convertion_quality' => "balanced",
            'auto_image_format' => ['jpeg', 'jpg'],
            'auto_image_maxsize' => 2560,
        ], $this->optimize_options['auto_update']);

        $this->optimize_options['auto_update']['state'] = "true";

        update_option('lws_optimize_config_array', $this->optimize_options);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Stop the auto-convert-on-upload feature
     */
    public function lwsop_stop_autoconvertion()
    {
        check_ajax_referer('lwsop_stop_autoconvertion_nonce', '_ajax_nonce');

        $this->optimize_options['auto_update']['state'] = "false";

        update_option('lws_optimize_config_array', $this->optimize_options);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Check and return the state of the preloading
     */
    public function lwsop_check_preload_update()
    {
        check_ajax_referer('lwsop_check_for_update_preload_nonce', '_ajax_nonce');

        $sitemap = get_sitemap_url("index");
        stream_context_set_default( [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $headers = get_headers($sitemap);
        if (substr($headers[0], 9, 3) == 404) {
            $sitemap = home_url('/sitemap_index.xml');
        }

        $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $time = $urls['time'] ?? 0;

        // It has been more than an hour since the latest fetch from the sitemap
        if ($time + 300 < time()) {
            // We get the freshest data
            $urls = $this->fetch_url_sitemap($sitemap, []);
            if (!empty($urls)) {
                update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
            }
        } else {
            // We get the ones currently saved in base
            $urls = $urls['urls'] ?? [];
        }

        $done = 0;

        if (empty($urls)){
            wp_die(json_encode(array('code' => "ERROR", "data" => $sitemap, 'message' => "Failed to get some of the datas", 'domain' => site_url())), JSON_PRETTY_PRINT);
        }

        foreach ($urls as $url) {
            $parsed_url = parse_url($url);
            $parsed_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';
            $path = $this->lwsOptimizeCache->lwsop_set_cachedir($parsed_url);

            $file_exists = glob($path . "index*") ?? [];
            if (!empty($file_exists)) {
                $done++;
            }
        }

        $this->optimize_options['filebased_cache']['preload_done'] = $done;
        $this->optimize_options['filebased_cache']['preload_ongoing'] = $this->optimize_options['filebased_cache']['preload_quantity'] - $done == 0 ? "false" : "true";

        update_option('lws_optimize_config_array', $this->optimize_options);

        $next = wp_next_scheduled('lws_optimize_start_filebased_preload') ?? null;
        if ($next != null) {
            $next = get_date_from_gmt(date('Y-m-d H:i:s', $next), 'Y-m-d H:i:s');
        } else {
            if (!wp_next_scheduled('lws_optimize_start_filebased_preload')) {
                wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
            }
        }

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
    public function lwsop_update_current_media_convertion_database()
    {
        $data = get_option('lws_optimize_all_media_convertion');

        $keepcopy = $data['convertion_keeporiginal'] ?? null;
        $authorized_types = $data['image_format'] ?? null;

        if ($keepcopy == null || $authorized_types == null) {
            return [
                'max' => 0,
                'to_convert' => 0,
                'converted' => 0,
                'left' => 0,
                'type' => "-",
                'gains' => "0%"
            ];
        }

        $all_medias_to_convert =  get_option('lws_optimize_images_convertion', []);

        $original = 0;
        $converted = 0;

        $gained_size = 1;
        $original_size = 1;

        // Get all images
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => null,
            'post_parent' => null
        );
        $attachments = get_posts($args);

        $type = "webp";

        // Go through each image, verify its status and update the array
        // Also fetch $data on how many images are $converted and how many are still $original
        foreach ($attachments as $attachment) {

            // Get the URL and PATH of the image
            $attachment_url = wp_get_attachment_url($attachment->ID);
            $attachment_path = get_attached_file($attachment->ID);

            $tmp = explode('.', $attachment_url);
            array_pop($tmp);
            $attachment_url_converted = implode('.', $tmp) . ".$type";

            $tmp = explode('.', $attachment_path);
            array_pop($tmp);
            $attachment_path_converted = implode('.', $tmp) . ".$type";

            $mimetype = $attachment->post_mime_type;
            $name = $attachment->post_name;

            // Ignore non-images
            if (!str_contains($mimetype, "image/")) {
                continue;
            }

            // Get size of the file
            $size_attachment = filesize($attachment_path) ?? 0;

            // Get the extension on the current file
            $tmp = explode(".", $attachment_path);
            $extension = array_pop($tmp);

            // Do not bother with this attachment if the file does not exists
            if (!file_exists($attachment_path)) {
                continue;
            }

            // Get, if it exists, its values in the array
            $inlist_attachment = $all_medias_to_convert[$attachment->ID] ?? null;

            // If the attachment is not in our array
            if ($inlist_attachment == null) {

                // Do not convert images not on the authorized_type table
                if (!in_array($extension, $authorized_types)) {
                    continue;
                }

                // If the original and the converted version are the same, it means it already was in $type
                if ($attachment_path == $attachment_path_converted) {
                    continue;
                }

                // We add it to the array with infos about the original file
                $all_medias_to_convert[$attachment->ID] = [
                    'ID' => $attachment->ID,
                    'name' => $name,
                    'original_url' => $attachment_url,
                    'original_path' => $attachment_path,
                    'original_mime' => $mimetype,
                    'original_extension' => $extension,
                    'original_size' => $size_attachment,
                    'url' => $attachment_url_converted,
                    'path' => $attachment_path_converted,
                    'mime' => "image/$type",
                    'extension' => $type,
                    'size' => $size_attachment,
                    'to_keep' => $keepcopy,
                    'converted' => false,
                    'date_convertion' => false,
                ];

                // If the file is already converted
                if (file_exists($attachment_path_converted)) {
                    $converted++;
                    $all_medias_to_convert[$attachment->ID] = array_merge(
                        $all_medias_to_convert[$attachment->ID],
                        [
                            'size' => filesize($attachment_path_converted) ?? $size_attachment,
                            'converted' => true,
                            'date_convertion' => time(),
                        ]
                    );
                }
            } else {
                // The attachment is already in our array

                // Remove the attachment from the listing if it does not exists anymore (and should still exists)
                if ((!file_exists($inlist_attachment['original_path']) && $inlist_attachment['to_keep'] == "true") || ($inlist_attachment['to_keep'] == "false" && isset($inlist_attachment['original_path']) && !file_exists($inlist_attachment['original_path']))) {
                    unset($all_medias_to_convert[$attachment->ID]);
                    continue;
                }

                // Do not convert images not on the authorized_type table
                if (!in_array($inlist_attachment['original_extension'], $authorized_types)) {
                    if (!$inlist_attachment['converted']) {
                        unset($all_medias_to_convert[$attachment->ID]);
                    }
                    continue;
                }

                // It is already converted to the right format, the converted file exists
                if (file_exists($inlist_attachment['path'])) {
                    $all_medias_to_convert[$attachment->ID]['converted'] = true;
                    $all_medias_to_convert[$attachment->ID]['date_convertion'] = $all_medias_to_convert[$attachment->ID]['date_convertion'] ?? time();
                    $converted++;
                } else {
                    // The file "converted" cannot be found, we consider it not to be converted, then
                    $all_medias_to_convert[$attachment->ID] = array_merge($all_medias_to_convert[$attachment->ID], [
                        'converted' => false,
                        'date_convertion' => false,
                    ]);
                }
            }
            $original++;
        }

        $original_size = 1;
        $gained_size = 1;
        foreach ($all_medias_to_convert as $media_convert_size) {
            if ($media_convert_size['converted']) {
                $original_size += $media_convert_size['original_size'];
                $gained_size += $media_convert_size['size'];
            }
        }

        $gains = number_format((($original_size - $gained_size) * 100) / $original_size, 2, ".", '') . "%";
        update_option('lws_optimize_images_convertion', $all_medias_to_convert);

        // Update the stats about the current convertion
        update_option('lws_optimize_current_convertion_stats', ['type' => $type, 'original' => $original, 'converted' => $converted, 'gains' => $gains]);

        return [
            'max' => $original,
            'to_convert' => $original,
            'converted' => $converted,
            'left' => $original - $converted,
            'type' => $type,
            'gains' => $gains,
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
        $type = sanitize_text_field($latest_convertion['type']) ?? '-';
        $gains = sanitize_text_field($latest_convertion['gains']) ?? '0%';

        $images = get_option('lws_optimize_images_convertion', []);

        $revertable_images = $this->lws_optimize_get_revertion_stats();
        $revertable_images_count = 0;
        if (is_array($revertable_images)) {
            $revertable_images_count = count($revertable_images);
        }

        $data = [
            'status' => $next_scheduled_all_convert ? true : false,
            'status_revert' => wp_next_scheduled('lwsop_revertOptimization') ? true : false,
            'next' => htmlentities($next),
            'next_deconvert' => htmlentities($next_deconvert),
            'max' => intval($max) ?? 0,
            'left' => intval($left) ?? 0,
            'done' => intval($done) ?? 0,
            'listing' => $images,
            'convert_type' => htmlentities($type),
            'deconvert_left' => $revertable_images_count,
            'gains' => $gains
        ];

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Remove a directory and all its content
     */
    public function removeDir(string $dir): void
    {
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            $it,
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                $this->removeDir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
