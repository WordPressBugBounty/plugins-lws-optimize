<?php

namespace Lws\Classes\Integrations;

/**
 * Cloudflare Automatic Platform Optimization (APO) integration.
 *
 * APO caches the full HTML at Cloudflare edge, bringing TTFB to ~50ms worldwide.
 * It is normally a paid CF add-on, but free Cloudflare accounts can replicate
 * 80% of its behaviour using:
 *   1. A Cache Rule that caches HTML responses matching the configured cookie/
 *      query-string exclusions.
 *   2. Cache-purge calls from WordPress when content changes (this class).
 *
 * What this class does:
 * - Adds a small server header (Cache-Tag) to every HTML response so CF can
 *   purge by tag on save_post.
 * - Listens to save_post / comment_post / cache-purge events and sends a
 *   purge_cache call to Cloudflare API (https://api.cloudflare.com/client/v4).
 * - Provides a WP-CLI / admin button to install the recommended Cache Rule on
 *   the zone, given a CF API token (Zone.Cache Rules:Edit + Zone.Cache Purge).
 *
 * Configuration:
 *   $opts['cloudflare_apo']['state']    = "true"|"false"
 *   $opts['cloudflare_apo']['zone_id']  = "abcd…"
 *   $opts['cloudflare_apo']['api_token']= "…"  (scoped: Cache Purge + Cache Rules)
 *
 * Bypass cookies used by the recommended Cache Rule (filterable):
 *   wp-, wordpress_logged_in_, woocommerce_*, edd_*, comment_author_
 */
class LwsOptimizeCloudflareAPO
{
    const CF_API_BASE = 'https://api.cloudflare.com/client/v4';

    public static function startActions()
    {
        // Tag responses so CF can purge by tag
        add_action('send_headers', [__CLASS__, 'send_cache_tag_header']);

        // Purge hooks (mirror the file-cache autopurge so CF stays in sync)
        add_action('save_post', [__CLASS__, 'purge_on_post_change'], 20, 1);
        add_action('comment_post', [__CLASS__, 'purge_on_post_change'], 20, 1);
        add_action('lws_optimize_clear_filebased_cache', [__CLASS__, 'purge_url_from_filter'], 20, 1);

        // Admin AJAX to install Cache Rule
        add_action('wp_ajax_lwsop_cloudflare_install_cache_rule', [__CLASS__, 'ajax_install_cache_rule']);
    }

    /**
     * Sends a Cache-Tag header on every HTML response. Cloudflare uses these to
     * group entries for tag-based purging (paid Enterprise feature) but the
     * header is harmless on other plans.
     *
     * Tag format:
     *   lwsop-host:{HOST}  — purge all pages of the site
     *   lwsop-post:{ID}    — purge a single post by id (sent in singular contexts)
     */
    public static function send_cache_tag_header()
    {
        if (is_admin() || headers_sent()) {
            return;
        }
        $host = parse_url(home_url(), PHP_URL_HOST);
        $tags = ['lwsop-host:' . $host];
        if (function_exists('is_singular') && is_singular() && function_exists('get_the_ID')) {
            $id = get_the_ID();
            if ($id) {
                $tags[] = 'lwsop-post:' . $id;
            }
        }
        header('Cache-Tag: ' . implode(',', $tags));
        // Hint to CF Cache Rule: tell it this is cacheable HTML for anonymous traffic.
        // The Rule itself decides whether to honour it.
        header('X-LWSOP-Cacheable: yes');
    }

    public static function purge_on_post_change($post_id)
    {
        $url = get_permalink($post_id);
        if ($url) {
            self::purge_urls([$url, home_url('/')]);
        }
    }

    public static function purge_url_from_filter($url)
    {
        if (is_string($url) && $url !== '') {
            self::purge_urls([$url]);
        }
    }

    /**
     * Sends a Cloudflare purge_cache request for the given URLs (max 30 per call).
     * Also synchronises the edge cache (Varnish / LiteSpeed / LWSCache) for each
     * URL so that the LWS hosting stack stays consistent with the CF edge.
     * Returns true on success, false on failure (errors logged).
     */
    public static function purge_urls(array $urls)
    {
        $cfg = self::get_config();
        if (!$cfg) {
            return false;
        }
        $urls = array_values(array_unique(array_filter($urls)));
        if (empty($urls)) {
            return false;
        }
        // VARNISH/LITESPEED/LWSCACHE SYNC: purge edge cache per URL so the next
        // CF MISS reaches the origin in a clean state (no stale Varnish hit).
        if (isset($GLOBALS['lws_optimize'])
            && method_exists($GLOBALS['lws_optimize'], 'lwsop_purge_varnish_url')
        ) {
            foreach ($urls as $u) {
                $GLOBALS['lws_optimize']->lwsop_purge_varnish_url($u);
            }
        }
        // CF API caps at 30 URLs per call
        $chunks = array_chunk($urls, 30);
        $any_failed = false;
        foreach ($chunks as $batch) {
            $resp = wp_remote_post(
                self::CF_API_BASE . '/zones/' . rawurlencode($cfg['zone_id']) . '/purge_cache',
                [
                    'timeout' => 10,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $cfg['api_token'],
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => wp_json_encode(['files' => $batch]),
                ]
            );
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
                $any_failed = true;
                if (isset($GLOBALS['lws_optimize']) && !empty($GLOBALS['lws_optimize']->log_file)) {
                    $msg = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                    @file_put_contents(
                        $GLOBALS['lws_optimize']->log_file,
                        '[' . gmdate('Y-m-d H:i:s') . "] CF purge failed: $msg\n",
                        FILE_APPEND
                    );
                }
            }
        }
        return !$any_failed;
    }

    /**
     * AJAX handler to install the recommended Cache Rule on the CF zone.
     * Caps protected by the global capability gate in LwsOptimizeManageAdmin.
     *
     * Rule expression (CF Cache Rules language):
     *   (http.host eq "example.com" and not (http.request.uri.path matches "^/(wp-admin|wp-login)") and
     *    not (any(http.cookie[*] in {"wp-" "wordpress_logged_in_" "woocommerce_" "edd_" "comment_author_"})))
     *
     * Action: cache + edge_ttl 8h + bypass on no-cache request.
     */
    public static function ajax_install_cache_rule()
    {
        check_ajax_referer('lwsop_cf_install', '_ajax_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['code' => 'FORBIDDEN'], 403);
        }
        $cfg = self::get_config();
        if (!$cfg) {
            wp_send_json_error(['code' => 'NO_CONFIG', 'message' => 'Missing zone_id or api_token']);
        }
        $host = parse_url(home_url(), PHP_URL_HOST);
        $bypass_cookies = apply_filters('lwsop_cf_bypass_cookies', [
            'wp-', 'wordpress_logged_in_', 'woocommerce_', 'edd_', 'comment_author_',
        ]);
        $cookies_expr = implode('" "', $bypass_cookies);
        $expression = sprintf(
            '(http.host eq "%s" and not (http.request.uri.path matches "^/(wp-admin|wp-login)") and not (any(http.cookie[*] in {"%s"})))',
            $host,
            $cookies_expr
        );
        $body = [
            'rules' => [[
                'expression' => $expression,
                'action'     => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 28800], // 8h
                    'browser_ttl' => ['mode' => 'respect_origin'],
                ],
                'description' => 'LWS Optimize APO — cache HTML for anonymous traffic',
                'enabled'     => true,
            ]],
        ];
        $resp = wp_remote_request(
            self::CF_API_BASE . '/zones/' . rawurlencode($cfg['zone_id']) . '/rulesets/phases/http_request_cache_settings/entrypoint',
            [
                'method'  => 'PUT',
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . $cfg['api_token'],
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );
        if (is_wp_error($resp)) {
            wp_send_json_error(['code' => 'WP_ERROR', 'message' => $resp->get_error_message()]);
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            wp_send_json_error([
                'code' => 'CF_API_FAIL',
                'http' => $code,
                'body' => wp_remote_retrieve_body($resp),
            ]);
        }
        wp_send_json_success(['code' => 'INSTALLED']);
    }

    private static function get_config()
    {
        $opts = get_option('lws_optimize_config_array', []);
        $cf   = $opts['cloudflare_apo'] ?? [];
        if (empty($cf['state']) || $cf['state'] !== 'true') {
            return null;
        }
        if (empty($cf['zone_id']) || empty($cf['api_token'])) {
            return null;
        }
        return [
            'zone_id'   => sanitize_text_field($cf['zone_id']),
            'api_token' => sanitize_text_field($cf['api_token']),
        ];
    }
}
