<?php

namespace Lws\Classes;

use Google\Web_Stories\Remove_Transients;
use Lws\Classes\Admin\LwsOptimizeManageAdmin;
use Lws\Classes\FileCache\LwsOptimizeAutoPurge;
use Lws\Classes\Images\LwsOptimizeImageOptimizationPro;
use Lws\Classes\LazyLoad\LwsOptimizeLazyLoading;
use Lws\Classes\FileCache\LwsOptimizeFileCache;
use Lws\Classes\FileCache\LwsOptimizeCloudFlare;
use Lws\Classes\Front\LwsOptimizeJSManager;
use Lws\Classes\Images\LwsOptimizeImageFrontManager;

class LwsOptimize
{
    public $log_file;
    public $optimize_options;
    public $lwsOptimizeCache;
    public $lwsImageOptimization;
    public $lwsImageOptimizationPro;
    public $cloudflare_manager;
    public $nginx_purger;
    public $chosen_purger;

    public function __construct()
    {
        // Store the class in GLOBALS for later usage
        $GLOBALS['lws_optimize'] = $this;

        // Create the log file if needed, otherwise just get the path
        $this->setupLogfile();

        // Path to the object-cache file (for Memcached)
        define('LWSOP_OBJECTCACHE_PATH', WP_CONTENT_DIR . '/object-cache.php');

        // Get all the options for LWSOptimize. If none are found (first start, erased from DB), recreate the array
        $optimize_options = get_option('lws_optimize_config_array', []);
        if (empty($optimize_options)) {
            $optimize_options = $this->lwsop_auto_setup_optimize("basic", true);
            $this->lws_optimize_reset_header_htaccess();

            // Deactivate the filebased_cache preloading at first
            $optimize_options['filebased_cache']['preload'] = "false";
            if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
            }

            // Deactivate the plugin on activation
            delete_option('lws_optimize_offline');
            delete_option('lws_optimize_preload_is_ongoing');

            $this->lws_optimize_set_cache_htaccess();

            update_option('lws_optimize_config_array', $optimize_options);
        }

        // Store the array globally to avoid updating it each time
        $this->optimize_options = $optimize_options;

        // Add custom action hooks for external cache clearing
        add_action('lws_optimize_clear_all_cache', [$this, 'clear_all_cache_external']);
        add_action('lws_optimize_clear_url_cache', [$this, 'clear_url_cache_external'], 10, 1);

        // If it got installed by the LWS Auto-installer, then proceed to activate it on recommended by default
        $auto_installer_mode = get_option('lws_from_autoinstall_optimize', false);
        if ($auto_installer_mode) {
            $this->lwsop_auto_setup_optimize("basic", true);
            delete_option("lws_from_autoinstall_optimize");
            delete_option('lws_optimize_offline');
        }

        // Init the FileCache Class
        $this->lwsOptimizeCache = new LwsOptimizeFileCache($this);

        // Init the ImageOptimization Class
        $this->lwsImageOptimization = new LwsOptimizeImageOptimizationPro();

        add_action("wp_ajax_lwsop_deactivate_temporarily", [$this, "lwsop_deactivate_temporarily"]);

        if (!get_option('lws_optimize_deactivate_temporarily')) {
            // If the plugin was updated...
            add_action('plugins_loaded', [$this, 'lws_optimize_after_update_actions']);

            // If Memcached is activated but there is no object-cache.php, add it back
            if ($this->lwsop_check_option('memcached')['state'] === "true") {
                // Deactivate Memcached if Redis is activated
                if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                    $optimize_options['memcached']['state'] = "false";
                } else {
                    if (class_exists('Memcached')) {
                        $memcached = new \Memcached();
                        if (empty($memcached->getServerList())) {
                            $memcached->addServer('localhost', 11211);
                        }

                        if ($memcached->getVersion() === false) {
                            $optimize_options['memcached']['state'] = "false";
                            if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                                unlink(LWSOP_OBJECTCACHE_PATH);
                            }
                        } else {
                            if (!file_exists(LWSOP_OBJECTCACHE_PATH)) {
                                file_put_contents(LWSOP_OBJECTCACHE_PATH, file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
                            }
                        }
                    } else {
                        $optimize_options['memcached']['state'] = "false";
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

            if ($this->lwsop_check_option('image_add_sizes')['state'] === "true") {
                LwsOptimizeImageFrontManager::startImageWidth();
            }

            // If the lazyloading of images has been activated on the website
            if ($this->lwsop_check_option('image_lazyload')['state'] === "true") {
                // Skip lazyloading in admin pages and page builders
                if (!is_admin() &&
                    !isset($_GET['elementor-preview']) &&
                    !isset($_GET['et_fb']) &&
                    !isset($_GET['fl_builder']) &&
                    !isset($_GET['vcv-action']) &&
                    !isset($_GET['vc_action']) &&
                    !isset($_GET['vc_editable'])) {
                    LwsOptimizeLazyLoading::startActionsImage();
                }
            }

            // If the lazyloading of iframes/videos has been activated on the website
            if ($this->lwsop_check_option('iframe_video_lazyload')['state'] === "true") {
                // Skip lazyloading in admin pages and page builders
                if (!is_admin() &&
                    !isset($_GET['elementor-preview']) &&
                    !isset($_GET['et_fb']) &&
                    !isset($_GET['fl_builder']) &&
                    !isset($_GET['vcv-action']) &&
                    !isset($_GET['vc_action']) &&
                    !isset($_GET['vc_editable'])) {
                    LwsOptimizeLazyLoading::startActionsIframe();
                }
            }

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });

            add_filter('lws_optimize_convert_media_cron', [$this, 'lws_optimize_convert_media_cron'], 10, 2);
            add_filter('lws_optimize_clear_filebased_cache', [$this, 'lws_optimize_clean_filebased_cache'], 10, 2);
            add_filter('lws_optimize_clear_filebased_cache_cron', [$this, 'lws_optimize_clean_filebased_cache_cron'], 10, 2);
            add_filter('lws_optimize_clear_all_filebased_cache', [$this, 'lws_optimize_clean_all_filebased_cache'], 10, 1);



            add_action('lws_optimize_start_filebased_preload', [$this, 'lws_optimize_start_filebased_preload']);

            if ($this->lwsop_check_option("maintenance_db")['state'] == "true" && !wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_schedule_event(time(), 'weekly', 'lws_optimize_maintenance_db_weekly');
            }

            // Add new schedules time for crons
            add_filter('cron_schedules', [$this, 'lws_optimize_timestamp_crons']);


            // Activate functions related to CloudFlare
            $this->cloudflare_manager = new LwsOptimizeCloudFlare();
            $this->cloudflare_manager->activate_cloudflare_integration();

            add_action("wp_ajax_lwsop_dump_dynamic_cache", [$this, "lwsop_dump_dynamic_cache"]);
            add_action("wp_ajax_lws_optimize_activate_cleaner", [$this, "lws_optimize_activate_cleaner"]);

            // Launch the weekly DB cleanup
            add_action("lws_optimize_maintenance_db_weekly", [$this, "lws_optimize_create_maintenance_db_options"]);
            add_action("wp_ajax_lws_optimize_set_maintenance_db_options", [$this, "lws_optimize_set_maintenance_db_options"]);
            add_action("wp_ajax_lws_optimize_get_maintenance_db_options", [$this, "lws_optimize_manage_maintenance_get"]);

            add_action('wp_ajax_lwsop_change_optimize_configuration', [$this, "lwsop_get_setup_optimize"]);

            // If LWSOptimize is off or the cache has been deactivated, do not start the caching process
            if ($this->lwsop_check_option('filebased_cache')['state'] === "true") {
                $this->lwsOptimizeCache->lwsop_launch_cache();
            }

            // If the autopurge has been activated, add hooks that will clear specific cache on specific actions
            if (!get_option('lws_optimize_deactivate_temporarily') && $this->lwsop_check_option("autopurge")['state'] == "true") {
                $autopurge_manager = new LwsOptimizeAutoPurge();
                $autopurge_manager->start_autopurge();
            }

            if (is_admin()) {
                // Change configuration state for the differents element of LWSOptimize
                add_action("wp_ajax_lws_optimize_checkboxes_action", [$this, "lws_optimize_manage_config"]);
                add_action("wp_ajax_lws_optimize_checkboxes_action_delayed", [$this, "lws_optimize_manage_config_delayed"]);
                add_action("wp_ajax_lws_optimize_exclusions_changes_action", [$this, "lws_optimize_manage_exclusions"]);
                add_action("wp_ajax_lws_optimize_exclusions_media_changes_action", [$this, "lws_optimize_manage_exclusions_media"]);
                add_action("wp_ajax_lws_optimize_fetch_exclusions_action", [$this, "lws_optimize_fetch_exclusions"]);
                // Activate the "preload" option for the file-based cache
                add_action("wp_ajax_lwsop_start_preload_fb", [$this, "lwsop_preload_fb"]);
                add_action("wp_ajax_lwsop_change_preload_amount", [$this, "lwsop_change_preload_amount"]);

                add_action("wp_ajax_lwsop_regenerate_cache", [$this, "lwsop_regenerate_cache"]);
                add_action("wp_ajax_lwsop_regenerate_cache_general", [$this, "lwsop_regenerate_cache_general"]);

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

                if (isset($this->lwsop_check_option('filebased_cache')['data']['preload']) && $this->lwsop_check_option('filebased_cache')['data']['preload'] === "true") {
                    add_action("wp_ajax_lwsop_check_preload_update", [$this, "lwsop_check_preload_update"]);
                }

                add_action("wp_ajax_lws_clear_fb_cache", [$this, "lws_optimize_clear_cache"]);
                add_action("wp_ajax_lws_op_clear_all_caches", [$this, "lws_op_clear_all_caches"]);
                add_action("wp_ajax_lws_clear_opcache", [$this, "lws_clear_opcache"]);
                add_action("wp_ajax_lws_clear_html_fb_cache", [$this, "lws_optimize_clear_htmlcache"]);
                add_action("wp_ajax_lws_clear_style_fb_cache", [$this, "lws_optimize_clear_stylecache"]);
                add_action("wp_ajax_lws_clear_currentpage_fb_cache", [$this, "lws_optimize_clear_currentcache"]);


                add_action("wp_ajax_lws_optimize_fb_cache_change_status", [$this, "lws_optimize_set_fb_status"]);
                add_action("wp_ajax_lws_optimize_fb_cache_change_cache_time", [$this, "lws_optimize_set_fb_timer"]);
            }
        }

        update_option('lws_optimize_config_array', $optimize_options);
        // Store the array globally to avoid updating it each time
        $this->optimize_options = $optimize_options;

        add_action('init', [$this, "lws_optimize_init"]);
        add_action("wp_ajax_lws_optimize_do_pagespeed", [$this, "lwsop_do_pagespeed_test"]);
    }

    /**
     * Clear all cache via external do_action hook
     * Usage: do_action('lws_optimize_clear_all_cache');
     */
    public function clear_all_cache_external() {
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] External request: Clearing all cache' . PHP_EOL);
        fclose($logger);

        // Delete file-based cache directories
        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);

        // Clear dynamic cache if available
        $this->lwsop_dump_all_dynamic_caches();

        // Clear opcache if available
        if (function_exists("opcache_reset")) {
            opcache_reset();
        }

        // Reset preloading state if needed
        delete_option('lws_optimize_sitemap_urls');
        delete_option('lws_optimize_preload_is_ongoing');
        $this->after_cache_purge_preload();

        return true;
    }

    /**
     * Clear cache for a specific URL via external do_action hook
     * Usage: do_action('lws_optimize_clear_url_cache', 'https://example.com/page/');
     *
     * @param string $url The URL to clear cache for
     * @return bool True on success, false on failure
     */
    public function clear_url_cache_external($url) {
        if (empty($url)) {
            return false;
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] External request: Clearing cache for URL: ' . $url . PHP_EOL);
        fclose($logger);

        // Parse the URL to get the path
        $parsed_url = parse_url($url);
        $path_uri = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        if (empty($path_uri)) {
            return false;
        }

        // Get cache paths for desktop and mobile
        $path_desktop = $this->lwsOptimizeCache->lwsop_set_cachedir($path_uri);
        $path_mobile = $this->lwsOptimizeCache->lwsop_set_cachedir($path_uri, true);

        $removed = false;

        // Remove desktop cache files
        $files_desktop = glob($path_desktop . '/index_*');
        if (!empty($files_desktop)) {
            array_map('unlink', array_filter($files_desktop, 'is_file'));
            $removed = true;
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed desktop cache for: ' . $path_uri . PHP_EOL);
            fclose($logger);
        }

        // Remove mobile cache files
        $files_mobile = glob($path_mobile . '/index_*');
        if (!empty($files_mobile)) {
            array_map('unlink', array_filter($files_mobile, 'is_file'));
            $removed = true;
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed mobile cache for: ' . $path_uri . PHP_EOL);
            fclose($logger);
        }

        // If cache was cleared, also clear dynamic cache for this URL
        if ($removed) {
            $this->lwsop_dump_all_dynamic_caches();
        }

        return $removed;
    }

    /**
     * Initial setup of the plugin ; execute all basic actions
     */
    public function lws_optimize_init()
    {
        $optimize_options = get_option('lws_optimize_config_array', []);

        load_textdomain('lws-optimize', LWS_OP_DIR . '/languages/lws-optimize-' . determine_locale() . '.mo');

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

        // Add all options referring to the WPAdmin page or the AdminBar
        $admin_manager = new LwsOptimizeManageAdmin();
        $admin_manager->manage_options();

        if (! function_exists('wp_crop_image')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Schedule the cache cleanout again if it has been deleted
        // If the plugin is OFF or the filecached is deactivated, unregister the WPCron
        if (isset($optimize_options['filebased_cache']['timer']) && !get_option('lws_optimize_deactivate_temporarily')) {
            if (!wp_next_scheduled('lws_optimize_clear_filebased_cache_cron') && $optimize_options['filebased_cache']['timer'] != 0) {
                wp_schedule_event(time(), $optimize_options['filebased_cache']['timer'], 'lws_optimize_clear_filebased_cache_cron');
            }
        } elseif (get_option('lws_optimize_deactivate_temporarily') || $this->lwsop_check_option('filebased_cache')['state'] === "false") {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
        }
    }

    public function lwsop_deactivate_temporarily() {
        check_ajax_referer('lwsop_deactivate_temporarily_nonce', '_ajax_nonce');
        if (!isset($_POST['duration'])) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $duration = intval($_POST['duration']);
        if ($duration < 0) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        // Get options before making any changes
        $optimize_options = get_option('lws_optimize_config_array', []);

        if ($duration == 0) {
            delete_option('lws_optimize_deactivate_temporarily');

            // Update all .htaccess files by removing or adding the rules
            if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
                $this->lws_optimize_set_cache_htaccess();
            } else {
                $this->unset_cache_htaccess();
            }
            if (isset($optimize_options['gzip_compression']['state']) && $optimize_options['gzip_compression']['state'] == "true") {
                $this->set_gzip_brotli_htaccess();
            } else {
                $this->unset_gzip_brotli_htaccess();
            }
            $this->lws_optimize_reset_header_htaccess();
        } else {
            $transient_set = update_option('lws_optimize_deactivate_temporarily', time() + $duration);

            // Verify that the transient is set
            if (!$transient_set) {
                wp_die(json_encode(array('code' => "TRANSIENT_ERROR", 'data' => "Could not set temporary deactivation"), JSON_PRETTY_PRINT));
            }

            // Remove .htaccess rules
            $this->unset_cache_htaccess();
            $this->unset_gzip_brotli_htaccess();
            $this->unset_header_htaccess();

            // Verify the transient was set correctly
            $transient_value = get_option('lws_optimize_deactivate_temporarily', false);
            if ($transient_value === false) {
                wp_die(json_encode(array('code' => "TRANSIENT_VERIFY_ERROR", 'data' => "Temporary deactivation may not work correctly"), JSON_PRETTY_PRINT));
            }
        }

        $this->lwsop_dump_all_dynamic_caches();

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => array('duration' => $duration)), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_after_update_actions() {
        if (get_option('wp_lwsoptimize_post_update') && !get_option('lws_optimize_deactivate_temporarily')) {
            delete_option('wp_lwsoptimize_post_update');

            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');

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

            $optimize_options = get_option('lws_optimize_config_array', []);

            // Update all .htaccess files by removing or adding the rules
            if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
                $this->lws_optimize_set_cache_htaccess();
            } else {
                $this->unset_cache_htaccess();
            }
            if (isset($optimize_options['gzip_compression']['state']) && $optimize_options['gzip_compression']['state'] == "true") {
                $this->set_gzip_brotli_htaccess();
            } else {
                $this->unset_gzip_brotli_htaccess();
            }
            $this->lws_optimize_reset_header_htaccess();

            $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed cache after update' . PHP_EOL);
            fclose($logger);

            $this->after_cache_purge_preload();
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

        $schedules['lws_three_minutes'] = array(
            'interval' => 120,
            'display' => __('Every 2 Minutes', 'lws-optimize')
        );

        $schedules['lws_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minutes', 'lws-optimize')
        );


        return $schedules;
    }

    public function lwsop_dump_dynamic_cache()
    {
        check_ajax_referer('lwsop_empty_d_cache_nonce', '_ajax_nonce');
        wp_die($this->lwsop_dump_all_dynamic_caches());
    }

    /**
     * Purge the cache using the found purger (if exists)
     */
    public function lwsop_dump_all_dynamic_caches()
    {
        $chosen_purger = null;

        if (isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1' && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] == 'varnish') {
            // Verify whether this is Varnish using IPxChange or not
            if (isset($_SERVER['HTTP_X_CDN_INFO']) && $_SERVER['HTTP_X_CDN_INFO'] == "ipxchange") {
                $ipXchange_IP = dns_get_record($_SERVER['HTTP_HOST'])[0]['ip'] ?? false;
                $host = $_SERVER['SERVER_NAME'] ?? false;

                // If we find the IP and the host, we can purge the cache
                // Otherwise, we will purge the cache without the host
                if ($ipXchange_IP && $host) {
                    wp_remote_request(str_replace($host, $ipXchange_IP, get_site_url()), array('method' => 'FULLPURGE', 'Host' => $host));
                } else {
                    wp_remote_request(get_site_url(), array('method' => 'FULLPURGE'));
                }
            } else {
                wp_remote_request(get_site_url(), array('method' => 'FULLPURGE'));
            }

            $chosen_purger = "Varnish";
        } elseif (isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE']) && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1' && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] == 'litespeed') {
            // If LiteSpeed, simply purge the cache
            wp_remote_request(get_site_url() . "/.*", array('method' => 'PURGE'));
            wp_remote_request(get_site_url() . "/*", array('method' => 'FULLPURGE'));
            $chosen_purger = "LiteSpeed";
        } elseif (isset($_ENV['lwscache']) && strtolower($_ENV['lwscache']) == "on") {
            // If LWSCache, simply purge the cache
            wp_remote_request(get_site_url(null, '', 'https') . "/*", array('method' => 'PURGE'));
            wp_remote_request(get_site_url(null, '', 'http') . "/*", array('method' => 'PURGE'));

            wp_remote_request(get_site_url(null, '', 'https') . "/*", array('method' => 'FULLPURGE'));
            wp_remote_request(get_site_url(null, '', 'http') . "/*", array('method' => 'FULLPURGE'));
            $chosen_purger = "LWS Cache";
        } else {
            // No cache, no purge
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] No compatible cache found or cache deactivated: no server cache purge' . PHP_EOL);
            fclose($logger);
            return (json_encode(array('code' => "FAILURE", 'data' => "No cache method usable"), JSON_PRETTY_PRINT));
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Compatible cache found : starting server cache purge on {$chosen_purger}" . PHP_EOL);
        fclose($logger);
        return (json_encode(array('code' => "SUCCESS", 'data' => ""), JSON_PRETTY_PRINT));
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

        $optimize_options = get_option('lws_optimize_config_array', []);

        // Get the exclusions
        $preloads = isset($optimize_options['preload_css']['links']) ? $optimize_options['preload_css']['links'] : array();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $preloads, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    public function lwsop_set_url_preload()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_files_set', '_ajax_nonce');
        $optimize_options = get_option('lws_optimize_config_array', []);

        if (isset($_POST['data'])) {
            $urls = array();

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }
            $optimize_options['preload_css']['links'] = $urls;

            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }

    public function lwsop_get_url_preload_font()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_fonts', '_ajax_nonce');
        if (!isset($_POST['action'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        $optimize_options = get_option('lws_optimize_config_array', []);

        // Get the exclusions
        $preloads = isset($optimize_options['preload_font']['links']) ? $optimize_options['preload_font']['links'] : array();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $preloads, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    public function lwsop_set_url_preload_font()
    {
        check_ajax_referer('nonce_lws_optimize_preloading_url_fonts_set', '_ajax_nonce');
        if (isset($_POST['data'])) {
            $urls = array();

            $optimize_options = get_option('lws_optimize_config_array', []);

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }

            $optimize_options['preload_font']['links'] = $urls;

            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
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

    /**
     * Recursively fetches URLs from sitemaps
     *
     * @param string $url The sitemap URL to fetch
     * @param array $data Accumulated URLs
     * @return array Array of URLs found in the sitemap
     */
    public function fetch_url_sitemap($url, $data = [])
    {
        // Use stream context to avoid SSL verification issues and set timeout
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 30 // Set a reasonable timeout
            ]
        ]);

        $sitemap_content = @file_get_contents($url, false, $context);

        // Check if content is retrieved
        if ($sitemap_content === false) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to fetch sitemap: ' . $url . PHP_EOL);
            fclose($logger);
            return $data;
        }

        // Suppress warnings from malformed XML
        libxml_use_internal_errors(true);
        $sitemap = simplexml_load_string($sitemap_content);

        if ($sitemap === false) {
            libxml_clear_errors();
            return $data;
        }

        // Process standard sitemap URLs
        if (isset($sitemap->url)) {
            foreach ($sitemap->url as $url_entry) {
                if (isset($url_entry->loc) && !in_array((string)$url_entry->loc, $data)) {
                    $data[] = (string)$url_entry->loc;
                }
            }
        }

        // Process sitemap index entries
        if (isset($sitemap->sitemap)) {
            foreach ($sitemap->sitemap as $entry) {
                if (!isset($entry->loc)) {
                    continue;
                }

                $child_sitemap_url = (string)$entry->loc;

                // Prevent processing the same sitemap twice (avoid loops)
                static $processed_sitemaps = [];
                if (in_array($child_sitemap_url, $processed_sitemaps)) {
                    continue;
                }
                $processed_sitemaps[] = $child_sitemap_url;

                // Recursively fetch child sitemaps
                $data = $this->fetch_url_sitemap($child_sitemap_url, $data);
            }
        }

        return array_reverse(array_unique($data));
    }

    public function lwsop_preload_fb()
    {
        check_ajax_referer('update_fb_preload', '_ajax_nonce');

        if (!isset($_POST['action']) || !isset($_POST['state'])) {
            wp_die(json_encode(['code' => "FAILED_ACTIVATE", 'data' => "Missing required parameters"]), JSON_PRETTY_PRINT);
        }

        // IMPORTANT: Get a fresh copy of options from the database
        $optimize_options = get_option('lws_optimize_config_array', []);

        // Clean previous preload data
        delete_option('lws_optimize_sitemap_urls');
        delete_option('lws_optimize_preload_is_ongoing');

        $state = sanitize_text_field($_POST['state']);
        $amount = isset($_POST['amount']) ? absint($_POST['amount']) : 3;

        // Update preload configuration
        $optimize_options['filebased_cache']['preload'] = $state;
        $optimize_options['filebased_cache']['preload_amount'] = $amount;
        $optimize_options['filebased_cache']['preload_done'] = 0;
        $optimize_options['filebased_cache']['preload_ongoing'] = $state;

        // Get sitemap URLs
        $urls = $this->get_sitemap_urls();
        $optimize_options['filebased_cache']['preload_quantity'] = count($urls);

        // Manage scheduled preload task
        if ($state === "false") {
            // Disable scheduled preload
            if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
            }
        } else {
            // Enable scheduled preload
            if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
            }
            wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
        }

        // Update options in database
        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        wp_die(json_encode(['code' => "SUCCESS", 'data' => $optimize_options['filebased_cache']]), JSON_PRETTY_PRINT);
    }

    /**
     * Helper method to get sitemap URLs
     * @return array Array of URLs from sitemap
     */
    public function get_sitemap_urls()
    {
        $sitemap = get_sitemap_url("index");

        // Set SSL context to avoid verification issues
        stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        // Check if sitemap exists
        $headers = get_headers($sitemap);
        if (substr($headers[0], 9, 3) == 404) {
            $sitemap = home_url('/sitemap_index.xml');
        }

        $cached_urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $cache_time = $cached_urls['time'] ?? 0;

        // If cache is fresh (less than an hour old), use cached URLs
        if ($cache_time + 3600 > time()) {
            return $cached_urls['urls'] ?? [];
        }

        // Create log entry
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Starting to fetch sitemap [$sitemap] again" . PHP_EOL);
        fclose($logger);

        // Otherwise fetch fresh URLs from sitemap
        $urls = $this->fetch_url_sitemap($sitemap, []);
        if (!empty($urls)) {
            update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
        }

        return $urls;
    }

    /**
     * Preload the file-based cache. Get all URLs from the sitemap and cache each of them
     */
    public function lws_optimize_start_filebased_preload()
    {
        $ongoing = get_option('lws_optimize_preload_is_ongoing', false);

        if ($ongoing) {
            // Do not continue if the cron is ongoing BUT force if it has been ~10m
            if (time() - $ongoing > 600) {
                // Create log entry
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preloading still ongoing, 600 seconds ellapsed, forcing new instance" . PHP_EOL);
                fclose($logger);
                delete_option('lws_optimize_preload_is_ongoing');
            } else {
                // Create log entry
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preloading still ongoing, not starting new instance" . PHP_EOL);
                fclose($logger);
                exit;
            }
        }

        update_option('lws_optimize_preload_is_ongoing', time());

        $lws_filebased = new LwsOptimizeFileCache($GLOBALS['lws_optimize']);

        $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $time = $urls['time'] ?? 0;

        // It has been more than an hour since the latest fetch from the sitemap
        if ($time +  3600 < time()) {
            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] URLs last fetched more than 1 hour ago, fetching new data" . PHP_EOL);
            fclose($logger);

            // We get the freshest data
            $urls = $this->get_sitemap_urls();

            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] New URLs fetched. Amount: " . count($urls) . PHP_EOL);
            fclose($logger);
        } else {
            // We get the ones currently saved in base
            $urls = $urls['urls'] ?? [];
        }

        $array = get_option('lws_optimize_config_array', []);
        if (!isset($array['filebased_cache']['state']) || $array['filebased_cache']['state'] == "false") {
            delete_option('lws_optimize_preload_is_ongoing');

            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Filebased cache is disabled, aborting preload" . PHP_EOL);
            fclose($logger);
            return;
        }


        // Initialize variables from configuration
        $max_try = intval($array['filebased_cache']['preload_amount'] ?? 5);
        $done = intval($array['filebased_cache']['preload_done'] ?? 0);
        $first_run = ($done == 0);
        $current_try = 0;

        // Define user agents for preloading
        $userAgents = [
            'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36; compatible; LWSOptimizePreload/1.0',
            'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15.0 Safari/604.1; compatible; LWSOptimizePreload/1.0'
        ];

        // Remove mobile agent if mobile caching is disabled
        if (isset($array['cache_mobile_user']['state']) && $array['cache_mobile_user']['state'] == "true") {
            unset($userAgents['mobile']);
        }

        if ($array['filebased_cache']['preload_ongoing'] == "true") {
            // Create log entry for the preload process
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Starting preload batch - max: ' . $max_try . ' urls' . PHP_EOL);
            fclose($logger);
        }

        // Process URLs from the sitemap
        foreach ($urls as $key => $url) {
            // Stop after reaching maximum number of tries
            if ($current_try >= $max_try) {
                break;
            }

            // Add cache-busting parameter
            $query = parse_url($url, PHP_URL_QUERY);
            $url .= ($query ? '&' : '?') . 'nocache=' . time();

            // Get path for caching
            $parsed_url = parse_url($url);
            $path_uri = isset($parsed_url['path']) ? $parsed_url['path'] : '';

            $path = $lws_filebased->lwsop_set_cachedir($path_uri);
            $path_mobile = $lws_filebased->lwsop_set_cachedir($path_uri, true);

            // Check if cache files already exist
            $file_exists = glob($path . "index*") ?? [];
            $file_exists_mobile = glob($path_mobile . "index*") ?? [];

            // If files exist and this is first run, count it as done
            if (!empty($file_exists) && (!isset($userAgents['mobile']) || !empty($file_exists_mobile))) {
                if ($first_run) {
                    $done++;
                }
                continue;
            }

            // Fetch pages with appropriate user agents
            foreach ($userAgents as $agent) {
            // Ensure the nocache parameter is unique for each request
            $unique_nocache = 'nocache=' . time() . '-' . mt_rand(1000, 9999);
            $request_url = str_replace('nocache=' . time(), $unique_nocache, $url);

            // Make the request with additional cache-busting headers
            $response = wp_remote_get(
                $request_url,
                [
                'timeout'     => 30,
                'user-agent'  => $agent,
                'headers'     => [
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma'        => 'no-cache',
                    'Expires'       => '0',
                    'X-LWS-Preload' => time(), // Additional unique header
                    'X-No-Cache'    => '1'     // Some servers recognize this
                ],
                'sslverify'   => false,
                'blocking'    => true,
                'cookies'     => [], // Clean request with no cookies
                'reject_unsafe_urls' => false // Allow URLs with query strings
                ]
            );
            }

            // Check if cache file was created
            $file_exists = glob($path . "index*") ?? [];
            if (!empty($file_exists)) {
                $done++;
                $current_try++;

                // Log successful cache creation
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Successfully cached: $url" . PHP_EOL);
                fclose($logger);
            } else {
                // Log failed cache attempt
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to cache: $url - removed from queue" . PHP_EOL);
                fclose($logger);
                unset($urls[$key]);
            }
        }

        // Only log if we actually tried to cache something in this batch
        if ($current_try > 0) {
            // Log completion of preload batch with actual stats
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preload batch completed - URLs cached: $current_try, total cached: $done" . PHP_EOL);
            fclose($logger);
        }

        update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
        delete_option('lws_optimize_preload_is_ongoing');
    }

    public function lwsop_change_preload_amount()
    {
        check_ajax_referer('update_fb_preload_amount', '_ajax_nonce');

        if (isset($_POST['action'])) {
            $optimize_options = get_option('lws_optimize_config_array', []);

            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;
            $optimize_options['filebased_cache']['preload_amount'] =  $amount;

            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;

            wp_die(json_encode(array('code' => "SUCCESS", 'data' => "DONE")));
        }
        wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
    }

    // Start regenerating file-based cache (from 0 instead of just adding)
    // Useful if stats are broken for some reasons
    public function lwsop_regenerate_cache() {
        check_ajax_referer('lws_regenerate_nonce_cache_fb', '_ajax_nonce');
        $stats = $this->lwsop_recalculate_stats('regenerate');

        $stats['desktop']['size'] = $this->lwsOpSizeConvert($stats['desktop']['size'] ?? 0);
        $stats['mobile']['size'] = $this->lwsOpSizeConvert($stats['mobile']['size'] ?? 0);
        $stats['css']['size'] = $this->lwsOpSizeConvert($stats['css']['size'] ?? 0);
        $stats['js']['size'] = $this->lwsOpSizeConvert($stats['js']['size'] ?? 0);

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $stats)));

    }

    // Regenrate cache stats
    public function lwsop_regenerate_cache_general() {
        check_ajax_referer('lws_regenerate_nonce_cache_fb', '_ajax_nonce');
        $cache_stats = $this->lwsop_recalculate_stats('regenerate');

        // Get the specifics values
        $file_cache = $cache_stats['desktop']['amount'];
        $file_cache_size = $this->lwsOpSizeConvert($cache_stats['desktop']['size']) ?? 0;

        $mobile_cache = $cache_stats['mobile']['amount'] ?? 0;
        $mobile_cache_size = $this->lwsOpSizeConvert($cache_stats['mobile']['size']) ?? 0;

        $css_cache = $cache_stats['css']['amount'] ?? 0;
        $css_cache_size = $this->lwsOpSizeConvert($cache_stats['css']['size']) ?? 0;

        $js_cache = $cache_stats['js']['amount'] ?? 0;
        $js_cache_size = $this->lwsOpSizeConvert($cache_stats['js']['size']) ?? 0;

        $caches = [
            'files' => [
                'size' => $file_cache_size,
                'title' => __('Computer Cache', 'lws-optimize'),
                'alt_title' => __('Computer', 'lws-optimize'),
                'amount' => $file_cache,
                'id' => "lws_optimize_file_cache",
                'image_file' => esc_url(plugins_url('images/ordinateur.svg', __DIR__)),
                'image_alt' => "computer icon",
                'width' => "60px",
                'height' => "60px",
            ],
            'mobile' => [
                'size' => $mobile_cache_size,
                'title' => __('Mobile Cache', 'lws-optimize'),
                'alt_title' => __('Mobile', 'lws-optimize'),
                'amount' => $mobile_cache,
                'id' => "lws_optimize_mobile_cache",
                'image_file' => esc_url(plugins_url('images/mobile.svg', __DIR__)),
                'image_alt' => "mobile icon",
                'width' => "50px",
                'height' => "60px",
            ],
            'css' => [
                'size' => $css_cache_size,
                'title' => __('CSS Cache', 'lws-optimize'),
                'alt_title' => __('CSS', 'lws-optimize'),
                'amount' => $css_cache,
                'id' => "lws_optimize_css_cache",
                'image_file' => esc_url(plugins_url('images/css.svg', __DIR__)),
                'image_alt' => "css logo in a window icon",
                'width' => "60px",
                'height' => "60px",
            ],
            'js' => [
                'size' => $js_cache_size,
                'title' => __('JS Cache', 'lws-optimize'),
                'alt_title' => __('JS', 'lws-optimize'),
                'amount' => $js_cache,
                'id' => "lws_optimize_js_cache",
                'image_file' => esc_url(plugins_url('images/js.svg', __DIR__)),
                'image_alt' => "js logo in a window icon",
                'width' => "60px",
                'height' => "60px",

            ],
        ];

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $caches)));

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

        // Validate required parameters
        if (!isset($_POST['timer']) || !isset($_POST['state'])) {
            wp_die(json_encode(['code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()]));
        }

        // Get fresh copy of options
        $optimize_options = get_option('lws_optimize_config_array', []);

        // Sanitize inputs
        $timer = sanitize_text_field($_POST['timer']);
        $state = sanitize_text_field($_POST['state']) === "true" ? "true" : "false";

        // Update configuration
        $optimize_options['filebased_cache']['exceptions'] = $optimize_options['filebased_cache']['exceptions'] ?? [];
        $optimize_options['filebased_cache']['state'] = $state;
        $optimize_options['filebased_cache']['timer'] = $timer;

        // Update Cloudflare TTL to match filebased cache clear timer
        $this->cloudflare_manager->lws_optimize_change_cloudflare_ttl($timer);

        // Update preload status if necessary
        if (isset($optimize_options['filebased_cache']['preload']) && $optimize_options['filebased_cache']['preload'] == "true") {
            $optimize_options['filebased_cache']['preload_ongoing'] = "true";
        }

        // Update all .htaccess files by removing or adding the rules
        if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
        } else {
            $this->unset_cache_htaccess();
        }
        if (isset($optimize_options['gzip_compression']['state']) && $optimize_options['gzip_compression']['state'] == "true") {
            $this->set_gzip_brotli_htaccess();
        } else {
            $this->unset_gzip_brotli_htaccess();
        }
        $this->lws_optimize_reset_header_htaccess();

        // Clear dynamic cache
        $this->lwsop_dump_all_dynamic_caches();

        // Save updated options
        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        wp_die(json_encode(['code' => "SUCCESS", 'data' => $state]), JSON_PRETTY_PRINT);
    }

    public function set_gzip_brotli_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        try {
            // Create or verify .htaccess file
            if (!file_exists($htaccess)) {
                $old_umask = umask(0);

                if (!chmod(ABSPATH, 0755)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not change directory permissions for .htaccess' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                if (!touch($htaccess)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not create .htaccess file' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                chmod($htaccess, 0644);
                umask($old_umask);
            }

            // Ensure file is writable
            if (!is_writable($htaccess)) {
                if (!chmod($htaccess, 0644)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not make .htaccess writable' . PHP_EOL);
                    fclose($logger);
                    return;
                }
            }

            // Read existing content
            $htaccess_content = file_get_contents($htaccess);
            if ($htaccess_content === false) {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not read .htaccess file' . PHP_EOL);
                fclose($logger);
                return;
            }

            // Remove existing GZIP rules
            $pattern = '/#LWS OPTIMIZE - GZIP COMPRESSION[\s\S]*?#END LWS OPTIMIZE - GZIP COMPRESSION\n?/';
            $htaccess_content = preg_replace($pattern, '', $htaccess_content);

            // Skip if temporarily deactivated
            if (get_option('lws_optimize_deactivate_temporarily')) {
                if (file_put_contents($htaccess, $htaccess_content) === false) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not update .htaccess content' . PHP_EOL);
                }
                fclose($logger);
                return;
            }

            // Build new GZIP rules
            $hta = '';
            // Brotli compression rules
            $hta .= "<IfModule mod_brotli.c>\n";
            $compress_types = [
                'application/javascript', 'application/json', 'application/rss+xml',
                'application/xml', 'application/atom+xml', 'application/vnd.ms-fontobject',
                'application/x-font-ttf', 'font/opentype', 'text/plain', 'text/pxml',
                'text/html', 'text/css', 'text/x-component', 'image/svg+xml', 'image/x-icon'
            ];
            foreach ($compress_types as $type) {
                $hta .= "AddOutputFilterByType BROTLI_COMPRESS " . $type . "\n";
            }
            $hta .= "</IfModule>\n\n";

            // Deflate compression rules
            $hta .= "<IfModule mod_deflate.c>\n";
            $hta .= "SetOutputFilter DEFLATE\n";
            $hta .= "<IfModule mod_filter.c>\n";
            foreach ($compress_types as $type) {
                $hta .= "AddOutputFilterByType DEFLATE " . $type . "\n";
            }
            $hta .= "</IfModule>\n";
            $hta .= "</IfModule>\n";

            // Add header and combine content
            $hta = "#LWS OPTIMIZE - GZIP COMPRESSION\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n"
                  . $hta
                  . "#END LWS OPTIMIZE - GZIP COMPRESSION\n";
            $new_content = $hta . $htaccess_content;

            // Write new content
            if (file_put_contents($htaccess, $new_content) === false) {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to write new .htaccess content' . PHP_EOL);
            } else {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Successfully updated GZIP rules in .htaccess' . PHP_EOL);
            }

        } catch (\Exception $e) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Error updating .htaccess: ' . $e->getMessage() . PHP_EOL);
        }

        fclose($logger);
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
        $http_path = $urlparts['path'] ?? '';

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

        $available_htaccess = true;

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            // Try to create .htaccess
            if (!touch($htaccess)) {
                // Failed to create, check permissions
                $old_umask = umask(0);
                if (!chmod(ABSPATH, 0755)) {
                    // Could not change directory permissions
                    error_log("LWSOptimize: Could not change directory permissions for .htaccess");
                    $available_htaccess = false;
                }

                // Try creating again with new permissions
                if (!touch($htaccess)) {
                    // Still failed, abort
                    error_log("LWSOptimize: Could not create .htaccess file");
                    umask($old_umask);
                    $available_htaccess = false;
                }
                umask($old_umask);
            }
        }

        // Get the directory (wp-content, by default)
        $wp_content_directory = explode('/', WP_CONTENT_DIR);
        $wp_content_directory = array_pop($wp_content_directory);

        if ($available_htaccess) {
            // Remove the htaccess related to caching
            // Read the htaccess file
            $htaccess = ABSPATH . '/.htaccess';
            if (file_exists($htaccess) && is_writable($htaccess)) {
                // Read htaccess content
                $htaccess_content = file_get_contents($htaccess);

                // Remove caching rules if they exist
                $pattern = '/#LWS OPTIMIZE - CACHING[\s\S]*?#END LWS OPTIMIZE - CACHING\n?/';
                $htaccess_content = preg_replace($pattern, '', $htaccess_content);

                // Write back to file
                file_put_contents($htaccess, $htaccess_content);
            } else {
                // Log error if htaccess can't be modified
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Unable to modify .htaccess - file not found or not writable' . PHP_EOL);
                fclose($logger);
            }
            // Content
            $hta = '';

            if (!get_option('lws_optimize_deactivate_temporarily')) {
                // Add instructions to load cache file without starting PHP
                $hta .= "#Last Modification: $current_date\n";
                $hta .= "<IfModule mod_rewrite.c>"."\n";
                $hta .= "#---- STARTING DIRECTIVES ----#\n";
                $hta .= "RewriteEngine On"."\n";
                $hta .= "#### ####\n";
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

                // Remove eTag to fix broken 304 Not Modified
                $hta .= "FileETag None\nHeader unset ETag\n";
                $hta .= "</IfModule>\n\n";

                $hta .= "<FilesMatch \"index_.*\\.html$\">\n";
                $hta .= "<If \"%{REQUEST_URI} =~ m#" . preg_quote($wp_content_directory, '#') . "/cache/lwsoptimize/cache#\">\n";
                $hta .= "Header set Edge-Cache-Platform 'lwsoptimize'\n";
                $hta .= "</If>\n";
                $hta .= "</FilesMatch>\n";

                $hta = "#LWS OPTIMIZE - CACHING\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n $hta#END LWS OPTIMIZE - CACHING\n";

                if (is_file($htaccess)) {
                    $hta .= file_get_contents($htaccess);
                }

                if (($f = fopen($htaccess, 'w+')) !== false) {
                    if (!fwrite($f, $hta)) {
                        fclose($f);
                        error_log(json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | Caching | .htaccess file is not writtable")));
                    } else {
                        fclose($f);
                    }
                } else {
                    error_log(json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | Caching | .htaccess file is not openable")));
                }
            }
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

    public function unset_gzip_brotli_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove GZIP rules using regex
        $pattern = '/#LWS OPTIMIZE - GZIP COMPRESSION[\s\S]*?#END LWS OPTIMIZE - GZIP COMPRESSION\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (file_put_contents($htaccess, $htaccess_content) === false) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Successfully removed GZIP rules from .htaccess' . PHP_EOL);
        }

        fclose($logger);
    }

    public function unset_cache_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove caching rules using regex
        $pattern = '/#LWS OPTIMIZE - CACHING[\s\S]*?#END LWS OPTIMIZE - CACHING\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (file_put_contents($htaccess, $htaccess_content) === false) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Successfully removed caching rules from .htaccess' . PHP_EOL);
        }

        fclose($logger);
    }

    /**
     * Set the expiration headers in the .htaccess. Will remove it before adding it back.
     * If the cache is not active or an error occurs, headers won't be added
     */
    function lws_optimize_reset_header_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        $optimize_options = get_option('lws_optimize_config_array', []);
        $timer = $optimize_options['filebased_cache']['timer'] ?? "lws_yearly";

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

        try {
            // Create or verify .htaccess file
            if (!file_exists($htaccess)) {
                $old_umask = umask(0);

                if (!chmod(ABSPATH, 0755)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not change directory permissions for .htaccess' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                if (!touch($htaccess)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not create .htaccess file' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                chmod($htaccess, 0644);
                umask($old_umask);
            }

            // Ensure file is writable
            if (!is_writable($htaccess)) {
                if (!chmod($htaccess, 0644)) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not make .htaccess writable' . PHP_EOL);
                    fclose($logger);
                    return;
                }
            }

            // Read existing content
            $htaccess_content = file_get_contents($htaccess);
            if ($htaccess_content === false) {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not read .htaccess file' . PHP_EOL);
                fclose($logger);
                return;
            }

            // Remove expire header section using regex
            $pattern = '/#LWS OPTIMIZE - EXPIRE HEADER[\s\S]*?#END LWS OPTIMIZE - EXPIRE HEADER\n?/';
            $htaccess_content = preg_replace($pattern, '', $htaccess_content);

            // Skip if temporarily deactivated
            if (get_option('lws_optimize_deactivate_temporarily')) {
                if (file_put_contents($htaccess, $htaccess_content) === false) {
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Could not update .htaccess content' . PHP_EOL);
                }
                fclose($logger);
                return;
            }

            // Build new expire header rules
            $hta = '';
            $hta .= "<IfModule mod_expires.c>\n";
            $hta .= "ExpiresActive On\n";
            $hta .= "AddOutputFilterByType DEFLATE application/json\n";

            $expire_types = [
                'image/jpg', 'image/jpeg', 'image/gif', 'image/png',
                'image/svg', 'image/x-icon', 'text/css', 'application/pdf',
                'application/javascript', 'application/x-javascript',
                'application/x-shockwave-flash'
            ];

            foreach ($expire_types as $type) {
                $hta .= "ExpiresByType $type \"access $date\"\n";
            }

            $hta .= "ExpiresByType text/html A0\n";
            $hta .= "ExpiresDefault \"access $date\"\n";
            $hta .= "</IfModule>\n\n";

            $hta .= "<FilesMatch \"index_[0-2]\\.(html|htm)$\">\n";
            $hta .= "<IfModule mod_headers.c>\n";
            $hta .= "Header set Cache-Control \"public, max-age=0, no-cache, must-revalidate\"\n";
            $hta .= "Header set CDN-Cache-Control \"public, maxage=$cdn_date\"\n";
            $hta .= "Header set Pragma \"no-cache\"\n";
            $hta .= "Header set Expires \"Mon, 29 Oct 1923 20:30:00 GMT\"\n";
            $hta .= "</IfModule>\n";
            $hta .= "</FilesMatch>\n";

            // Add header and combine content
            $hta = "#LWS OPTIMIZE - EXPIRE HEADER\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n"
                  . $hta
                  . "#END LWS OPTIMIZE - EXPIRE HEADER\n";
            $new_content = $hta . $htaccess_content;

            // Write new content
            if (file_put_contents($htaccess, $new_content) === false) {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to write new .htaccess content' . PHP_EOL);
            } else {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Successfully updated Header rules in .htaccess' . PHP_EOL);
            }

        } catch (\Exception $e) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Error updating .htaccess: ' . $e->getMessage() . PHP_EOL);
        }

        fclose($logger);
    }

    function unset_header_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove expire header section using regex
        $pattern = '/#LWS OPTIMIZE - EXPIRE HEADER[\s\S]*?#END LWS OPTIMIZE - EXPIRE HEADER\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (file_put_contents($htaccess, $htaccess_content) === false) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Successfully removed expire headers from .htaccess' . PHP_EOL);
        }

        fclose($logger);
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

        $optimize_options = get_option('lws_optimize_config_array', []);

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
            $optimize_options['filebased_cache']['state'] = "false";
        }
        if (isset($optimize_options['filebased_cache']['timer']) && $optimize_options['filebased_cache']['timer'] === $timer) {
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $timer)), JSON_PRETTY_PRINT);
        }

        if ($fb_options['state'] == "true") {
           $this->lws_optimize_reset_header_htaccess();
        } else {
            $this->unset_header_htaccess();
        }

        // Update all .htaccess files by removing or adding the rules
        if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
        } else {
            $this->unset_cache_htaccess();
        }
        if (isset($optimize_options['gzip_compression']['state']) && $optimize_options['gzip_compression']['state'] == "true") {
            $this->set_gzip_brotli_htaccess();
        } else {
            $this->unset_gzip_brotli_htaccess();
        }
        $this->lws_optimize_reset_header_htaccess();

        $optimize_options['filebased_cache']['timer'] = $timer;

        // Update Cloudflare TTL to match filebased cache clear timer
        $this->cloudflare_manager->lws_optimize_change_cloudflare_ttl($timer);

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        // Remove the old event and schedule a new one with the new timer
        if (wp_next_scheduled('lws_optimize_clear_filebased_cache_cron')) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
        }

        // Never start cron if timer is defined as zero (infinite)
        if ($timer != 0) {
            wp_schedule_event(time(), $timer, 'lws_optimize_clear_filebased_cache_cron');
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $timer)), JSON_PRETTY_PRINT);
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

        // IMPORTANT: Get a fresh copy of options from the database
        $optimize_options = get_option('lws_optimize_config_array', []);

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

        $optimize_options[$element]['state'] = $state;

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
                $optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $optimize_options);
                wp_die(json_encode(array('code' => "INCOMPATIBLE", "data" => "LWSCache is incompatible with this hosting. Use LWS.")), JSON_PRETTY_PRINT);
            }

            if ($lwscache_status == false && $fastest_cache_status === null) {
                $optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $optimize_options);
                wp_die(json_encode(array('code' => "PANEL_CACHE_OFF", "data" => "LWSCache is not activated on LWSPanel.")), JSON_PRETTY_PRINT);
            }

            if ($lwscache_status === null && $fastest_cache_status == false) {
                $optimize_options[$element]['state'] = "false";
                update_option('lws_optimize_config_array', $optimize_options);
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
                $optimize_options[$element]['state'] = "false";
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
                $this->set_gzip_brotli_htaccess();
            } else {
                $this->unset_gzip_brotli_htaccess();
            }
        } elseif ($element == "htaccess_rules") {
            if ($state == "true") {
                $this->lws_optimize_set_cache_htaccess();
            } elseif ($state == "false") {
                $this->unset_cache_htaccess();
            }
        }

        // If the tab where the option comes from is frontend, we clear the cache
        // as those options needs the cache to be emptied to work properly
        if (isset($tab) && $tab == "frontend") {
            $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed cache after configuration change' . PHP_EOL);
            fclose($logger);
        }

        if ($element == "cache_mobile_user" || $element == "cache_logged_user") {
            if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
                $this->lws_optimize_set_cache_htaccess();
            }
        }

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        // If correctly added and updated
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $optimize_options[$element]['state'] = $state, 'type' => $element)), JSON_PRETTY_PRINT);
    }

    public function lws_optimize_manage_config_delayed() {
        check_ajax_referer('nonce_lws_optimize_checkboxes_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data']) || !is_array($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        $optimize_options = get_option('lws_optimize_config_array', []);

        $errors = [];

        foreach ($_POST['data'] as $element) {
            $id = sanitize_text_field($element['type']);
            $state = sanitize_text_field($element['state']);

            if (preg_match('/lws_optimize_(.*?)_check/', $id, $match) !== 1) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to parse ID for configuration: ' . $id . PHP_EOL);
                fclose($logger);
                continue;
            }

            // Get the ID of the option to update
            $id = $match[1];

            // Update the state of the option
            $optimize_options[$id]['state'] = $state;

            // In case it is the dynamic cache, check compatibility
            if ($id == "dynamic_cache") {
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
                    $optimize_options[$id]['state'] = "false";
                    $errors[$id] = 'INCOMPATIBLE';
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] LWSCache is incompatible with this hosting' . PHP_EOL);
                    fclose($logger);
                    continue;
                }

                if ($lwscache_status == false && $fastest_cache_status === null) {
                    $optimize_options[$id]['state'] = "false";
                    $errors[$id] = 'PANEL_CACHE_OFF';
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] LWSCache is not activated on LWSPanel' . PHP_EOL);
                    fclose($logger);
                    continue;
                }

                if ($lwscache_status === null && $fastest_cache_status == false) {
                    $optimize_options[$id]['state'] = "false";
                    $errors[$id] = 'CPANEL_CACHE_OFF';
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Varnish is not activated on cPanel' . PHP_EOL);
                    fclose($logger);
                    continue;
                }

            } elseif ($id == "maintenance_db") {
                if (wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                    wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
                }
                if ($state == "true") {
                    wp_schedule_event(time() + 604800, 'weekly', 'lws_optimize_maintenance_db_weekly');
                }

            } elseif ($id == "memcached") {
                if ($this->lwsop_plugin_active('redis-cache/redis-cache.php')) {
                    $optimize_options[$id]['state'] = "false";
                    $errors[$id] = 'REDIS_ALREADY_HERE';
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Redis cache plugin is already active' . PHP_EOL);
                    fclose($logger);
                    continue;
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
                        $errors[$id] = 'MEMCACHE_NOT_WORK';
                        $logger = fopen($this->log_file, 'a');
                        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Memcached server not responding' . PHP_EOL);
                        fclose($logger);
                        continue;
                    }

                    file_put_contents(LWSOP_OBJECTCACHE_PATH, file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));

                } else {
                    if (file_exists(LWSOP_OBJECTCACHE_PATH)) {
                        unlink(LWSOP_OBJECTCACHE_PATH);
                    }
                    $errors[$id] = 'MEMCACHE_NOT_FOUND';
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Memcached extension not found' . PHP_EOL);
                    fclose($logger);
                    continue;
                }

            } elseif ($id == "gzip_compression") {
                if ($state == "true") {
                    $this->set_gzip_brotli_htaccess();
                } else {
                    $this->unset_gzip_brotli_htaccess();
                }
            } elseif ($id == "htaccess_rules") {
                if ($state == "true") {
                    $this->lws_optimize_set_cache_htaccess();
                } elseif ($state == "false") {
                    $this->unset_cache_htaccess();
                }
            } elseif ($id == "preload_cache") {
                // Clean previous preload data
                delete_option('lws_optimize_sitemap_urls');
                delete_option('lws_optimize_preload_is_ongoing');

                // Update preload configuration
                $optimize_options['filebased_cache']['preload'] = $state;
                $optimize_options['filebased_cache']['preload_amount'] = $optimize_options['filebased_cache']['preload_amount'] ?: 3;
                $optimize_options['filebased_cache']['preload_done'] = 0;
                $optimize_options['filebased_cache']['preload_ongoing'] = $state;

                // Get sitemap URLs
                $urls = $this->get_sitemap_urls();
                $optimize_options['filebased_cache']['preload_quantity'] = count($urls);

                // Manage scheduled preload task
                if ($state === "false") {
                    // Disable scheduled preload
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                    }
                } else {
                    // Enable scheduled preload
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                    }
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
            }
        }

        // Clear cache when updating data
        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed cache after configuration change' . PHP_EOL);
        fclose($logger);

        $this->after_cache_purge_preload();

        if (function_exists("opcache_reset")) {
            opcache_reset();
        }

        if (isset($optimize_options['htaccess_rules']['state']) && $optimize_options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
        }

        $optimize_options['personnalized'] = "true";

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        // If correctly added and updated
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $optimize_options, 'errors' => $errors), JSON_PRETTY_PRINT));
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

        $optimize_options = get_option('lws_optimize_config_array', []);

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
        $config_element = $optimize_options[$element]['exclusions'] = $exclusions;

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
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
        $optimize_options = get_option('lws_optimize_config_array', []);

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
        $config_element = $optimize_options[$element]['exclusions'] = $exclusions;

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $config_element, 'id' => $id)), JSON_PRETTY_PRINT);
    }

    public function lws_optimize_fetch_exclusions()
    {
        check_ajax_referer('nonce_lws_optimize_fetch_exclusions', '_ajax_nonce');
        $optimize_options = get_option('lws_optimize_config_array', []);

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
        $exclusions = isset($optimize_options[$element]['exclusions']) ? $optimize_options[$element]['exclusions'] : array('rs-lazyload');

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $exclusions, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Clear every caches available on Optimize
    */
    public function lws_op_clear_all_caches() {
        check_ajax_referer('lws_op_clear_all_caches_nonce', '_ajax_nonce');

        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed all caches' . PHP_EOL);
        fclose($logger);

        delete_option('lws_optimize_sitemap_urls');
        delete_option('lws_optimize_preload_is_ongoing');
        $this->after_cache_purge_preload();

        $this->lwsop_dump_all_dynamic_caches();

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    /**
     * Clear the file-based cache completely
     */
    public function lws_optimize_clear_cache()
    {
        check_ajax_referer('clear_fb_caching', '_ajax_nonce');

        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed cache on demand' . PHP_EOL);
        fclose($logger);

        delete_option('lws_optimize_sitemap_urls');
        delete_option('lws_optimize_preload_is_ongoing');
        $this->after_cache_purge_preload();
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
        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS . "/cache-css", $this);
        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS . "/cache-js", $this);

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed CSS/JS cache' . PHP_EOL);
        fclose($logger);

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_htmlcache()
    {
        check_ajax_referer('clear_html_fb_caching', '_ajax_nonce');

        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS . "/cache", $this);
        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS . "/cache-mobile", $this);

        $this->after_cache_purge_preload();

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Removed HTML cache' . PHP_EOL);
        fclose($logger);

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_currentcache()
    {
        check_ajax_referer('clear_currentpage_fb_caching', '_ajax_nonce');

        // Get the request_uri of the current URL to remove
        // If not found, do not delete anything
        $uri = esc_url($_POST['request_uri']) ?? false;

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Starting to remove $uri cache" . PHP_EOL);
        fclose($logger);

        if ($uri === false) {
            wp_die(json_encode(array('code' => 'ERROR', 'data' => "/"), JSON_PRETTY_PRINT));
        }

        apply_filters("lws_optimize_clear_filebased_cache", $uri, "lws_optimize_clear_currentcache");

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
     * Get URLs for categories, tags, and pagination for cache clearing
     *
     * @return array Array of URLs to be cleared
     */
    public function get_taxonomy_and_pagination_urls() {
        $urls = [];

        // Get category URLs
        $categories = get_categories([
            'hide_empty' => false,
            'taxonomy' => 'category'
        ]);

        foreach ($categories as $category) {
            $urls[] = get_category_link($category->term_id);
        }

        // Get tag URLs
        $tags = get_tags([
            'hide_empty' => false
        ]);

        foreach ($tags as $tag) {
            $urls[] = get_tag_link($tag->term_id);
        }

        // Get main pagination URLs (blog/posts page)
        $posts_page_id = get_option('page_for_posts');
        if ($posts_page_id) {
            $posts_page_url = get_permalink($posts_page_id);
        } else {
            $posts_page_url = home_url('/');
        }

        // Get custom taxonomies if any
        $taxonomies = get_taxonomies(['public' => true, '_builtin' => false], 'objects');
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
            ]);

            foreach ($terms as $term) {
                $urls[] = get_term_link($term);
            }
        }

        // Filter out any invalid URLs and make them unique
        $urls = array_filter($urls, function($url) {
            return !is_wp_error($url);
        });

        return array_unique($urls);
    }


    /**
     * Clean the given directory.
     */
    public function lws_optimize_clean_all_filebased_cache($action = "???")
    {
        $logger = fopen($this->log_file, 'a');

        try {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Starting [FULL] cache clearing for action [$action]..." . PHP_EOL);

            // Delete file-based cache directories
            $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);

            // Clear dynamic cache if available
            $this->lwsop_dump_all_dynamic_caches();

            // Clear opcache if available
            if (function_exists("opcache_reset")) {
                opcache_reset();
            }

            // Reset preloading state if needed
            delete_option('lws_optimize_sitemap_urls');
            delete_option('lws_optimize_preload_is_ongoing');
            $this->after_cache_purge_preload();

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }
            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
            return json_encode(['code' => 'ERROR', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        } finally {
            fclose($logger);
        }
    }


    /**
     * Clean the given directory.
     */
    public function lws_optimize_clean_filebased_cache($directory = false, $action = "???")
    {
        $logger = fopen($this->log_file, 'a');


        try {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Starting AutoPurge cache clearing for action [$action]... [$directory]" . PHP_EOL);

            // Get site URL components for main cache
            $site_url = site_url();
            $domain_parts = parse_url($site_url);
            $path = isset($domain_parts['path']) ? trim($domain_parts['path'], '/') : '';

            // Define all cache directories to clean
            $cache_dirs = [
                $this->lwsop_get_content_directory("cache/$path") => 'main desktop',
                $this->lwsop_get_content_directory("cache-mobile/$path") => 'main mobile'
            ];

            // Get cache paths
            $cache_desktop = $this->lwsOptimizeCache->lwsop_set_cachedir($directory);
            $cache_mobile = $this->lwsOptimizeCache->lwsop_set_cachedir($directory, true);
            if (is_dir($cache_desktop)) {
                // Add desktop and mobile specific cache directories
                $cache_dirs = array_merge([$cache_desktop => 'desktop specific'], $cache_dirs);
            } else {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Directory $cache_desktop not found." . PHP_EOL);
            }

            if (is_dir($cache_mobile)) {
                // Add desktop and mobile specific cache directories
                $cache_dirs = array_merge([$cache_mobile => 'mobile specific'], $cache_dirs);
            } else {
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Directory $cache_mobile not found." . PHP_EOL);
            }

            // Clean each cache directory
            foreach ($cache_dirs as $dir => $type) {
                $files = glob($dir . '/index_*');
                if (!empty($files)) {
                    array_map('unlink', array_filter($files, 'is_file'));
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Removed cache files from $type cache ($dir)" . PHP_EOL);
                }
            }

            // Additionally clear cache for categories, tags and pagination
            $taxonomy_urls = $this->get_taxonomy_and_pagination_urls();
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Clearing cache for " . count($taxonomy_urls) . " taxonomy and pagination URLs" . PHP_EOL);

            foreach ($taxonomy_urls as $url) {
                $parsed_url = parse_url($url);
                $path_uri = isset($parsed_url['path']) ? $parsed_url['path'] : '';

                // Clear desktop cache
                $path = $this->lwsOptimizeCache->lwsop_set_cachedir($path_uri);
                $files = glob($path . '/index_*');
                if (!empty($files)) {
                    array_map('unlink', array_filter($files, 'is_file'));
                }

                // Clear mobile cache
                $path_mobile = $this->lwsOptimizeCache->lwsop_set_cachedir($path_uri, true);
                $files = glob($path_mobile . '/index_*');
                if (!empty($files)) {
                    array_map('unlink', array_filter($files, 'is_file'));
                }
            }


            // Handle preload configuration
            $optimize_options = get_option('lws_optimize_config_array', []);
            if ($optimize_options) {
                $optimize_options['filebased_cache']['preload_done'] = 0;

                if (isset($optimize_options['filebased_cache']['preload']) &&
                    $optimize_options['filebased_cache']['preload'] == "true") {

                    $optimize_options['filebased_cache']['preload_ongoing'] = "true";
                    $current_time = time();
                    $next_scheduled = wp_next_scheduled("lws_optimize_start_filebased_preload");

                    // Manage preload scheduling
                    if ($next_scheduled && ($next_scheduled - $current_time < 120)) {
                        // Reschedule if too soon
                        wp_unschedule_event($next_scheduled, "lws_optimize_start_filebased_preload");
                        wp_schedule_event($current_time + 300, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                    } elseif (!$next_scheduled) {
                        // Schedule new if none exists
                        wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
                    }
                } else {
                    // Unschedule if preload disabled
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"),
                        "lws_optimize_start_filebased_preload");
                }

                update_option('lws_optimize_config_array', $optimize_options);
            }

            // Clear other caches
            $this->lwsop_dump_all_dynamic_caches();
            $this->lwsop_remove_opcache();

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }

            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
            return json_encode(['code' => 'ERROR', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        } finally {
            fclose($logger);
        }
    }

    /**
     * Clean the cache completely.
     */
    public function lws_optimize_clean_filebased_cache_cron()
    {
        $logger = fopen($this->log_file, 'a');

        try {
            $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);

            // Handle preload configuration
            $optimize_options = get_option('lws_optimize_config_array', []);
            if ($optimize_options) {
                $optimize_options['filebased_cache']['preload_done'] = 0;

                if (isset($optimize_options['filebased_cache']['preload']) &&
                    $optimize_options['filebased_cache']['preload'] == "true") {

                    $optimize_options['filebased_cache']['preload_ongoing'] = "true";
                    $current_time = time();
                    $next_scheduled = wp_next_scheduled("lws_optimize_start_filebased_preload");

                    // Manage preload scheduling
                    if ($next_scheduled && ($next_scheduled - $current_time < 120)) {
                        // Reschedule if too soon
                        wp_unschedule_event($next_scheduled, "lws_optimize_start_filebased_preload");
                        wp_schedule_event($current_time + 300, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                    } elseif (!$next_scheduled) {
                        // Schedule new if none exists
                        wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
                    }
                } else {
                    // Unschedule if preload disabled
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"),
                        "lws_optimize_start_filebased_preload");
                }

                update_option('lws_optimize_config_array', $optimize_options);
            }

            // Clear other caches
            $this->lwsop_dump_all_dynamic_caches();
            $this->lwsop_remove_opcache();

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }

            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
            return json_encode(['code' => 'ERROR', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        } finally {
            fclose($logger);
        }
    }

    /**
     * Function that restart the preload process after a cache purge (if activated)
     */
    public function after_cache_purge_preload() {
        $logger = fopen($this->log_file, 'a');

        // Handle preload configuration
        $optimize_options = get_option('lws_optimize_config_array', []);
        if ($optimize_options) {
            $optimize_options['filebased_cache']['preload_done'] = 0;

            if (isset($optimize_options['filebased_cache']['preload']) &&
                $optimize_options['filebased_cache']['preload'] == "true") {

                $optimize_options['filebased_cache']['preload_ongoing'] = "true";
                $current_time = time();
                $next_scheduled = wp_next_scheduled("lws_optimize_start_filebased_preload");

                // Manage preload scheduling
                if ($next_scheduled && ($next_scheduled - $current_time < 120)) {
                    // Reschedule if too soon
                    wp_unschedule_event($next_scheduled, "lws_optimize_start_filebased_preload");
                    wp_schedule_event($current_time + 300, "lws_minute", "lws_optimize_start_filebased_preload");
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                } elseif (!$next_scheduled) {
                    // Schedule new if none exists
                    wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
                }
            } else {
                // Unschedule if preload disabled
                wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"),
                    "lws_optimize_start_filebased_preload");
            }

            update_option('lws_optimize_config_array', $optimize_options);
            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);
        }
    }

    public function lwsop_specified_urls_fb()
    {
        check_ajax_referer('lwsop_get_specified_url_nonce', '_ajax_nonce');
        $optimize_options = get_option('lws_optimize_config_array', []);

        if (isset($optimize_options['filebased_cache']) && isset($optimize_options['filebased_cache']['specified'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $optimize_options['filebased_cache']['specified'], 'domain' => site_url()), JSON_PRETTY_PRINT));
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

            $optimize_options = get_option('lws_optimize_config_array', []);

            foreach ($_POST['data'] as $data) {
                $value = sanitize_text_field($data['value']);
                if ($value == "" || empty($value)) {
                    continue;
                }
                $urls[] = $value;
            }

            $optimize_options['filebased_cache']['specified'] = $urls;

            update_site_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;

            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $urls, 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }

    public function lwsop_exclude_urls_fb()
    {
        check_ajax_referer('lwsop_get_excluded_nonce', '_ajax_nonce');
        $optimize_options = get_option('lws_optimize_config_array', []);

        if (isset($optimize_options['filebased_cache']) && isset($optimize_options['filebased_cache']['exclusions'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $optimize_options['filebased_cache']['exclusions'], 'domain' => site_url()), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => array(), 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
    }

    public function lwsop_exclude_cookies_fb()
    {
        check_ajax_referer('lwsop_get_excluded_cookies_nonce', '_ajax_nonce');
        $optimize_options = get_option('lws_optimize_config_array', []);

        if (isset($optimize_options['filebased_cache']) && isset($optimize_options['filebased_cache']['exclusions_cookies'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $optimize_options['filebased_cache']['exclusions_cookies'], 'domain' => site_url()), JSON_PRETTY_PRINT));
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

            $optimize_options = get_option('lws_optimize_config_array', []);
            $optimize_options['filebased_cache']['exclusions'] = $urls;

            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;

            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
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

            $optimize_options = get_option('lws_optimize_config_array', []);
            $optimize_options['filebased_cache']['exclusions_cookies'] = $urls;


            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;

            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
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

            $optimize_options = get_option('lws_optimize_config_array', []);

            $option = sanitize_text_field($option);
            if (isset($optimize_options[$option]) && isset($optimize_options[$option]['state'])) {
                $array = $optimize_options[$option];
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

        $optimize_options = get_option('lws_optimize_config_array', []);

        if (!isset($optimize_options['maintenance_db']) || !isset($optimize_options['maintenance_db']['options'])) {
            $optimize_options['maintenance_db']['options'] = array(
                'myisam' => false,
                'drafts' => false,
                'revisions' => false,
                'deleted_posts' => false,
                'spam_posts' => false,
                'deleted_comments' => false,
                'expired_transients' => false
            );
            update_option('lws_optimize_config_array', $optimize_options);
            $this->optimize_options = $optimize_options;
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $optimize_options['maintenance_db']['options'], 'domain' => site_url())), JSON_PRETTY_PRINT);
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

        $optimize_options = get_option('lws_optimize_config_array', []);
        $optimize_options['maintenance_db']['options'] = $options;

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        if (wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
        }
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $options)), JSON_PRETTY_PRINT);
    }

    public function lws_optimize_create_maintenance_db_options()
    {
        global $wpdb;
        $optimize_options = get_option('lws_optimize_config_array', []);

        $config_options = $optimize_options['maintenance_db']['options'];
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
        if (!isset($_POST['action']) || !isset($_POST['value'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        $value = sanitize_text_field($_POST['value']);

        // No value ? Cannot proceed
        if (!isset($value) || !$value) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        switch ($value) {
            case 'essential':
                $value = "basic";
                break;
            case 'optimized':
                $value = "advanced";
                break;
            case 'max':
                $value = "full";
                break;
            default:
                $value = "basic";
                break;
        }


        $this->lwsop_auto_setup_optimize($value);
        wp_die(json_encode(array('code' => "SUCCESS", "data" => "")), JSON_PRETTY_PRINT);
    }

    public function lwsop_auto_setup_optimize($type = "basic", $no_preloading = false)
    {
        $options = get_option('lws_optimize_config_array', []);
        $options['personnalized'] = "false";
        switch ($type) {
            case 'basic': // recommended only
                $options['autosetup_type'] = "essential";
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = $no_preloading ? "false" : "true";
                $options['filebased_cache']['preload_amount'] = "2";
                $options['filebased_cache']['timer'] = "lws_yearly";
                $options['combine_css']['state'] = "false";
                $options['combine_js']['state'] = "false";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['defer_js']['state'] = "false";
                $options['delay_js']['state'] = "false";
                $options['minify_html']['state'] = "false";
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
                $options['cache_logged_user']['state'] = "false";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";
                $options['image_add_sizes']['state'] = "false";
                $options['remove_css']['state'] = "false";
                $options['critical_css']['state'] = "false";


                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
                wp_schedule_event(time(), 'lws_yearly', 'lws_optimize_clear_filebased_cache_cron');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                }

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
                break;
            case 'advanced':
                $options['autosetup_type'] = "optimized";
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = $no_preloading ? "false" : "true";
                $options['filebased_cache']['preload_amount'] = "3";
                $options['filebased_cache']['timer'] = "lws_yearly";
                $options['combine_css']['state'] = "true";
                $options['combine_js']['state'] = "true";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['defer_js']['state'] = "true";
                $options['delay_js']['state'] = "false";
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
                $options['cache_logged_user']['state'] = "false";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";
                $options['image_add_sizes']['state'] = "true";
                $options['remove_css']['state'] = "false";
                $options['critical_css']['state'] = "false";

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
                wp_schedule_event(time(), 'lws_yearly', 'lws_optimize_clear_filebased_cache_cron');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                }
                break;
            case 'full':
                $options['autosetup_type'] = "max";
                $options['filebased_cache']['state'] = "true";
                $options['filebased_cache']['preload'] = $no_preloading ? "false" : "true";
                $options['filebased_cache']['preload_amount'] = "5";
                $options['filebased_cache']['timer'] = "lws_biyearly";
                $options['combine_css']['state'] = "true";
                $options['combine_js']['state'] = "true";
                $options['minify_css']['state'] = "true";
                $options['minify_js']['state'] = "true";
                $options['defer_js']['state'] = "true";
                $options['delay_js']['state'] = "true";
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
                $options['cache_logged_user']['state'] = "false";
                $options['dynamic_cache']['state'] = "true";
                $options['htaccess_rules']['state'] = "true";
                $options['image_add_sizes']['state'] = "true";
                $options['remove_css']['state'] = "true";
                $options['critical_css']['state'] = "true";

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
                wp_schedule_event(time(), 'lws_biyearly', 'lws_optimize_clear_filebased_cache');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time() + 10, "lws_minute", "lws_optimize_start_filebased_preload");
                }
                break;
            default:
                break;
        }

        // Update all .htaccess files by removing or adding the rules
        if (isset($options['htaccess_rules']['state']) && $options['htaccess_rules']['state'] == "true") {
            $this->lws_optimize_set_cache_htaccess();
        } else {
            $this->unset_cache_htaccess();
        }
        if (isset($options['gzip_compression']['state']) && $options['gzip_compression']['state'] == "true") {
            $this->set_gzip_brotli_htaccess();
        } else {
            $this->unset_gzip_brotli_htaccess();
        }
        $this->lws_optimize_reset_header_htaccess();

        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);

        return $options;
    }

    /**
     * Check and return the state of the preloading
     */
    public function lwsop_check_preload_update()
    {
        check_ajax_referer('lwsop_check_for_update_preload_nonce', '_ajax_nonce');

        $optimize_options = get_option('lws_optimize_config_array', []);

        $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $time = $urls['time'] ?? 0;

        // It has been more than an hour since the latest fetch from the sitemap
        if ($time + 300 < time()) {
            // We get the freshest data
            $urls = $this->get_sitemap_urls();
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

        $optimize_options['filebased_cache']['preload_quantity'] = count($urls);
        $optimize_options['filebased_cache']['preload_done'] = $done;
        $optimize_options['filebased_cache']['preload_ongoing'] = $optimize_options['filebased_cache']['preload_quantity'] - $done == 0 ? "false" : "true";

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
            'quantity' => $optimize_options['filebased_cache']['preload_quantity'] ?? null,
            'done' => $optimize_options['filebased_cache']['preload_done'] ?? null,
            'ongoing' => $optimize_options['filebased_cache']['preload_ongoing'] ?? null,
            'next' => $next ?? null
        ];

        update_option('lws_optimize_config_array', $optimize_options);
        $this->optimize_options = $optimize_options;

        if ($data['quantity'] === null || $data['done'] === null || $data['ongoing'] === null || $data['next'] === null) {
            wp_die(json_encode(array('code' => "ERROR", "data" => $data, 'message' => "Failed to get some of the datas", 'domain' => site_url())), JSON_PRETTY_PRINT);
        }


        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Convert a certain amount (between 1 and 15) of images to the desired format.
     * The function does not check much else, whether or not anything got converted is not checked
     *
     * Deprecated
     */
    public function lws_optimize_convert_media_cron()
    {
        wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
        wp_die(json_encode(array('code' => "SUCCESS", "data" => [], 'domain' => site_url())), JSON_PRETTY_PRINT);
    }

    /**
     * Remove the cron for the restoration of all converted medias, stopping the process
     *
     * Deprecated
     */
    public function lws_optimize_stop_deconvertion()
    {
        check_ajax_referer('lwsop_stop_deconvertion_nonce', '_ajax_nonce');
        wp_unschedule_event(wp_next_scheduled('lwsop_revertOptimization'), 'lwsop_revertOptimization');

        wp_die(json_encode(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url())), JSON_PRETTY_PRINT);
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

    public function setupLogfile() {
        // Create log file in uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/lwsoptimize';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        $this->log_file = $log_dir . '/debug.log';

        // Check if the log file exists and is too large (over 5MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 5 * 1024 * 1024) {
            // Create a timestamp for the archived log
            $timestamp = date('Y-m-d-His');

            // Rename the existing log file
            $archive_name = $log_dir . '/debug-' . $timestamp . '.log';
            rename($this->log_file, $archive_name);

            // Keep only the latest 15 archived logs
            $log_files = glob($log_dir . '/debug-*.log');
            if ($log_files && count($log_files) > 15) {
                usort($log_files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                $files_to_delete = array_slice($log_files, 0, count($log_files) - 5);
                foreach ($files_to_delete as $file) {
                    @unlink($file);
                }
            }
        }

        // Create a new log file if it doesn't exist
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
            // Add header to the new log file
            $header = '[' . date('Y-m-d H:i:s') . '] Log file created' . PHP_EOL;
            file_put_contents($this->log_file, $header);
        }
    }
}

