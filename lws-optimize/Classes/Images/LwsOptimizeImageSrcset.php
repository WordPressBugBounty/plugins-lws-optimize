<?php

namespace Lws\Classes\Images;

/**
 * Rewrites the WordPress-generated <img srcset="…"> so each candidate URL points
 * to the optimised WebP/AVIF variant (suffix `_lwsoptimized.{ext}`) when one is
 * available on disk.
 *
 * Why:
 * - WP's `wp_calculate_image_srcset()` returns the JPEG/PNG candidates only.
 * - The plugin already generates a single WebP/AVIF per attachment, but the
 *   intermediate WP sizes (e.g. -300x200, -768x512) keep their original format,
 *   defeating the purpose of WebP on responsive images.
 *
 * What this class does:
 * - Hooks into `wp_calculate_image_srcset` (priority 99, after WP core has built
 *   the srcset array) and replaces each candidate URL by its `_lwsoptimized`
 *   sibling when that file exists on disk.
 * - Also rewrites the bare `src` via `wp_get_attachment_image_attributes`.
 * - Picks AVIF over WebP when both exist (smaller).
 *
 * Compatibility: if the optimised sibling does not exist, the original URL is
 * kept — so this is strictly additive and safe.
 */
class LwsOptimizeImageSrcset
{
    /**
     * Cache of file_exists() probes within a single request, keyed by absolute path.
     * @var array<string,bool>
     */
    private static $exists_cache = [];

    public static function startActions()
    {
        add_filter('wp_calculate_image_srcset', [__CLASS__, 'rewrite_srcset'], 99, 5);
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'rewrite_attributes'], 99, 3);
    }

    /**
     * @param array  $sources    map of width => ['url'=>…, 'descriptor'=>…, 'value'=>…]
     * @param array  $size_array
     * @param string $image_src
     * @param array  $image_meta
     * @param int    $attachment_id
     * @return array
     */
    public static function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!is_array($sources) || empty($sources)) {
            return $sources;
        }
        foreach ($sources as $width => $source) {
            if (!isset($source['url'])) {
                continue;
            }
            $rewritten = self::map_url_to_optimised($source['url']);
            if ($rewritten !== null) {
                $sources[$width]['url'] = $rewritten;
            }
        }
        return $sources;
    }

    /**
     * Rewrite the bare `src` and `srcset` attributes returned for <img> tags.
     */
    public static function rewrite_attributes($attr, $attachment, $size)
    {
        if (!empty($attr['src'])) {
            $rewritten = self::map_url_to_optimised($attr['src']);
            if ($rewritten !== null) {
                $attr['src'] = $rewritten;
            }
        }
        // srcset is a comma-separated list "URL 300w, URL 600w, …"
        if (!empty($attr['srcset'])) {
            $attr['srcset'] = self::rewrite_srcset_string($attr['srcset']);
        }
        return $attr;
    }

    private static function rewrite_srcset_string($srcset)
    {
        $parts = preg_split('/\s*,\s*/', $srcset);
        if (!$parts) {
            return $srcset;
        }
        foreach ($parts as $i => $part) {
            $tokens = preg_split('/\s+/', trim($part), 2);
            if (empty($tokens[0])) {
                continue;
            }
            $rewritten = self::map_url_to_optimised($tokens[0]);
            if ($rewritten !== null) {
                $parts[$i] = $rewritten . (isset($tokens[1]) ? ' ' . $tokens[1] : '');
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Given an image URL, returns the URL of its `_lwsoptimized.avif` sibling
     * (preferred) or `_lwsoptimized.webp` sibling, if either exists on disk.
     * Returns null when no optimised file is available.
     */
    private static function map_url_to_optimised($url)
    {
        // Only rewrite local uploads URLs
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl']) || strpos($url, $uploads['baseurl']) !== 0) {
            return null;
        }
        // Already in webp/avif? Don't touch.
        $lc = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.(webp|avif)(\?.*)?$/i', $lc)) {
            return null;
        }
        // Strip query string for path computation, keep it for the final URL
        $clean_url = preg_replace('/\?.*$/', '', $url);
        $path_in_uploads = substr($clean_url, strlen(rtrim($uploads['baseurl'], '/')));
        $abs_path = rtrim($uploads['basedir'], '/') . $path_in_uploads;

        $info = pathinfo($abs_path);
        if (empty($info['filename']) || empty($info['extension'])) {
            return null;
        }
        // Try AVIF first (smaller), then WebP
        foreach (['avif', 'webp'] as $ext) {
            $candidate_abs = $info['dirname'] . '/' . $info['filename'] . '_lwsoptimized.' . $ext;
            if (self::exists($candidate_abs)) {
                $rel = dirname($path_in_uploads) . '/' . $info['filename'] . '_lwsoptimized.' . $ext;
                return rtrim($uploads['baseurl'], '/') . str_replace('//', '/', '/' . ltrim($rel, '/'));
            }
        }
        return null;
    }

    private static function exists($abs_path)
    {
        if (!isset(self::$exists_cache[$abs_path])) {
            self::$exists_cache[$abs_path] = is_file($abs_path);
        }
        return self::$exists_cache[$abs_path];
    }
}
