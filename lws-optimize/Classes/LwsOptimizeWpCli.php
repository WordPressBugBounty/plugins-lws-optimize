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
                        'next_clear' => $next ? date('Y-m-d H:i:s', $next) : 0,
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
                            \WP_CLI::line('*  Next preload scheduled for: ' . date('Y-m-d H:i:s', $next));
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
                            'next_clear' => date('Y-m-d H:i:s', $next),
                            'next' => $next,
                        ]));
                    } else {
                        \WP_CLI::success("Next preload scheduled for: " . date('Y-m-d H:i:s', $next));
                    }
                } else {
                    if ($json_output) {
                        \WP_CLI::line(json_encode([
                            'next_clear' => date('Y-m-d H:i:s', 0),
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
     * : The action to perform on memcached (status|activate|deactivate)
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
            \WP_CLI::error('Please specify an action: status, activate, or deactivate');
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
                if ($redis) {
                    \WP_CLI::error('RedisCache plugin is activated, Memcached cannot be used at the same time.');
                    return -1;
                }

                if ($state && $memcached_state) {
                    \WP_CLI::success('Memcached is already activated.');
                    return 0;
                }

                if (!$memcached_state) {
                    \WP_CLI::error('Memcached module is not available/activated on this server.');
                    return -1;
                }

                $options['memcached']['state'] = "true";
                if (update_option('lws_optimize_config_array', $options)) {
                    // If the option is activated, we need to create the object-cache.php file
                    @unlink(LWSOP_OBJECTCACHE_PATH);
                    if (!file_exists(LWSOP_OBJECTCACHE_PATH)) {
                        file_put_contents(LWSOP_OBJECTCACHE_PATH, file_get_contents(LWS_OP_DIR . '/views/object-cache.php'));
                    }

                    \WP_CLI::success('Memcached activated.');
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
                    // If the option is deactivated, we need to remove the object-cache.php file
                    @unlink(LWSOP_OBJECTCACHE_PATH);

                    \WP_CLI::success('Memcached deactivated.');
                    return 0;
                } else {
                    \WP_CLI::error('Failed to deactivate Memcached.');
                    return -1;
                }
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
     * : The action to perform on configuration (status|clear|activate|deactivate|basic|advanced|complete)
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

}