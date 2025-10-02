<?php

namespace Lws\Classes\Admin;

use Lws\Classes\LwsOptimize;

class LwsOptimizeManageAdmin extends LwsOptimize
{
    public $version = "3.2.4.3";

    public function manage_options()
    {
        // Create the link to the options
        add_action('admin_menu', [$this, 'lws_optimize_addmenu']);
        // Add styles and scripts to the admin
        add_action('admin_enqueue_scripts', [$this, 'lws_optimize_add_styles']);
        // Add styles for the adminbar in front-end
        add_action('wp_enqueue_scripts', [$this, 'lws_optimize_add_styles_frontend']);
        // Add a "Settings" link in the extension page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'lws_optimize_add_settings_link']);
        // Verify if any plugins are incompatible with this one
        // Also allow to deactivate those plugins
        add_action('admin_init', [$this, 'lws_optimize_deactivate_on_conflict']);
        add_action("wp_ajax_lws_optimize_deactivate_incompatible_plugin", [$this, "lws_optimize_deactivate_plugins_incompatible"]);
        // Manage the state of the plugin
        add_action("wp_ajax_lws_optimize_manage_state", [$this, "lws_optimize_manage_state"]);

        // Remove all notices and popup while on the config page
        add_action('admin_notices', function () {
            if (substr(get_current_screen()->id, 0, 29) == "toplevel_page_lws-op-config") {
                remove_all_actions('admin_notices');
                if (get_option('lws_optimize_deactivate_temporarily') && (is_plugin_active('wp-rocket/wp-rocket.php') || is_plugin_active('powered-cache/powered-cache.php') || is_plugin_active('wp-super-cache/wp-cache.php')
                    || is_plugin_active('wp-optimize/wp-optimize.php') || is_plugin_active('wp-fastest-cache/wpFastestCache.php') || is_plugin_active('w3-total-cache/w3-total-cache.php'))) {
                    $this->lws_optimize_warning_incompatibiliy();
                }
            }
        }, 0);

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

            add_action("wp_ajax_lwsop_check_preload_update", [$this, "lwsop_check_preload_update"]);

            add_action("wp_ajax_lws_clear_fb_cache", [$this, "lws_optimize_clear_cache"]);
            add_action("wp_ajax_lws_op_clear_all_caches", [$this, "lws_op_clear_all_caches"]);
            add_action("wp_ajax_lws_clear_opcache", [$this, "lws_clear_opcache"]);
            add_action("wp_ajax_lws_clear_html_fb_cache", [$this, "lws_optimize_clear_htmlcache"]);
            add_action("wp_ajax_lws_clear_style_fb_cache", [$this, "lws_optimize_clear_stylecache"]);
            add_action("wp_ajax_lws_clear_currentpage_fb_cache", [$this, "lws_optimize_clear_currentcache"]);


            add_action("wp_ajax_lws_optimize_fb_cache_change_status", [$this, "lws_optimize_set_fb_status"]);
            add_action("wp_ajax_lws_optimize_fb_cache_change_cache_time", [$this, "lws_optimize_set_fb_timer"]);

            add_action("wp_ajax_lwsop_regenerate_logs", [$this, "lwsop_regenerate_logs"]);

            add_action("wp_ajax_lwsOp_sendFeedbackUser", [$this, "lwsOp_sendFeedbackUser"]);
        }

        add_action("wp_ajax_lwsop_deactivate_temporarily", [$this, "lwsop_deactivate_temporarily"]);
        add_action("wp_ajax_lws_optimize_do_pagespeed", [$this, "lwsop_do_pagespeed_test"]);
        add_action("wp_ajax_lwsop_dump_dynamic_cache", [$this, "lwsop_dump_dynamic_cache"]);
        add_action("wp_ajax_lws_optimize_activate_cleaner", [$this, "lws_optimize_activate_cleaner"]);

        // Launch the weekly DB cleanup
        add_action("lws_optimize_maintenance_db_weekly", [$this, "lws_optimize_create_maintenance_db_options"]);
        add_action("wp_ajax_lws_optimize_set_maintenance_db_options", [$this, "lws_optimize_set_maintenance_db_options"]);
        add_action("wp_ajax_lws_optimize_get_maintenance_db_options", [$this, "lws_optimize_manage_maintenance_get"]);

        add_action('wp_ajax_lwsop_change_optimize_configuration', [$this, "lwsop_get_setup_optimize"]);

        // Add the LwsOptimize button on the admin-bar
        if (!function_exists("is_user_logged_in")) {
            include_once ABSPATH . "/wp-includes/pluggable.php";
        }
        if (is_admin_bar_showing()) {
            add_action('admin_bar_menu', [$this, 'lws_optimize_admin_bar'], 300);
            add_action('admin_footer', [$this, 'lws_optimize_adminbar_scripts'], 300);
            add_action('wp_footer', [$this, 'lws_optimize_adminbar_scripts'], 300);
        }
    }


    // Add a link in the menu of the WPAdmin to access LwsOptimize
    public function lws_optimize_addmenu()
    {
        add_menu_page(
            __('LWS Optimize', 'lws-optimize'),
            __('LWS Optimize', 'lws-optimize'),
            'manage_options',
            'lws-op-config',
            [$this, 'lws_optimize_options_page'],
            LWS_OP_URL . 'images/plugin_lws_optimize.svg'
        );

        add_submenu_page(
            '',
            __('Advanced Settings', 'lws-optimize'),
            __('Advanced Settings', 'lws-optimize'),
            'manage_options',
            'lws-op-config-advanced',
            [$this, 'lws_optimize_options_page']
        );
    }

    // Create the options page of LWSOptimize
    public function lws_optimize_options_page()
    {
        include_once LWS_OP_DIR . '/views/feedback_button.php';

        // Only load this file, everything else will be loaded within tabs.php
        include_once LWS_OP_DIR . '/views/main_page.php';
    }

    // Add every JS and CSS for the admin
    public function lws_optimize_add_styles()
    {
        // Everywhere on the WPAdmin
        wp_enqueue_style('lws_optimize_adminbar', LWS_OP_URL . "css/lws_op_stylesheet_adminbar.css");

        // On the LwsOptimize option page
        if (get_current_screen()->base == ('toplevel_page_lws-op-config') || get_current_screen()->base == ('admin_page_lws-op-config-advanced')) {
            wp_enqueue_style('lws_optimize_options_css', LWS_OP_URL . "css/lws_op_stylesheet.css");
            wp_enqueue_style('lws_optimize_Poppins_font', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
            wp_enqueue_style("lws_optimize_bootstrap_css", LWS_OP_URL . 'css/bootstrap.min.css?v=' . $this->version);
            wp_enqueue_script("lws_optimize_bootstrap_js", LWS_OP_URL . 'js/bootstrap.min.js?v=' . $this->version, array('jquery'));
            // DataTable assets
            wp_enqueue_style("lws_optimize_datatable_css", LWS_OP_URL . "css/jquery.dataTables.min.css");
            wp_enqueue_script("lws_optimize_datatable_js", LWS_OP_URL . "/js/jquery.dataTables.min.js", array('jquery'));
            wp_enqueue_script("lws_optimize_popper", "https://unpkg.com/@popperjs/core@2");
        }
    }

    // Add AdminBar CSS for the front-end
    public function lws_optimize_add_styles_frontend()
    {
        if (current_user_can('editor') || current_user_can('administrator')) {
            wp_enqueue_style('lws_optimize_adminbar', LWS_OP_URL . "css/lws_op_stylesheet_adminbar.css");
        }
    }


    // Add the "LWSOptimize" adminbar when connected
    public function lws_optimize_admin_bar(\WP_Admin_Bar $Wp_Admin_Bar)
    {

        if (current_user_can('manage_options')) {
            $Wp_Admin_Bar->add_menu(
                [
                    'id' => "lws_optimize_managecache",
                    'parent' => null,
                    'href' => esc_url(admin_url('admin.php?page=lws-op-config')),
                    'title' => '<span class="lws_optimize_admin_icon">' . __('LWS Optimize', 'lws-optimize') . '</span>',
                ]
            );
            $Wp_Admin_Bar->add_menu(
                [
                    'id' => "lws_optimize_clearcache",
                    'parent' => "lws_optimize_managecache",
                    'title' => __('Clear all cache', 'lws-optimize')
                ]
            );
            $Wp_Admin_Bar->add_menu(
                [
                    'id' => "lws_optimize_clearopcache",
                    'parent' => "lws_optimize_managecache",
                    'title' => __('Clear OPcache', 'lws-optimize')
                ]
            );
            if (!is_admin()) {
                $Wp_Admin_Bar->add_menu(
                    [
                        'id' => "lws_optimize_clearcache_page",
                        'parent' => "lws_optimize_managecache",
                        'title' => __('Clear current page cache files', 'lws-optimize')
                    ]
                );
            }
        }
    }

    // Add the scripts to make the adminbar work
    public function lws_optimize_adminbar_scripts()
    { ?>
        <script>
            document.addEventListener('click', function(event) {
                let target = event.target;

                if (target.closest('#wp-admin-bar-lws_optimize_clearcache')) {
                    document.body.insertAdjacentHTML('afterbegin', "<div id='lws_optimize_temp_black' style='position: fixed; width: 100%; height: 100%; background: #000000a3; z-index: 100000';></div>");
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 60000,
                        context: document.body,
                        data: {
                            action: "lws_clear_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
                        },
                        success: function(data) {
                            window.location.reload();
                        },
                        error: function(error) {
                            window.location.reload();
                        }
                    });
                } else if (target.closest('#wp-admin-bar-lws_optimize_clearopcache')) {
                    document.body.insertAdjacentHTML('afterbegin', "<div id='lws_optimize_temp_black' style='position: fixed; width: 100%; height: 100%; background: #000000a3; z-index: 100000';></div>");
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 60000,
                        context: document.body,
                        data: {
                            action: "lws_clear_opcache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_opcache_caching')); ?>'
                        },
                        success: function(data) {
                            window.location.reload();
                        },
                        error: function(error) {
                            window.location.reload();
                        }
                    });
                } else if (target.closest('#wp-admin-bar-lws_optimize_clearcache_html')) {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 60000,
                        context: document.body,
                        data: {
                            action: "lws_clear_html_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_html_fb_caching')); ?>'
                        },
                        success: function(data) {
                            window.location.reload();
                        },
                        error: function(error) {
                            window.location.reload();
                        }
                    });
                } else if (target.closest('#wp-admin-bar-lws_optimize_clearcache_jscss')) {
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 60000,
                        context: document.body,
                        data: {
                            action: "lws_clear_style_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_style_fb_caching')); ?>'
                        },
                        success: function(data) {
                            window.location.reload();
                        },
                        error: function(error) {
                            window.location.reload();
                        }
                    });
                } else if (target.closest('#wp-admin-bar-lws_optimize_clearcache_page')) {
                    document.body.insertAdjacentHTML('afterbegin', "<div id='lws_optimize_temp_black' style='position: absolute; width: 100%; height: 100%; background: #000000a3; z-index: 100000';></div>");
                    jQuery.ajax({
                        url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                        type: "POST",
                        dataType: 'json',
                        timeout: 60000,
                        context: document.body,
                        data: {
                            action: "lws_clear_currentpage_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_currentpage_fb_caching')); ?>',
                            request_uri: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'
                        },
                        success: function(data) {
                            window.location.reload();
                        },
                        error: function(error) {
                            window.location.reload();
                        }
                    });
                }
            });
        </script>
    <?php
    }

    // Add a "Settings" link in the array of plugins in plugin.php
    public function lws_optimize_add_settings_link($actions)
    {
        return array_merge(array('<a href="' . admin_url('admin.php?page=lws-op-config') . '">' . __('Settings') . '</a>'), $actions);
    }

    // Show a popup when a plugin is incompatible
    public function lws_optimize_warning_incompatibiliy()
    {
    ?>
        <div class="notice notice-warning is-dismissible" style="padding-bottom: 10px;">
            <p><?php _e('You already have another cache plugin installed on your website. LWS-Optimize will be inactive as long as those below are not deactivated to prevent incompatibilities: ', 'lws-optimize'); ?></p>
            <?php if (is_plugin_active('wp-rocket/wp-rocket.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">WPRocket <a class="wp-core-ui button" id="lwsop_deactivate_button_wprocket" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
            <?php if (is_plugin_active('powered-cache/powered-cache.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">PoweredCache <a class="wp-core-ui button" id="lwsop_deactivate_button_pc" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
            <?php if (is_plugin_active('wp-super-cache/wp-cache.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">WP Super Cache <a class="wp-core-ui button" id="lwsop_deactivate_button_wpsc" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
            <?php if (is_plugin_active('wp-optimize/wp-optimize.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">WP-Optimize <a class="wp-core-ui button" id="lwsop_deactivate_button_wpo" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
            <?php if (is_plugin_active('wp-fastest-cache/wpFastestCache.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">WP Fastest Cache <a class="wp-core-ui button" id="lwsop_deactivate_button_wpfc" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
            <?php if (is_plugin_active('w3-total-cache/w3-total-cache.php')) : ?>
                <div style="display: flex; align-items: center; gap: 8px; line-height: 35px;">W3 Total Cache <a class="wp-core-ui button" id="lwsop_deactivate_button_wp3" style="display: flex; align-items: center; width: fit-content;"><?php _e('Deactivate', 'lws-optimize'); ?></a></div>
            <?php endif ?>
        </div>

        <script>
            document.querySelectorAll('a[id^="lwsop_deactivate_button_"]').forEach(function(element) {
                element.addEventListener('click', function(event) {
                    let id = (element.id).replace('lwsop_deactivate_button_', '');
                    this.parentNode.parentNode.style.pointerEvents = "none";
                    this.innerHTML = `<img src="<?php echo LWS_OP_URL; ?>/images/loading_black.svg" width="20px">`;
                    var data = {
                        _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('deactivate_incompatible_plugins_nonce')); ?>',
                        action: "lws_optimize_deactivate_incompatible_plugin",
                        data: {
                            id: id
                        },
                    };
                    jQuery.post(ajaxurl, data, function(response) {
                        location.reload();
                    });
                });
            });
        </script>
<?php
    }

    // If a plugin is incompatible, deactivate the plugin
    public function lws_optimize_deactivate_on_conflict()
    {
        if (
            is_plugin_active('wp-rocket/wp-rocket.php') ||
            is_plugin_active('powered-cache/powered-cache.php') ||
            is_plugin_active('wp-super-cache/wp-cache.php') ||
            is_plugin_active('wp-optimize/wp-optimize.php') ||
            is_plugin_active('wp-fastest-cache/wpFastestCache.php') ||
            is_plugin_active('w3-total-cache/w3-total-cache.php')
        ) {
            add_option('lws_optimize_deactivate_temporarily', true, time() + 86400);
            $this->lws_optimize_set_cache_htaccess();
            $this->lws_optimize_reset_header_htaccess();
            $this->lwsop_dump_all_dynamic_caches();
            add_action('admin_notices', [$this, 'lws_optimize_warning_incompatibiliy']);
        }
    }

    // Deactivate the incompatible plugin
    public function lws_optimize_deactivate_plugins_incompatible()
    {
        check_ajax_referer('deactivate_incompatible_plugins_nonce', '_ajax_nonce');
        if (isset($_POST['action']) && isset($_POST['data'])) {
            switch (htmlspecialchars($_POST['data']['id'])) {
                case 'wprocket':
                    deactivate_plugins('wp-rocket/wp-rocket.php');
                    break;
                case 'pc':
                    deactivate_plugins('powered-cache/powered-cache.php');
                    break;
                case 'wpsc':
                    deactivate_plugins('wp-super-cache/wp-cache.php');
                    break;
                case 'wpo':
                    deactivate_plugins('wp-optimize/wp-optimize.php');
                    break;
                case 'wpfc':
                    deactivate_plugins('wp-fastest-cache/wpFastestCache.php');
                    break;
                case 'wp3':
                    deactivate_plugins('w3-total-cache/w3-total-cache.php');
                    break;
                default:
                    break;
            }
        }
    }

    // Activate or deactivate the plugin
    public function lws_optimize_manage_state()
    {
        check_ajax_referer('nonce_lws_optimize_activate_config', '_ajax_nonce');
        $result = delete_option('lws_optimize_offline');

        // if (!isset($_POST['action']) || !isset($_POST['checked'])) {
        //     wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        // }

        // $state = sanitize_text_field($_POST['checked']);
        // if ($state == "true") {
        //     $result = delete_option('lws_optimize_offline');
        // } else {
        //     $result = update_option('lws_optimize_offline', "ON");
        // }

        // // Remove Dynamic Cache at the same time
        // $this->lws_optimize_set_cache_htaccess();
        // $this->lws_optimize_reset_header_htaccess();
        // $this->lwsop_dump_all_dynamic_caches();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $result)), JSON_PRETTY_PRINT);
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

        // If correctly added and updated
        wp_die(json_encode(array('code' => "SUCCESS", "data" => $optimize_options, 'errors' => $errors), JSON_PRETTY_PRINT));
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

        wp_die(json_encode(['code' => "SUCCESS", 'data' => $optimize_options['filebased_cache']]), JSON_PRETTY_PRINT);
    }

    public function lwsop_change_preload_amount()
    {
        check_ajax_referer('update_fb_preload_amount', '_ajax_nonce');

        if (isset($_POST['action'])) {
            $optimize_options = get_option('lws_optimize_config_array', []);

            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;
            $optimize_options['filebased_cache']['preload_amount'] =  $amount;

            update_option('lws_optimize_config_array', $optimize_options);

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
                'image_file' => esc_url(LWS_OP_URL . 'images/ordinateur.svg', __DIR__),
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
                'image_file' => esc_url(LWS_OP_URL . 'images/mobile.svg', __DIR__),
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
                'image_file' => esc_url(LWS_OP_URL . 'images/css.svg', __DIR__),
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
                'image_file' => esc_url(LWS_OP_URL . 'images/js.svg', __DIR__),
                'image_alt' => "js logo in a window icon",
                'width' => "60px",
                'height' => "60px",

            ],
        ];

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $caches)));

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

            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
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
            wp_die(json_encode(array('code' => "SUCCESS", "data" => $urls)), JSON_PRETTY_PRINT);
        }
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
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

        if ($data['quantity'] === null || $data['done'] === null || $data['ongoing'] === null || $data['next'] === null) {
            wp_die(json_encode(array('code' => "ERROR", "data" => $data, 'message' => "Failed to get some of the datas", 'domain' => site_url())), JSON_PRETTY_PRINT);
        }


        wp_die(json_encode(array('code' => "SUCCESS", "data" => $data, 'domain' => site_url())), JSON_PRETTY_PRINT);
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

        $this->lws_optimize_delete_directory(LWS_OP_UPLOADS, $GLOBALS['lws_optimize']);
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

        wp_die(json_encode(['code' => "SUCCESS", 'data' => $state]), JSON_PRETTY_PRINT);
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

    public function lwsop_dump_dynamic_cache()
    {
        check_ajax_referer('lwsop_empty_d_cache_nonce', '_ajax_nonce');
        wp_die($this->lwsop_dump_all_dynamic_caches());
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



    public function lwsop_regenerate_logs()
    {
        check_ajax_referer('lws_regenerate_nonce_logs', '_ajax_nonce');

        $dir = wp_upload_dir();
        $file = $dir['basedir'] . '/lwsoptimize/debug.log';
        if (empty($file)) {
            $content = __('No log file found.', 'lws-optimize');
        } else {
            $content = esc_html(implode("\n", array_reverse(file($file, FILE_IGNORE_NEW_LINES))));
        }

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $content)), JSON_PRETTY_PRINT);
    }

    public function lwsOp_sendFeedbackUser() {
        check_ajax_referer('lwsOP_sendFeedbackUser', '_ajax_nonce');

        if (!isset($_POST['form']) || empty($_POST['form'])) {
            echo json_encode(['code' => 'ERROR_FORM', 'data' => 'No feedback data provided']);
            exit;
        }

        $formData = $_POST['form'] ?? [];

        $type = $formData['type'] ?? 'suggestion';
        $name = $formData['name'] ?? 'Anonymous';
        $email = $formData['email'] ?? '';
        $feedback = $formData['feedback'] ?? '';
        $timestamp = $formData['timestamp'] ?? date('c');
        $page = $formData['page'] ?? '';

        empty($name) ? $name = 'Anonyme' : $name = htmlspecialchars(trim($name));
        empty($email) ? $email = 'Non renseigné' : $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);

        switch ($type) {
            case 'bug':
                $type = 'Bug / Problème';
                break;
            case 'improvement':
                $type = 'Amélioration';
                break;
            case 'other':
                $type = 'Autre';
                break;
            case 'suggestion':
            default:
                $type = 'Suggestion';
                break;
        }

        $subject = "[Feedback LWSOptimize] $type";

        $html_content = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007cba; color: white; padding: 15px; border-radius: 5px; }
                .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-top: 10px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #007cba; }
                .value { margin-top: 5px; }
                .feedback-text { background-color: white; padding: 15px; border-left: 4px solid #007cba; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nouveau feedback Auto-Installeur</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>Type de feedback :</div>
                        <div class='value'>$type</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Nom :</div>
                        <div class='value'>$name</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Email :</div>
                        <div class='value'>$email</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Domaine : </div>
                        <div class='value'>{$_SERVER['MD_MASTER']}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Page :</div>
                        <div class='value'>$page</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Date/Heure :</div>
                        <div class='value'>$timestamp</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Message :</div>
                        <div class='feedback-text'>$feedback</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";

        if (mail('lwsoptimize@lws.fr', $subject, $html_content, $headers)) {
            echo json_encode(['code' => 'SUCCESS', 'data' => "Mail was sent successfully"]);
            exit;
        } else {
            echo json_encode(['code' => 'ERROR', 'data' => 'Failed to send mail']);
            exit;
        }
    }
}
