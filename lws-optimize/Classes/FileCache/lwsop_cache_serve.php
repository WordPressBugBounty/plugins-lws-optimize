<?php
/**
 * Lightweight cache delivery intermediary — no WordPress bootstrap.
 *
 * Called by Apache mod_rewrite when the "PHP stats intermediary" option is
 * enabled. Apache has already verified that a cache file exists (via the -f
 * RewriteCond), so this script reconstructs the same path, serves the file,
 * and records a hit in stats.json using the same format as
 * LwsOptimizeUsageStats — all without loading WordPress.
 *
 * Intentionally avoids any dependency on Apache [E=] / SetEnv env vars so
 * it works correctly with both mod_php and PHP-FPM deployments.
 */

// ── Resolve filesystem roots ───────────────────────────────────────────────
// This file: wp-content/plugins/lws-optimize/Classes/FileCache/lwsop_cache_serve.php
// Depth:      wp-content / plugins / lws-optimize / Classes / FileCache / <file>
// 5 dirname() calls climb to wp-content/.
$wp_content_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
$wp_install_dir  = dirname($wp_content_dir);
$cache_root      = $wp_content_dir . '/cache/lwsoptimize/';

// ── Determine install base path for subdirectory installs ─────────────────
// e.g. DOCUMENT_ROOT=/var/www/html, wp installed in /var/www/html/blog →
// $install_base = '/blog'
$doc_root     = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$install_base = '';
if ($doc_root !== '' && strpos($wp_install_dir, $doc_root) === 0) {
    $install_base = substr($wp_install_dir, strlen($doc_root)); // e.g. '' or '/blog'
}

// ── Determine user type from cookies (0 = anonymous, 2 = logged-in) ───────
$uid = 0;
foreach (array_keys($_COOKIE) as $cookie_name) {
    if (strpos($cookie_name, 'wordpress_logged_in_') === 0) {
        $uid = 2;
        break;
    }
}

// ── Determine if mobile from User-Agent ───────────────────────────────────
$is_mobile  = (bool) preg_match('/Mobile|Android|iPhone|iPad|CriOS|Opera\s*Mini|IEMobile/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
$cache_type = $is_mobile ? 'cache-mobile' : 'cache';

// ── Build the URI path relative to the WP install ─────────────────────────
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Strip subdirectory prefix so /blog/my-page/ → /my-page/
if ($install_base !== '' && strpos($uri, $install_base) === 0) {
    $uri = substr($uri, strlen($install_base));
}
$uri = rtrim($uri ?: '/', '/') . '/';

// ── Try candidate cache file locations ────────────────────────────────────
// Attempt 1: standard install  → cache/{uri}/index_{uid}.html
// Attempt 2: multisite/Polylang → {host}/cache/{uri}/index_{uid}.html
$host       = $_SERVER['HTTP_HOST'] ?? '';
$candidates = [
    $cache_root . $cache_type . $uri . "index_{$uid}.html",
    $cache_root . $host . '/' . $cache_type . $uri . "index_{$uid}.html",
];

$real_file       = null;
$real_cache_root = realpath($cache_root);

foreach ($candidates as $candidate) {
    $resolved = realpath($candidate);
    if ($resolved && $real_cache_root && strpos($resolved, $real_cache_root) === 0) {
        $real_file = $resolved;
        break;
    }
}

if (!$real_file) {
    http_response_code(404);
    exit;
}

// ── Read and serve ─────────────────────────────────────────────────────────
$content = @file_get_contents($real_file);
if ($content === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($real_file)) . ' GMT');
header('X-LWSOP-Cache: HIT');
header('Edge-Cache-Platform: lwsoptimize');

// ── Track hit in stats.json ────────────────────────────────────────────────
lwsop_serve_track_hit($cache_root . 'stats.json', strlen($content));

echo $content;
exit;

// ──────────────────────────────────────────────────────────────────────────
function lwsop_serve_track_hit($stats_file, $bytes)
{
    $dir = dirname($stats_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $today = gmdate('Y-m-d');
    $fp    = @fopen($stats_file, 'c+');
    if (!$fp) {
        return;
    }
    if (!@flock($fp, LOCK_EX)) {
        @fclose($fp);
        return;
    }

    $raw  = stream_get_contents($fp);
    $data = ($raw && ($d = json_decode($raw, true)) && is_array($d)) ? $d : [];

    // Rotation: drop entries older than 30 days.
    $cutoff = gmdate('Y-m-d', time() - 30 * 86400);
    foreach (array_keys($data) as $day) {
        if ($day < $cutoff) {
            unset($data[$day]);
        }
    }

    if (!isset($data[$today])) {
        $data[$today] = ['hits' => 0, 'misses' => 0, 'bypass' => 0, 'bytes_saved' => 0];
    }
    $data[$today]['hits']        = ($data[$today]['hits']        ?? 0) + 1;
    $data[$today]['bytes_saved'] = ($data[$today]['bytes_saved'] ?? 0) + $bytes;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));

    @flock($fp, LOCK_UN);
    @fclose($fp);
}
