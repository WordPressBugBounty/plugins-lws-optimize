<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeAutoPurge
{
    public function start_autopurge()
    {
        // Comment-related hooks - consolidated into a single hook group
        $comment_hooks = ['comment_post', 'edit_comment', 'transition_comment_status'];
        foreach ($comment_hooks as $hook) {
            add_action($hook, [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        }

        // Post update hooks - using only the most reliable hook with high priority
        add_action('post_updated', [$this, 'lwsop_remove_cache_post_change'], 999, 2);

        // Betheme compatibility
        add_action('wp_ajax_updatevbview', [$this, 'lwsop_remove_cache_post_change_betheme'], 30, 2);

        // WooCommerce cart hooks - consolidated
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $cart_hooks = [
                'woocommerce_add_to_cart',
                'woocommerce_cart_item_removed',
                'woocommerce_cart_item_restored',
                'woocommerce_after_cart_item_quantity_update'
            ];
            foreach ($cart_hooks as $hook) {
                add_action($hook, [$this, 'lwsop_remove_fb_cache_on_cart_update']);
            }
        }

        // Post status change hooks - consolidated
        $post_status_hooks = ['deleted_post', 'trashed_post', 'untrashed_post'];
        foreach ($post_status_hooks as $hook) {
            add_action($hook, [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        }
    }

    public function purge_specified_url()
    {
        $config_array = get_option('lws_optimize_config_array', []);

        $specified = $config_array['filebased_cache']['specified'] ?? [];
        foreach ($specified as $url) {
            if ($url == null) {
                continue;
            }
            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($url);
            $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($url, true);

            $action = current_filter();
            // Do not purge if there is no cache file
            if ($file !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
                if ($file_mobile !== null) {
                    apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
                }
                $url = str_replace("https://", "", get_site_url());
                $url = str_replace("http://", "", $url);
                $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            }
        }
    }

    /**
     * Clear cache whenever a new comment is posted
     */
    public function lws_optimize_clear_cache_on_comment($comment_id, $comment)
    {
        $post_id = $comment->comment_post_ID;

        $uri = get_page_uri($post_id);
        $uri = get_site_url(null, $uri);
        //$uri = parse_url($uri)['path'];

        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
        $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

        $action = current_filter();
        // Do not purge if there is no cache file
        if ($file !== null) {
            apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
            if ($file_mobile !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
            }
            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
        }
    }

    /**
     * Clear cache whenever a post is modified
     */
    public function lwsop_remove_cache_post_change($post_id, $post)
    {
        $action = current_filter();

        // If WooCommerce is active, then remove the shop cache when adding/modifying new products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = \wc_get_page_id('shop');
            $uri = get_permalink($shop_id);
            //$uri = parse_url($uri)['path'];
            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
            $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

            $logger = fopen($GLOBALS['lws_optimize']->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] AutoPurge Files: ' . PHP_EOL);
            fwrite($logger, 'File: ' . $file . PHP_EOL);
            fwrite($logger, 'File Mobile: ' . $file_mobile . PHP_EOL);
            fclose($logger);



            // Do not purge if there is no cache file
            if ($file !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
                if ($file_mobile !== null) {
                    apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
                }
            }
        }

        $uri = get_permalink($post_id);

        //$uri = parse_url($uri)['path'];
        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
        $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

        // Do not purge if there is no cache file
        if ($file !== null) {
            if ($file_mobile !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
            }
            apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
        }
    }

    /**
     * Clear cache whenever a post status is changed
     */
    public function lwsop_remove_cache_post_change_specific($post_id, $status)
    {
        $post = get_post($post_id);
        $action = current_filter();

        // If WooCommerce is active, then remove the shop cache when removing products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = \wc_get_page_id('shop');

            $uri = get_permalink($shop_id);
            //$uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
            $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

            $logger = fopen($GLOBALS['lws_optimize']->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] AutoPurge Files: ' . PHP_EOL);
            fwrite($logger, 'File: ' . $file . PHP_EOL);
            fwrite($logger, 'File Mobile: ' . $file_mobile . PHP_EOL);
            fclose($logger);

            // Do not purge if there is no cache file
            if ($file !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
                if ($file_mobile !== null) {
                    apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
                }
            }
        }

        $uri = get_permalink($post_id);
        //$uri = parse_url($uri)['path'];

        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
        $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

        // Do not purge if there is no cache file
        if ($file !== null) {
            apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
            if ($file_mobile !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
            }
            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
        }
    }

    // BeTheme support
    public function lwsop_remove_cache_post_change_betheme()
    {
        $post_id = $_POST['pageid'];

        $uri = get_permalink($post_id);
        //$uri = parse_url($uri)['path'];
        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
        $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

        $action = current_filter();
        // Do not purge if there is no cache file
        if ($file !== null) {
            apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
            if ($file_mobile !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
            }
            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
        }
    }

    /**
     * WooCommerce-specific actions ; Remove the cache for the checkout page and the cart page when the later is modified
     */
    public function lwsop_remove_fb_cache_on_cart_update()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $cart_id = \wc_get_page_id('cart');

            $uri = get_permalink($cart_id);
            //$uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
            $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

            $action = current_filter();
            // Do not purge if there is no cache file
            if ($file !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
                if ($file_mobile !== null) {
                    apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
                }
            }

            $checkout_id = \wc_get_page_id('checkout');

            $uri = get_permalink($checkout_id);
            //$uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
            $file_mobile = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri, true);

            // Do not purge if there is no cache file
            if ($file !== null) {
                apply_filters("lws_optimize_clear_filebased_cache", $file, $action, true);
                if ($file_mobile !== null) {
                    apply_filters("lws_optimize_clear_filebased_cache", $file_mobile, $action, true);
                }
                $url = str_replace("https://", "", get_site_url());
                $url = str_replace("http://", "", $url);
                $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
                $this->purge_specified_url();
            }
        }
    }
}
