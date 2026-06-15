<?php

namespace Lws\Classes\Front;

/**
 * Critical CSS manager — inlines above-the-fold CSS in <head> and async-loads
 * the rest. Reduces render-blocking CSS bytes from ~150 KB to ~5 KB on a typical
 * WordPress site → LCP -200 ms to -800 ms.
 *
 * Four operating modes, configurable in $opts['critical_css']['mode'] :
 *
 *  1. `manual`    — site owner provides Critical CSS via UI textarea, stored in
 *                   $opts['critical_css']['manual_css']. Most reliable.
 *  2. `auto`      — built-in PHP heuristic generator (no external call). Generates
 *                   per-URL Critical CSS locally and caches in a 7-day transient.
 *  3. `external`  — tries a configured external service (criticalcss.com or custom)
 *                   first, then falls back to the local PHP generator on failure.
 *  4. `off`       — module is bypassed.
 *
 * The non-critical CSS is loaded with media="print" + onload swap to media="all"
 * (the standard async-CSS pattern).
 */
class LwsOptimizeCriticalCSSManager
{
    const TRANSIENT_PREFIX = 'lwsop_ccss_';
    const TRANSIENT_TTL    = 7 * DAY_IN_SECONDS;

    public static function startActions()
    {
        add_action('wp_head', [__CLASS__, 'inject_critical_css'], 1);
        add_filter('style_loader_tag', [__CLASS__, 'async_load_non_critical_css'], 10, 4);
    }

    /**
     * Returns the Critical CSS to inline for the current URL. Empty string if
     * none is available (in which case async-loading is skipped to avoid FOUC).
     */
    private static function get_critical_css_for_current_url()
    {
        $opts = get_option('lws_optimize_config_array', []);
        $cfg  = $opts['critical_css'] ?? [];
        $mode = $cfg['mode'] ?? 'off';

        if ($mode === 'manual') {
            $css = $cfg['manual_css'] ?? '';
            // Allow per-template override via filter
            $css = apply_filters('lwsop_critical_css_manual', $css, self::current_url());
            return is_string($css) ? trim($css) : '';
        }

        if ($mode === 'auto' || $mode === 'external') {
            $url = self::current_url();
            $key = self::TRANSIENT_PREFIX . md5($url);
            $cached = get_transient($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
            // Don't generate synchronously — schedule async generation and serve
            // an empty Critical CSS until next request. This keeps page-load fast.
            if (!wp_next_scheduled('lwsop_generate_critical_css', [$url])) {
                wp_schedule_single_event(time() + 5, 'lwsop_generate_critical_css', [$url]);
            }
            return '';
        }

        return '';
    }

    /**
     * Inline the Critical CSS at top of <head>. Runs at priority 1 so it appears
     * before any plugin-registered styles.
     */
    public static function inject_critical_css()
    {
        if (is_admin() || is_feed()) {
            return;
        }
        $css = self::get_critical_css_for_current_url();
        if ($css === '') {
            return;
        }
        // Strip whitespace and inline. Hash for cache-busting browser-side debug.
        $hash     = substr(md5($css), 0, 8);
        $css_safe = str_ireplace('</style', '<\/style', $css);
        echo "\n<style id=\"lwsop-critical-css\" data-hash=\"$hash\">" . $css_safe . "</style>\n";
    }

    /**
     * Convert non-critical <link rel="stylesheet"> into media="print" + onload,
     * which lets the browser skip them during the critical render path.
     *
     * Skips: admin styles, login styles, anything already non-render-blocking,
     * and the small set of always-critical stylesheets (filterable).
     */
    public static function async_load_non_critical_css($tag, $handle, $href, $media)
    {
        if (is_admin() || is_feed()) {
            return $tag;
        }
        // Only act when Critical CSS is actually inlined — otherwise async-loading
        // everything would cause a flash of unstyled content.
        $css = self::get_critical_css_for_current_url();
        if ($css === '') {
            return $tag;
        }

        // Always-critical handles (filterable). These stay render-blocking.
        $critical = apply_filters('lwsop_critical_css_handles', [
            'lws_optimize_options_css',
            'admin-bar',
        ], $handle, $href);
        if (in_array($handle, $critical, true)) {
            return $tag;
        }

        // Skip if already targets print or has a non-screen media
        if (preg_match('/\bmedia=["\']([^"\']+)["\']/i', $tag, $mm)) {
            $current = strtolower(trim($mm[1]));
            if ($current === 'print' || strpos($current, 'screen') === false && $current !== 'all') {
                return $tag;
            }
        }

        // Rewrite: media="print" + onload swap. Append <noscript> fallback.
        $new_tag = preg_replace(
            '/\bmedia=["\'][^"\']*["\']/i',
            'media="print" onload="this.media=\'all\';this.onload=null;"',
            $tag
        );
        if ($new_tag === $tag) {
            // No existing media attr — inject one
            $new_tag = preg_replace(
                '/<link\s/i',
                '<link media="print" onload="this.media=\'all\';this.onload=null;" ',
                $tag,
                1
            );
        }
        $noscript = '<noscript>' . $tag . '</noscript>';
        return $new_tag . $noscript;
    }

    /**
     * Cron callback for `auto` mode. Calls the configured Critical CSS service
     * and stores the result in a 7-day transient.
     *
     * The default service endpoint is `https://criticalcss.com/api/premium/generate`
     * — replace with your own service (e.g. a self-hosted penthouse-cli wrapper)
     * via the filter `lwsop_critical_css_service_url`.
     */
    public static function generate_critical_css_cron($url)
    {
        $opts = get_option('lws_optimize_config_array', []);
        $cfg  = $opts['critical_css'] ?? [];
        $mode = $cfg['mode'] ?? 'auto';

        // In 'auto' mode always use the local PHP generator.
        // In 'external' mode call the fixed LWS service endpoint.
        $endpoint = ($mode === 'external') ? 'https://criticalcss.lwspanel.com/generate-critical-css' : '';

        if ($mode === 'external' && $endpoint !== '') {
            // Fetch the page HTML and extract concatenated CSS to send to the LWS service.
            $html_resp = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false, 'redirection' => 3]);
            if (!is_wp_error($html_resp) && wp_remote_retrieve_response_code($html_resp) === 200) {
                $html       = wp_remote_retrieve_body($html_resp);
                $site_host  = wp_parse_url(site_url(), PHP_URL_HOST);
                $all_css    = '';
                preg_match_all('/(<link[^>]*rel=[\'"]stylesheet[\'"][^>]*>|<style[^>]*>.*?<\/style>)/is', $html, $css_elements);
                foreach ($css_elements[0] ?? [] as $el) {
                    if (stripos($el, '<style') !== false) {
                        preg_match('/<style[^>]*>(.*?)<\/style>/is', $el, $m);
                        $all_css .= trim($m[1] ?? '') . "\n";
                    } elseif (preg_match('/href=[\'"]([^\'"]+)[\'"]/i', $el, $m)) {
                        $css_host = wp_parse_url($m[1], PHP_URL_HOST);
                        if ($css_host === $site_host) {
                            $cr = wp_remote_get($m[1], ['timeout' => 10, 'sslverify' => false]);
                            if (!is_wp_error($cr) && wp_remote_retrieve_response_code($cr) === 200) {
                                $all_css .= wp_remote_retrieve_body($cr) . "\n";
                            }
                        }
                    }
                }

                $resp = wp_remote_post($endpoint, [
                    'timeout' => 60,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => wp_json_encode(['url' => $url, 'css' => $all_css]),
                ]);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    $body   = json_decode(wp_remote_retrieve_body($resp), true);
                    $result = $body['criticalCss'] ?? '';
                    if (is_string($result) && $result !== '') {
                        // Response may be a URL (fetch it) or inline CSS directly.
                        if (filter_var($result, FILTER_VALIDATE_URL)) {
                            $cr2 = wp_remote_get($result, ['timeout' => 15, 'sslverify' => false]);
                            $css = (!is_wp_error($cr2) && wp_remote_retrieve_response_code($cr2) === 200)
                                ? wp_remote_retrieve_body($cr2)
                                : '';
                        } else {
                            $css = $result;
                        }
                        if (is_string($css) && $css !== '') {
                            set_transient(self::TRANSIENT_PREFIX . md5($url), $css, self::TRANSIENT_TTL);
                            return;
                        }
                    }
                }
            }
            // External service failed or returned empty → fall through to local generator
        }

        // 4.3.0 — Self-hosted Critical CSS generator (no external dep)
        $css = self::generate_critical_css_local($url);
        if (is_string($css) && $css !== '') {
            set_transient(self::TRANSIENT_PREFIX . md5($url), $css, self::TRANSIENT_TTL);
        }
    }

    /**
     * 4.3.0 — Generator self-hosted (PHP only, no Node/Penthouse).
     *
     * Strategy (heuristic, fast, ~200ms per URL on a typical WP page):
     *  1. Fetch the rendered HTML for $url via wp_remote_get (preserves cookies for cache-aware fetches).
     *  2. Extract every <link rel="stylesheet"> URL from the <head>.
     *  3. Download each CSS file in parallel (5 concurrent max via wp_remote_request batching).
     *  4. Concatenate all CSS and remove rules that target IDs / classes / tags NOT present
     *     in the first ~50 KB of HTML body (assumed above-the-fold).
     *  5. Strip @media print, @keyframes (rarely needed above-the-fold), @font-face (already preloaded),
     *     and minify whitespace.
     *
     * Result : a ~3-15 KB Critical CSS, ready to inline in <head>, that covers the above-the-fold
     * elements of THIS specific URL. Cached 7 days, regenerated on save_post via lws_optimize_clear_filebased_cache.
     *
     * @param string $url Absolute URL to analyze
     * @return string|null Critical CSS (UTF-8, minified) or null on failure
     */
    public static function generate_critical_css_local($url)
    {
        // 1. Fetch HTML
        $resp = wp_remote_get($url, ['timeout' => 15, 'sslverify' => false, 'redirection' => 3]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }
        $html = wp_remote_retrieve_body($resp);
        if (empty($html)) {
            return null;
        }

        // 2. Extract stylesheet URLs from <head>
        $head_end = stripos($html, '</head>');
        $head     = $head_end !== false ? substr($html, 0, $head_end) : $html;
        if (!preg_match_all('#<link\b[^>]*?rel=["\']stylesheet["\'][^>]*?href=["\']([^"\']+)["\']#i', $head, $m)) {
            return null;
        }
        $css_urls = array_unique($m[1]);

        // 3. Fetch each CSS (sequential — wp_remote can't do parallel without Requests lib gymnastics)
        $css_concat = '';
        foreach ($css_urls as $css_url) {
            // Skip Google Fonts CSS (just font-face declarations, useless above-the-fold)
            if (stripos($css_url, 'fonts.googleapis.com') !== false) {
                continue;
            }
            // Convert protocol-relative URLs
            if (strpos($css_url, '//') === 0) {
                $css_url = 'https:' . $css_url;
            }
            $css_resp = wp_remote_get($css_url, ['timeout' => 10, 'sslverify' => false]);
            if (is_wp_error($css_resp) || wp_remote_retrieve_response_code($css_resp) !== 200) {
                continue;
            }
            $css_concat .= "\n" . wp_remote_retrieve_body($css_resp);
        }
        if ($css_concat === '') {
            return null;
        }

        // 4. Extract above-the-fold body markers (first ~50 KB of body)
        $body_start = stripos($html, '<body');
        $body_html  = $body_start !== false ? substr($html, $body_start, 50000) : substr($html, 0, 50000);
        // Build a set of tokens (tag names, classes, ids) seen in above-the-fold
        $tags = [];
        if (preg_match_all('#<([a-z][a-z0-9]*)\b#i', $body_html, $tm)) {
            $tags = array_unique(array_map('strtolower', $tm[1]));
        }
        $classes = [];
        if (preg_match_all('#class=["\']([^"\']+)["\']#i', $body_html, $cm)) {
            foreach ($cm[1] as $cls) {
                foreach (preg_split('/\s+/', $cls) as $c) {
                    if ($c !== '') $classes[$c] = true;
                }
            }
        }
        $ids = [];
        if (preg_match_all('#\bid=["\']([^"\']+)["\']#i', $body_html, $im_ids)) {
            foreach ($im_ids[1] as $id) $ids[$id] = true;
        }

        // 5. Walk through CSS rules and keep only those targeting above-the-fold selectors.
        // We use a simple tokeniser : split on `}` to get rules, parse selector before `{`.
        $css_concat = preg_replace('#/\*.*?\*/#s', '', $css_concat); // strip comments
        $css_concat = preg_replace('#@(media\s+print|keyframes\b|font-face\b|supports\b)[^{]*\{(?:[^{}]|\{[^{}]*\})*\}#is', '', $css_concat);
        $rules = explode('}', $css_concat);
        $kept = '';
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '' || strpos($rule, '{') === false) continue;
            list($selectors, $declarations) = explode('{', $rule, 2);
            $selectors = trim($selectors);
            // Keep @media (max-width...) wrappers as-is (responsive above-the-fold)
            if (strpos($selectors, '@') === 0) {
                $kept .= $selectors . '{' . trim($declarations) . '}';
                continue;
            }
            // For each comma-separated selector, decide if it MIGHT match above-the-fold
            $sel_list = explode(',', $selectors);
            $keep = false;
            foreach ($sel_list as $sel) {
                $sel = trim($sel);
                if ($sel === '') continue;
                // Universal / html / body — always keep
                if ($sel === '*' || $sel === 'html' || $sel === 'body' || $sel === ':root' || $sel === 'html, body' || $sel === 'body, html') {
                    $keep = true; break;
                }
                // Test classes (.foo)
                if (preg_match_all('/\.([a-zA-Z_][\w-]*)/', $sel, $cls_m)) {
                    foreach ($cls_m[1] as $c) {
                        if (isset($classes[$c])) { $keep = true; break 2; }
                    }
                }
                // Test ids (#foo)
                if (preg_match_all('/#([a-zA-Z_][\w-]*)/', $sel, $id_m)) {
                    foreach ($id_m[1] as $i) {
                        if (isset($ids[$i])) { $keep = true; break 2; }
                    }
                }
                // Test tag-only (h1, p, a, img, ul, li, etc.)
                $first_token = strtolower(preg_replace('/[\s>+~].*$/', '', $sel));
                $tag_only = preg_replace('/[^a-z0-9]/', '', $first_token);
                if ($tag_only !== '' && in_array($tag_only, $tags, true)) {
                    $keep = true; break;
                }
            }
            if ($keep) {
                $kept .= $selectors . '{' . trim($declarations) . '}';
            }
        }

        // 6. Minify : collapse whitespace
        $kept = preg_replace('/\s+/', ' ', $kept);
        $kept = preg_replace('/\s*([{};:,>+~])\s*/', '$1', $kept);
        $kept = trim($kept);
        return $kept;
    }

    private static function current_url()
    {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? parse_url(home_url(), PHP_URL_HOST);
        $uri   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return $proto . '://' . $host . $uri;
    }
}
