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

global $wp_version;


$mimetype_select_values = [
    'webp' => "WebP",
    'jpeg' => "JPEG",
    'png'  => "PNG",
];


if (floatval($wp_version) > 6.5) {
    $mimetype_select_values = array_merge(['avif' => "AVIF"], $mimetype_select_values);
}

$latest_convertion = get_option('lws_optimize_current_media_convertion', [
    'done' => 0,
    'latest_time' => 0,
]);

$autoconvert_state = $GLOBALS['lws_optimize']->lwsop_check_option('auto_update')['state'];

$done_convertion = $latest_convertion['done'] ?? 0;
$latest_time = $latest_convertion['latest_time'] ?? 0;
$local_timestamp = get_date_from_gmt(date('Y-m-d H:i:s', $latest_time), 'Y-m-d H:i:s');

$next_scheduled_all_convert = wp_next_scheduled('lws_optimize_convert_media_cron');
?>

<div class="lwsoptimize_container">
    <div class="lwsop_title_banner">
        <div class="lwsop_top_banner" <?php if (!$lwscache_locked) : ?>style="max-width: none;" <?php endif ?>>
            <img src="<?php echo esc_url(plugins_url('images/plugin_lws_optimize_logo.svg', __DIR__)) ?>" alt="LWS Optimize Logo" width="80px" height="80px">
            <div class="lwsop_top_banner_text">
                <div class="lwsop_top_title_block">
                    <div class="lwsop_top_title">
                        <span><?php echo esc_html('LWS Optimize'); ?></span>
                        <span><?php esc_html_e('by', 'lws-optimize'); ?></span>
                        <span class="logo_lws"></span>
                    </div>
                    <div class="lwsop_top_rateus">
                        <?php echo esc_html_e('You like this plugin ? ', 'lws-optimize'); ?>
                        <?php echo wp_kses(__('A <a href="https://wordpress.org/support/plugin/lws-optimize/reviews/#new-post" target="_blank" class="link_to_rating_with_stars"><div class="lwsop_stars">★★★★★</div> rating</a> will motivate us a lot.', 'lws-optimize'), ['a' => ['class' => [], 'href' => [], 'target' => []], 'div' => ['class' => []]]); ?>
                    </div>
                </div>
                <div class="lwsop_top_description">
                    <?php echo esc_html_e('LWS Optimize lets you get better performances on your WordPress website. Improve your loading times thanks to our tools: caching, media optimisation, files minification and concatenation...', 'lws-optimize'); ?>
                </div>
            </div>
        </div>

        <?php if ($lwscache_locked) : ?>
            <div class="lwsop_top_banner_right">
                <div class="lwsop_top_banner_right_top">
                    <img src="<?php echo esc_url(plugins_url('images/wordpress_black.svg', __DIR__)) ?>" alt="Logo WP noir" width="20px" height="20px">
                    <div>
                        <?php echo wp_kses(__('<b>Exclusive</b>: Get <b>15%</b> off your WordPress hosting', 'lws-optimize'), ['b' => []]); ?>
                    </div>
                </div>
                <div class="lwsop_top_banner_right_bottom">
                    <label onclick="lwsoptimize_copy_clipboard(this)" readonly text="WPEXT15">
                        <span><?php echo esc_html('WPEXT15'); ?></span>
                        <img src="<?php echo esc_url(plugins_url('images/copier_new.svg', __DIR__)) ?>" alt="Logo Copy Element" width="15px" height="18px">
                    </label>
                    <a target="_blank" href="<?php echo esc_url('https://www.lws.fr/hebergement_wordpress.php'); ?>"><?php esc_html_e("Let's go!", 'lws-optimize'); ?></a>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="lwsop_activate_plugin">
        <div>
            <span class="lws_op_front_text_title"><?php esc_html_e('Activate LWS Optimize', 'lws-optimize'); ?></span>
            <span class="lwsop_necessary"><?php esc_html_e('necessary', 'lws-optimize'); ?></span>
        </div>
        <div class="lwsop_contentblock_rightside">
            <button class="lwsop_blue_button" data-toggle="modal" data-target="#lwsop_preconfigurate_plugin">
                <img src="<?php echo esc_url(plugins_url('images/magie_ia.svg', __DIR__)) ?>" alt="Logo IA Magie" width="20px" height="20px">
                <?php esc_html_e('Pre-configuration', 'lws-optimize'); ?>
            </button>
            <label class="lwsop_checkbox">
                <input type="checkbox" name="manage_plugin_state" id="manage_plugin_state" <?php echo get_option('lws_optimize_offline') ? esc_html('') : esc_html('checked'); ?>>
                <span class="slider round"></span>
            </label>
        </div>
    </div>


    <div class="lwsoptimize_main_content">
        <div class="lws_op_configpage" style="border-radius: 10px;">
            <div class="lwsop_contentblock">
                <div class="lwsop_contentblock_leftside">
                    <h2 class="lwsop_contentblock_title">
                        <?php esc_html_e('Convert all images', 'lws-optimize'); ?>
                        <a href="" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" 
                        width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
                    </h2>
                    <div class="lwsop_contentblock_description">
                        <?php esc_html_e('Convert all images on your website to another Mime-Type', 'lws-optimize'); ?>
                    </div>
                    <div class="lwsop_contentblock_convertion_status" id="lwsop_convertion_status">
                        <div>
                            <span><?php echo esc_html__('Status: ', 'lws-optimize'); ?></span> 
                            <span><?php echo $next_scheduled_all_convert ? esc_html__('Ongoing','lws-optimize') : esc_html__('Inactive','lws-optimize'); ?></span>
                        </div>
                        <?php if ($latest_time == 0) : ?>
                            <div><?php esc_html_e('No convertion recorded.', 'lws-optimize'); ?></div>
                        <?php else : ?>
                            <div>
                                <span><?php echo esc_html__('Latest convertion done on: ', 'lws-optimize'); ?></span>
                                <span> <?php echo esc_html($local_timestamp); ?></span>
                            </div>
                            <div>
                                <span><?php echo esc_html__('Amount of images converted: ', 'lws-optimize'); ?></span>
                                <span><?php echo esc_html($done_convertion); ?></span>
                            </div>
                        <?php endif; ?>
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
                    <button type="button" class="lwsop_blue_button" id="lwsop_revert_all_images" name="lwsop_revert_all_images">
                        <span>
                            <!-- <img src="<?php //echo esc_url(plugins_url('images/', __DIR__)) ?>" alt="" width="20px"> -->
                            <?php esc_html_e('Revert images', 'lws-optimize'); ?>
                        </span>
                    </button>
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
                    <label class="lwsop_checkbox">
                        <input type="checkbox" name="lwsop_onupload_convertion" id="lwsop_onupload_convertion" <?php echo $autoconvert_state == "true" ? esc_attr("checked") : ""; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="lws_made_with_heart"><?php esc_html_e('Created with ❤️ by LWS.fr', 'lws-optimize'); ?></div>

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
                    <input class="lwsop_convert_modal_amount_convertion_label" type="number" min="1" max="50" value="10" id="lwsop_amount_convertion">
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

<div class="modal fade" id="lwsop_preconfigurate_plugin" tabindex='-1' role='dialog'>
    <div class="modal-dialog" style="margin-top: 10%">
        <div class="modal-content configurate_plugin">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Choose which configuration to apply', 'lws-optimize'); ?></h2>
            <form method="POST" name="lwsop_form_choose_configuration" id="lwsop_form_choose_configuration">
                <div class="lwsop_configuration_block">
                    <label class="lwsop_configuration_block_sub selected" name="lwsop_configuration_selector_div">
                        <label class="lwsop_configuration_block_l">
                            <input type="radio" name="lwsop_configuration[]" value="recommended" checked>
                            <span><?php esc_html_e('Recommended configuration', 'lws-optimize'); ?></span>
                        </label>
                        <div class="lwsop_configuration_description">
                            <?php esc_html_e('Beginner-friendly! Activate recommended settings to optimize your website\'s speed fast and easily.', 'lws-optimize'); ?>
                        </div>
                    </label>

                    <label class="lwsop_configuration_block_sub" name="lwsop_configuration_selector_div">
                        <label class="lwsop_configuration_block_l">
                            <input type="radio" name="lwsop_configuration[]" value="advanced">
                            <span><?php esc_html_e('Advanced configuration', 'lws-optimize'); ?></span>
                        </label>
                        <div class="lwsop_configuration_description">
                            <?php esc_html_e('Activate all previous options and further optimize your website with CSS preloading, database optimisation and more.', 'lws-optimize'); ?>
                        </div>
                    </label>

                    <label class="lwsop_configuration_block_sub" name="lwsop_configuration_selector_div">
                        <label class="lwsop_configuration_block_l">
                            <input type="radio" name="lwsop_configuration[]" value="complete">
                            <span><?php esc_html_e('Complete configuration', 'lws-optimize'); ?></span>
                        </label>
                        <div class="lwsop_configuration_description">
                            <?php esc_html_e('Activate every options to fully optimize your website. Not recommended to beginners, may needs tweakings to make it work on your website.', 'lws-optimize'); ?>
                        </div>
                    </label>
                </div>
                <div class="lwsop_modal_buttons">
                    <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                    <button type="submit" id="lwsop_submit_new_config_button" class="lwsop_validatebutton">
                        <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                        <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="lws_optimize_cloudflare_warning" tabindex='-1' role='dialog'>
    <div class="modal-dialog cloudflare_dialog">
        <div class="modal-content cloudflare_content" style="padding: 30px 0;">
            <h2 class="lwsop_exclude_title" id="lws_optimize_cloudflare_manage_title"><?php esc_html_e('About Cloudflare Integration', 'lws-optimize'); ?></h2>
            <div id="lwsop_blue_info" class="lwsop_blue_info"><?php esc_html_e('We detected that you are using Cloudflare on this website. Make sure to enable the CDN Integration in the CDN tab.', 'lws-optimize'); ?></div>
            <form method="POST" id="lws_optimize_cloudflare_manage_form"></form>
            <div class="lwsop_modal_buttons" id="lws_optimize_cloudflare_manage_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                <button type="button" class="lws_optimize_cloudflare_next" data-dismiss="modal" id="lwsop_goto_cloudflare_integration"><?php echo esc_html_e('Go to the option', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<div id='modal_popup' class='modal fade' data-result="warning" tabindex='-1' role='dialog' aria-labelledby='myModalLabel' style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class='modal-body'>
                <div class="container-modal">
                    <div class="success-animation">
                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                            <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                            <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                        </svg>
                    </div>
                    <div class="error-animation">
                        <svg class="circular red-stroke">
                            <circle class="path" cx="75" cy="75" r="50" fill="none" stroke-width="5" stroke-miterlimit="10" />
                        </svg>
                        <svg class="cross red-stroke">
                            <g transform="matrix(0.79961,8.65821e-32,8.39584e-32,0.79961,-502.652,-204.518)">
                                <path class="first-line" d="M634.087,300.805L673.361,261.53" fill="none" />
                            </g>
                            <g transform="matrix(-1.28587e-16,-0.79961,0.79961,-1.28587e-16,-204.752,543.031)">
                                <path class="second-line" d="M634.087,300.805L673.361,261.53" />
                            </g>
                        </svg>
                    </div>
                    <div class="warning-animation">
                        <svg class="circular yellow-stroke">
                            <circle class="path" cx="75" cy="75" r="50" fill="none" stroke-width="5" stroke-miterlimit="10" />
                        </svg>
                        <svg class="alert-sign yellow-stroke">
                            <g transform="matrix(1,0,0,1,-615.516,-257.346)">
                                <g transform="matrix(0.56541,-0.56541,0.56541,0.56541,93.7153,495.69)">
                                    <path class="line" d="M634.087,300.805L673.361,261.53" fill="none" />
                                </g>
                                <g transform="matrix(2.27612,-2.46519e-32,0,2.27612,-792.339,-404.147)">
                                    <circle class="dot" cx="621.52" cy="316.126" r="1.318" />
                                </g>
                            </g>
                        </svg>
                    </div>
                    <div class="content_message" id="container_content"></div>
                    <div>
                        <button class="btn" data-dismiss="modal" aria-hidden="true" onclick="closemodal()">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

        document.getElementById('lwsop_form_choose_configuration').addEventListener("submit", function(event) {
            var element = event.target;
            event.preventDefault();
            document.body.style.pointerEvents = "none";
            let formData = jQuery(element).serializeArray();


            let submit_button = document.getElementById('lwsop_submit_new_config_button');
            let old = element.innerHTML;
            element.innerHTML = `
                <div class="loading_animation">
                        <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                    </div>
            `;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: formData,
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_change_optimize_configuration_nonce")); ?>",
                    action: "lwsop_change_optimize_configuration",
                },
                success: function(data) {
                    element.innerHTML = old;
                    document.querySelectorAll('input[name="lwsop_configuration[]"]').forEach(function(element) {
                        element.addEventListener('change', function() {
                            document.querySelectorAll('.lwsop_configuration_block_sub.selected').forEach(function(element) {
                                element.classList.remove('selected')
                            });
                            element.parentElement.parentElement.classList.add('selected')
                        })
                    })

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

                    jQuery(document.getElementById('lwsop_preconfigurate_plugin')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e('New configuration applied.', 'lws-optimize'); ?>");
                            location.reload();
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e('Failed to configurate the plugin.', 'lws-optimize'); ?>");
                            break;
                    }
                },
                error: function(error) {
                    element.innerHTML = old;
                    document.querySelectorAll('input[name="lwsop_configuration[]"]').forEach(function(element) {
                        element.addEventListener('change', function() {
                            document.querySelectorAll('.lwsop_configuration_block_sub.selected').forEach(function(element) {
                                element.classList.remove('selected')
                            });
                            element.parentElement.parentElement.classList.add('selected')
                        })
                    })

                    document.body.style.pointerEvents = "all";
                    jQuery(document.getElementById('lwsop_preconfigurate_plugin')).modal('hide');
                    callPopup("error", "<?php esc_html_e('Unknown error. Cannot configurate the plugin.', 'lws-optimize'); ?>");
                    console.log(error);
                }
            });
        });

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
            let amount_per_patch = document.getElementById('lwsop_amount_per_patch_convertion');

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

        jQuery(document).ready(function() {
            jQuery('[data-toggle="tooltip"]').tooltip();
        });
    </script>
<?php endif ?>