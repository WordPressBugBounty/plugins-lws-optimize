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
        'desc' => __('Fuse multiple CSS files into one to reduce server requests.', 'lws-optimize'),
        'recommended' => true,
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
        'desc' => __('Fuse multiple JS files into one to reduce server requests.', 'lws-optimize'),
        'recommended' => true,
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
        'desc' => __('Reduce your HTML file size by deleting useless characters.', 'lws-optimize'),
        'recommended' => true,
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
    // TODO : Separate optimize & preload
    // Optimize will, for now, preload fonts
    'preload_font' => array(
        'title' => __('Webfont preloading', 'lws-optimize'),
        'desc' => __('Preload used fonts to improve rendering speed.', 'lws-optimize'),
        // 'title' => __('Webfont optimization', 'lws-optimize'),
        // 'desc' => __('Modify the Google webfont loading to save HTTP requests and preload all other fonts.', 'lws-optimize'),
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
    // 'webfont_preload' => array(
    //     'title' => __('Webfont preloading', 'lws-optimize'),
    //     'desc' => __('Preload used fonts to improve rendering speed.', 'lws-optimize'),
    //     'recommended' => false,
    //     'has_exclusion' => false,
    //     'has_exclusion_button' => false,
    //     'has_special_button' => true,
    //     's_button_title' => __('Preload fonts', 'lws-optimize'),
    //     's_button_infobubble' => __('fonts', 'lws-optimize'),
    //     's_button_id' => "lws_optimize_webfont_preload_button",
    //     's_infobubble_value' => "X",
    //     'has_checkbox' => false,
    // ),
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

<?php $stop_css = isset($config_array['cloudflare']['tools']['min_css']) && $config_array['cloudflare']['tools']['min_css'] === true ? true : false; ?>
<?php $stop_js = isset($config_array['cloudflare']['tools']['min_js']) && $config_array['cloudflare']['tools']['min_js'] === true ? true : false; ?>
<?php foreach ($first_bloc_array as $name => $data) : ?>
    <div class="lwsop_contentblock">
        <div class="lwsop_contentblock_leftside">
            <h2 class="lwsop_contentblock_title">
                <?php echo esc_html($data['title']); ?>
                <?php if ($data['recommended']) : ?>
                    <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                <?php endif ?>
                <?php if (isset($data['has_tooltip'])) : ?>
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo esc_html($data['desc']); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) : ?>
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
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $stop_css) {
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
                <label class="lwsop_checkbox">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $stop_css) {
                                                                                                                                                                                                                            echo esc_html("disabled");
                                                                                                                                                                                                                        } ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
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
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo esc_html($data['desc']); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <?php if ($data['has_exclusion']) : ?>
                <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                    <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                </div>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) {
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
                <label class="lwsop_checkbox">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) {
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
                    <a href="<?php echo esc_url($data['tooltip_link']); ?>" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                <?php endif ?>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo esc_html($data['desc']); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside">
            <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <?php if ($data['has_exclusion']) : ?>
                <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                    <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                </div>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>" <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) {
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
                <label class="lwsop_checkbox">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?> <?php if (($name == "minify_css" || $name == "minify_js") && $stop_js) {
                                                                                                                                                                                                                            echo esc_html("disabled");
                                                                                                                                                                                                                        } ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
<?php endforeach; ?>

<?php
// Do not load scripts if LWS Optimize is OFF
if (get_option('lws_optimize_offline', null) === null) : ?>
    <script>
        // document.getElementById('lws_optimize_webfont_preload_button').addEventListener('click', function(event) {
        //     let element = this;
        //     let type = element.getAttribute('id');
        // });
    </script>
<?php endif ?>