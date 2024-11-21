<?php
require_once __DIR__ . "/vendor/autoload.php";

use Lws\Classes\LwsOptimize;


/**
 * Plugin Name:       LWS Optimize
 * Plugin URI:        https://www.lws.fr/
 * Description:       Reach better speed and performances with Optimize! Minification, Combination, Media convertion... Everything you need for a better website
 * Version:           3.2.1.2
 * Author:            LWS
 * Author URI:        https://www.lws.fr
 * Tested up to:      6.7
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

$GLOBALS['lws_optimize'] = $lwsop = new LwsOptimize();
