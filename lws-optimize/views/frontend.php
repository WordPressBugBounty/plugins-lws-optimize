<?php

// Prepare all data for the "Front-End" tab
$first_bloc_array = array(
    'minify_css' => array(
        'title' => __('Minify CSS files', 'lws-optimize'),
        'desc' => __('Compress your CSS files to reduce their size and accelerate their loading.', 'lws-optimize'),
        'recommended' => true,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_minify_css_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_minify_css_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1873"
    ),
    'combine_css' => array(
        'title' => __('Combine CSS files', 'lws-optimize'),
        'desc' => __('Fuse multiple CSS files into one to reduce server requests. <br> If you notice any display problem on your website, such as missing CSS or messed-up elements, deactivating this option or excluding the problematic files may solve the issue.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_combine_css_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_combine_css_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1882"
    ),
    'preload_css' => array(
        'title' => __('Preload CSS files', 'lws-optimize'),
        'desc' => __('Preload CSS files to accelerate page rendering.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => true,
        's_button_title' => __('Add files', 'lws-optimize'),
        's_button_id' => "lws_op_add_to_preload_files",
        'exclusion_id' => "lws_op_add_to_preload_files",
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_preload_css_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1883"
    ),
    'remove_css' => array(
        'title' => __('Remove unused CSS', 'lws-optimize'),
        'desc' => __('Remove all the CSS not used in the page to reduce file sizes and improve loading speeds. <br> Accessing the page for the first time will result in a longer loading time as the CSS is analysed. Preloading is recommended.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'exclusion_id' => "lws_optimize_remove_css_exclusion",
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_remove_css_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/"
    ),
    'critical_css' => array(
        'title' => __('Critical CSS', 'lws-optimize'),
        'desc' => __('Load only the CSS necessary for above-the-fold content in order to render content to the user as fast as possible. All the CSS not needed when rendering the page (CSS for content at the bottom of the page, for example) will be removed and only loaded once the page is ready. <br> Accessing the page for the first time will result in a longer loading time as the CSS is generated. Preloading is recommended.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_critical_css_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/"
    )
);

$second_bloc_array = array(
    'minify_js' => array(
        'title' => __('Minify JS files', 'lws-optimize'),
        'desc' => __('Reduce JS files size to boost loading performances.', 'lws-optimize'),
        'recommended' => true,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_minify_js_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_minify_js_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1873"
    ),
    'combine_js' => array(
        'title' => __('Combine JS files', 'lws-optimize'),
        'desc' => __('Fuse multiple JS files into one to reduce server requests. <br> This may cause issues when paired with some plugins or themes. Deactivating the option or excluding problematic files may fix the issue.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_combine_js_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_combine_js_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1882"
    ),
    'defer_js' => array(
        'title' => __('Defer JS files', 'lws-optimize'),
        'desc' => __('Delay JavaScript execution until after the page has loaded, improving initial page rendering speed.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_defer_js_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_defer_js_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/"
    ),
    'delay_js' => array(
        'title' => __('Delay JS files', 'lws-optimize'),
        'desc' => __('Delay JavaScript execution until any actions, such as moving the cursor, typing on the keyboard or scrolling the page is done. <br> It may however provoke Javascript errors with some themes and plugins', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_delay_js_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_delay_js_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/"
    ),
    // 'preload_js' => array(
    //     'title' => __('Report loading JavaScript blocking rendering', 'lws-optimize'),
    //     'desc' => __('Delay JS files loading blocking rendering for a faster initial loading.', 'lws-optimize'),
    //     'recommended' => false,
    //     'has_exclusion' => true,
    //     'exclusion' => "X",
    //     'has_exclusion_button' => true,
    //     'exclusion_id' => "lws_optimize_preload_js_exclusion",
    //     'has_special_button' => false,
    //     'has_checkbox' => true,
    //     'checkbox_id' => "lws_optimize_preload_js_check",
    // )
);

$third_bloc_array = array(
    'minify_html' => array(
        'title' => __('HTML Minification', 'lws-optimize'),
        'desc' => __('Reduce your HTML file size by deleting useless characters. <br> It may cause rendering issues with some themes and extensions.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => true,
        'exclusion' => "X",
        'has_exclusion_button' => true,
        'exclusion_id' => "lws_optimize_minify_html_exclusion",
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_minify_html_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1873"
    ),
    'preload_font' => array(
        'title' => __('Webfont preloading (manual)', 'lws-optimize'),
        'desc' => __('Manually preload font files (.woff/.woff2) you upload below to improve rendering speed.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => true,
        's_button_title' => __('Add files', 'lws-optimize'),
        's_button_id' => "lws_op_add_to_preload_font",
        'exclusion_id' => "lws_op_add_to_preload_font",
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_preload_font_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1883"
    ),
    // 4.4.3 — Auto-détection Google Fonts, complémentaire au manuel ci-dessus.
    // Détecte fonts.googleapis.com / gstatic.com dans les wp_styles enqueued et
    // injecte preconnect en haut de <head> → -100 à -300ms LCP sur pages text-heavy.
    'font_preload' => array(
        'title' => __('Google Fonts auto-detect (preconnect)', 'lws-optimize'),
        'desc' => __('Detects Google Fonts loaded via wp_enqueue_style and adds preconnect hints to fonts.googleapis.com / fonts.gstatic.com. Different from the manual option above — this one is automatic and only fires if Google Fonts are detected.', 'lws-optimize'),
        'recommended' => true,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_font_preload_check",
        'has_tooltip' => false,
    ),
    // 4.4.3 — RUM monitoring (Core Web Vitals anonymes). Conforme RGPD, 0 PII,
    // 0 cookie, rate-limited 60r/min/IP. Dashboard sous lws-op-rum.
    'rum' => array(
        'title' => __('Real User Monitoring (RUM)', 'lws-optimize'),
        'desc' => __('Anonymously collect real visitor load times to identify slow pages. No IP, no cookie, GDPR-compliant. View the dashboard in "LWS Optimize → RUM" once enabled.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_rum_check",
        'has_tooltip' => false,
    ),
    'deactivate_emoji' => array(
        'title' => __('Deactivate WordPress Emojis', 'lws-optimize'),
        'desc' => __('Deactivate the WordPress automatic emoji functionnality.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_deactivate_emoji_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1885"
    ),
    'eliminate_requests' => array(
        'title' => __('Remove query strings from static resources', 'lws-optimize'),
        'desc' => __('Remove query strings (?ver=) from your static resources, improving caching of those resources.', 'lws-optimize'),
        'recommended' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_eliminate_requests_check",
    ),
);
//

// Dynamic data added
$deactivated_filebased = false;
if ((isset($config_array['filebased_cache']['state']) && $config_array['filebased_cache']['state'] == "false") || !isset($config_array['filebased_cache']['state'])) {
    $deactivated_filebased = true;
}

$cloudflare_state = isset($config_array['cloudflare']['state']) && $config_array['cloudflare']['state'] == "true" ? true : false;

foreach ($first_bloc_array as $key => $array) {
    if ($deactivated_filebased) {
        $first_bloc_array[$key]['has_checkbox'] = false;
        $first_bloc_array[$key]['has_exclusion_button'] = false;
        $first_bloc_array[$key]['has_special_button'] = false;
    }
    $first_bloc_array[$key]['has_exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? true : false;
    $first_bloc_array[$key]['exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? $config_array[$key]['exclusions'] : "X";
    $first_bloc_array[$key]['state'] = isset($config_array[$key]['state']) && $config_array[$key]['state'] == "true" ? true : false;
    $first_bloc_array[$key]['s_infobubble_value'] = isset($config_array[$key]['special']) && count($config_array[$key]['special']) > 0 ? $config_array[$key]['special'] : "X";

    if ($key == "preload_css") {
        $first_bloc_array[$key]['has_exclusion'] = isset($config_array[$key]['links']) && count($config_array[$key]['links']) > 0 ? true : false;
        $first_bloc_array[$key]['exclusion'] = isset($config_array[$key]['links']) && count($config_array[$key]['links']) > 0 ? $config_array[$key]['links'] : "X";
    }
}

foreach ($second_bloc_array as $key => $array) {
    if ($deactivated_filebased) {
        $second_bloc_array[$key]['has_checkbox'] = false;
        $second_bloc_array[$key]['has_exclusion_button'] = false;
        $second_bloc_array[$key]['has_special_button'] = false;
    }
    $second_bloc_array[$key]['has_exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? true : false;
    $second_bloc_array[$key]['exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? $config_array[$key]['exclusions'] : "X";
    $second_bloc_array[$key]['state'] = isset($config_array[$key]['state']) && $config_array[$key]['state'] == "true" ? true : false;
    $second_bloc_array[$key]['s_infobubble_value'] = isset($config_array[$key]['special']) && count($config_array[$key]['special']) > 0 ? $config_array[$key]['special'] : "X";
}
foreach ($third_bloc_array as $key => $array) {
    if ($deactivated_filebased) {
        $third_bloc_array[$key]['has_checkbox'] = false;
        $third_bloc_array[$key]['has_exclusion_button'] = false;
        $third_bloc_array[$key]['has_special_button'] = false;
    }
    $third_bloc_array[$key]['has_exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? true : false;
    $third_bloc_array[$key]['exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? $config_array[$key]['exclusions'] : "X";
    $third_bloc_array[$key]['state'] = isset($config_array[$key]['state']) && $config_array[$key]['state'] == "true" ? true : false;
    $third_bloc_array[$key]['s_infobubble_value'] = isset($config_array[$key]['special']) && count($config_array[$key]['special']) > 0 ? $config_array[$key]['special'] : "X";

    if ($key == "webfont_optimize") {
        $third_bloc_array[$key]['has_exclusion'] = isset($config_array[$key]['links']) && count($config_array[$key]['links']) > 0 ? true : false;
        $third_bloc_array[$key]['exclusion'] = isset($config_array[$key]['links']) && count($config_array[$key]['links']) > 0 ? $config_array[$key]['links'] : "X";
    }
}
//
?>
<?php if ($deactivated_filebased) : ?>
    <div class="lwsop_frontend_cache_block"></div>
<?php endif ?>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('CSS Files', 'lws-optimize'); ?></h2>
</div>

<?php foreach ($first_bloc_array as $name => $data) : ?>
    <div class="lwsop_contentblock">
        <div class="lwsop_contentblock_leftside">
            <h2 class="lwsop_contentblock_title">
                <?php echo esc_html($data['title']); ?>
                <?php if ($data['recommended']) : ?>
                    <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                <?php endif ?>
                <?php if (isset($data['has_tooltip'])) : ?>
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo wp_kses($data['desc'], ['br' => []]); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <?php if ($data['has_exclusion']) : ?>
                <?php if ($name == "preload_css") : ?>
                    <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                        <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('files', 'lws-optimize'); ?></span>
                    </div>
                <?php else : ?>
                    <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                        <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                    </div>
                <?php endif ?>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                                    echo esc_html("disabled");
                                                                                                                                                                                                                                } ?>>
                    <span>
                        <?php esc_html_e('Exclude files', 'lws-optimize'); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_special_button']) : ?>
                <?php if ($data['s_infobubble_value'] != "X") : ?>
                    <div name="exclusion_bubble" class="lwsop_exclusion_infobubble"><?php echo esc_html($data['s_infobubble_value']); ?><?php echo esc_html($data['s_button_infobubble']); ?></div>
                <?php endif ?>
                <button type="button" class="lwsop_darkblue_button" id="<?php echo esc_html($data['s_button_id']); ?>" name="<?php echo esc_html($data['s_button_id']); ?>">
                    <span>
                        <?php echo esc_html($data['s_button_title']); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_checkbox']) : ?>
                <label class="lwsop_checkbox" for="<?php echo esc_html($data['checkbox_id']); ?>">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                            echo esc_html("disabled");
                                                                                                                                                                                                                        } ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>

    <?php
    if ($name === 'critical_css') :
        $is_fr_ccss  = substr(get_locale(), 0, 2) === 'fr';
        $ccss_mode   = $config_array['critical_css']['mode'] ?? 'off';
        $ccss_manual = $config_array['critical_css']['manual_css'] ?? '';
        $ccss_state  = ($config_array['critical_css']['state'] ?? 'false') === 'true';
        $ccss_badges = [
            'off'      => ['lbl' => $is_fr_ccss ? 'Désactivé' : 'Disabled', 'bg' => '#FFE5E5', 'fg' => '#DB3D3D'],
            'manual'   => ['lbl' => $is_fr_ccss ? 'Manuel'    : 'Manual',   'bg' => '#FFEDE1', 'fg' => '#FF6600'],
            'auto'     => ['lbl' => $is_fr_ccss ? 'Auto'      : 'Auto',     'bg' => '#DDF9EA', 'fg' => '#008A56'],
            'external' => ['lbl' => $is_fr_ccss ? 'Externe'   : 'External', 'bg' => '#DDF9EA', 'fg' => '#008A56'],
        ];
        $badge = $ccss_badges[$ccss_mode] ?? ['lbl' => $ccss_mode, 'bg' => '#ECF5FE', 'fg' => '#1C469D'];
    ?>
    <style>
        #lwsop_ccss_config_inline {
            display: none;
            padding: 18px 30px 20px;
            background: #ffffff;
            border-bottom: 1px solid #d9dbdb;
            border-left: 4px solid #1C469D;
        }
        #lwsop_ccss_config_inline.open { display: block; }
        #lwsop_ccss_config_inline .lwsop_ccss_title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 14px;
            font-family: Poppins, sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #1C469D;
        }
        #lwsop_ccss_config_inline .lwsop_ccss_badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        #lwsop_ccss_config_inline .lwsop_ccss_mode_row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        #lwsop_ccss_config_inline .lwsop_ccss_mode_row label {
            font-family: Poppins, sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #1D2327;
            white-space: nowrap;
        }
        #lwsop_ccss_mode {
            font-family: Poppins, sans-serif;
            font-size: 13px;
            padding: 6px 10px;
            border: 1px solid #d9dbdb;
            border-radius: 5px;
            background: #fff;
            color: #1D2327;
            min-width: 320px;
        }
        #lwsop_ccss_manual_wrap { margin-top: 4px; }
        #lwsop_ccss_manual {
            width: 100%;
            min-height: 100px;
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            border: 1px solid #d9dbdb;
            border-radius: 5px;
            box-sizing: border-box;
            background: #fff;
            resize: vertical;
            color: #1D2327;
        }
    </style>
    <div id="lwsop_ccss_config_inline"<?php echo $ccss_state ? ' class="open"' : ''; ?>>
        <p class="lwsop_ccss_title">
            <?php echo esc_html($is_fr_ccss ? 'Configuration du CSS critique' : 'Critical CSS configuration'); ?>
            <span class="lwsop_ccss_badge" id="lwsop_ccss_mode_badge" style="background:<?php echo esc_attr($badge['bg']); ?>;color:<?php echo esc_attr($badge['fg']); ?>"><?php echo esc_html($badge['lbl']); ?></span>
        </p>
        <div class="lwsop_ccss_mode_row">
            <label for="lwsop_ccss_mode"><?php echo esc_html($is_fr_ccss ? 'Mode :' : 'Mode:'); ?></label>
            <select id="lwsop_ccss_mode">
                <option value="auto"     <?php selected($ccss_mode, 'auto'); ?>><?php echo esc_html($is_fr_ccss ? 'Auto — génération locale (PHP intégré, recommandé)' : 'Auto — local generation (built-in PHP, recommended)'); ?></option>
                <option value="external" <?php selected($ccss_mode, 'external'); ?>><?php echo esc_html($is_fr_ccss ? 'Auto — génération via service LWS' : 'Auto — generation via LWS service'); ?></option>
                <option value="manual"   <?php selected($ccss_mode, 'manual'); ?>><?php echo esc_html($is_fr_ccss ? 'Manuel — coller le CSS ci-dessous' : 'Manual — paste CSS below'); ?></option>
            </select>
        </div>
        <div id="lwsop_ccss_manual_wrap"<?php echo $ccss_mode !== 'manual' ? ' style="display:none"' : ''; ?>>
            <textarea id="lwsop_ccss_manual" placeholder="<?php echo esc_attr($is_fr_ccss ? '/* CSS critique above-the-fold ici */' : '/* Critical CSS above-the-fold here */'); ?>"><?php echo esc_textarea($ccss_manual); ?></textarea>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('JavaScript Files', 'lws-optimize'); ?></h2>
</div>

<?php foreach ($second_bloc_array as $name => $data) : ?>
    <div class="lwsop_contentblock">
        <div class="lwsop_contentblock_leftside">
            <h2 class="lwsop_contentblock_title">
                <?php echo esc_html($data['title']); ?>
                <?php if ($data['recommended']) : ?>
                    <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                <?php endif ?>
                <?php if (isset($data['has_tooltip'])) : ?>
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo wp_kses($data['desc'], ['br' => []]); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <?php if ($data['has_exclusion']) : ?>
                <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                    <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                </div>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                                    echo esc_html("disabled");
                                                                                                                                                                                                                                } ?>>
                    <span>
                        <?php esc_html_e('Exclude files', 'lws-optimize'); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_special_button']) : ?>
                <?php if ($data['s_infobubble_value'] != "X") : ?>
                    <div name="exclusion_bubble" class="lwsop_exclusion_infobubble"><?php echo esc_html($data['s_infobubble_value']); ?><?php echo esc_html($data['s_button_infobubble']); ?></div>
                <?php endif ?>
                <button type="button" class="lwsop_darkblue_button" id="<?php echo esc_html($data['s_button_id']); ?>" name="<?php echo esc_html($data['s_button_id']); ?>">
                    <span>
                        <?php echo esc_html($data['s_button_title']); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_checkbox']) : ?>
                <label class="lwsop_checkbox" for="<?php echo esc_html($data['checkbox_id']); ?>">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                            echo esc_html("disabled");
                                                                                                                                                                                                                        } ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('General Optimisations', 'lws-optimize'); ?></h2>
</div>

<?php foreach ($third_bloc_array as $name => $data) : ?>
    <div class="lwsop_contentblock">
        <div class="lwsop_contentblock_leftside">
            <h2 class="lwsop_contentblock_title">
                <?php echo esc_html($data['title']); ?>
                <?php if ($data['recommended']) : ?>
                    <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                <?php endif ?>
                <?php if (isset($data['has_tooltip'])) : ?>
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo wp_kses($data['desc'], ['br' => []]); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <?php if ($data['has_exclusion']) : ?>
                <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                    <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                </div>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                                    echo esc_html("disabled");
                                                                                                                                                                                                                                } ?>>
                    <span>
                        <?php esc_html_e('Exclude files', 'lws-optimize'); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_special_button']) : ?>
                <?php if ($data['s_infobubble_value'] != "X") : ?>
                    <div name="exclusion_bubble" class="lwsop_exclusion_infobubble"><?php echo esc_html($data['s_infobubble_value']); ?><?php echo esc_html($data['s_button_infobubble']); ?></div>
                <?php endif ?>
                <button type="button" class="lwsop_darkblue_button" id="<?php echo esc_html($data['s_button_id']); ?>" name="<?php echo esc_html($data['s_button_id']); ?>">
                    <span>
                        <?php echo esc_html($data['s_button_title']); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_checkbox']) : ?>
                <label class="lwsop_checkbox" for="<?php echo esc_html($data['checkbox_id']); ?>">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $cloudflare_state) {
                                                                                                                                                                                                                            echo esc_html("disabled");
                                                                                                                                                                                                                        } ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
<?php endforeach; ?>

<?php
$_ccss_fr = substr(get_locale(), 0, 2) === 'fr';
$_ccss_badges_json = wp_json_encode([
    'auto'     => ['Auto',                                    '#DDF9EA', '#008A56'],
    'external' => [$_ccss_fr ? 'Externe'    : 'External',    '#DDF9EA', '#008A56'],
    'manual'   => [$_ccss_fr ? 'Manuel'     : 'Manual',      '#FFEDE1', '#FF6600'],
    'off'      => [$_ccss_fr ? 'Désactivé'  : 'Disabled',    '#FFE5E5', '#DB3D3D'],
]);
?>
<script>
(function(){
    var TOGGLE_ID = 'lws_optimize_critical_css_check';
    var STORE_KEY = 'lws_optimize_current_configuration_changes';
    // Separate key: currently-saved (DB) extra values. Format: [{type, value:{mode,manual_css}}]
    var EXTRA_KEY = 'lws_optimize_current_extra_config';
    var badges    = <?php echo $_ccss_badges_json; ?>;

    var modeSelect = document.getElementById('lwsop_ccss_mode');
    var manualWrap = document.getElementById('lwsop_ccss_manual_wrap');
    var manualArea = document.getElementById('lwsop_ccss_manual');
    var badge      = document.getElementById('lwsop_ccss_mode_badge');
    var toggle     = document.getElementById(TOGGLE_ID);
    var panel      = document.getElementById('lwsop_ccss_config_inline');

    // ── Saved-extras helpers ──────────────────────────────────────────────────
    function getSaved() {
        try {
            var arr = JSON.parse(localStorage.getItem(EXTRA_KEY) || '[]');
            var idx = arr.findIndex(function(it){ return it.type === TOGGLE_ID; });
            return idx !== -1 ? arr[idx].value : null;
        } catch(e) { return null; }
    }
    function setSaved(val) {
        try {
            var arr = JSON.parse(localStorage.getItem(EXTRA_KEY) || '[]');
            var idx = arr.findIndex(function(it){ return it.type === TOGGLE_ID; });
            if (idx === -1) arr.push({ type: TOGGLE_ID, value: val });
            else arr[idx].value = val;
            localStorage.setItem(EXTRA_KEY, JSON.stringify(arr));
        } catch(e) {}
    }

    // savedToggle: DB-saved toggle state. Initialised from PHP.
    // Updated by the localStorage override ONLY on actual saves (not on native
    // toggle-remove or deleteEntry calls — guarded by the two flags below).
    var savedToggle   = !!(toggle && toggle.checked);
    var inToggleHnd   = false; // true while the toggle change handler is running
    var inDeleteEntry = false; // true while deleteEntry() is executing

    // Initialise EXTRA_KEY from PHP-rendered values (current DB state).
    setSaved({
        mode:       (modeSelect || {}).value || 'auto',
        manual_css: (manualArea  || {}).value || '',
    });

    // ── Pending-changes helpers ───────────────────────────────────────────────
    function collectExtra() {
        return {
            mode:       (modeSelect || {}).value || 'auto',
            manual_css: (manualArea  || {}).value || '',
        };
    }
    function refreshCounter(cfg) {
        var el = document.getElementById('lws_optimize_amount_configuration_elements');
        if (el) el.innerHTML = cfg.length;
        var btn = document.getElementById('lws_optimize_validate_changes');
        if (btn) btn.disabled = cfg.length === 0;
    }
    function upsertEntry() {
        try {
            var cfg   = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
            var idx   = cfg.findIndex(function(it){ return it.type === TOGGLE_ID; });
            var entry = { type: TOGGLE_ID, state: !!(toggle && toggle.checked), extra: collectExtra() };
            if (idx === -1) cfg.push(entry); else cfg[idx] = entry;
            localStorage.setItem(STORE_KEY, JSON.stringify(cfg));
            refreshCounter(cfg);
        } catch(e) {}
    }
    function deleteEntry() {
        inDeleteEntry = true;
        try {
            var cfg = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
            var idx = cfg.findIndex(function(it){ return it.type === TOGGLE_ID; });
            if (idx !== -1) cfg.splice(idx, 1);
            localStorage.setItem(STORE_KEY, JSON.stringify(cfg));
            refreshCounter(cfg);
        } catch(e) {}
        inDeleteEntry = false;
    }

    // ── UI helpers ────────────────────────────────────────────────────────────
    function updateModeUI(mode) {
        var b = badges[mode] || badges['off'];
        if (badge) {
            badge.textContent      = b[0];
            badge.style.background = b[1];
            badge.style.color      = b[2];
        }
        if (manualWrap) manualWrap.style.display = (mode === 'manual') ? '' : 'none';
    }
    function restoreFromSaved() {
        var s = getSaved() || {};
        if (modeSelect) modeSelect.value = s.mode || 'auto';
        if (manualArea) manualArea.value  = s.manual_css || '';
        updateModeUI(s.mode || 'auto');
    }

    // ── Mode select listener ──────────────────────────────────────────────────
    if (modeSelect) {
        modeSelect.addEventListener('change', function(){
            updateModeUI(modeSelect.value);
            if (!toggle || !toggle.checked) return;
            var s         = getSaved() || {};
            var curManual = (manualArea || {}).value || '';
            // Only treat as "no change" if mode, manual AND toggle state all match DB.
            // Without the toggle check, reverting only the mode would wrongly remove
            // an entry that still carries a pending toggle-state change.
            if (modeSelect.value === (s.mode || 'auto')
                    && curManual === (s.manual_css || '')
                    && toggle.checked === savedToggle) {
                deleteEntry();
            } else {
                upsertEntry();
            }
        });
    }

    // ── Manual-CSS textarea listener ─────────────────────────────────────────
    if (manualArea) {
        manualArea.addEventListener('input', function(){
            if (!toggle || !toggle.checked) return;
            var s = getSaved() || {};
            if ((modeSelect || {}).value === (s.mode || 'auto')
                    && manualArea.value === (s.manual_css || '')
                    && toggle.checked === savedToggle) {
                deleteEntry();
            } else {
                upsertEntry();
            }
        });
    }

    // ── Toggle listener ───────────────────────────────────────────────────────
    if (toggle && panel) {
        toggle.addEventListener('change', function(){
            panel.classList.toggle('open', toggle.checked);
            inToggleHnd = true;

            if (toggle.checked) {
                restoreFromSaved();
            } else {
                restoreFromSaved();
                updateModeUI('off');
            }

            // Capture savedToggle now, before the native listener or override can
            // change it. The deferred closure must use this snapshot.
            var capturedSavedToggle = savedToggle;
            setTimeout(function(){
                var cfg = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
                var idx = cfg.findIndex(function(it){ return it.type === TOGGLE_ID; });
                if (idx !== -1) {
                    // Native added a new entry — attach extra payload.
                    cfg[idx].extra = collectExtra();
                    localStorage.setItem(STORE_KEY, JSON.stringify(cfg));
                } else if (toggle.checked !== capturedSavedToggle) {
                    // Native removed an existing entry (was a mode-change entry), but
                    // the toggle state genuinely differs from DB → real change.
                    upsertEntry();
                }
                // else: toggle is back to DB state → counter correctly stays 0.
                inToggleHnd = false;
            }, 0);
        });
    }

    // ── Post-save baseline update ─────────────────────────────────────────────
    // Intercept every write to STORE_KEY. When our entry is absent in the result
    // (tabs.php cleared on save, or deleteEntry removed the last entry), update the
    // saved baseline to reflect the current UI.
    // savedToggle is updated only when NEITHER inToggleHnd NOR inDeleteEntry is set,
    // meaning the write came from the save handler (not from our own housekeeping).
    var _lsSetItem = localStorage.setItem.bind(localStorage);
    localStorage.setItem = function(key, value) {
        _lsSetItem(key, value);
        if (key !== STORE_KEY) return;
        try {
            var cfg = JSON.parse(value || '[]');
            if (cfg.findIndex(function(it){ return it.type === TOGGLE_ID; }) !== -1) return;
            setSaved({
                mode:       (modeSelect || {}).value || 'auto',
                manual_css: (manualArea  || {}).value || '',
            });
            if (!inToggleHnd && !inDeleteEntry) {
                // This write came from the save handler → update savedToggle too.
                savedToggle = !!(toggle && toggle.checked);
            }
            var s = getSaved() || {};
            if (modeSelect) modeSelect.value = s.mode || 'auto';
            if (manualArea)  manualArea.value  = s.manual_css || '';
            updateModeUI(toggle && toggle.checked ? (s.mode || 'auto') : 'off');
        } catch(e) {}
    };
})();
</script>
