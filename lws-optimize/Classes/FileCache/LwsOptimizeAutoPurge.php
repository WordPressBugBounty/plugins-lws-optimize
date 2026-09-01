<?php

namespace Lws\Classes\FileCache;

class LwsOptimizeAutoPurge
{
    public function start_autopurge()
    {

        add_action('comment_post', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 1);
        add_action('edit_comment', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 1);
        add_action('transition_comment_status', [$this, 'lws_optimize_clear_cache_on_comment'], 10, 1);

        add_action('post_updated', [$this, 'lwsop_remove_cache_post_change'], 10, 2);
        add_action('publish_post', [$this, 'lwsop_remove_cache_post_change'], 10, 2);

        add_action('woocommerce_product_set_stock', [$this, 'lwsop_remove_cache_stock_change'], 10, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'lwsop_remove_cache_stock_change'], 10, 1);

        // Betheme compatibility
        add_action('wp_ajax_updatevbview', [$this, 'lwsop_remove_cache_post_change_betheme'], 10, 0);

        add_action('deleted_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('trashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);
        add_action('untrashed_post', [$this, 'lwsop_remove_cache_post_change_specific'], 10, 2);

        add_action('customize_save_after', [$this, 'lwsop_remove_cache_customize_saved'], 10, 2);
    }

    // After updating the "Customize" settings, clear the cache
    public function lwsop_remove_cache_customize_saved($manager) {
        apply_filters("lws_optimize_clear_all_filebased_cache", "customize_save_after");
    }

    // Note: WooCommerce cart hooks (add_to_cart, cart_item_removed,
    // cart_item_restored, after_cart_item_quantity_update) intentionally do
    // NOT purge anything here. Cart/checkout/receipt/confirmation/my-account
    // pages are already excluded from the file-based cache (see
    // LwsOptimizeFileCache::lwsop_page_to_ignore()), so there was never
    // anything to purge for them, while the purge itself was firing a
    // site-wide edge-cache purge + opcache_reset() + wp_cache_flush() on
    // every cart action — see readme/changelog for details.

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
    public function lws_optimize_clear_cache_on_comment($comment_id)
    {
        $comment = get_comment( $comment_id );
        if ($comment) {
            $post_id = $comment->comment_post_ID;
            $action = current_filter();

            $uri = get_permalink($post_id);
            $this->purge_specified_url();

            apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
        }
    }

    /**
     * Post types/states that must never trigger a cache purge on their own
     * (e.g. attachments are updated several times in a row by the media
     * uploader, which would otherwise cause repeated full purges).
     */
    private function lwsop_should_skip_purge_for_post($post_id, $post)
    {
        $excluded_post_types = ['attachment', 'revision', 'nav_menu_item'];

        if (!$post || in_array($post->post_type, $excluded_post_types, true)) {
            return true;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return true;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return true;
        }

        return false;
    }

    /**
     * Clear cache whenever a post is modified
     */
    public function lwsop_remove_cache_post_change($post_id, $post)
    {
        if ($this->lwsop_should_skip_purge_for_post($post_id, $post)) {
            return;
        }

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
     * Products whose cache has already been queued for purge during this
     * request. Stock hooks can fire once per order line item (and again for
     * a variation's parent), so this collapses repeats onto the same URL
     * into a single purge call instead of one per firing.
     */
    private $purged_stock_urls = [];

    /**
     * Clear cache when stock is changed programmatically (order completion,
     * REST API, bulk-edit, subscription renewal, ...) without necessarily
     * going through post_updated. Reuses the same targeted, throttled purge
     * path as every other trigger in this class.
     */
    public function lwsop_remove_cache_stock_change($product)
    {
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $action = current_filter();

        // get_permalink() on WC_Product already resolves variations to their
        // parent product's URL, so no separate variation-vs-parent handling
        // is needed here.
        $uri = $product->get_permalink();
        if ($uri && !isset($this->purged_stock_urls[$uri])) {
            $this->purged_stock_urls[$uri] = true;
            apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
        }

        $shop_id = \wc_get_page_id('shop');
        $shop_uri = $shop_id ? get_permalink($shop_id) : false;
        if ($shop_uri && !isset($this->purged_stock_urls[$shop_uri])) {
            $this->purged_stock_urls[$shop_uri] = true;
            apply_filters("lws_optimize_clear_filebased_cache", $shop_uri, $action, true);
        }
    }

    /**
     * Clear cache whenever a post status is changed
     */
    public function lwsop_remove_cache_post_change_specific($post_id, $status)
    {
        $post = get_post($post_id);

        if ($post && !$this->lwsop_should_skip_purge_for_post($post_id, $post)) {
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
    }

    // BeTheme support
    public function lwsop_remove_cache_post_change_betheme()
    {
        // BeTheme's own nonce — verify it instead of trusting $_POST['pageid'] blindly.
        // Fall back to current_user_can() so the handler is never callable by anonymous users.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mfn-vb')) {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['code' => 'FORBIDDEN'], 403);
            }
        }

        $post_id = isset($_POST['pageid']) ? absint(wp_unslash($_POST['pageid'])) : 0;
        if (!$post_id || get_post_status($post_id) === false) {
            wp_send_json_error(['code' => 'INVALID_PAGEID'], 400);
        }

        // Capability check on the specific post being edited
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['code' => 'FORBIDDEN'], 403);
        }

        $action = current_filter();
        $uri = get_permalink($post_id);
        $this->purge_specified_url();

        apply_filters("lws_optimize_clear_filebased_cache", $uri, $action, true);
    }

}
