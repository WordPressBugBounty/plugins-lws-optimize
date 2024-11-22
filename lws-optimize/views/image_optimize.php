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


// Look up which Cache system is on this hosting. If FastestCache or LWSCache are found, we are on a LWS Hosting
$fastest_cache_status = $_SERVER['HTTP_EDGE_CACHE_ENGINE_ENABLE'] ?? null;
$lwscache_status = $_SERVER['lwscache'] ?? null;

// If not on a LWS Hosting
$lwscache_locked = false;
if ($lwscache_status === null && $fastest_cache_status === null) {
    $lwscache_locked = true;
}

// All options for the convertion modal
$convertion_options = [
    'image_format' => [
        'title' => __('1 - Which image do you wish to convert?', 'lws-optimize'),
        'description' => '',
        'checkboxes' => [
            'jpg' => __('JPEG <b>(recommended)</b>', 'lws-optimize'),
            'png' => __('PNG <b>(not recommended)</b>', 'lws-optimize'),
        ]
    ],

    'convertion_quality' => [
        'title' => __('2 - What quality do you wish for your converted images?', 'lws-optimize'),
        'description' => __('Define the quality of converted images. A lower qualite will result in lower sharpness but reduced size.', 'lws-optimize'),
        'select' => [
            'balanced' => __('Balanced <b>(recommended)</b> <span>64% quality</span>', 'lws-optimize'),
            'low' => __('Low <span>30% quality</span>', 'lws-optimize'),
            'high' => __('High <span>90% quality</span>', 'lws-optimize'),
        ]
    ],
    'image_maxsize' => [
        'title' => __('3 - Do you wish to resize images that are too big?', 'lws-optimize'),
        'description' => __('Define a maximum width to limit image size.', 'lws-optimize'),
        'select' => [
            '2560' => __('2560px <b>(recommended)</b>', 'lws-optimize'),
            '2048' => '2048px',
            '1920' => '1920px',
            '1600' => '1600px',
            '1024' => '1024px',
        ],
        'deactivated' => false
    ],
    'convertion_keeporiginal' => [
        'title' => __('4 - Do you wish to keep original images?', 'lws-optimize'),
        'description' => __('Keeping originals allows you to revert them to the initial type but increase used storage space.', 'lws-optimize'),
        'select' => [
            'keep' => __('Yes <b>(recommended)</b>', 'lws-optimize'),
            'not_keep' => __('No'),
        ]
    ],
];

$autoconvertion_options = [
    'auto_image_format' => [
        'title' => __('1 - Which image do you wish to convert?', 'lws-optimize'),
        'description' => '',
        'checkboxes' => [
            'jpg' => __('JPEG <b>(recommended)</b>', 'lws-optimize'),
            'png' => __('PNG <b>(not recommended)</b>', 'lws-optimize'),
        ]
    ],
    'auto_convertion_quality' => [
        'title' => __('2 - What quality do you wish for your converted images?', 'lws-optimize'),
        'description' => __('Define the quality of converted images. A lower qualite will result in lower sharpness but reduced size.', 'lws-optimize'),
        'select' => [
            'balanced' => __('Balanced <b>(recommended)</b> <span>64% quality</span>', 'lws-optimize'),
            'low' => __('Low <span>30% quality</span>', 'lws-optimize'),
            'high' => __('High <span>90% quality</span>', 'lws-optimize'),
        ]
    ],
    'auto_image_maxsize' => [
        'title' => __('3 - Do you wish to resize images that are too big?', 'lws-optimize'),
        'description' => __('Define a maximum width to limit image size.', 'lws-optimize'),
        'select' => [
            '2560' => __('2560px <b>(recommended)</b>', 'lws-optimize'),
            '2048' => '2048px',
            '1920' => '1920px',
            '1600' => '1600px',
            '1024' => '1024px',
        ],
        'deactivated' => false
    ],
];


$is_imagick = false;
if (class_exists('Imagick')) {
    $is_imagick = true;
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

$current_convertion = get_option('lws_optimize_current_convertion_stats', ['type' => "-", 'original' => "0", 'converted' => "0"]);

$revert_data_left = $GLOBALS['lws_optimize']->lws_optimize_get_revertion_stats();
$revert_data_count = 0;
if (is_array($revert_data_left)) {
    $revert_data_count = count($revert_data_left);
}

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
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Images WebP Convertion Tool', 'lws-optimize'); ?> [BETA]</h2>
</div>

<div class="lwop_beta_cutout">
    <span><?php esc_html_e('Warning: This convertion functionnality is in beta and may not work properly on all websites. Make sure to have a backup at the ready before using it.', 'lws-optimize'); ?></span>
    <ul>
        <?php if (!$max_exec) : ?>
            <li style="font-weight: 500;"><?php echo $execution_time_text . $execution_time_text_resolution; ?></li>
        <?php endif ?>
        <?php if (!$memory_limit) : ?>
            <li style="font-weight: 500;"><?php echo $memory_limit_text . $execution_time_text_resolution; ?></li>
        <?php endif ?>

        <?php if (!defined("DISABLE_WP_CRON") || !DISABLE_WP_CRON) : ?>
            <li>
                <div>
                    <span><?php esc_html_e('Image convertion is a recurring task which may consume a lot of resources for a prolonged time. You are currently using WP-Cron, which means this task will only be executed when there is activity on your website and will use your website resources, slowing it down.', 'lws-optimize'); ?></span> <br>
                    <span><?php esc_html_e('We recommend using a server cron, which will execute tasks at a specified time and without hogging resources, no matter what is happening on your website.', 'lws-optimize'); ?></span>
                    <span>
                        <?php if ($lwscache_locked) {
                            esc_html_e('For more informations on how to setup server crons, contact your hosting provider.', 'lws-optimize');
                        } elseif ($lwscache_status !== null) {
                            esc_html_e('For more informations on how to setup server crons by using the WPManager, follow this ', 'lws-optimize');
                            ?><a href="https://tutoriels.lws.fr/wordpress/wp-manager-de-lws-gerer-son-site-wordpress#Gerer_la_securite_et_les_parametres_generaux_de_votre_site_WordPress_avec_WP_Manager_LWS" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a>
                            <?php
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

<?php if ($memory_limit && $max_exec) : ?>
    <div class="lws_optimize_image_convertion_main first">
        <div class="lws_optimize_image_convertion_main_left">
            <h2 class="lws_optimize_image_convertion_title">
                <span><?php esc_html_e('Convert all images to WebP', 'lws-optimize'); ?></span>
                <button id="lws_optimize_button_refresh_image_convertion" class="lws_optimize_image_convertion_refresh">
                    <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo Refresh" width="15px" height="15px">
                    <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
                </button>
            </h2>
            <div class="lws_optimize_convertion_bar">
                <div class="lws_optimize_convertion_bar_element">
                    <span class="lws_optimize_convertion_bar_element_title">
                        <img id="lws_optimize_convertion_status_icon" src="<?php echo $next_scheduled_all_convert ? esc_url(plugins_url('images/actif.svg', __DIR__)) : esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)); ?>" alt="Logo Status" width="15px" height="15px">
                        <?php echo esc_html__('Status: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_convertion_bar_dynamic_element" id="lws_optimize_convertion_status"><?php echo $next_scheduled_all_convert ? esc_html__('Ongoing', 'lws-optimize') : esc_html__('Inactive', 'lws-optimize'); ?></span>
                </div>
                <div class="lws_optimize_convertion_bar_element">
                    <span class="lws_optimize_convertion_bar_element_title">
                        <img src="<?php echo esc_url(plugins_url('images/horloge.svg', __DIR__)); ?>" alt="Logo Status" width="15px" height="15px">
                        <?php echo esc_html__('Next convertion: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_convertion_bar_dynamic_element" id="lws_optimize_convertion_next"><?php echo $next_scheduled_all_convert ? $next_scheduled_all_convert : ' - '; ?></span>
                </div>
            </div>
        </div>
        <div class="lws_optimize_image_convertion_main_right">
            <span id="lws_optimize_image_convertion_status_text"><?php echo $next_scheduled_all_convert ? esc_html__('Ongoing convertion...', 'lws-optimize'): esc_html(''); ?></span>
            <button type="button" class="lws_optimize_action_button" id="lws_optimize_image_convertion_actionbutton" data-target="#<?php echo $next_scheduled_all_convert ? "lws_optimize_image_stop_convertion_modal" : "lws_optimize_image_convertion_modal"; ?>" data-toggle="modal">
                <?php if ($next_scheduled_all_convert) : ?>
                    <img id="lws_optimize_image_convertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                    <span id="lws_optimize_image_convertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                <?php else : ?>
                    <span id="lws_optimize_image_convertion_text"><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <div class="lws_optimize_convertion_details">
        <div class="lws_optimize_convertion_details_element">
            <img src="<?php echo esc_url(plugins_url('images/type-mime.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
            <span><?php esc_html_e('Convertion format', 'lws-optimize'); ?></span>
            <span id="lws_optimize_convertion_type" class="lws_optimize_convertion_details_dynamic_element"><?php echo esc_html($current_convertion['type'] ?? '-'); ?></span>
        </div>
        <div class="lws_optimize_convertion_details_element">
            <img src="<?php echo esc_url(plugins_url('images/images.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
            <span><?php esc_html_e('Image total', 'lws-optimize'); ?></span>
            <span id="lws_optimize_convertion_max" class="lws_optimize_convertion_details_dynamic_element"><?php echo esc_html($current_convertion['original']) ?? 0; ?></span>
        </div>
        <div class="lws_optimize_convertion_details_element">
            <img src="<?php echo esc_url(plugins_url('images/images_optimisees.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
            <span><?php esc_html_e('Converted images', 'lws-optimize'); ?></span>
            <span id="lws_optimize_convertion_done" class="lws_optimize_convertion_details_dynamic_element"><?php echo esc_html($current_convertion['converted']) ?? 0; ?></span>
        </div>
        <div class="lws_optimize_convertion_details_element">
            <img src="<?php echo esc_url(plugins_url('images/temps.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
            <span><?php esc_html_e('Remaining convertions', 'lws-optimize'); ?></span>
            <span id="lws_optimize_convertion_left" class="lws_optimize_convertion_details_dynamic_element"><?php echo esc_html($current_convertion['original'] - $current_convertion['converted']) ?? 0; ?></span>
        </div>
        <div class="lws_optimize_convertion_details_element">
            <img src="<?php echo esc_url(plugins_url('images/reduction_pourcentage.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
            <span><?php esc_html_e('Total size reduction', 'lws-optimize'); ?></span>
            <span id="lws_optimize_convertion_gains" class="lws_optimize_convertion_details_dynamic_element"><?php echo esc_html($current_convertion['gains'] ?? "0%"); ?></span>
        </div>
    </div>

    <div class="lws_optimize_error_listing">
        <div class="lws_optimize_error_listing_button" id="show_images_converted_action">
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
                            <td><?php echo esc_html($attachment['name'] . "." . $attachment['original_extension']); ?></td>
                            <?php if ($attachment['converted']) : ?>
                                <td><?php echo esc_html($attachment['original_mime'] . " => " . $attachment['mime']); ?></td>
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

    <div class="lws_optimize_image_convertion_main">
        <div class="lws_optimize_image_convertion_main_left">
            <h2 class="lws_optimize_image_convertion_title">
                <span><?php esc_html_e('Restore all images', 'lws-optimize'); ?></span>
                <button id="lws_optimize_button_refresh_image_deconvertion" class="lws_optimize_image_convertion_refresh">
                    <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo Refresh" width="15px" height="15px">
                    <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
                </button>
            </h2>
            <div class="lws_optimize_image_convertion_description">
                <span><?php esc_html_e('Restore all converted images to their original format il the original copy is available.', 'lws-optimize'); ?></span>
                <span><?php esc_html_e('Only works for images not automatically converted on upload (see below)', 'lws-optimize'); ?></span>
            </div>
            <div class="lws_optimize_convertion_bar">
                <div class="lws_optimize_convertion_bar_element">
                    <span class="lws_optimize_convertion_bar_element_title">
                        <img id="lws_optimize_deconvertion_status_icon" src="<?php echo $next_scheduled_deconvert ? esc_url(plugins_url('images/actif.svg', __DIR__)) : esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)); ?>" alt="Logo Status" width="15px" height="15px">
                        <?php echo esc_html__('Status: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_convertion_bar_dynamic_element" id="lws_optimize_deconvertion_status"><?php echo $next_scheduled_deconvert ? esc_html__('Ongoing', 'lws-optimize') : esc_html__('Inactive', 'lws-optimize'); ?></span>
                </div>
                <div class="lws_optimize_convertion_bar_element">
                    <span class="lws_optimize_convertion_bar_element_title">
                        <img src="<?php echo esc_url(plugins_url('images/horloge.svg', __DIR__)); ?>" alt="Logo Horloge" width="15px" height="15px">
                        <?php echo esc_html__('Next deconvertion: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_convertion_bar_dynamic_element" id="lws_optimize_deconvertion_next"><?php echo $next_scheduled_deconvert ? $next_scheduled_deconvert : ' - '; ?></span>
                </div>
                <div class="lws_optimize_convertion_bar_element">
                    <span class="lws_optimize_convertion_bar_element_title">
                        <img src="<?php echo esc_url(plugins_url('images/page.svg', __DIR__)); ?>" alt="Logo Page" width="15px" height="15px">
                        <?php echo esc_html__('Images left: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_convertion_bar_dynamic_element" id="lws_optimize_deconvertion_left"><?php echo esc_html($revert_data_count ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="lws_optimize_image_convertion_main_right">
            <span id="lws_optimize_image_deconvertion_status_text"><?php echo $next_scheduled_deconvert ? esc_html__('Ongoing deconvertion...', 'lws-optimize'): esc_html(''); ?></span>
            <button type="button" class="lws_optimize_action_button" id="lws_optimize_image_deconvertion_actionbutton" data-target="#<?php echo $next_scheduled_deconvert ? "lws_optimize_image_stop_deconvertion_modal" : "lws_optimize_image_deconvertion_modal"; ?>" data-toggle="modal">
                <?php if ($next_scheduled_deconvert) : ?>
                    <img id="lws_optimize_image_deconvertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                    <span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                <?php else : ?>
                    <span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Restore images', 'lws-optimize'); ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <div class="lws_optimize_image_convertion_main">
        <div class="lws_optimize_image_convertion_main_left">
            <h2 class="lws_optimize_image_convertion_title">
                <span><?php esc_html_e('Automatic convertion on upload', 'lws-optimize'); ?></span>
            </h2>
            <div class="lws_optimize_image_convertion_description">
                <span><?php esc_html_e('Automatically convert new images uplaoded on your WordPress website.', 'lws-optimize'); ?></span>
            </div>
        </div>
        <div class="lws_optimize_image_convertion_main_right">
            <button type="button" class="lws_optimize_action_button" id="lws_optimize_image_autoconvertion_actionbutton" data-target="#lws_optimize_image_autoconvertion_modal" data-toggle="modal">
                <span id="lws_optimize_image_autoconvertion_text"><?php esc_html_e('Configurate', 'lws-optimize'); ?></span>
            </button>
            <label class="lwsop_checkbox">
                <input type="checkbox" id="lwsop_image_autoconvertion_check" <?php echo $autoconvert_state == "true" ? esc_attr('checked') : ''; ?>>
                <span class="slider round"></span>
            </label>
        </div>
    </div>

    <?php $media_convertion_values = get_option('lws_optimize_all_media_convertion', []); $media_convertion_values = array_merge(['convertion_keeporiginal' => "keep", 'convertion_quality' => 'balanced', 'image_format' => ['jpg', 'jpeg'], 'image_maxsize' => 2560], $media_convertion_values);?>
    <div class="modal fade" id="lws_optimize_image_convertion_modal" tabindex='-1'>
        <div class="modal-dialog lws_optimize_image_convertion_modal_dialog">
            <div class="modal-content lws_optimize_image_convertion_modal_content">
                <form id="lws_optimize_image_convertion_form" class="lws_optimize_image_convertion_modal_form">
                    <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('WebP convertion options', 'lws-optimize'); ?></h2>
                    <?php foreach ($convertion_options as $option_id => $option) : ?>
                        <span class="lws_optimize_image_convertion_modal_element">
                            <h3 class="lws_optimize_image_convertion_modal_element_title"><?php echo esc_html($option['title']); ?></h3>
                            <span class="lws_optimize_image_convertion_modal_element_description"><?php echo esc_html($option['description']); ?></span>
                            <?php if (isset($option['checkboxes'])) : ?>
                                <span class="lws_optimize_image_convertion_checkbox_block">
                                <?php foreach ($option['checkboxes'] as $checkbox_id => $checkbox) : ?>
                                    <label for="lwsop_image_convertion_checkbox_<?php echo esc_attr($checkbox_id); ?>">
                                        <input type="checkbox" class="lws_optimize_custom_checkboxes" id="lwsop_image_convertion_checkbox_<?php echo esc_attr($checkbox_id); ?>" name="lws_optimize_image_convertion_checkbox_<?php echo esc_html($checkbox_id); ?>" <?php echo in_array($checkbox_id, $media_convertion_values[$option_id]) ? esc_attr("checked") : ""; ?>>
                                        <span><?php echo wp_kses($checkbox, ['b' => [], 'span' => []]); ?></span>
                                        <?php if ($checkbox_id == "png") : ?>
                                            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("PNG with transparency may, in rare cases, lose their transparency after convertion. Use this option knowing the risks or if you do not need transparency.", "lws-optimize"); ?>">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                </span>
                            <?php else : ?>
                                    <div class="lwsop_custom_select image_optimization" id="lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>">
                                        <span id="lws_optimize_image_convertion_select_<?php echo esc_html($option_id); ?>" class="lwsop_custom_option image_optimization">
                                            <div class="custom_option_content image_optimization">
                                                <span class="custom_option_content_text image_optimization" value="<?php echo $media_convertion_values[$option_id]; ?>"><?php echo wp_kses($option['select'][$media_convertion_values[$option_id]], ['b' => [], 'span' => []]); ?></span>
                                                <input type="hidden" id="lws_optimize_image_convertion_select_options_<?php echo esc_html($option_id); ?>" value="<?php echo $media_convertion_values[$option_id]; ?>">
                                            </div>
                                            <img src="<?php echo esc_url(plugins_url('images/chevron_wp_manager.svg', __DIR__)) ?>" alt="chevron" width="12px" height="7px">
                                        </span>
                                        <ul class="lws_op_dropdown image_optimization" id="lws_optimize_image_convertion_select_options_<?php echo esc_attr($option_id); ?>">
                                            <?php foreach ($option['select'] as $select_id => $select) : ?>
                                                <li class="lws_op_dropdown_list image_optimization">
                                                    <span class="lws_op_dropdown_list_content image_optimization" value="<?php echo esc_attr($select_id); ?>" class=""><?php echo wp_kses($select, ['b' => [], 'span' => []]); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <!-- Scripts for the select -->
                                    <script>
                                        document.getElementById('lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>').addEventListener('click', function() {
                                            let dropdown = this;
                                            if (dropdown.classList.contains('active')) {
                                                dropdown.classList.remove('active')
                                            } else {
                                                dropdown.classList.add('active')
                                            }
                                        });

                                        document.addEventListener('click', function(event) {
                                            let dropdown = document.getElementById('lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>');
                                            let target = event.target;
                                            let closest = target.closest('#lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>');
                                            let select_options = ['desktop_option', 'mobile_option'];

                                            // Hide the dropdown menu when clicking somewhere else on the page
                                            if (closest === null && dropdown.classList.contains("active")) {
                                                dropdown.classList.remove('active');
                                            }

                                            // If clicking on one of the options, select it, as a normal select would
                                            if (target.parentNode !== null && target.parentNode.id == "lws_optimize_image_convertion_select_options_<?php echo esc_attr($option_id); ?>") {
                                                document.getElementById("lws_optimize_image_convertion_select_<?php echo esc_html($option_id); ?>").children[0].innerHTML = target.innerHTML + `<input type="hidden" id="lws_optimize_image_convertion_select_options_<?php echo esc_html($option_id); ?>" value="` + target.children[0].getAttribute('value') + `">`;
                                                dropdown.classList.remove('active');
                                            }
                                        });
                                    </script>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>

                    <div class="lws_optimize_modal_button_block">
                        <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                        <button type="button" id="lws_optimize_start_image_convertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Convert images', 'lws-optimize'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php $media_convertion_values = $GLOBALS['lws_optimize']->optimize_options['auto_update'] ?? []; $media_convertion_values = array_merge(['auto_convertion_quality' => 'balanced', 'auto_image_format' => ['jpg', 'jpeg'], 'auto_image_maxsize' => 2560], $media_convertion_values);?>
    <div class="modal fade" id="lws_optimize_image_autoconvertion_modal" tabindex='-1'>
        <div class="modal-dialog lws_optimize_image_convertion_modal_dialog">
            <div class="modal-content lws_optimize_image_convertion_modal_content">
                <form id="lws_optimize_image_autoconvertion_form" class="lws_optimize_image_convertion_modal_form">
                    <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('WebpP autoconvertion options', 'lws-optimize'); ?></h2>
                    <?php foreach ($autoconvertion_options as $option_id => $option) : ?>
                        <span class="lws_optimize_image_convertion_modal_element">
                            <h3 class="lws_optimize_image_convertion_modal_element_title"><?php echo esc_html($option['title']); ?></h3>
                            <span class="lws_optimize_image_convertion_modal_element_description"><?php echo esc_html($option['description']); ?></span>
                            <?php if (isset($option['checkboxes'])) : ?>
                                <span class="lws_optimize_image_convertion_checkbox_block">
                                <?php foreach ($option['checkboxes'] as $checkbox_id => $checkbox) : ?>
                                    <label for="lwsop_image_autoconvertion_checkbox_<?php echo esc_attr($checkbox_id); ?>">
                                        <input type="checkbox" class="lws_optimize_custom_checkboxes" id="lwsop_image_autoconvertion_checkbox_<?php echo esc_attr($checkbox_id); ?>" name="lws_optimize_image_autoconvertion_checkbox_<?php echo esc_html($checkbox_id); ?>" <?php echo in_array($checkbox_id, $media_convertion_values[$option_id]) ? esc_attr("checked") : ""; ?>>
                                        <span><?php echo wp_kses($checkbox, ['b' => [], 'span' => []]); ?></span>
                                        <?php if ($checkbox_id == "png") : ?>
                                            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("PNG with transparency may, in rare cases, lose their transparency after convertion. Use this option knowing the risks or if you do not need transparency.", "lws-optimize"); ?>">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                </span>
                            <?php else : ?>
                                    <div class="lwsop_custom_select image_optimization" id="lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>">
                                        <span id="lws_optimize_image_autoconvertion_select_<?php echo esc_html($option_id); ?>" class="lwsop_custom_option image_optimization">
                                            <div class="custom_option_content image_optimization">
                                                <span class="custom_option_content_text image_optimization" value="<?php echo $media_convertion_values[$option_id]; ?>"><?php echo wp_kses($option['select'][$media_convertion_values[$option_id]], ['b' => [], 'span' => []]); ?></span>
                                                <input type="hidden" id="lws_optimize_image_autoconvertion_select_options_<?php echo esc_html($option_id); ?>" value="<?php echo $media_convertion_values[$option_id]; ?>">
                                            </div>
                                            <img src="<?php echo esc_url(plugins_url('images/chevron_wp_manager.svg', __DIR__)) ?>" alt="chevron" width="12px" height="7px">
                                        </span>
                                        <ul class="lws_op_dropdown image_optimization" id="lws_optimize_image_autoconvertion_select_options_<?php echo esc_attr($option_id); ?>">
                                            <?php foreach ($option['select'] as $select_id => $select) : ?>
                                                <li class="lws_op_dropdown_list image_optimization">
                                                    <span class="lws_op_dropdown_list_content image_optimization" value="<?php echo esc_attr($select_id); ?>" class=""><?php echo wp_kses($select, ['b' => [], 'span' => []]); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <script>
                                        document.getElementById('lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>').addEventListener('click', function() {
                                            let dropdown = this;
                                            if (dropdown.classList.contains('active')) {
                                                dropdown.classList.remove('active')
                                            } else {
                                                dropdown.classList.add('active')
                                            }
                                        });

                                        document.addEventListener('click', function(event) {
                                            let dropdown = document.getElementById('lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>');
                                            let target = event.target;
                                            let closest = target.closest('#lws_optimize_custom_select_<?php echo esc_attr($option_id); ?>');
                                            let select_options = ['desktop_option', 'mobile_option'];

                                            // Hide the dropdown menu when clicking somewhere else on the page
                                            if (closest === null && dropdown.classList.contains("active")) {
                                                dropdown.classList.remove('active');
                                            }

                                            // If clicking on one of the options, select it, as a normal select would
                                            if (target.parentNode !== null && target.parentNode.id == "lws_optimize_image_autoconvertion_select_options_<?php echo esc_attr($option_id); ?>") {
                                                document.getElementById("lws_optimize_image_autoconvertion_select_<?php echo esc_html($option_id); ?>").children[0].innerHTML = target.innerHTML + `<input type="hidden" id="lws_optimize_image_autoconvertion_select_options_<?php echo esc_html($option_id); ?>" value="` + target.children[0].getAttribute('value') + `">`;
                                                dropdown.classList.remove('active');
                                            }
                                        });
                                    </script>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>

                    <div class="lws_optimize_modal_button_block">
                        <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                        <button type="button" id="lws_optimize_start_image_autoconvertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Validate', 'lws-optimize'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lws_optimize_image_deconvertion_modal" tabindex='-1' style="top:12%;">
        <div class="modal-dialog lws_optimize_image_convertion_modal_dialog">
            <div class="modal-content">
                <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('Image restoration confirmation', 'lws-optimize'); ?></h2>
                <div class="lws_optimize_blue_warning_block">
                    <span><?php esc_html_e('Are you sure you want to restore all converted images to their original format? It will replace converted images if their original copy is available.', 'lws-optimize'); ?></span>
                    <span><?php echo wp_kses(__('<b>Warning: </b> This operation will only works for images not converted automatically on upload and whose original copy was conserved.', 'lws-optimize'), ['b' => []]); ?></span>
                </div>
                <div class="lws_optimize_modal_button_block">
                    <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button type="button" id="lws_optimize_start_image_revertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Restore images', 'lws-optimize'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lws_optimize_image_stop_deconvertion_modal" tabindex='-1' style="top:12%;">
        <div class="modal-dialog">
            <div class="modal-content">
                <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('Stop image restoration', 'lws-optimize'); ?></h2>
                <div class="lws_optimize_modal_button_block">
                    <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button type="button" id="lwsop_deactivate_deconvertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Stop restoration', 'lws-optimize'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lws_optimize_image_stop_convertion_modal" tabindex='-1' style="top:12%;">
        <div class="modal-dialog">
            <div class="modal-content">
                <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('Stop image convertion', 'lws-optimize'); ?></h2>
                <div class="lws_optimize_modal_button_block">
                    <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button type="button" id="lwsop_deactivate_convertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Stop convertion', 'lws-optimize'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lws_optimize_image_stop_autoconvertion_modal" tabindex='-1' style="top:12%;">
        <div class="modal-dialog">
            <div class="modal-content">
                <h2 class="lws_optimize_image_convertion_modal_title"><?php esc_html_e('Stop image autoconvertion', 'lws-optimize'); ?></h2>
                <div class="lws_optimize_modal_button_block">
                    <button type="button" class="lws_optimize_modal_close_button" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button type="button" id="lwsop_deactivate_autoconvertion" class="lws_optimize_validate_button"><?php echo esc_html_e('Stop autoconvertion', 'lws-optimize'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        if (document.getElementById('show_images_converted_action') != null) {
            document.getElementById('show_images_converted_action').addEventListener('click', function() {
                // TODO : Ajouter le changement de texte + changer d'un + vers -
                let content = document.getElementById('show_images_converted');
                if (content != null) {
                    content.classList.toggle('hidden');
                    let image = this.children[0];
                    let text = this.children[1];
                    if (content.classList.contains('hidden')) {
                        image.src = "<?php echo esc_url(plugins_url('images/plus.svg', __DIR__)) ?>";
                        text.innerHTML = "<?php esc_html_e('Show converted images', 'lws-optimize'); ?>";
                    } else {
                        image.src = "<?php echo esc_url(plugins_url('images/moins.svg', __DIR__)) ?>";
                        text.innerHTML = "<?php esc_html_e('Hide converted images', 'lws-optimize'); ?>";
                    }
                }
            })
        }
    </script>
    <?php if (get_option('lws_optimize_offline', null) === null) : ?>
        <script>
            // Start the cron to convertion images in the designated type
            if (document.getElementById('lws_optimize_start_image_convertion')) {
                document.getElementById('lws_optimize_start_image_convertion').addEventListener('click', function() {
                    var element = document.getElementById('lws_optimize_image_convertion_actionbutton');
                    if (!element) {
                        jQuery(document.getElementById('lws_optimize_image_convertion_modal')).modal('hide');
                        callPopup('error', "<?php esc_html_e('Failed to start the restoration', 'lws-optimize'); ?>");
                        return -1;
                    }

                    document.body.style.pointerEvents = "none";
                    let old = element.innerHTML;
                    element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;

                    let media_quality = document.getElementById('lws_optimize_image_convertion_select_options_convertion_quality');
                    let media_keepcopy = document.getElementById('lws_optimize_image_convertion_select_options_convertion_keeporiginal');
                    let image_size = document.getElementById('lws_optimize_image_convertion_select_options_image_maxsize');

                    let mimetypes = [];
                    document.querySelectorAll('[id^="lwsop_image_convertion_checkbox_"]').forEach(function(check) {
                        let id = check.id.replace('lwsop_image_convertion_checkbox_', '');
                        if (check.checked) {
                            mimetypes.push(id);
                        }
                    });

                    media_quality = media_quality != null ? media_quality.value : "balanced";
                    media_keepcopy = media_keepcopy != null ? media_keepcopy.value : "keep";
                    image_size = image_size != null ? image_size.value : 2560;


                    let data = {
                        'quality': media_quality,
                        'keepcopy': media_keepcopy,
                        'mimetypes': mimetypes,
                        'size': image_size
                    };

                    jQuery(document.getElementById('lws_optimize_image_convertion_modal')).modal('hide');

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
                            element.innerHTML = old;

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
                                    callPopup('success', "<?php esc_html_e('Image convertion started. It may take a while depending on the amount of files to process.', 'lws-optimize'); ?>");

                                    let convert_check_button = document.getElementById('lws_optimize_button_refresh_image_convertion');
                                    if (convert_check_button != null) {
                                        convert_check_button.dispatchEvent(new Event('click'));
                                    }

                                    let image_status = document.getElementById('lws_optimize_convertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_convertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";
                                    }


                                    let convert_text =document.getElementById('lws_optimize_image_convertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php echo esc_html__('Ongoing convertion...', 'lws-optimize'); ?>";
                                    }

                                    let next_text = document.getElementById('lws_optimize_convertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = data['next'] ?? "-";
                                    }

                                    element.setAttribute('data-target', '#lws_optimize_image_stop_convertion_modal');
                                    element.innerHTML = `
                                        <img id="lws_optimize_image_convertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                                        <span id="lws_optimize_image_convertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                                    `;

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
                            element.innerHTML = old;
                            callPopup("error", "<?php esc_html_e('Failed to start converting images', 'lws-optimize'); ?>");
                            console.log(error);
                            return -1;
                        }
                    });
                });
            }

            if (document.getElementById('lwsop_image_autoconvertion_check')) {
                document.getElementById('lwsop_image_autoconvertion_check').addEventListener('change', function() {
                    let element = this;
                    if (this.checked) {
                        let ajaxRequest = jQuery.ajax({
                            url: ajaxurl,
                            type: "POST",
                            timeout: 120000,
                            context: document.body,
                            data: {
                                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_start_autoconvertion_nonce")); ?>",
                                action: "lwsop_start_autoconvertion",
                            },
                            success: function(data) {
                                element.checked = false;
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
                                        element.checked = true;
                                        callPopup('success', "<?php esc_html_e('The autoconvertion has been started.', 'lws-optimize'); ?>");
                                        break;
                                    default:
                                        callPopup('error', "<?php esc_html_e('Failed to start the autoconvertion', 'lws-optimize'); ?>");
                                        break;
                                }
                            },
                            error: function(error) {
                                element.checked = false;
                                callPopup("error", "<?php esc_html_e('Failed to start the autoconvertion', 'lws-optimize'); ?>");
                                console.log(error);
                                return -1;
                            }
                        });
                    } else {
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
                                element.checked = true;
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
                                        callPopup('success', "<?php esc_html_e('The autoconvertion has been stopped.', 'lws-optimize'); ?>");
                                        break;
                                    default:
                                        callPopup('error', "<?php esc_html_e('Failed to stop the autoconvertion', 'lws-optimize'); ?>");
                                        break;
                                }
                            },
                            error: function(error) {
                                element.checked = true;
                                callPopup("error", "<?php esc_html_e('Failed to stop the autoconvertion', 'lws-optimize'); ?>");
                                console.log(error);
                                return -1;
                            }
                        });
                    }

                    let convert_check_button = document.getElementById('lws_optimize_button_refresh_image_deconvertion');
                    if (convert_check_button != null) {
                        convert_check_button.dispatchEvent(new Event('click'));
                    }
                });
            }

            if (document.getElementById('lws_optimize_start_image_autoconvertion')) {
                document.getElementById('lws_optimize_start_image_autoconvertion').addEventListener('click', function() {
                    let element = document.getElementById('lws_optimize_image_autoconvertion_actionbutton');
                    if (!element) {
                        jQuery(document.getElementById('lws_optimize_image_convertion_modal')).modal('hide');
                        callPopup('error', "<?php esc_html_e('Failed to configurate the restoration', 'lws-optimize'); ?>");
                        return -1;
                    }

                    document.body.style.pointerEvents = "none";
                    let old = element.innerHTML;
                    element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;


                    let media_quality = document.getElementById('lws_optimize_image_autoconvertion_select_options_auto_convertion_quality');
                    let image_size = document.getElementById('lws_optimize_image_autoconvertion_select_options_auto_image_maxsize');

                    let mimetypes = [];
                    document.querySelectorAll('[id^="lwsop_image_autoconvertion_checkbox_"]').forEach(function(check) {
                        let id = check.id.replace('lwsop_image_autoconvertion_checkbox_', '');
                        if (check.checked) {
                            mimetypes.push(id);
                        }
                    });

                    media_quality = media_quality !== null ? media_quality.value : "balanced";
                    image_size = image_size !== null ? image_size.value : 2560;

                    let data = {
                        'quality': media_quality,
                        'mimetypes': mimetypes,
                        'size': image_size
                    };

                    jQuery(document.getElementById('lws_optimize_image_autoconvertion_modal')).modal('hide');

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

                            switch (returnData['code']) {
                                case 'SUCCESS':
                                    callPopup('success', "<?php esc_html_e('Image convertion has been configurated', 'lws-optimize'); ?>");
                                    break;
                                case 'FAILED':
                                default:
                                    console.log(returnData);
                                    callPopup('error', "<?php esc_html_e('Failed to configurate', 'lws-optimize'); ?>");
                                    break;
                            }
                        },
                        error: function(error) {
                            element.innerHTML = old;
                            document.body.style.pointerEvents = "all";

                            console.log(error);
                            callPopup("error", "<?php esc_html_e('Failed to configurate', 'lws-optimize'); ?>");
                            return -1;
                        }
                    });
                });
            }

            if (document.getElementById('lwsop_deactivate_convertion') != null) {
                document.getElementById('lwsop_deactivate_convertion').addEventListener('click', function() {
                    var element = document.getElementById('lws_optimize_image_convertion_actionbutton');
                    if (!element) {
                        jQuery(document.getElementById('lws_optimize_image_stop_convertion_modal')).modal('hide');
                        callPopup('error', "<?php esc_html_e('Failed to stop the convertion', 'lws-optimize'); ?>");
                        return -1;
                    }

                    jQuery(document.getElementById('lws_optimize_image_stop_convertion_modal')).modal('hide');

                    document.body.style.pointerEvents = "none";
                    let old = element.innerHTML;
                    element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;

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

                                    let convert_text =document.getElementById('lws_optimize_image_convertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    let image_status = document.getElementById('lws_optimize_convertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_convertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }


                                    let next_text = document.getElementById('lws_optimize_convertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = "-";
                                    }

                                    element.setAttribute('data-target', '#lws_optimize_image_convertion_modal');
                                    element.innerHTML = `<span id="lws_optimize_image_convertion_text"><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>`;
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

            // Deactivate the deconvertion
            if (document.getElementById('lwsop_deactivate_deconvertion') != null) {
                document.getElementById('lwsop_deactivate_deconvertion').addEventListener('click', function() {
                    var element = document.getElementById('lws_optimize_image_deconvertion_actionbutton');
                    if (!element) {
                        jQuery(document.getElementById('lws_optimize_image_stop_deconvertion_modal')).modal('hide');
                        callPopup('error', "<?php esc_html_e('Failed to stop the restoration', 'lws-optimize'); ?>");
                        return -1;
                    }

                    jQuery(document.getElementById('lws_optimize_image_stop_deconvertion_modal')).modal('hide');

                    document.body.style.pointerEvents = "none";
                    let old = element.innerHTML;
                    element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;

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
                                    let image_status = document.getElementById('lws_optimize_deconvertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_deconvertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }

                                    let convert_text =document.getElementById('lws_optimize_image_deconvertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    let next_text = document.getElementById('lws_optimize_deconvertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = "-";
                                    }

                                    element.setAttribute('data-target', '#lws_optimize_image_deconvertion_modal');
                                    element.innerHTML = `<span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Restore images', 'lws-optimize'); ?></span>`;
                                    callPopup('success', "<?php esc_html_e('The restoration has been stopped.', 'lws-optimize'); ?>");
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

            // Start the cron for the deconvertion of all medias
            if (document.getElementById('lws_optimize_start_image_revertion') != null) {
                document.getElementById('lws_optimize_start_image_revertion').addEventListener('click', function() {
                    var element = document.getElementById('lws_optimize_image_deconvertion_actionbutton');
                    if (!element) {
                        jQuery(document.getElementById('lws_optimize_image_deconvertion_modal')).modal('hide');
                        callPopup('error', "<?php esc_html_e('Failed to start the restoration', 'lws-optimize'); ?>");
                        return -1;
                    }

                    document.body.style.pointerEvents = "none";
                    let old = element.innerHTML;
                    element.innerHTML = `<div class="loading_animation"><img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="30px" height="auto"></div>`;
                    jQuery(document.getElementById('lws_optimize_image_deconvertion_modal')).modal('hide');

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
                            document.body.style.pointerEvents = "all";
                            element.innerHTML = old;

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
                                    let image_status = document.getElementById('lws_optimize_deconvertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_deconvertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";
                                    }

                                    let next_text = document.getElementById('lws_optimize_deconvertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = data['next_deconvert'] ?? "-";
                                    }

                                    let deconvert_text =document.getElementById('lws_optimize_image_deconvertion_status_text');
                                    if (deconvert_text) {
                                        deconvert_text.innerHTML = "<?php echo esc_html__('Ongoing deconvertion...', 'lws-optimize'); ?>";
                                    }

                                    let deconvert_amount = document.getElementById('lws_optimize_deconvertion_left');
                                    if (deconvert_amount) {
                                        deconvert_amount.innerHTML = parseInt(data);
                                    }

                                    callPopup('success', "<?php esc_html_e('All images are getting reverted. It may take a few moments.', 'lws-optimize'); ?>");

                                    element.setAttribute('data-target', '#lws_optimize_image_stop_deconvertion_modal');
                                    element.innerHTML = `
                                        <img id="lws_optimize_image_deconvertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                                        <span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>`;
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
                let button = this;
                if (button.getAttribute('value') == "occupied") {
                    return -1;
                }

                button.setAttribute('value', "occupied");

                let old_text = button.innerHTML;
                button.innerHTML = `
                <span name="loading">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="chargement" width="18px" height="18px">
                </span>`;

                button.disabled = true;


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
                        button.setAttribute('value', "");

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

                                let type = document.getElementById('lws_optimize_convertion_type');
                                let next = document.getElementById('lws_optimize_convertion_next');
                                let done = document.getElementById('lws_optimize_convertion_done');
                                let max = document.getElementById('lws_optimize_convertion_max');
                                let left = document.getElementById('lws_optimize_convertion_left');
                                let listing = document.getElementById('show_images_converted_tbody');

                                let button_element = document.getElementById('lws_optimize_image_convertion_actionbutton');
                                let button_element_deconvert = document.getElementById('lws_optimize_image_deconvertion_actionbutton');

                                // Checks for the Convertion
                                if (data['status'] !== null && data['status'] == true) {
                                    let image_status = document.getElementById('lws_optimize_convertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_convertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";
                                    }

                                    let convert_text = document.getElementById('lws_optimize_image_convertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php esc_html_e('Ongoing convertion...', 'lws-optimize'); ?>";
                                    }

                                    let next_text = document.getElementById('lws_optimize_convertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = data['next'] ?? "-";
                                    }

                                    if(button_element) {
                                        button_element.setAttribute('data-target', '#lws_optimize_image_stop_convertion_modal');
                                        button_element.innerHTML = `
                                            <img id="lws_optimize_image_convertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                                            <span id="lws_optimize_image_convertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                                        `;
                                    }


                                    // Show the image restoration deactivated
                                    let image_status_deconvert = document.getElementById('lws_optimize_deconvertion_status_icon');
                                    let text_status_deconvert = document.getElementById('lws_optimize_deconvertion_status');
                                    let button_element_deconvertion = document.getElementById('lws_optimize_image_deconvertion_actionbutton');

                                    if (image_status_deconvert) {
                                        image_status_deconvert.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status_deconvert) {
                                        text_status_deconvert.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }

                                    let deconvert_text = document.getElementById('lws_optimize_image_deconvertion_status_text');
                                    if (deconvert_text) {
                                        deconvert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    let deconvert_next_text = document.getElementById('lws_optimize_deconvertion_next');
                                    if (deconvert_next_text) {
                                        deconvert_next_text.innerHTML = "-";
                                    }

                                    button_element_deconvertion.setAttribute('data-target', '#lws_optimize_image_deconvertion_modal');
                                    button_element_deconvertion.innerHTML = `<span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Restore images', 'lws-optimize'); ?></span>`;


                                } else {
                                    let image_status = document.getElementById('lws_optimize_convertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_convertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }

                                    let convert_text = document.getElementById('lws_optimize_image_convertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    if(button_element) {
                                        button_element.setAttribute('data-target', '#lws_optimize_image_convertion_modal');
                                        button_element.innerHTML = `<span id="lws_optimize_image_convertion_text"><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>`;
                                    }
                                }

                                // Checks for the Deconvertion
                                if (data['status_revert'] !== null && data['status_revert'] == true) {
                                    let image_status = document.getElementById('lws_optimize_deconvertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_deconvertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";
                                    }

                                    let deconvert_text = document.getElementById('lws_optimize_image_deconvertion_status_text');
                                    if (deconvert_text) {
                                        deconvert_text.innerHTML = "<?php esc_html_e('Ongoing deconvertion...', 'lws-optimize'); ?>";
                                    }

                                    let next_text = document.getElementById('lws_optimize_deconvertion_next');
                                    if (next_text) {
                                        next_text.innerHTML = data['next_deconvert'] ?? "-";
                                    }

                                    if(button_element_deconvert) {
                                        button_element_deconvert.setAttribute('data-target', '#lws_optimize_image_stop_deconvertion_modal');
                                        button_element_deconvert.innerHTML = `
                                            <img id="lws_optimize_image_convertion_image" src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                                            <span id="lws_optimize_image_convertion_text"><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                                        `;
                                    }


                                    // Show the image convertion deactivated
                                    let image_status_convert = document.getElementById('lws_optimize_convertion_status_icon');
                                    let text_status_convert = document.getElementById('lws_optimize_convertion_status');
                                    let button_element_convertion = document.getElementById('lws_optimize_image_convertion_actionbutton');

                                    if (image_status_convert) {
                                        image_status_convert.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status_convert) {
                                        text_status_convert.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }

                                    let convert_text = document.getElementById('lws_optimize_image_convertion_status_text');
                                    if (convert_text) {
                                        convert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    let convert_next_text = document.getElementById('lws_optimize_convertion_next');
                                    if (convert_next_text) {
                                        convert_next_text.innerHTML = "-";
                                    }

                                    button_element_convertion.setAttribute('data-target', '#lws_optimize_image_convertion_modal');
                                    button_element_convertion.innerHTML = `<span id="lws_optimize_image_convertion_text"><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>`;
                                } else {
                                    let image_status = document.getElementById('lws_optimize_deconvertion_status_icon');
                                    let text_status = document.getElementById('lws_optimize_deconvertion_status');

                                    if (image_status) {
                                        image_status.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>"
                                    }
                                    if (text_status) {
                                        text_status.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";
                                    }

                                    let deconvert_text = document.getElementById('lws_optimize_image_deconvertion_status_text');
                                    if (deconvert_text) {
                                        deconvert_text.innerHTML = "<?php echo esc_html(''); ?>";
                                    }

                                    if(button_element_deconvert) {
                                        button_element_deconvert.setAttribute('data-target', '#lws_optimize_image_deconvertion_modal');
                                        button_element_deconvert.innerHTML = `<span id="lws_optimize_image_deconvertion_text"><?php esc_html_e('Restore images', 'lws-optimize'); ?></span>`;
                                    }
                                }

                                let total_gains = document.getElementById('lws_optimize_convertion_gains');
                                if (total_gains) {
                                    total_gains.innerHTML = data['gains'] ?? '0%';
                                }


                                let deconvert_amount = document.getElementById('lws_optimize_deconvertion_left');
                                if (deconvert_amount) {
                                    deconvert_amount.innerHTML = parseInt(data['deconvert_left']);
                                }

                                if (type) {
                                    type.innerHTML = data['convert_type'] ?? '-';
                                }
                                if (next) {
                                    next.innerHTML = data['next'];
                                }
                                if (done) {
                                    done.innerHTML = data['done'];
                                }
                                if (max) {
                                    max.innerHTML = data['max'];
                                }
                                if (left) {
                                    left.innerHTML = data['left'];
                                }

                                if (listing) {
                                    let data_listing = data['listing'] ?? [];
                                    listing.innerHTML = '';
                                    for (i in data_listing) {
                                        if (data_listing[i]['converted']) {
                                            listing.insertAdjacentHTML('afterbegin', `
                                        <tr>
                                            <td>` + data_listing[i]['name'] + "." + data_listing[i]['original_extension'] + `</td>
                                            <td>` + data_listing[i]['original_mime'] + " => " + data_listing[i]['mime'] + `</td>
                                            <td><?php echo esc_html__('Done', 'lws-optimize'); ?></td>
                                            <td>` + (new Date(data_listing[i]['date_convertion'] * 1000).toLocaleString()).replaceAll('/', '-') + `</td>
                                            <td>` + (data_listing[i]['compression'] ?? 0) + `</td>
                                        </tr>
                                        `);
                                        } else {
                                            listing.insertAdjacentHTML('afterbegin', `
                                        <tr>
                                            <td>` + data_listing[i]['name'] + "." + data_listing[i]['original_extension'] + `</td>
                                            <td>` + data_listing[i]['original_mime'] + `</td>
                                            <td><?php echo esc_html__('Pending', 'lws-optimize'); ?></td>
                                            <td>/</td>
                                            <td>/</td>
                                        </tr>
                                        `);
                                        }
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
                        button.setAttribute('value', "");
                        console.log(error);
                        return -1;
                    }
                });
            }

            let convert_check_button = document.getElementById('lws_optimize_button_refresh_image_convertion');
            if (convert_check_button != null) {
                convert_check_button.addEventListener('click', lws_op_update_convertion_info);

                setInterval(function() {
                    convert_check_button.dispatchEvent(new Event('click'));
                }, 60000);
            }

            let deconvert_check_button = document.getElementById('lws_optimize_button_refresh_image_deconvertion');
            if (deconvert_check_button != null) {
                deconvert_check_button.addEventListener('click', lws_op_update_convertion_info);

                setInterval(function() {
                    deconvert_check_button.dispatchEvent(new Event('click'));
                }, 60000)
            }

            jQuery(document).ready(function() {
                jQuery('[data-toggle="tooltip"]').tooltip();
            });
        </script>
    <?php endif ?>
<?php else : ?>
    <div class="lws_optimize_php_not_ok">
        <?php esc_html_e('Image convertion cannot be used with your current PHP configuration', 'lws-optimize'); ?>
    </div>
<?php endif; ?>