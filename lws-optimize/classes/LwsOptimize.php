<?php

// TODO : 
/*
boutons choix mode de cache (diff options activées)
boutons actualiser stats cache + nb pages en cache totale
*/
class LwsOptimize
{
    public $optimize_options;
    public $lwsOptimizeCache;
    public $state;

    public function __construct()
    {
        // Get all the options for LWSOptimize. If none are found (first start, erased from DB), recreate the array with only the cache activated
        $this->optimize_options = get_option('lws_optimize_config_array', NULL);
        if ($this->optimize_options === NULL) {
            $this->optimize_options = [
                'filebased_cache' => ['state' => "true"]
            ];
            update_option('lws_optimize_config_array', $this->optimize_options);
        }

        // If Memcached is activated but there is no object-cache.php, add it back
        if ($this->lwsop_check_option('memcached')['state'] === "true") {
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
                    if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                        unlink(WP_CONTENT_DIR . '/object-cache.php');
                    }
                    // NO MEMCACHED
                } else {
                    if (!file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                        file_put_contents(WP_CONTENT_DIR . '/object-cache.php', file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
                    }
                }
            } else {
                $this->optimize_options['memcached']['state'] = "false";
                if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                    var_dump("no_class");
                    unlink(WP_CONTENT_DIR . '/object-cache.php');
                }
                // NO MEMCACHED
            }
        }
    } else {
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            unlink(WP_CONTENT_DIR . '/object-cache.php');
        }
    }

        // Whether the plugin should work or not. While deactivated, only PageSpeed works
        $this->state = get_option('lws_optimize_offline', false);

        if (!$this->state) {
            add_action('admin_bar_menu', [$this, 'lws_optimize_admin_bar'], 300);
            add_action('admin_footer', [$this, 'lws_optimize_admin_footer_scripts'], 300);

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });
            add_action('wp_footer', [$this, 'lws_optimize_wp_footer_scripts'], 300);

            add_filter('lws_optimize_clear_filebased_cache', [$this, 'lws_optimize_clean_filebased_cache']);
            add_action('lws_optimize_start_filebased_preload', [$this, 'lws_optimize_start_filebased_preload']);

            // Fetch an array containing every URLs that should get purged each time an autopurge starts
            add_action("wp_ajax_lwsop_get_specified_url", [$this, "lwsop_specified_urls_fb"]);
            // Update the specified-URLs array
            add_action("wp_ajax_lwsop_save_specified_url", [$this, "lwsop_save_specified_urls_fb"]);
            // Fetch an array containing every URLs that should not be cached
            add_action("wp_ajax_lwsop_get_excluded_url", [$this, "lwsop_exclude_urls_fb"]);
            // Update the excluded-URLs array
            add_action("wp_ajax_lwsop_save_excluded_url", [$this, "lwsop_save_urls_fb"]);

            add_action("wp_ajax_lws_optimize_add_url_to_preload", [$this, "lwsop_get_url_preload"]);
            add_action("wp_ajax_lws_optimize_set_url_to_preload", [$this, "lwsop_set_url_preload"]);

            add_action("wp_ajax_lws_optimize_add_font_to_preload", [$this, "lwsop_get_url_preload_font"]);
            add_action("wp_ajax_lws_optimize_set_url_to_preload_font", [$this, "lwsop_set_url_preload_font"]);

            add_action("wp_ajax_lwsop_reload_stats", [$this, "lwsop_reload_stats"]);

            if ($this->lwsop_check_option("maintenance_db")['state'] == "true") {
                if (!wp_next_scheduled('lws_optimize_maintenance_db_weekly')) {
                    wp_schedule_event(time(), 'weekly', 'lws_optimize_maintenance_db_weekly');
                }
            }

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
            // add_action('woocommerce_cart_contents_changed', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
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

        if ( ! function_exists( 'wp_crop_image' ) ) {
            include( ABSPATH . 'wp-admin/includes/image.php' );
        }

        // TODO
        // Force remove compression/webp if were in use
        // if (get_option('lws_op_autoconvert_compression')) {
        //     delete_option('lws_op_autoconvert_compression');
        //     lws_op_uncompress_all_to_jpeg();
        // }

        // if (get_option('lws_op_autoconvert_webp')) {
        //     delete_option('lws_op_autoconvert_webp');
        //     lws_op_delete_webp_files();
        // }

        // if (get_option('lws_op_deactivated')) {
        //     delete_option('lws_op_deactivated');
        // }

        // Schedule the cache cleanout again if it has been deleted
        // If the plugin is OFF or the filecached is deactivated, unregister the WPCron
        if (isset($this->optimize_options['filebased_cache']['timer']) && !$this->state) {
            if (!wp_next_scheduled('lws_optimize_clear_filebased_cache')) {
                if ($this->optimize_options['filebased_cache']['timer'] != 0) {
                    wp_schedule_event(time(), $this->optimize_options['filebased_cache']['timer'], 'lws_optimize_clear_filebased_cache');
                }
            }
        } else if ($this->state || $this->lwsop_check_option('filebased_cache')['state'] === "false") {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_clear_filebased_cache'), 'lws_optimize_clear_filebased_cache');
        }
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
            var adminajaxurl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            if (document.getElementById("wp-admin-bar-lwsop-clear-cache") != null) {
                document.getElementById("wp-admin-bar-lwsop-clear-cache").addEventListener('click', function() {
                    jQuery.ajax({
                        url: adminajaxurl,
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
                        url: adminajaxurl,
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
                        url: adminajaxurl,
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
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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
                        url: adminajaxurl,
                        dataType: 'json',
                        type: "POST",
                        timeout: 120000,
                        context: document.body,
                        data: {
                            action: "lws_clear_currentpage_fb_cache",
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_currentpage_fb_caching')); ?>',
                            request_uri: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'
                        },
                        success: function(data) {
                            switch (data['code']) {
                                case 'SUCCESS':
                                    alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
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

        $id = sanitize_text_field($_POST['data']['type']);

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

        $id = sanitize_text_field($_POST['data']['type']);

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
        $stats = $this->lwsop_recalculate_stats();
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $stats)));
    }

    public function purge_specified_url()
    {
        $specified = $this->optimize_options['filebased_cache']['specified'] ?? [];
        foreach ($specified as $url) {
            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($url);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            lwsop_clear_cache_cloudflare("purge", array($url));
        }
    }

    function lwsop_preload_fb()
    {
        check_ajax_referer('update_fb_preload', '_ajax_nonce');

        if (isset($_POST['action']) && isset($_POST['state'])) {
            $amount = $_POST['amount'] ? sanitize_text_field($_POST['amount']) : 3;

            $old = $this->optimize_options;
            $this->optimize_options['filebased_cache']['preload'] = sanitize_text_field($_POST['state']);
            $this->optimize_options['filebased_cache']['preload_amount'] =  $amount;
            if ($old === $this->optimize_options) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => "DONE")));
            }

            $return = update_option('lws_optimize_config_array', $this->optimize_options);
            if ($return) {
                if (sanitize_text_field($_POST['state'] == "true")) {
                    if (!wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                        wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                    } else {
                        wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                        wp_schedule_event(time(), "lws_minute", "lws_optimize_start_filebased_preload");
                    }
                } else {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                }
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => "DONE")));
            } else {
                wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
            }
        }
        wp_die(json_encode(array('code' => "FAILED_ACTIVATE", 'data' => "FAIL")));
    }

    function lwsop_change_preload_amount()
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

    function lwsop_do_pagespeed_test()
    {
        check_ajax_referer('lwsop_doing_pagespeed_nonce', '_ajax_nonce');
        $url = $_POST['url'] ?? NULL;
        $type = $_POST['type'] ?? NULL;
        $date = time();


        if ($url === NULL || $type === NULL) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $config_array = get_option('lws_optimize_pagespeed_history', array());
        $last_test = array_reverse($config_array)[0]['date'] ?? 0;


        if ($last_test = strtotime($last_test)) {
            if (time() - $last_test < 180) {
                wp_die(json_encode(array('code' => "TOO_RECENT", 'data' => 180 - ($date - $last_test)), JSON_PRETTY_PRINT));
            }
        }



        $url = esc_url($url);
        $type = sanitize_text_field($type);
        $key = "AIzaSyD8yyUZIGg3pGYgFOzJR1NsVztAf8dQUFQ";

        $response = wp_remote_get("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$url&key=$key&strategy=$type", ['timeout' => 45, 'sslverify' => false]);
        if (is_wp_error($response)) {
            wp_die(json_encode(array('code' => "ERROR_PAGESPEED", 'data' => $response), JSON_PRETTY_PRINT));
        }

        $response = json_decode($response['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
        }

        $performance = $response['lighthouseResult']['categories']['performance']['score'] ?? NULL;
        $speedMetric = $response['lighthouseResult']['audits']['speed-index']['displayValue'] ?? NULL;
        $speedMetricValue = $response['lighthouseResult']['audits']['speed-index']['numericValue'] ?? NULL;
        $speedMetricUnit = $response['lighthouseResult']['audits']['speed-index']['numericUnit'] ?? NULL;


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
            if (empty($GLOBALS['lws_optimize_cache_timestamps']) || array_key_first($GLOBALS['lws_optimize_cache_timestamps']) === NULL) {
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
            $is_mutu = false;
            $is_cpanel = false;

            $fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLE'] ?? NULL;
            $lwscache_status = $_SERVER['lwscache'] ?? NULL;

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

            $lwscache_locked = false;

            if ($lwscache_status === NULL && $fastest_cache_status === NULL) {
                $lwscache_locked = true;
                wp_die(json_encode(array('code' => "INCOMPATIBLE", "data" => "LWSCache is incompatible with this hosting. Use LWS.")), JSON_PRETTY_PRINT);
            } else {
                if ($lwscache_status === NULL) {
                    $is_cpanel = true;
                } elseif ($fastest_cache_status === NULL) {
                    $is_mutu = true;
                }
            }

            if ($lwscache_status == false && $fastest_cache_status === NULL) {
                wp_die(json_encode(array('code' => "PANEL_CACHE_OFF", "data" => "LWSCache is not activated on LWSPanel.")), JSON_PRETTY_PRINT);
            } else if ($fastest_cache_status == false && $lwscache_status === NULL) {
                wp_die(json_encode(array('code' => "CPANEL_CACHE_OFF", "data" => "LWSCache is not activated on cPanel.")), JSON_PRETTY_PRINT);
            }

            // if (isset($this->optimize_options['filebased_cache']) && isset($this->optimize_options['filebased_cache']['specified'])) {
            //     $specified = count($this->optimize_options['filebased_cache']['specified']);
            // } else {
            //     $specified = "0";
            // }
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
                    if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                        unlink(WP_CONTENT_DIR . '/object-cache.php');
                    }
                    wp_die(json_encode(array('code' => "MEMCACHE_NOT_WORK", 'data' => "FAILURE", 'state' => "unknown")));
                }

                file_put_contents(WP_CONTENT_DIR . '/object-cache.php', file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
            } else {
                if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                    unlink(WP_CONTENT_DIR . '/object-cache.php');
                }
                wp_die(json_encode(array('code' => "MEMCACHE_NOT_FOUND", 'data' => "FAILURE", 'state' => "unknown")));
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
    function lws_optimize_manage_exclusions()
    {
        check_ajax_referer('nonce_lws_optimize_exclusions_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the ID for the currently open modal and get the action to modify
        $data = $_POST['data'];
        foreach ($data as $key => $var) {
            if ($var['name'] == "lwsoptimize_exclude_url_id") {
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
        if (preg_match('/lws_optimize_(.*?)_exclusion/', $id, $match) !== 1) {
            wp_die(json_encode(array('code' => "UNKNOWN_ID", "data" => $id)), JSON_PRETTY_PRINT);
        }

        $exclusions = array();

        // The $element to update
        $element = $match[1];
        // All configs for LWS Optimize
        $old = $this->optimize_options;

        // Get all exclusions
        foreach ($data as $key => $var) {
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
        } else {
            wp_die(json_encode(array('code' => "FAILURE", "data" => $this->optimize_options)), JSON_PRETTY_PRINT);
        }
    }

    function lws_optimize_manage_exclusions_media()
    {
        check_ajax_referer('nonce_lws_optimize_exclusions_media_config', '_ajax_nonce');
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the ID for the currently open modal and get the action to modify
        $data = $_POST['data'];
        foreach ($data as $key => $var) {
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
        foreach ($data as $key => $var) {
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

    function lws_optimize_fetch_exclusions()
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

        $this->lwsop_recalculate_stats();
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_stylecache()
    {
        check_ajax_referer('clear_style_fb_caching', '_ajax_nonce');
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-js"));
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-css"));

        $this->lwsop_recalculate_stats();
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_clear_htmlcache()
    {
        check_ajax_referer('clear_html_fb_caching', '_ajax_nonce');
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache"));
        apply_filters("lws_optimize_clear_filebased_cache", $this->lwsop_get_content_directory("cache-mobile"));

        $this->lwsop_recalculate_stats();
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

        // Get the PATH to the 
        $cache_dir = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);
        if ($cache_dir !== false && is_dir($cache_dir)) {
            if ($cache_dir == $this->lwsop_get_content_directory("cache") || $cache_dir == $this->lwsop_get_content_directory("cache-mobile")) {
                $files = glob($cache_dir . '/index_*');

                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            } else {
                apply_filters("lws_optimize_clear_filebased_cache", $cache_dir);
            }
        }

        // Update the stats after the end
        $this->lwsop_recalculate_stats();
        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => "/"), JSON_PRETTY_PRINT));
    }

    /**
     * Clean the given directory. If no directory is given, remove /cache/lwsoptimize/
     */
    function lws_optimize_clean_filebased_cache($directory = false)
    {
        if (!function_exists("lws_optimize_delete_directory")) {
            function lws_optimize_delete_directory($dir, $stats = false)
            {
                if (!file_exists($dir)) {
                    return false;
                }

                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    if (is_dir("$dir/$file")) {
                        lws_optimize_delete_directory("$dir/$file");
                    } else {
                        @unlink("$dir/$file");
                        if (file_exists("$dir/$file")) {
                            return false;
                        }
                    }
                }

                rmdir($dir);
                return (!file_exists($dir));
            }
        }

        if ($directory) {
            $directory = esc_url($directory);
            if (is_dir($directory)) {
                lws_optimize_delete_directory($directory);
            }
        } else {
            lws_optimize_delete_directory(LWS_OP_UPLOADS);
        }

        if ($array = get_option('lws_optimize_config_array', false)) {
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
    function lws_optimize_start_filebased_preload()
    {
        global $wpdb;
        $urls_to_cache = array();

        $page_ids = $wpdb->get_results("SELECT ID FROM " . $wpdb->posts . " WHERE (" . $wpdb->prefix . "posts.post_type = 'post' OR " . $wpdb->prefix . "posts.post_type = 'page') AND (" . $wpdb->prefix . "posts.post_status = 'publish');");
        foreach ($page_ids as $id) {
            $urls_to_cache[] = get_permalink($id->ID);
        }

        if ($array = get_option('lws_optimize_config_array', false)) {
            $max_try = intval($array['filebased_cache']['preload_amount'] ?? 5);
            $current_try = 0;

            $lws_filebased = new FileCache($GLOBALS['lws_optimize']);

            foreach ($urls_to_cache as $caching) {
                if ($current_try < $max_try) {
                    $parsed_url = parse_url($caching);
                    $parsed_url = isset($parsed_url['path']) ? $parsed_url['path'] : '';

                    if (!file_exists($lws_filebased->lwsop_set_cachedir($parsed_url))) {
                        $response = wp_remote_get($caching, array(
                            'user-agent' => "LWSOptimize Preloading cURL", 'timeout' => 10,
                            'sslverify' => false, 'headers' => array("Cache-Control" => "no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0")
                        ));

                        if (!$response || is_wp_error($response)) {
                            error_log($response->get_error_message() . " - ");
                        }
                        $current_try++;
                    }
                }
            }
        }
    }

    function lwsop_specified_urls_fb()
    {
        check_ajax_referer('lwsop_get_specified_url_nonce', '_ajax_nonce');
        if (isset($this->optimize_options['filebased_cache']) && isset($this->optimize_options['filebased_cache']['specified'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $this->optimize_options['filebased_cache']['specified'], 'domain' => site_url()), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => array(), 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
    }

    function lwsop_save_specified_urls_fb()
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

    function lwsop_exclude_urls_fb()
    {
        global $config_array;
        check_ajax_referer('lwsop_get_excluded_nonce', '_ajax_nonce');
        if (isset($config_array['filebased_cache']) && isset($config_array['filebased_cache']['exclusions'])) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array['filebased_cache']['exclusions'], 'domain' => site_url()), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => array(), 'domain' => site_url()), JSON_PRETTY_PRINT));
        }
    }

    function lwsop_save_urls_fb()
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
            if (empty($option) || $option === NULL) {
                return ['state' => "false", 'data' => []];
            }

            $option = sanitize_text_field($option);
            if (isset($this->optimize_options[$option])) {
                if (isset($this->optimize_options[$option]['state'])) {
                    $array = $this->optimize_options[$option];
                    $state = $array['state'];
                    unset($array['state']);
                    $data = $array;

                    return ['state' => $state, 'data' => $data];
                }
            }
            return ['state' => "false", 'data' => []];
        } catch (Exception $e) {
            error_log("LwsOptimize.php::lwsop_check_option | " . $e);
            return ['state' => "false", 'data' => []];
        }
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
                        $my_current_lang = apply_filters('wpml_current_language', NULL);
                        if ($my_current_lang) {
                            $additional = $my_current_lang;
                        }
                        break;
                }
            }

            if ($this->lwsop_plugin_active('multiple-domain-mapping-on-single-site/multidomainmapping.php') || $this->lwsop_plugin_active('multiple-domain/multiple-domain.php') || is_multisite()) {
                $additional = $_SERVER['HTTP_HOST'];
            }

            if ($this->lwsop_plugin_active('polylang/polylang.php')) {
                $polylang_settings = get_option("polylang");
                if (isset($polylang_settings["force_lang"])) {
                    if ($polylang_settings["force_lang"] == 2 || $polylang_settings["force_lang"] == 3) {
                        $additional = $_SERVER['HTTP_HOST'];
                    }
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
    public function lwsop_recalculate_stats()
    {
        $stats = [
            'desktop' => ['amount' => 0, 'size' => 0],
            'mobile' => ['amount' => 0, 'size' => 0],
            'css' => ['amount' => 0, 'size' => 0],
            'js' => ['amount' => 0, 'size' => 0],
        ];

        $paths = [
            'desktop' => $this->lwsop_get_content_directory("cache"),
            'mobile' => $this->lwsop_get_content_directory("cache-mobile"),
            'css' => $this->lwsop_get_content_directory("cache-css"),
            'js' => $this->lwsop_get_content_directory("cache-js")
        ];

        $usable_stats = [];


        foreach ($paths as $type => $path) {
            $totalSize = 0;
            $fileCount = 0;
            if (is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path),
                    RecursiveIteratorIterator::SELF_FIRST
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


            $usable_stats[$type] = [
                'amount' => $fileCount,
                'size' => $this->lws_size_convert($totalSize)
            ];
        }

        update_option('lws_optimize_cache_statistics', $stats);

        return $usable_stats;
    }

    function lws_size_convert($size)
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
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if ($post->post_type == "product") {
                $shop_id = wc_get_page_id('shop');
                $uri = get_page_uri($shop_id);
                $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

                apply_filters("lws_optimize_clear_filebased_cache", $file);
            }
        }

        $uri = get_page_uri($post_id);
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
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if ($post->post_type == "product") {
                $shop_id = wc_get_page_id('shop');
                $uri = get_page_uri($shop_id);
                $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

                apply_filters("lws_optimize_clear_filebased_cache", $file);
            }
        }
        $uri = get_page_uri($post_id);
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
            $uri = get_page_uri($cart_id);
            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $checkout_id = wc_get_page_id('checkout');
            $uri = get_page_uri($checkout_id);
            $file = $this->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            lwsop_clear_cache_cloudflare("purge", array($url));
            $this->purge_specified_url();
        }
    }

    // Fetch options for maintaining DB
    function lws_optimize_manage_maintenance_get()
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
    function lws_optimize_set_maintenance_db_options()
    {
        // Add all URLs to an array, but ignore empty URLs
        // If all fields are empty, remove the option from DB
        check_ajax_referer('lwsop_set_maintenance_db_nonce', '_ajax_nonce');
        if (isset($_POST['formdata'])) {
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
        wp_die(json_encode(array('code' => "NO_DATA", 'data' => $_POST, 'domain' => site_url()), JSON_PRETTY_PRINT));
    }
    //

    function lws_optimize_create_maintenance_db_options()
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
                    $wpdb->query("DELETE FROM {$wpdb->prefix}_posts WHERE post_status = 'draft';");
                    // Remove drafts
                    break;
                case 'revisions':
                    $wpdb->query("DELETE FROM `{$wpdb->prefix}_posts` WHERE post_type = 'revision';");
                    // Remove revisions
                    break;
                case 'deleted_posts':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}_posts WHERE post_status = 'trash' && post_type = 'post' OR post_type = 'page';");
                    // Remove trashed posts/page
                    break;
                case 'spam_comments':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}_comments WHERE comment_approved = 'spam';");
                    // remove spam comments
                    break;
                case 'deleted_comments':
                    $wpdb->query("DELETE FROM {$wpdb->prefix}_comments WHERE comment_approved = 'trash';");
                    // remove deleted comments
                    break;
                case 'expired_transients':
                    $query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}_options WHERE option_name LIKE '%_transient_timeout_%' AND option_value < ?;", [time()]);
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
        if (!isset($_POST['action']) || !isset($_POST['data'])) {
            wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
        }

        // Get the value to change the plugin configuration
        $data = $_POST['data'];
        $value = false;
        foreach ($data as $key => $var) {
            if ($var['name'] == "lwsop_configuration[]") {
                $value = sanitize_text_field($var['value']);
                break;
            }
        }

        // No value ? Cannot proceed
        if (!isset($value) || $value == false) {
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
                $options['memcached']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "false";
                $options['maintenance_db']['options'] = [];
                $options['preload_css']['state'] = "false";
                $options['preload_font']['state'] = "false";
                $options['deactivate_emoji']['state'] = "false";
                $options['eliminate_requests']['state'] = "false";
                $options['mobile_cache']['state'] = "false";
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
                $options['memcached']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "true";
                $options['maintenance_db']['options'] = ["myisam", "spam_comments", "expired_transients"];
                $options['preload_css']['state'] = "true";
                $options['preload_font']['state'] = "true";
                $options['deactivate_emoji']['state'] = "false";
                $options['eliminate_requests']['state'] = "false";
                $options['mobile_cache']['state'] = "false";
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
                $options['memcached']['state'] = "true";
                $options['image_lazyload']['state'] = "true";
                $options['iframe_video_lazyload']['state'] = "true";
                $options['maintenance_db']['state'] = "true";
                $options['maintenance_db']['options'] = ["myisam", "spam_comments", "expired_transients", "drafts", "revisions", "deleted_posts", "deleted_comments"];
                $options['preload_css']['state'] = "true";
                $options['preload_font']['state'] = "true";
                $options['deactivate_emoji']['state'] = "true";
                $options['eliminate_requests']['state'] = "true";
                $options['mobile_cache']['state'] = "true";
                $options['dynamic_cache']['state'] = "true";

                update_option('lws_optimize_config_array', $options);
                break;
            default:
                break;
        }

        apply_filters("lws_optimize_clear_filebased_cache", false);
    }

    public function manage_image_optimization() {
        require_once (dirname(__DIR__) . "/classes/ImageOptimization.php");

        // If the media optimisation is active, instantiate the class
        if ($this->lwsop_check_option("media_optimize")['state'] == "true") {
            $media_options = $this->lwsop_check_option("media_optimize")['data'] ?? [];

            $auto_convert_on_upload = $media_options['convert_on_upload'] ?? false;
            $auto_convert_on_cron = $media_options['convert_cron'] ?? false;

            // If not found, add back the cron for the media conversion
            // Executed every hour, it will convert all images to the selected mimetype
            if ($auto_convert_on_cron) {
                if (!wp_next_scheduled('lws_optimize_convert_media_cron')) {
                    wp_schedule_event(time(), 'hourly', 'lws_optimize_convert_media_cron');
                }
                // $im_opt->convert_all_medias();
            } else {
                wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
            }

            if ($auto_convert_on_upload) {
                $im_opt = new ImageOptimization($autoupdate = true);
            }
        }

        // $im_opt->convert_all_medias();
        // $im_opt->revertOptimization();

        // TODO : Onglet dédié, si pas activé, rien disponible
        // Si activé, propose les différentes actions
    }

    public function lws_optimize_convert_media_cron() {
        require_once (dirname(__DIR__) . "/classes/ImageOptimization.php");
        $im_opt = new ImageOptimization();
        $im_opt->convert_all_medias();
    }
}
