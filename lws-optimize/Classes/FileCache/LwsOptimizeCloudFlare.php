<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeCloudFlare {
    public function activate_cloudflare_integration() {
        add_action("wp_ajax_lws_optimize_check_cloudflare_key", [$this, "lws_optimize_check_cf_key"]);
        add_action("wp_ajax_lws_optimize_complete_cloudflare_integration", [$this, "lws_optimize_complete_cloudflare_integration"]);
        add_action("wp_ajax_lws_optimize_cloudflare_deactivation", [$this, "lws_optimize_cloudflare_deactivation"]);
    }

    // Clear CloudFlare cache on-demand
    public function lws_optimize_clear_cloudflare_cache(string|null $cache_type = null, string|null $url = null)
    {
        switch ($cache_type) {
            case 'full':
                $cache_type = "full";
                break;
            case 'partial':
                $cache_type = "partial";
                break;
            default:
                $cache_type = 'full';
                break;
        }

        // Get the Token Key and the Zone ID to change the CF cache
        $options = get_option('lws_optimize_config_array', []);
        $token_key = $options['cloudflare']['apiToken'] ?? null;
        $zone_id = $options['cloudflare']['zone_id'] ?? null;

        if ($options['cloudflare']['state'] !== "true") {
            return -1;
            // wp_die(json_encode(array('code' => "CLOUDFLARE_NOT_ACTIVE", 'data' => $options), JSON_PRETTY_PRINT));
        }

        $result = false;

        // If removing the entire cache for the domain
        if ($cache_type == 'full') {
            $result = wp_remote_request(
                "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
                [
                    'method' => 'POST',
                    'timeout' => 45,
                    'sslverify' => false,
                    'headers' => [
                        "Authorization" => "Bearer " . $token_key,
                        "Content-Type" => "application/json"
                    ],
                    'body' => json_encode(['purge_everything' => true]
                    )
                ]
            );
        }
        else {
            if ($url === null) {
                return -1;
                // wp_die(json_encode(array('code' => "NO_URL"), JSON_PRETTY_PRINT));
            }

            $url = esc_url($url);
            $result = wp_remote_request(
                "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache",
                [
                    'method' => 'POST',
                    'timeout' => 45,
                    'sslverify' => false,
                    'headers' => [
                        "Authorization" => "Bearer " . $token_key,
                        "Content-Type" => "application/json"
                    ],
                    'body' => json_encode(['files' => [
                        'https://' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH)
                    ]])
                ]
            );
        }

        // Failed to cURL the API
        if (is_wp_error($result)) {
            return -1;
            // wp_die(json_encode(['code' => "ERROR_CURL", 'data' => $result], JSON_PRETTY_PRINT));
        }

        // Get the response and decode the JSON
        $body = wp_remote_retrieve_body($result);
        $result = json_decode($body, true);
        // Error during decoding, it was (probably) not a JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return -1;
            // wp_die(json_encode(['code' => "ERROR_DECODE", 'data' => $body], JSON_PRETTY_PRINT));
        }

        // Check if the request was successful
        $success = $result['success'] ?? false;

        // If the request was not successful, we cannot proceed
        if (!$success) {
            return -1;
            // wp_die(json_encode(array('code' => "REQUEST_FAILED", 'data' => $result), JSON_PRETTY_PRINT));
        }

        // Cache cleared, we can continue
        // wp_die(json_encode(array('code' => "SUCCESS", 'data' => $result), JSON_PRETTY_PRINT));
        return 0;
    }

    public function lws_optimize_change_cloudflare_ttl(int $ttl) {
        $options = get_option('lws_optimize_config_array', []);
        $token_key = $options['cloudflare']['api_token'] ?? null;
        $zone_id = $options['cloudflare']['zone_id'] ?? null;

        if ($options['cloudflare']['state'] !== "true") {
            wp_die(json_encode(array('code' => "CLOUDFLARE_NOT_ACTIVE", 'data' => $options), JSON_PRETTY_PRINT));
        }

        // CloudFlare allowed values: 0, 30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600, 72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 2678400
        switch ($ttl) {
            case 'lws_daily':
                $ttl = "86400";
                break;
            case 'lws_weekly':
                $ttl = "691200";
                break;
            case 'lws_monthly':
                $ttl = "2678400";
                break;
            case 'lws_thrice_monthly':
                $ttl = "5356800";
                break;
            case 'lws_biyearly':
                $ttl = "16070400";
                break;
            case 'lws_yearly':
                $ttl = "31536000";
                break;
            case 'lws_two_years':
            case 'lws_never':
                $ttl = "31536000";
                break;
            default:
                $ttl = "31536000";
                break;
        }


        $result = wp_remote_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/browser_cache_ttl",
            [
            'method' => 'PATCH',
            'timeout' => 45,
            'sslverify' => false,
            'headers' => [
                "Authorization" => "Bearer " . $token_key,
                "Content-Type" => "application/json"
            ],
            'body' => json_encode(
                [
                'value' => intval($ttl)
                ]
            )
            ]
        );

        if (is_wp_error($result)) {
            wp_die(json_encode(['code' => "ERROR_CURL", 'data' => $result], JSON_PRETTY_PRINT));
        }

        $body = wp_remote_retrieve_body($result);
        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(['code' => "ERROR_DECODE", 'data' => $body], JSON_PRETTY_PRINT));
        }

        if (!($result['success'] ?? false)) {
            wp_die(json_encode(array('code' => "REQUEST_FAILED", 'data' => $result), JSON_PRETTY_PRINT));
        }

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $result), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_check_cf_key() {
        check_ajax_referer('lwsop_check_cloudflare_key_nonce', '_ajax_nonce');
        $token_key = $_POST['key'] ?? null;

        // Get the Token Key, necessary to check the Cloudflare API
        if ($token_key === null) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $token_key = sanitize_text_field($token_key);

        // Verify the token validity
        $response = wp_remote_get(
            "https://api.cloudflare.com/client/v4/user/tokens/verify",
            [
                'timeout' => 45,
                'headers' => [
                    "Authorization" => "Bearer " . $token_key,
                    "Content-Type" => "application/json"
                ]
            ]
        );

        // Failed to cURL the API
        if (is_wp_error($response)) {
            wp_die(json_encode(['code' => "ERROR_CURL", 'data' => $response], JSON_PRETTY_PRINT));
        }

        // Get the response and decode the JSON
        $body = wp_remote_retrieve_body($response);
        $response = json_decode($body, true);

        // Error during decoding, it was (probably) not a JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(['code' => "ERROR_DECODE", 'data' => $body], JSON_PRETTY_PRINT));
        }

        // Get the Token status
        $status = $response['result']['status'] ?? 'inactive';

        // Cannot proceed if the token is not active, we do not have access to the API
        if ($status == "inactive") {
            wp_die(json_encode(array('code' => "INACTIVE_TOKEN", 'data' => $response), JSON_PRETTY_PRINT));
        }

        // Use the token to get every zones managed by the account
        // Which we filter by the current domain
        $zones_response = wp_remote_get(
            "https://api.cloudflare.com/client/v4/zones?per_page=50&name=" . $_SERVER['SERVER_NAME'],
            [
                'timeout' => 45,
                'sslverify' => false,
                'headers' => [
                    "Authorization" => "Bearer " . $token_key,
                    "Content-Type" => "application/json"
                ]
            ]
        );


        // Failed to cURL the API
        if (is_wp_error($zones_response)) {
            wp_die(json_encode(['code' => "ERROR_CURL_ZONES", 'data' => $response], JSON_PRETTY_PRINT));
        }

        // Get the response and decode the JSON
        $body = wp_remote_retrieve_body($zones_response);
        $zones_response = json_decode($body, true);

        // Error during decoding, it was (probably) not a JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(['code' => "ERROR_DECODE_ZONES", 'data' => $body], JSON_PRETTY_PRINT));
        }

        // Check if the request was successful
        $success = $zones_response['success'] ?? false;

        // If the request was not successful, we cannot proceed
        if (!$success) {
            wp_die(json_encode(array('code' => "REQUEST_ZONE_FAILED", 'data' => $zones_response), JSON_PRETTY_PRINT));
        }

        // Prepare to get all useful information about the zone
        $zone_infos = [];
        foreach ($zones_response['result'] as $zone) {
            if ($zone['name'] == $_SERVER['SERVER_NAME']) {
                $zone_infos = [
                    'api_token' => $token_key,
                    'name' => $zone['name'],
                    'id' => $zone['id'],
                    'account' => $zone['account']['id'],
                    'account_name' => $zone['account']['name'],
                    'status' => $zone['status'],
                    'name_servers' => $zone['name_servers'],
                    'original_name_servers' => $zone['original_name_servers'],
                    'type' => $zone['type'],
                ];
                break;
            }
        }

        // Failed to get a zone, either an error or the zone does not exist
        if (empty($zone_infos)) {
            wp_die(json_encode(array('code' => "NO_ZONE", 'data' => $zones_response), JSON_PRETTY_PRINT));
        }

        // Zone fetched, we can continue
        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $zone_infos), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_complete_cloudflare_integration() {
        check_ajax_referer('lwsop_complete_cf_integration_nonce', '_ajax_nonce');
        $zone = $_POST['zone'] ?? [];

        if (!is_array($zone)) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $token_key = '';
        $zone_id = '';

        foreach ($zone as $key => $value) {
            if ($key === 'apiToken') {
                $token_key = sanitize_text_field($value);
            } elseif ($key === 'id') {
                $zone_id = sanitize_text_field($value);
            }
        }

        if ($token_key === null || $zone_id === null) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $_POST), JSON_PRETTY_PRINT));
        }

        $options = get_option('lws_optimize_config_array', []);

        // Get the current filecache timer before expiration
        $timer = $options['filebased_cache']['timer'] ?? 'lws_yearly';
        // CloudFlare allowed values: 0, 30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600, 72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 2678400, 5356800, 16070400, 31536000
        switch ($timer) {
            case 'lws_daily':
                $cdn_date = "86400";
                break;
            case 'lws_weekly':
                $cdn_date = "691200";
                break;
            case 'lws_monthly':
                $cdn_date = "2678400";
                break;
            case 'lws_thrice_monthly':
                $cdn_date = "5356800";
                break;
            case 'lws_biyearly':
                $cdn_date = "16070400";
                break;
            case 'lws_yearly':
                $cdn_date = "31536000";
                break;
            case 'lws_two_years':
            case 'lws_never':
                $cdn_date = "31536000";
                break;
            default:
                $cdn_date = "31536000";
                break;
        }

        $options['cloudflare'] = [
            'state' => "true",
            'api_token' => $token_key,
            'zone_id' => $zone_id,
            'lifespan' => $cdn_date,
            'deactivate_tools' => true,
        ];

        $set_ttl_cache = wp_remote_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/browser_cache_ttl",
            [
                'method' => 'PATCH',
                'timeout' => 45,
                'sslverify' => false,
                'headers' => [
                    "Authorization" => "Bearer " . $token_key,
                    "Content-Type" => "application/json"
                ],
                'body' => json_encode(
                    [
                    'value' => intval($cdn_date)
                    ]
                )
            ]
        );

        // Failed to cURL the API
        if (is_wp_error($set_ttl_cache)) {
            wp_die(json_encode(['code' => "ERROR_CURL_TTL", 'data' => $set_ttl_cache], JSON_PRETTY_PRINT));
        }

        // Get the response and decode the JSON
        $body = wp_remote_retrieve_body($set_ttl_cache);
        $set_ttl_cache = json_decode($body, true);

        // Error during decoding, it was (probably) not a JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(json_encode(['code' => "ERROR_DECODE_TTL", 'data' => $body], JSON_PRETTY_PRINT));
        }

        // Check if the request was successful
        $success = $set_ttl_cache['success'] ?? false;

        // If the request was not successful, we cannot proceed
        if (!$success) {
            wp_die(json_encode(array('code' => "REQUEST_CF_FAILED", 'data' => $set_ttl_cache), JSON_PRETTY_PRINT));
        }

        update_option('lws_optimize_config_array', $options);
        $GLOBALS['lws_optimize']->optimize_options = $options;

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $options['cloudflare']), JSON_PRETTY_PRINT));

    }

    public function lws_optimize_cloudflare_deactivation() {
        check_ajax_referer('lwsop_complete_cf_deactivation_nonce', '_ajax_nonce');

        // Get the Token Key and the Zone ID to change the CF cache
        $options = get_option('lws_optimize_config_array', []);
        $token_key = $options['cloudflare']['api_token'] ?? null;
        $zone_id = $options['cloudflare']['zone_id'] ?? null;

        // Set the integration to false and update
        $options['cloudflare']['state'] = "false";
        update_option('lws_optimize_config_array', $options);
        $GLOBALS['lws_optimize']->optimize_options = $options;

        // Additionnaly reset the cache to its default value (whether it worked or not)
        $set_ttl_cache = wp_remote_request(
            "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/browser_cache_ttl",
            [
                'method' => 'PATCH',
                'timeout' => 45,
                'sslverify' => false,
                'headers' => [
                    "Authorization" => "Bearer " . $token_key,
                    "Content-Type" => "application/json"
                ],
                'body' => json_encode(
                    [
                    'value' => '14400'
                    ]
                )
            ]
        );

        wp_die(json_encode(array('code' => "SUCCESS"), JSON_PRETTY_PRINT));
    }
}
