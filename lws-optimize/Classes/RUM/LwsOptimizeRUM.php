<?php

namespace Lws\Classes\RUM;

/**
 * Real User Monitoring (RUM) — collects Core Web Vitals from actual visitors
 * and aggregates them per URL / device for the LWS Optimize dashboard.
 *
 * Design constraints:
 * - Zero personally identifiable information collected (no IP, no user-agent
 *   string stored, no cookies). Only: URL path, device class (mobile/desktop),
 *   the 4 Core Web Vitals values (LCP, INP, CLS, TTFB).
 * - Anonymous endpoint (no auth) — rate-limited via transient-based throttling
 *   per IP (60 reqs/min).
 * - Beacon-based POST using `navigator.sendBeacon` so it never blocks page-unload.
 * - Aggregation done server-side every 12h to a compact summary stored in the
 *   `lwsop_rum_aggregate` option (p50, p75, p95 per URL/device).
 *
 * Storage:
 * - Raw samples: `{prefix}lwsop_rum_samples` custom table (one row per metric per visit).
 * - Aggregates : `lwsop_rum_aggregate` option (per URL/device/metric, 30-day rolling window).
 */
class LwsOptimizeRUM
{
    const RATE_LIMIT = 60; // requests per minute per IP
    const RATE_WINDOW = 60;
    const TABLE_NAME = 'lwsop_rum_samples';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function startActions()
    {
        // Ensure the table exists — checked once per site and cached in the config
        // option to avoid a SQL lookup on every page load when RUM is active.
        $optimize_options = get_option('lws_optimize_config_array', []);
        if (empty($optimize_options['rum']['table_ready'])) {
            global $wpdb;
            $table  = $wpdb->prefix . self::TABLE_NAME;
            $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!$exists) {
                self::create_table(); // also sets rum.table_ready via create_table()
            } else {
                $optimize_options['rum']['table_ready'] = true;
                update_option('lws_optimize_config_array', $optimize_options);
            }
        }

        // Public AJAX endpoint (no auth — rate-limited and validated)
        add_action('wp_ajax_lwsop_rum_collect',        [__CLASS__, 'collect']);
        add_action('wp_ajax_nopriv_lwsop_rum_collect', [__CLASS__, 'collect']);

        // Inject the web-vitals snippet on every front page
        add_action('wp_footer', [__CLASS__, 'inject_collector_snippet'], 100);

        // Twice-daily aggregation cron
        add_action('lwsop_rum_aggregate_cron', [__CLASS__, 'aggregate']);
        if (!wp_next_scheduled('lwsop_rum_aggregate_cron')) {
            wp_schedule_event(time() + 600, 'twicedaily', 'lwsop_rum_aggregate_cron');
        }

        // Admin handlers
        add_action('wp_ajax_lwsop_rum_force_aggregate',  [__CLASS__, 'ajax_force_aggregate']);
        add_action('wp_ajax_lwsop_rum_purge',            [__CLASS__, 'ajax_purge']);
        add_action('wp_ajax_lwsop_rum_get_table_data',   [__CLASS__, 'ajax_get_table_data']);
        add_action('wp_ajax_lwsop_rum_get_page_samples', [__CLASS__, 'ajax_get_page_samples']);
    }

    // -------------------------------------------------------------------------
    // Table creation + migration from wp_options
    // -------------------------------------------------------------------------

    public static function create_table()
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            collected_at DATETIME        NOT NULL,
            path         VARCHAR(200)    NOT NULL,
            device       ENUM('desktop','mobile') NOT NULL DEFAULT 'desktop',
            metric       ENUM('LCP','CLS','INP','TTFB') NOT NULL,
            value        FLOAT UNSIGNED  NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_rum_page (path(100), device, metric),
            KEY idx_rum_date (collected_at)
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Migrate legacy wp_options samples if they still exist
        $legacy = get_option('lwsop_rum_samples', null);
        if (is_array($legacy) && !empty($legacy)) {
            foreach (array_chunk($legacy, 200) as $chunk) {
                foreach ($chunk as $s) {
                    if (!isset($s['m'], $s['v'], $s['p'], $s['d'])) continue;
                    $metric = $s['m'];
                    if (!in_array($metric, ['LCP', 'CLS', 'INP', 'TTFB'], true)) continue;
                    $device = in_array($s['d'] ?? '', ['mobile', 'desktop'], true) ? $s['d'] : 'desktop';
                    $wpdb->insert($table, [
                        'collected_at' => isset($s['t']) ? gmdate('Y-m-d H:i:s', (int) $s['t']) : current_time('mysql'),
                        'path'         => sanitize_text_field(substr((string) $s['p'], 0, 200)),
                        'device'       => $device,
                        'metric'       => $metric,
                        'value'        => (float) $s['v'],
                    ], ['%s', '%s', '%s', '%s', '%f']);
                }
            }
            delete_option('lwsop_rum_samples');
            // Reset aggregate so it is recomputed from migrated data
            delete_option('lwsop_rum_aggregate');
            delete_option('lwsop_rum_aggregate_ts');
        }

        // Mark the table as ready in the config so startActions() skips the
        // SHOW TABLES check on subsequent page loads.
        $optimize_options = get_option('lws_optimize_config_array', []);
        $optimize_options['rum']['table_ready'] = true;
        update_option('lws_optimize_config_array', $optimize_options);
    }

    // -------------------------------------------------------------------------
    // Front-end snippet injection
    // -------------------------------------------------------------------------

    public static function inject_collector_snippet()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if (is_admin() || is_feed() || is_preview() || is_404()) {
            return;
        }
        $endpoint = esc_url_raw(admin_url('admin-ajax.php?action=lwsop_rum_collect'));
        $path     = self::normalize_path(isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/');
        // CACHE-11: no nonce is emitted here. This snippet is baked into the cached HTML,
        // so a per-request nonce would expire with the page and silently break collection.
        // The collector is public (nopriv), strictly validated and rate-limited per IP.
        ?>
<script id="lwsop-rum">
(function(){
  if(!('sendBeacon' in navigator))return;
  if(window.self!==window.top)return;
  if(document.prerendering)return;
  var path=<?php echo wp_json_encode($path); ?>;
  var device=/Mobi|Android|iPhone|iPad/.test(navigator.userAgent)?'mobile':'desktop';
  var endpoint=<?php echo wp_json_encode($endpoint); ?>;
  var metrics={LCP:null,CLS:null,INP:null,TTFB:null};
  var sent=false;
  function flush(){
    if(sent)return;sent=true;
    var b=new Blob([JSON.stringify({batch:1,metrics:metrics,p:path,d:device})],{type:'application/json'});
    navigator.sendBeacon(endpoint,b);
  }
  try{
    var nav=performance.getEntriesByType('navigation')[0];
    if(nav)metrics.TTFB=Math.round((nav.responseStart-nav.requestStart)*100)/100;
  }catch(e){}
  if('PerformanceObserver' in window){
    try{
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(metrics.LCP===null||e.startTime>metrics.LCP)metrics.LCP=Math.round(e.startTime*100)/100;});
      }).observe({type:'largest-contentful-paint',buffered:true});
    }catch(e){}
    try{
      // CLS is cumulative and 0 is its valid resting value (no shifts yet), so mark it
      // measured as soon as the observer attaches rather than waiting for a first entry.
      metrics.CLS=0;
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(!e.hadRecentInput)metrics.CLS=Math.round((metrics.CLS+e.value)*10000)/10000;});
      }).observe({type:'layout-shift',buffered:true});
    }catch(e){}
    try{
      // INP only exists once a qualifying interaction happens — stays null (unmeasured)
      // otherwise, since 0 is not a meaningful INP value.
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(metrics.INP===null||e.duration>metrics.INP)metrics.INP=Math.round(e.duration*100)/100;});
      }).observe({type:'event',buffered:true,durationThreshold:40});
    }catch(e){}
  }
  // 'pagehide' alone misses tab/browser close and iOS Safari app-switch in many
  // cases; 'visibilitychange' to 'hidden' fires reliably in those situations, so
  // both are wired to flush (flush() is idempotent via the `sent` guard).
  addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')flush();});
  addEventListener('pagehide',flush,{once:true});
  setTimeout(flush,8000);
})();
</script>
        <?php
    }

    /**
     * Reduces a request URI to the identifier used to group RUM samples.
     *
     * strtok(..., '?') used to be used here, which drops the query string
     * entirely — on plain-permalink sites (/?p=123) that collapses every
     * post into a single "/" row. The query string is kept, but known
     * tracking params (utm_*, gclid, fbclid, ...) are stripped first so they
     * don't fragment pretty-permalink pages into one row per campaign link,
     * and remaining params are sorted so ?a=1&b=2 and ?b=2&a=1 group together.
     */
    public static function normalize_path($request_uri)
    {
        $parts = explode('?', (string) $request_uri, 2);
        $path  = $parts[0] !== '' ? $parts[0] : '/';

        if (!isset($parts[1]) || $parts[1] === '') {
            return $path;
        }

        parse_str($parts[1], $query);
        if (empty($query)) {
            return $path;
        }

        static $tracking_params = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref', '_ga',
        ];
        foreach ($tracking_params as $param) {
            unset($query[$param]);
        }

        if (empty($query)) {
            return $path;
        }

        ksort($query);
        return $path . '?' . http_build_query($query);
    }

    // -------------------------------------------------------------------------
    // AJAX: collect beacon
    // -------------------------------------------------------------------------

    public static function collect()
    {
        global $wpdb;

        // Rate limit per IP
        $ip  = self::client_ip();
        $key = 'lwsop_rum_rl_' . md5($ip);
        $hit = (int) get_transient($key);
        if ($hit >= self::RATE_LIMIT) {
            wp_send_json_error(['code' => 'RATE_LIMITED'], 429);
        }
        set_transient($key, $hit + 1, self::RATE_WINDOW);

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        // CACHE-11: no nonce check — the snippet lives in cached HTML so a nonce would
        // expire with the page. Abuse is bounded by the per-IP rate limit above and the
        // strict validation below (enum device, capped path, float-cast metrics).
        if (!is_array($data)) {
            wp_send_json_error(['code' => 'BAD_PAYLOAD'], 400);
        }

        $path   = sanitize_text_field(substr((string) ($data['p'] ?? ''), 0, 200));
        $device = in_array($data['d'] ?? '', ['mobile', 'desktop'], true) ? $data['d'] : 'desktop';
        $now    = current_time('mysql');
        $table  = $wpdb->prefix . self::TABLE_NAME;
        $saved  = 0;

        if (!empty($data['batch']) && isset($data['metrics']) && is_array($data['metrics'])) {
            foreach (['LCP', 'CLS', 'INP', 'TTFB'] as $m) {
                // null/absent means "not measured" (e.g. INP with no interaction yet) and
                // must be skipped, but a real 0 (e.g. CLS with no layout shift) is a valid
                // sample and must be stored — do not conflate the two.
                if (!array_key_exists($m, $data['metrics']) || $data['metrics'][$m] === null) continue;
                $val = (float) $data['metrics'][$m];
                if ($val < 0) continue;
                $wpdb->insert($table, [
                    'collected_at' => $now,
                    'path'         => $path,
                    'device'       => $device,
                    'metric'       => $m,
                    'value'        => $val,
                ], ['%s', '%s', '%s', '%s', '%f']);
                $saved++;
            }
        }

        if ($saved === 0) {
            wp_send_json_error(['code' => 'NO_METRICS'], 400);
        }

        wp_send_json_success(['saved' => $saved]);
    }

    // -------------------------------------------------------------------------
    // Aggregation cron
    // -------------------------------------------------------------------------

    public static function aggregate()
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table (name from internal constant, not user input); twice-daily cron-only bulk purge
        // Purge samples older than 30 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table}` WHERE collected_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table (name from internal constant, not user input); twice-daily cron-only aggregate read, result cached into the lwsop_rum_aggregate option below
        // Fetch all fresh samples sorted for percentile calculation
        $rows = $wpdb->get_results(
            "SELECT path, device, metric, value
             FROM `{$table}`
             WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY path, device, metric, value ASC",
            ARRAY_A
        );

        if (empty($rows)) {
            delete_option('lwsop_rum_aggregate');
            update_option('lwsop_rum_aggregate_ts', time(), false);
            return;
        }

        // Group sorted values by device|path|metric
        $groups = [];
        foreach ($rows as $row) {
            $k = $row['device'] . '|' . $row['path'] . '|' . $row['metric'];
            $groups[$k][] = (float) $row['value'];
        }

        $aggregate = [];
        foreach ($groups as $key => $values) {
            // values are already sorted ASC from SQL
            $n = count($values);
            $aggregate[$key] = [
                'n'   => $n,
                'p50' => $values[max(0, (int) ceil($n * 0.50) - 1)],
                'p75' => $values[max(0, (int) ceil($n * 0.75) - 1)],
                'p95' => $values[max(0, (int) ceil($n * 0.95) - 1)],
            ];
        }

        update_option('lwsop_rum_aggregate', $aggregate, false);
        update_option('lwsop_rum_aggregate_ts', time(), false);
    }

    // -------------------------------------------------------------------------
    // Admin AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_force_aggregate()
    {
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);
        self::aggregate();
        wp_send_json_success(['code' => 'OK']);
    }

    /**
     * Purge samples by age.
     * POST param `days`: 0 = delete all, N = delete rows older than N days.
     */
    public static function ajax_purge()
    {
        global $wpdb;
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);

        $days  = isset($_POST['days']) ? absint(wp_unslash($_POST['days'])) : 30;
        $table = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table (name from internal constant, not user input); admin-only, nonce-checked, on-demand purge
        if ($days <= 0) {
            $deleted = $wpdb->query("DELETE FROM `{$table}`");
        } else {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM `{$table}` WHERE collected_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
        }

        // Recompute aggregate after purge
        self::aggregate();

        wp_send_json_success(['deleted' => $deleted]);
    }

    /**
     * Returns all aggregated page/device rows as a JSON array for DataTables.
     */
    public static function ajax_get_table_data()
    {
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);

        $aggregate = get_option('lwsop_rum_aggregate', []);
        $thresholds = self::thresholds();

        // Index by device|path
        $by_page = [];
        foreach ($aggregate as $key => $stats) {
            $parts = explode('|', $key, 3);
            if (count($parts) !== 3) continue;
            [$dev, $path, $metric] = $parts;
            if (!isset($by_page[$dev . '|' . $path])) {
                $by_page[$dev . '|' . $path] = ['path' => $path, 'device' => $dev, 'metrics' => []];
            }
            $by_page[$dev . '|' . $path]['metrics'][$metric] = $stats;
        }

        $rows = [];
        foreach ($by_page as $item) {
            $m   = $item['metrics'];
            $lcp  = $m['LCP']['p75']  ?? null;
            $cls  = $m['CLS']['p75']  ?? null;
            $inp  = $m['INP']['p75']  ?? null;
            $ttfb = $m['TTFB']['p75'] ?? null;
            // Visit count = max samples across all metrics (iOS Safari skips LCP/CLS/INP)
            $visits = max($m['LCP']['n'] ?? 0, $m['TTFB']['n'] ?? 0, $m['INP']['n'] ?? 0, $m['CLS']['n'] ?? 0);

            $rows[] = [
                'path'      => $item['path'],
                'device'    => $item['device'],
                'lcp'       => $lcp,
                'cls'       => $cls,
                'inp'       => $inp,
                'ttfb'      => $ttfb,
                'visits'    => $visits,
                'lcp_cls'   => self::score_class('LCP',  $lcp,  $thresholds),
                'cls_cls'   => self::score_class('CLS',  $cls,  $thresholds),
                'inp_cls'   => self::score_class('INP',  $inp,  $thresholds),
                'ttfb_cls'  => self::score_class('TTFB', $ttfb, $thresholds),
            ];
        }

        // Sort by LCP p75 descending (slowest first)
        usort($rows, function ($a, $b) {
            return ($b['lcp'] ?? 0) <=> ($a['lcp'] ?? 0);
        });

        wp_send_json_success($rows);
    }

    /**
     * Returns the last 200 individual visit rows for a specific page/device.
     * Each row is one beacon: LCP, CLS, INP, TTFB pivoted by collected_at.
     */
    public static function ajax_get_page_samples()
    {
        global $wpdb;
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);

        $path   = sanitize_text_field(substr((string) wp_unslash($_POST['path'] ?? ''), 0, 200));
        $device = in_array($_POST['device'] ?? '', ['mobile', 'desktop'], true) ? sanitize_key(wp_unslash($_POST['device'])) : 'desktop';
        $table  = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom plugin table (name from internal constant, not user input); admin-only, nonce-checked drill-down read
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT collected_at,
                        MAX(CASE WHEN metric='LCP'  THEN value END) AS lcp,
                        MAX(CASE WHEN metric='CLS'  THEN value END) AS cls,
                        MAX(CASE WHEN metric='INP'  THEN value END) AS inp,
                        MAX(CASE WHEN metric='TTFB' THEN value END) AS ttfb
                 FROM `{$table}`
                 WHERE path = %s AND device = %s
                   AND collected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY collected_at
                 ORDER BY collected_at DESC
                 LIMIT 200",
                $path,
                $device
            ),
            ARRAY_A
        );

        wp_send_json_success($rows);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function thresholds()
    {
        return [
            'LCP'  => ['good' => 2500,  'poor' => 4000,  'unit' => 'ms'],
            'CLS'  => ['good' => 0.1,   'poor' => 0.25,  'unit' => ''],
            'INP'  => ['good' => 200,   'poor' => 500,   'unit' => 'ms'],
            'TTFB' => ['good' => 800,   'poor' => 1800,  'unit' => 'ms'],
        ];
    }

    public static function score_class($metric, $value, $thresholds)
    {
        if ($value === null) return 'rum-na';
        $t = $thresholds[$metric] ?? null;
        if (!$t) return 'rum-na';
        if ((float) $value <= $t['good']) return 'rum-good';
        if ((float) $value >= $t['poor']) return 'rum-poor';
        return 'rum-needs';
    }

    public static function format_value($metric, $value, $thresholds)
    {
        if ($value === null) return '—';
        $t = $thresholds[$metric] ?? null;
        if (!$t) return number_format((float) $value, 0);
        if ($t['unit'] === 'ms') return number_format((float) $value, 0) . ' ms';
        return number_format((float) $value, 3);
    }

    private static function client_ip()
    {
        // Trust CF-Connecting-IP only when CF-Ray is also present (proves real CF edge).
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && !empty($_SERVER['HTTP_CF_RAY'])) {
            $ip = trim(explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'])))[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        $ip = trim(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
