<?php

namespace Lws\Classes\FileCache;

/**
 * 4.5.0 — Tracking des hits/misses du cache file-based + Memcached + bytes saved.
 *
 * Stockage : `wp-content/cache/lwsoptimize/stats.json` au format compact
 *   {
 *     "2026-06-01": {
 *       "hits": 1234,        // pages servies depuis le cache
 *       "misses": 56,        // pages générées (cache vide ou expiré)
 *       "bypass": 12,        // requêtes admin/POST/etc. non éligibles
 *       "bytes_saved": 89012345  // somme des tailles des HTML servis depuis le cache
 *     },
 *     "2026-06-02": { ... },
 *     ...
 *   }
 *
 * Rotation : on garde 30 jours max. Plus vieux = purgés à l'écriture.
 *
 * Performance : 1 fopen + flock + fwrite par hit/miss. Négligeable comparé à
 * la génération d'une page WP. Sur HIT (cas optimal), c'est juste après que la
 * page est déjà envoyée (echo + exit) — mais on hook *avant* l'exit pour ne pas
 * perdre le compteur.
 */
class LwsOptimizeUsageStats
{
    const STATS_FILE  = '/cache/lwsoptimize/stats.json';
    const KEEP_DAYS   = 30;

    /**
     * Track a hit/miss/bypass event. Optionally with byte count (HTML size served).
     */
    public static function track($type, $bytes = 0)
    {
        if (!in_array($type, ['hits', 'misses', 'bypass'], true)) {
            return;
        }
        $path = WP_CONTENT_DIR . self::STATS_FILE;

        // Crée le dossier si besoin
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $today = gmdate('Y-m-d');

        // Open with x+ creates the file if missing, else r+ opens existing
        $fp = @fopen($path, 'c+');
        if (!$fp) return;

        // Bloque les autres writes pendant qu'on update
        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return;
        }

        $raw  = stream_get_contents($fp);
        $data = $raw ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];

        // Rotation : supprime les entrées > KEEP_DAYS
        $cutoff = gmdate('Y-m-d', time() - self::KEEP_DAYS * 86400);
        foreach (array_keys($data) as $day) {
            if ($day < $cutoff) unset($data[$day]);
        }

        if (!isset($data[$today])) {
            $data[$today] = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0];
        }
        $data[$today][$type] = ($data[$today][$type] ?? 0) + 1;
        if ($bytes > 0 && $type === 'hits') {
            $data[$today]['bytes_saved'] = ($data[$today]['bytes_saved'] ?? 0) + (int) $bytes;
        }

        // Rewrite
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));

        @flock($fp, LOCK_UN);
        @fclose($fp);
    }

    /**
     * Lit le fichier stats.json et retourne le tableau de stats (max 30 jours).
     * Returns ['days' => [...], 'totals_24h' => [...], 'totals_7d' => [...], 'totals_30d' => [...]]
     */
    public static function read()
    {
        $path = WP_CONTENT_DIR . self::STATS_FILE;
        if (!file_exists($path)) {
            return self::empty_stats();
        }
        $raw = @file_get_contents($path);
        $data = $raw ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];

        // Calculs des totaux
        $today    = gmdate('Y-m-d');
        $cutoff7  = gmdate('Y-m-d', time() - 7 * 86400);
        $cutoff30 = gmdate('Y-m-d', time() - 30 * 86400);

        $totals_24h = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0];
        $totals_7d  = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0];
        $totals_30d = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0];

        foreach ($data as $day => $row) {
            foreach (['hits', 'misses', 'bypass', 'bytes_saved'] as $k) {
                if ($day === $today)  $totals_24h[$k] += (int) ($row[$k] ?? 0);
                if ($day >= $cutoff7) $totals_7d[$k]  += (int) ($row[$k] ?? 0);
                if ($day >= $cutoff30) $totals_30d[$k] += (int) ($row[$k] ?? 0);
            }
        }

        // Compute hit rates
        $totals_24h['hit_rate'] = self::hit_rate($totals_24h);
        $totals_7d['hit_rate']  = self::hit_rate($totals_7d);
        $totals_30d['hit_rate'] = self::hit_rate($totals_30d);

        // Sparkline 30j : array [hits_jour_J, ..., hits_aujourd_hui]
        $sparkline = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', time() - $i * 86400);
            $sparkline[] = (int) ($data[$day]['hits'] ?? 0);
        }

        return [
            'days'       => $data,
            'totals_24h' => $totals_24h,
            'totals_7d'  => $totals_7d,
            'totals_30d' => $totals_30d,
            'sparkline'  => $sparkline,
        ];
    }

    private static function empty_stats()
    {
        $empty = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0, 'hit_rate' => 0];
        return [
            'days'       => [],
            'totals_24h' => $empty,
            'totals_7d'  => $empty,
            'totals_30d' => $empty,
            'sparkline'  => array_fill(0, 30, 0),
        ];
    }

    private static function hit_rate($row)
    {
        $total = (int) ($row['hits'] ?? 0) + (int) ($row['misses'] ?? 0);
        if ($total <= 0) return 0;
        return round(($row['hits'] / $total) * 100, 1);
    }
}
