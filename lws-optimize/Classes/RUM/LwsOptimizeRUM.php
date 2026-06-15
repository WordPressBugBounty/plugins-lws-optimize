<?php

namespace Lws\Classes\RUM;

/**
 * Real User Monitoring (RUM) — collects Core Web Vitals from actual visitors
 * and aggregates them per URL / device for the LWS Optimize dashboard.
 *
 * Design constraints:
 * - Zero personally identifiable information collected (no IP, no user-agent
 *   string stored, no cookies). Only: URL path, device class (mobile/desktop),
 *   the 4 Core Web Vitals values (LCP, FID/INP, CLS, TTFB).
 * - Anonymous endpoint (no auth) — rate-limited via transient-based throttling
 *   per IP (60 reqs/min).
 * - Beacon-based POST using `navigator.sendBeacon` so it never blocks page-unload.
 * - Aggregation done server-side every 6h to a compact summary stored in the
 *   `lwsop_rum_aggregate` option (p50, p75, p95 per URL/device).
 *
 * Storage:
 * - Raw samples: `lwsop_rum_samples` option (capped at 1000 entries, FIFO).
 * - Aggregates : `lwsop_rum_aggregate` option (per URL, 30 day rolling window).
 */
class LwsOptimizeRUM
{
    const MAX_SAMPLES   = 1000;
    const RATE_LIMIT    = 60; // requests per minute per IP
    const RATE_WINDOW   = 60;

    public static function startActions()
    {
        // Public AJAX endpoint (no auth — but rate-limited and validated)
        add_action('wp_ajax_lwsop_rum_collect',        [__CLASS__, 'collect']);
        add_action('wp_ajax_nopriv_lwsop_rum_collect', [__CLASS__, 'collect']);

        // Inject the web-vitals snippet on every front page
        add_action('wp_footer', [__CLASS__, 'inject_collector_snippet'], 100);

        // Twice-daily aggregation cron + samples auto-purge > 30 days
        add_action('lwsop_rum_aggregate_cron', [__CLASS__, 'aggregate']);
        if (!wp_next_scheduled('lwsop_rum_aggregate_cron')) {
            wp_schedule_event(time() + 600, 'twicedaily', 'lwsop_rum_aggregate_cron');
        }

        // 4.4.0 — Admin handlers (force agg + purge)
        add_action('wp_ajax_lwsop_rum_force_aggregate', [__CLASS__, 'ajax_force_aggregate']);
        add_action('wp_ajax_lwsop_rum_purge_old',       [__CLASS__, 'ajax_purge_old']);
    }

    /**
     * 4.4.0 — Admin AJAX : déclencher l'agrégation maintenant (au lieu d'attendre 12h).
     */
    public static function ajax_force_aggregate()
    {
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);
        self::aggregate();
        wp_send_json_success(['code' => 'OK']);
    }

    /**
     * 4.4.0 — Admin AJAX : purger les samples > 30 jours (libère l'option).
     */
    public static function ajax_purge_old()
    {
        check_ajax_referer('lwsop_rum_admin', '_ajax_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['code' => 'FORBIDDEN'], 403);
        $samples = get_option('lwsop_rum_samples', []);
        $cutoff  = time() - (30 * DAY_IN_SECONDS);
        $kept    = array_values(array_filter($samples, function ($s) use ($cutoff) {
            return ($s['t'] ?? 0) >= $cutoff;
        }));
        update_option('lwsop_rum_samples', $kept, false);
        wp_send_json_success(['kept' => count($kept), 'removed' => count($samples) - count($kept)]);
    }

    /**
     * Inline the tiny web-vitals collector. Uses the official Google library
     * via CDN (5 KB gzipped, async).
     */
    public static function inject_collector_snippet()
    {
        if (is_admin() || is_feed() || is_preview() || is_404()) {
            return;
        }
        $endpoint = esc_url_raw(admin_url('admin-ajax.php?action=lwsop_rum_collect'));
        $path     = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $nonce    = wp_create_nonce('lwsop_rum');
        ?>
<script id="lwsop-rum">
(function(){
  // 4.3.1 — Batched RUM beacon : avant 4 beacons séparés (TTFB+LCP+CLS+INP) à 4
  // appels admin-ajax.php = 4× wp-load.php (~1.5s cumulé loading-spinner browser).
  // Maintenant 1 seul beacon sur pagehide avec toutes les métriques = 1 hit.
  if(!('sendBeacon' in navigator))return;
  var path=<?php echo wp_json_encode($path); ?>;
  var device=/Mobi|Android|iPhone|iPad/.test(navigator.userAgent)?'mobile':'desktop';
  var nonce=<?php echo wp_json_encode($nonce); ?>;
  var endpoint=<?php echo wp_json_encode($endpoint); ?>;
  var metrics={LCP:0,CLS:0,INP:0,TTFB:0};
  var sent=false;
  function flush(){
    if(sent)return;sent=true;
    var b=new Blob([JSON.stringify({batch:1,metrics:metrics,p:path,d:device,n:nonce})],{type:'application/json'});
    navigator.sendBeacon(endpoint,b);
  }
  try{
    var nav=performance.getEntriesByType('navigation')[0];
    if(nav)metrics.TTFB=Math.round((nav.responseStart-nav.requestStart)*100)/100;
  }catch(e){}
  if('PerformanceObserver' in window){
    try{
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(e.startTime>metrics.LCP)metrics.LCP=Math.round(e.startTime*100)/100;});
      }).observe({type:'largest-contentful-paint',buffered:true});
    }catch(e){}
    try{
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(!e.hadRecentInput)metrics.CLS=Math.round((metrics.CLS+e.value)*10000)/10000;});
      }).observe({type:'layout-shift',buffered:true});
    }catch(e){}
    try{
      new PerformanceObserver(function(l){
        l.getEntries().forEach(function(e){if(e.duration>metrics.INP)metrics.INP=Math.round(e.duration*100)/100;});
      }).observe({type:'event',buffered:true,durationThreshold:40});
    }catch(e){}
  }
  addEventListener('pagehide',flush,{once:true});
  // Fallback : si l'utilisateur ne quitte jamais la page (long séjour), on flush après 30s
  setTimeout(flush,30000);
})();
</script>
        <?php
    }

    /**
     * AJAX collector. Validates payload shape, rate-limits, appends to samples.
     */
    public static function collect()
    {
        // 4.4.0 — Anti-overflow sampling pour gros sites :
        // Si lwsop_rum_samples contient déjà 1000+ entries, on échantillonne 1/10
        // (mt_rand) pour ne pas faire exploser l'option.
        $samples_count = count((array) get_option('lwsop_rum_samples', []));
        if ($samples_count >= self::MAX_SAMPLES && mt_rand(1, 10) > 1) {
            // skip silently, return 204-like success
            wp_send_json_success(['code' => 'SAMPLED_OUT']);
        }

        // Rate limit per IP (transient key)
        $ip  = self::client_ip();
        $key = 'lwsop_rum_rl_' . md5($ip);
        $hit = (int) get_transient($key);
        if ($hit >= self::RATE_LIMIT) {
            wp_send_json_error(['code' => 'RATE_LIMITED'], 429);
        }
        set_transient($key, $hit + 1, self::RATE_WINDOW);

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data) || !wp_verify_nonce($data['n'] ?? '', 'lwsop_rum')) {
            wp_send_json_error(['code' => 'BAD_PAYLOAD'], 400);
        }

        $path   = sanitize_text_field(substr((string) ($data['p'] ?? ''), 0, 200));
        $device = in_array($data['d'] ?? '', ['mobile', 'desktop'], true) ? $data['d'] : 'desktop';
        $now    = time();
        $new_samples = [];

        // 4.3.1 — Batched payload : {batch:1, metrics:{LCP,CLS,INP,TTFB}, p, d, n}
        if (!empty($data['batch']) && isset($data['metrics']) && is_array($data['metrics'])) {
            foreach (['LCP', 'CLS', 'INP', 'TTFB'] as $m) {
                if (isset($data['metrics'][$m]) && (float) $data['metrics'][$m] > 0) {
                    $new_samples[] = [
                        't' => $now,
                        'm' => $m,
                        'v' => (float) $data['metrics'][$m],
                        'p' => $path,
                        'd' => $device,
                    ];
                }
            }
        }
        // Legacy single-metric payload (rétrocompat avec versions JS < 4.3.1)
        elseif (isset($data['m'], $data['v'])) {
        $metric = preg_replace('/[^A-Z]/', '', strtoupper((string) $data['m']));
            if (in_array($metric, ['LCP', 'CLS', 'INP', 'TTFB'], true)) {
                $new_samples[] = [
                    't' => $now,
            'm' => $metric,
            'v' => (float) $data['v'],
                    'p' => $path,
                    'd' => $device,
                ];
            }
        }

        if (empty($new_samples)) {
            wp_send_json_error(['code' => 'NO_METRICS'], 400);
        }

        $samples = get_option('lwsop_rum_samples', []);
        $samples = array_merge($samples, $new_samples);
        if (count($samples) > self::MAX_SAMPLES) {
            $samples = array_slice($samples, -self::MAX_SAMPLES);
        }
        update_option('lwsop_rum_samples', $samples, false);
        wp_send_json_success(['saved' => count($new_samples)]);
    }

    /**
     * Aggregate raw samples into per-URL/device percentiles. Called by cron.
     * The aggregate is what the dashboard reads — `lwsop_rum_samples` is just
     * a rolling buffer to be safe under high traffic.
     */
    public static function aggregate()
    {
        $samples = get_option('lwsop_rum_samples', []);
        if (empty($samples)) {
            update_option('lwsop_rum_aggregate_ts', time(), false);
            return;
        }
        // 4.4.0 — Auto-purge des samples > 30 jours pendant l'agrégation
        // (au lieu de les agréger puis les garder en stock pour rien).
        $cutoff = time() - (30 * DAY_IN_SECONDS);
        $fresh  = array_values(array_filter($samples, function ($s) use ($cutoff) {
            return ($s['t'] ?? 0) >= $cutoff;
        }));
        if (count($fresh) !== count($samples)) {
            update_option('lwsop_rum_samples', $fresh, false);
        }

        // Group by device|path|metric
        $groups = [];
        foreach ($fresh as $s) {
            $k = $s['d'] . '|' . $s['p'] . '|' . $s['m'];
            $groups[$k][] = $s['v'];
        }
        $aggregate = [];
        foreach ($groups as $key => $values) {
            sort($values);
            $n = count($values);
            $aggregate[$key] = [
                'n'   => $n,
                'p50' => $values[(int) floor(($n - 1) * 0.50)] ?? null,
                'p75' => $values[(int) floor(($n - 1) * 0.75)] ?? null,
                'p95' => $values[(int) floor(($n - 1) * 0.95)] ?? null,
            ];
        }
        update_option('lwsop_rum_aggregate', $aggregate, false);
        update_option('lwsop_rum_aggregate_ts', time(), false);
    }

    private static function client_ip()
    {
        // Trust CF-Connecting-IP only when CF-Ray is also present (proves real CF edge).
        // X-Forwarded-For is spoofable by any client, so we skip it entirely.
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && !empty($_SERVER['HTTP_CF_RAY'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        $ip = trim($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
