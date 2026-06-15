<?php

namespace Lws\Classes\Front;

/**
 * 4.4.3 — Google Fonts preload / preconnect.
 *
 * Détecte automatiquement les feuilles de style enqueued pointant vers
 * fonts.googleapis.com ou fonts.gstatic.com et injecte les hints DNS/TCP
 * (preconnect) en tout début de <head>. Gain LCP typique : -100 à -300 ms
 * sur les pages chargées en texte.
 *
 * Pourquoi preconnect plutôt que preload :
 * - preload nécessite l'URL exacte du .woff2 (ex: /s/lato/v14/S6u9w4BMUTPHh50XSwiPGQ.woff2)
 *   qui change à chaque version Google Fonts → fragile, casse régulièrement.
 * - preconnect ouvre la connexion TCP+TLS vers fonts.gstatic.com en parallèle
 *   du parsing HTML → -200ms typique sans risque de bad-URL preload.
 *
 * Pas de gain si pas de Google Fonts → on n'injecte rien (auto-détection).
 */
class LwsOptimizeFontPreload
{
    public static function startActions()
    {
        // Priorité 1 pour passer AVANT le wp_resource_hints natif (prio 2).
        add_action('wp_head', [__CLASS__, 'inject_preconnect'], 1);
    }

    public static function inject_preconnect()
    {
        if (is_admin() || is_feed()) {
            return;
        }
        global $wp_styles;
        if (!$wp_styles instanceof \WP_Styles) {
            return;
        }

        $needs_googleapis = false;
        $needs_gstatic    = false;
        foreach ($wp_styles->registered as $handle => $style) {
            $src = (string) ($style->src ?? '');
            if ($src === '') continue;
            if (stripos($src, 'fonts.googleapis.com') !== false) $needs_googleapis = true;
            if (stripos($src, 'fonts.gstatic.com')    !== false) $needs_gstatic    = true;
            if ($needs_googleapis && $needs_gstatic) break;
        }

        // gstatic est presque toujours utilisé dès qu'on a googleapis (les .woff2
        // sont sur gstatic). On le préconnecte aussi s'il y a au moins un Google
        // Font CSS enqueued — c'est là que se trouve le vrai gain LCP.
        if ($needs_googleapis || $needs_gstatic) {
            echo "\n<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">";
            echo "\n<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
        }
    }
}
