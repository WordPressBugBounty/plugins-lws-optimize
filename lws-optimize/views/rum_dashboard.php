<?php
/**
 * RUM Dashboard view.
 */

use Lws\Classes\RUM\LwsOptimizeRUM;

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('Forbidden');

global $wpdb;

$aggregate    = get_option('lwsop_rum_aggregate', []);
$last_agg     = get_option('lwsop_rum_aggregate_ts', 0);
$config_array = get_option('lws_optimize_config_array', []);
$rum_state    = ($config_array['rum']['state'] ?? 'false') === 'true';

// Real visit count = number of LCP rows in the DB table (one per page view)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, admin-only dashboard render gated by manage_options above
$visit_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM `{$wpdb->prefix}lwsop_rum_samples` WHERE metric = 'LCP'"
);

$thresholds = LwsOptimizeRUM::thresholds();

$metrics_info = [
    'LCP' => [
        'label' => __('Page Load Speed', 'lws-optimize'),
        'full'  => __('Largest Contentful Paint', 'lws-optimize'),
        'desc'  => __('Time until the biggest visible element (image or text block) appears on screen. This is what visitors notice first when they land on your page.', 'lws-optimize'),
        'icon'  => '⏱',
        'gfmt'  => '2.5 s',
        'pfmt'  => '4 s',
    ],
    'CLS' => [
        'label' => __('Layout Stability', 'lws-optimize'),
        'full'  => __('Cumulative Layout Shift', 'lws-optimize'),
        'desc'  => __('How much the page layout shifts while loading. A high score means text and buttons jump around unexpectedly, which frustrates visitors.', 'lws-optimize'),
        'icon'  => '📐',
        'gfmt'  => '0.1',
        'pfmt'  => '0.25',
    ],
    'INP' => [
        'label' => __('Responsiveness', 'lws-optimize'),
        'full'  => __('Interaction to Next Paint', 'lws-optimize'),
        'desc'  => __('How fast the page responds when a visitor clicks, taps or types. A slow score makes the site feel unresponsive and laggy.', 'lws-optimize'),
        'icon'  => '👆',
        'gfmt'  => '200 ms',
        'pfmt'  => '500 ms',
    ],
    'TTFB' => [
        'label' => __('Server Speed', 'lws-optimize'),
        'full'  => __('Time to First Byte', 'lws-optimize'),
        'desc'  => __('How fast your server starts sending data back to the visitor. A slow server slows down every single page — fix this first.', 'lws-optimize'),
        'icon'  => '⚡',
        'gfmt'  => '800 ms',
        'pfmt'  => '1.8 s',
    ],
];
?>

<div class="lwsoptimize_container">
    <?php $is_deactivated = get_option('lws_optimize_deactivate_temporarily', false); ?>
    <?php include LWS_OP_DIR . '/views/_header_banner.php'; ?>

    <div class="lwsop_oneclickconfig_main rum-wrap">

    <?php if (!$rum_state) : ?>
        <div class="lwsop_oneclickconfig_block">
            <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Real Visitor Performance (RUM)', 'lws-optimize'); ?></h2>
            <div class="lwsop_bluebanner_subtitle"><?php esc_html_e('Measure your website\'s real speed as experienced by actual visitors — anonymously, without any cookie.', 'lws-optimize'); ?></div>
            <div class="rum-notice warn" style="margin-top:16px">
                <?php esc_html_e('RUM is currently disabled. To start collecting real visitor performance data, enable it in the "Advanced integrations" panel (available in Advanced mode).', 'lws-optimize'); ?>
            </div>
        </div>

    <?php else : ?>

    <!-- ── Block 1 : Controls ──────────────────────────────────────────── -->
    <div class="lwsop_oneclickconfig_block">
        <div class="rum-header-grid">
            <div class="rum-header-left">
                <h2 class="lwsop_bluebanner_title" style="margin:0"><?php esc_html_e('Real Visitor Performance (RUM)', 'lws-optimize'); ?></h2>
                <p class="rum-intro">
                    <?php esc_html_e('These scores come from real visitors browsing your site — not from a lab test. They show exactly what your visitors experience, and what Google measures to rank your pages.', 'lws-optimize'); ?>
                </p>

                <div class="rum-stats-row">
                    <div>
                        <span class="rum-stat-num"><?php echo esc_html(number_format($visit_count)); ?></span>
                        <span class="rum-stat-lbl"><?php esc_html_e('visits recorded', 'lws-optimize'); ?></span>
                    </div>
                    <div class="rum-stat-sep"></div>
                    <div class="rum-stat-sub">
                        <?php esc_html_e('Last update:', 'lws-optimize'); ?>&nbsp;
                        <strong>
                        <?php if ($last_agg > 0) :
                            echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_agg));
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

                    <select id="lwsop_rum_purge_days" class="rum-purge-select">
                        <option value="1"><?php esc_html_e('Older than 1 day', 'lws-optimize'); ?></option>
                        <option value="2"><?php esc_html_e('Older than 2 days', 'lws-optimize'); ?></option>
                        <option value="7"><?php esc_html_e('Older than 7 days', 'lws-optimize'); ?></option>
                        <option value="30" selected><?php esc_html_e('Older than 30 days', 'lws-optimize'); ?></option>
                        <option value="90"><?php esc_html_e('Older than 90 days', 'lws-optimize'); ?></option>
                        <option value="0"><?php esc_html_e('All data', 'lws-optimize'); ?></option>
                    </select>
                    <button type="button" class="lwsop_blue_button" id="lwsop_rum_purge">
                        <span><?php esc_html_e('Delete selected data', 'lws-optimize'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Block 2 : What do these scores mean? ──────────────────────── -->
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
                            <?php /* translators: %s: Metric threshold value, e.g. "2.5 s" */ ?>
                            <?php echo esc_html(sprintf(__('under %s', 'lws-optimize'), $info['gfmt'])); ?>
                        </span>
                    </div>
                    <div class="rum-threshold-row">
                        <span class="rum-dot needs"></span>
                        <span class="rum-threshold-key"><?php esc_html_e('Needs work:', 'lws-optimize'); ?></span>
                        <span class="rum-threshold-range">
                            <?php echo esc_html(sprintf('%1$s – %2$s', $info['gfmt'], $info['pfmt'])); ?>
                        </span>
                    </div>
                    <div class="rum-threshold-row">
                        <span class="rum-dot poor"></span>
                        <span class="rum-threshold-key"><?php esc_html_e('Poor:', 'lws-optimize'); ?></span>
                        <span class="rum-threshold-range">
                            <?php /* translators: %s: Metric threshold value, e.g. "4 s" */ ?>
                            <?php echo esc_html(sprintf(__('over %s', 'lws-optimize'), $info['pfmt'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Block 3 : DataTables page table ───────────────────────────── -->
    <div class="lwsop_oneclickconfig_block">
        <div class="rum-table-header">
            <h3><?php esc_html_e('Performance by page', 'lws-optimize'); ?></h3>
            <p><?php esc_html_e('Sorted by page load speed — the slowest pages appear first. Use the column filters to narrow down. Focus on red and orange values first.', 'lws-optimize'); ?></p>
        </div>

        <div id="lwsop-rum-table-wrap">
            <div class="rum-dt-loading"><?php esc_html_e('Loading data…', 'lws-optimize'); ?></div>
        </div>

        <?php if (!empty($aggregate)) : ?>
        <div class="rum-legend">
            <strong><?php esc_html_e('How to read these scores:', 'lws-optimize'); ?></strong>
            <?php esc_html_e('"p75" means 75% of your visitors got this score or better — it\'s a realistic measure, not a best-case scenario.', 'lws-optimize'); ?>
            <br>
            <span><span class="rum-legend-dot" style="background:#16a34a"></span> <?php esc_html_e('Green = Good', 'lws-optimize'); ?></span>
            <span><span class="rum-legend-dot" style="background:#ea580c"></span> <?php esc_html_e('Orange = Needs improvement', 'lws-optimize'); ?></span>
            <span><span class="rum-legend-dot" style="background:#dc2626"></span> <?php esc_html_e('Red = Poor', 'lws-optimize'); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; // rum_state ?>

    </div><!-- /.lwsop_oneclickconfig_main.rum-wrap -->
</div><!-- /.lwsoptimize_container -->

