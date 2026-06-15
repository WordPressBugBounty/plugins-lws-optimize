<?php
/**
 * 4.4.0 — Dashboard RUM (Real User Monitoring).
 * 4.5.4 — Refonte avec classes natives du plugin.
 * 4.6.0 — Redesign beginner-friendly + full WordPress i18n (no more $is_fr).
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Forbidden');

$aggregate     = get_option('lwsop_rum_aggregate', []);
$samples       = get_option('lwsop_rum_samples', []);
$last_agg      = get_option('lwsop_rum_aggregate_ts', 0);
$config_array  = get_option('lws_optimize_config_array', []);
$rum_state     = ($config_array['rum']['state'] ?? 'false') === 'true';
$device_filter = isset($_GET['device']) ? sanitize_text_field(wp_unslash($_GET['device'])) : 'all';

// Restructure aggregate by page (key: device|path|metric)
$by_page = [];
foreach ($aggregate as $key => $stats) {
    $parts = explode('|', $key, 3);
    if (count($parts) !== 3) continue;
    list($dev, $path, $metric) = $parts;
    if ($device_filter !== 'all' && $device_filter !== $dev) continue;
    if (!isset($by_page[$path])) $by_page[$path] = ['desktop' => [], 'mobile' => [], 'n' => 0];
    $by_page[$path][$dev][$metric] = $stats;
    $by_page[$path]['n'] += $stats['n'] ?? 0;
}

// Sort by LCP p75 descending — slowest pages first
uasort($by_page, function ($a, $b) {
    $lcp = function ($r) { return max($r['desktop']['LCP']['p75'] ?? 0, $r['mobile']['LCP']['p75'] ?? 0); };
    return $lcp($b) <=> $lcp($a);
});
$top = array_slice($by_page, 0, 20, true);

// Google Core Web Vitals thresholds
$thresholds = [
    'LCP'  => ['good' => 2500, 'poor' => 4000, 'unit' => 'ms', 'gfmt' => '2.5 s',  'pfmt' => '4 s'],
    'CLS'  => ['good' => 0.1,  'poor' => 0.25, 'unit' => '',   'gfmt' => '0.1',     'pfmt' => '0.25'],
    'INP'  => ['good' => 200,  'poor' => 500,  'unit' => 'ms', 'gfmt' => '200 ms',  'pfmt' => '500 ms'],
    'TTFB' => ['good' => 800,  'poor' => 1800, 'unit' => 'ms', 'gfmt' => '800 ms',  'pfmt' => '1.8 s'],
];

if (!function_exists('lwsop_rum_cls')) {
    function lwsop_rum_cls($metric, $value, $thresholds) {
        if ($value === null || $value === '') return 'rum-na';
        $t = $thresholds[$metric] ?? null;
        if (!$t) return 'rum-na';
        if ((float)$value <= $t['good']) return 'rum-good';
        if ((float)$value >= $t['poor']) return 'rum-poor';
        return 'rum-needs';
    }
}
if (!function_exists('lwsop_rum_val')) {
    function lwsop_rum_val($metric, $value, $thresholds) {
        if ($value === null || $value === '') return '—';
        $t = $thresholds[$metric] ?? null;
        if (!$t) return number_format((float)$value, 0);
        if ($t['unit'] === 'ms') return number_format((float)$value, 0) . ' ms';
        return number_format((float)$value, 3);
    }
}

// Metric definitions with plain-language descriptions
$metrics_info = [
    'LCP' => [
        'label' => __('Page Load Speed', 'lws-optimize'),
        'full'  => __('Largest Contentful Paint', 'lws-optimize'),
        'desc'  => __('Time until the biggest visible element (image or text block) appears on screen. This is what visitors notice first when they land on your page.', 'lws-optimize'),
        'icon'  => '⏱',
        'key'   => 'LCP',
    ],
    'CLS' => [
        'label' => __('Layout Stability', 'lws-optimize'),
        'full'  => __('Cumulative Layout Shift', 'lws-optimize'),
        'desc'  => __('How much the page layout shifts while loading. A high score means text and buttons jump around unexpectedly, which frustrates visitors.', 'lws-optimize'),
        'icon'  => '📐',
        'key'   => 'CLS',
    ],
    'INP' => [
        'label' => __('Responsiveness', 'lws-optimize'),
        'full'  => __('Interaction to Next Paint', 'lws-optimize'),
        'desc'  => __('How fast the page responds when a visitor clicks, taps or types. A slow score makes the site feel unresponsive and laggy.', 'lws-optimize'),
        'icon'  => '👆',
        'key'   => 'INP',
    ],
    'TTFB' => [
        'label' => __('Server Speed', 'lws-optimize'),
        'full'  => __('Time to First Byte', 'lws-optimize'),
        'desc'  => __('How fast your server starts sending data back to the visitor. A slow server slows down every single page — fix this first.', 'lws-optimize'),
        'icon'  => '⚡',
        'key'   => 'TTFB',
    ],
];

$score_labels = [
    'rum-good'  => __('Good',       'lws-optimize'),
    'rum-needs' => __('Needs work', 'lws-optimize'),
    'rum-poor'  => __('Poor',       'lws-optimize'),
];
?>

<style>
/* ── RUM Dashboard ─────────────────────────────────────────── */
.rum-wrap { display:flex; flex-direction:column; gap:24px; }

/* Controls block */
.rum-header-grid { display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; }
.rum-header-left { flex:1; min-width:240px; }
.rum-intro { font-size:13px; color:#475569; line-height:1.7; margin:8px 0 0; max-width:620px; }

.rum-stats-row { display:flex; gap:32px; flex-wrap:wrap; align-items:baseline; margin:20px 0 0; }
.rum-stat-num  { font-size:30px; font-weight:700; color:#1e40af; }
.rum-stat-lbl  { font-size:13px; color:#64748b; margin-left:6px; }
.rum-stat-sep  { width:1px; background:#e2e8f0; align-self:stretch; }
.rum-stat-sub  { font-size:13px; color:#64748b; }
.rum-stat-sub strong { color:#334155; }

.rum-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }

.rum-filterbar { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
.rum-filterbar a { padding:5px 16px; border:1.5px solid #cbd5e1; border-radius:20px; text-decoration:none; font-size:13px; color:#475569; background:#fff; transition:all .15s; }
.rum-filterbar a:hover { background:#f1f5f9; border-color:#94a3b8; color:#1e293b; }
.rum-filterbar a.active { background:#1e40af; color:#fff; border-color:#1e40af; font-weight:600; }

/* Metric explanation cards */
.rum-cards-header { margin-bottom:18px; }
.rum-cards-header h3 { margin:0 0 4px; font-size:15px; font-weight:700; color:#1e293b; }
.rum-cards-header p  { margin:0; font-size:12px; color:#64748b; line-height:1.5; }

.rum-metric-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
@media (max-width:1100px) { .rum-metric-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:600px)  { .rum-metric-grid { grid-template-columns:1fr; } }

.rum-metric-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px; }
.rum-metric-card-icon  { font-size:20px; line-height:1; margin-bottom:8px; }
.rum-metric-card-label { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:1px; }
.rum-metric-card-acro  { font-size:10px; font-family:monospace; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.rum-metric-card-desc  { font-size:12px; color:#475569; line-height:1.6; margin-bottom:12px; min-height:52px; }
.rum-metric-card-thresholds { display:flex; flex-direction:column; gap:4px; font-size:11px; }
.rum-threshold-row { display:flex; align-items:center; gap:6px; color:#475569; }
.rum-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.rum-dot.good  { background:#16a34a; }
.rum-dot.needs { background:#ea580c; }
.rum-dot.poor  { background:#dc2626; }
.rum-threshold-key   { font-weight:600; min-width:72px; }
.rum-threshold-range { color:#94a3b8; }

/* Table block */
.rum-table-header { margin-bottom:16px; }
.rum-table-header h3 { margin:0 0 4px; font-size:15px; font-weight:700; color:#1e293b; }
.rum-table-header p  { margin:0; font-size:12px; color:#64748b; }

.rum-table-wrap { overflow-x:auto; }
table.rum-table { width:100%; border-collapse:collapse; font-size:13px; }
table.rum-table thead th {
    background:#f1f5f9;
    padding:8px 14px 10px;
    text-align:left;
    border-bottom:2px solid #e2e8f0;
    vertical-align:bottom;
    white-space:nowrap;
}
table.rum-table thead th .th-main { display:block; font-size:12px; font-weight:700; color:#334155; white-space:nowrap; }
table.rum-table thead th .th-sub  { display:block; font-size:10px; font-weight:400; color:#94a3b8; margin-top:1px; font-family:monospace; }
table.rum-table thead th.th-right { text-align:right; }
table.rum-table tbody td { padding:9px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
table.rum-table tbody tr:last-child td { border-bottom:none; }
table.rum-table tbody tr:hover td { background:#fafbfc; }

.rum-url { font-family:monospace; font-size:12px; color:#475569; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:block; }
.rum-device-badge {
    display:inline-flex; align-items:center; gap:4px;
    font-size:11px; color:#64748b;
    background:#f1f5f9; border-radius:4px; padding:2px 7px;
}

.rum-cell { display:flex; flex-direction:column; align-items:flex-end; }
.rum-cell-val { font-weight:700; font-size:13px; line-height:1.2; }
.rum-cell-badge { font-size:9px; font-weight:700; border-radius:3px; padding:1px 5px; margin-top:3px; text-transform:uppercase; letter-spacing:.3px; }

/* Score colors */
.rum-good  .rum-cell-val  { color:#16a34a; }
.rum-needs .rum-cell-val  { color:#ea580c; }
.rum-poor  .rum-cell-val  { color:#dc2626; }
.rum-na    .rum-cell-val  { color:#cbd5e1; }
.rum-good  .rum-cell-badge { background:#dcfce7; color:#15803d; }
.rum-needs .rum-cell-badge { background:#ffedd5; color:#c2410c; }
.rum-poor  .rum-cell-badge { background:#fee2e2; color:#b91c1c; }

.rum-visits { text-align:right; font-size:12px; color:#94a3b8; }

/* Notices */
.rum-notice { padding:14px 18px; border-radius:8px; font-size:13px; line-height:1.7; }
.rum-notice.warn { background:#fffbeb; border-left:4px solid #f59e0b; color:#78350f; }
.rum-notice.info { background:#eff6ff; border-left:4px solid #3b82f6; color:#1e40af; }

/* Legend */
.rum-legend { font-size:11px; color:#94a3b8; line-height:1.8; padding-top:14px; border-top:1px solid #f1f5f9; margin-top:16px; }
.rum-legend span { display:inline-flex; align-items:center; gap:4px; margin-right:12px; white-space:nowrap; }
.rum-legend-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
</style>

<div class="lwsoptimize_container">
    <?php $is_deactivated = get_option('lws_optimize_deactivate_temporarily', false); ?>
    <?php include LWS_OP_DIR . '/views/_header_banner.php'; ?>

    <div class="lwsop_oneclickconfig_main rum-wrap">

    <?php if (!$rum_state) : ?>
        <!-- RUM disabled -->
        <div class="lwsop_oneclickconfig_block">
            <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Real Visitor Performance (RUM)', 'lws-optimize'); ?></h2>
            <div class="lwsop_bluebanner_subtitle"><?php esc_html_e('Measure your website\'s real speed as experienced by actual visitors — anonymously, without any cookie.', 'lws-optimize'); ?></div>
            <div class="rum-notice warn" style="margin-top:16px">
                <?php esc_html_e('RUM is currently disabled. To start collecting real visitor performance data, enable it in the "Advanced integrations" panel (available in Advanced mode).', 'lws-optimize'); ?>
            </div>
        </div>

    <?php else : ?>

    <!-- ── Block 1 : Controls ──────────────────────────────────── -->
    <div class="lwsop_oneclickconfig_block">
        <div class="rum-header-grid">
            <div class="rum-header-left">
                <h2 class="lwsop_bluebanner_title" style="margin:0"><?php esc_html_e('Real Visitor Performance (RUM)', 'lws-optimize'); ?></h2>
                <p class="rum-intro">
                    <?php esc_html_e('These scores come from real visitors browsing your site — not from a lab test. They show exactly what your visitors experience, and what Google measures to rank your pages.', 'lws-optimize'); ?>
                </p>

                <div class="rum-stats-row">
                    <div>
                        <span class="rum-stat-num"><?php echo esc_html(count($samples)); ?></span>
                        <span class="rum-stat-lbl"><?php esc_html_e('visits recorded', 'lws-optimize'); ?></span>
                    </div>
                    <div class="rum-stat-sep"></div>
                    <div class="rum-stat-sub">
                        <?php esc_html_e('Last update:', 'lws-optimize'); ?>&nbsp;
                        <strong>
                        <?php if ($last_agg > 0) :
                            /* translators: %s: human-readable time difference, e.g. "5 minutes" */
                            echo esc_html(sprintf(__('%s ago', 'lws-optimize'), human_time_diff($last_agg, time())));
                        else :
                            esc_html_e('never', 'lws-optimize');
                        endif; ?>
                        </strong>
                    </div>
                </div>

                <div class="rum-actions">
                    <button type="button" class="lwsop_darkblue_button" id="lwsop_rum_force_agg">
                        <span><?php esc_html_e('Refresh data now', 'lws-optimize'); ?></span>
                    </button>
                    <button type="button" class="lwsop_blue_button" id="lwsop_rum_purge">
                        <span><?php esc_html_e('Delete old data (> 30 days)', 'lws-optimize'); ?></span>
                    </button>
                </div>

                <div class="rum-filterbar">
                    <?php
                    $filters = [
                        'all'     => __('All devices', 'lws-optimize'),
                        'desktop' => __('Desktop', 'lws-optimize'),
                        'mobile'  => __('Mobile', 'lws-optimize'),
                    ];
                    foreach ($filters as $val => $lbl) :
                        $cls = $device_filter === $val ? 'active' : '';
                    ?>
                    <a class="<?php echo esc_attr($cls); ?>" href="<?php echo esc_url(admin_url('admin.php?page=lws-op-rum&device=' . $val)); ?>"><?php echo esc_html($lbl); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Block 2 : What do these scores mean? ───────────────── -->
    <div class="lwsop_oneclickconfig_block">
        <div class="rum-cards-header">
            <h3><?php esc_html_e('What do these scores measure?', 'lws-optimize'); ?></h3>
            <p><?php esc_html_e('Google uses these 4 metrics (called Core Web Vitals) to measure the quality of your visitors\' experience. They directly influence your Google search ranking.', 'lws-optimize'); ?></p>
        </div>
        <div class="rum-metric-grid">
        <?php foreach ($metrics_info as $metric_key => $info) :
            $t = $thresholds[$metric_key];
        ?>
            <div class="rum-metric-card">
                <div class="rum-metric-card-icon"><?php echo esc_html($info['icon']); ?></div>
                <div class="rum-metric-card-label"><?php echo esc_html($info['label']); ?></div>
                <div class="rum-metric-card-acro"><?php echo esc_html($metric_key . ' — ' . $info['full']); ?></div>
                <div class="rum-metric-card-desc"><?php echo esc_html($info['desc']); ?></div>
                <div class="rum-metric-card-thresholds">
                    <div class="rum-threshold-row">
                        <span class="rum-dot good"></span>
                        <span class="rum-threshold-key"><?php esc_html_e('Good:', 'lws-optimize'); ?></span>
                        <span class="rum-threshold-range">
                            <?php
                            if ($metric_key === 'CLS') {
                                /* translators: threshold value */
                                echo esc_html(sprintf(__('under %s', 'lws-optimize'), $t['gfmt']));
                            } else {
                                echo esc_html(sprintf(__('under %s', 'lws-optimize'), $t['gfmt']));
                            }
                            ?>
                        </span>
                    </div>
                    <div class="rum-threshold-row">
                        <span class="rum-dot needs"></span>
                        <span class="rum-threshold-key"><?php esc_html_e('Needs work:', 'lws-optimize'); ?></span>
                        <span class="rum-threshold-range">
                            <?php
                            /* translators: 1: lower bound, 2: upper bound */
                            echo esc_html(sprintf(__('%1$s – %2$s', 'lws-optimize'), $t['gfmt'], $t['pfmt']));
                            ?>
                        </span>
                    </div>
                    <div class="rum-threshold-row">
                        <span class="rum-dot poor"></span>
                        <span class="rum-threshold-key"><?php esc_html_e('Poor:', 'lws-optimize'); ?></span>
                        <span class="rum-threshold-range">
                            <?php echo esc_html(sprintf(__('over %s', 'lws-optimize'), $t['pfmt'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Block 3 : Page table ───────────────────────────────── -->
    <div class="lwsop_oneclickconfig_block">
        <div class="rum-table-header">
            <h3><?php esc_html_e('Your 20 slowest pages', 'lws-optimize'); ?></h3>
            <p><?php esc_html_e('Sorted by page load speed — the slowest pages appear first. Focus on red and orange values first.', 'lws-optimize'); ?></p>
        </div>

        <?php if (empty($top)) : ?>
            <div class="rum-notice info">
                <?php esc_html_e('No data collected yet. RUM starts recording as soon as visitors browse your site. Data is refreshed every 12 hours — you can also click "Refresh data now" above.', 'lws-optimize'); ?>
            </div>
        <?php else : ?>
            <div class="rum-table-wrap">
                <table class="rum-table">
                    <thead>
                        <tr>
                            <th>
                                <span class="th-main"><?php esc_html_e('Page', 'lws-optimize'); ?></span>
                            </th>
                            <th>
                                <span class="th-main"><?php esc_html_e('Device', 'lws-optimize'); ?></span>
                            </th>
                            <th class="th-right">
                                <span class="th-main"><?php esc_html_e('Page Load Speed', 'lws-optimize'); ?></span>
                                <span class="th-sub">LCP p75</span>
                            </th>
                            <th class="th-right">
                                <span class="th-main"><?php esc_html_e('Layout Stability', 'lws-optimize'); ?></span>
                                <span class="th-sub">CLS p75</span>
                            </th>
                            <th class="th-right">
                                <span class="th-main"><?php esc_html_e('Responsiveness', 'lws-optimize'); ?></span>
                                <span class="th-sub">INP p75</span>
                            </th>
                            <th class="th-right">
                                <span class="th-main"><?php esc_html_e('Server Speed', 'lws-optimize'); ?></span>
                                <span class="th-sub">TTFB p75</span>
                            </th>
                            <th class="th-right">
                                <span class="th-main"><?php esc_html_e('Visits', 'lws-optimize'); ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top as $path => $data) :
                        foreach (['desktop', 'mobile'] as $dev) :
                            if (empty($data[$dev])) continue;
                            $row  = $data[$dev];
                            $lcp  = $row['LCP']['p75']  ?? null;
                            $cls  = $row['CLS']['p75']  ?? null;
                            $inp  = $row['INP']['p75']  ?? null;
                            $ttfb = $row['TTFB']['p75'] ?? null;
                            $n    = $row['LCP']['n'] ?? $row['TTFB']['n'] ?? 0;

                            $dev_icon  = $dev === 'desktop' ? '🖥' : '📱';
                            $dev_label = $dev === 'desktop'
                                ? __('Desktop', 'lws-optimize')
                                : __('Mobile', 'lws-optimize');

                            $cells = [
                                'LCP'  => $lcp,
                                'CLS'  => $cls,
                                'INP'  => $inp,
                                'TTFB' => $ttfb,
                            ];
                    ?>
                        <tr>
                            <td>
                                <span class="rum-url" title="<?php echo esc_attr($path); ?>">
                                    <?php echo esc_html(strlen($path) > 55 ? substr($path, 0, 52) . '…' : $path); ?>
                                </span>
                            </td>
                            <td>
                                <span class="rum-device-badge"><?php echo esc_html($dev_icon . ' ' . $dev_label); ?></span>
                            </td>
                            <?php foreach ($cells as $metric => $value) :
                                $cls_class = lwsop_rum_cls($metric, $value, $thresholds);
                                $fmt_val   = lwsop_rum_val($metric, $value, $thresholds);
                                $badge     = $cls_class !== 'rum-na' ? ($score_labels[$cls_class] ?? '') : '';
                            ?>
                            <td style="text-align:right">
                                <div class="rum-cell <?php echo esc_attr($cls_class); ?>">
                                    <span class="rum-cell-val"><?php echo esc_html($fmt_val); ?></span>
                                    <?php if ($badge) : ?>
                                    <span class="rum-cell-badge"><?php echo esc_html($badge); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                            <td class="rum-visits"><?php echo esc_html($n); ?></td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="rum-legend">
                <strong><?php esc_html_e('How to read these scores:', 'lws-optimize'); ?></strong>
                <?php esc_html_e('"p75" means 75% of your visitors got this score or better — it\'s a realistic measure, not a best-case scenario.', 'lws-optimize'); ?>
                <br>
                <span><span class="rum-legend-dot" style="background:#16a34a"></span> <?php esc_html_e('Green = Good', 'lws-optimize'); ?></span>
                <span><span class="rum-legend-dot" style="background:#ea580c"></span> <?php esc_html_e('Orange = Needs improvement', 'lws-optimize'); ?></span>
                <span><span class="rum-legend-dot" style="background:#dc2626"></span> <?php esc_html_e('Red = Poor', 'lws-optimize'); ?></span>
                <br>
                <?php esc_html_e('Note: to avoid overloading the server, a maximum of 1,000 visits are stored at a time. On very busy sites, only 1 in 10 visits is recorded.', 'lws-optimize'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; // rum_state ?>

    </div><!-- /.lwsop_oneclickconfig_main.rum-wrap -->
</div><!-- /.lwsoptimize_container -->

<script>
(function () {
    var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    var forceBtn = document.getElementById('lwsop_rum_force_agg');
    if (forceBtn) {
        forceBtn.addEventListener('click', function () {
            forceBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'lwsop_rum_force_aggregate');
            fd.append('_ajax_nonce', '<?php echo wp_create_nonce('lwsop_rum_admin'); ?>');
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        if (typeof callPopup === 'function') callPopup('success', '<?php echo esc_js(__('Data refreshed — reloading…', 'lws-optimize')); ?>');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        if (typeof callPopup === 'function') callPopup('error', '<?php echo esc_js(__('Refresh failed', 'lws-optimize')); ?>');
                        forceBtn.disabled = false;
                    }
                })
                .catch(function () { forceBtn.disabled = false; });
        });
    }

    var purgeBtn = document.getElementById('lwsop_rum_purge');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            if (!confirm('<?php echo esc_js(__('Delete all visit data older than 30 days?', 'lws-optimize')); ?>')) return;
            purgeBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'lwsop_rum_purge_old');
            fd.append('_ajax_nonce', '<?php echo wp_create_nonce('lwsop_rum_admin'); ?>');
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        if (typeof callPopup === 'function') callPopup('success', '<?php echo esc_js(__('Old data deleted — reloading…', 'lws-optimize')); ?>');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        if (typeof callPopup === 'function') callPopup('error', '<?php echo esc_js(__('Deletion failed', 'lws-optimize')); ?>');
                        purgeBtn.disabled = false;
                    }
                })
                .catch(function () { purgeBtn.disabled = false; });
        });
    }
})();
</script>
