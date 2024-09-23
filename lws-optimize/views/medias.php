<?php

$media_array = array(
    // 'media_optimize' => array(
    //     'title' => __('LWS Media Optimize plugin', 'lws-optimize'),
    //     'desc' => __('This plugin lets you optimize and convert images (JPEG, PNG, WEBP, AVIF), reduce their size and maximum dimensions to speed up the website all the while assuring an universal browser compatibility.', 'lws-optimize'),
    //     'recommended' => true,
    //     'has_logo' => true,
    //     'logo' => plugin_dir_url(__DIR__) . 'images/logo_media_optimizer.svg',
    //     'logo_desc' => __('Logo Media Optimizer', 'lws-optimize'),
    //     'has_exclusion' => false,
    //     'has_exclusion_button' => false,
    //     'has_special_button' => true,
    //     's_button_title' => __('Manage', 'lws-optimize'),
    //     's_button_id' => "lws_optimize_media_optimize_button",
    //     'has_checkbox' => true,
    //     'checkbox_id' => "lws_optimize_media_optimize_check",
    // ),
    'image_lazyload' => array(
        'title' => __('Image Lazy Loading', 'lws-optimize'),
        'desc' => __('Load images only when they appear on screen, speeding up pages loading.', 'lws-optimize'),
        'recommended' => true,
        'has_logo' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_image_lazyload_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1886"
    ),
    // 'bg_image_lazyload' => array(
    //     'title' => __('CSS Background Image Lazy Loading', 'lws-optimize'),
    //     'desc' => __('Load CSS background images only when they appear on screen, speeding up loading time.', 'lws-optimize'),
    //     'recommended' => true,
    //     'has_logo' => false,
    //     'has_exclusion' => false,
    //     'has_exclusion_button' => false,
    //     'has_special_button' => false,
    //     'has_checkbox' => true,
    //     'checkbox_id' => "lws_optimize_bg_image_lazyload_check",
    // ),
    'iframe_video_lazyload' => array(
        'title' => __('Iframes and videos Lazy Loading', 'lws-optimize'),
        'desc' => __('Load integrated widgets and videos only when they appear on screen, boosting loading speed for pages rich in content.', 'lws-optimize'),
        'recommended' => true,
        'has_logo' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => false,
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_iframe_video_lazyload_check",
        'has_tooltip' => true,
        'tooltip_link' => "https://aide.lws.fr/a/1886"
    ),
    'lazyload_exclusion' => array(
        'title' => __('Exclude from Lazy Loading', 'lws-optimize'),
        'desc' => __('Exclude CSS classes, media types or specific images, videos and iframes.', 'lws-optimize'),
        'recommended' => false,
        'has_logo' => false,
        'has_exclusion' => false,
        'has_exclusion_button' => false,
        'has_special_button' => true,
        's_button_title' => __('Exclude files', 'lws-optimize'),
        's_button_id' => "lws_optimize_lazyload_exclusion_button",
        'has_checkbox' => false,
    ),
);

$media_type_excluded = array(
    'gravatar' => __('Gravatars', 'lws-optimize'),
    'thumbnails' => __('Thumbnails', 'lws-optimize'),
    'responsive' => __('Responsive', 'lws-optimize'),
    'iframe' => __('Iframes', 'lws-optimize'),
    'mobile' => __('Mobile', 'lws-optimize'),
    'video' => __('Videos', 'lws-optimize'),
);

foreach ($media_array as $key => $array) {
    $media_array[$key]['has_exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? true : false;
    $media_array[$key]['exclusion'] = isset($config_array[$key]['exclusions']) && count($config_array[$key]['exclusions']) > 0 ? $config_array[$key]['exclusions'] : "X";
    $media_array[$key]['state'] = isset($config_array[$key]['state']) && $config_array[$key]['state'] == "true" ? true : false;
}
?>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('LWS Media Optimize plugin', 'lws-optimize'); ?>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('This plugin lets you optimize and convert images (JPEG, PNG, WEBP, AVIF), reduce their size and maximum dimensions to speed up the website all the while assuring an universal browser compatibility.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <button type="button" class="lwsop_darkblue_button" id="" name="" disabled>
            <span><?php esc_html_e('Coming soon', 'lws-optimize'); ?></span>
        </button>
    </div>
</div>

<?php foreach ($media_array as $data) : ?>
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
            <?php if ($data['has_exclusion']) : ?>
                <div id="<?php echo esc_html($data['exclusion_id']); ?>_exclusions" name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                    <span><?php echo esc_html(count($data['exclusion'])); ?></span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                </div>
            <?php endif ?>
            <?php if ($data['has_exclusion_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['exclusion_id']); ?>" name="<?php echo esc_html($data['exclusion_id']); ?>">
                    <span>
                        <?php esc_html_e('Exclude files', 'lws-optimize'); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_special_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" id="<?php echo esc_html($data['s_button_id']); ?>" name="<?php echo esc_html($data['s_button_id']); ?>">
                    <span>
                        <?php echo esc_html($data['s_button_title']); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_checkbox']) : ?>
                <label class="lwsop_checkbox">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="lws_optimize_exclusion_lazyload_modale" tabindex='-1' role='dialog' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title" id="lws_optimize_exclusion_lazyload_title"><?php esc_html_e('Exclude from Lazy Loading', 'lws-optimize'); ?></h2>
            <form method="POST" id="lws_optimize_exclusion_lazyload_form"></form>
            <div class="lwsop_modal_buttons" id="lws_optimize_exclusion_lazyload_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('lws_optimize_lazyload_exclusion_button').addEventListener('click', function(event) {
        let element = this;
        let type = element.getAttribute('id');
        let name = element.getAttribute('value');

        let data = {
            'type': type,
            'name': name
        };

        // Show the modale on screen with loading animation & modified title
        let title = document.getElementById('lws_optimize_exclusion_lazyload_title');
        let form = document.getElementById('lws_optimize_exclusion_lazyload_form');
        form.innerHTML = `
            <div class="loading_animation">
                <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
            </div>
        `;

        document.getElementById('lws_optimize_exclusion_lazyload_buttons').innerHTML = `
            <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
        `;

        title.innerHTML = "<?php esc_html_e('Exclude from Lazy Loading ', 'lws-optimize'); ?>";
        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_fetch_exclusions")); ?>",
                action: "lws_optimize_fetch_exclusions_action",
                data: data
            },

            success: function(data) {
                if (data === null || typeof data != 'string') {
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    console.log(e);
                    returnData = {
                        'code': "NOT_JSON",
                        'data': "FAIL"
                    };
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        let infos = returnData['data'];
                        let site_url = returnData['domain'];

                        document.getElementById('lws_optimize_exclusion_lazyload_buttons').innerHTML = `
                            <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                            <button type="button" id="lws_optimize_exclusion_form_media" class="lwsop_validatebutton">
                                <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                            </button>
                        `;

                        form.innerHTML = `
                            <input type="hidden" name="lwsoptimize_exclude_url_id_media" value="` + type + `">
                        `;

                        if (infos['css_classes'] !== undefined) {
                            form.insertAdjacentHTML('beforeend', `
                                <h2 class="lwsoptimize_modal_exclude_subtitle"><?php esc_html_e('CSS Classes exclusion', 'lws-optimize'); ?></h2>
                                <div class="lwsoptimize_exclude_element_grid" id="lwsoptimize_exclude_element_grid"></div>
                                <h2 class="lwsoptimize_modal_exclude_subtitle"><?php esc_html_e('Media Types exclusion', 'lws-optimize'); ?></h2>
                                <div class="lwsoptimize_exclude_media_types" id="lwsoptimize_exclude_media_types"></div>
                            `);

                            for (var i in infos['css_classes']) {
                                document.getElementById('lwsoptimize_exclude_element_grid').insertAdjacentHTML('beforeend', `
                                    <div class="lwsoptimize_exclude_element">        
                                        <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="` + infos['css_classes'][i] + `">
                                        <div class="lwsoptimize_exclude_action_buttons">
                                            <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                            <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                        </div>
                                    </div>
                                `);
                            }
                        } else {
                            form.insertAdjacentHTML('beforeend', `
                                <h2 class="lwsoptimize_modal_exclude_subtitle"><?php esc_html_e('CSS Classes exclusion', 'lws-optimize'); ?></h2>
                                <div class="lwsoptimize_exclude_element_grid" id="lwsoptimize_exclude_element_grid">
                                    <div class="lwsoptimize_exclude_element">        
                                        <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="">
                                        <div class="lwsoptimize_exclude_action_buttons">
                                            <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                            <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                        </div>
                                    </div>
                                </div>
                                <h2 class="lwsoptimize_modal_exclude_subtitle"><?php esc_html_e('Media Types exclusion', 'lws-optimize'); ?></h2>
                                <div class="lwsoptimize_exclude_media_types" id="lwsoptimize_exclude_media_types"></div>
                            `);
                        }

                        let exclude_amount = document.querySelectorAll('div#lwsoptimize_exclude_element_grid input[name="lwsoptimize_exclude_url"]').length;
                        if (exclude_amount <= 1 && document.getElementById('lwsoptimize_exclude_element_grid') !== null) {
                            document.getElementById('lwsoptimize_exclude_element_grid').style.display = "block";
                        } else if (exclude_amount == 2 && document.getElementById('lwsoptimize_exclude_element_grid') !== null) {
                            document.getElementById('lwsoptimize_exclude_element_grid').style.display = "grid";
                            document.getElementById('lwsoptimize_exclude_element_grid').style.rowGap = "0";
                        } else if (document.getElementById('lwsoptimize_exclude_element_grid') !== null) {
                            document.getElementById('lwsoptimize_exclude_element_grid').style.display = "grid";
                            document.getElementById('lwsoptimize_exclude_element_grid').style.rowGap = "30px";
                        }

                        <?php foreach ($media_type_excluded as $id => $media) : ?>
                            document.getElementById('lwsoptimize_exclude_media_types').insertAdjacentHTML('beforeend', `
                                <div class="lwsoptimize_exclude_media_types_element">
                                    <label class="new_checkbox">
                                        <input type="checkbox" id="lwsoptimize_<?php echo esc_html($id); ?>" name="lwsoptimize_<?php echo esc_html($id); ?>">
                                        <span class="new_checkbox_check"></span>
                                    </label>
                                    <span><?php echo esc_html($media); ?></span>
                                </div>
                            `);

                            if (infos['media_types'] !== undefined) {
                                if (infos['media_types']["<?php echo esc_html($id); ?>"] !== undefined) {
                                    document.getElementById("lwsoptimize_<?php echo esc_html($id); ?>").checked = true;
                                } else {
                                    document.getElementById("lwsoptimize_<?php echo esc_html($id); ?>").checked = false;
                                }
                            } else {
                                document.getElementById("lwsoptimize_<?php echo esc_html($id); ?>").checked = false;
                            }
                        <?php endforeach; ?>

                        form.insertAdjacentHTML('beforeend', `
                            <h2 class="lwsoptimize_modal_exclude_subtitle"><?php esc_html_e('Images or Iframes excluded', 'lws-optimize'); ?></h2>
                            <div class="lwsoptimize_exclusion_info">
                                <?php esc_html_e('Exclude specific images or iframes by typing filenames or urls. One element per line.', 'lws-optimize'); ?>
                            </div>
                            <div style="padding: 30px; border-bottom: 1px solid #CCCCCC;">
                                <textarea id="lwsoptimize_excluded_iframes_img" name="lwsoptimize_excluded_iframes_img" placeholder="https://site.fr/wp-content/themes/mytheme/img/image.png\nhttps://www.lws.fr/"></textarea>
                            </div>
                        `);

                        if (infos['img_iframe'] !== null) {
                            document.getElementById('lwsoptimize_excluded_iframes_img').value = "";
                            for (var i in infos['img_iframe']) {
                                if ((infos['img_iframe'][i]).trim() !== '') {
                                    document.getElementById('lwsoptimize_excluded_iframes_img').value += (infos['img_iframe'][i]).trim() + "\n";
                                }
                            }
                        }

                        break;
                    case 'NOT_JSON':
                        callPopup('error', "<?php esc_html_e('Bad server response. Could not change action state.', 'lws-optimize'); ?>");
                        break;
                    case 'DATA_MISSING':
                        callPopup('error', "<?php esc_html_e('Not enough informations were sent to the server, please refresh and try again. Could not change action state.', 'lws-optimize'); ?>");
                        break;
                    case 'UNKNOWN_ID':
                        callPopup('error', "<?php esc_html_e('No matching action bearing this ID, please refresh and retry. Could not change action state.', 'lws-optimize'); ?>");
                        break;
                    case 'FAILURE':
                        callPopup('error', "<?php esc_html_e('Could not save change to action state in the database.', 'lws-optimize'); ?>");
                        break;
                    default:
                        break;
                }
            },
            error: function(error) {
                callPopup("error", "<?php esc_html_e('Unknown error. Cannot activate this option.', 'lws-optimize'); ?>");
                console.log(error);
                return 1;
            }
        });

        jQuery("#lws_optimize_exclusion_lazyload_modale").modal('show');
    });

    document.getElementById('lws_optimize_exclusion_lazyload_form').addEventListener("submit", function(event) {
        var element = event.target;
        if (element.getAttribute('id') == "lws_optimize_exclusion_lazyload_form") {
            event.preventDefault();
            document.body.style.pointerEvents = "none";
            let formData = jQuery(this).serializeArray();

            element.innerHTML = `
                <div class="loading_animation">
                    <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                </div>
            `;

            document.getElementById('lws_optimize_exclusion_lazyload_buttons').innerHTML = '';
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: formData,
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_exclusions_media_config")); ?>",
                    action: "lws_optimize_exclusions_media_changes_action",
                },
                success: function(data) {
                    document.body.style.pointerEvents = "all";
                    if (data === null || typeof data != 'string') {
                        return 0;
                    }

                    try {
                        var returnData = JSON.parse(data);
                    } catch (e) {
                        console.log(e);
                        returnData = {
                            'code': "NOT_JSON",
                            'data': "FAIL"
                        };
                    }

                    jQuery(document.getElementById('lws_optimize_exclusion_lazyload_modale')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e('Exclusions have been successfully saved.', 'lws-optimize'); ?>");

                            // Update "exclusions" count
                            let id = returnData['id'];
                            let bubble = document.getElementById(id + '_exclusions');

                            if (returnData['data'].length > 0) {
                                if (bubble == null) {
                                    document.getElementById(id).parentNode.insertAdjacentHTML('afterbegin', `
                                    <div id="` + id + `_exclusions"  name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                                        <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                                    </div>
                                    `);
                                } else {
                                    bubble.innerHTML = `
                                        <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('exclusions', 'lws-optimize'); ?></span>
                                    `;
                                }
                            } else {
                                if (bubble !== null) {
                                    bubble.remove();
                                }
                            }
                            break;
                        case 'NOT_JSON':
                            callPopup('error', "<?php esc_html_e('Bad server response. Could not save changes.', 'lws-optimize'); ?>");
                            break;
                        case 'DATA_MISSING':
                            callPopup('error', "<?php esc_html_e('Not enough informations were sent to the server, please try again.', 'lws-optimize'); ?>");
                            break;
                        case 'UNKNOWN_ID':
                            callPopup('error', "<?php esc_html_e('No matching action bearing this ID, please retry.', 'lws-optimize'); ?>");
                            break;
                        case 'FAILURE':
                            callPopup('error', "<?php esc_html_e('Could not save changes in the database.', 'lws-optimize'); ?>");
                            break;
                        default:
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";
                    jQuery(document.getElementById('lws_optimize_exclusion_modale')).modal('hide');
                    callPopup("error", "<?php esc_html_e('Unknown error. Cannot activate this option.', 'lws-optimize'); ?>");
                    console.log(error);
                }
            });
        }
    });
</script>