<?php

namespace Lws\Classes\Admin;

class LwsOptimizeManageAdmin
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
            null,
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
            $GLOBALS['lws_optimize']->lws_optimize_set_cache_htaccess();
            $GLOBALS['lws_optimize']->lws_optimize_reset_header_htaccess();
            $GLOBALS['lws_optimize']->lwsop_dump_all_dynamic_caches();
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
        // $GLOBALS['lws_optimize']->lws_optimize_set_cache_htaccess();
        // $GLOBALS['lws_optimize']->lws_optimize_reset_header_htaccess();
        // $GLOBALS['lws_optimize']->lwsop_dump_all_dynamic_caches();

        wp_die(json_encode(array('code' => "SUCCESS", "data" => $result)), JSON_PRETTY_PRINT);
    }
}
