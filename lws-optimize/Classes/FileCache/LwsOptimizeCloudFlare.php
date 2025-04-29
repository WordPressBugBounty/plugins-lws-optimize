<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeCloudFlare {
    public function activate_cloudflare_integration() {
        add_action("wp_ajax_lws_optimize_check_cloudflare_key", [$this, "lws_optimize_check_cf_key"]);
        add_action("wp_ajax_lws_optimize_cloudflare_tools_deactivation", [$this, "lws_optimize_cf_tools_deactivation"]);
        add_action("wp_ajax_lws_optimize_cloudflare_cache_duration", [$this, "lws_optimize_cf_cache_duration"]);
        add_action("wp_ajax_lws_optimize_cloudflare_finish_configuration", [$this, "lws_optimize_cf_finish_config"]);
        add_action("wp_ajax_lws_optimize_deactivate_cloudflare_integration", [$this, "lws_optimize_deactivate_cf_inte"]);
    }

    // $cache_type : purge / part_purge ; $url : ["url", "url"]
    // Clear CloudFlare cache on-demand
    public function lws_optimize_clear_cloudflare_cache(string $cache_type, array $url)
    {
        $purge_type = null;
        if ($cache_type === "purge") {
            $purge_type = "full_purge";
        } elseif ($cache_type === "part_purge") {
            $purge_type = "part_purge";
        } else {
            $purge_type = null;
        }

        $config_array = $GLOBALS['lws_optimize']->optimize_options;

        $zone_id = $config_array['cloudflare']['zone_id'] ?? null;
        $api_token = $config_array['cloudflare']['api'] ?? null;

        if ($zone_id === null || $api_token === null || $purge_type === null || $url === null || !is_array($url)) {
            return json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT);
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
            error_log(json_encode(array('code' => "UNDEFINED_PTYPE", 'data' => $purge_type), JSON_PRETTY_PRINT));
            return json_encode(array('code' => "UNDEFINED_PTYPE", 'data' => $purge_type), JSON_PRETTY_PRINT);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT));
            return json_encode(array('code' => "ERROR_DECODE", 'data' => $response), JSON_PRETTY_PRINT);
        }

        if ($response['success']) {
            error_log(json_encode(array('code' => "SUCCESS", 'data' => $response), JSON_PRETTY_PRINT));
            return json_encode(array('code' => "SUCCESS", 'data' => $response), JSON_PRETTY_PRINT);
        } else {
            error_log(json_encode(array('code' => "FAIL_PURGE", 'data' => $response), JSON_PRETTY_PRINT));
            return json_encode(array('code' => "FAIL_PURGE", 'data' => $response), JSON_PRETTY_PRINT);
        }
    }

    public function lws_optimize_check_cf_key() {
        check_ajax_referer('lwsop_check_cloudflare_key_nonce', '_ajax_nonce');
        $token_key = $_POST['key'] ?? null;

        if ($token_key === null) {
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

        $success = $response['success'] ?? null;
        $status = $response['result']['status'] ?? null;

        // The verification has succeeded
        if ($success !== null && $success === true) {
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

                $zone_id = null;
                $account_id = null;
                $success = $zones_response['success'] ?? null;
                if ($success !== null && $success === true) {
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

                        if ($zone_id === null) {
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
    }

    public function lws_optimize_cf_tools_deactivation () {
        check_ajax_referer('lwsop_opti_cf_tools_nonce', '_ajax_nonce');
        $min_css = $_POST['min_css'] ?? null;
        $min_js = $_POST['min_js'] ?? null;
        $dynamic_cache = $_POST['cache_deactivate'] ?? null;

        $config_array = $GLOBALS['lws_optimize']->optimize_options;

        $config_array['cloudflare']['tools'] = [
            'min_css' => $min_css === null ? false : true,
            'min_js' => $min_js === null ? false : true,
            'dynamic_cache' => $dynamic_cache === null ? false : true,
        ];
        update_option('lws_optimize_config_array', $config_array);
        $GLOBALS['lws_optimize']->optimize_options = $config_array;

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_cf_cache_duration () {
        check_ajax_referer('lwsop_opti_cf_duration_nonce', '_ajax_nonce');
        $cache_span = $_POST['lifespan'] ?? null;

        if ($cache_span !== null) {
            $config_array = $GLOBALS['lws_optimize']->optimize_options;
            $config_array['cloudflare']['lifespan'] = sanitize_text_field($cache_span);
            update_option('lws_optimize_config_array', $config_array);
            $GLOBALS['lws_optimize']->optimize_options = $config_array;
        }

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
    }

    public function lws_optimize_cf_finish_config () {
        check_ajax_referer('lwsop_cloudflare_finish_config_nonce', '_ajax_nonce');

        $config_array = $GLOBALS['lws_optimize']->optimize_options;
        $zone_id = $config_array['cloudflare']['zone_id'] ?? null;
        $api_token = $config_array['cloudflare']['api'] ?? null;
        $cache_span = $config_array['cloudflare']['lifespan'] ?? null;
        $tools = $config_array['cloudflare']['tools'] ?? null;

        if ($zone_id === null || $api_token === null || $cache_span === null || $tools === null) {
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

            update_option('lws_optimize_config_array', $config_array);
            $GLOBALS['lws_optimize']->optimize_options = $config_array;
            wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array), JSON_PRETTY_PRINT));
        } else {
            unset($config_array['cloudflare']);
            wp_die(json_encode(array('code' => "FAILED_PATCH", 'data' => $config_array), JSON_PRETTY_PRINT));
        }
    }

    public function lws_optimize_deactivate_cf_inte() {
        check_ajax_referer('lwsop_deactivate_cf_integration_nonce', '_ajax_nonce');

        $config_array = $GLOBALS['lws_optimize']->optimize_options;

        $zone_id = $config_array['cloudflare']['zone_id'] ?? null;
        $api_token = $config_array['cloudflare']['api'] ?? null;
        $cache_span = $config_array['cloudflare']['lifespan'] ?? null;

        if ($zone_id === null || $api_token === null || $cache_span === null) {
            wp_die(json_encode(array('code' => "NO_PARAM", 'data' => $config_array), JSON_PRETTY_PRINT));
        }

        unset($config_array['cloudflare']);

        update_option('lws_optimize_config_array', $config_array);
        $GLOBALS['lws_optimize']->optimize_options = $config_array;

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

        wp_die(json_encode(array('code' => "SUCCESS", 'data' => $config_array, 'cache' => $response), JSON_PRETTY_PRINT));
    }
}
