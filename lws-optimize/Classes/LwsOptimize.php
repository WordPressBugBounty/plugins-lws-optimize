<?php

namespace Lws\Classes;

use Google\Web_Stories\Remove_Transients;
use Lws\Classes\Admin\LwsOptimizeManageAdmin;
use Lws\Classes\FileCache\LwsOptimizeAutoPurge;
use Lws\Classes\Images\LwsOptimizeImageOptimizationPro;
use Lws\Classes\Images\LwsOptimizeImageSrcset;
use Lws\Classes\LazyLoad\LwsOptimizeLazyLoading;
use Lws\Classes\FileCache\LwsOptimizeFileCache;
use Lws\Classes\FileCache\LwsOptimizeCloudFlare;
use Lws\Classes\Front\LwsOptimizeJSManager;
use Lws\Classes\Front\LwsOptimizeDelayJS;
use Lws\Classes\Front\LwsOptimizeCriticalCSSManager;
use Lws\Classes\Front\LwsOptimizeFontPreload;
use Lws\Classes\Admin\LwsOptimizeDashboardWidget;
use Lws\Classes\Integrations\LwsOptimizeCloudflareAPO;
use Lws\Classes\RUM\LwsOptimizeRUM;
use Lws\Classes\Images\LwsOptimizeImageFrontManager;

class LwsOptimize
{
    public $log_file;
    public $lwsOptimizeCache;
    public $lwsImageOptimization;
    public $lwsImageOptimizationPro;
    public $cloudflare_manager;
    public $nginx_purger;
    public $chosen_purger;

    /**
     * Centralized debug logger, gated behind WP_DEBUG.
     */
    public function lwsop_debug_log($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- centralized, WP_DEBUG-gated logger
        }
    }

    public function __construct()
    {
        // Initialize the global LWS Optimize instance
        $GLOBALS['lws_optimize'] = $this;

        // Ensure a per-site secret exists for authenticating the preload crawler header.
        if (!get_option('lwsop_preload_secret')) {
            update_option('lwsop_preload_secret', bin2hex(random_bytes(16)), false);
        }

        // Create the log file if needed, otherwise just get the path
        $this->setupLogfile();

        // Get all the options for LWSOptimize. If array is not found, initialize it
        $optimize_options = get_option('lws_optimize_config_array', []);
        if (empty($optimize_options)) {
            // Generate the options
            $optimize_options = $this->lwsop_auto_setup_optimize("basic", true);

            // Deactivate the filebased_cache preloading
            $optimize_options['filebased_cache']['preload'] = "false";
            delete_option('lws_optimize_preload_is_ongoing');
            if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
            }

            // If it got installed by the LWS Auto-installer, then proceed to activate the plugin by default
            if (get_option('lws_from_autoinstall_optimize', false)) {
                delete_option("lws_from_autoinstall_optimize");
                delete_option('lws_optimize_offline');
            }

            update_option('lws_optimize_config_array', $optimize_options);
        }

        // Load translations at the recommended plugins_loaded timing (priority 10)
        add_action('plugins_loaded', [$this, 'lws_optimize_load_textdomain']);

        // Memcached dropin lifecycle — runs after textdomain is loaded (priority 20)
        // so that lwsop_validate_memcached_environment() can safely call __()
        add_action('plugins_loaded', [$this, 'lwsop_boot_memcached_dropin'], 20);

        // Start actions that will occur on plugin initialization
        add_action('init', [$this, "lws_optimize_init"]);

        // Add new schedules time for crons
        add_filter('cron_schedules', [$this, 'lws_optimize_timestamp_crons']);

        // Init the FileCache Class
        $this->lwsOptimizeCache = new LwsOptimizeFileCache($this);

        // Init the ImageOptimization Class
        $this->lwsImageOptimization = new LwsOptimizeImageOptimizationPro();

        if (!get_option('lws_optimize_deactivate_temporarily')) {
            // If the plugin was updated...
            add_action('plugins_loaded', [$this, 'lws_optimize_after_update_actions']);

            $avif_probe = get_option('lwsop_avif_probe', null);
            if (!is_array($avif_probe) || ($avif_probe['ts'] ?? 0) < (time() - 3600)) {
                $supported = 0;
                if (extension_loaded('imagick') && class_exists('Imagick')) {
                    try {
                        $im = new \Imagick();
                        $supported = in_array('AVIF', $im->queryFormats(), true) ? 1 : 0;
                    } catch (\Exception $e) {
                        $supported = 0;
                    }
                }
                update_option('lwsop_avif_probe', ['supported' => $supported, 'ts' => time()], false);
            }

            if ($this->lwsop_check_option('image_add_sizes')['state'] === "true") {
                LwsOptimizeImageFrontManager::startImageWidth();
            }

            if (apply_filters('lwsop_rewrite_srcset', true)) {
                LwsOptimizeImageSrcset::startActions();
            }

            // Critical CSS front-end hooks: inline critical CSS in <head> and async-load
            // all other stylesheets. Only active when state=true and a mode is selected.
            $ccss_opt    = get_option('lws_optimize_config_array', [])['critical_css'] ?? [];
            $ccss_active = (($ccss_opt['state'] ?? 'false') === 'true')
                           && (($ccss_opt['mode'] ?? 'off') !== 'off');
            if ($ccss_active) {
                LwsOptimizeCriticalCSSManager::startActions();
            }
            // Always register the cron callback so scheduled events can fire even
            // when the toggle is temporarily disabled between scheduling and execution.
            add_action('lwsop_generate_critical_css', [LwsOptimizeCriticalCSSManager::class, 'generate_critical_css_cron'], 10, 1);

            if (($this->lwsop_check_option('cloudflare_apo')['state'] ?? '') === 'true') {
                LwsOptimizeCloudflareAPO::startActions();
            }

            // RUM (Real User Monitoring) collector. Anonymous, beacon-based.
            if (($this->lwsop_check_option('rum')['state'] ?? '') === 'true') {
                LwsOptimizeRUM::startActions();
            }

            if (($this->lwsop_check_option('font_preload')['state'] ?? '') === 'true') {
                LwsOptimizeFontPreload::startActions();
            }

            if (is_admin()) {
                LwsOptimizeDashboardWidget::startActions();
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

            // Enqueue JQuery on the plugin
            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });

            // Add the filters that can be used to clear all or parts of the cache
            add_filter('lws_optimize_convert_media_cron', [$this, 'lws_optimize_convert_media_cron'], 10, 2);
            add_filter('lws_optimize_clear_filebased_cache', [$this, 'lws_optimize_clean_filebased_cache'], 10, 2);
            add_filter('lws_optimize_clear_filebased_cache_cron', [$this, 'lws_optimize_clean_filebased_cache_cron'], 10, 2);
            add_filter('lws_optimize_clear_all_filebased_cache', [$this, 'lws_optimize_clean_all_filebased_cache'], 10, 1);

            // Action to start preloading the file-based cache
            add_action('lws_optimize_start_filebased_preload', [$this, 'lws_optimize_start_filebased_preload']);

            // If the maintenance is active but has no cron, start one
            if ($this->lwsop_check_option("maintenance_db")['state'] == "true" && !wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                wp_schedule_event(time(), 'weekly', 'lws_optimize_maintenance_db_weekly');
            }

            // Check daily if Memcached is working fine ; deactivate if not
            add_action('lwsop_daily_health_check', [$this, 'lwsop_periodic_memcached_validation']);
            if (!wp_next_scheduled('lwsop_daily_health_check')) {
                wp_schedule_event(time() + 600, 'daily', 'lwsop_daily_health_check');
            }

            // Activate functions related to CloudFlare
            $this->cloudflare_manager = new LwsOptimizeCloudFlare();
            $this->cloudflare_manager->activate_cloudflare_integration();

            // Start the file-based cache actions
            if ($this->lwsop_check_option('filebased_cache')['state'] === "true") {
                $this->lwsOptimizeCache->lwsop_launch_cache();
            }

            // Start the autopurge actions
            if ($this->lwsop_check_option("autopurge")['state'] == "true") {
                $autopurge_manager = new LwsOptimizeAutoPurge();
                $autopurge_manager->start_autopurge();
            }
        }

        // Update the configuration options in case they got modified
        update_option('lws_optimize_config_array', $optimize_options);

        // Add custom action hooks for external cache clearing
        add_action('lws_optimize_clear_all_cache', [$this, 'clear_all_cache_external']);
        add_action('lws_optimize_clear_url_cache', [$this, 'clear_url_cache_external'], 10, 1);
    }

    // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.WP.AlternativeFunctions.file_system_operations_touch,WordPress.WP.AlternativeFunctions.file_system_operations_is_writable,WordPress.WP.AlternativeFunctions.rename_rename,PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected,PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite -- from here to the end of the class: direct filesystem calls are used deliberately for the cache/.htaccess/log/drop-in engine (activation-time, cron, and per-request hot-path writes); all writes target LWS_OP_UPLOADS (WP_CONTENT_DIR . '/cache/lwsoptimize/'), never the plugin's own directory, so update-time data loss does not apply. WP_Filesystem would add credential-prompt UX and overhead that this caching plugin can't tolerate.

    /**
     * Clear all cache via external do_action hook
     * Usage: do_action('lws_optimize_clear_all_cache');
     */
    public function clear_all_cache_external() {
        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] External request: Clearing all cache' . PHP_EOL);
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
     */
    public function clear_url_cache_external($url) {
        if (empty($url)) {
            return false;
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] External request: Clearing cache for URL: ' . $url . PHP_EOL);
        fclose($logger);

        // Parse the URL to get the path
        $parsed_url = wp_parse_url($url);
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
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Removed desktop cache for: ' . $path_uri . PHP_EOL);
            fclose($logger);
        }

        // Remove mobile cache files
        $files_mobile = glob($path_mobile . '/index_*');
        if (!empty($files_mobile)) {
            array_map('unlink', array_filter($files_mobile, 'is_file'));
            $removed = true;
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Removed mobile cache for: ' . $path_uri . PHP_EOL);
            fclose($logger);
        }

        // If cache was cleared, also clear dynamic cache for this URL
        if ($removed) {
            $this->lwsop_dump_all_dynamic_caches();
        }

        return $removed;
    }

    public function lws_optimize_load_textdomain()
    {
        load_textdomain('lws-optimize', LWS_OP_DIR . '/languages/lws-optimize-' . determine_locale() . '.mo');
    }

    public function lwsop_boot_memcached_dropin()
    {
        if (get_option('lws_optimize_deactivate_temporarily')) {
            return;
        }

        $optimize_options = get_option('lws_optimize_config_array', []);

        // Object cache drop-in lifecycle.
        // CRITICAL: never delete or overwrite the drop-in if it belongs to a third party
        // (Redis Object Cache, W3TC, wp-redis, etc.) — always go through the safe helpers.
        if ($this->lwsop_check_option('memcached')['state'] === "true") {
            $env = $this->lwsop_validate_memcached_environment();
            if (!$env['ok'] && $env['severity'] === 'fatal') {
                // Auto-disable pour éviter l'écran blanc à la prochaine requête authentifiée.
                $optimize_options['memcached']['state'] = "false";
                update_option('lws_optimize_config_array', $optimize_options);
                $this->lwsop_safe_delete_dropin(LWSOP_OBJECTCACHE_PATH);
                if (!empty($this->log_file)) {
                    @file_put_contents(
                        $this->log_file,
                        sprintf('[%s] Auto-disabled Memcached at boot — %s : %s' . PHP_EOL,
                            gmdate('Y-m-d H:i:s'), $env['reason'], substr($env['message'], 0, 200)),
                        FILE_APPEND
                    );
                }
            } else {
                // Install OUR drop-in only if not already present
                if (!file_exists(LWSOP_OBJECTCACHE_PATH)) {
                    $this->lwsop_safe_write_dropin(
                        LWSOP_OBJECTCACHE_PATH,
                        LWS_OP_DIR . '/views/object-cache.php'
                    );
                }
            }
        } else {
            // Memcached disabled → remove OUR drop-in, but never touch a third-party one.
            $this->lwsop_safe_delete_dropin(LWSOP_OBJECTCACHE_PATH);
        }
    }

    /**
     * Initial setup of the plugin ; execute all basic actions
     */
    public function lws_optimize_init()
    {
        $optimize_options = get_option('lws_optimize_config_array', []);

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
        // This will manage everything that can happen on the lws-optimize page
        $admin_manager = new LwsOptimizeManageAdmin();
        $admin_manager->manage_options();

        if (! function_exists('wp_crop_image')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $timer = $optimize_options['filebased_cache']['timer'] ?? "lws_yearly";
        switch ($timer) {
            case 'lws_daily':
                $cdn_date = "86400";
                break;
            case 'lws_weekly':
                $cdn_date = "604800";
                break;
            case 'lws_monthly':
                $cdn_date = "2592000";
                break;
            case 'lws_thrice_monthly':
                $cdn_date = "7776000";
                break;
            case 'lws_biyearly':
                $cdn_date = "15552000";
                break;
            case 'lws_yearly':
                $cdn_date = "31104000";
                break;
            case 'lws_two_years':
                $cdn_date = "62208000";
                break;
            case 'lws_never':
                $cdn_date = "93312000";
                break;
            default:
                $cdn_date = "7776000";
                break;
        }

        // Schedule the cache cleanout again if it has been deleted
        // If the plugin is OFF or the filecached is deactivated, unregister the WPCron
        if (isset($optimize_options['filebased_cache']['timer']) && !get_option('lws_optimize_deactivate_temporarily')) {
            if (!wp_next_scheduled('lws_optimize_clear_filebased_cache_cron') && $optimize_options['filebased_cache']['timer'] != 0) {
                wp_schedule_event(time() + $cdn_date, $optimize_options['filebased_cache']['timer'], 'lws_optimize_clear_filebased_cache_cron');
            }
        } elseif (get_option('lws_optimize_deactivate_temporarily') || $this->lwsop_check_option('filebased_cache')['state'] === "false") {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
        }
    }

    /**
     * Routine actions after an update happened
     */
    public function lws_optimize_after_update_actions() {
        if (get_option('wp_lwsoptimize_post_update')) {
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

            // $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $this);
            // $logger = fopen($this->log_file, 'a');
            // fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Removed cache after update' . PHP_EOL);
            // fclose($logger);

            // $this->after_cache_purge_preload();
        }
    }

    /**
     * Add a new timestamp for crons
     */
    public function lws_optimize_timestamp_crons($schedules)
    {

        $lws_optimize_cache_timestamps = [
            'lws_daily' => [86400, __('Once a day', 'lws-optimize')],
            'lws_weekly' => [604800, __('Once a week', 'lws-optimize')],
            'lws_monthly' => [2629743, __('Once a month', 'lws-optimize')],
            'lws_thrice_monthly' => [7889232, __('Once every 3 months', 'lws-optimize')],
            'lws_biyearly' => [15778463, __('Once every 6 months', 'lws-optimize')],
            'lws_yearly' => [31556926, __('Once a year', 'lws-optimize')],
            'lws_two_years' => [63113852, __('Once every 2 years', 'lws-optimize')],
            'lws_never' => [0, __('Never expire', 'lws-optimize')],
        ];

        foreach ($lws_optimize_cache_timestamps as $code => $schedule) {
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
                $ipxchange_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
                $ipXchange_IP = $ipxchange_host !== '' ? (dns_get_record($ipxchange_host)[0]['ip'] ?? false) : false;
                $host = isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : false;

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
        } elseif (isset($_ENV['lwscache']) && strtolower(sanitize_text_field(wp_unslash($_ENV['lwscache']))) == "on") {
            // If LWSCache, simply purge the cache
            wp_remote_request(get_site_url(null, '', 'https') . "/*", array('method' => 'PURGE'));
            wp_remote_request(get_site_url(null, '', 'http') . "/*", array('method' => 'PURGE'));

            wp_remote_request(get_site_url(null, '', 'https') . "/*", array('method' => 'FULLPURGE'));
            wp_remote_request(get_site_url(null, '', 'http') . "/*", array('method' => 'FULLPURGE'));
            $chosen_purger = "LWS Cache";
        } else {
            // No cache, no purge
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] No compatible cache found or cache deactivated: no server cache purge' . PHP_EOL);
            fclose($logger);
            return (json_encode(array('code' => "FAILURE", 'data' => "No cache method usable"), JSON_PRETTY_PRINT));
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Compatible cache found : starting server cache purge on {$chosen_purger}" . PHP_EOL);
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

    /**
     * Recursively fetches URLs from sitemaps
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
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to fetch sitemap: ' . $url . PHP_EOL);
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

    /**
     * Helper method to get sitemap URLs
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

        // Check if sitemap exists and is valid XML
        $headers = @get_headers($sitemap);
        $is_valid = false;

        if ($headers && is_array($headers) && isset($headers[0]) && intval(substr($headers[0], 9, 3)) === 200) {
            // Check if it's actually XML by trying to load it
            $sitemap_content = @file_get_contents($sitemap);
            if ($sitemap_content !== false) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($sitemap_content);
            if ($xml !== false) {
                $is_valid = true;
            }
            libxml_clear_errors();
            }
        }

        // Fall back to alternative sitemap if the first one is invalid
        if (!$is_valid) {
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
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Starting to fetch sitemap [$sitemap] again" . PHP_EOL);
        fclose($logger);

        // Otherwise fetch fresh URLs from sitemap
        $urls = $this->fetch_url_sitemap($sitemap, []);

        // If sitemap yielded nothing, fall back to querying WordPress directly
        if (empty($urls)) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] No URLs from sitemap, falling back to WordPress query" . PHP_EOL);
            fclose($logger);
            $urls = $this->get_urls_from_wp();
        }

        if (!empty($urls)) {
            update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);
        }

        return $urls;
    }

    /**
     * Fallback URL source: query WordPress directly for all published, publicly
     * accessible content when no usable sitemap is available.
     */
    public function get_urls_from_wp()
    {
        $urls = [];

        // Always include the home URL
        $urls[] = trailingslashit(home_url('/'));

        // Collect all public, queryable post types (built-in + custom)
        $post_types = get_post_types(['public' => true, 'publicly_queryable' => true], 'names');
        // Ensure core types are included even if publicly_queryable differs
        foreach (['post', 'page'] as $core_type) {
            if (!in_array($core_type, $post_types, true)) {
                $post_types[] = $core_type;
            }
        }

        // Remove attachments — their "pages" are rarely useful to preload
        unset($post_types['attachment']);
        $post_types = array_values($post_types);

        if (empty($post_types)) {
            return $urls;
        }

        // Query in batches of 500 to avoid memory issues on large sites
        $batch_size = 500;
        $paged      = 1;

        do {
            $query = new \WP_Query([
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'posts_per_page'         => $batch_size,
                'paged'                  => $paged,
                'no_found_rows'          => false,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'ignore_sticky_posts'    => true,
                'fields'                 => 'ids',
            ]);

            if (empty($query->posts)) {
                break;
            }

            foreach ($query->posts as $post_id) {
                $permalink = get_permalink($post_id);
                if ($permalink && !in_array($permalink, $urls, true)) {
                    $urls[] = $permalink;
                }
            }

            $max_pages = (int) $query->max_num_pages;
            $paged++;
            wp_reset_postdata();
        } while ($paged <= $max_pages);

        return array_values(array_unique($urls));
    }

    /**
     * Preload the file-based cache. Get all URLs from the sitemap and cache each of them
     */
    public function lws_optimize_start_filebased_preload()
    {
        $_lwsop_cfg = get_option('lws_optimize_config_array', []);
        if (($_lwsop_cfg['filebased_cache']['preload'] ?? 'false') !== 'true') {
            delete_option('lws_optimize_preload_is_ongoing');
            // Désinscrire aussi le cron pour qu'il ne se re-déclenche pas chaque minute
            if (wp_next_scheduled('lws_optimize_start_filebased_preload')) {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
            }
            return;
        }

        // Atomic lock: add_option() returns false when the key already exists in the DB
        // (MySQL UNIQUE constraint prevents two concurrent INSERTs). This is the only
        // reliable way to stop two cron instances from running simultaneously in WordPress.
        if (!add_option('lws_optimize_preload_is_ongoing', time(), '', false)) {
            $existing = (int) get_option('lws_optimize_preload_is_ongoing', 0);
            if (time() - $existing <= 600) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preloading still ongoing, not starting new instance" . PHP_EOL);
                fclose($logger);
                return;
            }
            // Stale lock (> 10 min) — force acquire and continue
            update_option('lws_optimize_preload_is_ongoing', time());
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preloading lock stale (>600s), forcing new instance" . PHP_EOL);
            fclose($logger);
        }

        try {

        $lws_filebased = new LwsOptimizeFileCache($GLOBALS['lws_optimize']);

        $urls = get_option('lws_optimize_sitemap_urls', ['time' => 0, 'urls' => []]);
        $time = $urls['time'] ?? 0;

        // It has been more than an hour since the latest fetch from the sitemap
        if ($time +  3600 < time()) {
            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] URLs last fetched more than 1 hour ago, fetching new data" . PHP_EOL);
            fclose($logger);

            // We get the freshest data
            $urls = $this->get_sitemap_urls();

            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] New URLs fetched. Amount: " . count($urls) . PHP_EOL);
            fclose($logger);
        } else {
            // We get the ones currently saved in base
            $urls = $urls['urls'] ?? [];
        }

        $array = get_option('lws_optimize_config_array', []);
        if (!isset($array['filebased_cache']['state']) || $array['filebased_cache']['state'] == "false") {
            // Create log entry
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Filebased cache is disabled, aborting preload" . PHP_EOL);
            fclose($logger);
            return;
        }


        // Initialize variables from configuration
        $max_try = intval($array['filebased_cache']['preload_amount'] ?? 5);
        $done = 0; // recounted from scratch each run by iterating all urls
        $current_try = 0;

        $current_error_try = 0; // Track errors to stop if too much are found
        $max_error_try = 20; // Stop if we have 20 errors during the loop (to avoid infinite loops)

        // Define user agents for preloading with custom identifiers (HTTP/2 compatible)
        $userAgents = [
            'desktop' => 'LWSOptimizePreload/2.0 (HTTP/2)',
            'mobile' => 'LWSOptimizePreload/2.0 (Mobile; HTTP/2)'
        ];


        // Remove mobile agent if mobile caching is disabled
        if (isset($array['cache_mobile_user']['state']) && $array['cache_mobile_user']['state'] == "true") {
            unset($userAgents['mobile']);
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Starting preload batch - max: ' . $max_try . ' urls' . PHP_EOL);
        fclose($logger);

        $preload_secret = get_option('lwsop_preload_secret', '');

        // Cross-run rate limit: some hosts (e.g. LWS) blacklist the IP past a certain
        // rate of requests/minute. The admin already controls throughput via
        // "preload_amount" ("pages per minute cached"), so that's the budget we enforce
        // here — one entry per page, not per HTTP request (desktop+mobile count as one
        // page). The budget is shared across cron ticks — a batch starting right after
        // the previous one finished must know what was already sent this minute, or the
        // two runs together blow past whatever the host tolerates.
        $request_log = get_option('lwsop_preload_request_log', []);
        $request_log = array_values(array_filter($request_log, function ($ts) {
            return $ts > time() - 60;
        }));
        $rate_limited = false;

        // Process URLs from the sitemap
        foreach ($urls as $key => $url) {
            if ($current_try >= $max_try || $current_error_try >= $max_error_try) {
                break;
            }

            $path_uri    = wp_parse_url($url, PHP_URL_PATH) ?: '/';
            $path        = $lws_filebased->lwsop_set_cachedir($path_uri);
            $path_mobile = $lws_filebased->lwsop_set_cachedir($path_uri, true);

            $file_exists        = glob($path . "index*")        ?: [];
            $file_exists_mobile = glob($path_mobile . "index*") ?: [];

            // Determine what still needs caching
            $need_desktop = empty($file_exists);
            $need_mobile  = isset($userAgents['mobile']) && empty($file_exists_mobile);

            // Already fully cached — count and skip
            if (!$need_desktop && !$need_mobile) {
                $done++;
                continue;
            }

            // Budget exhausted for this rolling minute — stop entirely and let the
            // next cron tick (once older timestamps age out) pick up here. This URL
            // is left untouched in the queue, it was never actually attempted.
            if (count($request_log) >= $max_try) {
                $rate_limited = true;
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload rate limit reached ($max_try pages/min) - pausing until next cycle" . PHP_EOL);
                fclose($logger);
                break;
            }

            $request_log[] = time();
            update_option('lwsop_preload_request_log', $request_log, false);

            // Fetch only what is still missing
            $sep = wp_parse_url($url, PHP_URL_QUERY) ? '&' : '?';
            $fetch_diagnostics = [];
            foreach ($userAgents as $type => $agent) {
                if ($type === 'desktop' && !$need_desktop) continue;
                if ($type === 'mobile'  && !$need_mobile)  continue;

                $request_url = $url . $sep . 'nocache=' . time() . wp_rand(1000, 9999);

                $response = wp_remote_get($request_url, [
                    'timeout'            => 120,
                    'user-agent'         => $agent,
                    'headers'            => [
                        'Cache-Control' => 'no-cache, no-store, must-revalidate',
                        'Pragma'        => 'no-cache',
                        'Expires'       => '0',
                        'X-LWS-Preload' => $preload_secret,
                        'X-No-Cache'    => '1',
                    ],
                    'sslverify'          => true,
                    'blocking'           => true,
                    'cookies'            => [],
                    'reject_unsafe_urls' => false,
                    'redirection'        => 3,
                ]);

                // Record what actually happened on the wire so a cache-miss can be diagnosed
                // (timeout, WAF/firewall block, SSL failure, non-200 status, ...) instead of
                // only ever knowing that no file appeared on disk.
                if (is_wp_error($response)) {
                    $fetch_diagnostics[] = "$type: " . $response->get_error_message();
                } else {
                    $fetch_diagnostics[] = "$type: HTTP " . wp_remote_retrieve_response_code($response);
                }
            }

            // Check if desktop cache was created (primary success indicator)
            $file_exists = glob($path . "index*") ?: [];
            if (!empty($file_exists)) {
                $done++;
                $current_try++;
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Successfully cached: $url" . PHP_EOL);
                fclose($logger);
            } else {
                $current_error_try++;
                $logger = fopen($this->log_file, 'a');
                $diag = !empty($fetch_diagnostics) ? ' (' . implode(', ', $fetch_diagnostics) . ')' : '';
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Failed to cache: $url - removed from queue$diag" . PHP_EOL);
                fclose($logger);
                unset($urls[$key]);
            }
        }

        if ($current_error_try >= $max_error_try) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload batch stopped due to excessive errors ($current_error_try)" . PHP_EOL);
            fclose($logger);
        }

        if ($current_try > 0) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload batch completed - URLs cached: $current_try, total done: $done" . PHP_EOL);
            fclose($logger);
        }

        // Reindex after unset() calls for failed URLs
        $urls = array_values($urls);
        update_option('lws_optimize_sitemap_urls', ['time' => time(), 'urls' => $urls]);

        // Persist accurate progress so the admin UI reflects reality
        $array['filebased_cache']['preload_done'] = $done;

        // Completion: nothing was attempted this run (current_try=0, current_error_try=0)
        // means every URL was either already cached (skipped) or the list is now empty.
        // A rate-limited run must NOT be treated as "complete" — there is still work
        // left, it was just deferred to the next cron tick.
        if ($current_try === 0 && $current_error_try === 0 && !$rate_limited) {
            // Only the cycle-status flag is cleared here — 'preload' is the user's
            // ON/OFF preference and must survive cycle completion so the next purge
            // (post publish, nightly cron, ...) knows to restart preloading.
            $array['filebased_cache']['preload_ongoing'] = "false";
            update_option('lws_optimize_config_array', $array);

            $ts = wp_next_scheduled('lws_optimize_start_filebased_preload');
            if ($ts) {
                wp_unschedule_event($ts, 'lws_optimize_start_filebased_preload');
            }

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload complete — all URLs cached. Cron stopped." . PHP_EOL);
            fclose($logger);
        } else {
            update_option('lws_optimize_config_array', $array);
        }

        } finally {
            delete_option('lws_optimize_preload_is_ongoing');
        }
    }

    public function set_gzip_brotli_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        try {
            // Create or verify .htaccess file
            if (!file_exists($htaccess)) {
                $old_umask = umask(0);

                if (!chmod(ABSPATH, 0755)) {
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not change directory permissions for .htaccess' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                if (!touch($htaccess)) {
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not create .htaccess file' . PHP_EOL);
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
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not make .htaccess writable' . PHP_EOL);
                    fclose($logger);
                    return;
                }
            }

            // Read existing content
            $htaccess_content = file_get_contents($htaccess);
            if ($htaccess_content === false) {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not read .htaccess file' . PHP_EOL);
                fclose($logger);
                return;
            }

            // Remove existing GZIP rules
            $pattern = '/#LWS OPTIMIZE - GZIP COMPRESSION[\s\S]*?#END LWS OPTIMIZE - GZIP COMPRESSION\n?/';
            $htaccess_content = preg_replace($pattern, '', $htaccess_content);

            // Skip if temporarily deactivated
            if (get_option('lws_optimize_deactivate_temporarily')) {
                if (!$this->lwsop_atomic_write($htaccess, $htaccess_content)) {
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not update .htaccess content' . PHP_EOL);
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

            // Deflate compression rules (fallback when mod_brotli is unavailable)
            $hta .= "<IfModule !mod_brotli.c>\n";
            $hta .= "<IfModule mod_deflate.c>\n";
            $hta .= "<IfModule mod_filter.c>\n";
            foreach ($compress_types as $type) {
                $hta .= "AddOutputFilterByType DEFLATE " . $type . "\n";
            }
            $hta .= "</IfModule>\n";
            $hta .= "</IfModule>\n";
            $hta .= "</IfModule>\n";

            // Add header and combine content
            $hta = "#LWS OPTIMIZE - GZIP COMPRESSION\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n"
                  . $hta
                  . "#END LWS OPTIMIZE - GZIP COMPRESSION\n";
            $new_content = $hta . $htaccess_content;

            // Write new content
            if (!$this->lwsop_atomic_write($htaccess, $new_content)) {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to write new .htaccess content' . PHP_EOL);
            } else {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Successfully updated GZIP rules in .htaccess' . PHP_EOL);
            }

        } catch (\Exception $e) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Error updating .htaccess: ' . $e->getMessage() . PHP_EOL);
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

        // PHP stats intermediary option
        $php_intermediary = $this->lwsop_check_option('htaccess_php_intermediary')['state'] === 'true';

        // Deploy the serve script outside /plugins/ so security plugins that block
        // PHP execution under /plugins/ cannot 403 the cache intermediary.
        $deployed_script  = $php_intermediary ? $this->lwsop_deploy_serve_script() : null;
        $serve_script     = $deployed_script
            ?? ltrim(str_replace(ABSPATH, '', LWS_OP_DIR . 'Classes/FileCache/lwsop_cache_serve.php'), '/');

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
                $additional = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            }

            if ($this->lwsop_plugin_active('polylang/polylang.php')) {
                $polylang_settings = get_option("polylang");
                if (isset($polylang_settings["force_lang"]) && ($polylang_settings["force_lang"] == 2 || $polylang_settings["force_lang"] == 3)) {
                    $additional = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
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
        $current_date = gmdate("d/m/Y H:i:s", time());

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
                    $this->lwsop_debug_log("LWSOptimize: Could not change directory permissions for .htaccess");
                    $available_htaccess = false;
                }

                // Try creating again with new permissions
                if (!touch($htaccess)) {
                    // Still failed, abort
                    $this->lwsop_debug_log("LWSOptimize: Could not create .htaccess file");
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
                $this->lwsop_atomic_write($htaccess, $htaccess_content);
            } else {
                // Log error if htaccess can't be modified
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Unable to modify .htaccess - file not found or not writable' . PHP_EOL);
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
                    if ($php_intermediary) {
                        $hta .= "RewriteRule ^(.*) $serve_script [L,E=LWSOP_CACHE:HIT]\n\n";
                    } else {
                        $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path$http_path/$1index_2.html [L,E=LWSOP_CACHE:HIT]\n\n";
                    }

                    // If connected users on mobile have their own cache
                    if ($this->lwsop_check_option('cache_mobile_user')['state'] === "false") {
                        $hta .= "## Connected mobile ##\n";
                        $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
                        $hta .= "RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]\n";
                        $hta .= "RewriteCond %{HTTP_USER_AGENT} .*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
                        $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path_mobile$http_path/$1index_2.html -f\n";
                        if ($php_intermediary) {
                            $hta .= "RewriteRule ^(.*) $serve_script [L,E=LWSOP_CACHE:HIT]\n\n";
                        } else {
                            $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path_mobile$http_path/$1index_2.html [L,E=LWSOP_CACHE:HIT]\n\n";
                        }
                    }
                }

                // If not connected users on mobile have cache
                if ($this->lwsop_check_option('cache_mobile_user')['state'] === "false") {
                    $hta .= "## Anonymous mobile ##\n";
                    $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
                    $hta .= "RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_ [NC]\n";
                    $hta .= "RewriteCond %{HTTP_USER_AGENT} .*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
                    $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path_mobile$http_path/$1index_0.html -f\n";
                    if ($php_intermediary) {
                        $hta .= "RewriteRule ^(.*) $serve_script [L,E=LWSOP_CACHE:HIT]\n\n";
                    } else {
                        $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path_mobile$http_path/$1index_0.html [L,E=LWSOP_CACHE:HIT]\n\n";
                    }
                }

                // Non connected and non-mobile users
                $hta .= "## Anonymous desktop ##\n";
                $hta .= $this->lws_optimize_basic_htaccess_conditions($http_host, $admin_users);
                $hta .= "RewriteCond %{HTTP:Cookie} !wordpress_logged_in [NC]\n";
                $hta .= "RewriteCond %{HTTP_USER_AGENT} !^.*\bCrMo\b|CriOS|Android.*Chrome\/[.0-9]*\s(Mobile)?|\bDolfin\b|Opera.*Mini|Opera.*Mobi|Android.*Opera|Mobile.*OPR\/[0-9.]+|Coast\/[0-9.]+|Skyfire|Mobile\sSafari\/[.0-9]*\sEdge|IEMobile|MSIEMobile|fennec|firefox.*maemo|(Mobile|Tablet).*Firefox|Firefox.*Mobile|FxiOS|bolt|teashark|Blazer|Version.*Mobile.*Safari|Safari.*Mobile|MobileSafari|Tizen|UC.*Browser|UCWEB|baiduboxapp|baidubrowser|DiigoBrowser|Puffin|\bMercury\b|Obigo|NF-Browser|NokiaBrowser|OviBrowser|OneBrowser|TwonkyBeamBrowser|SEMC.*Browser|FlyFlow|Minimo|NetFront|Novarra-Vision|MQQBrowser|MicroMessenger|Android.*PaleMoon|Mobile.*PaleMoon|Android|blackberry|\bBB10\b|rim\stablet\sos|PalmOS|avantgo|blazer|elaine|hiptop|palm|plucker|xiino|Symbian|SymbOS|Series60|Series40|SYB-[0-9]+|\bS60\b|Windows\sCE.*(PPC|Smartphone|Mobile|[0-9]{3}x[0-9]{3})|Window\sMobile|Windows\sPhone\s[0-9.]+|WCE;|Windows\sPhone\s10.0|Windows\sPhone\s8.1|Windows\sPhone\s8.0|Windows\sPhone\sOS|XBLWP7|ZuneWP7|Windows\sNT\s6\.[23]\;\sARM\;|\biPhone.*Mobile|\biPod|\biPad|Apple-iPhone7C2|MeeGo|Maemo|J2ME\/|\bMIDP\b|\bCLDC\b|webOS|hpwOS|\bBada\b|BREW.*$ [NC]\n";
                $hta .= "RewriteCond %{DOCUMENT_ROOT}/$http_path/$wp_content_directory$cache_path$http_path/$1index_0.html -f\n";
                if ($php_intermediary) {
                    $hta .= "RewriteRule ^(.*) $serve_script [L,E=LWSOP_CACHE:HIT]\n\n";
                } else {
                    $hta .= "RewriteRule ^(.*) $wp_content_directory$cache_path$http_path/$1index_0.html [L,E=LWSOP_CACHE:HIT]\n\n";
                }

                $hta .= "</IfModule>\n\n";

                // mod_headers block is separate — Header directives must not live inside <IfModule mod_rewrite.c>
                $hta .= "<IfModule mod_headers.c>\n";
                $hta .= "FileETag None\nHeader unset ETag\n";
                // When PHP intermediary is active it sets these headers itself; skip to avoid duplicates.
                if (!$php_intermediary) {
                    $hta .= "Header set X-LWSOP-Cache \"HIT\" env=LWSOP_CACHE\n";
                    $hta .= "Header set Edge-Cache-Platform \"lwsoptimize\" env=LWSOP_CACHE\n";
                }
                $hta .= "</IfModule>\n\n";

                $hta = "#LWS OPTIMIZE - CACHING\n# Règles ajoutées par LWS Optimize\n# Rules added by LWS Optimize\n $hta#END LWS OPTIMIZE - CACHING\n";

                if (is_file($htaccess)) {
                    $hta .= file_get_contents($htaccess);
                }

                if (($f = fopen($htaccess, 'w+')) !== false) {
                    if (!fwrite($f, $hta)) {
                        fclose($f);
                        $this->lwsop_debug_log(wp_json_encode(array('code' => 'CANT_WRITE', 'data' => "LWSOptimize | Caching | .htaccess file is not writtable")));
                    } else {
                        fclose($f);
                    }
                } else {
                    $this->lwsop_debug_log(wp_json_encode(array('code' => 'CANT_OPEN', 'data' => "LWSOptimize | Caching | .htaccess file is not openable")));
                }
            }
        }
    }

    public function lws_optimize_basic_htaccess_conditions($http_host, $admin_users) {
        $hta = '';

        // No redirections for special query strings
        $hta .= "RewriteCond %{QUERY_STRING} !^((gclid|fbclid|y(ad|s)?clid|utm_(source|medium|campaign|content|term)=[^&]+)+)$ [NC]\n";

        // Do not cache pages with do_not_cache_lwsoptimize parameter
        $hta .= "RewriteCond %{QUERY_STRING} !do_not_cache_lwsoptimize [NC]\n";

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
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove GZIP rules using regex
        $pattern = '/#LWS OPTIMIZE - GZIP COMPRESSION[\s\S]*?#END LWS OPTIMIZE - GZIP COMPRESSION\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (!$this->lwsop_atomic_write($htaccess, $htaccess_content)) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Successfully removed GZIP rules from .htaccess' . PHP_EOL);
        }

        fclose($logger);
    }

    /**
     * Empirically checks whether Apache is actually applying output compression on
     * this vhost. The <IfModule mod_brotli.c>/<IfModule mod_deflate.c> guards written
     * by set_gzip_brotli_htaccess() silently no-op when those modules aren't loaded —
     * common on shared hosting — so this is the only reliable way to tell. The result
     * is cached in an option (read by lwsop_cache_serve.php's PHP-level fallback)
     * instead of being probed on every page load.
     *
     * @return bool|null true/false on a conclusive check, null if the probe itself failed.
     */
    public function lwsop_check_apache_compression_support()
    {
        $response = wp_remote_get(home_url('/'), [
            'timeout'   => 10,
            'sslverify' => true,
            'cookies'   => [],
            'headers'   => ['Accept-Encoding' => 'br, gzip'],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $supported = wp_remote_retrieve_header($response, 'content-encoding') !== '';
        update_option('lwsop_apache_compression_detected', $supported ? 'true' : 'false');

        return $supported;
    }

    /**
     * Copies lwsop_cache_serve.php to wp-content/cache/lwsoptimize/ so it lives
     * outside /plugins/, avoiding 403s from security plugins that block PHP
     * execution there. Returns the ABSPATH-relative path on success, or null if
     * the copy failed (caller then falls back to the /plugins/ path).
     */
    private function lwsop_deploy_serve_script() {
        $source   = LWS_OP_DIR . 'Classes/FileCache/lwsop_cache_serve.php';
        $dest_dir = WP_CONTENT_DIR . '/cache/lwsoptimize/';
        $dest     = $dest_dir . 'lwsop_cache_serve.php';

        if (!is_dir($dest_dir)) {
            @mkdir($dest_dir, 0755, true);
        }

        if (!file_exists($dest) || filemtime($source) > filemtime($dest)) {
            if (!@copy($source, $dest)) {
                return null;
            }
        }

        return ltrim(str_replace(ABSPATH, '', $dest), '/');
    }

    public function unset_cache_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove caching rules using regex
        $pattern = '/#LWS OPTIMIZE - CACHING[\s\S]*?#END LWS OPTIMIZE - CACHING\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (!$this->lwsop_atomic_write($htaccess, $htaccess_content)) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Successfully removed caching rules from .htaccess' . PHP_EOL);
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
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not change directory permissions for .htaccess' . PHP_EOL);
                    umask($old_umask);
                    fclose($logger);
                    return;
                }

                if (!touch($htaccess)) {
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not create .htaccess file' . PHP_EOL);
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
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not make .htaccess writable' . PHP_EOL);
                    fclose($logger);
                    return;
                }
            }

            // Read existing content
            $htaccess_content = file_get_contents($htaccess);
            if ($htaccess_content === false) {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not read .htaccess file' . PHP_EOL);
                fclose($logger);
                return;
            }

            // Remove expire header section using regex
            $pattern = '/#LWS OPTIMIZE - EXPIRE HEADER[\s\S]*?#END LWS OPTIMIZE - EXPIRE HEADER\n?/';
            $htaccess_content = preg_replace($pattern, '', $htaccess_content);

            // Skip if temporarily deactivated
            if (get_option('lws_optimize_deactivate_temporarily')) {
                if (!$this->lwsop_atomic_write($htaccess, $htaccess_content)) {
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Could not update .htaccess content' . PHP_EOL);
                }
                fclose($logger);
                return;
            }

            // Build new expire header rules
            $hta = '';
            $hta .= "<IfModule mod_expires.c>\n";
            $hta .= "ExpiresActive On\n";

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
            if (!$this->lwsop_atomic_write($htaccess, $new_content)) {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to write new .htaccess content' . PHP_EOL);
            } else {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Successfully updated Header rules in .htaccess' . PHP_EOL);
            }

            $this->lwsop_write_static_assets_cdn_htaccess($cdn_date);

        } catch (\Exception $e) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Error updating .htaccess: ' . $e->getMessage() . PHP_EOL);
        }

        fclose($logger);
    }

    /**
     * Combined/minified CSS/JS (wp-content/cache/lwsoptimize/cache-css|js/) only ever
     * inherited the generic mod_expires rule above, with no "public" Cache-Control and
     * no CDN-Cache-Control — the exact signal the LWS CDN uses to decide whether to
     * cache a response, which is why these assets stayed permanent MISSes. A dedicated
     * per-directory .htaccess is used instead of widening the root <FilesMatch> to
     * *.css/*.js, which would also catch unrelated theme/plugin assets outside this
     * plugin's own cache directories.
     */
    /**
     * The CDN-facing max-age (in seconds) matching the current 'filebased_cache.timer'
     * setting — same mapping used by lws_optimize_reset_header_htaccess() for the HTML
     * page cache, reused here so the cache-css/cache-js directory .htaccess (see
     * lwsop_write_static_assets_cdn_htaccess()) can be backfilled as soon as
     * LwsOptimizeCSSManager/LwsOptimizeJSManager create those directories, without
     * waiting for a settings change to trigger the main .htaccess rewrite.
     */
    public function lwsop_get_cache_cdn_date()
    {
        $optimize_options = get_option('lws_optimize_config_array', []);
        $timer = $optimize_options['filebased_cache']['timer'] ?? "lws_yearly";

        switch ($timer) {
            case 'lws_daily':
                return "86400";
            case 'lws_weekly':
                return "604800";
            case 'lws_monthly':
                return "2592000";
            case 'lws_thrice_monthly':
                return "7776000";
            case 'lws_biyearly':
                return "15552000";
            case 'lws_yearly':
                return "31104000";
            case 'lws_two_years':
                return "62208000";
            case 'lws_never':
                return "93312000";
            default:
                return "7776000";
        }
    }

    public function lwsop_write_static_assets_cdn_htaccess($cdn_date)
    {
        $hta = "#LWS OPTIMIZE - STATIC ASSETS CACHE\n"
             . "<IfModule mod_headers.c>\n"
             . "Header set Cache-Control \"public, max-age=$cdn_date\"\n"
             . "Header set CDN-Cache-Control \"public, maxage=$cdn_date\"\n"
             . "</IfModule>\n"
             . "#END LWS OPTIMIZE - STATIC ASSETS CACHE\n";

        foreach (['cache-css/', 'cache-js/'] as $subdir) {
            $directory = $this->lwsop_get_content_directory($subdir);
            if (!is_dir($directory)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- mirrors LwsOptimizeCSSManager/LwsOptimizeJSManager, which already create these same directories without WP_Filesystem
                mkdir($directory, 0755, true);
            }
            if (is_dir($directory)) {
                $this->lwsop_atomic_write($directory . '.htaccess', $hta);
            }
        }
    }

    function unset_header_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        $logger = fopen($this->log_file, 'a');

        // Check if .htaccess exists
        if (!file_exists($htaccess)) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] .htaccess file does not exist' . PHP_EOL);
            fclose($logger);
            return;
        }

        // Read htaccess content
        $htaccess_content = file_get_contents($htaccess);

        // Remove expire header section using regex
        $pattern = '/#LWS OPTIMIZE - EXPIRE HEADER[\s\S]*?#END LWS OPTIMIZE - EXPIRE HEADER\n?/';
        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

        // Write back to file
        if (!$this->lwsop_atomic_write($htaccess, $htaccess_content)) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Failed to update .htaccess file' . PHP_EOL);
        } else {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Successfully removed expire headers from .htaccess' . PHP_EOL);
        }

        fclose($logger);
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
        $request_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        wp_remote_post(
            "https://127.0.0.1:8443/api/domains/" . $request_host,
            array(
                'method'      => 'PUT',
                'headers'     => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => "application/x-www-form-urlencoded"),
                'body'          => array(
                    'template' => "default",
                    'cache-enabled' => $state,
                    'cache-engine' => 'varnish'
                ),
                'sslverify' => true
            )
        );
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
                @wp_delete_file("$dir/$file");
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
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Starting [FULL] cache clearing for action [$action]..." . PHP_EOL);

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
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }
            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
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
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Starting AutoPurge cache clearing for action [$action]... [$directory]" . PHP_EOL);

            // Get site URL components for main cache
            $site_url = site_url();
            $domain_parts = wp_parse_url($site_url);
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
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Directory for $directory not found. No cache to purge" . PHP_EOL);
            }

            if (is_dir($cache_mobile)) {
                // Add desktop and mobile specific cache directories
                $cache_dirs = array_merge([$cache_mobile => 'mobile specific'], $cache_dirs);
            } else {
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Directory for $directory not found. No cache to purge" . PHP_EOL);
            }

            // Clean each cache directory
            foreach ($cache_dirs as $dir => $type) {
                $files = glob($dir . '/index_*');
                if (!empty($files)) {
                    array_map('unlink', array_filter($files, 'is_file'));
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Removed cache files from $type cache ($dir)" . PHP_EOL);
                }
            }

            // Additionally clear cache for categories, tags and pagination
            $taxonomy_urls = $this->get_taxonomy_and_pagination_urls();
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Clearing cache for " . count($taxonomy_urls) . " taxonomy and pagination URLs" . PHP_EOL);

            foreach ($taxonomy_urls as $url) {
                $parsed_url = wp_parse_url($url);
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
                        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                    } elseif (!$next_scheduled) {
                        // Schedule new if none exists
                        wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
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
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }

            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
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
                        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                    } elseif (!$next_scheduled) {
                        // Schedule new if none exists
                        wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
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
                fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] WordPress object cache cleared" . PHP_EOL);
            }

            return json_encode(['code' => 'SUCCESS'], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL);
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
        fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Restarting preload process after cache purge..." . PHP_EOL);

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
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] Preload rescheduled (+5 min)" . PHP_EOL);
                } elseif (!$next_scheduled) {
                    // Schedule new if none exists
                    wp_schedule_event($current_time, "lws_minute", "lws_optimize_start_filebased_preload");
                    fwrite($logger, '[' . gmdate('Y-m-d H:i:s') . "] New preload scheduled" . PHP_EOL);
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

    /**
     * Check if the given $option is set. If it is active, return the data if it exists.
     * Example: {filebased_cache} => ["state" => "true", "data" => ["timer" => "lws_daily", ...]]
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
            $this->lwsop_debug_log("LwsOptimize.php::lwsop_check_option | " . $e);
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
     * Adapted from WPFastestCache for the plugin part and the idea of using RegEx.
     *
     * @param string $path Path from wp-content to the cache file; trailing slash not necessary.
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
                $additional = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            }

            if ($this->lwsop_plugin_active('polylang/polylang.php')) {
                $polylang_settings = get_option("polylang");
                if (isset($polylang_settings["force_lang"]) && ($polylang_settings["force_lang"] == 2 || $polylang_settings["force_lang"] == 3)) {
                    $additional = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
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
                            if ($file->isFile() && !preg_match('/\.(gz|br)$/i', $file->getFilename())) {
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
                $options['memcached']['state'] = $this->lwsop_can_recommend_memcached()['recommend'] ? "true" : "false";
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
                $options['htaccess_php_intermediary']['state'] = "false";
                $options['image_add_sizes']['state'] = "false";
                $options['remove_css']['state'] = "false";
                $options['critical_css']['state'] = "false";
                $options['cloudflare_apo']['state']     = "false";
                $options['rum']['state']                = "true";
                $options['font_preload']['state']       = "false";


                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
                wp_schedule_event(time() + YEAR_IN_SECONDS, 'lws_yearly', 'lws_optimize_clear_filebased_cache_cron');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                }

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time() + 60, "lws_minute", "lws_optimize_start_filebased_preload");
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
                $options['memcached']['state'] = $this->lwsop_can_recommend_memcached()['recommend'] ? "true" : "false";
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
                $options['htaccess_php_intermediary']['state'] = "false";
                $options['image_add_sizes']['state'] = "true";
                $options['remove_css']['state'] = "false";
                $options['critical_css']['state'] = "false";
                $options['cloudflare_apo']['state']     = "false"; // nécessite zone_id + api_token
                $options['rum']['state']                = "true";  // collecte CWV anonyme
                $options['font_preload']['state']       = "true";  // preload Google Fonts

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache_cron'), 'lws_optimize_clear_filebased_cache_cron');
                wp_schedule_event(time() + YEAR_IN_SECONDS, 'lws_yearly', 'lws_optimize_clear_filebased_cache_cron');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time() + 60, "lws_minute", "lws_optimize_start_filebased_preload");
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
                $options['memcached']['state'] = $this->lwsop_can_recommend_memcached()['recommend'] ? "true" : "false";
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
                $options['htaccess_php_intermediary']['state'] = "false";
                $options['image_add_sizes']['state'] = "true";
                $options['remove_css']['state'] = "true";
                $options['critical_css']['state'] = "true";
                $options['cloudflare_apo']['state']     = "false";
                $options['rum']['state']                = "true";
                $options['font_preload']['state']       = "true";

                update_option('lws_optimize_config_array', $options);

                wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
                wp_schedule_event(time() + (6 * MONTH_IN_SECONDS), 'lws_biyearly', 'lws_optimize_clear_filebased_cache_cron');
                wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');

                if (!$no_preloading) {
                    if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
                    }
                    wp_schedule_event(time() + 60, "lws_minute", "lws_optimize_start_filebased_preload");
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
     * Deprecated: kept only as a stub so old scheduled crons don't error.
     */
    public function lws_optimize_convert_media_cron()
    {
        wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
        wp_send_json(array('code' => "SUCCESS", "data" => [], 'domain' => site_url()));
    }

    /**
     * Remove the cron for the restoration of all converted medias, stopping the process. Deprecated.
     */
    public function lws_optimize_stop_deconvertion()
    {
        check_ajax_referer('lwsop_stop_deconvertion_nonce', '_ajax_nonce');
        wp_unschedule_event(wp_next_scheduled('lwsop_revertOptimization'), 'lwsop_revertOptimization');
        wp_send_json(array('code' => "SUCCESS", "data" => "Done", 'domain' => site_url()));
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
                wp_delete_file($file->getPathname());
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
            $timestamp = gmdate('Y-m-d-His');

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
                    @wp_delete_file($file);
                }
            }
        }

        // Create a new log file if it doesn't exist
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
            // Add header to the new log file
            $header = '[' . gmdate('Y-m-d H:i:s') . '] Log file created' . PHP_EOL;
            file_put_contents($this->log_file, $header);
        }
    }

    /**
     * Signature marker present in our own drop-in (views/object-cache.php).
     * Used to distinguish our drop-in from a third-party one (Redis Object Cache,
     * W3 Total Cache, WP Redis...) before any destructive operation.
     */
    const LWSOP_DROPIN_SIGNATURE = 'LWS_OPTIMIZE_OBJECT_CACHE_v1';

    /**
     * Markers known to be present in popular third-party object-cache.php drop-ins.
     * If we detect any of these, we MUST refuse to overwrite or delete.
     */
    const LWSOP_THIRD_PARTY_MARKERS = [
        'redis-cache',          // Redis Object Cache (rhubarbgroup)
        'WP_Object_Cache_Redis',
        'w3-total-cache',       // W3TC
        'w3tc',
        'wp-redis',             // wp-redis (humanmade)
        'memcachier',
        'object-cache.php (a)', // Pantheon
    ];

    /**
     * Returns true if the file at $path is OUR drop-in (contains our signature).
     * Returns false if the file does not exist OR is owned by a third party.
     */
    public function lwsop_is_owned_dropin($path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            return false;
        }
        $content = @file_get_contents($path, false, null, 0, 8192);
        if ($content === false) {
            return false;
        }
        return strpos($content, self::LWSOP_DROPIN_SIGNATURE) !== false;
    }

    /**
     * 4.5.11 — Détermine si on peut/doit recommander Memcached à l'utilisateur dans
     * les presets auto-setup (essential / optimized / max). Wrapper léger autour
     * de lwsop_validate_memcached_environment() : on ne recommande que si la
     * validation complète passe sans severity fatal.
     *
     * @return array{recommend:bool, reason:string, details:array}
     */
    public function lwsop_can_recommend_memcached()
    {
        $check = $this->lwsop_validate_memcached_environment();
        return [
            'recommend' => $check['ok'] && $check['severity'] !== 'fatal',
            'reason'    => $check['reason'],
            'details'   => $check['details'] ?? [],
        ];
    }

    /**
     * 4.5.12 — Valide complètement l'environnement avant activation du drop-in
     * Memcached ; bloque les configurations connues pour casser wp-admin
     * (extension PHP absente, serveur injoignable, drop-in tiers, saturation).
     * Voir les commentaires "Check A".."Check F" dans le corps de la fonction.
     *
     * Check C (conflit sessions PHP) est le root cause du crash wp-admin sur
     * top10hebergeursweb.com en 4.5.9 : session_start() + drop-in concurrent sur
     * le même backend Memcached sans PREFIX ni locking = race condition fatale.
     *
     * Hooks pour environnements custom : lwsop_memcached_host (def. 127.0.0.1),
     * lwsop_memcached_port (def. 11211).
     *
     * @return array{ok:bool, severity:'fatal'|'warning'|'info', reason:string, message:string, fix_url?:string, details?:array}
     */
    public function lwsop_validate_memcached_environment()
    {
        // -- Check A : extension PHP --------------------------------------
        if (!class_exists('Memcached')) {
            return [
                'ok'       => false,
                'severity' => 'fatal',
                'reason'   => 'php_memcached_extension_missing',
                'message'  => "L'extension PHP « memcached » n'est pas installée sur ce serveur. Contactez votre hébergeur pour l'activer.",
                'details'  => [],
            ];
        }

        $host = apply_filters('lwsop_memcached_host', '127.0.0.1');
        $port = (int) apply_filters('lwsop_memcached_port', 11211);

        // -- Check B : serveur joignable + R/W probe ----------------------
        $mc = new \Memcached();
        $mc->addServer($host, $port);
        $mc->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 200);
        $mc->setOption(\Memcached::OPT_POLL_TIMEOUT,    200);
        $mc->setOption(\Memcached::OPT_SEND_TIMEOUT,    200);
        $mc->setOption(\Memcached::OPT_RECV_TIMEOUT,    200);

        $probe_key   = 'lwsop_probe_' . uniqid('', true) . wp_rand(1000, 9999);
        $probe_value = (string) microtime(true);
        @$mc->set($probe_key, $probe_value, 30);
        $retrieved = @$mc->get($probe_key);
        $rc        = $mc->getResultCode();
        @$mc->delete($probe_key);

        if ($retrieved !== $probe_value) {
            return [
                'ok'       => false,
                'severity' => 'fatal',
                'reason'   => 'memcached_unreachable_or_broken',
                'message'  => sprintf(
                    /* translators: 1: host, 2: port, 3: Memcached result message, 4: Memcached result code */
                    __('Could not read/write to the Memcached server (%1$s:%2$d). Memcached code: %3$s (%4$d). Service down or network blocked?', 'lws-optimize'),
                    $host, $port, $mc->getResultMessage(), $rc
                ),
                'details'  => ['host' => $host, 'port' => $port, 'result_code' => $rc],
            ];
        }

        // -- Check C : conflit PHP sessions ⚠ ROOT CAUSE 4.5.9 ------------
        $session_handler = (string) ini_get('session.save_handler');
        $session_path    = (string) ini_get('session.save_path');

        if (in_array($session_handler, ['memcached', 'memcache'], true)) {
            $session_targets_same_memcached =
                (strpos($session_path, $host . ':' . $port) !== false) ||
                (strpos($session_path, 'localhost:' . $port) !== false) ||
                (strpos($session_path, '127.0.0.1:' . $port) !== false);

            if ($session_targets_same_memcached) {
                // Si PREFIX= présent, les namespaces sont isolés → pas de conflit
                $has_prefix = (bool) preg_match('/PREFIX=[^,]+/', $session_path);

                if (!$has_prefix) {
                    return [
                        'ok'       => false,
                        'severity' => 'fatal',
                        'reason'   => 'memcached_shared_with_php_sessions',
                        'message'  =>
                            __("Server conflict detected: PHP uses Memcached as the session backend on the same instance as LWS Optimize (session.save_handler = memcached, session.save_path shares the same address). Enabling the object cache would create a race condition between PHP sessions and the WP cache, which can cause HTTP 500 errors in wp-admin for logged-in users.\n\nSolutions (ask your host):\n  1. Switch PHP sessions to files: session.save_handler = files\n  2. Prefix the sessions namespace: session.save_path = \"PREFIX=php_sess.,127.0.0.1:11211\"\n  3. Enable locking: memcached.sess_locking = On (mitigation, not ideal)", 'lws-optimize'),
                        'fix_url'  => 'https://aide.lws.fr/base/Creation-de-site--Wordpress/Plugins-WordPress-LWS-Optimize',
                        'details'  => [
                            'session_handler' => $session_handler,
                            'session_path'    => $session_path,
                            'sess_locking'    => ini_get('memcached.sess_locking'),
                            'lazy_write'      => ini_get('session.lazy_write'),
                        ],
                    ];
                }
            }
        }

        // -- Check D : drop-in tiers --------------------------------------
        $third_party = $this->lwsop_detect_third_party_dropin(LWSOP_OBJECTCACHE_PATH);
        if ($third_party !== null) {
            return [
                'ok'       => false,
                'severity' => 'fatal',
                'reason'   => 'third_party_dropin_exists',
                'message'  => sprintf(
                    /* translators: %s: name of the other plugin that installed the object-cache.php drop-in */
                    __("An object-cache.php drop-in from another plugin (%s) is already installed. Uninstall it or deactivate Memcached in LWS Optimize before installing a new one, to avoid overwriting its configuration.", 'lws-optimize'),
                    $third_party
                ),
                'details'  => ['detected' => $third_party, 'path' => LWSOP_OBJECTCACHE_PATH],
            ];
        }

        // -- Check E : saturation (warning seulement) ---------------------
        $stats        = @$mc->getStats();
        $server_stats = is_array($stats) ? reset($stats) : [];
        if (!empty($server_stats)) {
            $limit    = (int) ($server_stats['limit_maxbytes'] ?? 0);
            $used     = (int) ($server_stats['bytes'] ?? 0);
            $evicted  = (int) ($server_stats['evictions'] ?? 0);
            $pct_used = $limit > 0 ? round($used * 100 / $limit, 1) : 0;

            if ($pct_used > 90) {
                return [
                    'ok'       => true,
                    'severity' => 'warning',
                    'reason'   => 'memcached_near_capacity',
                    'message'  => sprintf(
                        /* translators: 1: percentage used, 2: memory used, 3: memory limit, 4: number of evictions */
                        __("Memcached is using %1\$.1f%% of its memory (%2\$s of %3\$s). %4\$d evictions have already occurred. The cache will be frequently invalidated, limiting the performance benefit. Ask your host to increase limit_maxbytes (typically 256-512 MB).", 'lws-optimize'),
                        $pct_used, size_format($used), size_format($limit), $evicted
                    ),
                    'details'  => ['pct_used' => $pct_used, 'used' => $used, 'limit' => $limit, 'evictions' => $evicted],
                ];
            }
        }

        // -- Check F : tout OK --------------------------------------------
        return [
            'ok'       => true,
            'severity' => 'info',
            'reason'   => 'all_checks_passed',
            'message'  => __('Memcached environment validated.', 'lws-optimize'),
            'details'  => ['host' => $host, 'port' => $port],
        ];
    }

    /**
     * 4.5.12 — Cron daily : si Memcached est activé mais l'environnement est
     * devenu invalide (ex: hébergeur a modifié php.ini pour basculer les sessions
     * sur Memcached), on auto-désactive + retire le drop-in + email à l'admin
     * AVANT que la prochaine requête wp-admin authentifiée ne crashe.
     */
    public function lwsop_periodic_memcached_validation()
    {
        $opts = get_option('lws_optimize_config_array', []);
        if (($opts['memcached']['state'] ?? 'false') !== 'true') {
            return; // Memcached pas activé, rien à surveiller
        }

        $check = $this->lwsop_validate_memcached_environment();
        if ($check['ok'] || $check['severity'] !== 'fatal') {
            return; // env toujours OK (ou seulement warning)
        }

        // Auto-disable
        $opts['memcached']['state'] = 'false';
        update_option('lws_optimize_config_array', $opts);
        $this->lwsop_safe_delete_dropin(LWSOP_OBJECTCACHE_PATH);

        // Log
        if (!empty($this->log_file)) {
            @file_put_contents(
                $this->log_file,
                sprintf('[%s] Daily health-check auto-disabled Memcached — %s : %s' . PHP_EOL,
                    gmdate('Y-m-d H:i:s'), $check['reason'], substr($check['message'], 0, 300)),
                FILE_APPEND
            );
        }
        $this->lwsop_debug_log('LWS Optimize daily health-check: auto-disabled Memcached — ' . $check['reason']);

        // Notification admin
        $admin_email = get_option('admin_email');
        if (!empty($admin_email) && is_email($admin_email)) {
            // translators: %s: Site name.
            $subject = sprintf(__('[%s] LWS Optimize a désactivé Memcached automatiquement', 'lws-optimize'), get_bloginfo('name'));
            // translators: 1: Health-check error message, 2: Technical failure reason, 3: Site URL.
            $body = sprintf(
                __("L'environnement Memcached est devenu invalide :\n\n%1\$s\n\nLWS Optimize a désactivé Memcached et retiré le drop-in object-cache.php pour éviter un crash de wp-admin pour vos utilisateurs authentifiés. Vous pouvez le réactiver manuellement depuis l'interface du plugin une fois l'environnement corrigé.\n\nRaison technique : %2\$s\nSite : %3\$s", 'lws-optimize'),
                $check['message'],
                $check['reason'],
                home_url()
            );
            @wp_mail($admin_email, $subject, $body);
        }
    }

    public function lwsop_detect_third_party_dropin($path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }
        $content = @file_get_contents($path, false, null, 0, 8192);
        if ($content === false) {
            return null;
        }
        // Our own drop-in wins
        if (strpos($content, self::LWSOP_DROPIN_SIGNATURE) !== false) {
            return null;
        }
        // Legacy LWS drop-in
        if (strpos($content, 'Memcached Object Cache Drop-In') !== false
            && strpos($content, 'Place in wp-content/object-cache.php') !== false
            && strpos($content, 'global $memcached_instance') !== false) {
            return null;
        }
        foreach (self::LWSOP_THIRD_PARTY_MARKERS as $marker) {
            if (stripos($content, $marker) !== false) {
                return $marker;
            }
        }
        // Unknown drop-in (no LWS signature and no known marker) → still treat as third-party to be safe
        return 'unknown';
    }

    /**
     * Safely delete the object-cache.php drop-in ONLY if we own it.
     * Returns true on success (deleted or already absent), false if refused (third-party detected).
     */
    public function lwsop_safe_delete_dropin($path)
    {
        if (!file_exists($path)) {
            return true;
        }
        $third_party = $this->lwsop_detect_third_party_dropin($path);
        if ($third_party !== null) {
            $msg = sprintf(
                '[%s] LWS Optimize refused to delete %s — third-party drop-in detected: %s',
                gmdate('Y-m-d H:i:s'),
                $path,
                $third_party
            );
            if (!empty($this->log_file)) {
                @file_put_contents($this->log_file, $msg . PHP_EOL, FILE_APPEND);
            }
            $this->lwsop_debug_log($msg);
            return false;
        }
        return @wp_delete_file($path) !== false;
    }

    /**
     * Targeted Varnish / LiteSpeed / LWSCache purge for a single URL. Used by
     * integrations (Cloudflare APO, autopurge hooks) to keep edge caches in
     * sync after a content change; falls through silently if no compatible
     * edge cache is detected. Detection mirrors lwsop_dump_all_dynamic_caches().
     */
    public function lwsop_purge_varnish_url($url)
    {
        if (empty($url) || !is_string($url)) {
            return null;
        }
        $url = esc_url_raw($url);
        if (!$url) {
            return null;
        }
        // Varnish (LWS managed VPS, ISPConfig)
        if (isset($_SERVER['HTTP_X_CACHE_ENABLED'], $_SERVER['HTTP_EDGE_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1'
            && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] === 'varnish'
        ) {
            wp_remote_request($url, ['method' => 'PURGE', 'timeout' => 5]);
            return 'Varnish';
        }
        // LiteSpeed
        if (isset($_SERVER['HTTP_X_CACHE_ENABLED'], $_SERVER['HTTP_EDGE_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENABLED'] == '1'
            && $_SERVER['HTTP_EDGE_CACHE_ENGINE'] === 'litespeed'
        ) {
            wp_remote_request($url, ['method' => 'PURGE', 'timeout' => 5]);
            return 'LiteSpeed';
        }
        // LWSCache (mutualised hosting)
        if (isset($_ENV['lwscache']) && strtolower(sanitize_text_field(wp_unslash($_ENV['lwscache']))) === 'on') {
            wp_remote_request($url, ['method' => 'PURGE', 'timeout' => 5]);
            return 'LWSCache';
        }
        return null;
    }

    /**
     * Atomically writes content to a critical file (.htaccess, drop-in, config)
     * via write-to-tmp + rename. Avoids leaving a truncated file on I/O error,
     * which would otherwise cause a 500 (notably for .htaccess).
     */
    public function lwsop_atomic_write($path, $content)
    {
        $tmp = $path . '.tmp.' . uniqid('', true);
        $written = @file_put_contents($tmp, $content);
        if ($written === false || $written !== strlen($content)) {
            @wp_delete_file($tmp);
            return false;
        }
        // Preserve permissions of the existing file if any
        if (file_exists($path)) {
            $perms = @fileperms($path);
            if ($perms !== false) {
                @chmod($tmp, $perms & 0777);
            }
        }
        if (!@rename($tmp, $path)) {
            @wp_delete_file($tmp);
            return false;
        }
        return true;
    }

    /**
     * Safely write the LWS Optimize drop-in ONLY if no third-party drop-in is in place.
     * Uses atomic rename to avoid leaving a truncated file on I/O error.
     */
    public function lwsop_safe_write_dropin($path, $source_path)
    {
        $third_party = $this->lwsop_detect_third_party_dropin($path);
        if ($third_party !== null) {
            $msg = sprintf(
                '[%s] LWS Optimize refused to overwrite %s — third-party drop-in detected: %s',
                gmdate('Y-m-d H:i:s'),
                $path,
                $third_party
            );
            if (!empty($this->log_file)) {
                @file_put_contents($this->log_file, $msg . PHP_EOL, FILE_APPEND);
            }
            $this->lwsop_debug_log($msg);
            return false;
        }
        $content = @file_get_contents($source_path);
        if ($content === false) {
            return false;
        }

        $tmp = $path . '.tmp.' . uniqid('', true);
        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }
        if (!@rename($tmp, $path)) {
            @wp_delete_file($tmp);
            return false;
        }
        return true;
    }
    // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.WP.AlternativeFunctions.file_system_operations_touch,WordPress.WP.AlternativeFunctions.file_system_operations_is_writable,WordPress.WP.AlternativeFunctions.rename_rename,PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected,PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite
}

