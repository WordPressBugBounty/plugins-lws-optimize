<?php
// X-Cdn-Info => cloudflare
// Cf-Connecting-Ip

$state = isset($config_array['cloudflare']['state']) && $config_array['cloudflare']['state'] == "true" ? true : false;
// If the CDN integration if not active...
if ($state === false) {
    $headers = getallheaders();
    // If we find Cloudflare headers, then we show a popup to incite users to integrate CDN
    if (isset($headers['X-Cdn-Info']) && isset($header['X-Cdn-Info']) && $header['X-Cdn-Info'] == "cloudflare") {
?>
        <script>
            jQuery(document).ready(function() {
                let warning_modale = document.getElementById('lws_optimize_cloudflare_warning');
                if (warning_modale !== null) {
                    jQuery(warning_modale).modal('show');
                }
            });
        </script>
<?php
    }
}

$list_time = array(
    '0' => __('Default', 'lws-optimize'),
    '3600' => __('One hour', 'lws-optimize'),
    '14400' => __('4 hours', 'lws-optimize'),
    '86400' => __('A day', 'lws-optimize'),
    '691200' => __('8 days', 'lws-optimize'),
    '2678400' => __('A month', 'lws-optimize'),
    '5356800' => __('2 months', 'lws-optimize'),
    '16070400' => __('6 months', 'lws-optimize'),
    '31536000' => __('A year', 'lws-optimize'),
);

?>
<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <img src="<?php echo esc_url(plugins_url('images/cloudflare.svg', __DIR__)) ?>" alt="pc icon" width="30px" height="30px">
            <?php echo esc_html_e('Cloudflare integration with LWS Optimize', 'lws-optimize'); ?>
            <a href="https://aide.lws.fr/a/1890" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php echo esc_html_e('LWS Optimize is fully compatible with Cloudflare CDN. This integration prevent incompatibilities by modifying Cloudflare settings. Furthermore, it purges Cloudflare cache at the same time as LWS Optimize.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox">
            <input type="checkbox" name="lwsop_cloudflare_manage" id="lwsop_cloudflare_manage" <?php echo $state ? esc_html('checked') : esc_html(''); ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="modal fade" id="lws_optimize_cloudflare_manage" tabindex='-1' role='dialog' aria-hidden='true'>
    <div class="modal-dialog cloudflare_dialog">
        <div class="modal-content cloudflare_content" style="padding: 30px 0;">
            <h2 class="lwsop_exclude_title" id="lws_optimize_cloudflare_manage_title"><?php esc_html_e('Cloudflare settings : Insert API Keys', 'lws-optimize'); ?></h2>
            <div id="lwsop_blue_info" class="lwsop_blue_info"><?php esc_html_e('Fill in your API Token below to access Cloudflare APIs.', 'lws-optimize'); ?></div>
            <form method="POST" id="lws_optimize_cloudflare_manage_form"></form>
            <div class="lwsop_modal_buttons" id="lws_optimize_cloudflare_manage_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('lwsop_cloudflare_manage').addEventListener('change', function(event) {
        let b = this;
        let button = this.parentNode;
        let text = button.innerHTML;

        if (this.checked == false) {
            button.innerHTML = '<div class="load-animated"><div class="line black"></div><div class="line black"></div><div class="line black"></div></div>';
            this.checked = false;
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_deactivate_cf_integration_nonce')); ?>',
                    action: "lws_optimize_deactivate_cloudflare_integration",
                },
                success: function(data) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
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
                            callPopup('success', `<?php esc_html_e('Cloudflare integration has been deactivated', 'lws-optimize'); ?>`);
                            b.checked = false;
                            break;
                        default:
                            callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";
                    console.log(error);
                    callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                }
            });
        } else {
            this.checked = !this.checked;
            jQuery("#lws_optimize_cloudflare_manage").modal('show');

            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
            let infobulle = document.getElementById('lwsop_blue_info');

            infobulle.style.display = "flex"
            infobulle.innerHTML = `<?php esc_html_e('Fill in your API Token and API Key below to access Cloudflare APIs.', 'lws-optimize'); ?>`;

            form.innerHTML = `
                <div class="cloudflare_token_input_block">
                    <label class="cloudflare_token_label">
                        <span class="cloudflare_token_label_text"><?php esc_html_e('API Token', 'lws-optimize'); ?></span>
                        <input class="cloudflare_token_input" id="lws_optimize_cloudflare_token_api" name="lws_optimize_cloudflare_token_api" required>
                    </label>
                    <a class="cloudflare_token_link" href="https://aide.lws.fr/a/1890" target="_blank"><?php esc_html_e('Help: how to include Cloudflare in LWS Optimize', 'lws-optimize'); ?></a>
                </div>
            `;
            form.style.display = "flex";

            buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button type="button" id="lws_optimize_cloudflare_next_1" class="lws_optimize_cloudflare_next">
                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                </button>
            `;
        }
    });

    document.addEventListener('click', function(event) {
        let target = event.target;
        if (target.id == "lws_optimize_cloudflare_previous_1") {

            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
            let infobulle = document.getElementById('lwsop_blue_info');

            infobulle.style.display = "flex"
            infobulle.innerHTML = `<?php esc_html_e('Fill in your API Token and API Key below to access Cloudflare APIs.', 'lws-optimize'); ?>`;

            title.innerHTML = `<?php esc_html_e('Cloudflare settings : Insert API Keys', 'lws-optimize'); ?>`;

            form.innerHTML = `
                <div class="cloudflare_token_input_block">
                    <label class="cloudflare_token_label">
                        <span class="cloudflare_token_label_text"><?php esc_html_e('API Token', 'lws-optimize'); ?></span>
                        <input class="cloudflare_token_input" id="lws_optimize_cloudflare_token_api" name="lws_optimize_cloudflare_token_api" required>
                    </label>
                    <a class="cloudflare_token_link" href="https://aide.lws.fr/a/1890" target="_blank"><?php esc_html_e('Help: how to include Cloudflare in LWS Optimize', 'lws-optimize'); ?></a>
                </div>
            `;

            buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button type="button" id="lws_optimize_cloudflare_next_1" class="lws_optimize_cloudflare_next">
                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                </button>
            `;
            form.style.display = "flex";
        }
        if (target.id == "lws_optimize_cloudflare_previous_2") {
            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
            let infobulle = document.getElementById('lwsop_blue_info');

            infobulle.style.display = "none"
            infobulle.innerHTML = '';

            title.innerHTML = `<?php esc_html_e('Cloudflare settings: Tools deactivation', 'lws-optimize'); ?>`;

            buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button type="button" id="lws_optimize_cloudflare_previous_1" class="lws_optimize_cloudflare_next">
                    <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                    <?php echo esc_html_e('Previous', 'lws-optimize'); ?>
                </button>
                <button type="button" id="lws_optimize_cloudflare_next_2" class="lws_optimize_cloudflare_next">
                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                </button>
            `;
            form.innerHTML = `
                <div class="cloudflare_tools_block">
                    <div class="cloudflare_tools_element">
                        <label class="lwsop_maintenance_checkbox">
                            <input type="checkbox" id="lws_optimize_deactivate_min_css" name="lws_optimize_deactivate_min_css" checked>
                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate CSS Files Minification', 'lws-optimize'); ?></div>
                            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                        </label>
                        <div class="cloudflare_tools_description">
                            <?php esc_html_e('Avoid conflicts and redundancies with Cloudflare, which already optimizes CSS files.', 'lws-optimize'); ?>
                        </div>
                    </div>
                    <div class="cloudflare_tools_element">
                        <label class="lwsop_maintenance_checkbox">
                            <input type="checkbox" id="lws_optimize_deactivate_min_js" name="lws_optimize_deactivate_min_js" checked>
                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate JS Files Minification', 'lws-optimize'); ?></div>
                            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                        </label>
                        <div class="cloudflare_tools_description">
                            <?php esc_html_e('Avoid multiples processing and errors, Cloudflare already manage JS minification.', 'lws-optimize'); ?>
                        </div>
                    </div>
                    <div class="cloudflare_tools_element">
                        <label class="lwsop_maintenance_checkbox">
                            <input type="checkbox" id="lws_optimize_deactivate_dynamic_cache" name="lws_optimize_deactivate_dynamic_cache" checked>
                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate Dynamic Cache', 'lws-optimize'); ?></div>
                            <span class="lwsop_necessary"><?php esc_html_e('necessary', 'lws-optimize'); ?></span>
                        </label>
                        <div class="cloudflare_tools_description">
                            <?php esc_html_e('Prevents caching overlaps, as Cloudflare already performs cache optimization efficiently.', 'lws-optimize'); ?>
                        </div>
                    </div>
                </div>
            `;
            form.style.display = "flex";
        }
        if (target.id == "lws_optimize_cloudflare_previous_3") {
            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
            let infobulle = document.getElementById('lwsop_blue_info');

            infobulle.style.display = "flex"
            infobulle.innerHTML = `<?php esc_html_e('The Browser Cache Expiration option has been set to 6 months. This is the period when Cloudflare tells browsers to keep files cached.', 'lws-optimize'); ?>`;

            title.innerHTML = `<?php esc_html_e('Cloudflare settings: Browser Cache Expiration', 'lws-optimize'); ?>`;

            buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button type="button" id="lws_optimize_cloudflare_previous_2" class="lws_optimize_cloudflare_next">
                    <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                    <?php echo esc_html_e('Previous', 'lws-optimize'); ?>
                </button>
                <button type="button" id="lws_optimize_cloudflare_next_3" class="lws_optimize_cloudflare_next">
                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                </button>
            `;

            form.innerHTML = `
                <div class="cloudflare_browser_cache_block">
                    <label class="cloudflare_browser_cache_label">
                        <span><?php echo esc_html_e('Browser cache lifespan:', 'lws-optimize'); ?></span>
                        <select id="lws_optimize_browser_cache_lifespan">
                            <?php foreach ($list_time as $time => $value) : ?>
                                <option value="<?php echo esc_html($time); ?>" <?php echo $time == "16070400" ? "selected" : ""; ?>><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            `;
            form.style.display = "flex";
        }

        if (target.id == "lws_optimize_cloudflare_next_1") {
            let button = target;
            let text = button.innerHTML;
            button.innerHTML = '<div class="load-animated"><div class="line"></div><div class="line"></div><div class="line"></div></div>';
            let token_api = document.getElementById('lws_optimize_cloudflare_token_api').value;
            document.body.style.pointerEvents = "none";
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_check_cloudflare_key_nonce')); ?>',
                    action: "lws_optimize_check_cloudflare_key",
                    key: token_api,
                },
                success: function(data) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
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
                            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
                            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
                            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
                            let infobulle = document.getElementById('lwsop_blue_info');

                            infobulle.style.display = "none"
                            infobulle.innerHTML = '';

                            title.innerHTML = `<?php esc_html_e('Cloudflare settings: Tools deactivation', 'lws-optimize'); ?>`;

                            buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_cloudflare_previous_1" class="lws_optimize_cloudflare_next">
                                    <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                                    <?php echo esc_html_e('Previous', 'lws-optimize'); ?>
                                </button>
                                <button type="button" id="lws_optimize_cloudflare_next_2" class="lws_optimize_cloudflare_next">
                                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                                </button>
                            `;
                            form.innerHTML = `
                                <div class="cloudflare_tools_block">
                                    <div class="cloudflare_tools_element">
                                        <label class="lwsop_maintenance_checkbox">
                                            <input type="checkbox" id="lws_optimize_deactivate_min_css" name="lws_optimize_deactivate_min_css" checked>
                                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate CSS Files Minification', 'lws-optimize'); ?></div>
                                            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                                        </label>
                                        <div class="cloudflare_tools_description">
                                            <?php esc_html_e('Avoid conflicts and redundancies with Cloudflare, which already optimizes CSS files.', 'lws-optimize'); ?>
                                        </div>
                                    </div>
                                    <div class="cloudflare_tools_element">
                                        <label class="lwsop_maintenance_checkbox">
                                            <input type="checkbox" id="lws_optimize_deactivate_min_js" name="lws_optimize_deactivate_min_js" checked>
                                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate JS Files Minification', 'lws-optimize'); ?></div>
                                            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                                        </label>
                                        <div class="cloudflare_tools_description">
                                            <?php esc_html_e('Avoid multiples processing and errors, Cloudflare already manage JS minification.', 'lws-optimize'); ?>
                                        </div>
                                    </div>
                                    <div class="cloudflare_tools_element">
                                        <label class="lwsop_maintenance_checkbox">
                                            <input type="checkbox" id="lws_optimize_deactivate_dynamic_cache" name="lws_optimize_deactivate_dynamic_cache" checked>
                                            <div class="cloudflare_tools_text"><?php esc_html_e('Deactivate Dynamic Cache', 'lws-optimize'); ?></div>
                                            <span class="lwsop_necessary"><?php esc_html_e('necessary', 'lws-optimize'); ?></span>
                                        </label>
                                        <div class="cloudflare_tools_description">
                                            <?php esc_html_e('Prevents caching overlaps, as Cloudflare already performs cache optimization efficiently.', 'lws-optimize'); ?>
                                        </div>
                                    </div>
                                </div>
                            `;
                            form.style.display = "flex";
                            break;
                        case 'NO_PARAM':
                            callPopup('error', `<?php esc_html_e('Please enter a valid API Token in the field and try again', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_CURL':
                            callPopup('error', `<?php esc_html_e('The request failed to proceed', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_DECODE':
                            callPopup('error', `<?php esc_html_e('Could not decode the Cloudflare response. Please try again', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'FAILED_SAVE':
                            callPopup('error', `<?php esc_html_e('The API Token could not be saved. Please try again', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'INVALID':
                            callPopup('error', `<?php esc_html_e('This API Token is not active and cannot be used', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_REQUEST':
                            callPopup('error', `<?php esc_html_e('The verification request failed. Make sure the API Token is valid', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_CURL_ZONES':
                            callPopup('error', `<?php esc_html_e('Could not verify the zones', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_DECODE_ZONES':
                            callPopup('error', `<?php esc_html_e('Could not parse the zones data', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'NO_ZONES':
                            callPopup('error', `<?php esc_html_e('No zones found with this API Token.', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'NO_ZONE_FOR_DOMAIN':
                            callPopup('error', `<?php esc_html_e('No zones found for this domain with this API Token.', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        case 'ERROR_REQUEST_ZONES':
                            callPopup('error', `<?php esc_html_e('Could not get any zones from Cloudflare. Please try again', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        default:
                            callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
                    console.log(error);
                    callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                }
            });
        }

        if (target.id == "lws_optimize_deactivate_dynamic_cache") {
            target.checked = true;
        }

        if (target.id == "lws_optimize_cloudflare_next_2") {
            let button = target;
            let text = button.innerHTML;
            button.innerHTML = '<div class="load-animated"><div class="line"></div><div class="line"></div><div class="line"></div></div>';

            let minify_css = document.getElementById('lws_optimize_deactivate_min_css');
            let minify_js = document.getElementById('lws_optimize_deactivate_min_js');
            let deactivate = document.getElementById('lws_optimize_deactivate_dynamic_cache');

            document.body.style.pointerEvents = "none";
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_opti_cf_tools_nonce')); ?>',
                    action: "lws_optimize_cloudflare_tools_deactivation",
                    min_css: minify_css.value,
                    min_js: minify_js.value,
                    cache_deactivate: deactivate.value,
                },
                success: function(data) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
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
                            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
                            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
                            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
                            let infobulle = document.getElementById('lwsop_blue_info');

                            infobulle.style.display = "flex"
                            infobulle.innerHTML = `<?php esc_html_e('The Browser Cache Expiration option has been set to 6 months. This is the period when Cloudflare tells browsers to keep files cached.', 'lws-optimize'); ?>`;

                            title.innerHTML = `<?php esc_html_e('Cloudflare settings: Browser Cache Expiration', 'lws-optimize'); ?>`;

                            buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_cloudflare_previous_2" class="lws_optimize_cloudflare_next">
                                    <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                                    <?php echo esc_html_e('Previous', 'lws-optimize'); ?>
                                </button>
                                <button type="button" id="lws_optimize_cloudflare_next_3" class="lws_optimize_cloudflare_next">
                                    <?php echo esc_html_e('Next', 'lws-optimize'); ?>
                                    <img src="<?php echo esc_url(plugins_url('images/fleche_suivant.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                                </button>
                            `;

                            form.innerHTML = `
                                <div class="cloudflare_browser_cache_block">
                                    <label class="cloudflare_browser_cache_label">
                                        <span><?php echo esc_html_e('Browser cache lifespan:', 'lws-optimize'); ?></span>
                                        <select id="lws_optimize_browser_cache_lifespan">
                                            <?php foreach ($list_time as $time => $value) : ?>
                                                <option value="<?php echo esc_html($time); ?>" <?php echo $time == "16070400" ? "selected" : ""; ?>><?php echo esc_html($value); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            `;
                            form.style.display = "flex";
                            break;
                        case 'FAILED_SAVE':
                            callPopup('error', `<?php esc_html_e('The tools to be deactivated could not be saved. Please try again', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                        default:
                            callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                            return 0;
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
                    console.log(error);
                    callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                }
            });
        }
        if (target.id == "lws_optimize_cloudflare_next_3") {
            let button = target;
            let text = button.innerHTML;
            button.innerHTML = '<div class="load-animated"><div class="line"></div><div class="line"></div><div class="line"></div></div>';
            let select_cache = document.getElementById('lws_optimize_browser_cache_lifespan');

            document.body.style.pointerEvents = "none";
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_opti_cf_duration_nonce')); ?>',
                    action: "lws_optimize_cloudflare_cache_duration",
                    lifespan: select_cache.value
                },
                success: function(data) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
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
                            let form = document.getElementById('lws_optimize_cloudflare_manage_form');
                            let buttons = document.getElementById('lws_optimize_cloudflare_manage_buttons');
                            let title = document.getElementById('lws_optimize_cloudflare_manage_title');
                            let infobulle = document.getElementById('lwsop_blue_info');

                            infobulle.style.display = "flex"
                            infobulle.innerHTML = `<?php esc_html_e('Everything is configurated! Click on the finish button below and that is it.', 'lws-optimize'); ?>`;

                            title.innerHTML = `<?php esc_html_e('Cloudflare settings: Ready to go!', 'lws-optimize'); ?>`;

                            buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_cloudflare_previous_3" class="lws_optimize_cloudflare_next">
                                    <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="pc icon" width="7px" height="12px">
                                    <?php echo esc_html_e('Previous', 'lws-optimize'); ?>
                                </button>
                                <button type="button" id="lws_optimize_cloudflare_end" class="lws_optimize_cloudflare_end">
                                    <?php echo esc_html_e('Finish', 'lws-optimize'); ?>
                                </button>
                            `;

                            form.innerHTML = "";
                            form.style.display = "none";
                            break;
                        case 'FAILED_SAVE':
                            callPopup('error', `<?php esc_html_e('The cache lifetime could not be saved. Please try again', 'lws-optimize'); ?>`);
                            break;
                        default:
                            callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                            break;
                    }
                },
                error: function(error) {
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
                    console.log(error);
                }
            });
        }

        if (target.id == "lws_optimize_cloudflare_end") {
            let button = target;
            let text = button.innerHTML;
            button.innerHTML = '<div class="load-animated"><div class="line"></div><div class="line"></div><div class="line"></div></div>';

            document.body.style.pointerEvents = "none";
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_cloudflare_finish_config_nonce')); ?>',
                    action: "lws_optimize_cloudflare_finish_configuration",
                },
                success: function(data) {
                    jQuery("#lws_optimize_cloudflare_manage").modal('hide');
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
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
                            document.getElementById('lwsop_cloudflare_manage').checked = true;
                            callPopup('success', `<?php esc_html_e('The Cloudflare integration was successfully activated', 'lws-optimize'); ?>`);
                            location.reload();
                            break;
                        case 'NO_PARAM':
                            break;
                        case 'ERROR_DECODE':
                            break;
                        case 'FAILED_SAVE':
                            break;
                        case 'FAILED_PATCH':
                            callPopup('error', `<?php esc_html_e('The Cloudflare integration failed', 'lws-optimize'); ?>`);
                            break;
                        default:
                            callPopup('error', `<?php esc_html_e('The Cloudflare integration failed', 'lws-optimize'); ?>`);
                            break;
                    }
                },
                error: function(error) {
                    jQuery("#lws_optimize_cloudflare_manage").modal('hide');
                    callPopup('error', `<?php esc_html_e('The Cloudflare integration failed', 'lws-optimize'); ?>`);
                    document.body.style.pointerEvents = "all";
                    button.innerHTML = text;
                    console.log(error);
                }
            });
        }

        if (target.id == "lwsop_goto_cloudflare_integration") {
            document.getElementById('nav-cdn').dispatchEvent(new Event('click'));
        }
    });
</script>