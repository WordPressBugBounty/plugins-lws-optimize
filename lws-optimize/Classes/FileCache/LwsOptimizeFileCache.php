<?php

namespace Lws\Classes\FileCache;

use Lws\Classes\Front\LwsOptimizeCSSManager;
use Lws\Classes\Front\LwsOptimizeJSManager;

class LwsOptimizeFileCache
{
    private $base;
    private $cache_directory;
    private $content_type;
    private $page_type;
    private $need_cache;

    public function __construct($parent)
    {
        $this->base = $parent;
        include_once ABSPATH . "wp-includes/pluggable.php";
        $this->need_cache = $this->lwsop_check_need_cache();
        $this->lwsop_set_cachedir();
    }

    public function lwsop_clear_current_page_cache()
    {
        if (!function_exists("lws_optimize_delete_directory")) {
            function lws_optimize_delete_directory($dir, $stats = false)
            {
                global $stats;
                if (!file_exists($dir)) {
                    return false;
                }
                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    if (is_dir("$dir/$file")) {
                        lws_optimize_delete_directory("$dir/$file");
                    } else {
                        @unlink("$dir/$file");
                        if (file_exists("$dir/$file")) {
                            return false;
                        } else {
                            // If the file being deleted is from the LWSOptimize Cache, update the stats
                            // Stats will be updated depending on the file type (html/mobile_html/css/js)
                            if (preg_match("/\/cache\/lwsoptimize\//i", $dir) && $stats !== false && is_array($stats)) {
                                if (preg_match("/\.html/i", $file)) {
                                    $stats['desktop']['amount'] -= 1;
                                    $stats['desktop']['size'] -= filesize("$dir/$file");
                                } elseif (preg_match("/\/cache-mobile\//i", $dir)) {
                                    $stats['mobile']['amount'] -= 1;
                                    $stats['mobile']['size'] -= filesize("$dir/$file");
                                } elseif (preg_match("/\.(min\.css|css)/i", $file)) {
                                    $stats['css']['amount'] -= 1;
                                    $stats['css']['size'] -= filesize("$dir/$file");
                                } elseif (preg_match("/\.(min\.js|js)/i", $file)) {
                                    $stats['js']['amount'] -= 1;
                                    $stats['js']['size'] -= filesize("$dir/$file");
                                }
                            }
                        }
                    }
                }

                rmdir($dir);
                return !file_exists($dir);
            }
        }

        global $stats;
        $stats = get_option('lws_optimize_cache_statistics', [
            'desktop' => ['amount' => 0, 'size' => 0],
            'mobile' => ['amount' => 0, 'size' => 0],
            'css' => ['amount' => 0, 'size' => 0],
            'js' => ['amount' => 0, 'size' => 0],
        ]);

        lws_optimize_delete_directory($this->cache_directory, $stats);
    }

    public function lwsop_launch_cache()
    {
        // Don't launch cache if checks have revealed that this URL should not be cached
        if (!$this->need_cache || !$this->cache_directory) {
            return false;
        }

        // If there is already a cache file for this URL, then get it and echo it
        if (!empty(glob($this->cache_directory))) {
            $user_id = get_current_user_id();
            $extension = "html";
            if (file_exists($this->cache_directory . "index_$user_id.html")) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($this->cache_directory . "index_$user_id.html")) . ' GMT', true, 200);
                $extension = "html";
            } elseif (file_exists($this->cache_directory . "index_$user_id.xml")) {
                header('Content-type: text/xml');
                $extension = "xml";
            } elseif (file_exists($this->cache_directory . "index_$user_id.json")) {
                header('Content-type: application/json');
                $extension = "json";
            }


            $content = @file_get_contents($this->cache_directory . "index_$user_id.$extension");
            if ($content) {
                die($content);
            }
        }

        add_action('wp', array($this, "lwsop_detect_page_type"));
        add_action('get_footer', array($this, "lwsop_detect_page_type"));
        add_action('get_footer', function () {
            echo "<!--LWSOPTIMIZE_HERE_START_FOOTER-->";
        });

        if ($this->base->lwsop_check_option('webfont_optimize')['state'] == "true") {
            add_filter('style_loader_tag', [$this, 'lws_optimize_manage_frontend_webfont_optimize'], 10, 3);
        }

        if ($this->base->lwsop_check_option('deactivate_emoji')['state'] == "true") {
            add_filter('init', [$this, 'lws_optimize_manage_frontend_deactivate_emoji']);
        }

        if ($this->base->lwsop_check_option('eliminate_requests')['state'] == "true") {
            add_filter('style_loader_src', [$this, 'lws_optimize_manage_frontend_eliminate_requests']);
            add_filter('script_loader_src', [$this, 'lws_optimize_manage_frontend_eliminate_requests']);
        }

        ob_start(array($this, "callback"));
    }

    public function callback($buffer)
    {
        // Get the Content-Type of the current page (xml, json, html)
        $this->lwsop_detect_page_content_type($buffer);

        // We need to wait until now to check if the page type is excluded as we have no way of knowing the type beforehand
        if ($this->lwsop_page_has_been_excluded($buffer)) {
            return $buffer;
        }

        // We cannot cache the error page, otherwise even once the error is resolved or the page created, it won't appear
        if ((function_exists("http_response_code") && (http_response_code() === 404)) || is_404() || preg_match("/<body\sid\=\"error-page\">\s*<div\sclass\=\"wp-die-message\">/i", $buffer)) {
            return $buffer;
        }

        // If the page is password protected, then do not cache
        if (preg_match("/action\=[\'\"].+postpass.*[\'\"]/", $buffer)) {
            return $buffer . "<!-- Password protected content has been detected -->";
        }

        // We cannot cache the login page for obvious reasons
        if ($GLOBALS["pagenow"] == "wp-login.php") {
            return $buffer . "<!-- wp-login.php -->";
        }

        // Do not cache the page if the DONOTCACHEPAGE is defined (can be defined by a few plugins, like Wordfence or iThemes)
        if (defined('DONOTCACHEPAGE')) {
            return $buffer . "<!-- DONOTCACHEPAGE is defined as TRUE -->";
        }

        if ((is_single() || is_page()) && preg_match("/<input[^\>]+_wpcf7_captcha[^\>]+>/i", $buffer)) {
            return $buffer . "<!-- This page was not cached because ContactForm7's captcha -->";
        }

        // Check a list of pages that cannot be cached (core WP, specific plugin pages like WooCommerce, ...) as it would break or hinder the website
        if ($this->lwsop_page_to_ignore($buffer)) {
            return $buffer;
        }

        // Do not cache the page if it is a page preview (from Gutenberg, for example)
        if (is_preview()) {
            return $buffer . "<!-- not cached -->";
        }

        // Do not cache the page if it is a page preview (from Gutenberg, for example) (Alt version)
        if (isset($_GET["preview"])) {
            return $buffer . "<!-- not cached -->";
        }

        // If the current page is HTML but does not have the correct <html><body></body> structure, do not cache it.
        if ($this->content_type === "html" && !(preg_match('/<\s*html[^\>]*>/si', $buffer) && preg_match('/<\s*body[^\>]*>/si', $buffer) && preg_match('/<\/body\s*>/si', $buffer))) {
            return $buffer;
        }

        // If the page is a redirection (temp/permanent), do not cache the page. Otherwise,
        if (http_response_code() == 301 || http_response_code() == 302) {
            return $buffer;
        }

        $modified = $buffer;

        $cached_elements = [
            'css' => ['file' => 0, 'size' => 0],
            'js' => ['file' => 0, 'size' => 0],
            'desktop' => ['file' => 0, 'size' => 0],
            'mobile' => ['file' => 0, 'size' => 0]
        ];

        $original_data_array = get_option("lws_optimize_original_image", []);
        $media_data = $original_data_array['auto_update']['original_media'] ?? [];

        $media_to_update = [];

        if (!empty($media_data)) {
            foreach ($media_data as $media) {
                if (file_exists($media['path'])) {
                    $media_to_update[] = [
                        'original' => $media['original_url'],
                        'new' => $media['url']
                    ];
                }
            }
        }

        if ($this->base->lwsop_check_option('preload_css')['state'] == "true") {
            $preload = $this->base->lwsop_check_option('preload_css')['data']['links'] ?? [];
            $lwsOptimizeCssManager = new LwsOptimizeCSSManager($modified, $preload, []);
            $modified = $lwsOptimizeCssManager->preload_css();
        }

        if ($this->base->lwsop_check_option('preload_font')['state'] == "true") {
            $preload = $this->base->lwsop_check_option('preload_font')['data']['links'] ?? [];
            $lwsOptimizeCssManager = new LwsOptimizeCSSManager($modified, [], $preload);
            $modified = $lwsOptimizeCssManager->preload_fonts();
        }

        // We can put the current page to cache. We now apply the chosen options to the file (minify CSS/JS, combine CSS/JS, ...)
        if ($this->base->lwsop_check_option('cloudflare')['state'] == "false" || !isset($this->base->lwsop_check_option('cloudflare')['data']['tools']['min_css'])) {
            if ($this->base->lwsop_check_option('combine_css')['state'] == "true") {
                $lwsOptimizeCssManager = new LwsOptimizeCSSManager($modified, [], [], $media_to_update);
                $data = $lwsOptimizeCssManager->combine_css_update();
                $modified = $data['html'];

                $cached_elements['css']['file'] += $data['files']['file'];
                $cached_elements['css']['size'] += $data['files']['size'];
            } elseif ($this->base->lwsop_check_option('minify_css')['state'] == "true") {
                $lwsOptimizeCssManager = new LwsOptimizeCSSManager($modified, [], [], $media_to_update);
                $data = $lwsOptimizeCssManager->minify_css();
                $modified = $data['html'];

                $cached_elements['css']['file'] += $data['files']['file'];
                $cached_elements['css']['size'] += $data['files']['size'];
            }
        }

        if ($this->base->lwsop_check_option('cloudflare')['state'] == "false" && !isset($this->base->lwsop_check_option('cloudflare')['data']['tools']['min_js'])) {
            if ($this->base->lwsop_check_option('combine_js')['state'] == "true") {
                $lwsOptimizeJsManager = new LwsOptimizeJSManager($modified);
                $data = $lwsOptimizeJsManager->combine_js_update();

                $modified = $data['html'];

                $cached_elements['js']['file'] += $data['files']['file'];
                $cached_elements['js']['size'] += $data['files']['size'];
            } elseif ($this->base->lwsop_check_option('minify_js')['state'] == "true") {
                $lwsOptimizeJsManager = new LwsOptimizeJSManager($modified);
                $data = $lwsOptimizeJsManager->minify_js();

                $modified = $data['html'];

                $cached_elements['js']['file'] += $data['files']['file'];
                $cached_elements['js']['size'] += $data['files']['size'];
            }
        }

        // Finally add the cache file
        if ($this->content_type === "html") {

            // Add a filter for users to modify the cache file before it is saved
            $tmp_content = (string) apply_filters('lwsop_buffer_callback_filter', $modified, "cache", $this->cache_directory);

            if (!$tmp_content) {
                return $modified;
            } else {
                $modified = $tmp_content;
            }

            if ($this->base->lwsop_check_option('minify_html')['state'] == "true") {

                $exclusions = $this->base->lwsop_check_option('minify_html');
                $exclusions = $exclusions['data']['exclusions'] ?? null;

                if ($exclusions === null || empty($exclusions)) {
                    $htmlMin = new \Abordage\HtmlMin\HtmlMin();
                    $modified = $htmlMin->minify($modified);
                } else {
                    $no_minify = false;
                    foreach ($exclusions as $exclusion) {
                        if (preg_match("~$exclusion~", $_SERVER["REQUEST_URI"])) {
                            $no_minify = true;
                        }
                    }

                    if (!$no_minify) {
                        $htmlMin = new \Abordage\HtmlMin\HtmlMin();
                        $modified = $htmlMin->minify($modified);
                    }
                }
            }

            $is_mobile = false;
            if ($this->_lwsop_is_mobile()) {
                $is_mobile = true;
            } else {
                $is_mobile = false;
            }

            $this->lwsop_add_to_cache($modified, $cached_elements, $is_mobile);
        } elseif ($this->content_type === "xml") {
            if (preg_match("/<link><\/link>/", $buffer) && preg_match("/\/feed$/", $_SERVER["REQUEST_URI"])) {
                return $buffer . time();
            }
            $this->lwsop_add_to_cache($buffer, $cached_elements, false);
        } elseif ($this->content_type === "json") {
            $this->lwsop_add_to_cache($buffer, $cached_elements, false);
        }

        return $modified;
    }

    public function lwsop_add_to_cache($content, $cached, $mobile = false)
    {
        $this->lwsop_set_cachedir();

        if (!empty($content) && $this->cache_directory !== false) {
            $user_id = get_current_user_id();
            $name = "index_$user_id.";

            // If the file is html/xml/json, add it to /wp-content/cache/
            if ($content !== false && preg_match("/html|xml|json/i", $this->content_type)) {

                if (!is_dir($this->cache_directory)) {
                    mkdir($this->cache_directory, 0755, true);
                }

                if (is_dir($this->cache_directory) && !file_exists($this->cache_directory . $name . $this->content_type)) {
                    file_put_contents($this->cache_directory . $name . $this->content_type, $content);

                    $cached['html']['file'] = 1;
                    $cached['html']['size'] = filesize($this->cache_directory . $name . $this->content_type);

                    $GLOBALS['lws_optimize']->lwsop_recalculate_stats("plus", $cached, $mobile);
                }
            }
        }
    }

    public function lws_optimize_manage_frontend_preload_css($html)
    {
        if (is_admin()) {
            return $html;
        }

        $html = str_replace("rel='stylesheet'", "rel='preload' as='style' ", $html);

        return $html;
    }

    public function lws_optimize_manage_frontend_preload_js($tag, $handle, $src)
    {
        if (is_admin()) {
            return $tag;
        }

        if ($handle == "admin-bar" || str_contains($src, "jquery") || str_contains($handle, "jquery") || str_contains($handle, "wc")) {
            return $tag;
        }

        if (isset($GLOBALS['lws_optimize']->optimize_options['preload_js']['exclusions'])) {
            foreach ((array)$GLOBALS['lws_optimize']->optimize_options['preload_js']['exclusions'] as $exception) {
                if (preg_match("~$exception~", $src)) {
                    return $tag;
                }
            }
        }

        $tag = str_replace('></script>', ' defer></script>', $tag);
        return $tag;
    }
    public function lws_optimize_manage_frontend_eliminate_requests($src)
    {
        if (is_admin()) {
            return $src;
        }

        if (strpos($src, '?ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        if (strpos($src, '?v=')) {
            $src = remove_query_arg('v', $src);
        }
        return $src;
    }

    public function lws_optimize_manage_frontend_webfont_optimize($html, $handle, $href)
    {
        if (is_admin()) {
            return $html;
        }

        if (!str_contains($handle, "fonts") && !str_contains($href, "fonts")) {
            return $html;
        }

        return "<link rel='preconnect' href='$href' crossorigin/>";
    }

    public function lws_optimize_manage_frontend_deactivate_emoji()
    {
        // Disable WordPress emoji support
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ('dns-prefetch' == $relation_type) {
                $urls = array_diff($urls, array(apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/')));
            }
            return $urls;
        }, 10, 2);

        // Disable TinyMCE emoji script
        add_filter('tiny_mce_plugins', function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : array();
        });
    }

    public function lwsop_detect_page_type()
    {
        if (preg_match("/\?/", $_SERVER["REQUEST_URI"]) || preg_match("/^\/wp-json/", $_SERVER["REQUEST_URI"])) {
            return true;
        }

        if (is_front_page()) {
            $this->page_type = "homepage";
        } elseif (is_category()) {
            $this->page_type = "category";
        } elseif (is_tag()) {
            $this->page_type = "tag";
        } elseif (is_tax()) {
            $this->page_type = "tax";
        } elseif (is_author()) {
            $this->page_type = "author";
        } elseif (is_search()) {
            $this->page_type = "search";
        } elseif (is_single()) {
            $this->page_type = "post";
        } elseif (is_page()) {
            $this->page_type = "page";
        } elseif (is_attachment()) {
            $this->page_type = "attachment";
        } elseif (is_archive()) {
            $this->page_type = "archive";
        }
    }

    public function lwsop_detect_page_content_type()
    {
        $content_type = false;
        if (function_exists("headers_list")) {
            $headers = headers_list();
            foreach ($headers as $header) {
                if (preg_match("/Content-Type\:/i", $header)) {
                    $content_type = preg_replace("/Content-Type\:\s(.+)/i", "$1", $header);
                }
            }
        }

        if (preg_match("/xml/i", $content_type)) {
            $this->content_type = "xml";
        } elseif (preg_match("/json/i", $content_type)) {
            $this->content_type = "json";
        } else {
            $this->content_type = "html";
        }
    }

    public function lwsop_page_has_been_excluded($buffer = null)
    {
        $url = urldecode($_SERVER["REQUEST_URI"]);

        $exclusions = $this->optimize_options['filebased_cache']['exclusions'] ?? [];

        foreach ($exclusions as $page) {
            $type = $page['type'] ?? null;
            $page = $page['value'] ?? null;
            $category = $page['category'] ?? null;

            if ($type === null || $page === null || $category === null) {
                continue;
            }

            if ($category == "pages") {
                $page = trim($page);

                // We check if $buffer is given to make sure this test is made only once the page_type is found
                if ($buffer && preg_match("/^(homepage|category|tag|tax|author|search|post|page|archive|attachment)$/", $page)) {
                    if ($page == $this->page_type) {
                        return true;
                    }
                } elseif ($type == "exact") {
                    $url = trim($url, "/");
                    $page = trim($page, "/");

                    if (strtolower($page) == strtolower($url)) {
                        return true;
                    }
                } elseif ($type == "regex") {
                    if (preg_match("/" . $page . "/i", $url)) {
                        return true;
                    }
                } else {
                    if ($type == "startwith") {
                        $url = ltrim($url, "/");
                        $page = ltrim($page, "/");

                        $preg_match_rule = "^" . preg_quote($page, "/");
                    } elseif ($type == "contain") {
                        $preg_match_rule = preg_quote($page, "/");
                    }

                    if ($preg_match_rule && preg_match("/" . $preg_match_rule . "/i", $url)) {
                        return true;
                    }
                }
            } elseif ($category == "useragent") {
                if (preg_match("/" . preg_quote($page, "/") . "/i", $_SERVER['HTTP_USER_AGENT'])) {
                    return true;
                }
            } elseif ($category == "cookie" && isset($_SERVER['HTTP_COOKIE']) && preg_match("/" . preg_quote($page, "/") . "/i", $_SERVER['HTTP_COOKIE'])) {
                return true;
            }
        }

        return false;
    }

    public function lwsop_page_to_ignore()
    {
        $ignored = array(
            "\/wp\-comments\-post\.php",
            "\/wp\-login\.php",
            "\/robots\.txt",
            "\/wp\-cron\.php",
            "\/wp\-content",
            "\/wp\-admin",
            "\/wp\-includes",
            "\/index\.php",
            "\/xmlrpc\.php",
            "\/wp\-api\/",
            "leaflet\-geojson\.php",
            "\/clientarea\.php"
        );
        if ($GLOBALS['lws_optimize']->lwsop_plugin_active('woocommerce/woocommerce.php') && $this->page_type != "homepage") {
            global $post;

            if (isset($post->ID) && $post->ID && function_exists("wc_get_page_id")) {
                $woocommerce_ids = array();

                array_push($woocommerce_ids, \wc_get_page_id('cart'), \wc_get_page_id('checkout'), \wc_get_page_id('receipt'), \wc_get_page_id('confirmation'), \wc_get_page_id('myaccount'), \wc_get_page_id('product'), \wc_get_page_id('product-category'));

                if (in_array($post->ID, $woocommerce_ids)) {
                    return true;
                }
            }

            array_push($ignored, "\/cart\/?$", "\/checkout", "\/receipt", "\/confirmation", "\/wc-api\/");
        }

        if ($GLOBALS['lws_optimize']->lwsop_plugin_active('wp-easycart/wpeasycart.php')) {
            array_push($ignored, "\/cart");
        }

        if ($GLOBALS['lws_optimize']->lwsop_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
            array_push($ignored, "\/cart", "\/checkout");
        }

        if (preg_match("/" . implode("|", $ignored) . "/i", $_SERVER["REQUEST_URI"])) {
            return true;
        }

        return false;
    }

    /**
     * Check if the cache needs to be created for this URL. As a general rule, we do not cache pages with errors, bad URLs or redirections
     * as it serves no purposes to cache bad data. Unless specifically activated, no cache is made for admins and connected users.
     * Finally, if the user excluded the page, we do not cache it. Some checks are from WPFastestCache.
     */
    public function lwsop_check_need_cache($uri = false)
    {
        if (!$uri) {
            $uri = $_SERVER['REQUEST_URI'];
        } else {
            $uri = esc_url($uri);
        }

        // First check if the user is connected ; There is no point in doing checks even though the user is connected
        if ((is_user_logged_in() && $this->base->lwsop_check_option('cache_logged_user')['state'] == "true") || is_user_admin()) {
            return false;
        }

        // The user may choose to exclude some pages from the cache. We need to check that soon to reduce potentially useless checks
        if ($this->lwsop_page_has_been_excluded()) {
            return false;
        }

        // To prevent issues with requests and their data, no cache when sending POST requests
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "POST") {
            return false;
        }

        // If there is no User Agent defined, do not cache ; It may also have been a request from a CDM, which we do not cache
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        // Do not put into cache if user deactivated cache on mobile devices
        if ($this->base->lwsop_check_option('cache_mobile_user')['state'] == "true" && $this->_lwsop_is_mobile()) {
            return false;
        }

        // Do not cache the page if the UserAgent is not from a real user (non exhaustive list)
        if (
            preg_match("/(googlebot|bingbot|yandexbot|slurp|spider|robot|bot.html|bot.htm|facebookbot|facebookexternalhit|twitterbot|storebot|microsoftpreview|ahrefsbot|semrushbot|siteauditbot|splitsignalbot)/", $_SERVER['HTTP_USER_AGENT'])
        ) {
            return false;
        }

        // If the page has Yandex CID or GoogleAnalytics parameters, no cache #WPFC
        if (preg_match("/y(ad|s)?clid\=/i", urldecode($uri))) {
            return false;
        }
        if (preg_match("/utm_(source|medium|campaign|content|term)/i", urldecode($uri))) {
            return false;
        }

        // Do not cache sitemap pages
        if (preg_match("/(wp-sitemap|sitemap\.xml)/i", urldecode($uri))) {
            return false;
        }

        // Do not cache wp-json pages
        if (preg_match("/(wp-json)/i", urldecode($uri))) {
            return false;
        }

        // Get the COOKIES if set and loop through them to check for specific cookies
        if (isset($_COOKIE)) {
            foreach ($_COOKIE as $key => $cookie) {
                // We could use WP functions to check if a comment has been sent, but it is faster to check the cookie
                // + it can be done with the next check
                if (preg_match("/comment_author_/i", $cookie)) {
                    return false;
                }
                // If WPTouch Pro is active and current mode is "desktop", does not cache if the user is on mobile #WPFC
                if ($this->_lwsop_is_mobile() && $key == "wptouch-pro-view" && $cookie == "desktop") {
                    return false;
                }
            }
        }

        // If the request end with //, do not cache #WPFC
        if (isset($uri) && preg_match("/(\/){2}$/", $uri)) {
            return false;
        }

        // Do not cache if the permalink does not match the URL #WPFC
        if (isset($uri) && preg_match("/[^\/]+\/$/", $uri) && !preg_match("/\/$/", get_option('permalink_structure'))) {
            return false;
        }

        // No cache if the www. is inconsistent #WPFC
        if ((preg_match("/www\./i", get_option("home")) && !preg_match("/www\./i", $_SERVER['HTTP_HOST'])) || (!preg_match("/www\./i", get_option("home")) && preg_match("/www\./i", $_SERVER['HTTP_HOST']))) {
            return false;
        }

        // Do not cache if SSL parameters do not match
        // In case a plugin doing HTTPS redirection is active, proceed with the caching (this part if from #WPFC)
        if ((preg_match("/^https/i", get_option("home")) && !is_ssl()) || (!preg_match("/^https/i", get_option("home")) && is_ssl())
            || (
                !is_ssl()
                || (
                    $GLOBALS['lws_optimize']->lwsop_plugin_active('really-simple-ssl/rlrsssl-really-simple-ssl.php')
                    || $GLOBALS['lws_optimize']->lwsop_plugin_active('really-simple-ssl-on-specific-pages/really-simple-ssl-on-specific-pages.php')
                    || $GLOBALS['lws_optimize']->lwsop_plugin_active('ssl-insecure-content-fixer/ssl-insecure-content-fixer.php')
                    || $GLOBALS['lws_optimize']->lwsop_plugin_active('https-redirection/https-redirection.php')
                    || $GLOBALS['lws_optimize']->lwsop_plugin_active('better-wp-security/better-wp-security.php')
                )
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Return the PATH to the cachefile for the current URL
     *
     * Adapted from WPFastestCache, changes have been made to clean up the code a bit and update it
     *
     * @return string PATH to the cachefile. If it does not exist,
     */
    public function lwsop_set_cachedir($uri = false)
    {
        if (!$uri) {
            $uri = $_SERVER['REQUEST_URI'];
        } else {
            $uri = esc_url($uri);
        }

        $uri = preg_replace("/^(http|https):\/\//sx", "", $uri);
        // Remove the parameters from an URL
        $uri = preg_replace('/\?.+$/', '', $uri);

        // By default, "cache";
        $dir = "cache";
        // If the user is on mobile and the site has mobile cache, then "cache_mobile"
        if ($this->_lwsop_is_mobile()) {
            $dir = "cache-mobile";
        }

        if ($GLOBALS['lws_optimize']->lwsop_plugin_active('gtranslate/gtranslate.php')) {
            if (isset($_SERVER["HTTP_X_GT_LANG"])) {
                $this->cache_directory = $GLOBALS['lws_optimize']->lwsop_get_content_directory("$dir/{$_SERVER['HTTP_X_GT_LANG']}/");
            } else {
                $this->cache_directory = $GLOBALS['lws_optimize']->lwsop_get_content_directory("$dir/");
            }
        } else {
            $this->cache_directory = $GLOBALS['lws_optimize']->lwsop_get_content_directory("$dir/");
        }

        $this->cache_directory .= $uri;
        $this->cache_directory = rtrim(rtrim($this->cache_directory), "\/") . "/" ?? "";

        // Remove the query parameters from the URL if it has any ; Only if the parameters are from GoogleAnalytics, Google Click Identifier, Yandex, Facebook #WPFC
        if ((preg_match("/gclid\=/i", $this->cache_directory) || preg_match("/y(ad|s)?clid\=/i", $this->cache_directory) || preg_match("/fbclid\=/i", $this->cache_directory) || preg_match("/utm_(source|medium|campaign|content|term)/i", $this->cache_directory)) && strlen($uri) > 1) {
            $this->cache_directory = preg_replace("/\/*\?.+/", "", $this->cache_directory);
            $this->cache_directory = $this->cache_directory . "/";
        }

        $this->cache_directory = urldecode($this->cache_directory);

        // Security
        if (preg_match("/\.{2,}/", $this->cache_directory)) {
            $this->cache_directory = false;
        }


        /*
        This code snippet suggests logic for selective caching based on URL structure,
        file types, and specific query string parameters. It aims to avoid caching for dynamic content influenced by these parameters.
        */
        // for the sub-pages
        if (strlen($uri) > 1 && !preg_match("/\.(html|xml)/i", $uri) && $this->base->lwsop_plugin_active("custom-permalinks/custom-permalinks.php") || preg_match("/\/$/", get_option('permalink_structure', "")) && !preg_match("/\/$/", $uri) && (!isset($_SERVER["QUERY_STRING"]) || !$_SERVER["QUERY_STRING"] || !preg_match("/y(ad|s)?clid\=/i", $this->cache_directory) || !preg_match("/gclid\=/i", $this->cache_directory) || !preg_match("/fbclid\=/i", $this->cache_directory) || !preg_match("/utm_(source|medium|campaign|content|term)/i", $this->cache_directory))) {
            $this->cache_directory = false;
        }

        if ($this->_lwsop_is_mobile() && $this->base->lwsop_check_option('cache_mobile_user')['state'] == "true") {
            $this->cache_directory = false;
        }

        return $this->cache_directory;
    }

    /**
     * Check whether the current device is mobile of desktop
     * A copy of wp_is_mobile() [wp-includes/vars.php] - Last Updated 6.4.0
     *
     * @return bool true if is mobile, false otherwise
     */
    private function _lwsop_is_mobile()
    {
        if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
            // This is the `Sec-CH-UA-Mobile` user agent client hint HTTP request header.
            // See <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA-Mobile>.
            return '?1' === $_SERVER['HTTP_SEC_CH_UA_MOBILE'];
        } elseif (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        } elseif (
            str_contains($_SERVER['HTTP_USER_AGENT'], 'Mobile') // Many mobile devices (all iPhone, iPad, etc.)
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'Android')
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'Silk/')
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'Kindle')
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'BlackBerry')
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'Opera Mini')
            || str_contains($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi')
        ) {
            return true;
        } else {
            return false;
        }
    }
}
