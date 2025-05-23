<?php
$conversion_options = [
    // 'conversion_type' => [
    //     'title' => __('How would you like to convert your images?', 'lws-optimize'),
    //     'description' => __('Select your preferred conversion method', 'lws-optimize'),
    //     'select' => [
    //         'api' => __('API <b>(recommended)</b> <span>Faster and more efficient but uses credits</span>', 'lws-optimize'),
    //         'local' => __('Local <span>Uses server resources, may impact performance but costs no credits</span>', 'lws-optimize'),
    //     ]
    // ],
    'conversion_quality' => [
        'title' => __('What quality do you wish for your converted images?', 'lws-optimize'),
        'description' => __('Define the quality of converted images. A lower qualite will result in lower sharpness but reduced size.', 'lws-optimize'),
        'select' => [
            'balanced' => __('Balanced <b>(recommended)</b> <span>64% quality</span>', 'lws-optimize'),
            'low' => __('Low <span>30% quality</span>', 'lws-optimize'),
            'high' => __('High <span>90% quality</span>', 'lws-optimize'),
        ]
    ],
    'image_maxsize' => [
        'title' => __('Do you wish to resize images that are too big?', 'lws-optimize'),
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

// Check for Imagick extension support
$imagick_available = extension_loaded('imagick');
$avif_support = false;
$webp_support = false;

if ($imagick_available) {
    $imagick = new Imagick();
    $formats = $imagick->queryFormats();
    $avif_support = in_array('AVIF', $formats);
    $webp_support = in_array('WEBP', $formats);
}

// Format support info
$format_support = [
    'gd' => [
        'available' => function_exists('gd_info'),
        'webp' => isset(gd_info()['WebP Support']) ? gd_info()['WebP Support'] : false,
        'avif' => isset(gd_info()['AVIF Support']) ? gd_info()['AVIF Support'] : false
    ],
    'imagick' => [
        'available' => $imagick_available,
        'webp' => $webp_support,
        'avif' => $avif_support
    ]
];
?>

<div id="lwsop_imagepro_loading_overlay" class="lwsop_loading_overlay">
    <div class="lwsop_loading_spinner"></div>
</div>

<div class="lwsop_bluebanner_alt">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Images Conversion Tool', 'lws-optimize'); ?></h2>
    <div class="lwsop_bluebanner_subtitle">
    <?php esc_html_e('Convert your images to modern formats (WebP/AVIF) to significantly improve website loading speed.', 'lws-optimize'); ?>
    </div>
</div>

<div class="lwop_compatibility_alerts">
    <?php if ($format_support['gd']['avif'] == false && $format_support['gd']['webp'] == false) : ?>
        <div class="lwop_alert lwop_alert_warning">
            <i class="dashicons dashicons-warning"></i>
            <?php esc_html_e('Your server cannot create WebP or AVIF image thumbnails. JPEG will be used instead.', 'lws-optimize'); ?>
            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Your server's GDImage library doesn't support AVIF or WebP formats. WordPress uses this library to generate thumbnails of your images, so they will be created in JPEG format instead.", "lws-optimize"); ?>">
        </div>
    <?php else : ?>
        <?php if ($format_support['gd']['avif'] == false) : ?>
            <div class="lwop_alert lwop_alert_warning">
                <i class="dashicons dashicons-warning"></i>
                <?php esc_html_e('Your server cannot create AVIF thumbnails. JPEG will be used instead for thumbnails.', 'lws-optimize'); ?>
                <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Your server's GDImage library doesn't support AVIF format. Since WordPress uses this library to generate thumbnail versions of your images, AVIF thumbnails will be created as JPEG instead.", "lws-optimize"); ?>">
            </div>
        <?php elseif ($format_support['gd']['webp'] == false) : ?>
            <div class="lwop_alert lwop_alert_warning">
                <i class="dashicons dashicons-info-outline"></i>
                <?php esc_html_e('Your server cannot create WebP thumbnails. JPEG will be used instead for thumbnails.', 'lws-optimize'); ?>
                <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Your server's GDImage library doesn't support WebP format. Since WordPress uses this library to generate thumbnail versions of your images, WebP thumbnails will be created as JPEG instead.", "lws-optimize"); ?>">
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($format_support['imagick']['available'] == false) : ?>
        <div class="lwop_alert lwop_alert_error">
            <i class="dashicons dashicons-dismiss"></i>
            <?php esc_html_e('Standard image conversion is not available on your server.', 'lws-optimize'); ?>
            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("The Imagick PHP extension is not installed on your server. This extension is required for local image conversion, which will be unavailable.", "lws-optimize"); ?>">
        </div>
    <?php elseif ($format_support['imagick']['webp'] == false) : ?>
        <div class="lwop_alert lwop_alert_error">
            <i class="dashicons dashicons-dismiss"></i>
            <?php esc_html_e('Standard image conversion is not available on your server.', 'lws-optimize'); ?>
            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Your server's Imagick library doesn't support WebP format. This library is required for local image conversion, which will be unavailable.", "lws-optimize"); ?>">
        </div>
    <?php endif; ?>
</div>

<div class="lws_optimize_image_conversion_main first">
    <div class="lws_optimize_image_conversion_main_left alt">
        <div class="lws_optimize_conversion_bar">
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img id="lwsoppro_image_conversion_status" alt="Logo Status" width="15px" height="15px">
                    <?php echo esc_html__('Status: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_conversion_status">-</span>
            </div>
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img src="<?php echo esc_url(plugins_url('images/horloge.svg', __DIR__)); ?>" alt="Logo Horloge" width="15px" height="15px">
                    <?php echo esc_html__('Next conversion: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_conversion_next">-</span>
            </div>
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img src="<?php echo esc_url(plugins_url('images/credit.svg', __DIR__)); ?>" alt="Logo crédits" width="15px" height="15px">
                    <?php echo esc_html__('Remaining credits: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_conversion_credits">-</span>
                <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('Credits are used to convert images with our API. You get 2000 free credits to start with. Additional credits will soon be purchasable on our website.', 'lws-optimize'); ?>">
            </div>
        </div>

        <button onclick="refreshPage(this)" class="lws_optimize_image_conversion_refresh">
            <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo Refresh" width="15px" height="15px">
            <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
        </button>
    </div>

    <div id="lwsoppro_conversion_button_block" class="lws_optimize_image_conversion_main_right">
        <button type="button" class="lws_optimize_action_button">
                <span><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>
        </button>
    </div>
</div>

<div class="lws_optimize_conversion_details">
    <div class="lws_optimize_conversion_details_element">
        <img src="<?php echo esc_url(plugins_url('images/type-mime.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
        <span><?php esc_html_e('Conversion format', 'lws-optimize'); ?></span>
        <span id="lwsoppro_conversion_type" class="lws_optimize_conversion_details_dynamic_element">-</span>
    </div>
    <div class="lws_optimize_conversion_details_element">
        <img src="<?php echo esc_url(plugins_url('images/images.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
        <span><?php esc_html_e('Image total', 'lws-optimize'); ?></span>
        <span id="lwsoppro_conversion_max" class="lws_optimize_conversion_details_dynamic_element">-</span>
    </div>
    <div class="lws_optimize_conversion_details_element">
        <img src="<?php echo esc_url(plugins_url('images/images_optimisees.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
        <span><?php esc_html_e('Converted images', 'lws-optimize'); ?></span>
        <span id="lwsoppro_conversion_done" class="lws_optimize_conversion_details_dynamic_element">-</span>
    </div>
    <div class="lws_optimize_conversion_details_element">
        <img src="<?php echo esc_url(plugins_url('images/temps.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
        <span><?php esc_html_e('Remaining conversions', 'lws-optimize'); ?></span>
        <span id="lwsoppro_conversion_left" class="lws_optimize_conversion_details_dynamic_element">-</span>
    </div>
    <div class="lws_optimize_conversion_details_element">
        <img src="<?php echo esc_url(plugins_url('images/reduction_pourcentage.svg', __DIR__)); ?>" alt="Logo Mime-Type" width="60px" height="60px">
        <span><?php esc_html_e('Total size reduction', 'lws-optimize'); ?></span>
        <span id="lwsoppro_conversion_gains" class="lws_optimize_conversion_details_dynamic_element">-</span>
    </div>
</div>

<div class="lws_optimize_error_listing">
    <div class="lws_optimize_error_listing_button" id="lwsoppro_show_image_listing" onclick="changeStateTable(this)">
        <img src="<?php echo esc_url(plugins_url('images/plus.svg', __DIR__)) ?>" alt="Logo Plus" width="15px" height="15px">
        <span><?php esc_html_e('Show converted images', 'lws-optimize'); ?></span>
    </div>

    <div class="lwsop_contentblock_error_listing hidden">
        <table class="lwsop_error_listing">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'lws-optimize'); ?></th>
                    <th><?php esc_html_e('Type', 'lws-optimize'); ?></th>
                    <th><?php esc_html_e('Converted', 'lws-optimize'); ?></th>
                    <th><?php esc_html_e('Converted Type', 'lws-optimize'); ?></th>
                    <th><?php esc_html_e('Compression', 'lws-optimize'); ?></th>
                </tr>
            </thead>
            <tbody id="lwsoppro_tbody_listing"></tbody>
        </table>
    </div>
</div>

<div class="lws_optimize_image_conversion_main">
    <div class="lws_optimize_image_conversion_main_left">
        <h2 class="lws_optimize_image_conversion_title">
            <span><?php esc_html_e('Restore all images', 'lws-optimize'); ?></span>
            <button onclick="refreshPage(this)" class="lws_optimize_image_conversion_refresh">
                <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo Refresh" width="15px" height="15px">
                <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
            </button>
        </h2>

        <div class="lws_optimize_image_conversion_description">
            <span><?php esc_html_e('Restore all converted images to their original format il the original copy is available.', 'lws-optimize'); ?></span>
            <span><?php esc_html_e('Only works for images not automatically converted on upload (see below)', 'lws-optimize'); ?></span>
        </div>

        <div class="lws_optimize_conversion_bar">
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img id="lwsoppro_image_deconversion_status" alt="Logo Status" width="15px" height="15px">
                    <?php echo esc_html__('Status: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_deconversion_status">-</span>
            </div>
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img src="<?php echo esc_url(plugins_url('images/horloge.svg', __DIR__)); ?>" alt="Logo Horloge" width="15px" height="15px">
                    <?php echo esc_html__('Next deconversion: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_deconversion_next">-</span>
            </div>
            <div class="lws_optimize_conversion_bar_element">
                <span class="lws_optimize_conversion_bar_element_title">
                    <img src="<?php echo esc_url(plugins_url('images/page.svg', __DIR__)); ?>" alt="Logo Page" width="15px" height="15px">
                    <?php echo esc_html__('Images left: ', 'lws-optimize'); ?>
                </span>
                <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsoppro_deconversion_left">-</span>
            </div>
        </div>
    </div>

    <div id="lwsoppro_deconversion_button_block" class="lws_optimize_image_conversion_main_right">
        <button type="button" class="lws_optimize_action_button">
                <span><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>
        </button>
    </div>
</div>

<div class="lws_optimize_image_conversion_main">
    <div class="lws_optimize_image_conversion_main_left">
        <h2 class="lws_optimize_image_conversion_title">
            <span><?php esc_html_e('Automatic conversion on upload', 'lws-optimize'); ?></span>
        </h2>
        <div class="lws_optimize_image_conversion_description">
            <span><?php esc_html_e('Automatically convert new images uploaded on your WordPress website.', 'lws-optimize'); ?></span>
        </div>
    </div>
    <div class="lws_optimize_image_conversion_main_right">
        <label class="lwsop_checkbox">
            <input type="checkbox" id="lwsoppro_image_autoconversion_check" onchange="checkAutoupload(this)">
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="modal fade" id="lwsoppro_modal" tabindex='-1'>
    <div class="modal-dialog lws_optimize_image_conversion_modal_dialog">
        <div id="lwsoppro_modal_content" class="modal-content lws_optimize_image_conversion_modal_content"></div>
    </div>
</div>

<script>
    function showLoading() {
        let loading_overlay = document.getElementById('lwsop_imagepro_loading_overlay');
        if (loading_overlay) {
            loading_overlay.style.display = 'flex';
        }
    }

    function hideLoading() {
        let loading_overlay = document.getElementById('lwsop_imagepro_loading_overlay');
        if (loading_overlay) {
            loading_overlay.style.display = 'none';
        }
    }

    function changeStateTable(element) {
        let content = element.nextElementSibling;
        content.classList.toggle('hidden');

        if (content.classList.contains('hidden')) {
            element.innerHTML = `
                <img src="<?php echo esc_url(plugins_url('images/plus.svg', __DIR__)) ?>" alt="Logo Plus" width="15px" height="15px">
                <span><?php esc_html_e('Show converted images', 'lws-optimize'); ?></span>
            `;
        } else {
            element.innerHTML = `
                <img src="<?php echo esc_url(plugins_url('images/moins.svg', __DIR__)) ?>" alt="Logo Plus" width="15px" height="15px">
                <span><?php esc_html_e('Hide converted images', 'lws-optimize'); ?></span>
            `;
        }
    }

    function refreshPage(button) {
        showLoading();
        // Disable the button and show loading animation
        let originalText = '';
        if (button) {
            originalText = button.innerHTML;
            button.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
        }

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 60000,
            context: document.body,
            data: {
                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_image_conversion_data_fetch")); ?>",
                action: "lws_optimize_image_conversion_data_fetch",
            },
            success: function(data) {
                if (button) {
                    button.innerHTML = originalText;
                }

                try {
                    JSON.parse(data);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                    return -1;
                }

                let response = JSON.parse(data);

                switch (response['code']) {
                    case 'SUCCESS':
                        actionRefreshPage(response['data']);
                        break;
                    default:
                        callPopup("error", "<?php esc_html_e('An error occured and data could not be fetched. Please try again.', 'lws-optimize'); ?>");
                        break;
                }

                hideLoading();
            },
            error: function(error) {
                if (button) {
                    button.innerHTML = originalText;
                }

                callPopup("error", "<?php esc_html_e('An error occured and data could not be fetched. Please try again.', 'lws-optimize'); ?>");
                hideLoading();
            }
        });
    }

    function actionRefreshPage(data) {
        // Get the page body
        let page_body = document.getElementById('post-body-image_optimize_pro');

        // Get the elements managing the (de)conversion status
        let conversion_status_image = document.getElementById('lwsoppro_image_conversion_status');
        let conversion_status_text = document.getElementById('lwsoppro_conversion_status');
        let deconversion_status_image = document.getElementById('lwsoppro_image_deconversion_status');
        let deconversion_status_text = document.getElementById('lwsoppro_deconversion_status');

        // Get the elements managing the next (de)conversion date
        let conversion_next = document.getElementById('lwsoppro_conversion_next');
        let deconversion_next = document.getElementById('lwsoppro_deconversion_next');

        // Get the element managing the number of images left to deconvert
        let deconversion_left = document.getElementById('lwsoppro_deconversion_left');

        // Get the elements managing the stats
        let conversion_type = document.getElementById('lwsoppro_conversion_type');
        let conversion_max = document.getElementById('lwsoppro_conversion_max');
        let conversion_done = document.getElementById('lwsoppro_conversion_done');
        let conversion_left = document.getElementById('lwsoppro_conversion_left');
        let conversion_gains = document.getElementById('lwsoppro_conversion_gains');

        // Get the elements where the buttons will be generated
        let conversion_button_block = document.getElementById('lwsoppro_conversion_button_block');
        let deconversion_button_block = document.getElementById('lwsoppro_deconversion_button_block');

        // Get the checkbox for the automatic conversion
        let autoconversion_checkbox = document.getElementById('lwsoppro_image_autoconversion_check');

        // Get the modal and the content of the modal
        let conversion_modal = document.getElementById('lwsoppro_modal');
        let conversion_modal_content = document.getElementById('lwsoppro_modal_content');

        // Get the elements managing the images listing
        let conversion_listing_button = document.getElementById('lwsoppro_show_image_listing');
        let conversion_listing = document.getElementById('lwsoppro_tbody_listing');

        let credits_left = document.getElementById('lwsoppro_conversion_credits');

        // Check the status of the conversion
        if (conversion_status_image && conversion_status_text) {
            if (data['conversion_status']) {
                // conversion is ON
                conversion_status_image.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>";
                conversion_status_text.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";

                conversion_button_block.innerHTML = `
                    <span><?php echo esc_html__('Ongoing conversion...', 'lws-optimize'); ?></span>
                    <button type="button" class="lws_optimize_action_button" data-target="#lwsoppro_modal" data-toggle="modal" value="unconvert" onclick="loadModale(this.value)">
                        <img src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                        <span><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                    </button>
                `;
            } else {
                // conversion is OFF
                conversion_status_image.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>";
                conversion_status_text.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";

                conversion_button_block.innerHTML = `
                    <button type="button" class="lws_optimize_action_button" data-target="#lwsoppro_modal" data-toggle="modal" value="convert" onclick="loadModale(this.value)">
                        <span><?php esc_html_e('Convert images', 'lws-optimize'); ?></span>
                    </button>
                `;
            }
        }
        // Check the status of the deconversion and update the buttons
        if (deconversion_status_image && deconversion_status_text) {
            if (data['deconversion_status']) {
                // Deconversion is ON
                deconversion_status_image.src = "<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>";
                deconversion_status_text.innerHTML = "<?php echo esc_html__('Ongoing', 'lws-optimize'); ?>";

                deconversion_button_block.innerHTML = `
                    <span><?php echo esc_html__('Ongoing deconversion...', 'lws-optimize'); ?></span>
                    <button type="button" class="lws_optimize_action_button" data-target="#lwsoppro_modal" data-toggle="modal" value="unreverse" onclick="loadModale(this.value)">
                        <img src="<?php echo esc_url(plugins_url('images/arreter.svg', __DIR__)) ?>" alt="Logo Stop" width="15px" height="15px">
                        <span><?php esc_html_e('Stop', 'lws-optimize'); ?></span>
                    </button>
                `;
            } else {
                // Deconversion is OFF
                deconversion_status_image.src = "<?php echo esc_url(plugins_url('images/erreur-inactif.svg', __DIR__)) ?>";
                deconversion_status_text.innerHTML = "<?php echo esc_html__('Inactive', 'lws-optimize'); ?>";

                deconversion_button_block.innerHTML = `
                    <button type="button" class="lws_optimize_action_button" data-target="#lwsoppro_modal" data-toggle="modal" value="reverse" onclick="loadModale(this.value)">
                        <span><?php esc_html_e('Restore images', 'lws-optimize'); ?></span>
                    </button>
                `;
            }
        }

        // Check the next scheduled date for the conversion
        if (conversion_next) {
            conversion_next.innerHTML = data['next_conversion'] ? new Date(data['next_conversion'] * 1000).toLocaleString() : "-";
        }
        // Check the next scheduled date for the deconversion
        if (deconversion_next) {
            deconversion_next.innerHTML = data['next_deconversion'] ? new Date(data['next_deconversion'] * 1000).toLocaleString() : "-";
        }

        // Check the number of images left to deconvert
        if (deconversion_left) {
            deconversion_left.innerHTML = data['images_converted'] ? data['images_converted'] : "0";
        }

        // conversion type is fixed
        if (conversion_type) {
            conversion_type.innerHTML = "WebP/AVIF";
        }

        // Check the number of images to convert
        if (conversion_max) {
            conversion_max.innerHTML = data['images_to_convert'] ? data['images_to_convert'] : "0";
        }

        // Check the number of images converted
        if (conversion_done) {
            conversion_done.innerHTML = data['images_converted'] ? data['images_converted'] : "0";
        }

        // Check the number of images left to convert
        if (conversion_left) {
            conversion_left.innerHTML = data['images_left_to_convert'] ? data['images_left_to_convert'] : "0";
        }

        // Check the total size reduction
        if (conversion_gains) {
            conversion_gains.innerHTML = (data['size_reduction'] ? parseFloat(data['size_reduction']).toFixed(2) + "%" : "0%") + (data['size_reduction_num'] ? " | " + (data['size_reduction_num']) : '0');
        }

        // Check if autoconversion is active
        if (autoconversion_checkbox) {
            autoconversion_checkbox.checked = data['autoconversion_status'] == true ? true : false;
        }

        // Add the images to the Table
        if (conversion_listing) {
            // Destroy the DataTable instance if it exists
            if (jQuery('.lwsop_error_listing').length && jQuery.fn.DataTable.isDataTable('.lwsop_error_listing')) {
                jQuery('.lwsop_error_listing').DataTable().destroy();
            }

            // Clear the listing content
            conversion_listing.innerHTML = "";
            if (data['images_listing'] && Object.keys(data['images_listing']).length > 0) {
                Object.values(data['images_listing']).forEach(function(image) {
                    let row = document.createElement('tr');
                    if (image['converted']) {
                        if (image['unavailable']) {
                            row.innerHTML = `
                                <td>${image['name']}</td>
                                <td>${image['format']}</td>
                                <td><?php esc_html_e('Yes', 'lws-optimize'); ?></td>
                                <td>${image['converted_format']} | <?php esc_html_e('Failed', 'lws-optimize'); ?></td>
                                <td>${(image['compression']*100).toFixed(1)}%</td>
                            `;
                        } else {
                            row.innerHTML = `
                                <td>${image['name']}</td>
                                <td>${image['format']}</td>
                                <td><?php esc_html_e('Yes', 'lws-optimize'); ?></td>
                                <td>${image['converted_format']}</td>
                                <td>${(image['compression']*100).toFixed(1)}%</td>
                            `;
                        }
                    } else {
                        if (image['unavailable']) {
                            row.innerHTML = `
                                <td>${image['name']}</td>
                                <td>${image['format']}</td>
                                <td><?php esc_html_e('No', 'lws-optimize'); ?></td>
                                <td><?php esc_html_e('Failed', 'lws-optimize'); ?></td>
                                <td></td>
                            `;
                        } else {
                            row.innerHTML = `
                                <td>${image['name']}</td>
                                <td>${image['format']}</td>
                                <td><?php esc_html_e('No', 'lws-optimize'); ?></td>
                                <td></td>
                                <td></td>
                            `;
                            row.classList.add('lwsop_image_not_converted');
                        }
                    }

                    conversion_listing.appendChild(row);
                });
            } else {
                let row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="5"><?php esc_html_e('No images to show', 'lws-optimize'); ?></td>
                    <td></td>
                    <td></td>
		            <td></td>
		            <td></td>
                `;
                conversion_listing.appendChild(row);
            }

            // Initialize DataTable for the listing table
            if (jQuery('.lwsop_error_listing').length) {
                jQuery('.lwsop_error_listing').DataTable({
                    destroy: true,
                    "order": [[2, "desc"]], // Sort by the third column (Converted) by default
                    "language": {
                        "emptyTable": "<?php esc_html_e('No images to show', 'lws-optimize'); ?>",
                        "info": "<?php esc_html_e('Showing _START_ to _END_ of _TOTAL_ entries', 'lws-optimize'); ?>",
                        "infoEmpty": "<?php esc_html_e('Showing 0 to 0 of 0 entries', 'lws-optimize'); ?>",
                        "infoFiltered": "<?php esc_html_e('(filtered from _MAX_ total entries)', 'lws-optimize'); ?>",
                        "lengthMenu": "<?php esc_html_e('Show _MENU_ entries', 'lws-optimize'); ?>",
                        "search": "<?php esc_html_e('Search:', 'lws-optimize'); ?>",
                        "zeroRecords": "<?php esc_html_e('No matching records found', 'lws-optimize'); ?>",
                        "paginate": {
                            "first": "<?php esc_html_e('First', 'lws-optimize'); ?>",
                            "last": "<?php esc_html_e('Last', 'lws-optimize'); ?>",
                            "next": "<?php esc_html_e('Next', 'lws-optimize'); ?>",
                            "previous": "<?php esc_html_e('Previous', 'lws-optimize'); ?>"
                        }
                    },
                    "autoWidth": false,
                    "width": "100%",
                    "columnDefs": [
                        { "width": "50%", "targets": 0, "className": "dt-head-center" }, // Name column - wider
                        { "width": "12.5%", "targets": 1, "className": "dt-head-center" }, // Type column - narrower
                        { "width": "12.5%", "targets": 2, "className": "dt-head-center" }, // Converted column - narrower
                        { "width": "12.5%", "targets": 3, "className": "dt-head-center" }, // Converted Type column - narrower
                        { "width": "12.5%", "targets": 4, "className": "dt-head-center" }  // Compression column - narrower
                    ],
                    "initComplete": function(settings, json) {
                        // Add width to the "show entries" selector
                        jQuery('select[name="DataTables_Table_0_length"]').css({
                            'width': '60px',
                            'border-radius': '10px',
                            'font': 'normal normal normal 15px/29px Poppins',
                            'letter-spacing': '0px',
                            'color': 'rgb(41, 47, 52)',
                            'padding-left': '10px',
                            'padding-right': '10px'
                        });

                        // Add CSS styling to the search input
                        jQuery('.dataTables_filter input').css({
                            'border-radius': '10px',
                            'font': 'normal normal normal 15px/29px Poppins',
                            'letter-spacing': '0px',
                            'color': '#292F34',
                            'padding-left': '10px',
                            'padding-right': '10px',
                            'width': '180px',
                            'margin-bottom': '15px'
                        });

                        // Force table width to 100%
                        jQuery(this).css('width', '100%');

                        // Apply column widths explicitly after initialization
                        setTimeout(function() {
                            jQuery('.lwsop_error_listing').DataTable().columns.adjust();
                        }, 100);
                    }
                });
            }
        }

        if (credits_left) {
            credits_left.innerHTML = data.hasOwnProperty('remaining_credits') ? data['remaining_credits'] : "-";
        }
    }

    function loadModale(value) {
        let modal = document.getElementById('lwsoppro_modal');
        let modal_content = document.getElementById('lwsoppro_modal_content');

        let credits = document.getElementById('lwsoppro_conversion_credits');
        let remaining_images = document.getElementById('lwsoppro_conversion_left');
        let converted_images = document.getElementById('lwsoppro_conversion_done');

        let modal_title = '';
        let modal_text= '';
        let modal_type = '';
        let modal_button = '';
        let checkbox = '';

        switch (value) {
            // TODO : Ajouter la possibilité de choisir le type standard ou par API, traduire + tester
            // Qd on passe en mode API retire tout les options
            // ajouter l'ajax pour api ou standard
            case 'convert':
                modal_title = '<?php esc_html_e('Convert images', 'lws-optimize'); ?>';

                modal_text = `
                    <span class="lws_optimize_image_conversion_modal_element" style="position: relative;">
                        <h3 class="lws_optimize_image_conversion_modal_element_title" style="display: flex; justify-content: space-between; flex-wrap: wrap; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                <?php esc_html_e("Use our API to convert your images ?", 'lws-optimize'); ?>
                            </span>
                            <div class="lwsop_checkbox_container">
                                <label class="lwsop_checkbox_label">
                                    <label class="lwsop_checkbox">
                                        <input type="checkbox" id="lwsoppro_use_api_check" onchange="switchConversionType(this)">
                                        <span class="slider round"></span>
                                    </label>
                                </label>
                            </div>
                        </h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html_e("The API creates better AVIF or WebP images using credits. Local conversion creates WebP images only using your server at no cost.", 'lws-optimize'); ?></span>
                    </span>
                <?php foreach ($conversion_options as $option_id => $option) : ?>
                    <span class="lws_optimize_image_conversion_modal_element">
                        <h3 class="lws_optimize_image_conversion_modal_element_title"><?php echo esc_html($option['title']); ?></h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html($option['description']); ?></span>
                        <div class="lwsop_custom_select image_optimization" onclick="selectManager(this)">
                            <span class="lwsop_custom_option image_optimization">
                                <div class="custom_option_content image_optimization" id="lws_optimize_select_<?php echo esc_attr($option_id); ?>">
                                    <span class="custom_option_content_text image_optimization" value="<?php echo array_key_first($option['select']); ?>"><?php echo wp_kses($option['select'][array_key_first($option['select'])], ['b' => [], 'span' => []]); ?></span>
                                    <input type="hidden" value="<?php echo array_key_first($option['select']); ?>">
                                </div>
                                <img src="<?php echo esc_url(plugins_url('images/chevron_wp_manager.svg', __DIR__)) ?>" alt="chevron" width="12px" height="7px">
                            </span>
                            <ul class="lws_op_dropdown image_optimization">
                                <?php foreach ($option['select'] as $select_id => $select) : ?>
                                    <li class="lws_op_dropdown_list image_optimization">
                                        <span class="lws_op_dropdown_list_content image_optimization" value="<?php echo esc_attr($select_id); ?>" class=""><?php echo wp_kses($select, ['b' => [], 'span' => []]); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </span>
                    <?php endforeach; ?>
                `;

                modal_type = 'convert';
                modal_button = '<?php echo esc_html_e('Convert', 'lws-optimize'); ?>';
                break;
            case 'convertapi':
                modal_title = '<?php esc_html_e('Convert images', 'lws-optimize'); ?>';

                modal_text = `
                    <span class="lws_optimize_image_conversion_modal_element" style="position: relative;">
                        <h3 class="lws_optimize_image_conversion_modal_element_title" style="display: flex; justify-content: space-between; flex-wrap: wrap; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                <?php esc_html_e("Use our API to convert your images ?", 'lws-optimize'); ?>
                            </span>
                            <div class="lwsop_checkbox_container">
                                <label class="lwsop_checkbox_label">
                                    <label class="lwsop_checkbox">
                                        <input type="checkbox" id="lwsoppro_use_api_check" onchange="switchConversionType(this)" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </label>
                            </div>
                        </h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html_e("The API creates better AVIF or WebP images using credits. Local conversion creates WebP images only using your server at no cost.", 'lws-optimize'); ?></span>
                    </span>
                    <div class="lwsop_modal_infobubble" style="margin: 30px;">
                        <?php esc_html_e('You currently have ', 'lws-optimize'); ?>${credits.innerHTML} <?php esc_html_e('credits left and ', 'lws-optimize'); ?> ${remaining_images.innerHTML} <?php esc_html_e(' images are to be converted. It will only convert as much images as you have credits: if you run out during the conversion, it will be stopped.', 'lws-optimize'); ?>
                    </div>
                `;

                modal_type = 'convertapi';
                modal_button = '<?php echo esc_html_e('Convert', 'lws-optimize'); ?>';
                break;
            case 'unconvert':
                modal_title = '<?php esc_html_e('Stop conversion', 'lws-optimize'); ?>';
                modal_text = `
                <div class="lwsop_modal_infobubble" style="margin: 30px;">
                    ${remaining_images.innerHTML} <?php esc_html_e(' images left to convert.', 'lws-optimize'); ?>
                </div>
                `;
                modal_type = 'unconvert';
                modal_button = '<?php echo esc_html_e('Stop', 'lws-optimize'); ?>';
                break;
            case 'reverse':
                modal_title = '<?php esc_html_e('Restore images', 'lws-optimize'); ?>';
                modal_text = `
                <div class="lwsop_modal_infobubble" style="margin: 30px;">
                    ${converted_images.innerHTML} <?php esc_html_e(' images to restore. No credits will be used to restore images as the original images are stored on the website.', 'lws-optimize'); ?>
                </div>
                `;
                modal_type = 'reverse';
                modal_button = '<?php echo esc_html_e('Restore', 'lws-optimize'); ?>';
                break;
            case 'unreverse':
                modal_title = '<?php esc_html_e('Stop restoring images', 'lws-optimize'); ?>';
                modal_text = `
                <div class="lwsop_modal_infobubble" style="margin: 30px;">
                    ${converted_images.innerHTML} <?php esc_html_e(' images left to restore.', 'lws-optimize'); ?>
                </div>
                `;
                modal_type = 'unreverse';
                modal_button = '<?php echo esc_html_e('Stop', 'lws-optimize'); ?>';
                break;
            case 'autoupload':
                checkbox = document.getElementById('lwsoppro_image_autoconversion_check');
                if (checkbox) {
                    if (checkbox.checked) {
                        checkbox.checked = false;
                    }
                }

                modal_title = '<?php esc_html_e('Convert images on upload', 'lws-optimize'); ?>';

                modal_text = `
                    <span class="lws_optimize_image_conversion_modal_element" style="position: relative;">
                        <h3 class="lws_optimize_image_conversion_modal_element_title" style="display: flex; justify-content: space-between; flex-wrap: wrap; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                <?php esc_html_e("Use our API to convert your images ?", 'lws-optimize'); ?>
                            </span>
                            <div class="lwsop_checkbox_container">
                                <label class="lwsop_checkbox_label">
                                    <label class="lwsop_checkbox">
                                        <input type="checkbox" id="lwsoppro_use_api_check" value="autoupload" onchange="switchConversionType(this)">
                                        <span class="slider round"></span>
                                    </label>
                                </label>
                            </div>
                        </h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html_e("The API creates better AVIF or WebP images using credits. Local conversion creates WebP images only using your server at no cost.", 'lws-optimize'); ?></span>
                    </span>
                <?php foreach ($conversion_options as $option_id => $option) : ?>
                    <span class="lws_optimize_image_conversion_modal_element">
                        <h3 class="lws_optimize_image_conversion_modal_element_title"><?php echo esc_html($option['title']); ?></h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html($option['description']); ?></span>
                        <div class="lwsop_custom_select image_optimization" onclick="selectManager(this)">
                            <span class="lwsop_custom_option image_optimization">
                                <div class="custom_option_content image_optimization" id="lws_optimize_select_<?php echo esc_attr($option_id); ?>">
                                    <span class="custom_option_content_text image_optimization" value="<?php echo array_key_first($option['select']); ?>"><?php echo wp_kses($option['select'][array_key_first($option['select'])], ['b' => [], 'span' => []]); ?></span>
                                    <input type="hidden" value="<?php echo array_key_first($option['select']); ?>">
                                </div>
                                <img src="<?php echo esc_url(plugins_url('images/chevron_wp_manager.svg', __DIR__)) ?>" alt="chevron" width="12px" height="7px">
                            </span>
                            <ul class="lws_op_dropdown image_optimization">
                                <?php foreach ($option['select'] as $select_id => $select) : ?>
                                    <li class="lws_op_dropdown_list image_optimization">
                                        <span class="lws_op_dropdown_list_content image_optimization" value="<?php echo esc_attr($select_id); ?>" class=""><?php echo wp_kses($select, ['b' => [], 'span' => []]); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </span>
                    <?php endforeach; ?>
                `;

                modal_type = 'autoupload';
                modal_button = '<?php echo esc_html_e('Convert', 'lws-optimize'); ?>';

                // Open the modal dialog
                jQuery('#lwsoppro_modal').modal('show');
                break;
            case 'autouploadapi':
                checkbox = document.getElementById('lwsoppro_image_autoconversion_check');
                if (checkbox) {
                    if (checkbox.checked) {
                        checkbox.checked = false;
                    }
                }

                modal_title = '<?php esc_html_e('Convert images on upload', 'lws-optimize'); ?>';

                modal_text = `
                    <span class="lws_optimize_image_conversion_modal_element" style="position: relative;">
                        <h3 class="lws_optimize_image_conversion_modal_element_title" style="display: flex; justify-content: space-between; flex-wrap: wrap; align-items: center; width: 100%;">
                            <span style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                <?php esc_html_e("Use our API to convert your images ?", 'lws-optimize'); ?>
                            </span>
                            <div class="lwsop_checkbox_container">
                                <label class="lwsop_checkbox_label">
                                    <label class="lwsop_checkbox">
                                        <input type="checkbox" id="lwsoppro_use_api_check" value="autoupload" onchange="switchConversionType(this)" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </label>
                            </div>
                        </h3>
                        <span class="lws_optimize_image_conversion_modal_element_description"><?php echo esc_html_e("The API creates better AVIF or WebP images using credits. Local conversion creates WebP images only using your server at no cost.", 'lws-optimize'); ?></span>
                    </span>
                    <div class="lwsop_modal_infobubble" style="margin: 30px;">
                        <?php esc_html_e('You currently have ', 'lws-optimize'); ?>${credits.innerHTML} <?php esc_html_e('credits left. A credit will be used each time a PNG/JPEG image is uploaded on the website and converted. The file will be uploaded normally if there is not enough credits left.', 'lws-optimize'); ?>
                    </div>
                `;

                modal_type = 'autouploadapi';
                modal_button = '<?php echo esc_html_e('Convert', 'lws-optimize'); ?>';

                // Open the modal dialog
                jQuery('#lwsoppro_modal').modal('show');
                break;
            default :
                break;
        }

        modal_content.innerHTML = `
            <h2 class="lwsop_exclude_title">${modal_title}</h2>
            ${modal_text}
            <div class="lwsop_modal_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button type="button" class="lwsop_validatebutton" value="${modal_type}" onclick="actionModal(this)">
                    ${modal_button}
                </button>
            </div>
        `;
    }

    // Manage the fake select in the conversion modal
    function selectManager(element){
        if (element.classList.contains('active')) {
            element.classList.remove('active');
        } else {
            element.classList.add('active');
        }
    }
    document.addEventListener('click', function(event) {
        let target = event.target;
        if (target.classList.contains('lws_op_dropdown_list') && target.classList.contains('image_optimization')) {
            let value = target.children[0];
            let select = target.parentNode.previousElementSibling.children[0];
            if (select !== null && select.classList.contains("custom_option_content") && select.classList.contains("image_optimization")) {
                select.children[0].innerHTML = value.innerHTML + `<input type="hidden" value="` + value.getAttribute('value') + `">`;
            }
        }
    });
    //

    function actionModal(element) {
        let value = '';
        let state = '';

        // If called with a specific value, then ot means we have to deactivate autoconversion
        // As such, we force the autoupload state at false
        if (element == "unautoconvert") {
            value = "autoupload";
            state = false;
        } else {
            value = element.getAttribute('value');
            state = document.getElementById('lwsoppro_image_autoconversion_check').checked ? 0 : 1;
        }

        let modal = document.getElementById('lwsoppro_modal');
        let modal_content = document.getElementById('lwsoppro_modal_content');

        let button = undefined;
        let originalTect = '';

        switch (value) {
            case 'unconvert':
            case 'unreverse':

                button = document.querySelector("button[value='" + value + "'][data-target='#lwsoppro_modal']");
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_stop_all_conversions")); ?>",
                        action: "lws_optimize_stop_all_conversions",
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            case 'convertapi':
                button = document.querySelector("button[value='" + 'convert' + "'][data-target='#lwsoppro_modal']");
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_start_conversion_api")); ?>",
                        action: "lws_optimize_start_conversion_api",
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            case 'autoupload':
                button = document.getElementById('lwsoppro_image_autoconversion_check').parentNode;
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_start_autoconversion_standard")); ?>",
                        action: "lws_optimize_start_autoconversion_standard",
                        state: state
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            case 'autouploadapi':
                button = document.getElementById('lwsoppro_image_autoconversion_check').parentNode;
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_start_autoconversion_api")); ?>",
                        action: "lws_optimize_start_autoconversion_api",
                        state: state,
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            case 'convert':
                let quality_element = document.getElementById('lws_optimize_select_conversion_quality');
                let size_element = document.getElementById('lws_optimize_select_image_maxsize');

                let quality = quality_element.children[0].getAttribute('value');
                let size = size_element.children[0].getAttribute('value');

                button = document.querySelector("button[value='" + value + "'][data-target='#lwsoppro_modal']");
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_start_conversion_standard")); ?>",
                        action: "lws_optimize_start_conversion_standard",
                        quality: quality,
                        size: size,
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            case 'reverse':
                button = document.querySelector("button[value='" + value + "'][data-target='#lwsoppro_modal']");
                if (button) {
                    originalText = button.innerHTML;
                    button.innerHTML = `
                        <span name="loading" style="padding-left:5px">
                            <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                        </span>
                    `;
                    button.disabled = true;
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 60000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_for_lws_optimize_start_deconversion")); ?>",
                        action: "lws_optimize_start_deconversion",
                    },
                    success: function(data) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        try {
                            JSON.parse(data);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            callPopup("error", "<?php esc_html_e('Invalid response format received. Please try again.', 'lws-optimize'); ?>");
                            return -1;
                        }

                        let response = JSON.parse(data);

                        switch (response['code']) {
                            case 'SUCCESS':
                                refreshPage();
                                break;
                            default:
                                callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                                break;
                        }
                    },
                    error: function(error) {
                        if (button) {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }

                        callPopup("error", "<?php esc_html_e('An error occured and the action could not be executed. Please try again.', 'lws-optimize'); ?>");
                    }
                });
                break;
            default:
                break;
        }

        // Close the modal
        jQuery(modal).modal('hide');
    }

    function switchConversionType(element) {
        let type = '';
        if (element.getAttribute('value') === 'autoupload') {
            type = element.checked ? 'autouploadapi' : 'autoupload';
        } else {
            type = element.checked ? 'convertapi' : 'convert';

        }
        loadModale(type);
    }

    function checkAutoupload(element) {
        let check = element.checked ? true : false;
        if (check) {
            loadModale('autoupload');
        } else {
            actionModal('unautoconvert');
        }
    }

    refreshPage();
    setInterval(function() {
        refreshPage();
    }, 300000);
</script>