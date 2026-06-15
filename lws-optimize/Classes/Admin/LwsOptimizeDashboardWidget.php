<?php

namespace Lws\Classes\Admin;

use Lws\Classes\FileCache\LwsOptimizeUsageStats;

/**
 * 4.5.0 — Widget Dashboard WordPress (apparaît sur /wp-admin/index.php).
 *
 * Donne en un coup d'œil :
 * - État global du plugin (actif / inactif / configuration suboptimale)
 * - État de chaque module : file cache, Memcached, CSS critique, Google Fonts, RUM
 * - Stats Memcached live (si activé) : mémoire, hit rate, keys
 * - Utilisation réelle 24h : hits / misses / hit rate / bytes saved
 * - Sparkline 30j des hits/jour
 * - Lien vers le tableau de bord du plugin
 *
 * Aucun appel AJAX au load — tout est rendu en PHP. Léger.
 */
class LwsOptimizeDashboardWidget
{
    public static function startActions()
    {
        add_action('wp_dashboard_setup', [__CLASS__, 'register_widget']);
    }

    public static function register_widget()
    {
        if (!current_user_can('manage_options')) return;
        wp_add_dashboard_widget(
            'lwsop_dashboard_widget',
            '⚡ LWS Optimize',
            [__CLASS__, 'render_widget']
        );
    }

    public static function render_widget()
    {
        $cfg = get_option('lws_optimize_config_array', []);

        // État des modules
        $modules = [
            'filebased_cache' => ['label' => __('Cache fichier', 'lws-optimize'),     'state' => ($cfg['filebased_cache']['state'] ?? 'false') === 'true'],
            'memcached'       => ['label' => 'Memcached',                              'state' => ($cfg['memcached']['state']       ?? 'false') === 'true'],
            'critical_css'    => ['label' => __('CSS critique', 'lws-optimize'),       'state' => ($cfg['critical_css']['state']    ?? 'false') === 'true', 'extra' => ($cfg['critical_css']['mode'] ?? 'off')],
            'font_preload'    => ['label' => __('Google Fonts preconnect', 'lws-optimize'), 'state' => ($cfg['font_preload']['state'] ?? 'false') === 'true'],
            'rum'             => ['label' => 'RUM',                                    'state' => ($cfg['rum']['state']             ?? 'false') === 'true'],
            'cloudflare_apo'  => ['label' => 'Cloudflare APO',                         'state' => ($cfg['cloudflare_apo']['state']  ?? 'false') === 'true'],
        ];

        // Stats utilisation
        $stats = LwsOptimizeUsageStats::read();
        $t24h  = $stats['totals_24h'];

        // Stats Memcached live
        $memcached_stats = self::memcached_stats();

        // Coverage rapide (URLs sitemap réellement en cache)
        $sitemap = get_option('lws_optimize_sitemap_urls', ['urls' => []]);
        $urls    = is_array($sitemap['urls'] ?? null) ? array_values($sitemap['urls']) : [];
        $cov_total = count($urls);
        $cov_d = $cov_m = 0;
        $cache_root_d = WP_CONTENT_DIR . '/cache/lwsoptimize/cache';
        $cache_root_m = WP_CONTENT_DIR . '/cache/lwsoptimize/cache-mobile';
        foreach ($urls as $u) {
            $path = trim(parse_url($u, PHP_URL_PATH) ?: '/', '/');
            $fd = $path === '' ? $cache_root_d . '/index_0.html' : $cache_root_d . '/' . $path . '/index_0.html';
            $fm = $path === '' ? $cache_root_m . '/index_0.html' : $cache_root_m . '/' . $path . '/index_0.html';
            if (@is_file($fd)) $cov_d++;
            if (@is_file($fm)) $cov_m++;
        }
        $cov_pct = $cov_total > 0 ? round((($cov_d + $cov_m) / ($cov_total * 2)) * 100) : 0;

        // Global health
        $health_score = 0; $health_max = 0;
        foreach ($modules as $key => $m) {
            $health_max++;
            if ($m['state']) $health_score++;
        }
        $health_pct = $health_max > 0 ? round(($health_score / $health_max) * 100) : 0;
        $health_color = $health_pct >= 70 ? '#16a34a' : ($health_pct >= 40 ? '#f59e0b' : '#dc2626');
        $health_label = $health_pct >= 70 ? __('Bonne config', 'lws-optimize') : ($health_pct >= 40 ? __('Améliorable', 'lws-optimize') : __('À configurer', 'lws-optimize'));

        ?>
        <style>
            .lwsop_dw_grid { display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:12px }
            .lwsop_dw_card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px }
            .lwsop_dw_card h4 { margin:0 0 6px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; color:#64748b }
            .lwsop_dw_modules li { display:flex; justify-content:space-between; padding:3px 0; font-size:12px; border-bottom:1px solid #f1f5f9 }
            .lwsop_dw_modules li:last-child { border-bottom:none }
            .lwsop_dw_modules .lwsop_dw_status { font-weight:600 }
            .lwsop_dw_modules .on  { color:#16a34a }
            .lwsop_dw_modules .off { color:#94a3b8 }
            .lwsop_dw_modules .ext { color:#64748b; font-size:11px; font-style:italic; margin-left:4px }
            .lwsop_dw_metric { display:flex; justify-content:space-between; padding:3px 0; font-size:12px }
            .lwsop_dw_metric strong { font-weight:600; color:#0f172a }
            .lwsop_dw_health_bar { height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin:6px 0 }
            .lwsop_dw_health_bar > div { height:100%; transition:width 0.4s ease }
            .lwsop_dw_sparkline svg { display:block; width:100%; height:36px }
            .lwsop_dw_footer { margin-top:12px; padding-top:10px; border-top:1px solid #e2e8f0; text-align:center; font-size:12px }
            .lwsop_dw_footer a { color:#2563eb; text-decoration:none; font-weight:500 }
            .lwsop_dw_footer a:hover { text-decoration:underline }
        </style>

        <!-- Header global -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding:8px 12px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);border-radius:6px;color:#fff">
            <div>
                <strong style="font-size:14px"><?php esc_html_e('État global', 'lws-optimize'); ?></strong>
                <div style="font-size:11px;opacity:.9"><?php echo esc_html($health_label); ?> · <?php echo esc_html($health_score . '/' . $health_max); ?> <?php esc_html_e('modules actifs', 'lws-optimize'); ?></div>
            </div>
            <div style="text-align:right">
                <div style="font-size:22px;font-weight:700;line-height:1"><?php echo esc_html($health_pct); ?>%</div>
                <div style="font-size:10px;opacity:.85;text-transform:uppercase;letter-spacing:0.4px"><?php esc_html_e('santé', 'lws-optimize'); ?></div>
            </div>
        </div>

        <div class="lwsop_dw_grid">
            <!-- Modules -->
            <div class="lwsop_dw_card">
                <h4>📦 <?php esc_html_e('Modules', 'lws-optimize'); ?></h4>
                <ul class="lwsop_dw_modules" style="list-style:none;padding:0;margin:0">
                <?php foreach ($modules as $key => $m) : ?>
                    <li>
                        <span><?php echo esc_html($m['label']); ?>
                            <?php if (isset($m['extra']) && $m['state']) : ?><span class="ext"><?php echo esc_html($m['extra']); ?></span><?php endif; ?>
                        </span>
                        <span class="lwsop_dw_status <?php echo $m['state'] ? 'on' : 'off'; ?>">
                            <?php echo $m['state'] ? '✓' : '✗'; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>

            <!-- Performance / Utilisation 24h -->
            <div class="lwsop_dw_card">
                <h4>📊 <?php esc_html_e('Utilisation 24h', 'lws-optimize'); ?></h4>
                <?php if ($t24h['hits'] + $t24h['misses'] === 0) : ?>
                    <p style="font-size:11px;color:#94a3b8;font-style:italic;margin:8px 0 0">
                        <?php esc_html_e('Aucune donnée encore — patientez quelques visiteurs.', 'lws-optimize'); ?>
                    </p>
                <?php else : ?>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Hit rate', 'lws-optimize'); ?></span><strong><?php echo esc_html($t24h['hit_rate']); ?>%</strong></div>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Hits', 'lws-optimize'); ?></span><strong style="color:#16a34a"><?php echo esc_html(number_format_i18n($t24h['hits'])); ?></strong></div>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Misses', 'lws-optimize'); ?></span><strong style="color:#94a3b8"><?php echo esc_html(number_format_i18n($t24h['misses'])); ?></strong></div>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Données servies', 'lws-optimize'); ?></span><strong><?php echo esc_html(self::format_bytes($t24h['bytes_saved'])); ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coverage + Memcached -->
        <div class="lwsop_dw_grid">
            <div class="lwsop_dw_card">
                <h4>🎯 <?php esc_html_e('Couverture cache', 'lws-optimize'); ?></h4>
                <div class="lwsop_dw_health_bar"><div style="width:<?php echo esc_attr($cov_pct); ?>%;background:linear-gradient(90deg,#3b82f6,#1e40af)"></div></div>
                <div style="font-size:11px;color:#475569">
                    <strong style="color:#1e40af"><?php echo esc_html($cov_pct); ?>%</strong>
                    · <?php echo esc_html($cov_d); ?>/<?php echo esc_html($cov_total); ?> <?php esc_html_e('desktop', 'lws-optimize'); ?>
                    · <?php echo esc_html($cov_m); ?>/<?php echo esc_html($cov_total); ?> <?php esc_html_e('mobile', 'lws-optimize'); ?>
                </div>
            </div>

            <div class="lwsop_dw_card">
                <h4>🧠 Memcached</h4>
                <?php if (!$memcached_stats['active']) : ?>
                    <p style="font-size:11px;color:#94a3b8;font-style:italic;margin:8px 0 0">
                        <?php esc_html_e('Inactif. Activez-le pour réduire les requêtes DB.', 'lws-optimize'); ?>
                    </p>
                <?php else : ?>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Mémoire', 'lws-optimize'); ?></span><strong><?php echo esc_html(self::format_bytes($memcached_stats['bytes'])); ?> / <?php echo esc_html(self::format_bytes($memcached_stats['limit_maxbytes'])); ?></strong></div>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Items', 'lws-optimize'); ?></span><strong><?php echo esc_html(number_format_i18n($memcached_stats['curr_items'])); ?></strong></div>
                    <div class="lwsop_dw_metric"><span><?php esc_html_e('Hit rate', 'lws-optimize'); ?></span><strong><?php echo esc_html($memcached_stats['hit_rate']); ?>%</strong></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sparkline 30j -->
        <div class="lwsop_dw_card" style="margin-bottom:6px">
            <h4>📈 <?php esc_html_e('Hits sur 30 jours', 'lws-optimize'); ?> · <?php echo esc_html(number_format_i18n($stats['totals_30d']['hits'])); ?></h4>
            <div class="lwsop_dw_sparkline"><?php echo self::sparkline_svg($stats['sparkline']); ?></div>
        </div>

        <div class="lwsop_dw_footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=lws-op-config')); ?>">
                <?php esc_html_e('→ Voir le tableau de bord complet', 'lws-optimize'); ?>
            </a>
            ·
            <a href="<?php echo esc_url(admin_url('admin.php?page=lws-op-config-advanced')); ?>">
                <?php esc_html_e('Mode avancé', 'lws-optimize'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Récupère les stats Memcached via l'extension PHP. Retourne ['active' => false]
     * si pas dispo ou pas connecté.
     * Public depuis 4.5.1 — réutilisé par main_page.php pour afficher le bloc Memcached.
     */
    public static function memcached_stats()
    {
        $cfg = get_option('lws_optimize_config_array', []);
        if (($cfg['memcached']['state'] ?? 'false') !== 'true' || !class_exists('Memcached')) {
            return ['active' => false];
        }
        try {
            $m = new \Memcached();
            if (empty($m->getServerList())) $m->addServer('localhost', 11211);
            $stats = $m->getStats();
            if (!is_array($stats) || empty($stats)) return ['active' => false];
            $node = reset($stats);
            $hits   = (int) ($node['get_hits']   ?? 0);
            $misses = (int) ($node['get_misses'] ?? 0);
            $total  = $hits + $misses;
            return [
                'active'         => true,
                'bytes'          => (int) ($node['bytes'] ?? 0),
                'limit_maxbytes' => (int) ($node['limit_maxbytes'] ?? 0),
                'curr_items'     => (int) ($node['curr_items'] ?? 0),
                'hit_rate'       => $total > 0 ? round(($hits / $total) * 100, 1) : 0,
            ];
        } catch (\Throwable $e) {
            return ['active' => false];
        }
    }

    /**
     * SVG sparkline minimal — sans lib externe. Trace une ligne avec area-fill
     * basée sur le tableau de valeurs (typiquement 30 jours de hits).
     */
    public static function sparkline_svg($values, $width = 280, $height = 36)
    {
        if (!is_array($values) || empty($values)) {
            return '<div style="font-size:11px;color:#94a3b8;font-style:italic;text-align:center;padding:8px">' . esc_html__('Pas encore de données', 'lws-optimize') . '</div>';
        }
        $max = max($values);
        if ($max <= 0) {
            return '<div style="font-size:11px;color:#94a3b8;font-style:italic;text-align:center;padding:8px">' . esc_html__('Aucun hit sur la période', 'lws-optimize') . '</div>';
        }
        $n = count($values);
        $step = $n > 1 ? $width / ($n - 1) : 0;
        $points = [];
        foreach ($values as $i => $v) {
            $x = round($i * $step, 2);
            $y = round($height - (($v / $max) * ($height - 4)) - 2, 2);
            $points[] = "$x,$y";
        }
        $line = implode(' ', $points);
        $area = "0,$height " . $line . " $width,$height";
        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">'
            . '<polygon points="' . esc_attr($area) . '" fill="rgba(59,130,246,0.15)" />'
            . '<polyline points="' . esc_attr($line) . '" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />'
            . '</svg>';
    }

    public static function format_bytes($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
