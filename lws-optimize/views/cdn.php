<?php
// X-Cdn-Info => cloudflare
// Cf-Connecting-Ip

$state = isset($config_array['cloudflare']['state']) && $config_array['cloudflare']['state'] == "true" ? true : false;

// If the CDN integration if not active...
if (!$state) :
    $headers = getallheaders();
    // If we find Cloudflare headers, then we show a popup to incite users to integrate CDN
    if (isset($headers['X-Cdn-Info']) && isset($header['X-Cdn-Info']) && $header['X-Cdn-Info'] == "cloudflare") : ?>
        <script>
            jQuery(document).ready(function() {
                let warning_modale = document.getElementById('lws_optimize_cloudflare_warning');
                if (warning_modale !== null) {
                    jQuery(warning_modale).modal('show');
                }
            });
        </script>
    <?php endif;
endif;

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
            <a href="https://aide.lws.fr/a/1890" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php echo esc_html_e('LWS Optimize is fully compatible with Cloudflare CDN. This integration prevent incompatibilities by modifying Cloudflare settings. Furthermore, it purges Cloudflare cache at the same time as LWS Optimize.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox">
            <input type="checkbox" name="lwsop_cloudflare_manage" onchange="lws_optimize_cloudflare_configuration(this)" id="lwsop_cloudflare_manage" <?php echo $state ? esc_html('checked') : esc_html(''); ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<?php
// ─── 4.4.0 — Cloudflare APO (edge HTML cache) ──────────────────────────────
// Sous-section ajoutée dans l'onglet CDN existant (au lieu du panneau Advanced
// integrations dans frontend.php — évite le doublon visuel).
// APO ne fait sens que si l'intégration CF de base est active (zone_id + token
// déjà stockés via lws_optimize_complete_cloudflare_integration).
$apo_state   = ($config_array['cloudflare_apo']['state'] ?? 'false') === 'true';
$apo_zone_id = $config_array['cloudflare_apo']['zone_id'] ?? ($config_array['cloudflare']['zone_id'] ?? '');
$apo_token   = $config_array['cloudflare_apo']['api_token'] ?? '';
$is_fr_apo   = substr(get_locale(), 0, 2) === 'fr';
?>
<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <img src="<?php echo esc_url(plugins_url('images/cloudflare.svg', __DIR__)) ?>" alt="cloudflare icon" width="30px" height="30px">
            <?php echo $is_fr_apo ? 'Cloudflare APO — cache HTML edge' : 'Cloudflare APO — edge HTML cache'; ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
            <a href="https://aide.lws.fr/a/" rel="noopener" target="_blank" title="<?php echo $is_fr_apo ? 'Met en cache le HTML directement sur les serveurs Cloudflare dans le monde entier. Avantage : TTFB ~50ms partout. Pré-requis : un compte Cloudflare et un token API avec les scopes Zone.Cache Purge + Zone.Cache Rules.' : 'Cache the HTML at Cloudflare edge nodes worldwide. Benefit: TTFB ~50ms everywhere. Requires: Cloudflare account + API token with scopes Zone.Cache Purge + Zone.Cache Rules.'; ?>">
                <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="<?php esc_attr_e('Learn more', 'lws-optimize'); ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top">
            </a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php echo esc_html($is_fr_apo
                ? 'Met en cache le HTML directement sur le réseau Cloudflare (edge) pour servir vos pages depuis le serveur CF le plus proche du visiteur. Résultat : TTFB ~50 ms partout dans le monde et charge serveur origine divisée par 10. La purge se synchronise automatiquement à chaque modification de contenu (save_post).'
                : 'Caches HTML at the Cloudflare edge so pages are served from the nearest CF node. Result: TTFB ~50ms worldwide and origin server load divided by 10. Purge syncs automatically on every content change (save_post).'); ?>
        </div>
        <?php if (!$state) : ?>
            <div style="margin-top:10px;padding:8px 12px;background:#fef3c7;border-radius:4px;font-size:12px;color:#92400e">
                <?php echo esc_html($is_fr_apo ? '⚠ Activez d\'abord l\'intégration Cloudflare ci-dessus pour pouvoir utiliser l\'APO.' : '⚠ Enable Cloudflare integration above first to use APO.'); ?>
            </div>
        <?php else : ?>
            <div class="lwsop_phase2_inputs" style="margin-top:12px">
                <label style="display:block;margin-bottom:6px">
                    <span style="display:inline-block;width:140px;font-size:13px"><?php echo esc_html($is_fr_apo ? 'Zone ID Cloudflare :' : 'Cloudflare Zone ID:'); ?></span>
                    <input type="text" id="lwsop_cf_apo_zone_id" placeholder="abc123def456..." style="width:340px;padding:5px;font-family:monospace;font-size:12px" value="<?php echo esc_attr($apo_zone_id); ?>">
                </label>
                <label style="display:block;margin-bottom:6px">
                    <span style="display:inline-block;width:140px;font-size:13px"><?php echo esc_html($is_fr_apo ? 'Token API :' : 'API Token:'); ?></span>
                    <input type="password" id="lwsop_cf_apo_token" placeholder="••••••••" style="width:340px;padding:5px;font-family:monospace;font-size:12px" value="<?php echo esc_attr($apo_token); ?>">
                </label>
                <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                    <button type="button" class="lwsop_darkblue_button" id="lwsop_cf_apo_install_rule">
                        <span><?php echo esc_html($is_fr_apo ? 'Installer la Cache Rule sur Cloudflare' : 'Install Cache Rule on Cloudflare'); ?></span>
                    </button>
                    <span id="lwsop_cf_apo_status" style="font-size:12px;color:#64748b"></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_cloudflare_apo_check">
            <input type="checkbox" id="lws_optimize_cloudflare_apo_check" <?php echo $apo_state ? 'checked' : ''; ?> <?php echo $state ? '' : 'disabled'; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<script>
(function(){
    // 4.4.3 — Cloudflare APO branché sur le mécanisme natif (compteur sticky-bar).
    // Le toggle suit le pattern lws_optimize_*_check, donc handler natif le pickup.
    // Les credentials (zone_id, api_token) sont stockés en "extra" du localStorage,
    // appliqués par le handler PHP étendu côté backend.
    var TOGGLE_ID  = 'lws_optimize_cloudflare_apo_check';
    var STORE_KEY  = 'lws_optimize_current_configuration_changes';
    var apoToggle  = document.getElementById(TOGGLE_ID);
    var apoZone    = document.getElementById('lwsop_cf_apo_zone_id');
    var apoToken   = document.getElementById('lwsop_cf_apo_token');
    var apoInst    = document.getElementById('lwsop_cf_apo_install_rule');
    var apoStat    = document.getElementById('lwsop_cf_apo_status');
    if (!apoToggle) return;

    function pushToCounter() {
        try {
            var cfg = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
            var extra = {
                zone_id:   apoZone  ? apoZone.value  : '',
                api_token: apoToken ? apoToken.value : '',
            };
            var idx = cfg.findIndex(function(it){ return it.type === TOGGLE_ID; });
            var entry = { type: TOGGLE_ID, state: apoToggle.checked, extra: extra };
            if (idx === -1) cfg.push(entry); else cfg[idx] = entry;
            localStorage.setItem(STORE_KEY, JSON.stringify(cfg));
            var counter = document.getElementById('lws_optimize_amount_configuration_elements');
            if (counter) counter.innerHTML = cfg.length;
            var btn = document.getElementById('lws_optimize_validate_changes');
            if (btn) btn.disabled = cfg.length === 0;
        } catch (e) {}
    }
    // Quand l'utilisateur édite zone_id ou api_token, on met à jour le compteur natif
    if (apoZone)  apoZone.addEventListener('input',  pushToCounter);
    if (apoToken) apoToken.addEventListener('input', pushToCounter);

    // Bouton "Installer la Cache Rule" : action ponctuelle, reste séparé. Sauve
    // d'abord les credentials via mécanisme natif puis push la rule.
    if (apoInst) apoInst.addEventListener('click', function(){
        if (!apoStat) return;
        apoStat.textContent = '<?php echo $is_fr_apo ? "Installation en cours…" : "Installing…"; ?>';
        apoStat.style.color = '#64748b';
        var fd = new FormData();
        fd.append('action', 'lwsop_cloudflare_install_cache_rule');
        fd.append('_ajax_nonce', '<?php echo wp_create_nonce('lwsop_cf_install'); ?>');
        fd.append('zone_id',   apoZone  ? apoZone.value  : '');
        fd.append('api_token', apoToken ? apoToken.value : '');
        fetch(ajaxurl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(j){
                if (j.success) {
                    apoStat.textContent='✓ <?php echo $is_fr_apo ? "Cache Rule installée" : "Cache Rule installed"; ?>';
                    apoStat.style.color='#16a34a';
                    if (typeof callPopup === 'function') callPopup('success', '<?php echo $is_fr_apo ? "Cache Rule Cloudflare installée" : "Cloudflare Cache Rule installed"; ?>');
                } else {
                    var code = (j.data && j.data.code) ? j.data.code : 'error';
                    apoStat.textContent='✗ '+code;
                    apoStat.style.color='#dc2626';
                    if (typeof callPopup === 'function') callPopup('error', '<?php echo $is_fr_apo ? "Échec install Cache Rule : " : "Cache Rule install failed: "; ?>' + code);
                }
            });
    });
})();
</script>

<div class="modal fade" id="lws_optimize_cloudflare_manage" tabindex='-1'>
    <div class="modal-dialog lws_optimize_image_conversion_modal_dialog">
        <div id="lws_optimize_cdn_contentmodal" class="modal-content lws_optimize_image_conversion_modal_content" style="padding: 30px;"></div>
    </div>
</div>

<div class="modal fade" id="lws_optimize_cloudflare_warning" tabindex='-1' role='dialog' aria-hidden='true'>
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

<script>
    function lws_optimize_cloudflare_configuration(checkbox) {
        let checked = checkbox.checked;
        // Do not update the checkbox yet
        checkbox.checked = !checked;

        let modal = document.getElementById('lws_optimize_cloudflare_manage');
        let modal_content = document.getElementById('lws_optimize_cdn_contentmodal');

        if (!modal_content) {
            console.error('Modal content element not found');
            return;
        }

        if (!checked) {
            // CF integration is currently active
            modal_content.innerHTML = `
                <h2 class="lwsop_exclude_title"><?php esc_html_e('CloudFlare Integration', 'lws-optimize'); ?></h2>
                <div class="lwsop_blue_info"><?php esc_html_e('LWS Optimize is currently integrated with CloudFlare. Would you like to terminate this connection?', 'lws-optimize'); ?></div>

                <div class="lwsop_modal_buttons">
                    <button class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button class="lws_optimize_cloudflare_next" onclick="lws_optimize_disconnect_cloudflare(this)"><?php echo esc_html_e('Deactivate', 'lws-optimize'); ?></button>
                </div>
            `;
        } else {
            // CF integration is currently inactive
            modal_content.innerHTML = `
                <h2 class="lwsop_exclude_title"><?php esc_html_e('CloudFlare Integration', 'lws-optimize'); ?></h2>
                <div class="lwsop_blue_info"><?php esc_html_e('Enter your API Token below to allow LWS Optimize access to the CloudFlare API', 'lws-optimize'); ?></div>

                <label class="cloudflare_token_label">
                    <span class="cloudflare_token_label_text"><?php esc_html_e('API Token', 'lws-optimize'); ?></span>
                    <input class="cloudflare_token_input" name="lws_optimize_cloudflare_token_api" required>
                </label>

                <div class="lwsop_modal_buttons">
                    <button class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button class="lws_optimize_cloudflare_next" onclick="lws_optimize_verify_cloudflare_connexion(this)"><?php echo esc_html_e('Verify', 'lws-optimize'); ?></button>
                </div>
            `;
        }

        // Show the modal now that the content is set
        jQuery(modal).modal('show');
    }

    function lws_optimize_disconnect_cloudflare(button) {
        let modal = document.getElementById('lws_optimize_cloudflare_manage');

        let originalText = '';
        if (button) {
            button.disabled = true;
            originalText = button.innerHTML;
            button.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
        }

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_complete_cf_deactivation_nonce')); ?>',
                action: "lws_optimize_cloudflare_deactivation",
            },
            success: function(data) {
                button.disabled = false;
                button.innerHTML = originalText;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "<?php esc_html_e('Bad data returned. Please try again', 'lws-optimize'); ?>");
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "<?php esc_html_e('Bad data returned. Please try again', 'lws-optimize'); ?>");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        callPopup('success', "<?php esc_html_e("Cloudflare integration has been deactivated", "lws-optimize"); ?>");
                        // Update the checkbox state
                        let checkbox = document.getElementById('lwsop_cloudflare_manage');
                        checkbox.checked = false;

                        // Close the modal
                        jQuery(modal).modal('hide');
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned.", "lws-optimize"); ?>");
                        break;
                }
            },
            error: function(error) {
                button.disabled = false;
                button.innerHTML = originalText;
                callPopup('error', "<?php esc_html_e("Unknown error.", "lws-optimize"); ?>");
                console.log(error);
            }
        });
    }

    function lws_optimize_verify_cloudflare_connexion(button) {
        let originalText = '';
        if (button) {
            button.disabled = true;
            originalText = button.innerHTML;
            button.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
        }

        let token_api = document.querySelector('input[name="lws_optimize_cloudflare_token_api"]').value;

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                key: token_api,
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_check_cloudflare_key_nonce')); ?>',
                action: "lws_optimize_check_cloudflare_key",
            },
            success: function(data) {
                button.disabled = false;
                button.innerHTML = originalText;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "<?php esc_html_e('Bad data returned. Please try again', 'lws-optimize'); ?>");
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "<?php esc_html_e('Bad data returned. Please try again', 'lws-optimize'); ?>");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        let infos = returnData['data'];
                        lws_optimize_cloudflare_verified_infos(infos);
                        callPopup('success', "<?php esc_html_e("Token verified. A zone has been found.", "lws-optimize"); ?>");
                        break;
                    case 'NO_PARAM':
                        callPopup('error', "<?php esc_html_e("No API Token provided.", "lws-optimize"); ?>");
                        break;
                    case 'ERROR_CURL':
                        callPopup('error', "<?php esc_html_e("Unable to connect to Cloudflare. Please try again.", "lws-optimize"); ?>");
                        break;
                    case 'ERROR_DECODE':
                        callPopup('error', "<?php esc_html_e("Unable to connect to Cloudflare. Please try again.", "lws-optimize"); ?>");
                        break;
                    case 'INACTIVE_TOKEN':
                        callPopup('error', "<?php esc_html_e("The token is inactive. Please check your Cloudflare account.", "lws-optimize"); ?>");
                        break;
                    case 'ERROR_CURL_ZONES':
                        callPopup('error', "<?php esc_html_e('Unable to connect to Cloudflare. Please check your API Token.', 'lws-optimize'); ?>");
                        break;
                    case 'ERROR_DECODE_ZONES':
                        callPopup('error', "<?php esc_html_e('Unable to read zones from Cloudflare. Please try again.', 'lws-optimize'); ?>");
                        break;
                    case 'REQUEST_ZONE_FAILED':
                        callPopup('error', "<?php esc_html_e('Unable to retrieve zones from Cloudflare. Please try again.', 'lws-optimize'); ?>");
                        break;
                    case 'NO_ZONE':
                        callPopup('error', "<?php esc_html_e('No zone were found for this token. Make sure the domain has been linked to your account', 'lws-optimize'); ?>");
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned.", "lws-optimize"); ?>");
                        break;
                }
            },
            error: function(error) {
                button.disabled = false;
                button.innerHTML = originalText;
                callPopup('error', "<?php esc_html_e("Unknown error.", "lws-optimize"); ?>");
                console.log(error);
            }
        });
    }

    function lws_optimize_cloudflare_verified_infos(zone_infos) {
        let modal = document.getElementById('lws_optimize_cloudflare_manage');
        let modal_content = document.getElementById('lws_optimize_cdn_contentmodal');

        // Extract info from zone_infos object
        let zone = {
            apiToken: zone_infos.api_token,
            name: zone_infos.name,
            id: zone_infos.id,
            account: zone_infos.account,
            accountName: zone_infos.account_name,
            status: zone_infos.status,
            nameServers: zone_infos.name_servers,
            originalNameServers: zone_infos.original_name_servers,
            type: zone_infos.type
        };

        if (!modal_content) {
            console.error('Modal content element not found');
            return;
        }

        modal_content.innerHTML = `
            <h2 class="lwsop_exclude_title"><?php esc_html_e('CloudFlare Zone found', 'lws-optimize'); ?></h2>
            <div class="lwsop_blue_info"><?php esc_html_e('A zone matching your domain has been found. Make sure to read the instructions before validating', 'lws-optimize'); ?></div>

            <div class="cloudflare_info_block">
                <div class="cloudflare_info_row">
                    <span class="info_label"><?php esc_html_e('Domain:', 'lws-optimize'); ?></span>
                    <span class="info_value">${zone.name}</span>
                </div>
                <div class="cloudflare_info_row">
                    <span class="info_label"><?php esc_html_e('Status:', 'lws-optimize'); ?></span>
                    <span class="info_value">${zone.status}</span>
                </div>
                <div class="cloudflare_info_row">
                    <span class="info_label"><?php esc_html_e('Name Servers:', 'lws-optimize'); ?></span>
                    <span class="info_value">${zone.nameServers.join(', ')}</span>
                </div>
            </div>

            <div class="cloudflare_info_recap">
                <ul>
                    <li>
                    <?php esc_html_e('CSS and JS minification will be deactivated as Cloudflare already handles this optimization', 'lws-optimize'); ?>
                    </li>
                    <li>
                    <?php esc_html_e('Cloudflare browser cache TTL will be set to match the duration of the filecache', 'lws-optimize'); ?>
                    </li>
                    <li>
                    <?php esc_html_e('Cloudflare cache will be automatically purged when clearing LWS Optimize cache', 'lws-optimize'); ?>
                    </li>
                    <li>
                    <?php esc_html_e('Cloudflare Dev Mode will be manageable from the website', 'lws-optimize'); ?>
                    </li>
                </ul>
            </div>

            <div class="lwsop_modal_buttons">
                <button class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button class="lws_optimize_cloudflare_next" id="lws_optimize_cloudflare_finish"><?php echo esc_html_e('Finish', 'lws-optimize'); ?></button>
            </div>
        `;

        // Add event listener to the button
        let finishButton = document.getElementById('lws_optimize_cloudflare_finish');
        if (finishButton) {
            finishButton.addEventListener('click', function() {
                let button = this;
                let originalText = '';
                button.disabled = true;
                originalText = button.innerHTML;
                button.innerHTML = `
                    <span name="loading" style="padding-left:5px">
                        <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                    </span>
                `;

                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        zone: zone,
                        _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_complete_cf_integration_nonce')); ?>',
                        action: "lws_optimize_complete_cloudflare_integration",
                    },
                    success: function(data) {
                        button.disabled = false;
                        button.innerHTML = originalText;

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
                                callPopup('success', `<?php esc_html_e('Cloudflare integration has been activated', 'lws-optimize'); ?>`);
                                // Update the checkbox state
                                let checkbox = document.getElementById('lwsop_cloudflare_manage');
                                checkbox.checked = true;

                                // Close the modal
                                jQuery(modal).modal('hide');
                                break;
                            case 'NO_PARAM':
                                callPopup('error', `<?php esc_html_e('No Zone or Token API found', 'lws-optimize'); ?>`);
                                break;
                            case 'ERROR_CURL_TTL':
                                callPopup('error', `<?php esc_html_e('Unable to connect to Cloudflare. Please try again.', 'lws-optimize'); ?>`);
                                break;
                            case 'ERROR_DECODE_TTL':
                                callPopup('error', `<?php esc_html_e('Unable to read TTL from Cloudflare. Please try again.', 'lws-optimize'); ?>`);
                                break;
                            case 'REQUEST_CF_FAILED':
                                callPopup('error', `<?php esc_html_e('Unable to set TTL on Cloudflare. Please try again.', 'lws-optimize'); ?>`);
                                break;
                            default:
                                callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                                break;
                        }
                    },
                    error: function(error) {
                        button.disabled = false;
                        button.innerHTML = originalText;

                        console.log(error);
                        callPopup('error', `<?php esc_html_e('An unknown error occured', 'lws-optimize'); ?>`);
                    }
                });
            });
        }

        // Show the modal now that the content is set
        jQuery(modal).modal('show');
    }
</script>
