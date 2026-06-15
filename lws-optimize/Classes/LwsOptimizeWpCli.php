<?php
namespace Lws\Classes;

/**
 * LWS Optimize WP-CLI Commands
 */
class LwsOptimizeWpCli {

    /**
     * Register the WP-CLI commands
     */
    public static function register_commands() {
        if (!class_exists('WP_CLI')) {
            return;
        }

        // Register the main commands
        \WP_CLI::add_command('lwsoptimize filecache', [self::class, 'filecache']);
        \WP_CLI::add_command('lwsoptimize preload', [self::class, 'preload']);
        \WP_CLI::add_command('lwsoptimize memcached', [self::class, 'memcached']);
        \WP_CLI::add_command('lwsoptimize autopurge', [self::class, 'autopurge']);
        \WP_CLI::add_command('lwsoptimize servercache', [self::class, 'servercache']);
        \WP_CLI::add_command('lwsoptimize configuration', [self::class, 'configuration']);
        \WP_CLI::add_command('lwsoptimize pagespeed', [self::class, 'pagespeed']);

        // 4.5.11 — Nouvelles commandes pour les features ajoutées en 4.4.x / 4.5.x
        \WP_CLI::add_command('lwsoptimize status',     [self::class, 'status']);
        \WP_CLI::add_command('lwsoptimize rum',        [self::class, 'rum']);
        \WP_CLI::add_command('lwsoptimize cloudflare', [self::class, 'cloudflare']);
        \WP_CLI::add_command('lwsoptimize phase2',     [self::class, 'phase2']);
    }

    /**
     * Manage the file-based cache
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform on file-based cache (clear|status|activate|deactivate)
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Clear the file-based cache
     *     $ wp lwsoptimize filecache clear
     *
     *     # Check the file-based cache status
     *     $ wp lwsoptimize filecache status
     *
     *     # Get file-based cache status in JSON format
     *     $ wp lwsoptimize filecache status --format=json
     *
     *     # Activate the file-based cache
     *     $ wp lwsoptimize filecache activate
     *
     * @when after_wp_load
     */
    public static function filecache($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: clear, status, activate, or deactivate');
            return -1;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        // Continue with the rest of your existing code
        switch ($action) {
            case 'clear':
                $result = $optimize->lws_optimize_clean_filebased_cache(false, "WPCLI");
                $decoded = json_decode($result, true);

                // Failed to decode JSON; cache may or may not be cleared
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \WP_CLI::error('Failed to get filecache state.');
                    return -1;
                }

                $status = $decoded['code'] ?? 'ERROR';
                switch ($status) {
                    // Standard case, cache cleared
                    case 'SUCCESS':
                        \WP_CLI::success('Filecache cleared successfully.');
                        return 0;
                    // Should never happen ; cache was not purged as autopurge cannot full clear
                    case 'FULL_CLEAR_FORBIDDEN':
                        \WP_CLI::error('Cannot fully clear filecache via the autopurge.');
                        return -1;
                    // Should never happen ; only home page was cleared
                    case 'ONLY_HOME':
                        \WP_CLI::warning('Only the home page was cleared.');
                        return 1;
                    // Default case, cache not cleared or uncaught error
                    default:
                        if ($json_output) {
                            \WP_CLI::line(json_encode(['status' => $status, 'message' => $decoded]));
                        } else {
                            \WP_CLI::error('Failed to clear filecache.');
                            \WP_CLI::line('Error code: ' . $status);
                        }
                        return -1;
                }
            case 'status':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Get the filecache state
                $state = $options['filebased_cache']['state'] ?? "false";

                // Get the amount of files in the cache
                $cache = $optimize->lwsop_recalculate_stats('regenerate');
                is_array($cache) || $cache = [];

                // Return to the user the state of the filecache
                if ($json_output) {
                    \WP_CLI::line(json_encode([
                        'state' => $state == "true" ? true : false,
                        'content' => $cache
                    ]));
                } else {
                    \WP_CLI::success('Filecache status retrieved: ');
                    \WP_CLI::line('*  Cache state: ' . ($state == "true" ? 'Enabled' : 'Disabled'));
                    \WP_CLI::line('*  Cache content: ');
                    \WP_CLI::line('   *  Desktop cache: ' . $cache['desktop']['amount'] . ' files, ' . size_format($cache['desktop']['size']) );
                    \WP_CLI::line('   *  Mobile cache: ' . $cache['mobile']['amount'] . ' files, ' . size_format($cache['mobile']['size']) );
                    \WP_CLI::line('   *  CSS cache: ' . $cache['css']['amount'] . ' files, ' . size_format($cache['css']['size']) );
                    \WP_CLI::line('   *  JS cache: ' . $cache['js']['amount'] . ' files, ' . size_format($cache['js']['size']) );

                }
                return 0;
            case 'activate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Cache is already activated, no need to do anything
                if (isset($options['filebased_cache']['state']) && $options['filebased_cache']['state'] == "true") {
                    \WP_CLI::success('Filecache is already activated.');
                    return 0;
                }

                // Update cache state
                $options['filebased_cache']['state'] = "true";
                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Filecache activated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to activate filecache.');
                    return -1;
                }
            case 'deactivate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Cache is already deactivated, no need to do anything
                if (isset($options['filebased_cache']['state']) && $options['filebased_cache']['state'] == "false") {
                    \WP_CLI::success('Filecache is already deactivated.');
                    return 0;
                }

                // Update cache state
                $options['filebased_cache']['state'] = "false";
                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Filecache deactivated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to deactivate filecache.');
                    return -1;
                }
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                return -1;
        }
    }

    /**
     * Manage the autopurge functionality
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform on autopurge (status|activate|deactivate)
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Activate autopurge
     *     $ wp lwsoptimize autopurge activate
     *
     *     # Deactivate autopurge
     *     $ wp lwsoptimize autopurge deactivate
     *
     *     # Check autopurge status
     *     $ wp lwsoptimize autopurge status
     *
     * @when after_wp_load
     */
    public static function autopurge($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status, activate or deactivate');
            return -1;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        // Continue with the rest of your existing code
        switch ($action) {
            case 'status':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Get the autopurge state
                $state = $options['autopurge']['state'] ?? "false";

                // Return to the user the state of the autopurge
                if ($json_output) {
                    \WP_CLI::line(json_encode([
                        'state' => $state == "true" ? true : false,
                    ]));
                } else {
                    \WP_CLI::success('Autopurge status retrieved: ');
                    \WP_CLI::line('*  Autopurge state: ' . ($state == "true" ? 'Enabled' : 'Disabled'));
                }
                return 0;
            case 'activate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Cache is already activated, no need to do anything
                if (isset($options['autopurge']['state']) && $options['autopurge']['state'] == "true") {
                    \WP_CLI::success('Autopurge is already activated.');
                    return 0;
                }

                // Update cache state
                $options['autopurge']['state'] = "true";

                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Autopurge activated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to activate autopurge.');
                    return -1;
                }
                break;
            case 'deactivate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Cache is already deactivated, no need to do anything
                if (isset($options['autopurge']['state']) && $options['autopurge']['state'] == "false") {
                    \WP_CLI::success('Autopurge is already deactivated.');
                    return 0;
                }

                // Update cache state
                $options['autopurge']['state'] = "false";

                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Autopurge deactivated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to deactivate autopurge.');
                    return -1;
                }
                break;
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                return -1;
        }
    }

    public static function servercache($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status or clear');
            return -1;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        switch ($action) {
            // case 'status':
            //     // Check server cache state using environment variables
            //     $cache_state = "false";
            //     $used_cache = "unsupported";

            //     // Check for LWSCache
            //     if (!empty($_SERVER['lwscache']) || !empty($_ENV['lwscache'])) {
            //         $used_cache = "lws";
            //         $server_value = !empty($_SERVER['lwscache']) ? $_SERVER['lwscache'] : $_ENV['lwscache'];
            //         $cache_state = (strtolower($server_value) == "on" || $server_value == "1" || $server_value === true) ? true : false;
            //     }
            //     // Check for Varnish cache
            //     elseif (!empty($_SERVER['HTTP_X_VARNISH'])) {
            //         $used_cache = "varnish";
            //         // Check if Varnish is active through any of the possible headers
            //         foreach (['HTTP_X_CACHE_ENABLED', 'HTTP_EDGE_CACHE_ENGINE_ENABLED', 'HTTP_EDGE_CACHE_ENGINE_ENABLE'] as $header) {
            //             if (!empty($_SERVER[$header])) {
            //                 $cache_state = ($_SERVER[$header] == "1" || strtolower($_SERVER[$header]) == "on" || $_SERVER[$header] === true) ? "true" : "false";
            //                 break;
            //             }
            //         }
            //     }
            //     // Check for LiteSpeed or other Edge cache engines
            //     elseif (isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE'])) {
            //         $engine = strtolower($_SERVER['HTTP_EDGE_CACHE_ENGINE']);
            //         if ($engine == 'litespeed') {
            //             $used_cache = "litespeed";
            //         } elseif ($engine == 'varnish') {
            //             $used_cache = "varnish";
            //         }

            //         if ($used_cache !== "unsupported") {
            //             $cache_state = ($_SERVER['HTTP_X_CACHE_ENABLED'] == "1" ||
            //                         strtolower($_SERVER['HTTP_X_CACHE_ENABLED']) == "on" ||
            //                         $_SERVER['HTTP_X_CACHE_ENABLED'] === true) ? "true" : "false";
            //         }
            //     }

            //     // Return to the user the state of the server cache
            //     if ($json_output) {
            //         \WP_CLI::line(json_encode([
            //             'state' => $cache_state,
            //             'used_cache' => $used_cache,
            //         ]));
            //     } else {
            //         \WP_CLI::success('Server cache status retrieved: ');
            //         \WP_CLI::line('*  Server cache state: ' . ($cache_state ? 'Enabled' : 'Disabled'));
            //         \WP_CLI::line('*  Type of server used: ' . $used_cache);
            //     }
            //     return 0;
            case 'clear':
                // Generic server cache clearing command
                wp_remote_request(get_site_url(), array('method' => 'FULLPURGE'));
                wp_remote_request(get_site_url(), array('method' => 'PURGE'));
                \WP_CLI::success('Server cache purged.');
                return 0;
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                return -1;
        }
    }

    /**
     * Manage the preload functionality
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform on preload (status|activate|deactivate|change_amount|next)
     *
     * [<amount>]
     * : Number of pages to preload (for <activate> and <change_amount> actions)
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Check preload status
     *     $ wp lwsoptimize preload status
     *
     *     # Activate preload with default amount
     *     $ wp lwsoptimize preload activate
     *
     *     # Activate preload with 10 pages
     *     $ wp lwsoptimize preload activate 10
     *
     *     # Deactivate preload
     *     $ wp lwsoptimize preload deactivate
     *
     *     # Change preload amount to 5
     *     $ wp lwsoptimize preload change_amount 5
     *
     *     # Check next scheduled preload
     *     $ wp lwsoptimize preload next
     *
     * @when after_wp_load
     */
    public static function preload($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status, activate, or deactivate');
            return -1;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        switch ($action) {
            case 'status':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Get the filecache and its preload state
                $preload_state = $options['filebased_cache']['preload'] ?? "false";
                $state = $options['filebased_cache']['state'] ?? "false";
                $preload_amount = $options['filebased_cache']['preload_amount'] ?? 0;
                $preload_done = $options['filebased_cache']['preload_done'] ?? 0;
                $preload_quantity = $options['filebased_cache']['preload_quantity'] ?? 0;

                $next = wp_next_scheduled("lws_optimize_start_filebased_preload");

                // Return to the user the state of both filecache and preload
                // as well as, for the preload, the current amount of preloaded pages
                if ($json_output) {
                    \WP_CLI::line(json_encode([
                        'state' => $state == "true" ? true : false,
                        'preload' => $preload_state == "true" ? true : false,
                        'next' => $next ?? 0,
                        'next_clear' => $next ? gmdate('Y-m-d H:i:s', $next) : 0,
                        'preload_amount' => intval($preload_amount),
                        'preload_done' => intval($preload_done),
                        'preload_total' => intval($preload_quantity)
                    ]));
                } else {
                    \WP_CLI::success('Preload status retrieved: ');
                    \WP_CLI::line('*  Filecache state: ' . ($state == "true" ? 'Enabled' : 'Disabled'));
                    \WP_CLI::line('*  Preload state: ' . ($preload_state == "true" ? 'Enabled' : 'Disabled'));
                    if ($preload_state == "true") {
                        \WP_CLI::line('*  Preload amount: ' . $preload_amount . ' pages');
                        \WP_CLI::line('*  Preload done: ' . $preload_done . '/' . $preload_quantity . ' pages preloaded');
                        if ($next) {
                            \WP_CLI::line('*  Next preload scheduled for: ' . gmdate('Y-m-d H:i:s', $next));
                        } else {
                            \WP_CLI::line('*  No preload scheduled.');
                        }
                    }
                }
                return 0;
            case 'activate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                if ($options['filebased_cache']['preload'] == "true" && wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    \WP_CLI::success('Preload is already activated.');
                    return 0;
                }

                // Get the amount parameter (optional)
                $amount = intval($args[1] ?? 0);
                $amount < 1 || $amount > 30 ? $amount = 3 : $amount; // Limit the amount to a range of 1 to 30 ; default 3

                // Update preload configuration
                $options['filebased_cache']['preload'] = "true";
                $options['filebased_cache']['preload_amount'] = $amount;
                $options['filebased_cache']['preload_done'] = 0;
                $options['filebased_cache']['preload_ongoing'] = "true";

                // Get sitemap URLs
                $urls = $optimize->get_sitemap_urls();
                $options['filebased_cache']['preload_quantity'] = count($urls);

                // Enable scheduled preload after 5 seconds
                if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                }
                wp_schedule_event(time() + 5, "lws_minute", "lws_optimize_start_filebased_preload");

                // Update options in database
                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Preload activated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to activate preload.');
                    return -1;
                }
                break;
            case 'deactivate':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                if ($options['filebased_cache']['preload'] == "false" && !wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    \WP_CLI::success('Preload is already deactivated.');
                    return 0;
                }

                // Update preload configuration
                $options['filebased_cache']['preload'] = "false";
                $options['filebased_cache']['preload_ongoing'] = "false";

                // Remove scheduled preload
                if (wp_next_scheduled("lws_optimize_start_filebased_preload")) {
                    wp_unschedule_event(wp_next_scheduled("lws_optimize_start_filebased_preload"), "lws_optimize_start_filebased_preload");
                }

                // Update options in database
                if (update_option('lws_optimize_config_array', $options)) {
                    \WP_CLI::success('Preload deactivated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to deactivate preload.');
                    return -1;
                }
            case 'change_amount':
                // Get plugin options from the database
                $options = get_option('lws_optimize_config_array', []);

                // Get the amount parameter (optional)
                $amount = intval($args[1] ?? 0);
                $amount < 1 || $amount > 30 ? $amount = 3 : $amount; // Limit the amount to a range of 1 to 30 ; default 3


                if ($options['filebased_cache']['preload_amount'] == $amount) {
                    if ($json_output) {
                        \WP_CLI::line(json_encode(['amount' => $amount]));
                    } else {
                        \WP_CLI::success("Preload amount changed to ({$amount}).");
                    }
                    return 0;
                }

                // Update preload configuration
                $options['filebased_cache']['preload_amount'] = $amount;

                // Update options in database
                if (update_option('lws_optimize_config_array', $options)) {
                    if ($json_output) {
                        \WP_CLI::line(json_encode(['amount' => $amount]));
                    } else {
                        \WP_CLI::success("Preload amount changed to ({$amount}).");
                    }
                    return 0;
                } else {
                    \WP_CLI::error('Failed to change preload amount.');
                    return -1;
                }
            case 'next':
                // Get plugin options from the database
                $next = wp_next_scheduled("lws_optimize_start_filebased_preload");
                if ($next) {
                    if ($json_output) {
                        \WP_CLI::line(json_encode([
                            'next_clear' => gmdate('Y-m-d H:i:s', $next),
                            'next' => $next,
                        ]));
                    } else {
                        \WP_CLI::success("Next preload scheduled for: " . gmdate('Y-m-d H:i:s', $next));
                    }
                } else {
                    if ($json_output) {
                        \WP_CLI::line(json_encode([
                            'next_clear' => gmdate('Y-m-d H:i:s', 0),
                            'next' => 0,
                        ]));
                    } else {
                        \WP_CLI::success('No preload scheduled.');
                    }
                }
                return 0;
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                return -1;
        }
    }

    /**
     * Manage memcached functionality
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform on memcached (status|activate|deactivate|recommend|validate)
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Check memcached status
     *     $ wp lwsoptimize memcached status
     *
     *     # Should we recommend activating Memcached on this host?
     *     # (exit 0 = yes, 1 = no — pipe-friendly for ops scripts)
     *     $ wp lwsoptimize memcached recommend
     *
     *     # Full env validation dry-run (extension + server + PHP sessions conflict + dropin)
     *     # exit 0 = OK, 1 = warning, 2 = fatal
     *     $ wp lwsoptimize memcached validate --format=json
     *
     *     # Activate memcached
     *     $ wp lwsoptimize memcached activate
     *
     *     # Deactivate memcached
     *     $ wp lwsoptimize memcached deactivate
     *
     *     # Get memcached status in JSON format
     *     $ wp lwsoptimize memcached status --format=json
     *
     * @when after_wp_load
     */
    public static function memcached($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status, activate, deactivate, recommend, or validate');
            return;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return;
        }

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        $options = get_option('lws_optimize_config_array', []);

        $state = $options['memcached']['state'];
        $state = $state == "true" ? true : false;

        $redis = false;
        $memcached_state = false;

        if (is_plugin_active('redis-cache/redis-cache.php')) {
            $redis = true;
        } else {
            if (class_exists('Memcached')) {
                $memcached = new \Memcached();
                if (empty($memcached->getServerList())) {
                    $memcached->addServer('localhost', 11211);
                }

                if ($memcached->getVersion() !== false) {
                    $memcached_state = true;
                }
            }
        }

        switch ($action) {
            case 'status':
                if ($json_output) {
                    \WP_CLI::line(json_encode([
                        'state' => $state && $memcached_state,
                        'memcached_module' => $memcached_state,
                        'redis' => $redis,
                    ]));
                    return  0;
                } else {
                    \WP_CLI::success('Memcached status retrieved: ');
                    // Check if Memecached is used (activated AND module available)
                    \WP_CLI::line('*  Memcached state: ' . ($state && $memcached_state ? 'Enabled' : 'Disabled'));
                    // Warning that RedisCache is activated (so Memcached is not used)
                    if ($redis) {
                        \WP_CLI::line('*  RedisCache plugin is activated, Memcached is not used');
                    }
                    // Warning that Memcached module is not available and as such cannot be used
                    if (!$memcached_state) {
                        \WP_CLI::line('*  Memcached module is not available/activated on this server');
                    }

                    return 0;
                }
            case 'activate':
                // 4.5.12 — Validation environnement complète (extension + serveur + sessions + drop-in tiers)
                $env = $optimize->lwsop_validate_memcached_environment();
                if (!$env['ok'] && $env['severity'] === 'fatal') {
                    \WP_CLI::error("Cannot activate Memcached — {$env['reason']} : " . substr($env['message'], 0, 300));
                    return -1;
                }
                if ($state && $memcached_state) {
                    \WP_CLI::success('Memcached is already activated.');
                    return 0;
                }

                $options['memcached']['state'] = "true";
                if (update_option('lws_optimize_config_array', $options)) {
                    if (isset($GLOBALS['lws_optimize'])) {
                        $GLOBALS['lws_optimize']->lwsop_safe_write_dropin(
                            LWSOP_OBJECTCACHE_PATH,
                            LWS_OP_DIR . '/views/object-cache.php'
                        );
                    }
                    if ($env['severity'] === 'warning') {
                        \WP_CLI::warning('Memcached activated WITH warning: ' . $env['message']);
                    } else {
                    \WP_CLI::success('Memcached activated.');
                    }
                    return 0;
                } else {
                    \WP_CLI::error('Failed to activate Memcached.');
                    return -1;
                }
                break;
            case 'deactivate':
                if (!$state) {
                    \WP_CLI::success('Memcached is already deactivated.');
                    return 0;
                }

                $options['memcached']['state'] = "false";
                if (update_option('lws_optimize_config_array', $options)) {
                    // Only delete the drop-in if we own it
                    if (isset($GLOBALS['lws_optimize'])) {
                        $GLOBALS['lws_optimize']->lwsop_safe_delete_dropin(LWSOP_OBJECTCACHE_PATH);
                    }

                    \WP_CLI::success('Memcached deactivated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to deactivate Memcached.');
                    return -1;
                }
            case 'recommend':
                // 4.5.11 — Renvoie la décision du helper lwsop_can_recommend_memcached()
                // pour permettre aux ops scripts / UI de décider s'il faut proposer
                // l'activation. Toutes les conditions sont vérifiées (extension,
                // connexion localhost:11211, Redis plugin, drop-in tiers).
                $reco = $optimize->lwsop_can_recommend_memcached();
                if ($json_output) {
                    \WP_CLI::line(json_encode($reco, JSON_PRETTY_PRINT));
                    return 0;
                }
                if ($reco['recommend']) {
                    \WP_CLI::success('Memcached can be safely recommended.');
                    \WP_CLI::line('  Run: wp lwsoptimize memcached activate');
                } else {
                    \WP_CLI::warning('Memcached is not recommended. Reason: ' . $reco['reason']);
                    foreach ($reco['details'] as $k => $v) {
                        \WP_CLI::line(sprintf('  %-22s %s', $k . ':', is_scalar($v) ? var_export($v, true) : json_encode($v)));
                    }
                }
                return $reco['recommend'] ? 0 : 1;
            case 'validate':
                // 4.5.12 — Dry-run de tous les checks (A à F) sans toucher l'état.
                // Pipe-friendly : exit 0 = OK, 1 = warning, 2 = fatal.
                $env = $optimize->lwsop_validate_memcached_environment();
                if ($json_output) {
                    \WP_CLI::line(json_encode($env, JSON_PRETTY_PRINT));
                    return $env['severity'] === 'fatal' ? 2 : ($env['severity'] === 'warning' ? 1 : 0);
                }
                $label = strtoupper($env['severity']);
                if ($env['severity'] === 'fatal') {
                    \WP_CLI::error_multi_line(["[$label] {$env['reason']}", $env['message']]);
                } elseif ($env['severity'] === 'warning') {
                    \WP_CLI::warning("[$label] {$env['reason']} — " . $env['message']);
                } else {
                    \WP_CLI::success("[$label] {$env['reason']} — " . $env['message']);
                }
                if (!empty($env['details'])) {
                    foreach ($env['details'] as $k => $v) {
                        \WP_CLI::line(sprintf('  %-22s %s', $k . ':', is_scalar($v) ? var_export($v, true) : json_encode($v)));
                    }
                }
                if (!empty($env['fix_url'])) {
                    \WP_CLI::line('  fix_url: ' . $env['fix_url']);
                }
                return $env['severity'] === 'fatal' ? 2 : ($env['severity'] === 'warning' ? 1 : 0);
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                break;
        }
    }

    /**
     * Manage the configuration of LWS Optimize, including activation, deactivation, and setup
     *
     * ## OPTIONS
     *
     * <action>
     * : The action to perform on configuration (activate|deactivate|basic|advanced|complete)
     *
     * [<time>]
     * : Duration for deactivation (in seconds) for <deactivate> action. Must be 300, 1800, 3600, or 86400 seconds. Default is 300 seconds.
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Clear the configuration
     *     $ wp lwsoptimize configuration clear
     *
     * @when after_wp_load
     */
    public static function configuration($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status or clear');
            return -1;
        }

        $action = $args[0];
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        $options = get_option('lws_optimize_config_array', []);

        // Check if json output is requested
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        // Get the duration parameter that may be passed
        $duration = 300; // Default to 5 minutes
        switch ($action) {
            case 'deactivate':
                if (get_option('lws_optimize_deactivate_temporarily')) {
                    \WP_CLI::success('LWS Optimize is already deactivated.');
                    return 0;
                }


                // Get the duration parameter (optional)
                $time = intval($args[1] ?? 0);
                $time < 300 || $time > 86400 ? $time = 300 : $time; // Limit the amount to a range of 300 to 86400 ; default 300

                // Check if the given time is valid
                switch ($time) {
                    case 300:
                    case 1800:
                    case 3600:
                    case 86400:
                        $duration = intval($time);
                        break;
                    default:
                        $duration = 300; // Default to 5 minutes
                        break;
                }

                $deactivated = add_option('lws_optimize_deactivate_temporarily', time() + $duration);
                $htaccess_cleaned = false;

                if ($deactivated) {
                    // Get htaccess content
                    $htaccess = ABSPATH . '/.htaccess';
                    if (file_exists($htaccess) && is_writable($htaccess)) {
                        // Read htaccess content
                        $htaccess_content = file_get_contents($htaccess);

                        // Remove caching rules if they exist
                        $pattern = '/#LWS OPTIMIZE - CACHING[\s\S]*?#END LWS OPTIMIZE - CACHING\n?/';
                        $htaccess_content = preg_replace($pattern, '', $htaccess_content);

                        // Write back to file
                        if (file_put_contents($htaccess, $htaccess_content) !== false) {
                            $htaccess_cleaned = true;
                        }
                    }
                } else {
                    \WP_CLI::error('Failed to deactivate LWS Optimize.');
                    return -1;
                }

                if ($json_output) {
                    \WP_CLI::line(json_encode([
                        'deactivated' => true,
                        'duration' => $duration,
                        'htaccess_cleaned' => $htaccess_cleaned,
                    ]));
                } else {
                    \WP_CLI::success('LWS Optimize deactivated for ' . $duration . ' seconds.');
                    if ($htaccess_cleaned) {
                        \WP_CLI::success('Caching rules removed from .htaccess.');
                    } else {
                        \WP_CLI::warning('Failed to clean .htaccess file.');
                    }
                }
                return 0;
            case 'activate':
                if (get_option('lws_optimize_deactivate_temporarily')) {
                    if (delete_option('lws_optimize_deactivate_temporarily') === true) {
                        if (isset($options['htaccess_rules']['state']) && $options['htaccess_rules']['state'] == "true") {
                            $optimize->lws_optimize_set_cache_htaccess();
                        }
                        \WP_CLI::success('LWS Optimize activated.');
                        return 0;
                    } else {
                        \WP_CLI::error('Failed to activate LWS Optimize.');
                        return -1;
                    }
                } else {
                    \WP_CLI::success('LWS Optimize is already activated.');
                    return 0;
                }
            case 'basic':
                $optimize->lwsop_auto_setup_optimize('basic');
                \WP_CLI::success('Basic configuration applied.');
                return 0;
            case 'advanced':
                $optimize->lwsop_auto_setup_optimize('advanced');
                \WP_CLI::success('Advanced configuration applied.');
                return 0;
            case 'complete':
                $optimize->lwsop_auto_setup_optimize('full');
                \WP_CLI::success('Complete configuration applied.');
                return 0;
            default:
                \WP_CLI::error("Action `$action` does not exists. See help for available actions.");
                return -1;
        }
    }

    /**
     * Get PageSpeed results for the current site
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     # Get PageSpeed results in JSON format
     *     $ wp lwsoptimize pagespeed --format=json
     *
     * @when after_wp_load
     */
    public static function pagespeed($args, $assoc_args) {
        // No subfunction, this time, so only get the main class and check for $json
        $optimize = $GLOBALS['lws_optimize'];
        if (!isset($optimize)) {
            \WP_CLI::error('LWS Optimize is not initialized.');
            return -1;
        }

        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        $url = site_url();

        // Define strategies to test
        $strategies = ['mobile', 'desktop'];
        $results = [];

        // Run tests for each strategy
        foreach ($strategies as $strategy) {
            $apiUrl = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$url&key=AIzaSyD8yyUZIGg3pGYgFOzJR1NsVztAf8dQUFQ&strategy=$strategy";
            $response = wp_remote_get($apiUrl, ['timeout' => 60, 'sslverify' => false]);

            if (is_wp_error($response)) {
                if ($json_output) {
                    \WP_CLI::line(json_encode(['error' => true, 'strategy' => $strategy, 'message' => $response->get_error_message()]));
                    continue;
                } else {
                    \WP_CLI::warning("Error getting $strategy results: " . $response->get_error_message());
                    continue;
                }
            }

            $decoded = json_decode($response['body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($json_output) {
                    \WP_CLI::line(json_encode(['error' => true, 'strategy' => $strategy, 'message' => 'Failed to decode API response']));
                    continue;
                } else {
                    \WP_CLI::warning("Failed to decode $strategy API response");
                    continue;
                }
            }

            $results[$strategy] = [
                'performance' => $decoded['lighthouseResult']['categories']['performance']['score'] ?? null,
                'speed' => $decoded['lighthouseResult']['audits']['speed-index']['displayValue'] ?? null,
                'speed_milli' => $decoded['lighthouseResult']['audits']['speed-index']['numericValue'] ?? null,
                'speed_unit' => $decoded['lighthouseResult']['audits']['speed-index']['numericUnit'] ?? null
            ];
        }
        if (is_wp_error($response)) {
            \WP_CLI::error('Failed to get PageSpeed results.');
            return -1;
        }

        $response = json_decode($response['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            \WP_CLI::error('Failed to decode PageSpeed API response.');
            return -1;
        }

        if ($json_output) {
            \WP_CLI::line(json_encode($results));
        } else {
            \WP_CLI::success('PageSpeed results retrieved: ');
            foreach ($results as $strategy => $result) {
                \WP_CLI::line('*  ' . ucfirst($strategy) . ' performance score: ' . ($result['performance']*100) . '%');
                \WP_CLI::line('*  ' . ucfirst($strategy) . ' speed metric: ' . $result['speed']);
                // \WP_CLI::line('*  ' . ucfirst($strategy) . ' speed metric value: ' . $result['speed_milli']);
                // \WP_CLI::line('*  ' . ucfirst($strategy) . ' speed metric unit: ' . $result['speed_unit']);
            }
        }

        return 0;
    }

    /**
     * Global health status of LWS Optimize (Memcached, file-cache, RUM,
     * Cloudflare APO, preload state). Useful for ops scripts / monitoring.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table|json)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *
     * ## EXAMPLES
     *
     *     $ wp lwsoptimize status
     *     $ wp lwsoptimize status --format=json
     *
     * @when after_wp_load
     */
    public static function status($args, $assoc_args) {
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';
        $options     = get_option('lws_optimize_config_array', []);

        $fb_state        = $options['filebased_cache']['state']           ?? 'false';
        $fb_preload      = $options['filebased_cache']['preload']         ?? 'false';
        $fb_preload_done = (int)($options['filebased_cache']['preload_done']     ?? 0);
        $fb_preload_qty  = (int)($options['filebased_cache']['preload_quantity'] ?? 0);
        $fb_preload_amt  = (int)($options['filebased_cache']['preload_amount']   ?? 5);
        $memcached       = $options['memcached']['state']                 ?? 'false';
        $object_cache    = file_exists(WP_CONTENT_DIR . '/object-cache.php') ? 'yes' : 'no';
        $cf_apo          = $options['cloudflare_apo']['state']            ?? 'false';
        $rum_enabled     = $options['rum']['state']                       ?? 'false';
        $rum_samples     = count((array) get_option('lwsop_rum_samples', []));
        $rum_agg_ts      = (int) get_option('lwsop_rum_aggregate_ts', 0);
        $maint_db        = $options['maintenance_db']['state']            ?? 'false';

        $cache_dir = WP_CONTENT_DIR . '/cache/lwsoptimize/';
        $cache_size = 0;
        if (is_dir($cache_dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cache_dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { if ($f->isFile()) $cache_size += $f->getSize(); }
        }

        $data = [
            'version'            => defined('LWS_OP_BASENAME') ? (get_file_data(WP_PLUGIN_DIR . '/' . LWS_OP_BASENAME, ['v' => 'Version'])['v'] ?? '?') : '?',
            'filebased_cache'    => $fb_state,
            'cache_size_bytes'   => $cache_size,
            'preload'            => $fb_preload,
            'preload_progress'   => "$fb_preload_done/$fb_preload_qty",
            'preload_amount'     => $fb_preload_amt,
            'memcached'          => $memcached,
            'object_cache_drop'  => $object_cache,
            'cloudflare_apo'     => $cf_apo,
            'rum_enabled'        => $rum_enabled,
            'rum_samples'        => $rum_samples,
            'rum_last_aggregate' => $rum_agg_ts ? gmdate('Y-m-d H:i:s', $rum_agg_ts) . ' UTC' : 'never',
            'maintenance_db'     => $maint_db,
        ];

        if ($json_output) {
            \WP_CLI::line(json_encode($data, JSON_PRETTY_PRINT));
            return 0;
        }
        \WP_CLI::success('LWS Optimize status:');
        foreach ($data as $k => $v) {
            \WP_CLI::line(sprintf('  %-22s %s', $k . ':', is_scalar($v) ? $v : json_encode($v)));
        }
        return 0;
    }

    /**
     * Manage Real User Monitoring (RUM).
     *
     * ## OPTIONS
     *
     * <action>
     * : list | aggregate | purge | export | stats
     *
     * [--days=<days>]
     * : For 'purge' : age threshold in days (default 30).
     *
     * [--format=<format>]
     * : Output format (table|json) — default table.
     *
     * ## EXAMPLES
     *
     *     # Show aggregated p50/p75/p95 per URL/device
     *     $ wp lwsoptimize rum stats
     *
     *     # Force aggregation now (instead of waiting for the twice-daily cron)
     *     $ wp lwsoptimize rum aggregate
     *
     *     # Purge raw samples older than 30 days
     *     $ wp lwsoptimize rum purge --days=30
     *
     *     # Export all raw samples to stdout as JSON
     *     $ wp lwsoptimize rum export --format=json
     *
     * @when after_wp_load
     */
    public static function rum($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: list, aggregate, purge, export, stats');
            return -1;
        }
        if (!class_exists('\\Lws\\Classes\\RUM\\LwsOptimizeRUM')) {
            \WP_CLI::error('RUM module not loaded.');
            return -1;
        }
        $action      = $args[0];
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';

        switch ($action) {
            case 'list':
            case 'export':
                $samples = (array) get_option('lwsop_rum_samples', []);
                if ($json_output || $action === 'export') {
                    \WP_CLI::line(json_encode($samples));
                    return 0;
                }
                \WP_CLI::success(count($samples) . ' RUM samples in buffer');
                foreach (array_slice($samples, -20) as $s) {
                    \WP_CLI::line(sprintf('  [%s] %-7s %s  %s %s', gmdate('Y-m-d H:i', $s['t'] ?? 0), $s['d'] ?? '?', $s['p'] ?? '?', $s['m'] ?? '?', $s['v'] ?? '?'));
                }
                return 0;

            case 'aggregate':
                \Lws\Classes\RUM\LwsOptimizeRUM::aggregate();
                $ts = (int) get_option('lwsop_rum_aggregate_ts', 0);
                \WP_CLI::success('RUM aggregated at ' . gmdate('Y-m-d H:i:s', $ts) . ' UTC');
                return 0;

            case 'purge':
                $days    = isset($assoc_args['days']) ? max(1, intval($assoc_args['days'])) : 30;
                $cutoff  = time() - ($days * DAY_IN_SECONDS);
                $samples = (array) get_option('lwsop_rum_samples', []);
                $kept    = array_values(array_filter($samples, function ($s) use ($cutoff) {
                    return ($s['t'] ?? 0) >= $cutoff;
                }));
                update_option('lwsop_rum_samples', $kept, false);
                \WP_CLI::success(sprintf('Purged samples older than %d days. Kept %d / removed %d.', $days, count($kept), count($samples) - count($kept)));
                return 0;

            case 'stats':
                $agg = (array) get_option('lwsop_rum_aggregate', []);
                if (empty($agg)) {
                    \WP_CLI::warning('No aggregate yet. Run `wp lwsoptimize rum aggregate` first.');
                    return 1;
                }
                if ($json_output) {
                    \WP_CLI::line(json_encode($agg, JSON_PRETTY_PRINT));
                    return 0;
                }
                \WP_CLI::success(count($agg) . ' aggregate entries');
                foreach ($agg as $key => $stats) {
                    \WP_CLI::line(sprintf('  %s  n=%d  p50=%s  p75=%s  p95=%s', $key, $stats['n'] ?? 0, $stats['p50'] ?? '-', $stats['p75'] ?? '-', $stats['p95'] ?? '-'));
                }
                return 0;

            default:
                \WP_CLI::error("Unknown action `$action`. Use: list, aggregate, purge, export, stats");
                return -1;
        }
    }

    /**
     * Manage Cloudflare APO integration.
     *
     * ## OPTIONS
     *
     * <action>
     * : status | install-rule | purge-all | set-config
     *
     * [--zone-id=<id>]
     * : Required for 'set-config'.
     *
     * [--api-token=<token>]
     * : Required for 'set-config' (Cloudflare API token with Cache Rules + Cache Purge scopes).
     *
     * [--state=<state>]
     * : For 'set-config' : true|false (enable/disable APO). Default true.
     *
     * ## EXAMPLES
     *
     *     $ wp lwsoptimize cloudflare status
     *     $ wp lwsoptimize cloudflare set-config --zone-id=abc123 --api-token=xxx
     *     $ wp lwsoptimize cloudflare install-rule
     *     $ wp lwsoptimize cloudflare purge-all
     *
     * @when after_wp_load
     */
    public static function cloudflare($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: status, install-rule, purge-all, set-config');
            return -1;
        }
        $action  = $args[0];
        $options = get_option('lws_optimize_config_array', []);
        $cf      = $options['cloudflare_apo'] ?? [];

        switch ($action) {
            case 'status':
                \WP_CLI::success('Cloudflare APO status:');
                \WP_CLI::line('  state:     ' . ($cf['state']     ?? 'false'));
                \WP_CLI::line('  zone_id:   ' . (!empty($cf['zone_id'])   ? substr($cf['zone_id'], 0, 8) . '…' : 'not set'));
                \WP_CLI::line('  api_token: ' . (!empty($cf['api_token']) ? '****' . substr($cf['api_token'], -4) : 'not set'));
                return 0;

            case 'set-config':
                if (empty($assoc_args['zone-id']) || empty($assoc_args['api-token'])) {
                    \WP_CLI::error('--zone-id and --api-token are required.');
                    return -1;
                }
                $options['cloudflare_apo'] = [
                    'state'     => ($assoc_args['state'] ?? 'true') === 'false' ? 'false' : 'true',
                    'zone_id'   => sanitize_text_field($assoc_args['zone-id']),
                    'api_token' => sanitize_text_field($assoc_args['api-token']),
                ];
                update_option('lws_optimize_config_array', $options);
                \WP_CLI::success('Cloudflare APO config saved (state=' . $options['cloudflare_apo']['state'] . ').');
                return 0;

            case 'install-rule':
                if (!class_exists('\\Lws\\Classes\\Integrations\\LwsOptimizeCloudflareAPO')) {
                    \WP_CLI::error('Cloudflare APO module not loaded.');
                    return -1;
                }
                if (empty($cf['state']) || $cf['state'] !== 'true' || empty($cf['zone_id']) || empty($cf['api_token'])) {
                    \WP_CLI::error('APO not configured. Run `wp lwsoptimize cloudflare set-config` first.');
                    return -1;
                }
                // Reuse the AJAX handler logic by invoking it directly with a fake nonce
                // (we have CLI auth equivalent to manage_options). For simplicity we
                // re-implement the API call to avoid the nonce check.
                $host = parse_url(home_url(), PHP_URL_HOST);
                $expression = sprintf(
                    '(http.host eq "%s" and not (http.request.uri.path matches "^/(wp-admin|wp-login)") and not (any(http.cookie[*] in {"wp-" "wordpress_logged_in_" "woocommerce_" "edd_" "comment_author_"})))',
                    $host
                );
                $body = ['rules' => [[
                    'expression' => $expression,
                    'action' => 'set_cache_settings',
                    'action_parameters' => [
                        'cache' => true,
                        'edge_ttl' => ['mode' => 'override_origin', 'default' => 28800],
                        'browser_ttl' => ['mode' => 'respect_origin'],
                    ],
                    'description' => 'LWS Optimize APO — cache HTML for anonymous traffic',
                    'enabled' => true,
                ]]];
                $resp = wp_remote_request(
                    'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($cf['zone_id']) . '/rulesets/phases/http_request_cache_settings/entrypoint',
                    [
                        'method'  => 'PUT',
                        'timeout' => 15,
                        'headers' => [
                            'Authorization' => 'Bearer ' . $cf['api_token'],
                            'Content-Type'  => 'application/json',
                        ],
                        'body' => wp_json_encode($body),
                    ]
                );
                if (is_wp_error($resp)) {
                    \WP_CLI::error('CF API error: ' . $resp->get_error_message());
                    return -1;
                }
                $code = wp_remote_retrieve_response_code($resp);
                if ($code !== 200) {
                    \WP_CLI::error("CF API HTTP $code : " . wp_remote_retrieve_body($resp));
                    return -1;
                }
                \WP_CLI::success('Cache Rule installed on zone ' . $cf['zone_id']);
                return 0;

            case 'purge-all':
                if (!class_exists('\\Lws\\Classes\\Integrations\\LwsOptimizeCloudflareAPO')) {
                    \WP_CLI::error('Cloudflare APO module not loaded.');
                    return -1;
                }
                if (empty($cf['state']) || $cf['state'] !== 'true' || empty($cf['zone_id']) || empty($cf['api_token'])) {
                    \WP_CLI::error('APO not configured.');
                    return -1;
                }
                $resp = wp_remote_post(
                    'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($cf['zone_id']) . '/purge_cache',
                    [
                        'timeout' => 15,
                        'headers' => [
                            'Authorization' => 'Bearer ' . $cf['api_token'],
                            'Content-Type'  => 'application/json',
                        ],
                        'body' => wp_json_encode(['purge_everything' => true]),
                    ]
                );
                if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
                    \WP_CLI::error('Purge failed: ' . (is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp)));
                    return -1;
                }
                \WP_CLI::success('All Cloudflare cache purged on zone ' . $cf['zone_id']);
                return 0;

            default:
                \WP_CLI::error("Unknown action `$action`.");
                return -1;
        }
    }

    /**
     * Manage Phase 2 configuration (advanced optimization toggles + coverage).
     *
     * ## OPTIONS
     *
     * <action>
     * : get | coverage | reset
     *
     * [--format=<format>]
     * : Output format (table|json) — default table.
     *
     * ## EXAMPLES
     *
     *     $ wp lwsoptimize phase2 get
     *     $ wp lwsoptimize phase2 coverage
     *     $ wp lwsoptimize phase2 reset
     *
     * @when after_wp_load
     */
    public static function phase2($args, $assoc_args) {
        if (empty($args[0])) {
            \WP_CLI::error('Please specify an action: get, coverage, reset');
            return -1;
        }
        $action      = $args[0];
        $json_output = isset($assoc_args['format']) && $assoc_args['format'] === 'json';
        $options     = get_option('lws_optimize_config_array', []);
        $phase2      = $options['phase2'] ?? [];

        switch ($action) {
            case 'get':
                if ($json_output) {
                    \WP_CLI::line(json_encode($phase2, JSON_PRETTY_PRINT));
                    return 0;
                }
                if (empty($phase2)) {
                    \WP_CLI::warning('No phase 2 configuration set.');
                    return 1;
                }
                \WP_CLI::success('Phase 2 configuration:');
                foreach ($phase2 as $k => $v) {
                    \WP_CLI::line(sprintf('  %-30s %s', $k . ':', is_scalar($v) ? $v : json_encode($v)));
                }
                return 0;

            case 'coverage':
                $coverage = (array) get_option('lwsop_phase2_coverage', []);
                if ($json_output) {
                    \WP_CLI::line(json_encode($coverage, JSON_PRETTY_PRINT));
                    return 0;
                }
                if (empty($coverage)) {
                    \WP_CLI::warning('No coverage data collected yet.');
                    return 1;
                }
                \WP_CLI::success('Phase 2 coverage:');
                foreach ($coverage as $k => $v) {
                    \WP_CLI::line(sprintf('  %-30s %s', $k . ':', is_scalar($v) ? $v : json_encode($v)));
                }
                return 0;

            case 'reset':
                if (isset($options['phase2'])) {
                    unset($options['phase2']);
                    update_option('lws_optimize_config_array', $options);
                }
                delete_option('lwsop_phase2_coverage');
                \WP_CLI::success('Phase 2 configuration and coverage reset.');
                return 0;

            default:
                \WP_CLI::error("Unknown action `$action`.");
                return -1;
        }
    }

}