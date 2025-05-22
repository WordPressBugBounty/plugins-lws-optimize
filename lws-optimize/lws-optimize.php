<?php
require_once __DIR__ . "/vendor/autoload.php";

use Lws\Classes\LwsOptimize;
use Lws\Classes\LwsOptimizeWpCli;

/**
 * Plugin Name:       LWS Optimize
 * Plugin URI:        https://www.lws.fr/
 * Description:       Reach better speed and performances with Optimize! Minification, Combination, Media convertion... Everything you need for a better website
 * Version:           3.3.3.1
 * Author:            LWS
 * Author URI:        https://www.lws.fr
 * Tested up to:      6.8
 * Domain Path:       /languages
 *
 * @link    https://www.lws.fr/
 * @since   1.0
 * @package lws-optimize
 *
 * This plugin is greatly based on a fork of WPFastestCache (https://wordpress.org/plugins/wp-fastest-cache/) by Emre Vona,
 * specifically the JS/CSS optimisation (minify, combine, ...) and the filebased-cache verifications (when not to cache the current page)
 */

if (!defined('ABSPATH')) {
    exit; //Exit if accessed directly
}

// Actions to execute when the plugin is activated / deleted / upgraded
register_activation_hook(__FILE__, 'lws_optimize_activation');
register_deactivation_hook(__FILE__, 'lws_optimize_deactivation');
register_uninstall_hook(__FILE__, 'lws_optimize_deletion');
// add_action('upgrader_process_complete', 'lws_optimize_upgrading', 10, 2);

function lws_optimize_check_update() {
    $ancienne_version = get_option('lwsop_plugin_version', 0);
    $nouvelle_version = '3.2.2.1'; // Remplacez par la version actuelle du plugin.

    if ($ancienne_version !== $nouvelle_version) {
        add_option( 'wp_lwsoptimize_post_update', 1);

        // Mettre à jour la version en base.
        update_option('lwsop_plugin_version', $nouvelle_version);
    }
}
add_action('plugins_loaded', 'lws_optimize_check_update');


// Actions to do when the plugin is activated
function lws_optimize_activation()
{
    // Unused
    set_transient('lwsop_remind_me', 691200);
    delete_option('lws_optimize_preload_is_ongoing');

    $optimize_options = get_option('lws_optimize_config_array', []);

    $GLOBALS['lws_optimize'] ->lws_optimize_set_cache_htaccess();
    $GLOBALS['lws_optimize'] ->lws_optimize_reset_header_htaccess();

    //@deactivate_plugins("lwscache/lwscache.php");


    // Deactivate the preloading on plugin activation to prevent issues
    if (isset($optimize_options['filebased_cache']) && $optimize_options['filebased_cache']['state'] == "true") {
        if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
        }

        $optimize_options['filebased_cache']['preload'] = "false";
        $optimize_options['filebased_cache']['preload_done'] =  0;
        $optimize_options['filebased_cache']['preload_ongoing'] = "true";

        update_option('lws_optimize_config_array', $optimize_options);

        //wp_schedule_event(time() + 3, "lws_minute", "lws_optimize_start_filebased_preload");
    }

    wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
    wp_unschedule_event(wp_next_scheduled('lwsop_revertOptimization'), 'lwsop_revertOptimization');

    if (isset($optimize_options['maintenance_db']) && $optimize_options['maintenance_db']['state'] == "true") {
        if (wp_next_scheduled("lws_optimize_maintenance_db_weekly")) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
        }
        wp_schedule_event(time() + 604800, 'weekly', "lws_optimize_maintenance_db_weekly");
    }
}

// Actions to do when the plugin is deactivated
function lws_optimize_deactivation()
{
    // Deactivate the cron of the preloading and convertion
    wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
    wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
    // Deactivate cron for the maintenance
    wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
    // Deactivate cron that revert the images
    wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");

    // Remove .htaccess content
    $htaccess_file = ABSPATH . '/.htaccess';

    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);

        // Remove LWS OPTIMIZE - CACHING section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - CACHING.*?#END LWS OPTIMIZE - CACHING\s*/s', "\n", $htaccess_content);

        // Remove LWS OPTIMIZE - EXPIRE HEADER section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - EXPIRE HEADER.*?#END LWS OPTIMIZE - EXPIRE HEADER\s*/s', "\n", $htaccess_content);

        // Remove LWS OPTIMIZE - GZIP COMPRESSION section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - GZIP COMPRESSION.*?#END LWS OPTIMIZE - GZIP COMPRESSION\s*/s', "\n", $htaccess_content);

        // Write the modified content back to the file
        file_put_contents($htaccess_file, $htaccess_content);
    }

    delete_option('lws_optimize_preload_is_ongoing');

    // Unused
    delete_transient('lwsop_remind_me');
}

// Actions to do when the plugin is to be deleted
function lws_optimize_deletion()
{
    // Remove the cache folder
    apply_filters("lws_optimize_clear_filebased_cache", false);

    // Deactivate the cron of the preloading and convertion
    wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
    wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
    // Deactivate cron for the maintenance
    wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
    // Deactivate cron that revert the images
    wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");

    // Remove .htaccess content
    $htaccess_file = ABSPATH . '/.htaccess';

    if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
        $htaccess_content = file_get_contents($htaccess_file);

        // Remove LWS OPTIMIZE - CACHING section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - CACHING.*?#END LWS OPTIMIZE - CACHING\s*/s', "\n", $htaccess_content);

        // Remove LWS OPTIMIZE - EXPIRE HEADER section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - EXPIRE HEADER.*?#END LWS OPTIMIZE - EXPIRE HEADER\s*/s', "\n", $htaccess_content);

        // Remove LWS OPTIMIZE - GZIP COMPRESSION section
        $htaccess_content = preg_replace('/\s*#LWS OPTIMIZE - GZIP COMPRESSION.*?#END LWS OPTIMIZE - GZIP COMPRESSION\s*/s', "\n", $htaccess_content);

        // Write the modified content back to the file
        file_put_contents($htaccess_file, $htaccess_content);
    }

    // Remove all options on delete
    if (get_option('lws_optimize_config_array', null) !== null) {
        delete_option('lws_optimize_config_array');
    }

    delete_option('lws_optimize_preload_is_ongoing');

    // Unused
    delete_transient('lwsop_remind_me');
}

// Actions to do when the plugin is updated
function lws_optimize_upgrading($upgrader_object, $options)
{
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $plugin) {
            // If the plugin getting updated is LWS Optimize
            if ($plugin == plugin_basename(__FILE__)) {
                add_option( 'wp_lwsoptimize_post_update', 1);
            }
        }
    }
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
    function str_starts_with($haystack, $needle)
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
    }
}

//AJAX DL Plugin//
add_action("wp_ajax_lws_op_downloadPlugin", "wp_ajax_install_plugin");
//

//AJAX Activate Plugin//
add_action("wp_ajax_lws_op_activatePlugin", function()
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
            default:
                break;
        }
    }
    wp_die();
});

$deactivated = get_option('lws_optimize_deactivate_temporarily', false);
if ($deactivated) {
    if (time() > $deactivated) {
        delete_option('lws_optimize_deactivate_temporarily');
    }
}

$GLOBALS['lws_optimize'] = $lwsop = new LwsOptimize();

// Register WP-CLI commands once the plugin is loaded
if (defined('WP_CLI') && WP_CLI) {
    LwsOptimizeWpCli::register_commands();
}
