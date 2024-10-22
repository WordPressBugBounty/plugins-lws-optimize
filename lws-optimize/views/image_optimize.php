<?php
// Fetch the configuration for each elements of LWSOptimize
$config_array = $GLOBALS['lws_optimize']->optimize_options;
// Look up which Cache system is on this hosting. If FastestCache or LWSCache are found, we are on a LWS Hosting
$fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLE'] ?? NULL;
$lwscache_status = $_SERVER['lwscache'] ?? NULL;

// Whether LWSCache/FastestCache is active or not. If status is NULL : not a LWS Hosting
if ($lwscache_status == "Off") {
    $lwscache_status = false;
} elseif ($lwscache_status == "On") {
    $lwscache_status = true;
}

if ($fastest_cache_status == "0") {
    $fastest_cache_status = false;
} elseif ($fastest_cache_status == "1") {
    $fastest_cache_status = true;
}

$lwscache_locked = false;
if ($lwscache_status === NULL && $fastest_cache_status === NULL) {
    $lwscache_locked = true;
}

$is_imagick = true;
if (class_exists('Imagick')){
    global $wp_version;

    $mimetype_select_values = [
        'webp' => "WebP",
        'jpeg' => "JPEG",
        'png'  => "PNG",
    ];

    $is_imagick = true;
    $img = new Imagick();
    $supported_formats = $img->queryFormats();

    if (floatval($wp_version) > 6.5 && in_array("AVIF", $supported_formats)) {
        $mimetype_select_values = array_merge(['avif' => "AVIF"], $mimetype_select_values);
    }

    $latest_convertion = get_option('lws_optimize_current_media_convertion', [
        'latest_time' => time(),
        'data' => [
            'max' => $data['max'] ?? 0,
            'to_convert' => $data['to_convert'] ?? 0,
            'converted' => $data['converted'] ?? 0,
            'left' => $data['left'] ?? 0,
        ]
    ]);

    $autoconvert_state = $GLOBALS['lws_optimize']->lwsop_check_option('auto_update')['state'];

    $latest_time = $latest_convertion['latest_time'] ?? 0;
    if ($latest_time == 0) {
        $local_timestamp = "-";
    } else {
        $local_timestamp = get_date_from_gmt(date('Y-m-d H:i:s', $latest_time), 'Y-m-d H:i:s');
    }

    $convertion_data = $latest_convertion['data'] ?? [
        'max' => 0,
        'to_convert' => 0,
        'converted' => 0,
        'left' => 0,
    ];

    $next_scheduled_all_convert = wp_next_scheduled('lws_optimize_convert_media_cron');
    if ($next_scheduled_all_convert) {
        $next_scheduled_all_convert = get_date_from_gmt(date('Y-m-d H:i:s', $next_scheduled_all_convert), 'Y-m-d H:i:s');
    } else {
        $next_scheduled_all_convert = false;
    }
} else {
    $is_imagick = false;
    $autoconvert_state = "false";
    $next_scheduled_all_convert = false;

    $latest_time = 0;
    $local_timestamp = "-";

    $convertion_data = $latest_convertion['data'] ?? [
        'max' => 0,
        'to_convert' => 0,
        'converted' => 0,
        'left' => 0,
    ];

}
?>
<?php if ( $is_imagick == false) : ?>
    <div class="lwsop_noimagick_block">
        <?php esc_html_e('Imagick has not been found on this server. Please contact your hosting provider to learn more about the issue.', 'lws-optimize'); ?>
    </div>
<?php endif ?>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Image Convertion', 'lws-optimize'); ?></h2>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Convert all images', 'lws-optimize'); ?>
            <a href="" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" 
            width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
            <button id="lwsop_update_convertion_value" class="lwsop_update_info_button"><?php esc_html_e('Refresh', 'lws-optimize'); ?></button>

        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Convert all images on your website to another Mime-Type', 'lws-optimize'); ?>
        </div>
        <div class="lwsop_contentblock_convertion_status" id="lwsop_convertion_status">
            <div>
                <span><?php echo esc_html__('Status: ', 'lws-optimize'); ?></span> 
                <span id="lwsop_convertion_status_element"><?php echo $next_scheduled_all_convert ? esc_html__('Ongoing','lws-optimize') : esc_html__('Inactive','lws-optimize'); ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('Latest convertion done on: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_latest"><?php echo esc_html($local_timestamp); ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('Next convertion: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_next"><?php echo esc_html($next_scheduled_all_convert != false ? $next_scheduled_all_convert : "-"); ?></span>
            </div>
            <div class="lwsop_contentblock_total_convert_image">
                <span id="lwsop_convertion_done"><?php echo ($convertion_data['max'] - $convertion_data['left']); ?></span>
                <span><?php echo esc_html__(' images converted out of ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_max"><?php echo ($convertion_data['max'] ?? 0); ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('Images left to be converted: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_left"><?php echo esc_html($convertion_data['left']); ?></span>
            </div>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside" style="display: flex; flex-direction: column; align-items: flex-end;">
        <button type="button" class="lwsop_blue_button" id="" name="" data-target="#lwsop_convert_all_modal" data-toggle="modal">
            <span>
                <!-- <img src="<?php //echo esc_url(plugins_url('images/', __DIR__)) ?>" alt="" width="20px"> -->
                <?php esc_html_e('Convert images', 'lws-optimize'); ?>
            </span>
        </button>
        <button type="button" id="lwsop_deactivate_convertion" <?php echo $next_scheduled_all_convert ? "" : esc_attr('hidden'); ?>><?php esc_html_e('Abort convertion', 'lws-optimize'); ?></button>
    </div>

    <div class="lwsop_error_listing_main">
            <div id="show_images_converted_action">
                <span><?php esc_html_e('Show converted images', 'lws-optimize'); ?></span>
            </div>
            <div class="lwsop_contentblock_error_listing hidden" id="show_images_converted">
                <table class="lwsop_error_listing">
                    <thead>
                        <tr>
                            <td><?php esc_html_e('Name', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('Path', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('Date', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('Type', 'lws-optimize'); ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $images_converted = get_option('lws_optimize_original_image', ['auto_update' => ['original_media' => []]]); 
                        if (!isset($images_converted['auto_update']) || !isset($images_converted['auto_update']['original_media'])) {
                            $images_converted['auto_update']['original_media'] = [];
                        }
                        ?>
                        <?php foreach ($images_converted['auto_update']['original_media'] as $image) : ?>
                            <?php if (isset($image['original_name']) && isset($image['original_mime']) && isset($image['path']) && isset($image['mime']) && isset($image['converted'])) : ?>
                            <tr>
                                <td><?php echo esc_html($image['original_name']); ?></td>
                                <td><?php echo esc_html($image['path']); ?></td>
                                <td><?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $image['converted']), 'Y-m-d H:i:s')); ?></td>
                                <td><?php echo esc_html("image/" . $image['original_mime'] . " => " . $image['mime']); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Revert all images', 'lws-optimize'); ?>
            <a href="" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" 
            width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Revert all images to their original format. This only works for images not converted on upload and if you choose to keep the original image.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
    <?php if ( $is_imagick != false) : ?>
        <button type="button" class="lwsop_blue_button" id="lwsop_revert_all_images" name="lwsop_revert_all_images">
            <span>
                <?php esc_html_e('Revert images', 'lws-optimize'); ?>
            </span>
        </button>
    <?php endif ?>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Convert new images on upload', 'lws-optimize'); ?>
            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Each time an image is uploaded on your WordPress, it will automatically get converted to the chosen MIME-Type.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <?php if ( $is_imagick != false) : ?>
            <label class="lwsop_checkbox">
                <input type="checkbox" name="lwsop_onupload_convertion" id="lwsop_onupload_convertion" <?php echo $autoconvert_state == "true" ? esc_attr("checked") : ""; ?>>
                <span class="slider round"></span>
            </label>
        <?php endif ?>
    </div>
    <?php $errors = get_option('lws_optimize_autooptimize_errors', []);
        if (!empty($errors)) : ?>
        <div class="lwsop_error_listing_main">
            <div id="show_errors_autoupdate_action">
                <span><?php esc_html_e('Show failed convertions', 'lws-optimize'); ?></span>
            </div>
            <div class="lwsop_contentblock_error_listing hidden" id="show_errors_autoupdate">
                <table class="lwsop_error_listing">
                    <thead>
                        <tr>
                            <td><?php esc_html_e('Error type', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('Date', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('File', 'lws-optimize'); ?></td>
                            <td><?php esc_html_e('Convertion', 'lws-optimize'); ?></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error) : ?>
                            <tr>
                                <td><?php echo esc_html($error['error_type']); ?></td>
                                <td><?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $error['time']), 'Y-m-d H:i:s')); ?></td>
                                <td><?php echo esc_html($error['file']); ?></td>
                                <td><?php echo esc_html($error['type'] . " => image/" . $error['convert']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif ?>
</div>

<?php if ( $is_imagick != false) : ?>
    <div class="modal fade" id="lwsop_convert_all_modal" tabindex='-1' role='dialog'>
        <div class="modal-dialog" style="margin-top: 4%">
            <div class="modal-content" style="padding: 30px 0;">
                <div class="lwsop_convert_modal">
                    <label class="lwsop_convert_modal_label" for="lwsop_mimetype_select">
                        <span>
                            <?php esc_html_e('MIME-type in which media will be converted: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("Images already in this format will not be modified. Some types may not appear if your installation is not compatible.", "lws-optimize"); ?>"></a>
                        </span>
                        <select class="lwsop_convert_modal_type_select" id="lwsop_mimetype_select">
                            <?php foreach ($mimetype_select_values as $key => $value) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_quality_convertion">
                        <span>
                            <?php esc_html_e('Quality of the converted images: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("100% means the image will have the same quality as the original. A lower quality will result in a smaller but less clear image. 75% is a good middleground between size and appearance.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_quality" type="number" min="1" max="100" value="75" id="lwsop_quality_convertion">
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_amount_convertion">
                        <span>
                            <?php esc_html_e('Amount of images to convert per batch: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("Image convertion can take time and as such images will be converted in multiple instances. A larger amount means images will get converted quicker but it may slow down the website due to a larger load.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_amount_convertion_label" type="number" min="1" max="15" value="10" id="lwsop_amount_convertion">
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_keepcopy_convertion">
                        <span>
                            <?php esc_html_e('Keep the original images: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("By default, the original image will be kept, to allow for a rollback of the image to their original format. This will result in an increase in your website's size as two images will be saved instead of one. Deactivating this option will result in smaller sizes but all convertions are definitives.", "lws-optimize"); ?>"></a>
                        </span>
                        <label class="lwsop_checkbox">
                            <input type="checkbox" name="lwsop_keepcopy_convertion" id="lwsop_keepcopy_convertion" checked>
                            <span class="slider round"></span>
                        </label>
                    </label>

                    <label class="lwsop_convert_modal_label textarea" label="lwsop_exclude_from_convertion">
                        <span>
                            <?php esc_html_e('Image exclusion:', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("Input the name of each media you wish to exclude from the convertion. Separate each name with a comma (,).", "lws-optimize"); ?>"></a>
                        </span>
                        <textarea  class="lwsop_convert_modal_exclusions" id="lwsop_exclude_from_convertion" placeholder="image_1.png,background_image.jpg"></textarea>
                    </label>

                    <div class="lwsop_modal_buttons">
                        <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                        <button type="button" id="lwsop_all_convert_images" class="lwsop_validatebutton">
                            <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                            <?php echo esc_html_e('Convert images', 'lws-optimize'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lwsop_modal_convert_on_upload" tabindex='-1' role='dialog'>
        <div class="modal-dialog" style="margin-top: 10%">
            <div class="modal-content" style="padding: 30px 0;">
                <div class="lwsop_convert_modal">
                    <label class="lwsop_convert_modal_label" for="lwsop_mimetype_select_upload">
                        <span>
                            <?php esc_html_e('MIME-type in which media will be converted: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("Images already in this format will not be modified. Some types may not appear if your installation is not compatible.", "lws-optimize"); ?>"></a>
                        </span>
                        <select class="lwsop_convert_modal_type_select" id="lwsop_mimetype_select_upload">
                            <?php foreach ($mimetype_select_values as $key => $value) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_quality_convertion_upload">
                        <span>
                            <?php esc_html_e('Quality of the converted images: ', 'lws-optimize'); ?>
                            <a href="#" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" 
                            title="<?php esc_html_e("100% means the image will have the same quality as the original. A lower quality will result in a smaller but less clear image. 75% is a good middleground between size and appearance.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_quality" type="number" min="1" max="100" value="75" id="lwsop_quality_convertion_upload">
                    </label>

                    <div class="lwsop_modal_buttons">
                        <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                        <button type="button" id="lwsop_all_convert_images_upload" class="lwsop_validatebutton">
                            <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                            <?php echo esc_html_e('Activate convertion', 'lws-optimize'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>
<script>
<?php if (!empty($errors)) : ?>
    if (document.getElementById('show_errors_autoupdate_action') != null) {
        document.getElementById('show_errors_autoupdate_action').addEventListener('click', function() {
            let content = document.getElementById('show_errors_autoupdate');
            if (content != null) {
                content.classList.toggle('hidden');
            }
        })
    }
<?php endif ?>
    if (document.getElementById('show_images_converted_action') != null) {
        document.getElementById('show_images_converted_action').addEventListener('click', function() {
            let content = document.getElementById('show_images_converted');
            if (content != null) {
                content.classList.toggle('hidden');
            }
        })
    }
</script>
<script>
    function callPopup(type, content) {
        let modal = document.getElementById('modal_popup');
        let text_content = document.getElementById('container_content');
        if (modal === null || text_content === null) {
            console.log(JSON.stringify({
                'code': "POPUP_FAIL",
                'data': "Failed to find modal or its elements"
            }));
            return 1;
        }

        text_content.innerHTML = content;
        modal.setAttribute('data-result', type);
        jQuery('#modal_popup').modal('toggle');
    }

    function closemodal() {
        let modal = document.getElementById('modal_popup');
        if (modal === null) {
            console.log(JSON.stringify({
                'code': "POPUP_FAIL",
                'data': "Failed to find modal"
            }));
            return 1;
        }
        jQuery('#modal_popup').modal('hide');
    }
</script>

<?php if (get_option('lws_optimize_offline', null) === null) : ?>
    <script>
        document.querySelectorAll('input[name="lwsop_configuration[]"]').forEach(function(element) {
            element.addEventListener('change', function() {
                document.querySelectorAll('.lwsop_configuration_block_sub.selected').forEach(function(element) {
                    element.classList.remove('selected')
                });
                element.parentElement.parentElement.classList.add('selected')
            })
        })

        // Either open the modal to manage auto-convertion or stop the auto-convertion
        document.getElementById('lwsop_onupload_convertion').addEventListener('change', function(event) {
            let element = this;
            let checked = element.checked;
            element.checked = !checked;
            if (!checked) {
                document.body.style.pointerEvents = "none";

                // Deactivate the auto convertion
                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_stop_autoconvertion_nonce")); ?>",
                        action: "lwsop_stop_autoconvertion",
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

                        switch (returnData['code']) {
                            case 'SUCCESS':
                                element.checked = false;
                                callPopup('success', "<?php esc_html_e('Auto convertion stopped.', 'lws-optimize'); ?>");
                                break;
                            default:
                                console.log(returnData);
                                callPopup('error', "<?php esc_html_e('Unknown error. Cannot stop auto convertion.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        document.body.style.pointerEvents = "all";
                        console.log(error);
                        callPopup("error", "<?php esc_html_e('Unknown error. Cannot stop auto convertion.', 'lws-optimize'); ?>");
                    }
                });
            } else {
                // Open the modal
                jQuery('#lwsop_modal_convert_on_upload').modal('show');

            }
        });

        // Activate the auto-convertion of images on upload
        document.getElementById('lwsop_all_convert_images_upload').addEventListener('click', function() {
            document.body.style.pointerEvents = "none";
            let element = this;
            let checkbox = document.getElementById('lwsop_onupload_convertion');

            let old = element.innerHTML;
            element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;

            let media_type = document.getElementById('lwsop_mimetype_select_upload');
            let media_quality = document.getElementById('lwsop_quality_convertion_upload');

            media_type = media_type != null ? media_type.value : "webp";
            media_quality = media_quality != null ? media_quality.value : 75;


            let data = {
                'type':media_type,
                'quality':media_quality,
            };

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: data,
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_convert_all_images_on_upload_nonce")); ?>",
                    action: "lwsop_autoconvert_all_images_activate",
                },
                success: function(data) {
                    element.innerHTML = old;
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

                    jQuery(document.getElementById('lwsop_modal_convert_on_upload')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            if (checkbox != null) {
                                checkbox.checked = true;
                            }
                            callPopup('success', "<?php esc_html_e('Image convertion will now be done for each images uploaded. It may slightly lengthen the upload time', 'lws-optimize'); ?>");
                            break;
                        case 'FAILED':
                        default:
                            console.log(returnData);
                            callPopup('error', "<?php esc_html_e('Failed to start converting images', 'lws-optimize'); ?>");
                            break;
                    }
                },
                error: function(error) {
                    element.innerHTML = old;
                    document.body.style.pointerEvents = "all";

                    jQuery(document.getElementById('lwsop_modal_convert_on_upload')).modal('hide');
                    console.log(error);
                    callPopup("error", "<?php esc_html_e('Unknown error. Failed to start converting images', 'lws-optimize'); ?>");
                }
            });
        });

        document.getElementById('lwsop_all_convert_images').addEventListener('click', function() {
            document.body.style.pointerEvents = "none";

            let element = this.parentNode.parentNode;

            let media_type = document.getElementById('lwsop_mimetype_select');
            let media_quality = document.getElementById('lwsop_quality_convertion');
            let media_keepcopy = document.getElementById('lwsop_keepcopy_convertion');
            let media_exceptions = document.getElementById('lwsop_exclude_from_convertion');
            let amount_per_patch = document.getElementById('lwsop_amount_convertion');

            media_type = media_type != null ? media_type.value : "webp";
            media_quality = media_quality != null ? media_quality.value : 75;
            media_keepcopy = media_keepcopy != null ? media_keepcopy.value : true;
            media_exceptions = media_exceptions != null ? media_exceptions.value : "";
            amount_per_patch = amount_per_patch != null ? amount_per_patch.value : 10;


            let data = {
                'type':media_type,
                'quality':media_quality,
                'keepcopy':media_keepcopy,
                'exceptions':media_exceptions,
                'amount_per_patch':amount_per_patch
            };

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: data,
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_convert_all_images_nonce")); ?>",
                    action: "lwsop_convert_all_images",
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

                    jQuery(document.getElementById('lwsop_convert_all_modal')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e('Image convertion started. It may take a while depending on the amount of files to process.', 'lws-optimize'); ?>");
                            let convert_check_button = document.getElementById('lwsop_update_convertion_value');
                            if (convert_check_button != null) {
                                convert_check_button.dispatchEvent(new Event('click'));
                            }
                            document.getElementById('lwsop_deactivate_convertion').style.display = "block";
                            break;
                        case 'FAILED':
                            callPopup('error', "<?php esc_html_e('Failed to start converting images', 'lws-optimize'); ?>");
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e('Failed to start converting images', 'lws-optimize'); ?>");
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";

                    jQuery(document.getElementById('lwsop_convert_all_modal')).modal('hide');
                    callPopup("error", "<?php esc_html_e('Unknown error. Failed to start converting images', 'lws-optimize'); ?>");
                    console.log(error);
                }
            });
        });

        document.getElementById('lwsop_deactivate_convertion').addEventListener('click', function() {
            var element = this;
            document.body.style.pointerEvents = "none";

            let old = element.innerHTML;
            element.innerHTML = `
                <div class="loading_animation">
                        <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto">
                    </div>
            `;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_stop_convertion_nonce")); ?>",
                    action: "lwsop_stop_convertion",
                },
                success: function(data) {
                    element.innerHTML = old;
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

                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e('The convertion has been stopped.', 'lws-optimize'); ?>");
                            element.style.display = "none";
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e('Unknown error. Cannot abort convertion.', 'lws-optimize'); ?>");
                            break;
                    }
                },
                error: function(error) {
                    element.innerHTML = old;
                    document.body.style.pointerEvents = "all";
                    callPopup("error", "<?php esc_html_e('Unknown error. Cannot abort convertion.', 'lws-optimize'); ?>");
                    console.log(error);
                }
            });
        });

        document.getElementById('lwsop_revert_all_images').addEventListener('click', function() {
            var element = this;
            document.body.style.pointerEvents = "none";

            let old = element.innerHTML;
            element.innerHTML = `
                <div class="loading_animation">
                        <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto">
                    </div>
            `;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_revert_convertion_nonce")); ?>",
                    action: "lws_optimize_revert_convertion",
                },
                success: function(data) {
                    element.innerHTML = old;
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

                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e('All images are getting reverted. It may take a few moments.', 'lws-optimize'); ?>");
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e('Unknown error. Cannot revert images.', 'lws-optimize'); ?>");
                            break;
                    }
                },
                error: function(error) {
                    element.innerHTML = old;
                    document.body.style.pointerEvents = "all";
                    callPopup("error", "<?php esc_html_e('Unknown error. Cannot revert images.', 'lws-optimize'); ?>");
                    console.log(error);
                }
            });
        });

        let convert_check_button = document.getElementById('lwsop_update_convertion_value');
        if (convert_check_button != null) {
            convert_check_button.addEventListener('click', function() {
                let old_text = this.innerHTML;
                this.innerHTML = `
                    <span name="loading" style="padding-left:5px">
                        <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                    </span>
                `;

                this.disabled = true;
                let button = this;


                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_check_for_update_convert_image_nonce')); ?>',
                        action: "lwsop_check_convert_images_update"
                    },
                    success: function(data) {
                        button.disabled = false;
                        button.innerHTML = old_text;

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
                                let data = returnData['data'];

                                let status = document.getElementById('lwsop_convertion_status_element');
                                let latest = document.getElementById('lwsop_convertion_latest');
                                let next = document.getElementById('lwsop_convertion_next');
                                let done = document.getElementById('lwsop_convertion_done');
                                let max = document.getElementById('lwsop_convertion_max');
                                let left = document.getElementById('lwsop_convertion_left');

                                if (status != null) {
                                    if (data['status'] == true) {
                                        status.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                    } else {
                                        status.innerHTML = "<?php esc_html_e("Inactive", "lws-optimize"); ?>";
                                    }
                                }

                                if (latest != null) {
                                    latest.innerHTML = data['latest'];
                                }
                                if (next != null) {
                                    next.innerHTML = data['next'];
                                }
                                if (done != null) {
                                    done.innerHTML = data['done'];
                                }
                                if (max != null) {
                                    max.innerHTML = data['max'];
                                }
                                if (left != null) {
                                    left.innerHTML = data['left'];
                                }
                                break;
                            default:
                                break;
                        }
                    },
                    error: function(error) {
                        button.disabled = false;
                        button.innerHTML = old_text;
                        console.log(error);
                    }
                });
            });
        }



        jQuery(document).ready(function() {
            jQuery('[data-toggle="tooltip"]').tooltip();
        });
    </script>
<?php endif ?>