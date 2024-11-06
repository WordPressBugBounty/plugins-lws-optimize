<?php

// Check the exection_time and memory_limit. If values are < 120 or < 128M, then we do not activate the convertion
$max_exec = ini_get('max_execution_time');
$memory_limit = ini_get('memory_limit');

if (str_contains($memory_limit, "M")) {
    $memory_limit = str_replace('M', '', $memory_limit);
    if ($memory_limit < 128) {
        $memory_limit = false;
    }
}

if ($max_exec < 120) {
    $max_exec = false;
}

// Fetch the configuration for each elements of LWSOptimize
$config_array = $GLOBALS['lws_optimize']->optimize_options;

// Look up which Cache system is on this hosting. If FastestCache or LWSCache are found, we are on a LWS Hosting
$fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLE'] ?? null;
$lwscache_status = $_SERVER['lwscache'] ?? null;

// Whether LWSCache/FastestCache is active or not. If status is null : not a LWS Hosting
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
if ($lwscache_status === null && $fastest_cache_status === null) {
    $lwscache_locked = true;
}

$convertible_images = [
    'avif' => "AVIF",
    'webp' => "WebP",
    'jpeg' => "JPEG",
    'png' => "PNG",
    'gif' => "GIF"
];

$is_imagick = true;
$avif_compatibility = false;

if (class_exists('Imagick')) {
    global $wp_version;

    $mimetype_select_values = [
        'webp' => "WebP",
    ];

    $is_imagick = true;
    $img = new Imagick();
    $supported_formats = $img->queryFormats();

    if (floatval($wp_version) > 6.5 && in_array("AVIF", $supported_formats)) {
        $mimetype_select_values = array_merge($mimetype_select_values,['avif' => "AVIF"]);
        $avif_compatibility = true;
    }

    $autoconvert_state = $GLOBALS['lws_optimize']->lwsop_check_option('auto_update')['state'];

    $next_scheduled_all_convert = wp_next_scheduled('lws_optimize_convert_media_cron');
    if ($next_scheduled_all_convert) {
        $next_scheduled_all_convert = get_date_from_gmt(date('Y-m-d H:i:s', $next_scheduled_all_convert), 'Y-m-d H:i:s');
    } else {
        $next_scheduled_all_convert = false;
    }

    $next_scheduled_deconvert = wp_next_scheduled('lwsop_revertOptimization');
    if ($next_scheduled_deconvert) {
        $next_scheduled_deconvert = get_date_from_gmt(date('Y-m-d H:i:s', $next_scheduled_deconvert), 'Y-m-d H:i:s');
    } else {
        $next_scheduled_deconvert = false;
    }
} else {
    $is_imagick = false;
    $autoconvert_state = "false";
    $next_scheduled_all_convert = false;
    $next_scheduled_deconvert = false;

    $latest_time = 0;
    $local_timestamp = "-";
}

$current_convertion = get_option('lws_optimize_current_convertion_stats', ['type' => "-", 'original' => "0", 'converted' => "0"]);

$execution_time_text_resolution = '';
if ($lwscache_locked) {
    $execution_time_text_resolution = esc_html__('Please contact your hosting provider to find out how to change this value.', 'lws-optimize');
} elseif ($lwscache_status !== null) {
    $execution_time_text_resolution = esc_html__('Please follow the instructions in the following ', 'lws-optimize') . '<a href="https://aide.lws.fr/base/Hebergement-web-mutualise/Utilisation-de-PHP/Configurer-PHP#content-5" rel="noopener" target="_blank">' . esc_html__('documentation', 'lws-optimize') . "</a>" . esc_html__(' to change this value.', 'lws-optimize');
} elseif ($fastest_cache_status !== null) {
    $execution_time_text_resolution = esc_html__('Please follow the instructions in the following ', 'lws-optimize') . '<a href="https://aide.lws.fr/a/1004" rel="noopener" target="_blank">' . esc_html__('documentation', 'lws-optimize') . "</a>" . esc_html__(' to change this value.', 'lws-optimize');
}

if (!$max_exec) {
    $execution_time_text = esc_html__('A max_execution_time of at least 120s is necessary to use this functionnality. Your currently have a value of ', 'lws-optimize') . ini_get('max_execution_time') . "s. <br>";
}

if (!$memory_limit) {
    $memory_limit_text = esc_html__('A memory_limit of at least 128M is necessary to use this functionnality. Your currently have a value of ', 'lws-optimize') . ini_get('memory_limit') . ". <br>";
}
?>

<?php if (!$is_imagick) : ?>
    <div class="lwsop_noimagick_block">
        <?php esc_html_e('Imagick has not been found on this server. Please contact your hosting provider to learn more about the issue.', 'lws-optimize'); ?>
    </div>
<?php endif ?>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Image Convertion', 'lws-optimize'); ?> - [BETA]</h2>
</div>

<div class="lwop_beta_cutout">
    <span><?php esc_html_e('This functionnality is still in beta and may not work properly on all websites. Please be careful and make sure to have a backup at the ready before using it.', 'lws-optimize'); ?></span>
    <span><?php esc_html_e('Important informations: ', 'lws-optimize'); ?></span>
    <ul>
        <?php if (!$max_exec) : ?>
            <li style="font-weight: 500;"><?php echo $execution_time_text . $execution_time_text_resolution; ?></li>
        <?php endif ?>
        <?php if (!$memory_limit) : ?>
            <li style="font-weight: 500;"><?php echo $memory_limit_text .$execution_time_text_resolution; ?></li>
        <?php endif ?>
        <?php if (!$avif_compatibility) : ?>
        <li><?php esc_html_e('AVIF convertion is not available on your website. Your WordPress needs to be updated to at least 6.5 and the installed Imagick version must be compatible with AVIF', 'lws-optimize'); ?></li>
        <?php else : ?>
        <li><?php esc_html_e('Images may lose transparency when converted to AVIF', 'lws-optimize'); ?></li>
        <li><?php esc_html_e('AVIF convertion is limited to images up to 400Kb. Depending on the amount of file and their size, the process may take a few minutes to complete', 'lws-optimize'); ?></li>
        <?php endif; ?>
        <?php if (!defined("DISABLE_WP_CRON") || !DISABLE_WP_CRON) : ?>
        <li>
        <div>
            <span><?php esc_html_e('Image convertion is a recurring task which may consume a lot of resources for a prolonged time. You are currently using WP-Cron, which means this task will only be executed when there is activity on your website and will use your website resources, slowing it down.', 'lws-optimize'); ?></span> <br>
            <span><?php esc_html_e('We recommend using a server cron, which will execute tasks at a specified time and without hogging resources, no matter what is happening on your website.', 'lws-optimize'); ?></span>
            <span>
                <?php if ($lwscache_locked) {
                    esc_html_e('For more informations on how to setup server crons, contact your hosting provider.', 'lws-optimize');
                }  elseif ($lwscache_status !== null) {
                    esc_html_e('For more informations on how to setup server crons by using the WPManager, follow this ', 'lws-optimize');
                    ?><a href="https://tutoriels.lws.fr/wordpress/wp-manager-de-lws-gerer-son-site-wordpress#Gerer_la_securite_et_les_parametres_generaux_de_votre_site_WordPress_avec_WP_Manager_LWS" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
                } elseif ($fastest_cache_status !== null) {
                    esc_html_e('For more informations on how to setup server crons, follow this ', 'lws-optimize');
                    ?><a href="https://support.cpanel.net/hc/en-us/articles/10687844130199-How-to-replace-wp-cron-with-cron-job-without-WP-Toolkit" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
                } ?>
            </span>
        </div>
        </li>
        <?php endif; ?>
    </ul>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Convert all images', 'lws-optimize'); ?>
            <a href="" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle"
                    width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
            <button id="lwsop_update_convertion_value" class="lwsop_update_info_button"><?php esc_html_e('Refresh', 'lws-optimize'); ?></button>

        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Convert all images on your website to another Mime-Type', 'lws-optimize'); ?>
        </div>
        <div class="lwsop_contentblock_convertion_status" id="lwsop_convertion_status">
            <div>
                <span><?php echo esc_html__('Status: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_status_element"><?php echo $next_scheduled_all_convert ? esc_html__('Ongoing', 'lws-optimize') : esc_html__('Inactive', 'lws-optimize'); ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('MIME-Type: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_type"><?php echo esc_html($current_convertion['type'] ?? '-'); ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('Next convertion: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_next"><?php echo esc_html($next_scheduled_all_convert ? $next_scheduled_all_convert : "-"); ?></span>
            </div>
            <div class="lwsop_contentblock_total_convert_image">
                <span id="lwsop_convertion_done"><?php echo $current_convertion['converted']; ?></span>
                <span><?php echo esc_html__(' images converted out of ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_max"><?php echo $current_convertion['original'] ?? 0; ?></span>
            </div>
            <div>
                <span><?php echo esc_html__('Images left to be converted: ', 'lws-optimize'); ?></span>
                <span id="lwsop_convertion_left"><?php echo esc_html(($current_convertion['original'] ?? 0) - ($current_convertion['converted'] ?? 0)); ?></span>
            </div>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside" style="display: flex; flex-direction: column; align-items: flex-end;">
        <?php if ($is_imagick && $max_exec && $memory_limit) : ?>
            <button style="<?php echo !$next_scheduled_all_convert ? esc_attr("display: block;") : esc_attr("display: none;"); ?>" type="button" class="lwsop_blue_button" id="lwsop_convertion_deactivate_modal" name="" data-target="#lwsop_convert_all_modal" data-toggle="modal">
                <span>
                    <?php esc_html_e('Convert images', 'lws-optimize'); ?>
                </span>
            </button>
            <button style="<?php echo $next_scheduled_all_convert ? esc_attr("display: block;") : esc_attr("display: none;"); ?>" type="button" class="lwsop_blue_button" id="lwsop_deactivate_convertion" <?php echo $next_scheduled_all_convert ? "" : esc_attr('hidden'); ?>><?php esc_html_e('Abort convertion', 'lws-optimize'); ?></button>
        <?php endif; ?>
    </div>

    <div class="lwsop_error_listing_main">
        <div id="show_images_converted_action">
            <img src="<?php echo esc_url(plugins_url('images/plus.svg', __DIR__)) ?>" alt="Logo Plus" width="15px" height="15px">
            <span><?php esc_html_e('Show converted images', 'lws-optimize'); ?></span>
        </div>
        <div class="lwsop_contentblock_error_listing hidden" id="show_images_converted">
            <table class="lwsop_error_listing">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'lws-optimize'); ?></th>
                        <th><?php esc_html_e('Type', 'lws-optimize'); ?></th>
                        <th><?php esc_html_e('Converted', 'lws-optimize'); ?></th>
                        <th><?php esc_html_e('Convertion Date', 'lws-optimize'); ?></th>
                        <th><?php esc_html_e('Compression', 'lws-optimize'); ?></th>
                    </tr>
                </thead>

                <tbody id="show_images_converted_tbody">
                    <?php $attachments = get_option('lws_optimize_images_convertion', []); ?>
                    <?php foreach ($attachments as $attachment) : ?>
                        <tr>
                            <td><?php echo esc_html($attachment['name']); ?></td>
                            <?php if ($attachment['converted']) : ?>
                                <td><?php echo esc_html($attachment['mime']); ?></td>
                                <td><?php echo esc_html__('Done', 'lws-optimize'); ?></td>
                                <td><?php echo get_date_from_gmt(date('Y-m-d H:i:s', $attachment['date_convertion']), 'Y-m-d H:i:s'); ?></td>
                                <td><?php echo esc_html(($attachment['compression'] ?? 0)) ?></td>
                            <?php else: ?>
                                <td><?php echo esc_html($attachment['original_mime']); ?></td>
                                <td><?php echo esc_html__('Pending', 'lws-optimize'); ?></td>
                                <td>/</td>
                                <td>/</td>
                            <?php endif ?>
                        </tr>
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
            <a href="" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle"
                    width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
            <button id="lwsop_update_deconvertion_value" class="lwsop_update_info_button"><?php esc_html_e('Refresh', 'lws-optimize'); ?></button>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Revert all images to their original format. This only works for images not converted on upload and if you choose to keep the original image.', 'lws-optimize'); ?>
        </div>

        <div class="lwsop_contentblock_convertion_status" id="lwsop_deconvertion_status">
            <div>
                <span><?php echo esc_html__('Status: ', 'lws-optimize'); ?></span>
                <span id="lwsop_deconvertion_status_element"><?php echo $next_scheduled_deconvert ? esc_html__('Ongoing', 'lws-optimize') : esc_html__('Inactive', 'lws-optimize'); ?></span>
            </div>
            <?php if ($next_scheduled_deconvert) : ?>
            <div id="lwsop_deconvertion_details_element">
                <div>
                    <span style="font-weight: 600;"><?php echo esc_html__('Next deconvertion: ', 'lws-optimize'); ?></span>
                    <span id="lwsop_deconvertion_next"><?php echo esc_html($next_scheduled_deconvert ? $next_scheduled_deconvert : "-"); ?></span>
                </div>
                <div class="lwsop_contentblock_total_convert_image">
                    <span id="lwsop_deconvertion_done"><?php echo $current_convertion['original'] - $current_convertion['converted']; ?></span>
                    <span><?php echo esc_html__(' images deconverted out of ', 'lws-optimize'); ?></span>
                    <span id="lwsop_deconvertion_max"><?php echo $convertion_data['original'] ?? 0; ?></span>
                </div>
                <div>
                    <span style="font-weight: 600;"><?php echo esc_html__('Images left to be deconverted: ', 'lws-optimize'); ?></span>
                    <span id="lwsop_deconvertion_left"><?php echo esc_html($current_convertion['converted']); ?></span>
                </div>
            </div>
            <?php endif ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside" style="display: flex; flex-direction: column; align-items: flex-end;">
        <?php if ($is_imagick && $max_exec && $memory_limit) : ?>
            <button style="<?php echo !$next_scheduled_deconvert ? esc_attr("display: block;") : esc_attr("display: none;"); ?>" type="button" class="lwsop_blue_button" id="lwsop_revert_all_images" name="lwsop_revert_all_images">
                <span>
                    <?php esc_html_e('Revert images', 'lws-optimize'); ?>
                </span>
            </button>
            <button style="<?php echo $next_scheduled_deconvert ? esc_attr("display: block;") : esc_attr("display: none;"); ?>" type="button" class="lwsop_blue_button" id="lwsop_deactivate_deconvertion" <?php echo $next_scheduled_deconvert ? "" : esc_attr('hidden'); ?>><?php esc_html_e('Abort restoration', 'lws-optimize'); ?></button>
        <?php endif ?>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Convert new images on upload', 'lws-optimize'); ?>
            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Each time an image is uploaded on your WordPress, it will automatically get converted to the chosen MIME-Type.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <?php if ($is_imagick && $max_exec && $memory_limit) : ?>
            <label class="lwsop_checkbox" for="lwsop_onupload_convertion">
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
                            <th><?php esc_html_e('Error type', 'lws-optimize'); ?></th>
                            <th><?php esc_html_e('Date', 'lws-optimize'); ?></th>
                            <th><?php esc_html_e('File', 'lws-optimize'); ?></th>
                            <th><?php esc_html_e('Convertion', 'lws-optimize'); ?></th>
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

<?php if ($is_imagick) : ?>
    <?php $media_convertion_values = get_option('lws_optimize_all_media_convertion', ['convert_type' => 'webp', 'keep_copy' => true, 'quality' => 75, 'exceptions' => [], 'amount_per_run' => 10]); ?>
    <div class="modal fade" id="lwsop_convert_all_modal" tabindex='-1'>
        <div class="modal-dialog" style="margin-top: 4%">
            <div class="modal-content" style="padding: 30px 0; overflow-y: scroll; max-height: 750px;">
                <div class="lwsop_convert_modal">
                    <label class="lwsop_convert_modal_label" for="lwsop_mimetype_select">
                        <span>
                            <?php esc_html_e('MIME-type in which media will be converted: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("Images already in this format will not be modified. Some types may not appear if your installation is not compatible.", "lws-optimize"); ?>"></a>
                        </span>
                        <select class="lwsop_convert_modal_type_select" id="lwsop_mimetype_select">
                            <?php foreach ($mimetype_select_values as $key => $value) : ?>
                                <option <?php echo isset($media_convertion_values['convert_type']) && $media_convertion_values['convert_type'] == $key ? esc_attr('selected') : ''; ?> value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_quality_convertion">
                        <span>
                            <?php esc_html_e('Quality of the converted images: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("100% means the image will have the same quality as the original. A lower quality will result in a smaller but less clear image. 75% is a good middleground between size and appearance.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_quality" type="number" min="1" max="100" value="<?php echo isset($media_convertion_values['quality']) ? esc_attr($media_convertion_values['quality']) : esc_attr(75); ?>" id="lwsop_quality_convertion">
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_amount_convertion">
                        <span>
                            <?php esc_html_e('Amount of images to convert per batch: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("Image convertion can take time and as such images will be converted in multiple instances. A larger amount means images will get converted quicker but it may slow down the website due to a larger load.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_amount_convertion_label" type="number" min="1" max="15" value="<?php echo isset($media_convertion_values['amount_per_run']) ? esc_attr($media_convertion_values['amount_per_run']) : esc_attr(10); ?>" id="lwsop_amount_convertion">
                    </label>

                    <label class="lwsop_convert_modal_label" for="lwsop_keepcopy_convertion">
                        <span>
                            <?php esc_html_e('Keep the original images: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("By default, the original image will be kept, to allow for a rollback of the image to their original format. This will result in an increase in your website's size as two images will be saved instead of one. Deactivating this option will result in smaller sizes but all convertions are definitives.", "lws-optimize"); ?>"></a>
                        </span>
                        <label class="lwsop_checkbox" for="lwsop_keepcopy_convertion">
                            <input type="checkbox" name="lwsop_keepcopy_convertion" id="lwsop_keepcopy_convertion" <?php echo isset($media_convertion_values['keep_copy']) && $media_convertion_values['keep_copy'] == "true" ? esc_attr('checked') : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </label>

                    <label class="lwsop_convert_modal_label column">
                        <span>
                            <?php esc_html_e('MIME-Types to convert: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("If you wish to add or remove some image types from the convertion. This allows you to only convert specific image types or have more control over what may or may not be converted.", "lws-optimize"); ?>"></a>
                        </span>
                        <div class="lwsop_convert_modal_mimetype_grid">
                            <?php foreach ($convertible_images as $key => $image_type) : ?>
                                <label class="">
                                    <input class="" type="checkbox" name="lwsop_convertible_image_<?php echo esc_attr($key); ?>" id="lwsop_convertible_image_<?php echo esc_attr($key); ?>" <?php echo isset($media_convertion_values['convertible_mimetype']) && !in_array($key, $media_convertion_values['convertible_mimetype']) ? '' :esc_attr('checked'); ?>>
                                    <span><?php echo esc_html($image_type); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </label>

                    <label class="lwsop_convert_modal_label textarea" label="lwsop_exclude_from_convertion">
                        <span>
                            <?php esc_html_e('Image exclusion:', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("Input the name of each media you wish to exclude from the convertion. Separate each name with a comma (,).", "lws-optimize"); ?>"></a>
                        </span>
                        <textarea class="lwsop_convert_modal_exclusions" id="lwsop_exclude_from_convertion" placeholder="image_1.png,background_image.jpg"><?php echo isset($media_convertion_values['exceptions']) ? esc_attr(implode(',', $media_convertion_values['exceptions'])) : ''; ?></textarea>
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

    <div class="modal fade" id="lwsop_modal_convert_on_upload" tabindex='-1'>
        <div class="modal-dialog" style="margin-top: 10%">
            <div class="modal-content" style="padding: 30px 0;">
                <div class="lwsop_convert_modal">
                    <label class="lwsop_convert_modal_label" for="lwsop_mimetype_select_upload">
                        <span>
                            <?php esc_html_e('MIME-type in which media will be converted: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
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
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("100% means the image will have the same quality as the original. A lower quality will result in a smaller but less clear image. 75% is a good middleground between size and appearance.", "lws-optimize"); ?>"></a>
                        </span>
                        <input class="lwsop_convert_modal_quality" type="number" min="1" max="100" value="75" id="lwsop_quality_convertion_upload">
                    </label>

                    <label class="lwsop_convert_modal_label column">
                        <span>
                            <?php esc_html_e('MIME-Types to convert: ', 'lws-optimize'); ?>
                            <a href="#" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                                    title="<?php esc_html_e("If you wish to add or remove some image types from the convertion. This allows you to only convert specific image types or have more control over what may or may not be converted.", "lws-optimize"); ?>"></a>
                        </span>
                        <div class="lwsop_convert_modal_mimetype_grid">
                            <?php foreach ($convertible_images as $key => $image_type) : ?>
                                <label class="">
                                    <input class="" type="checkbox" name="lwsop_auto_convertible_image_<?php echo esc_attr($key); ?>" id="lwsop_auto_convertible_image_<?php echo esc_attr($key); ?>" checked>
                                    <span><?php echo esc_html($image_type); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
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

        if (document.getElementById('lwsop_onupload_convertion') != null) {
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
                            return -1;
                        }
                    });
                } else {
                    // Open the modal
                    jQuery('#lwsop_modal_convert_on_upload').modal('show');

                }
            });
        }

        if (document.getElementById('lwsop_all_convert_images_upload') != null) {
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

                let mimetypes = [];
                document.querySelectorAll('[id^="lwsop_auto_convertible_image_"]').forEach(function(check) {
                    let id = check.id.replace('lwsop_auto_convertible_image_', '');
                    if (check.checked) {
                        mimetypes.push(id);
                    }
                });


                let data = {
                    'type': media_type,
                    'quality': media_quality,
                    'mimetypes': mimetypes
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
                        return -1;
                    }
                });
            });
        }

        if (document.getElementById('lwsop_all_convert_images') != null) {
            document.getElementById('lwsop_all_convert_images').addEventListener('click', function() {
                document.body.style.pointerEvents = "none";

                let element = this.parentNode.parentNode;

                let media_type = document.getElementById('lwsop_mimetype_select');
                let media_quality = document.getElementById('lwsop_quality_convertion');
                let media_keepcopy = document.getElementById('lwsop_keepcopy_convertion');
                let media_exceptions = document.getElementById('lwsop_exclude_from_convertion');
                let amount_per_patch = document.getElementById('lwsop_amount_convertion');

                let mimetypes = [];
                document.querySelectorAll('[id^="lwsop_convertible_image_"]').forEach(function(check) {
                    let id = check.id.replace('lwsop_convertible_image_', '');
                    if (check.checked) {
                        mimetypes.push(id);
                    }
                });

                media_type = media_type != null ? media_type.value : "webp";
                media_quality = media_quality != null ? media_quality.value : 75;
                media_keepcopy = media_keepcopy != null ? media_keepcopy.checked : true;
                media_exceptions = media_exceptions != null ? media_exceptions.value : "";
                amount_per_patch = amount_per_patch != null ? amount_per_patch.value : 10;


                let data = {
                    'type': media_type,
                    'quality': media_quality,
                    'keepcopy': media_keepcopy,
                    'exceptions': media_exceptions,
                    'amount_per_patch': amount_per_patch,
                    'mimetypes': mimetypes
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
                                document.getElementById('lwsop_convertion_deactivate_modal').style.display = "none";
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
                        return -1;
                    }
                });
            });
        }

        if (document.getElementById('lwsop_deactivate_convertion') != null) {
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
                                document.getElementById('lwsop_convertion_deactivate_modal').style.display = "block";
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
                        return -1;
                    }
                });
            });
        }

        if (document.getElementById('lwsop_deactivate_deconvertion') != null) {
            document.getElementById('lwsop_deactivate_deconvertion').addEventListener('click', function() {
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
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_stop_deconvertion_nonce")); ?>",
                        action: "lwsop_stop_deconvertion",
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
                                callPopup('success', "<?php esc_html_e('The restoration has been stopped.', 'lws-optimize'); ?>");
                                element.style.display = "none";
                                document.getElementById('lwsop_revert_all_images').style.display = "block";
                                break;
                            default:
                                callPopup('error', "<?php esc_html_e('Unknown error. Cannot abort restoration.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        element.innerHTML = old;
                        document.body.style.pointerEvents = "all";
                        callPopup("error", "<?php esc_html_e('Unknown error. Cannot abort restoration.', 'lws-optimize'); ?>");
                        console.log(error);
                        return -1;
                    }
                });
            });
        }

        if (document.getElementById('lwsop_revert_all_images') != null) {
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
                                data = returnData['data'];
                                callPopup('success', "<?php esc_html_e('All images are getting reverted. It may take a few moments.', 'lws-optimize'); ?>");
                                let deconvertion_status = document.getElementById('lwsop_deconvertion_status');
                                let deconvertion_status_content = document.getElementById('lwsop_deconvertion_details_element');
                                document.getElementById('lwsop_deactivate_deconvertion').style.display = "block";
                                element.style.display = "none";

                                // If the details are already shown
                                if (deconvertion_status_content != null) {
                                    let deconvertion_status = document.getElementById('lwsop_deconvertion_status_element');
                                    let deconvertion_next = document.getElementById('lwsop_deconvertion_next');
                                    let deconvertion_done = document.getElementById('lwsop_convertion_done');
                                    let deconvertion_max = document.getElementById('lwsop_deconvertion_max');
                                    let deconvertion_left = document.getElementById('lwsop_deconvertion_left');


                                    if (deconvertion_status != null && data['status'] == true) {
                                        deconvertion_status.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                    }

                                    if (deconvertion_next != null) {
                                        deconvertion_next.innerHTML = data['next'];
                                    }
                                    if (deconvertion_done != null) {
                                        deconvertion_done.innerHTML = data['done'];
                                    }
                                    if (deconvertion_max != null) {
                                        deconvertion_max.innerHTML = data['max'];
                                    }
                                    if (deconvertion_left != null) {
                                        deconvertion_left.innerHTML = data['left'];
                                    }
                                } else {
                                    // If only the status is shown
                                    if (deconvertion_status != null) {
                                        deconvertion_status.insertAdjacentHTML('beforeend', `
                                        <div id="lwsop_deconvertion_details_element">
                                            <div>
                                                <span style="font-weight: 600;"><?php echo esc_html__('Next deconvertion: ', 'lws-optimize'); ?></span>
                                                <span id="lwsop_deconvertion_next">` + data['next'] + `</span>
                                            </div>
                                            <div class="lwsop_contentblock_total_convert_image">
                                                <span id="lwsop_convertion_done">` + data['left'] + `</span>
                                                <span><?php echo esc_html__(' images deconverted out of ', 'lws-optimize'); ?></span>
                                                <span id="lwsop_deconvertion_max">`+ data['max'] + `</span>
                                            </div>
                                            <div>
                                                <span style="font-weight: 600;"><?php echo esc_html__('Images left to be deconverted: ', 'lws-optimize'); ?></span>
                                                <span id="lwsop_deconvertion_left">` + (data['max'] - data['left']) + `</span>
                                            </div>
                                        </div>
                                        `);
                                    }
                                }
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
                        return -1;
                    }
                });
            });
        }

        function lws_op_update_convertion_info() {
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="chargement" width="18px" height="18px">
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

                            // CONVERT IMAGES

                            let status = document.getElementById('lwsop_convertion_status_element');
                            let type = document.getElementById('lwsop_convertion_type');
                            let next = document.getElementById('lwsop_convertion_next');
                            let done = document.getElementById('lwsop_convertion_done');
                            let max = document.getElementById('lwsop_convertion_max');
                            let left = document.getElementById('lwsop_convertion_left');
                            let listing = document.getElementById('show_images_converted_tbody');

                            if (status != null) {
                                if (data['status'] == true) {
                                    status.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                    if (document.getElementById('lwsop_deactivate_convertion') != null) {
                                        document.getElementById('lwsop_deactivate_convertion').style.display = "block";
                                    }
                                    if (document.getElementById('lwsop_convertion_deactivate_modal') != null) {
                                        document.getElementById('lwsop_convertion_deactivate_modal').style.display = "none";
                                    }
                                } else {
                                    status.innerHTML = "<?php esc_html_e("Inactive", "lws-optimize"); ?>";
                                    if (document.getElementById('lwsop_deactivate_convertion') != null) {
                                        document.getElementById('lwsop_deactivate_convertion').style.display = "none";
                                    }
                                    if (document.getElementById('lwsop_convertion_deactivate_modal') != null) {
                                        document.getElementById('lwsop_convertion_deactivate_modal').style.display = "block";
                                    }
                                }
                            }

                            if (type != null) {
                                type.innerHTML = data['convert_type'] ?? '-';
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

                            if (listing != null) {
                                let data_listing = data['listing'] ?? [];
                                listing.innerHTML = '';
                                for (i in data_listing) {
                                    if (data_listing[i]['converted']) {
                                        listing.insertAdjacentHTML('afterbegin', `
                                        <tr>
                                            <td>` + data_listing[i]['name'] + `</td>
                                            <td>` + data_listing[i]['mime'] + `</td>
                                            <td><?php echo esc_html__('Done', 'lws-optimize'); ?></td>
                                            <td>` + (new Date(data_listing[i]['date_convertion'] * 1000).toLocaleString()).replaceAll('/', '-') + `</td>
                                            <td>` + (data_listing[i]['compression'] ?? 0) + `</td>
                                        </tr>
                                        `);
                                    } else {
                                        listing.insertAdjacentHTML('afterbegin', `
                                        <tr>
                                            <td>` + data_listing[i]['name'] + `</td>
                                            <td>` + data_listing[i]['original_mime'] + `</td>
                                            <td><?php echo esc_html__('Pending', 'lws-optimize'); ?></td>
                                            <td>/</td>
                                            <td>/</td>
                                        </tr>
                                        `);
                                    }
                                }
                            }

                            // REVERT IMAGES //

                            let deconvertion_status_main = document.getElementById('lwsop_deconvertion_status');
                            let deconvertion_status_content = document.getElementById('lwsop_deconvertion_details_element');
                            let deconvertion_status = document.getElementById('lwsop_deconvertion_status_element');

                            if (!data['status_revert']) {
                                if (deconvertion_status != null) {
                                    deconvertion_status.innerHTML = "<?php esc_html_e("Inactive", "lws-optimize"); ?>";
                                }
                                if (deconvertion_status_content != null) {
                                    deconvertion_status_content.remove();
                                }
                            }

                            // If the details are already shown
                            if (deconvertion_status_content != null) {
                                let deconvertion_status = document.getElementById('lwsop_deconvertion_status_element');
                                let deconvertion_next = document.getElementById('lwsop_deconvertion_next');
                                let deconvertion_done = document.getElementById('lwsop_deconvertion_done');
                                let deconvertion_max = document.getElementById('lwsop_deconvertion_max');
                                let deconvertion_left = document.getElementById('lwsop_deconvertion_left');


                                if (deconvertion_status != null && data['status_revert'] == true) {
                                    deconvertion_status.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                }

                                if (deconvertion_next != null) {
                                    deconvertion_next.innerHTML = data['next_deconvert'];
                                }
                                if (deconvertion_done != null) {
                                    deconvertion_done.innerHTML = data['left'];
                                }
                                if (deconvertion_max != null) {
                                    deconvertion_max.innerHTML = data['max'];
                                }
                                if (deconvertion_left != null) {
                                    deconvertion_left.innerHTML = data['done'];
                                }
                            } else {
                                if (deconvertion_status != null && data['status_revert'] == true) {
                                    // If only the status is shown
                                    deconvertion_status.insertAdjacentHTML('beforeend', `
                                    <div id="lwsop_deconvertion_details_element">
                                        <div>
                                            <span style="font-weight: 600;"><?php echo esc_html__('Next deconvertion: ', 'lws-optimize'); ?></span>
                                            <span id="lwsop_deconvertion_next">` + data['next_deconvert'] + `</span>
                                        </div>
                                        <div class="lwsop_contentblock_total_convert_image">
                                            <span id="lwsop_deconvertion_done">` + data['left'] + `</span>
                                            <span><?php echo esc_html__(' images deconverted out of ', 'lws-optimize'); ?></span>
                                            <span id="lwsop_deconvertion_max">`+ data['max'] + `</span>
                                        </div>
                                        <div>
                                            <span style="font-weight: 600;"><?php echo esc_html__('Images left to be deconverted: ', 'lws-optimize'); ?></span>
                                            <span id="lwsop_deconvertion_left">` + (data['done']) + `</span>
                                        </div>
                                    </div>`);
                                }
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
                    return -1;
                }
            });
        }

        let convert_check_button = document.getElementById('lwsop_update_convertion_value');
        if (convert_check_button != null) {
            convert_check_button.addEventListener('click', lws_op_update_convertion_info);

            setInterval(function(){
                convert_check_button.dispatchEvent(new Event('click'));
            }, 60000);
        }

        let deconvert_check_button = document.getElementById('lwsop_update_deconvertion_value');
        if (deconvert_check_button != null) {
            deconvert_check_button.addEventListener('click', lws_op_update_convertion_info);

            setInterval(function(){
                deconvert_check_button.dispatchEvent(new Event('click'));
            }, 60000)
        }

        jQuery(document).ready(function() {
            jQuery('[data-toggle="tooltip"]').tooltip();
        });
    </script>
<?php endif ?>
