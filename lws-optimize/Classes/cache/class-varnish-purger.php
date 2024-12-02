<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package
 */

/**
 * Description of Varnish_Purger
 *
 * @package
 * @subpackage /admin
 * @author     LWS
 */
class Varnish_Purger extends Purger
{
    private $ipxchange;
    /**
     * Initialize the class and set its properties.
     *
     * @since    2.0.0
     */
    public function __construct($ipxchange = false) {
        $this->ipxchange = $ipxchange;
    }

    /**
     * Purge all.
     */
    public function purge_all()
    {
        // Flush original WP domain
        //wp_remote_request(get_site_url(), array('method' => 'PURGEALL'));
        if ($this->ipxchange) {
            $ipXchange_IP = dns_get_record("cron.kghkhkhkh.fr")[0]['ip'] ?? false;
            $host = $_SERVER['SERVER_NAME'] ?? false;

            if ($ipXchange_IP && $host) {
                wp_remote_request(str_replace($host, $ipXchange_IP, get_site_url()), array('method' => 'FULLPURGE', 'Host' => $host));

            }
        } else {
            wp_remote_request(get_site_url(), array('method' => 'FULLPURGE'));
        }
    }

    /**
     * Purge url.
     *
     * @param string $url URL.
     * @param bool   $feed Feed or not.
     */
    public function purge_url($url, $feed = true)
    {
        global $lws_cache_admin;

        /**
         * Filters the URL to be purged.
         *
         * @since 1.0
         *
         * @param string $url URL to be purged.
         */
        $url_new = apply_filters('rt_lws_cache_purge_url', $url);

        $this->log('- Purging URL | ' . $url);

        $parse = wp_parse_url($url);

        if (! isset($parse['path'])) {
            $parse['path'] = '';
        }

        if ($this->ipxchange) {
            $ipXchange_IP = dns_get_record("cron.kghkhkhkh.fr")[0]['ip'] ?? false;
            $host = $_SERVER['SERVER_NAME'] ?? false;

            if ($ipXchange_IP && $host) {
                wp_remote_request(str_replace($host, $ipXchange_IP, $url), array('method' => 'PURGE', 'Host' => $host));

            }
        } else {
            wp_remote_request($url . '/', array('method' => 'PURGE'));
        }
    }

    /**
     * Custom purge urls.
     */
    public function custom_purge_urls()
    {
        global $lws_cache_admin;

        $purge_urls = isset($lws_cache_admin->options['purge_url']) && ! empty($lws_cache_admin->options['purge_url']) ?
            explode("\r\n", $lws_cache_admin->options['purge_url']) : array();

        if (is_array($purge_urls) && ! empty($purge_urls)) {
            foreach ($purge_urls as $purge_url) {
                $this->purge_url($purge_url);
            }
        }
    }
}