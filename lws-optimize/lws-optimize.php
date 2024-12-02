<?php
require_once __DIR__ . "/vendor/autoload.php";

use Lws\Classes\LwsOptimize;


/**
 * Plugin Name:       LWS Optimize
 * Plugin URI:        https://www.lws.fr/
 * Description:       Reach better speed and performances with Optimize! Minification, Combination, Media convertion... Everything you need for a better website
 * Version:           3.2.1.4
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

// Actions to execute when the plugin is activated / deleted / upgraded
register_activation_hook(__FILE__, 'lws_optimize_activation');
register_deactivation_hook(__FILE__, 'lws_optimize_deactivation');
register_uninstall_hook(__FILE__, 'lws_optimize_deletion');
add_action('upgrader_process_complete', 'lws_optimize_upgrading', 10, 2);


// Actions to do when the plugin is activated
function lws_optimize_activation()
{
    // Remove the cache folder
    apply_filters("lws_optimize_clear_filebased_cache", false);
    // Unused
    set_transient('lwsop_remind_me', 691200);

    $optimize_options = get_option('lws_optimize_config_array', []);
    if (isset($optimize_options['filebased_cache']) && $optimize_options['filebased_cache']['state'] == "true") {
        if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
        }

        $optimize_options['filebased_cache']['preload'] = "true";
        $optimize_options['filebased_cache']['preload_done'] =  0;
        $optimize_options['filebased_cache']['preload_ongoing'] = "true";

        $sitemap = get_sitemap_url("index");
        $headers = get_headers($sitemap);
        if (substr($headers[0], 9, 3) == 404) {
            $sitemap = home_url('/sitemap_index.xml');
        }

        function fetch_urls_amount($url, $data)
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
                        $data = fetch_urls_amount((string)$loc, $data);
                    }
                }
            }

            return $data;
        }

        $urls = fetch_urls_amount($sitemap, []);

        $optimize_options['filebased_cache']['preload_quantity'] = count($urls);
        update_option('lws_optimize_config_array', $optimize_options);

        wp_schedule_event(time() + 3, "lws_minute", "lws_optimize_start_filebased_preload");
    }

    $media_convertion = get_option('lws_optimize_all_media_convertion', ['ongoing' => false]);
    if (isset($media_convertion['ongoing']) && $media_convertion['ongoing']) {
        if (wp_next_scheduled("lws_optimize_convert_media_cron")) {
            wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
        }
        wp_schedule_event(time(), "lws_three_minutes", "lws_optimize_convert_media_cron");
    }

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
    // Remove the cache folder
    apply_filters("lws_optimize_clear_filebased_cache", false);
    // Deactivate the cron of the preloading and convertion
    wp_unschedule_event(wp_next_scheduled('lws_optimize_start_filebased_preload'), 'lws_optimize_start_filebased_preload');
    wp_unschedule_event(wp_next_scheduled('lws_optimize_convert_media_cron'), 'lws_optimize_convert_media_cron');
    // Deactivate cron for the maintenance
    wp_unschedule_event(wp_next_scheduled('lws_optimize_maintenance_db_weekly'), 'lws_optimize_maintenance_db_weekly');
    // Deactivate cron that revert the images
    wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");


    // Unused
    delete_transient('lwsop_remind_me');
}

// Actions to do when the plugin is to be deleted
function lws_optimize_deletion()
{
    // Remove the cache folder
    apply_filters("lws_optimize_clear_filebased_cache", false);

    // Remove all options on delete
    if (get_option('lws_optimize_config_array', null) !== null) {
        delete_option('lws_optimize_config_array');
    }

    // Unused
    delete_transient('lwsop_remind_me');
}

// Actions to do when the plugin is updated
function lws_optimize_upgrading($upgrader_object, $options)
{
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $each_plugin) {
            // If the plugin getting updated is LWS Optimize
            if ($each_plugin == plugin_basename(__FILE__)) {
                global $wpdb;

                // Force deactivate memcached for everyone
                $options = get_option('lws_optimize_config_array', []);
                $options['memcached']['state'] = false;
                update_option('lws_optimize_config_array', $options);

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
