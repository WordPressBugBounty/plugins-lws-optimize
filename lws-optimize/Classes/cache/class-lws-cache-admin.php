<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.lws.fr
 * @since      2.0.0
 *
 * @package    lwscache
 * @subpackage lwscache/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    lwscache
 * @subpackage lwscache/admin
 * @author     LWS
 */
class LWSCache_Admin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Various settings tabs.
     *
     * @since    1.0
     * @access   private
     * @var      string    $settings_tabs    Various settings tabs.
     */
    private $settings_tabs;

    /**
     * Purge options.
     *
     * @since    1.0
     * @access   public
     * @var      string    $options    Purge options.
     */
    public $options;

    /**
     * WP-CLI Command.
     *
     * @since    1.0
     * @access   public
     * @var      string    $options    WP-CLI Command.
     */
    const WP_CLI_COMMAND = 'lws-cache';

    /**
     * Initialize the class and set its properties.
     *
     * @since      1.0
     * @param      string $plugin_name       The name of this plugin.
     * @param      string $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;

        /**
         * Define settings tabs
         */
        $this->settings_tabs = apply_filters(
            'rt_lws_cache_settings_tabs',
            array()
        );

        $this->options = $this->lws_cache_settings();
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_styles($hook)
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in LWSCache_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The LWSCache_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        if (substr(get_current_screen()->id, 0, 29) == "toplevel_page_options-general") {

            // Hide all errors for the client
            error_reporting(0);
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/lws-cache-admin.js', array('jquery'), $this->version, false);

            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/lws-cache-admin.css', array(), $this->version, 'all');
            wp_enqueue_script("lwscache_bootstrap_js", plugin_dir_url(__FILE__) . 'js/bootstrap.min.js', array('jquery'), $this->version, false);
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/lws-cache-admin.js', array('jquery'), $this->version, false);
            wp_enqueue_style('lwscache_bootstrap_css', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css', array(), $this->version, false);
            wp_enqueue_style('lwscache-Poppins', 'https://fonts.googleapis.com/css?family=Poppins');
        }
        if ('settings_page_nginx' !== $hook) {
            return;
        }

        wp_enqueue_style($this->plugin_name . '-icons', plugin_dir_url(__FILE__) . 'icons/css/nginx-fontello.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts($hook)
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in LWSCache_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The LWSCache_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        if ('settings_page_nginx' !== $hook) {
            return;
        }


        $do_localize = array(
            'purge_confirm_string' => esc_html__('Purging entire cache is not recommended. Would you like to continue?', 'lwscache'),
        );
        wp_localize_script($this->plugin_name, 'lws_cache', $do_localize);
    }

    /**
     * Add admin menu.
     *
     * @since    2.0.0
     */
    public function lws_cache_admin_menu()
    {

        // if (is_multisite()) {
        //     add_menu_page(
        //         'LWSCache settings',
        //         'LWSCache',
        //         'manage_options',
        //         'options-general.php?page=nginx',
        //         array( &$this, 'lws_cache_setting_page' ),
        //         plugin_dir_url(__FILE__) . 'icons/lws_cache_menu.svg',
        //         1
        //     );
        // } else {
        //     add_menu_page(
        //         'LWSCache settings',
        //         'LWSCache',
        //         'manage_options',
        //         'options-general.php?page=nginx',
        //         array( &$this, 'lws_cache_setting_page' ),
        //         plugin_dir_url(__FILE__) . 'icons/lws_cache_menu.svg',
        //         150
        //     );
        // }
    }

    /**
     * Function to add toolbar purge link.
     *
     * @param object $wp_admin_bar Admin bar object.
     */
    public function lws_cache_toolbar_purge_link($wp_admin_bar)
    {
        $is_mutu = false;
        $is_cpanel = false;
        $t = explode('/', getcwd());
        if (!isset($t[1])) {
            return;
        }

        switch (explode('/', getcwd())[1]) {
            case 'htdocs':
                $is_mutu = true;
                $is_cpanel = false;
                break;
            case 'home':
                $is_cpanel = true;
                $is_mutu = false;
                break;
        }

        if (!$is_mutu && !$is_cpanel) {
            return;
        }

        if (! current_user_can('manage_options') || (($is_mutu && !isset($_SERVER['lwscache']) || $is_cpanel && !isset($_SERVER['HTTP_X_CACHE_ENGINE'])))) {
            return;
        }

        if (($is_mutu && $_SERVER['lwscache'] == "Off") || ($is_cpanel && $_SERVER['HTTP_X_CACHE_ENGINE'] == "0")) {
            return;
        }

        // if (is_admin()) {
        $lws_cache_urls = 'all';
        $link_title     = __('Purge LWSCache', 'lwscache');
        // } else {
        //     $lws_cache_urls = 'current-url';
        //     $link_title     = __('Purge Current Page', 'lwscache');
        // }

        $purge_url = add_query_arg(
            array(
                'lws_cache_action' => 'purge',
                'lws_cache_urls'   => $lws_cache_urls,
            )
        );

        $nonced_url = wp_nonce_url($purge_url, 'lws_cache-purge_all');

        $wp_admin_bar->add_menu(
            array(
                'id'    => 'lws-cache',
                'title' => __('LWSCache', 'lwscache'),
                'href'  => '/',
            )
        );
        $wp_admin_bar->add_menu(
            array(
                'parent' => 'lws-cache',
                'id'    => 'lws-cache-purge-all',
                'title' => $link_title,
                'href'  => $nonced_url,
                'meta'  => array('title' => $link_title),
            )
        );

        $path = wp_parse_url(get_site_url());
        if (!isset($path['path'])) {
            $path = "";
        } else {
            $path = $path['path'];
        }
        $wp_admin_bar->add_menu(
            array(
                'parent' => 'lws-cache',
                'id'    => 'lws-cache-settings',
                'title' => __('Settings', 'lwscache'),
                'href'  => $path . "/wp-admin/admin.php?page=options-general.php?page=nginx",
                'meta'  => array('title' => $link_title),
            )
        );
    }

    /**
     * Display settings.
     *
     * @global $string $pagenow Contain current admin page.
     *
     * @since    2.0.0
     */
    public function lws_cache_setting_page()
    {
        include_once plugin_dir_path(__FILE__) . 'partials/lws-cache-admin-display.php';
    }

    /**
     * Default settings.
     *
     * @since    2.0.0
     * @return array
     */
    public function lws_cache_default_settings()
    {
        return array(
            'enable_purge'                     => 1,
            'cache_method'                     => 'enable_fastcgi',
            'purge_method'                     => 'get_request',
            'enable_map'                       => 0,
            'enable_log'                       => 1,
            'log_level'                        => 'INFO',
            'log_filesize'                     => '5',
            'enable_stamp'                     => 0,
            'purge_homepage_on_edit'           => 1,
            'purge_homepage_on_del'            => 1,
            'purge_archive_on_edit'            => 1,
            'purge_archive_on_del'             => 1,
            'purge_archive_on_new_comment'     => 1,
            'purge_archive_on_deleted_comment' => 1,
            'purge_page_on_mod'                => 1,
            'purge_page_on_new_comment'        => 1,
            'purge_page_on_deleted_comment'    => 1,
            'redis_hostname'                   => '127.0.0.1',
            'redis_port'                       => '6379',
            'redis_prefix'                     => 'nginx-cache:',
            'purge_url'                        => '',
            'redis_enabled_by_constant'        => 0,
        );
    }

    /**
     * Get settings.
     *
     * @since    2.0.0
     */
    public function lws_cache_settings()
    {
        $options = get_site_option(
            'rt_wp_lws_cache_options',
            array(
                'enable_purge'                     => 1,
                'cache_method'                     => 'enable_fastcgi',
                'purge_method'                     => 'get_request',
                'enable_map'                       => 0,
                'enable_log'                       => 1,
                'log_level'                        => 'INFO',
                'log_filesize'                     => '5',
                'enable_stamp'                     => 0,
                'purge_homepage_on_edit'           => 1,
                'purge_homepage_on_del'            => 1,
                'purge_archive_on_edit'            => 1,
                'purge_archive_on_del'             => 1,
                'purge_archive_on_new_comment'     => 1,
                'purge_archive_on_deleted_comment' => 1,
                'purge_page_on_mod'                => 1,
                'purge_page_on_new_comment'        => 1,
                'purge_page_on_deleted_comment'    => 1,
                'redis_hostname'                   => '127.0.0.1',
                'redis_port'                       => '6379',
                'redis_prefix'                     => 'nginx-cache:',
                'purge_url'                        => '',
                'redis_enabled_by_constant'        => 0,
            )
        );

        $data = wp_parse_args(
            $options,
            $this->lws_cache_default_settings()
        );

        $is_redis_enabled = (
            defined('RT_WP_LWS_CACHE_REDIS_HOSTNAME') &&
            defined('RT_WP_LWS_CACHE_REDIS_PORT') &&
            defined('RT_WP_LWS_CACHE_REDIS_PREFIX')
        );

        if (! $is_redis_enabled) {
            return $data;
        }

        $data['redis_enabled_by_constant'] = $is_redis_enabled;
        $data['enable_purge']              = $is_redis_enabled;
        $data['cache_method']              = 'enable_redis';
        $data['redis_hostname']            = RT_WP_LWS_CACHE_REDIS_HOSTNAME;
        $data['redis_port']                = RT_WP_LWS_CACHE_REDIS_PORT;
        $data['redis_prefix']              = RT_WP_LWS_CACHE_REDIS_PREFIX;

        return $data;
    }

    /**
     * LWS cache setting link function.
     *
     * @param array $links links.
     *
     * @return mixed
     */
    public function lws_cache_settings_link($links)
    {
        if (is_network_admin()) {
            $setting_page = 'settings.php';
        } else {
            $setting_page = 'admin.php?page=options-general.php';
        }

        $settings_link = '<a href="' . network_admin_url($setting_page . '?page=nginx') . '">' . __('Settings', 'lwscache') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Retrieve the asset path.
     *
     * @since     1.0
     * @return    string    asset path of the plugin.
     */
    public function functional_asset_path()
    {
        $log_path = WP_CONTENT_DIR . '/uploads/lws-cache/';

        return apply_filters('nginx_asset_path', $log_path);
    }

    /**
     * Retrieve the asset url.
     *
     * @since     1.0
     * @return    string    asset url of the plugin.
     */
    public function functional_asset_url()
    {
        $log_url = WP_CONTENT_URL . '/uploads/lws-cache/';

        return apply_filters('nginx_asset_url', $log_url);
    }

    /**
     * Get latest news.
     *
     * @since     1.0
     */
    public function lws_cache_get_feeds()
    {
        // Get RSS Feed(s).
        require_once ABSPATH . WPINC . '/feed.php';

        $maxitems  = 0;
        $rss_items = array();

        // Get a SimplePie feed object from the specified feed source.
        $rss = fetch_feed('https://rtcamp.com/blog/feed/');

        if (! is_wp_error($rss)) { // Checks that the object is created correctly.
            // Figure out how many total items there are, but limit it to 5.
            $maxitems = $rss->get_item_quantity(5);
            // Build an array of all the items, starting with element 0 (first element).
            $rss_items = $rss->get_items(0, $maxitems);
        }
?>
        <ul>
            <?php
            if (0 === $maxitems) {
                echo '<li>' . esc_html_e('No items', 'lwscache') . '.</li>';
            } else {
                // Loop through each feed item and display each item as a hyperlink.
                foreach ($rss_items as $item) {
            ?>
                    <li>
                        <?php
                        printf(
                            '<a href="%s" title="%s">%s</a>',
                            esc_url($item->get_permalink()),
                            esc_attr__('Posted ', 'lwscache') . esc_attr($item->get_date('j F Y | g:i a')),
                            esc_html($item->get_title())
                        );
                        ?>
                    </li>
            <?php
                }
            }
            ?>
        </ul>
<?php
        die();
    }

    /**
     * Add time stamps in html.
     */
    public function add_timestamps()
    {
        global $pagenow;

        if (is_admin() || 1 !== (int) $this->options['enable_purge'] || 1 !== (int) $this->options['enable_stamp']) {
            return;
        }

        if (! empty($pagenow) && 'wp-login.php' === $pagenow) {
            return;
        }

        foreach (headers_list() as $header) {
            list($key, $value) = explode(':', $header, 2);
            $key                 = strtolower($key);
            if ('content-type' === $key && strpos(trim($value), 'text/html') !== 0) {
                return;
            }
            if ('content-type' === $key) {
                break;
            }
        }

        /**
         * Don't add timestamp if run from ajax, cron or wpcli.
         */
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        $timestamps = "\n<!--" .
            'Cached using lwscache on ' . current_time('mysql') . '. ' .
            'It took ' . get_num_queries() . ' queries executed in ' . timer_stop() . ' seconds.' .
            "-->\n" .
            '<!--Visit https://www.lws.fr/ for more details-->';

        echo wp_kses($timestamps, array());
    }

    /**
     * Get map
     *
     * @global object $wpdb
     *
     * @return string
     */
    public function get_map()
    {
        if (! $this->options['enable_map']) {
            return;
        }

        if (is_multisite()) {
            global $wpdb;

            $rt_all_blogs = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT blog_id, domain, path FROM ' . $wpdb->blogs . " WHERE site_id = %d AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0'",
                    $wpdb->siteid
                )
            );

            $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

            $rt_domain_map_sites = '';

            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") === $wpdb->dmtable) { // phpcs:ignore
                $rt_domain_map_sites = $wpdb->get_results("SELECT blog_id, domain FROM {$wpdb->dmtable} ORDER BY id DESC");
            }

            $rt_nginx_map       = '';
            $rt_nginx_map_array = array();

            if ($rt_all_blogs) {
                foreach ($rt_all_blogs as $blog) {
                    if (true === SUBDOMAIN_INSTALL) {
                        $rt_nginx_map_array[$blog->domain] = $blog->blog_id;
                    } else {
                        if (1 !== $blog->blog_id) {
                            $rt_nginx_map_array[$blog->path] = $blog->blog_id;
                        }
                    }
                }
            }

            if ($rt_domain_map_sites) {
                foreach ($rt_domain_map_sites as $site) {
                    $rt_nginx_map_array[$site->domain] = $site->blog_id;
                }
            }

            foreach ($rt_nginx_map_array as $domain => $domain_id) {
                $rt_nginx_map .= "\t" . $domain . "\t" . $domain_id . ";\n";
            }

            return $rt_nginx_map;
        }
    }

    /**
     * Update map
     */
    public function update_map()
    {
        if (is_multisite()) {
            $rt_nginx_map = $this->get_map();

            $fp = fopen($this->functional_asset_path() . 'map.conf', 'w+');
            if ($fp) {
                fwrite($fp, $rt_nginx_map);
                fclose($fp);
            }
        }
    }

    /**
     * Purge url when post status is changed.
     *
     * @global string $blog_id Blog id.
     * @global object $nginx_purger Nginx purger variable.
     *
     * @param string $new_status New status.
     * @param string $old_status Old status.
     * @param object $post Post object.
     */

    public function set_future_post_option_on_future_status($new_status, $old_status, $post)
    {
        global $blog_id, $nginx_purger;

        if (! $this->options['enable_purge']) {
            return;
        }

        $purge_status = array('publish', 'future');

        if (in_array($old_status, $purge_status, true) || in_array($new_status, $purge_status, true)) {
            $nginx_purger->log('Purge post on transition post STATUS from ' . $old_status . ' to ' . $new_status);
            $nginx_purger->purge_post($post->ID);
        }

        if (
            'future' === $new_status && $post && 'future' === $post->post_status &&
            (
                ('post' === $post->post_type || 'page' === $post->post_type) ||
                (
                    isset($this->options['custom_post_types_recognized']) && is_array($this->options['custom_post_types_recognized']) &&
                    in_array($post->post_type, $this->options['custom_post_types_recognized'], true)
                )
            )
        ) {
            $nginx_purger->log('Set/update future_posts option ( post id = ' . $post->ID . ' and blog id = ' . $blog_id . ' )');
            $this->options['future_posts'][$blog_id][$post->ID] = strtotime($post->post_date_gmt) + 60;
            update_site_option('rt_wp_lws_cache_options', $this->options);
        }
    }

    /**
     * Unset future post option on delete
     *
     * @global string $blog_id Blog id.
     * @global object $nginx_purger Nginx purger object.
     *
     * @param int $post_id Post id.
     */
    public function unset_future_post_option_on_delete($post_id)
    {
        global $blog_id, $nginx_purger;

        if (!isset($this->options['future_posts'][$blog_id]) || !is_array($this->options['future_posts'][$blog_id])) {
            return;
        }

        if (
            ! $this->options['enable_purge'] ||
            empty($this->options['future_posts']) ||
            empty($this->options['future_posts'][$blog_id]) ||
            isset($this->options['future_posts'][$blog_id][$post_id]) ||
            wp_is_post_revision($post_id)
        ) {
            return;
        }

        $nginx_purger->log('Unset future_posts option ( post id = ' . $post_id . ' and blog id = ' . $blog_id . ' )');

        unset($this->options['future_posts'][$blog_id][$post_id]);

        if (!count($this->options['future_posts'][$blog_id])) {
            unset($this->options['future_posts'][$blog_id]);
        }

        update_site_option('rt_wp_lws_cache_options', $this->options);
    }

    /**
     * Update map when new blog added in multisite.
     *
     * @global object $nginx_purger Nginx purger class object.
     *
     * @param string $blog_id blog id.
     */
    public function update_new_blog_options($site)
    {
        $blog_id = $site->site_id;
        global $nginx_purger;

        $nginx_purger->log("New site added ( id $blog_id )");
        $this->update_map();
        $nginx_purger->log("New site added to nginx map ( id $blog_id )");
        $helper_options = $this->lws_cache_default_settings();
        update_blog_option($blog_id, 'rt_wp_lws_cache_options', $helper_options);
        $nginx_purger->log("Default options updated for the new blog ( id $blog_id )");
    }

    /**
     * Purge all urls.
     * Purge current page cache when purging is requested from front
     * and all urls when requested from admin dashboard.
     *
     * @global object $nginx_purger
     */
    public function purge_all()
    {
        global $nginx_purger, $wp;

        $method = sanitize_text_field($_SERVER['REQUEST_METHOD']);

        if ('POST' === $method) {
            if (isset($_POST['lws_cache_action']))
                $action = sanitize_text_field($_POST['lws_cache_action']);
        } else {
            if (isset($_GET['lws_cache_action']))
                $action = sanitize_text_field($_GET['lws_cache_action']);
        }

        if (empty($action)) {
            return;
        }

        if (! current_user_can('manage_options')) {
            wp_die('Sorry, you do not have the necessary privileges to edit these options.');
        }

        if ('done' === $action) {
            add_action('admin_notices', array(&$this, 'display_notices'));
            add_action('network_admin_notices', array(&$this, 'display_notices'));
            return;
        }

        check_admin_referer('lws_cache-purge_all');

        $current_url = user_trailingslashit(home_url($wp->request));

        // if (! is_admin()) {
        //     $action       = 'purge_current_page';
        //     $redirect_url = $current_url;
        // } else {
        $redirect_url = add_query_arg(array('lws_cache_action' => 'done'));
        // }

        switch ($action) {
            case 'purge':
                $nginx_purger->purge_all();
                break;
            case 'purge_current_page':
                $nginx_purger->purge_url($current_url);
                break;
        }

        if ('purge' === $action) {
            /**
             * Fire an action after the entire cache has been purged whatever caching type is used.
             *
             * @since 1.0
             */
            do_action('rt_lws_cache_after_purge_all');
        }

        wp_redirect(esc_url_raw($redirect_url));
        exit();
    }

    /**
     * Dispay plugin notices.
     */
    public function display_notices()
    {
        echo '<div class="button_purge_done notice is-dismissible"><p>' . esc_html__('Purge initiated', 'lwscache') . '</p></div>';
    }
}
?>