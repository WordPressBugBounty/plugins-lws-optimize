<div class="lwsop_bluebanner_alt">
    <h2 class="lwsop_bluebanner_title">
        <?php esc_html_e('Analyze Performances of Your Website with Google PageSpeed', 'lws-optimize'); ?>
        <a href="https://aide.lws.fr/a/1892" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
    </h2>
    <div class="lwsop_bluebanner_subtitle"><?php esc_html_e('This section lets you launch Google PageSpeed tests and follow the PageSpeed scores history.', 'lws-optimize'); ?>
        <?php esc_html_e('Please be aware that those tests are done using low-end devices, to simulate performances on sub-optimal devices. Performances on your own devices may differ from what is shown.', 'lws-optimize'); ?></div>
</div>

<div class="lwsop_pagespeed_block">
    <div class="lwsop_pagespeed_start_line">
        <div class="lwsop_pagespeed_label">
            <span class="lwsop_pagespeed_label_text"><?php esc_html_e('Device type', 'lws-optimize'); ?></span>
            <div class="lwsop_custom_select" id="custom-select">
                <span id="custom-option" class="lwsop_custom_option">
                    <div id="custom_option_content">
                        <img style="pointer-events: none" src="<?php echo esc_url(plugins_url('images/ordinateur.svg', __DIR__)) ?>" alt="pc icon" width="20px" height="19px">
                        <span value="desktop" style="pointer-events: none" class=""><?php esc_html_e('Desktop', 'lws-optimize'); ?></span>
                    </div>
                    <img src="<?php echo esc_url(plugins_url('images/chevron_wp_manager.svg', __DIR__)) ?>" alt="chevron" width="12px" height="7px">
                </span>
                <ul class="lws_op_dropdown" id="custom-dropdown">
                    <li id="desktop_option">
                        <img style="pointer-events: none" src="<?php echo esc_url(plugins_url('images/ordinateur.svg', __DIR__)) ?>" alt="pc icon" width="20px" height="19px">
                        <span value="desktop" style="pointer-events: none" class=""><?php esc_html_e('Desktop', 'lws-optimize'); ?></span>
                    </li>
                    <li id="mobile_option">
                        <img style="pointer-events: none" src="<?php echo esc_url(plugins_url('images/mobile.svg', __DIR__)) ?>" alt="mobile icon" width="20px" height="19px">
                        <span value="mobile" style="pointer-events: none" class=""><?php esc_html_e('Mobile', 'lws-optimize'); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <label class="lwsop_pagespeed_textlabel" for="lwsop_pagespeed_url">
            <span class="lwsop_pagespeed_label_text"><?php esc_html_e('URL to Scan', 'lws-optimize'); ?></span>
            <input type="url" placeholder="https://example.com/" id="lwsop_pagespeed_url" value="<?php echo esc_url(get_site_url()); ?>/">
        </label>

        <button class="lwsop_pagespeed_button" id="pagespeed_scan_now">
            <?php esc_html_e('Scan', 'lws-optimize'); ?>
        </button>
    </div>
    <div id="pagespeed_results"></div>
</div>

<div class="lwsop_bluebanner_alt">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('PageSpeed History', 'lws-optimize'); ?></h2>
</div>

<?php
?>

<div class="lwsop_pagespeed_history" id="pagespeed_history">
    <?php $histories = get_option('lws_optimize_pagespeed_history', []);
    $histories = array_reverse($histories); ?>
    <?php if (!empty($histories)) : ?>
        <?php foreach ($histories as $history) : ?>
            <?php
            if ($history['scores']['performance'] * 100 >= 0 && $history['scores']['performance'] * 100 <= 49) {
                $background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#DB3D3D " . $history['scores']['performance'] * 100 . "%, #c9cbcc 0)";
                $color = "red";
            } else if ($history['scores']['performance'] * 100 >= 50 && $history['scores']['performance'] * 100 <= 89) {
                $background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#FF6600 " . $history['scores']['performance'] * 100 . "%, #c9cbcc 0)";
                $color = "orange";
            } else if ($history['scores']['performance'] * 100 >= 90 && $history['scores']['performance'] * 100 <= 100) {
                $background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#008A56 " . $history['scores']['performance'] * 100 . "%, #c9cbcc 0)";
                $color = "green";
            }

            if ($history['scores']['speed_milli'] >= 0 && $history['scores']['speed_milli'] <= 2000) {
                $bubble_color = "green";
            } else if ($history['scores']['speed_milli'] >= 2001 && $history['scores']['speed_milli'] <= 4000) {
                $bubble_color = "orange";
            } else if ($history['scores']['speed_milli'] >= 4001) {
                $bubble_color = "red";
            }
            ?>
            <div class="lwsop_pagespeed_history_element">
                <div class="lwsop_pagespeed_result_circle small" style="background:<?php echo esc_html($background); ?>">
                    <div class="lwsop_pagespeed_result_circle_text small <?php echo esc_html($color); ?>"><?php echo esc_html($history['scores']['performance'] * 100); ?></div>
                </div>
                <div class="lwsop_pagespeed_result_bubble small <?php echo esc_html($bubble_color); ?>">
                    <div class="lwsop_pagespeed_result_bubble_text small <?php echo esc_html($bubble_color); ?>"><?php echo esc_html($history['scores']['speed']); ?></div>
                </div>
                <div class="lwsop_pagespeed_history_text">
                    <div class="lwsop_pagespeed_history_text_top">
                        <?php echo esc_html__('PageSpeed Test from ', 'lws-optimize') . $history['date']; ?>
                    </div>
                    <div class="lwsop_pagespeed_history_text_bottom">
                        <?php if ($history['type'] == "desktop") : ?>
                            <div class="lwsop_pagespeed_history_text_bottom_left">
                                <img src="<?php echo esc_url(plugins_url('images/ordinateur.svg', __DIR__)) ?>" alt="pc icon" width="20px" height="19px">
                                <span value="desktop" class=""><?php esc_html_e('Desktop', 'lws-optimize'); ?></span>
                            </div>
                        <?php elseif ($history['type'] == "mobile") : ?>
                            <div class="lwsop_pagespeed_history_text_bottom_left">
                                <img src="<?php echo esc_url(plugins_url('images/mobile.svg', __DIR__)) ?>" alt="mobile icon" width="20px" height="19px">
                                <span value="mobile" class=""><?php esc_html_e('Mobile', 'lws-optimize'); ?></span>
                            </div>
                        <?php endif ?>
                        <div class="lwsop_pagespeed_history_text_bottom_right">
                            <span><?php echo esc_url($history['url']); ?></span>
                            <a target="_blank" rel="noopener" href="<?php echo esc_url($history['url']); ?>"><img src="<?php echo esc_url(plugins_url('images/lien.svg', __DIR__)) ?>" alt="icône de lien web" width="14px" height="13px"></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <span class="lwsop_no_pagespeed"><?php esc_html_e('No PageSpeed in history.', 'lws-optimize'); ?></span>
    <?php endif; ?>
</div>
<script>
    // Hide the dropdown menu when clicking on the select again
    document.getElementById('custom-option').addEventListener('click', function() {
        let dropdown = document.getElementById('custom-select');

        if (dropdown.classList.contains('active')) {
            dropdown.classList.remove('active')
        } else {
            dropdown.classList.add('active')
        }
    })

    document.addEventListener('click', function(event) {
        let dropdown = document.getElementById('custom-select');
        let target = event.target;
        let closest = target.closest('#custom-select');
        let select_options = ['desktop_option', 'mobile_option'];

        // Hide the dropdown menu when clicking somewhere else on the page
        if (closest === null && dropdown.classList.contains("active")) {
            dropdown.classList.remove('active');
        }

        // If clicking on one of the options, select it, as a normal select would
        if (jQuery.inArray(target.id, select_options) != -1) {
            document.getElementById('custom_option_content').innerHTML = target.innerHTML;
            dropdown.classList.remove('active');
        }
    })

    document.getElementById('pagespeed_scan_now').addEventListener('click', function() {
        let url = document.getElementById('lwsop_pagespeed_url').value;
        let type = document.getElementById('custom_option_content').children[1].getAttribute('value');

        let button = this;
        let text = button.innerHTML;

        button.innerHTML = '<div class="load-animated"><div class="line"></div><div class="line"></div><div class="line"></div></div>';

        if (url.length == 0 || url === undefined) {
            callPopup("error", `<?php esc_html_e('Please submit a valid URL', 'lws-optimize'); ?>`);
            return 0;
        }

        if (type.length == 0 || type === undefined) {
            callPopup("error", `<?php esc_html_e('Please submit a valid device type', 'lws-optimize'); ?>`);
            return 0;
        }

        button.style.pointerEvents = 'none';

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_doing_pagespeed_nonce')); ?>',
                action: "lws_optimize_do_pagespeed",
                type: type,
                url: url
            },

            success: function(data) {
                button.innerHTML = text;
                button.style.pointerEvents = 'all';
                if (data === null || typeof data != 'string') {
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        callPopup('success', "<?php esc_html_e('PageSpeed was executed successfully', 'lws-optimize'); ?>")

                        let score = returnData['data']['performance'] ?? null;
                        let speed = returnData['data']['speed'] ?? null;
                        let speed_milli = returnData['data']['speed_milli'] ?? null;

                        document.getElementById('pagespeed_results').innerHTML = `
                            <div class="lwsop_pagespeed_results left">
                                <div id="pagespeed_circle_bg" class="lwsop_pagespeed_result_circle">
                                    <div id="pagespeed_circle_text" class="lwsop_pagespeed_result_circle_text">` + score * 100 + `</div>
                                </div>
                                <span class="lwsop_pagespeed_result_subtext"><?php esc_html_e('Google Score : Performances', 'lws-optimize'); ?></span>
                                <div class="lwsop_pagespeed_result_scale_block">
                                    <span><?php esc_html_e('Scale:', 'lws-optimize'); ?></span>
                                    <span class="pagespeed_red_text">0-49</span>
                                    <span class="pagespeed_orange_text">50-89</span>
                                    <span class="pagespeed_green_text">90-100</span>
                                </div>
                            </div>

                            <div class="lwsop_pagespeed_results right">
                                <div id="pagespeed_bubble_speed_bg" class="lwsop_pagespeed_result_bubble">
                                    <div id="pagespeed_bubble_speed" class="lwsop_pagespeed_result_bubble_text">` + speed.replace(/\s/g, '') + `</div>
                                </div>
                                <span class="lwsop_pagespeed_result_subtext"><?php esc_html_e('Page Loading Time', 'lws-optimize'); ?></span>
                                <div class="lwsop_pagespeed_result_scale_block">
                                    <span><?php esc_html_e('Categories:', 'lws-optimize'); ?></span>
                                    <span class="pagespeed_red_text"><?php esc_html_e('More than 4s', 'lws-optimize'); ?></span>
                                    <span class="pagespeed_orange_text"><?php esc_html_e('2s-4s', 'lws-optimize'); ?></span>
                                    <span class="pagespeed_green_text"><?php esc_html_e('0s-2s', 'lws-optimize'); ?></span>
                                </div>
                            </div>
                        `;

                        let circle_bg = document.getElementById('pagespeed_circle_bg');
                        let circle_text = document.getElementById('pagespeed_circle_text');
                        let bubble_bg = document.getElementById('pagespeed_bubble_speed_bg');
                        let bubble_text = document.getElementById('pagespeed_bubble_speed');

                        if (score * 100 >= 0 && score * 100 <= 49) {
                            circle_bg.style.background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#DB3D3D " + score * 100 + "%, #c9cbcc 0)";
                            circle_text.classList.add('red');
                        } else if (score * 100 >= 50 && score * 100 <= 89) {
                            circle_bg.style.background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#FF6600 " + score * 100 + "%, #c9cbcc 0)";
                            circle_text.classList.add('orange');
                        } else if (score * 100 >= 90 && score * 100 <= 100) {
                            circle_bg.style.background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#008A56 " + score * 100 + "%, #c9cbcc 0)";
                            circle_text.classList.add('green');
                        }

                        if (speed_milli >= 0 && speed_milli <= 2000) {
                            bubble_bg.classList.add('green');
                            bubble_text.classList.add('green');
                        } else if (speed_milli >= 2001 && speed_milli <= 4000) {
                            bubble_bg.classList.add('orange');
                            bubble_text.classList.add('orange');
                        } else if (speed_milli >= 4001) {
                            bubble_bg.classList.add('red');
                            bubble_text.classList.add('red');
                        }

                        let page_history = document.getElementById('pagespeed_history');
                        if (page_history !== null) {
                            page_history.innerHTML = "";
                            let history = returnData['history'];
                            let background = '';
                            let color = '';
                            let bubble_color = '';

                            for (var i in history) {
                                if (history[i]['scores']['performance'] * 100 >= 0 && history[i]['scores']['performance'] * 100 <= 49) {
                                    background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#DB3D3D " + history[i]['scores']['performance'] * 100 + "%, #c9cbcc 0)";
                                    color = "red";
                                } else if (history[i]['scores']['performance'] * 100 >= 50 && history[i]['scores']['performance'] * 100 <= 89) {
                                    background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#FF6600 " + history[i]['scores']['performance'] * 100 + "%, #c9cbcc 0)";
                                    color = "orange";
                                } else if (history[i]['scores']['performance'] * 100 >= 90 && history[i]['scores']['performance'] * 100 <= 100) {
                                    background = "radial-gradient(closest-side, white 78%, transparent 80% 100%),conic-gradient(#008A56 " + history[i]['scores']['performance'] * 100 + "%, #c9cbcc 0)";
                                    color = "green";
                                }

                                if (history[i]['scores']['speed_milli'] >= 0 && history[i]['scores']['speed_milli'] <= 2000) {
                                    bubble_color = "green";
                                } else if (history[i]['scores']['speed_milli'] >= 2001 && history[i]['scores']['speed_milli'] <= 4000) {
                                    bubble_color = "orange";
                                } else if (history[i]['scores']['speed_milli'] >= 4001) {
                                    bubble_color = "red";
                                }
                                let type_text = '';
                                if (history[i]['type'] == 'desktop') {
                                    type_text = `
                                        <img src="<?php echo esc_url(plugins_url('images/ordinateur.svg', __DIR__)) ?>" alt="pc icon" width="20px" height="19px">
                                        <span value="desktop" class=""><?php esc_html_e('Desktop', 'lws-optimize'); ?></span>
                                    `;
                                } else if (history[i]['type'] == 'mobile') {
                                    type_text = `
                                        <img src="<?php echo esc_url(plugins_url('images/mobile.svg', __DIR__)) ?>" alt="mobile icon" width="20px" height="19px">
                                        <span value="mobile" class=""><?php esc_html_e('Mobile', 'lws-optimize'); ?></span>
                                    `;
                                }
                                page_history.insertAdjacentHTML('beforeend', `
                                    <div class="lwsop_pagespeed_history_element">
                                        <div class="lwsop_pagespeed_result_circle small" style="background:` + background + `">
                                            <div class="lwsop_pagespeed_result_circle_text small ` + color + `">` + history[i]['scores']['performance'] * 100 + `</div>
                                        </div>
                                        <div class="lwsop_pagespeed_result_bubble small ` + bubble_color + `">
                                            <div class="lwsop_pagespeed_result_bubble_text small ` + bubble_color + `">` + history[i]['scores']['speed'] + `</div>
                                        </div>
                                        <div class="lwsop_pagespeed_history_text">
                                            <div class="lwsop_pagespeed_history_text_top">
                                                <?php echo esc_html__('PageSpeed Test from ', 'lws-optimize') ?>` + history[i]['date'] + `
                                            </div>
                                            <div class="lwsop_pagespeed_history_text_bottom">
                                                <div class="lwsop_pagespeed_history_text_bottom_left">
                                                ` + type_text + `
                                                </div>
                                                <div class="lwsop_pagespeed_history_text_bottom_right">
                                                    <span>` + history[i]['url'] + `</span>
                                                    <a target="_blank" href="` + history[i]['url'] + `"><img src="<?php echo esc_url(plugins_url('images/lien.svg', __DIR__)) ?>" alt="icône de lien web" width="14px" height="13px"></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `);
                            }
                        }
                        break;
                    case 'NO_PARAM':
                        callPopup('error', "<?php esc_html_e('Please enter valid parameters and try again.', 'lws-optimize'); ?>")
                        break;
                    case 'ERROR_PAGESPEED':
                        callPopup('error', "<?php esc_html_e('PageSpeed returned an error. Please try again later.', 'lws-optimize'); ?>")
                        break;
                    case 'ERROR_DECODE':
                        callPopup('error', "<?php esc_html_e('Failed to process the PageSpeed.', 'lws-optimize'); ?>")
                        break;
                    case 'TOO_RECENT':
                        callPopup('warning', "<?php esc_html_e('You can only ask for one PageSpeed test every three minutes. Please wait and try again in ', 'lws-optimize'); ?>" + returnData['data'] + "<?php esc_html_e(' seconds', 'lws-optimize'); ?>")
                        break;
                    default:
                        console.log(returnData['code']);
                        break;
                }
            },
            error: function(error) {
                button.innerHTML = text;
                button.style.pointerEvents = 'all';
                console.log(error);
            }
        });
    })
</script>