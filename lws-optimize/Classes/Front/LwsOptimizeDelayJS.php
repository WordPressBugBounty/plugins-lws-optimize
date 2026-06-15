<?php

namespace Lws\Classes\Front;

/**
 * Delay JavaScript execution until the first user interaction (mousemove, touchstart,
 * scroll, keydown, click). Inspired by WP Rocket / FlyingPress « Delay JavaScript ».
 *
 * Why: many third-party scripts (analytics, chat widgets, ads, embeds) contribute
 * heavily to LCP and INP but are not needed before the user actually engages with
 * the page. Delaying their execution buys a near-instant first paint.
 *
 * How: every <script src> tag (except those matching exclusions) is rewritten with
 * type="lwsop/delay-script" so the browser won't execute it. A tiny inline loader
 * registers listeners for user-interaction events; on the first one, it walks the
 * marked scripts, swaps their type back to text/javascript, and replays them in
 * order. The loader weighs ~1.1 KB minified, has no external dependency.
 *
 * Exclusions API:
 *   $optimize_options['delay_js']['state']      = "true"|"false"
 *   $optimize_options['delay_js']['exclusions'] = ['jquery-core', 'recaptcha', ...]
 * Defaults exclude jQuery and known critical libraries.
 */
class LwsOptimizeDelayJS
{
    /**
     * Default patterns excluded from delay. Any <script> whose src OR id contains
     * one of these is left untouched. Filterable via `lwsop_delay_js_exclusions`.
     */
    const DEFAULT_EXCLUSIONS = [
        'jquery-core',
        'jquery.min.js',
        'jquery-migrate',
        '/jquery-3',
        '/jquery-2',
        '/jquery-1',
        'wp-includes/js/jquery',
        'lws-optimize',         // do not delay our own scripts
        'lws_op_',
        '/wp-emoji',
        'wp-polyfill',
        'recaptcha',            // form CAPTCHAs need to render up-front
        'turnstile',
    ];

    public static function startActions()
    {
        add_action('template_redirect', [__CLASS__, 'start_buffer'], 1);
    }

    public static function start_buffer()
    {
        if (is_admin() || is_feed() || is_preview()) {
            return;
        }
        // Skip on page builders / customizers
        $skip_keys = ['elementor-preview', 'et_fb', 'fl_builder', 'vcv-action', 'vc_action', 'vc_editable'];
        foreach ($skip_keys as $k) {
            if (isset($_GET[$k])) {
                return;
            }
        }
        ob_start([__CLASS__, 'process_html']);
    }

    /**
     * Main HTML rewriter. Marks delayable <script> tags and appends the loader.
     */
    public static function process_html($html)
    {
        if (empty($html) || stripos($html, '<script') === false) {
            return $html;
        }

        $opts = get_option('lws_optimize_config_array', []);
        $user_exclusions = $opts['delay_js']['exclusions'] ?? [];
        $exclusions = array_merge(self::DEFAULT_EXCLUSIONS, (array) $user_exclusions);
        $exclusions = apply_filters('lwsop_delay_js_exclusions', $exclusions);

        $count = 0;
        $html = preg_replace_callback(
            '#<script\b([^>]*)>(.*?)</script>#is',
            function ($m) use ($exclusions, &$count) {
                $attrs = $m[1];
                $body  = $m[2];

                // Skip if already marked
                if (stripos($attrs, 'lwsop/delay-script') !== false) {
                    return $m[0];
                }
                // Skip non-JavaScript types (module, JSON-LD, etc.) — module needs a different swap path
                if (preg_match('/\btype\s*=\s*["\']([^"\']+)["\']/i', $attrs, $tm)) {
                    $type = strtolower($tm[1]);
                    if ($type !== 'text/javascript' && $type !== 'application/javascript' && $type !== '') {
                        return $m[0];
                    }
                }
                // Build search haystack: src + id + body sample
                $haystack = $attrs . ' ' . substr($body, 0, 200);
                foreach ($exclusions as $needle) {
                    if ($needle !== '' && stripos($haystack, $needle) !== false) {
                        return $m[0];
                    }
                }
                $count++;
                // Replace type attribute or inject it
                if (preg_match('/\btype\s*=\s*["\'][^"\']*["\']/i', $attrs)) {
                    $new_attrs = preg_replace('/\btype\s*=\s*["\'][^"\']*["\']/i', 'type="lwsop/delay-script"', $attrs);
                } else {
                    $new_attrs = ' type="lwsop/delay-script"' . $attrs;
                }
                return '<script' . $new_attrs . '>' . $body . '</script>';
            },
            $html
        );

        if ($count === 0) {
            return $html;
        }

        // Inject the loader just before </body> (or append if absent)
        $loader = self::get_loader_script();
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('#</body>#i', $loader . "\n</body>", $html, 1);
        } else {
            $html .= $loader;
        }
        return $html;
    }

    /**
     * Returns the minified inline loader. Listens for the first user-interaction
     * event, then resumes execution of every delayed <script>.
     *
     * Scripts with src are re-created sequentially and chained on `onload` so the
     * original execution order is preserved (important for jQuery plugins or
     * scripts that depend on each other). Inline scripts are eval'd via Function
     * constructor to keep the strict-mode boundary that <script> uses.
     */
    private static function get_loader_script()
    {
        $js = <<<'JS'
(function(){
  var fired=false,events=['mousemove','touchstart','scroll','keydown','click','wheel'];
  function go(){
    if(fired)return;fired=true;
    events.forEach(function(e){window.removeEventListener(e,go,{passive:true});});
    var nodes=document.querySelectorAll('script[type="lwsop/delay-script"]');
    var i=0;
    function next(){
      if(i>=nodes.length)return;
      var node=nodes[i++];
      var src=node.getAttribute('src');
      if(src){
        var s=document.createElement('script');
        for(var a=0;a<node.attributes.length;a++){
          var at=node.attributes[a];
          if(at.name==='type')continue;
          s.setAttribute(at.name,at.value);
        }
        s.src=src;
        s.onload=s.onerror=next;
        node.parentNode.insertBefore(s,node);
        node.parentNode.removeChild(node);
      }else{
        try{(new Function(node.textContent))();}catch(e){console.error('[lwsop delay]',e);}
        next();
      }
    }
    next();
    // Fire DOMContentLoaded-style hook so jQuery's ready() callbacks resume
    if(window.jQuery&&typeof window.jQuery.ready==='function'){
      try{window.jQuery.ready();}catch(e){}
    }
  }
  events.forEach(function(e){window.addEventListener(e,go,{passive:true,once:true});});
  // Fail-safe: trigger after 10s of inactivity to avoid stuck pages
  setTimeout(go,10000);
})();
JS;
        return '<script id="lwsop-delay-loader">' . $js . '</script>';
    }
}
