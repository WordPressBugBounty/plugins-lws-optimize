<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeAutoPurge
{
    public function start_autopurge()
    {

        add_action('comment_post', [$this, 'lws_optimize_clear_cache_on_comment', 10, 2]);
        add_action('edit_comment', [$this, 'lws_optimize_clear_cache_on_comment', 10, 2]);
        add_action('transition_comment_status', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 2);

        add_action('post_updated', [$this, 'lwsop_remove_cache_post_change'], 10, 2);

        // Betheme compatibility
        add_action('wp_ajax_updatevbview', [$this, 'lwsop_remove_cache_post_change_betheme'], 10, 0);

        // WooCommerce cart hooks - consolidated
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('woocommerce_add_to_cart', [$this, 'lwsop_remove_fb_cache_on_cart_update'], 10, 0);
            add_action('woocommerce_cart_item_removed', [$this, 'lwsop_remove_fb_cache_on_cart_update'], 10, 0);
            add_action('woocommerce_cart_item_restored', [$this, 'lwsop_remove_fb_cache_on_cart_update'], 10, 0);
            add_action('woocommerce_after_cart_item_quantity_update', [$this, 'lwsop_remove_fb_cache_on_cart_update'], 10, 0);
        }

        add_action('deleted_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('trashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('untrashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);

        add_action('customize_save_after', [$this, 'lwsop_remove_cache_customize_saved'], 10, 2);
    }

    // After updating the "Customize" settings, clear the cache
    public function lwsop_remove_cache_customize_saved($manager) {
        apply_filters("lws_optimize_clear_all_filebased_cache", "customize_save_after");
    }

    public function purge_specified_url()
    {
        $config_array = get_option('lws_optimize_config_array', []);
        $specified = $config_array['filebased_cache']['specified'] ?? [];
        $action = current_filter();

        foreach ($specified as $url) {
            if ($url == null) {
                continue;
            }

            apply_filters("lws_optimize_clear_filebased_cache", $url, $action, true);
        }
    }

    /**
     * Clear cache whenever a new comment is posted
     */
    public function lws_optimize_clear_cache_on_comment($comment_id, $comment)
    {
        $post_id = $comment->comment_post_ID;
        $action = current_filter();

        $uri = get_permalink($post_id);
        $this->purge_specified_url();

        apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
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

            apply_filters("lws_optimize_clear_filebased_cache", $uri, $action . "_woocommerce", true);
        }

        $uri = get_permalink($post_id);
        $this->purge_specified_url();

        apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
    }

    /**
     * Clear cache whenever a post status is changed
     */
    public function lwsop_remove_cache_post_change_specific($post_id, $status)
    {
        $post = get_post($post_id);

        $post_name = site_url() . "/" . $post->post_name;
        // Remove '__trashed' suffix if present
        if (strpos($post_name, '__trashed') !== false) {
            $post_name = str_replace('__trashed', '', $post_name);
        }

        $action = current_filter();

        // If WooCommerce is active, then remove the shop cache when removing products
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && $post->post_type == "product") {
            $shop_id = \wc_get_page_id('shop');

            $uri = get_permalink($shop_id);

            apply_filters("lws_optimize_clear_filebased_cache", $uri, $action . "_woocommerce", true);
        }

        $uri = get_permalink($post_id);
        $this->purge_specified_url();

        apply_filters("lws_optimize_clear_filebased_cache", $post_name, $action, true);
    }

    // BeTheme support
    public function lwsop_remove_cache_post_change_betheme()
    {
        $post_id = $_POST['pageid'];
        $action = current_filter();

        $uri = get_permalink($post_id);
        $this->purge_specified_url();

        apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
    }

    /**
     * WooCommerce-specific actions ; Remove the cache for the checkout page and the cart page when the later is modified
     */
    public function lwsop_remove_fb_cache_on_cart_update()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $cart_id = \wc_get_page_id('cart');
            $checkout_id = \wc_get_page_id('checkout');

            $uri = get_permalink($cart_id);
            $uri_checkout = get_permalink($checkout_id);

            $action = current_filter();

            apply_filters("lws_optimize_clear_filebased_cache", $uri, $action . "_woocommerce", true);
            apply_filters("lws_optimize_clear_filebased_cache", $uri_checkout, $action . "_woocommerce", true);

            $this->purge_specified_url();
        }
    }
}
