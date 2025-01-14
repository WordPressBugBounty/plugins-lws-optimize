<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeAutoPurge
{
    public function start_autopurge() {
        // // Remove the cache for the page where the modified comment is located
        add_action('wp_insert_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        add_action('deleted_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        add_action('trashed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        add_action('spammed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        add_action('unspammed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);
        add_action('untrashed_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);

        // // Remove the cache on the page currently getting modified/deleted
        add_action('wp_insert_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
        add_action('edit_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
        add_action('save_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
        // Betheme compatibility
        add_action('wp_ajax_updatevbview', [$this, 'lwsop_remove_cache_post_change_betheme'], 30, 2);

        add_action('deleted_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('trashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('spammed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('unspammed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('untrashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);

        add_action('woocommerce_add_to_cart', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
        add_action('woocommerce_cart_item_removed', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
        add_action('woocommerce_cart_item_restored', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'lwsop_remove_fb_cache_on_cart_update']);
    }

    public function purge_specified_url()
    {
        $specified = $this->optimize_options['filebased_cache']['specified'] ?? [];
        foreach ($specified as $url) {
            if ($url == null) {
                continue;
            }
            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($url);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
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
            $uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);
            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
    }

    /**
     * Clear cache whenever a post is modified
     */
    public function lwsop_remove_cache_post_change($post_id, $post)
    {

        // If WooCommerce is active, then remove the shop cache when adding/modifying new products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = \wc_get_page_id('shop');
            $uri = get_permalink($shop_id);
            $uri = parse_url($uri)['path'];
            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);
        }

        $uri = get_permalink($post_id);
        $uri = parse_url($uri)['path'];
        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

        apply_filters("lws_optimize_clear_filebased_cache", $file);

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
        $this->purge_specified_url();
    }

    /**
     * Clear cache whenever a post status is changed
     */
    public function lwsop_remove_cache_post_change_specific($post_id, $status)
    {
        $post = get_post($post_id);

        // If WooCommerce is active, then remove the shop cache when removing products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = \wc_get_page_id('shop');

            $uri = get_permalink($shop_id);
            $uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);
        }

        $uri = get_permalink($post_id);
        $uri = parse_url($uri)['path'];

        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

        apply_filters("lws_optimize_clear_filebased_cache", $file);

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
        $this->purge_specified_url();
    }

    // BeTheme support
    public function lwsop_remove_cache_post_change_betheme() {
	    $post_id = $_POST['pageid'];

        $uri = get_permalink($post_id);
        $uri = parse_url($uri)['path'];
        $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

        apply_filters("lws_optimize_clear_filebased_cache", $file);

        $url = str_replace("https://", "", get_site_url());
        $url = str_replace("http://", "", $url);
        $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
        $this->purge_specified_url();
    }

    /**
     * WooCommerce-specific actions ; Remove the cache for the checkout page and the cart page when the later is modified
     */
    public function lwsop_remove_fb_cache_on_cart_update()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $cart_id = \wc_get_page_id('cart');

            $uri = get_permalink($cart_id);
            $uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $checkout_id = \wc_get_page_id('checkout');

            $uri = get_permalink($checkout_id);
            $uri = parse_url($uri)['path'];

            $file = $GLOBALS['lws_optimize']->lwsOptimizeCache->lwsop_set_cachedir($uri);

            apply_filters("lws_optimize_clear_filebased_cache", $file);

            $url = str_replace("https://", "", get_site_url());
            $url = str_replace("http://", "", $url);
            $GLOBALS['lws_optimize']->cloudflare_manager->lws_optimize_clear_cloudflare_cache("purge", array($url));
            $this->purge_specified_url();
        }
    }
}
