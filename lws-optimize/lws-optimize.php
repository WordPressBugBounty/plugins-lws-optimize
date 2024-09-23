<?php

/**
 * Plugin Name:       LWS Optimize
 * Plugin URI:        https://www.lws.fr/
 * Description:       Reach better speed and performances with Optimize! Minification, Combination, Media conversion... Everything you need for a better website
 * Version:           3.1.6.2
 * Author:            LWS
 * Author URI:        https://www.lws.fr
 * Tested up to:      6.5
 * Domain Path:       /languages
 *
 * @link    https://sms.lws.fr/
 * @since   1.0
 * @package lws-optimize
 * 
 * This plugin is greatly based on a fork of WPFastestCache (https://wordpress.org/plugins/wp-fastest-cache/) by Emre Vona, 
 * specifically the JS/CSS optimisation (minify, combine, ...) and the filebased-cache verifications (when not to cache the current page)
 */

if (!defined('ABSPATH')) {
    exit; //Exit if accessed directly
}

// https://example.fr/wp-content/plugins/lws-optimize/
if (!defined('LWS_OP_URL')) {
    define('LWS_OP_URL', plugin_dir_url(__FILE__));
}

// ABSPATH/wp-content/plugins/lws-optimize/
if (!defined('LWS_OP_DIR')) {
    define('LWS_OP_DIR', plugin_dir_path(__FILE__));
}

// ABSPATH/wp-content/cache/lwsoptimize/
if (!defined('LWS_OP_UPLOADS')) {
    define('LWS_OP_UPLOADS', ABSPATH . 'wp-content/cache/lwsoptimize/');
}

// lws-optimize/lws-optimize.php
if (!defined('LWS_OP_BASENAME')) {
    define('LWS_OP_BASENAME', plugin_basename(__FILE__));
}


// Polyfills of useful PHP8+ functions for PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * Create, if not already existing, a directory for the file-based cache
 */
register_activation_hook(__FILE__, 'lws_op_on_activation');
function lws_op_on_activation()
{
    set_transient('lwsop_remind_me', 691200);
}

/**
 * Remove the created directory on delete
 */
register_uninstall_hook(__FILE__, 'lws_op_on_delete');
function lws_op_on_delete()
{
    // Remove the cache folder
    apply_filters("lws_optimize_clear_filebased_cache", false);

    // Remove all options on delete
    if (get_option('lws_optimize_config_array', null) !== null) {
        delete_option('lws_optimize_config_array');
    }

    delete_transient('lwsop_remind_me');
}

/**
 * Remove old options from the database on update
 */
add_action('upgrader_process_complete', 'lws_optimize_on_upgrade_cleanup', 10, 2);
function lws_optimize_on_upgrade_cleanup($upgrader_object, $options)
{
    $current_plugin_path_name = plugin_basename(__FILE__);

    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $each_plugin) {
            if ($each_plugin == $current_plugin_path_name) {
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
                break;
            }
        }
    }
}

/**
 * Enqueue any CSS or JS script needed
 */
add_action('admin_enqueue_scripts', 'lws_op_scripts');
function lws_op_scripts()
{
    $versionning = "1.0";
    wp_enqueue_style('lws_top_css_out', LWS_OP_URL . "css/lws_op_stylesheet_out.css");
    if (get_current_screen()->base == ('toplevel_page_lws-op-config')) {

        wp_enqueue_style('lws_op_css', LWS_OP_URL . "css/lws_op_stylesheet.css?v=" . $versionning);
        wp_enqueue_style('lws_cl-Poppins', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
        wp_enqueue_script("lws_op_bootstrap_js", plugin_dir_url(__FILE__) . 'js/bootstrap.min.js?v=' . $versionning, array('jquery'));
        wp_enqueue_style("lws_op_bootstrap_css", plugin_dir_url(__FILE__) . 'css/bootstrap.min.css?v=' . $versionning);

        wp_enqueue_script("lws_op_popper_js", "https://unpkg.com/@popperjs/core@2");
    }
}

/**
 * Enqueue any CSS or JS script needed in front-end
 */
add_action('wp_enqueue_scripts', 'lws_op_scripts_out');
function lws_op_scripts_out()
{
    if (current_user_can('editor') || current_user_can('administrator')) {
        wp_enqueue_style('lws_op_style_admin', LWS_OP_URL . "css/lws_op_stylesheet_adminbar.css");
    }
}

function lwsop_review_ad_plugin()
{
?>
    <script>
        function lwsop_remind_me() {
            var data = {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('reminder_for_op')); ?>',
                action: "lws_op_reminder_ajax",
                data: true,
            };
            jQuery.post(ajaxurl, data, function(response) {
                jQuery("#lwsop_review_notice").addClass("animationFadeOut");
                setTimeout(() => {
                    jQuery("#lwsop_review_notice").addClass("lws_hidden");
                }, 800);
            });

        }

        function lwsop_do_not_bother_me() {
            var data = {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('donotask_for_op')); ?>',
                action: "lws_op_donotask_ajax",
                data: true,
            };
            jQuery.post(ajaxurl, data, function(response) {
                jQuery("#lwsop_review_notice").addClass("animationFadeOut");
                setTimeout(() => {
                    jQuery("#lwsop_review_notice").addClass("lws_hidden");
                }, 800);
            });
        }
    </script>

    <div class="notice notice-info is-dismissible lwsop_review_block_general" id="lwsop_review_notice">
        <div class="lwsop_circle">
            <img class="lwsop_review_block_image" src="<?php echo esc_url(plugins_url('images/plugin_lws-optimize.svg', __FILE__)) ?>" width="40px" height="40px">
        </div>
        <div style="padding:16px">
            <h1 class="lwsop_review_block_title"> <?php esc_html_e('Thank you for using LWS Optimize!', 'lws-optimize'); ?></h1>
            <p class="lwsop_review_block_desc"><?php _e('Evaluate our plugin to help others optimize and boost the performances of their WordPress website!', 'lws-optimize'); ?></p>
            <a class="lwsop_button_rate_plugin" href="https://wordpress.org/support/plugin/lws-optimize/reviews/" target="_blank"><img style="margin-right: 8px;" src="<?php echo esc_url(plugins_url('images/noter.svg', __FILE__)) ?>" width="15px" height="15px"><?php esc_html_e('Rate', 'lws-optimize'); ?></a>
            <a class="lwsop_review_button_secondary" onclick="lwsop_remind_me()"><?php esc_html_e('Remind me later', 'lws-optimize'); ?></a>
            <a class="lwsop_review_button_secondary" onclick="lwsop_do_not_bother_me()"><?php esc_html_e('Do not ask again', 'lws-optimize'); ?></a>
        </div>
    </div>
<?php
}

// //AJAX Reminder//
// add_action("wp_ajax_lws_op_reminder_ajax", "lws_op_remind_me_later");
// function lws_op_remind_me_later()
// {
//     check_ajax_referer('reminder_for_op', '_ajax_nonce');
//     if (isset($_POST['data'])) {
//         set_transient('lwsop_remind_me', 2592000);
//     }
// }

// //AJAX Reminder//
// add_action("wp_ajax_lws_op_donotask_ajax", "lws_op_do_not_ask");
// function lws_op_do_not_ask()
// {
//     check_ajax_referer('donotask_for_op', '_ajax_nonce');
//     if (isset($_POST['data'])) {
//         update_option('lwsop_do_not_ask_again', true);
//     }
// }

/**
 * Create plugin menu in wp-admin
 */
add_action('admin_menu', 'lws_op_menu_admin');
function lws_op_menu_admin()
{
    add_menu_page(__('LWS Optimize', 'lws-optimize'), 'LWS Optimize', 'manage_options', 'lws-op-config', 'lws_op_page', LWS_OP_URL . 'images/plugin_lws_optimize.svg');
}


function lws_op_page()
{
    $tabs_list = array(
        array('frontend', __('Frontend', 'lws-optimize')),
        array('medias', __('Medias', 'lws-optimize')),
        array('caching', __('Caching', 'lws-optimize')),
        array('cdn', __('CDN', 'lws-optimize')),
        array('database', __('Database', 'lws-optimize')),
        array('pagespeed', __('Pagespeed test', 'lws-optimize')),
        array('plugins', __('Our others plugins', 'lws-optimize')),
    );
    include __DIR__ . '/views/tabs.php';
}



add_action('admin_init', 'lws_op_check_compatibility');
function lws_op_check_compatibility()
{
    if (
        is_plugin_active('wp-rocket/wp-rocket.php') || is_plugin_active('powered-cache/powered-cache.php') || is_plugin_active('wp-super-cache/wp-cache.php')
        || is_plugin_active('wp-optimize/wp-optimize.php') || is_plugin_active('wp-fastest-cache/wpFastestCache.php') || is_plugin_active('w3-total-cache/w3-total-cache.php')
    ) {
        update_option('lws_optimize_offline', 'ON');
        add_action('admin_notices', 'lws_op_other_cache_plugin');
    }
}

function lws_op_other_cache_plugin()
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
                this.innerHTML = `
                    <img src="<?php echo plugin_dir_url(__FILE__); ?>/images/loading_black.svg" width="20px">
                `;
                var data = {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('deactivate_plugin_lwsoptimize')); ?>',
                    action: "lws_op_other_cache_plugin_ajax",
                    data: {
                        id: id
                    },
                };
                jQuery.post(ajaxurl, data, function(response) {
                    location.reload();
                });
            })
        });
    </script>
<?php
}

add_action("wp_ajax_lws_op_other_cache_plugin_ajax", "lws_optimize_remove_other_cache");
function lws_optimize_remove_other_cache()
{
    check_ajax_referer('deactivate_plugin_lwsoptimize', '_ajax_nonce');
    if (isset($_POST['action'])) {
        if (isset($_POST['data'])) {
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
            }
        }
    }
}



//AJAX DL Plugin//
add_action("wp_ajax_lws_op_downloadPlugin", "wp_ajax_install_plugin");
//

//AJAX Activate Plugin//
add_action("wp_ajax_lws_op_activatePlugin", "lws_op_activate_plugin");
function lws_op_activate_plugin()
{
    check_ajax_referer('activate_plugin', '_ajax_nonce');
    if (isset($_POST['ajax_slug'])) {
        switch (sanitize_textarea_field($_POST['ajax_slug'])) {
            case 'lws-hide-login':
                activate_plugin('lws-hide-login/lws-hide-login.php');
                break;
            case 'lws-sms':
                activate_plugin('lws-sms/lws-sms.php');
                break;
            case 'lws-tools':
                activate_plugin('lws-tools/lws-tools.php');
                break;
            case 'lws-affiliation':
                activate_plugin('lws-affiliation/lws-affiliation.php');
                break;
            case 'lws-cleaner':
                activate_plugin('lws-cleaner/lws-cleaner.php');
                break;
            case 'lwscache':
                activate_plugin('lwscache/lwscache.php');
                break;
            case 'lws-optimize':
                activate_plugin('lws-optimize/lws-optimize.php');
                break;
        }
    }
    wp_die();
}

// CDN
add_action("wp_ajax_lws_optimize_check_cloudflare_key", function () {
    check_ajax_referer('lwsop_check_cloudflare_key_nonce', '_ajax_nonce');
    $token_key = $_POST['key'] ?? NULL;

    if ($token_key === NULL) {
        wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
    }

    $token_key = sanitize_text_field($token_key);

    $response = wp_remote_get(
        "https://api.cloudflare.com/client/v4/user/tokens/verify",
        [
            'timeout' => 45,
            'sslverify' => false,
            'headers' => [
                "Authorization" => "Bearer " . $token_key,
                "Content-Type" => "application/json"
            ]
        ]
    );

    if (is_wp_error($response)) {
        wp_die(json_encode(array('code' => "ERROR_CURL", 'data' => $response), JSON_PRETTY_PRINT));
    }

    $response = json_decode($response['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
    }

    $success = $response['success'] ?? NULL;
    $status = $response['result']['status'] ?? NULL;

    // The verification has succeeded
    if ($success !== NULL && $success === true) {
        // If the key is active, we now check for zones
        if ($status == "active") {
            $zones_response = wp_remote_get(
                "https://api.cloudflare.com/client/v4/zones",
                [
                    'timeout' => 45,
                    'sslverify' => false,
                    'headers' => [
                        "Authorization" => "Bearer " . $token_key,
                        "Content-Type" => "application/json"
                    ]
                ]
            );

            if (is_wp_error($zones_response)) {
                wp_die(json_encode(array('code' => "ERROR_CURL_ZONES", 'data' => $zones_response), JSON_PRETTY_PRINT));
            }

            $zones_response = json_decode($zones_response['body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_die(json_encode(array('code' => "ERROR_DECODE_ZONES", 'data' => $zones_response), JSON_PRETTY_PRINT));
            }

            $zone_id = NULL;
            $account_id = NULL;
            $success = $zones_response['success'] ?? NULL;
            if ($success !== NULL && $success === true) {
                $amount = $zones_response['result_info']['count'];
                if ($amount <= 0) {
                    wp_die(json_encode(array('code' => "NO_ZONES", 'data' => $zones_response), JSON_PRETTY_PRINT));
                } else {
                    foreach ($zones_response['result'] as $zone) {
                        if ($zone['name'] == $_SERVER['SERVER_NAME']) {
                            $zone_id = $zone['id'];
                            $account_id = $zone['account']['id'];
                            break;
                        }
                    }

                    if ($zone_id === NULL) {
                        wp_die(json_encode(array('code' => "NO_ZONE_FOR_DOMAIN", 'data' => $zones_response), JSON_PRETTY_PRINT));
                    }
                }
            } else {
                wp_die(json_encode(array('code' => "ERROR_REQUEST_ZONES", 'data' => $response), JSON_PRETTY_PRINT));
            }

            $tmp = $config_array = get_option('lws_optimize_config_array', array());
            $config_array['cloudflare']['api'] = $token_key;
            $config_array['cloudflare']['zone_id'] = $zone_id;
            $config_array['cloudflare']['account_id'] = $account_id;
            $saved = update_option('lws_optimize_config_array', $config_array);
            if ($saved === true || empty(array_diff($config_array, $tmp))) {
                wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
            } else {
                wp_die(json_encode(array('code' => "FAILED_SAVE", 'data' => $response), JSON_PRETTY_PRINT));
            }
        } else {
            wp_die(json_encode(array('code' => "INVALID", 'data' => $response), JSON_PRETTY_PRINT));
        }
    } else {
        wp_die(json_encode(array('code' => "ERROR_REQUEST", 'data' => $response), JSON_PRETTY_PRINT));
    }
});

add_action("wp_ajax_lws_optimize_cloudflare_tools_deactivation", function () {
    check_ajax_referer('lwsop_opti_cf_tools_nonce', '_ajax_nonce');
    $min_css = $_POST['min_css'] ?? NULL;
    $min_js = $_POST['min_js'] ?? NULL;
    $dynamic_cache = $_POST['cache_deactivate'] ?? NULL;

    $tmp = $config_array = get_option('lws_optimize_config_array', array());

    $config_array['cloudflare']['tools'] = [
        'min_css' => $min_css === NULL ? false : true,
        'min_js' => $min_js === NULL ? false : true,
        'dynamic_cache' => $dynamic_cache === NULL ? false : true,
    ];
    $saved = update_option('lws_optimize_config_array', $config_array);

    if ($saved === true || empty(array_diff($config_array, $tmp))) {
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
    } else {
        wp_die(json_encode(array('code' => "FAILED_SAVE", 'data' => $config_array), JSON_PRETTY_PRINT));
    }
});

add_action("wp_ajax_lws_optimize_cloudflare_cache_duration", function () {
    check_ajax_referer('lwsop_opti_cf_duration_nonce', '_ajax_nonce');
    $cache_span = $_POST['lifespan'] ?? NULL;

    if ($cache_span !== NULL) {
        $tmp = $config_array = get_option('lws_optimize_config_array', array());
        $config_array['cloudflare']['lifespan'] = sanitize_text_field($cache_span);
        $saved = update_option('lws_optimize_config_array', $config_array);
    }

    if ($saved === true || empty(array_diff($config_array, $tmp))) {
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
    } else {
        wp_die(json_encode(array('code' => "FAILED_SAVE", 'data' => $config_array), JSON_PRETTY_PRINT));
    }
});

add_action("wp_ajax_lws_optimize_cloudflare_finish_configuration", function () {
    check_ajax_referer('lwsop_cloudflare_finish_config_nonce', '_ajax_nonce');

    $tmp = $config_array = get_option('lws_optimize_config_array', array());
    $zone_id = $config_array['cloudflare']['zone_id'] ?? NULL;
    $api_token = $config_array['cloudflare']['api'] ?? NULL;
    $cache_span = $config_array['cloudflare']['lifespan'] ?? NULL;
    $tools = $config_array['cloudflare']['tools'] ?? NULL;

    if ($zone_id === NULL || $api_token === NULL || $cache_span === NULL || $tools === NULL) {
        wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT));
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/browser_cache_ttl",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => "{\n  \"value\": " . $cache_span . "\n}",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$api_token}",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
    }

    if ($response['success'] === true) {
        // Redefine the cloudflare entry with the latest info + state true
        $config_array['cloudflare'] = [
            'zone_id' => $zone_id,
            'api' => $api_token,
            'lifespan' => $cache_span,
            'tools' => $tools,
            'state' => "true"

        ];

        // Deactivate the tools chosen by the user

        if ($tools['min_css'] === true) {
            $config_array['minify_css']['state'] = "false";
        }
        if ($tools['min_js'] === true) {
            $config_array['minify_js']['state'] = "false";
        }
        $config_array['dynamic_cache']['state'] = "false";

        $saved = update_option('lws_optimize_config_array', $config_array);
        if ($saved === true || empty(array_diff($config_array, $tmp))) {
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
        } else {
            wp_die(json_encode(array('code' => "FAILED_SAVE", 'data' => $config_array), JSON_PRETTY_PRINT));
        }
    } else {
        unset($config_array['cloudflare']);
        wp_die(json_encode(array('code' => "FAILED_PATCH", 'data' => $config_array), JSON_PRETTY_PRINT));
    }
});

add_action("wp_ajax_lws_optimize_deactivate_cloudflare_integration", function () {
    check_ajax_referer('lwsop_deactivate_cf_integration_nonce', '_ajax_nonce');

    $tmp = $config_array = get_option('lws_optimize_config_array', array());

    $zone_id = $config_array['cloudflare']['zone_id'] ?? NULL;
    $api_token = $config_array['cloudflare']['api'] ?? NULL;
    $cache_span = $config_array['cloudflare']['lifespan'] ?? NULL;

    if ($zone_id === NULL || $api_token === NULL || $cache_span === NULL) {
        wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT));
    }

    unset($config_array['cloudflare']);

    $saved = update_option('lws_optimize_config_array', $config_array);

    // Set the Cloudflare cache to its default value. Whether or not it works, continue
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/browser_cache_ttl",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => "{\n  \"value\": 14400\n}",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$api_token}",
            "Content-Type: application/json"
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($saved === true || empty(array_diff($config_array, $tmp))) {
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array, 'cache' => $response), JSON_PRETTY_PRINT));
    } else {
        wp_die(json_encode(array('code' => "FAILED_SAVE", 'data' => $config_array, 'cache' => $response), JSON_PRETTY_PRINT));
    }
});

/**
 * $cache_type : purge / part_purge ; $url : ["url", "url"]
 */
function lwsop_clear_cache_cloudflare(string $cache_type, array $url)
{
    $purge_type = NULL;
    if ($cache_type === "purge") {
        $purge_type = "full_purge";
    } elseif ($cache_type === "part_purge") {
        $purge_type = "part_purge";
    } else {
        $purge_type = NULL;
    }

    $config_array = get_option('lws_optimize_config_array', array());

    $zone_id = $config_array['cloudflare']['zone_id'] ?? NULL;
    $api_token = $config_array['cloudflare']['api'] ?? NULL;

    if ($zone_id === NULL || $api_token === NULL || $purge_type === NULL || $url === NULL || !is_array($url)) {
        // error_log(json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT));
        return (json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT));
    }

    $ch = curl_init();

    if ($purge_type === 'full_purge') {
        $host = esc_url($url[0]);
        $host = str_replace("https://", '', $host);

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\n  \"files\": [\n \"{$host}\"\n ]\n}",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$api_token}",
                "Content-Type: application/json"
            ],
        ]);
    } elseif ($purge_type === 'part_purge') {
        $tmp = "{\n  \"prefixes\": [\n ";
        foreach ($url as $prefix) {
            $prefix = esc_url($prefix);
            $tmp .= "\"$prefix\", ";
        }

        $tmp = substr($tmp, 0, -2);
        $tmp .= "\n ]\n}";

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $tmp,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$api_token}",
                "Content-Type: application/json"
            ],
        ]);
    } else {
        // error_log(json_encode(array('code' => "UNDEFINED_PTYPE", 'data' => $purge_type), JSON_PRETTY_PRINT));
        return (json_encode(array('code' => "UNDEFINED_PTYPE", 'data' => $purge_type), JSON_PRETTY_PRINT));
    }

    $response = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // error_log(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
        return (json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
    }

    if ($response['success']) {
        error_log(json_encode(array('code' => "SUCCESS", 'data' => $response), JSON_PRETTY_PRINT));
        return (json_encode(array('code' => "SUCCESS", 'data' => $response), JSON_PRETTY_PRINT));
    } else {
        error_log(json_encode(array('code' => "FAIL_PURGE", 'data' => $response), JSON_PRETTY_PRINT));
        return (json_encode(array('code' => "FAIL_PURGE", 'data' => $response), JSON_PRETTY_PRINT));
    }
}
// CDN


// Remove all notices and popup while on the config page
add_action('admin_notices', function () {
    if (substr(get_current_screen()->id, 0, 29) == "toplevel_page_lws-op-config") {
        remove_all_actions('admin_notices');
        if (get_option('lws_optimize_offline') && (is_plugin_active('wp-rocket/wp-rocket.php') || is_plugin_active('powered-cache/powered-cache.php') || is_plugin_active('wp-super-cache/wp-cache.php')
            || is_plugin_active('wp-optimize/wp-optimize.php') || is_plugin_active('wp-fastest-cache/wpFastestCache.php') || is_plugin_active('w3-total-cache/w3-total-cache.php'))) {
            lws_op_other_cache_plugin();
        }
    }
}, 0);

// Deactivate the plugin ; No more actions can be activated
add_action("wp_ajax_lws_optimize_manage_state", "lws_optimize_manage_state");
function lws_optimize_manage_state()
{
    check_ajax_referer('nonce_lws_optimize_activate_config', '_ajax_nonce');
    if (!isset($_POST['action']) || !isset($_POST['checked'])) {
        wp_die(json_encode(array('code' => "DATA_MISSING", "data" => $_POST)), JSON_PRETTY_PRINT);
    }

    $state = sanitize_text_field($_POST['checked']);
    if ($state == "true") {
        $result = delete_option('lws_optimize_offline');
    } else {
        $result = update_option('lws_optimize_offline', "ON");
    }

    wp_die(json_encode(array('code' => "SUCCESS", "data" => $result)), JSON_PRETTY_PRINT);
}


$state = get_option('lws_optimize_offline', null);
if (!isset($state)) {
    // Force all images to be/not be lazy loaded ; By default activated
    if ((isset($config_array['image_lazyload']['state']) && $config_array['image_lazyload']['state'] === "true")) {
        add_filter('wp_get_attachment_image_attributes', 'lws_optimize_manage_media_image_lazyload', 0, 3);
        // Manage lazy-loading for pages where this hook is usable
        function lws_optimize_manage_media_image_lazyload($attr, $attachment, $size)
        {
            global $config_array;

            if (is_admin()) {
                return $attr;
            }

            if (isset($attr['fetchpriority']) && $attr['fetchpriority'] == "high") {
                unset($attr['loading']);
            } else {
                $attr['loading'] = 'lazy';
            }

            // Thumbnails banned
            if (isset($config_array['lazyload']['exclusions']['media_types']['thumbnails'])) {
                if (str_contains($size, 'thumbnail')) {
                    unset($attr['loading']);
                    return $attr;
                }
            }

            // Responsive banned ; If has attribute "sizes", is considered as Responsive
            if (isset($config_array['lazyload']['exclusions']['media_types']['responsive'])) {
                if (isset($attr['sizes'])) {
                    unset($attr['loading']);
                    return $attr;
                }
            }

            // If banned class found, no lazy-loading
            if (isset($config_array['lazyload']['exclusions']['css_classes']) && isset($attr['class'])) {
                $classes = $config_array['lazyload']['exclusions']['css_classes'];
                $found_values = array_intersect($classes, explode(" ", $attr['class']));

                if (!empty($found_values)) {
                    unset($attr['loading']);
                    return $attr;
                }
            }


            // Remove lazy-load for images whose source contains
            if (isset($config_array['lazyload']['exclusions']['img_iframe'])) {
                foreach ($config_array['lazyload']['exclusions']['img_iframe'] as $url) {
                    // Find all img tags with $url in their src attribute and loading="lazy"
                    if (trim(esc_url($url)) == '') {
                        continue;
                    }
                    if (preg_match("~" . esc_url($url) . "~", $attr['src'])) {
                        unset($attr['loading']);
                    }
                }
            }

            // Lazyload the image
            return $attr;
        }


        add_filter('the_content', 'lws_optimize_manage_media_image_lazyload_content');
        // Function to add lazy-loading to images
        function lws_optimize_manage_media_image_lazyload_content($content)
        {
            if (is_admin()) {
                return $content;
            }

            global $config_array;
            // Define the classes that exempt images from lazy-loading
            if (isset($config_array['lazyload']['exclusions']['css_classes'])) {
                $exempt_classes = $config_array['lazyload']['exclusions']['css_classes'];
            } else {
                $exempt_classes = array();
            }

            // Find all <img> tags in the content
            $pattern = '/<img(.*?)>/';
            preg_match_all($pattern, $content, $matches);

            // Loop through each <img> tag and add lazy-loading if the class is not exempt
            foreach ($matches[0] as $img_tag) {
                $replace_img_tag = $img_tag;

                // Check if the <img> tag has a loading attribute
                if (!preg_match('/loading=["\'](.*?)["\']/', $img_tag)) {
                    // Check if the <img> tag has a class attribute
                    if (preg_match('/class=["\'](.*?)["\']/', $img_tag, $class_matches)) {
                        $classes = explode(' ', $class_matches[1]);

                        // Check if any of the exempt classes are present
                        if (array_intersect($classes, $exempt_classes)) {
                            continue; // Skip lazy-loading for exempt classes
                        }
                    }

                    // Remove lazy-load for responsive img (considered responsive if possess the attribute "sizes")
                    if (isset($config_array['lazyload']['exclusions']['media_types']['responsive'])) {
                        if (preg_match('/sizes=/', $img_tag)) {
                            continue;
                        }
                    }

                    // Remove lazy-load for images whose source contains gravatar
                    if (isset($config_array['lazyload']['exclusions']['media_types']['gravatar'])) {
                        if (preg_match('/src=["\'](.*?gravatar\.com.*?)["\']/', $img_tag)) {
                            continue;
                        }
                    }

                    // Remove lazy-load for images whose source contains $url
                    if (isset($config_array['lazyload']['exclusions']['media_types']['img_iframe'])) {
                        foreach ($config_array['lazyload']['exclusions']['img_iframe'] as $url) {
                            if (preg_match('/src=["\'](.*?' . esc_url($url) . '.*?)["\']/', $img_tag)) {
                                continue;
                            }
                        }
                    }

                    // Remove lazy-load for high fetch priority images
                    if (preg_match('/fetchpriority="high"/', $img_tag)) {
                        continue;
                    }

                    // Add lazy-loading attribute to the <img> tag
                    $replace_img_tag = preg_replace('/<img/', '<img loading="lazy"', $replace_img_tag, 1);
                }

                // Replace the original <img> tag with the modified one
                $content = str_replace($img_tag, $replace_img_tag, $content);
            }

            return $content;
        }
    }

    // Force all videos and iframes to be/not be lazy loaded ; By default activated
    if ((isset($config_array['iframe_video_lazyload']['state']) && $config_array['iframe_video_lazyload']['state'] === "true")) {
        add_filter('the_content', 'lws_optimize_manage_media_iframe_video_lazyload_content');
        // Function to add lazy-loading to images
        function lws_optimize_manage_media_iframe_video_lazyload_content($content)
        {
            if (is_admin()) {
                return $content;
            }
            global $config_array;

            // Define the classes that exempt images from lazy-loading
            if (isset($config_array['lazyload']['exclusions']['css_classes'])) {
                $exempt_classes = $config_array['lazyload']['exclusions']['css_classes'];
            } else {
                $exempt_classes = array();
            }

            // Find all <iframe> tags in the content
            $pattern = '/<iframe(.*?)>/';
            preg_match_all($pattern, $content, $matches);

            // Loop through each <iframe> tag and add lazy-loading if the class is not exempt
            foreach ($matches[0] as $iframe_tag) {
                if (isset($config_array['lazyload']['exclusions']['media_types']['iframe'])) {
                    break;
                }

                $replace_iframe_tag = $iframe_tag;

                // Check if the <iframe> tag has a loading attribute
                if (!preg_match('/loading=["\'](.*?)["\']/', $iframe_tag)) {
                    // Check if the <iframe> tag has a class attribute
                    if (preg_match('/class=["\'](.*?)["\']/', $iframe_tag, $class_matches)) {
                        $classes = explode(' ', $class_matches[1]);

                        // Check if any of the exempt classes are present
                        if (array_intersect($classes, $exempt_classes)) {
                            continue; // Skip lazy-loading for exempt classes
                        }
                    }

                    // Remove lazy-load for images whose source contains $url
                    if (isset($config_array['lazyload']['exclusions']['media_types']['img_iframe'])) {
                        foreach ($config_array['lazyload']['exclusions']['img_iframe'] as $url) {
                            if (preg_match('/src=["\'](.*?' . esc_url($url) . '.*?)["\']/', $iframe_tag)) {
                                continue;
                            }
                        }
                    }

                    // Remove lazy-load for high fetch priority images
                    if (preg_match('/fetchpriority="high"/', $iframe_tag)) {
                        continue;
                    }

                    // Add lazy-loading attribute to the <iframe> tag
                    $replace_iframe_tag = preg_replace('/<iframe/', '<iframe loading="lazy"', $replace_iframe_tag, 1);
                }

                // Replace the original <iframe> tag with the modified one
                $content = str_replace($iframe_tag, $replace_iframe_tag, $content);
            }

            // Find all <video> tags in the content
            $pattern = '/<video(.*?)>/';
            preg_match_all($pattern, $content, $matches);

            // Loop through each <video> tag and add lazy-loading if the class is not exempt
            foreach ($matches[0] as $video_tag) {
                if (isset($config_array['lazyload']['exclusions']['media_types']['video'])) {
                    break;
                }

                $replace_video_tag = $video_tag;

                // Check if the <video> tag has a loading attribute
                if (!preg_match('/loading=["\'](.*?)["\']/', $video_tag)) {
                    // Check if the <video> tag has a class attribute
                    if (preg_match('/class=["\'](.*?)["\']/', $video_tag, $class_matches)) {
                        $classes = explode(' ', $class_matches[1]);

                        // Check if any of the exempt classes are present
                        if (array_intersect($classes, $exempt_classes)) {
                            continue; // Skip lazy-loading for exempt classes
                        }
                    }

                    // Remove lazy-load for images whose source contains $url
                    if (isset($config_array['lazyload']['exclusions']['media_types']['img_iframe'])) {
                        foreach ($config_array['lazyload']['exclusions']['img_iframe'] as $url) {
                            if (preg_match('/src=["\'](.*?' . esc_url($url) . '.*?)["\']/', $video_tag)) {
                                continue;
                            }
                        }
                    }

                    // Remove lazy-load for high fetch priority images
                    if (preg_match('/fetchpriority="high"/', $video_tag)) {
                        continue;
                    }

                    // Add lazy-loading attribute to the <video> tag
                    $replace_video_tag = preg_replace('/<video/', '<video loading="lazy"', $replace_video_tag, 1);
                }

                // Replace the original <video> tag with the modified one
                $content = str_replace($video_tag, $replace_video_tag, $content);
            }

            return $content;
        }
    }

    add_action('init', function () {
        if (!in_array('lwscache/lwscache.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if (!class_exists("LWSCache")) {
                require LWS_OP_DIR . 'classes/cache/class-lws-cache.php';
            }
            $config_array = get_option('lws_optimize_config_array', array(
                'image_lazyload' => array('state' => true),
                'bg_image_lazyload' => array('state' => true),
                'iframe_video_lazyload' => array('state' => true),
            ));

            if (isset($config_array['dynamic_cache']['state']) && $config_array['dynamic_cache']['state'] == "true" && (!isset($config_array['cloudflare']['tools']['dynamic_cache']) || $config_array['cloudflare']['tools']['dynamic_cache'] === false)) {
                if (!function_exists("run_lws_cache")) {
                    function run_lws_cache()
                    {
                        global $lws_cache;

                        $lws_cache = new LWSCache();
                        $lws_cache->run();

                        // Load WP-CLI command.
                        if (defined('WP_CLI') && WP_CLI) {

                            if (!class_exists("LWSCache_WP_CLI_Command")) {
                                require_once LWS_OP_DIR . 'classes/cache/class-lws-cache-wp-cli-command.php';
                            }
                            \WP_CLI::add_command('lws-cache', 'LWSCache_WP_CLI_Command');
                        }
                    }
                    run_lws_cache();
                }
            }
        }
    });

    add_action("wp_ajax_lwsop_dump_dynamic_cache", "lwsop_dump_dynamic_cache");
    function lwsop_dump_dynamic_cache()
    {
        global $config_array, $lws_cache;
        check_ajax_referer('lwsop_empty_d_cache_nonce', '_ajax_nonce');

        if (!class_exists("LWSCache_Admin")) {
            require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-lws-cache-admin.php';
        }
        if (!class_exists("Purger")) {
            require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-purger.php';
        }
        $lws_cache_admin = new LWSCache_Admin("LWSCache", "1.0");

        // Defines global variables.
        if (!empty($lws_cache_admin->options['cache_method']) && 'enable_redis' === $lws_cache_admin->options['cache_method']) {
            if (class_exists('Redis')) { // Use PHP5-Redis extension if installed.
                if (class_exists('PhpRedis_Purger')) {
                    require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-phpredis-purger.php';
                }
                $nginx_purger = new PhpRedis_Purger();
            } else {
                if (class_exists('Predis_Purger')) {
                    require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-predis-purger.php';
                }
                $nginx_purger = new Predis_Purger();
            }
        } elseif (
            isset($_SERVER['HTTP_X_CACHE_ENGINE_ENABLED']) && isset($_SERVER['HTTP_X_CACHE_ENGINE'])
            && $_SERVER['HTTP_X_CACHE_ENGINE_ENABLED'] == '1' && $_SERVER['HTTP_X_CACHE_ENGINE'] == 'varnish'
        ) {
            if (!class_exists("Varnish_Purger")) {
                require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-varnish-purger.php';
            }
            $nginx_purger = new Varnish_Purger();
        } else {
            if (!class_exists("FastCGI_Purger")) {
                require_once plugin_dir_path((__FILE__)) . 'classes/cache/class-fastcgi-purger.php';
            }
            $nginx_purger = new FastCGI_Purger();
        }

        $return = $nginx_purger->purge_all();

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $return), JSON_PRETTY_PRINT));
    }

    add_action("wp_ajax_lws_optimize_activate_cleaner", "lws_optimize_activate_cleaner");
    function lws_optimize_activate_cleaner()
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
}


/////////////////////
// LWSOptimize 3.0 //
/////////////////////
$GLOBALS['lws_optimize_cache_timestamps'] =
    [
        'lws_daily' => [86400, __('Once a day', 'lws-optimize')],
        'lws_weekly' => [604800, __('Once a week', 'lws-optimize')],
        'lws_monthly' => [2629743, __('Once a month', 'lws-optimize')],
        'lws_thrice_monthly' => [7889232, __('Once every 3 months', 'lws-optimize')],
        'lws_biyearly' => [15778463, __('Once every 6 months', 'lws-optimize')],
        'lws_yearly' => [31556926, __('Once a year', 'lws-optimize')],
        'lws_two_years' => [63113852, __('Once every 2 years', 'lws-optimize')],
        'lws_never' => [0, __('Never expire', 'lws-optimize')],
    ];

/**
 * Add every special cron_schedules from the array above
 */
add_filter('cron_schedules', function () {
    foreach ($GLOBALS['lws_optimize_cache_timestamps'] as $code => $schedule) {
        $schedules[$code] = array(
            'interval' => $schedule[0],
            'display' => $schedule[1]
        );
    }

    $schedules['lws_minute'] = array(
        'interval' => 300,
        'display' => "Once every 5 minutes"
    );

    return $schedules;
});

include_once("classes/LwsOptimize.php");
$GLOBALS['lws_optimize'] = $lwsop = new LwsOptimize();
