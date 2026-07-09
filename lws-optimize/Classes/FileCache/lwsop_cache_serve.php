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
 *
 * NOTE ON WORDPRESS CODING STANDARDS: this file runs before WordPress is
 * loaded (that's the whole point), so none of wp_unslash(), sanitize_*(),
 * esc_*(), wp_parse_url(), or WP_Filesystem are available here. The
 * superglobal reads below are instead validated the way this bootstrap-free
 * context actually can: every path built from $_SERVER is resolved with
 * realpath() and checked to still be inside $cache_root before the file is
 * read (see the containment check a few lines down), which is a stronger
 * guarantee against path traversal than string sanitization would be. The
 * one raw `echo $body;` serves the full pre-rendered cached HTML page (optionally
 * Brotli/gzip-compressed, see below) — that IS the response body, so escaping it
 * would corrupt every cached page.
 */
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.parse_url_parse_url,WordPress.Security.EscapeOutput.OutputNotEscaped -- see docblock above: no WP bootstrap in this file, so none of the suggested WP-only replacements are available

// ── Resolve filesystem roots ───────────────────────────────────────────────
// Walk upward until we find a directory that looks like wp-content (contains
// both 'plugins' and 'themes'). This works regardless of where this script
// is deployed (inside /plugins/, inside /cache/, etc.), so security plugins
// that block PHP execution under /plugins/ can't cause a 403 when the script
// is deployed to /cache/lwsoptimize/ instead.
$wp_content_dir = null;
$_dir = __DIR__;
for ($i = 0; $i < 10; $i++) {
    if (is_dir($_dir . '/plugins') && is_dir($_dir . '/themes')) {
        $wp_content_dir = $_dir;
        break;
    }
    $_parent = dirname($_dir);
    if ($_parent === $_dir) {
        break;
    }
    $_dir = $_parent;
}
unset($_dir, $_parent, $i);

if ($wp_content_dir === null) {
    http_response_code(500);
    exit;
}

$wp_install_dir  = dirname($wp_content_dir);
$cache_root      = $wp_content_dir . '/cache/lwsoptimize/';

// ── User type ─────────────────────────────────────────────────────────────
// CACHE-1: only anonymous responses are ever cached, so this intermediary only
// serves index_0. Logged-in visitors are handled dynamically by WordPress (the
// .htaccess logged-in rules only fire when a file exists, which never happens now).
$uid = 0;
foreach (array_keys($_COOKIE) as $cookie_name) {
    if (strpos($cookie_name, 'wordpress_logged_in_') === 0) {
        // Authenticated request — never serve a shared cache file.
        http_response_code(404);
        exit;
    }
}

// ── Determine if mobile from User-Agent ───────────────────────────────────
// CACHE-5: match the exact token set used by LwsOptimizeFileCache::_lwsop_is_mobile()
// (a copy of wp_is_mobile) so this intermediary looks in the same cache/ vs
// cache-mobile/ directory the writer used. A mismatch here caused cache misses.
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])) {
    $is_mobile = ('?1' === $_SERVER['HTTP_SEC_CH_UA_MOBILE']);
} else {
    $is_mobile = (bool) preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/', $ua);
}
$cache_type = $is_mobile ? 'cache-mobile' : 'cache';

// ── Build the URI path ─────────────────────────────────────────────────────
// CACHE-4: the writer and the .htaccess -f check both store/look up the cache file
// under the FULL request path (including any subdirectory install prefix, e.g.
// /blog/my-page/). Do NOT strip the prefix here — doing so made every cached page
// 404 through this intermediary on subdirectory installs.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
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

// ── Compression ──────────────────────────────────────────────────────────
// Serve a pre-compressed sibling (written by LwsOptimizeFileCache when the
// page was cached) when the client accepts it, generating one on the fly as
// a fallback for cache files written before this existed. This does not rely
// on mod_deflate/mod_brotli being loaded — those aren't guaranteed on every
// shared-hosting vhost.
$accept_encoding  = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
$body             = $content;
$content_encoding = null;

if (function_exists('brotli_compress') && strpos($accept_encoding, 'br') !== false) {
    $compressed = lwsop_serve_get_compressed($real_file, $content, '.br', 'brotli_compress');
    if ($compressed !== null) {
        $body             = $compressed;
        $content_encoding = 'br';
    }
}

if ($content_encoding === null && function_exists('gzencode') && strpos($accept_encoding, 'gzip') !== false) {
    $compressed = lwsop_serve_get_compressed($real_file, $content, '.gz', static function ($data) {
        return gzencode($data, 9);
    });
    if ($compressed !== null) {
        $body             = $compressed;
        $content_encoding = 'gzip';
    }
}

header('Content-Type: text/html; charset=UTF-8');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($real_file)) . ' GMT');
header('X-LWSOP-Cache: HIT');
header('Edge-Cache-Platform: lwsoptimize');
header('Vary: Accept-Encoding');
if ($content_encoding !== null) {
    header('Content-Encoding: ' . $content_encoding);
}

// ── Track hit in stats.json ────────────────────────────────────────────────
lwsop_serve_track_hit($cache_root . 'stats.json', strlen($content));

echo $body;
exit;

// ──────────────────────────────────────────────────────────────────────────
// Reads the `.br`/`.gz` sibling of $source_file when it's at least as fresh as
// the source, otherwise compresses on the fly and persists the result (so the
// next hit reads it straight from disk) — a fallback for cache files written
// before LwsOptimizeFileCache started pre-generating these siblings.
function lwsop_serve_get_compressed($source_file, $raw_content, $suffix, $compress)
{
    $compressed_file = $source_file . $suffix;
    $source_mtime    = @filemtime($source_file);
    $cached_mtime    = @filemtime($compressed_file);

    if ($cached_mtime !== false && $source_mtime !== false && $cached_mtime >= $source_mtime) {
        $cached = @file_get_contents($compressed_file);
        if ($cached !== false) {
            return $cached;
        }
    }

    $compressed = @call_user_func($compress, $raw_content);
    if ($compressed === false || $compressed === null) {
        return null;
    }

    $tmp = $compressed_file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $compressed) !== false) {
        @rename($tmp, $compressed_file);
    } else {
        @unlink($tmp);
    }

    return $compressed;
}

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
