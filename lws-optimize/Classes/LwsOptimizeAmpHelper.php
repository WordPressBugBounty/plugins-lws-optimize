<?php

namespace Lws\Classes;

/**
 * Shared AMP request detection.
 *
 * AMP pages forbid custom author JavaScript and enforce strict markup rules,
 * so every front-end optimizer that rewrites HTML (Delay/Defer JS, LazyLoad,
 * Critical CSS, HTML minification, ...) must skip AMP requests entirely —
 * the page is still cached, just raw as the AMP plugin rendered it.
 *
 * amp_is_request() is only reliable once the main query is set up, but the
 * file cache's output buffer callback runs at shutdown, after the AMP plugin
 * may have torn down its state. The result is therefore captured once at the
 * 'wp' hook and served from there afterwards.
 */
class LwsOptimizeAmpHelper
{
    /** @var bool|null Result captured at the 'wp' hook; null until then. */
    private static $captured = null;

    /**
     * Hooked at 'wp' (PHP_INT_MAX) so the AMP plugin has finished deciding
     * whether this request is an AMP one.
     */
    public static function capture()
    {
        self::$captured = self::detect();
    }

    public static function is_amp_request(): bool
    {
        if (self::$captured !== null) {
            return self::$captured;
        }
        if (!did_action('wp')) {
            // Too early to know; callers before 'wp' must treat as non-AMP.
            return false;
        }
        return self::detect();
    }

    private static function detect(): bool
    {
        // Official AMP plugin (current, then legacy name), then "AMP for WP".
        if (function_exists('amp_is_request')) {
            return amp_is_request();
        }
        if (function_exists('is_amp_endpoint')) {
            return is_amp_endpoint();
        }
        return function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint();
    }
}
