<?php
if (!defined('ABSPATH')) exit;
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
            <?php esc_html_e('Cloudflare integration with LWS Optimize', 'lws-optimize'); ?>
            <a href="https://aide.lws.fr/a/1890" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('LWS Optimize is fully compatible with Cloudflare CDN. This integration prevent incompatibilities by modifying Cloudflare settings. Furthermore, it purges Cloudflare cache at the same time as LWS Optimize.', 'lws-optimize'); ?>
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
$apo_state       = ($config_array['cloudflare_apo']['state'] ?? 'false') === 'true';
$apo_zone_id     = $config_array['cloudflare_apo']['zone_id'] ?? ($config_array['cloudflare']['zone_id'] ?? '');
$apo_token       = $config_array['cloudflare_apo']['api_token'] ?? '';
$apo_installed_at = $config_array['cloudflare_apo']['rule_installed_at'] ?? null;
// Whether a Cache Rule has actually been pushed to Cloudflare — kept separate from
// $apo_state so the checkbox can be gated on it (see checkbox markup below).
$apo_installed   = !empty($apo_installed_at);
?>
<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <img src="<?php echo esc_url(plugins_url('images/cloudflare.svg', __DIR__)) ?>" alt="cloudflare icon" width="30px" height="30px">
            <?php esc_html_e('Cloudflare APO', 'lws-optimize'); ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
            <a href="https://aide.lws.fr/a/" rel="noopener" target="_blank" title="<?php esc_attr_e('Delivers your pages from the Cloudflare location closest to each visitor, so your site loads quickly wherever they are. Requires a Cloudflare account and an API token with cache permissions.', 'lws-optimize'); ?>">
                <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="<?php esc_attr_e('Learn more', 'lws-optimize'); ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top">
            </a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Keeps a copy of your pages on Cloudflare\'s network so they load faster for visitors anywhere in the world. This copy updates automatically every time you publish or edit content.', 'lws-optimize'); ?>
        </div>
        <div id="lwsop_cf_apo_locked_notice" style="margin-top:10px;padding:8px 12px;background:#fef3c7;border-radius:4px;font-size:12px;color:#92400e<?php echo $state ? ';display:none' : ''; ?>">
            <?php esc_html_e('⚠ Turn on the Cloudflare integration above first to use this feature.', 'lws-optimize'); ?>
        </div>
        <div class="lwsop_phase2_inputs" id="lwsop_cf_apo_fields" style="margin-top:12px<?php echo $state ? '' : ';display:none'; ?>">
            <label style="display:block;margin-bottom:6px">
                <span style="display:inline-block;width:140px;font-size:13px"><?php esc_html_e('Cloudflare Zone ID:', 'lws-optimize'); ?></span>
                <input type="text" id="lwsop_cf_apo_zone_id" placeholder="abc123def456..." style="width:340px;padding:5px;font-family:monospace;font-size:12px" value="<?php echo esc_attr($apo_zone_id); ?>">
            </label>
            <label style="display:block;margin-bottom:6px">
                <span style="display:inline-block;width:140px;font-size:13px"><?php esc_html_e('API Token:', 'lws-optimize'); ?></span>
                <input type="password" id="lwsop_cf_apo_token" placeholder="••••••••" style="width:340px;padding:5px;font-family:monospace;font-size:12px" value="<?php echo esc_attr($apo_token); ?>">
            </label>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                <span id="lwsop_cf_apo_status" style="font-size:12px;color:#16a34a"><?php
                    if ($apo_installed) {
                        $installed_label = __('✓ Turned on since', 'lws-optimize') . ' '
                            . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $apo_installed_at);
                        echo esc_html($installed_label);
                    }
                    // Off: no message.
                ?></span>
            </div>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_cloudflare_apo_check">
            <input type="checkbox" id="lws_optimize_cloudflare_apo_check" <?php echo $apo_state ? 'checked' : ''; ?> <?php echo (!$state) ? 'disabled' : ''; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<script>
(function(){
    // Cloudflare APO goes through the same "Save changes" batch flow as every
    // other checkbox: the generic handler in views/tabs.php (selector
    // input[id^="lws_optimize_"]) queues a bare {type, state} entry for this
    // checkbox on 'change', and lws_optimize_manage_config_delayed()
    // installs/removes the Cache Rule when the admin actually clicks "Save
    // changes". We layer on top of that to (a) attach the Zone ID/API token
    // as the entry's "extra" payload (the generic handler doesn't know about
    // those fields) and (b) treat editing either field — even without
    // touching the checkbox — as a pending change of its own, cleared again
    // if the fields are brought back to their saved values.
    //
    // The status line ("✓ Turned on since …") is rendered once by PHP from
    // the DB state and intentionally left alone here: it reflects what's
    // actually live on Cloudflare, not the not-yet-saved UI state.
    var TOGGLE_ID = 'lws_optimize_cloudflare_apo_check';
    var STORE_KEY = 'lws_optimize_current_configuration_changes';

    var apoToggle = document.getElementById(TOGGLE_ID);
    var apoZone   = document.getElementById('lwsop_cf_apo_zone_id');
    var apoToken  = document.getElementById('lwsop_cf_apo_token');
    if (!apoToggle) return;

    // DB-confirmed baseline. Only advances when tabs.php's save handler
    // actually clears the pending-changes store (see the localStorage
    // override below) — not on our own housekeeping writes.
    var savedToggle = apoToggle.checked;
    var savedZone   = apoZone  ? apoZone.value  : '';
    var savedToken  = apoToken ? apoToken.value : '';

    // True while a STORE_KEY write is our own bookkeeping (or the native
    // handler's, triggered by the same checkbox 'change' event) rather than
    // tabs.php's save-success handler clearing everything after a real save.
    var housekeeping = false;

    function isDirty() {
        return apoToggle.checked !== savedToggle
            || (apoZone  ? apoZone.value  : '') !== savedZone
            || (apoToken ? apoToken.value : '') !== savedToken;
    }

    function refreshCounter(cfg) {
        var el = document.getElementById('lws_optimize_amount_configuration_elements');
        if (el) el.innerHTML = cfg.length;
        var btn = document.getElementById('lws_optimize_validate_changes');
        if (btn) btn.disabled = cfg.length === 0;
    }

    // Reconciles STORE_KEY with the current UI: upserts a full entry (state +
    // extra) while anything differs from the saved baseline, removes it once
    // everything matches again.
    function syncEntry() {
        housekeeping = true;
        try {
            var cfg = JSON.parse(localStorage.getItem(STORE_KEY) || '[]');
            var idx = cfg.findIndex(function(item){ return item.type === TOGGLE_ID; });
            if (isDirty()) {
                var entry = {
                    type: TOGGLE_ID,
                    state: apoToggle.checked,
                    extra: {
                        zone_id:   apoZone  ? apoZone.value.trim()  : '',
                        api_token: apoToken ? apoToken.value.trim() : '',
                    },
                };
                if (idx === -1) cfg.push(entry); else cfg[idx] = entry;
            } else if (idx !== -1) {
                cfg.splice(idx, 1);
            }
            localStorage.setItem(STORE_KEY, JSON.stringify(cfg));
            refreshCounter(cfg);
        } catch (e) {}
        housekeeping = false;
    }

    apoToggle.addEventListener('change', function(event){
        if (apoToggle.checked) {
            var zoneVal  = apoZone  ? apoZone.value.trim()  : '';
            var tokenVal = apoToken ? apoToken.value.trim() : '';
            if (!zoneVal || !tokenVal) {
                // Not enough info to even queue the change — block it before the
                // generic handler (registered after this one, see views/tabs.php)
                // adds it to the pending-changes store.
                event.stopImmediatePropagation();
                apoToggle.checked = false;
                if (typeof callPopup === 'function') {
                    callPopup('error', <?php echo wp_json_encode(__('Please fill in your Cloudflare Zone ID and API token first', 'lws-optimize')); ?>);
                }
                return;
            }
        }
        // The native handler (views/tabs.php) reacts to this same 'change'
        // event right after this listener returns and does its own naive
        // push/pop of a bare {type, state} entry. Mark that write as
        // housekeeping too, then reconcile it into our full entry once it's
        // landed.
        housekeeping = true;
        setTimeout(syncEntry, 0);
    });

    if (apoZone)  apoZone.addEventListener('input', syncEntry);
    if (apoToken) apoToken.addEventListener('input', syncEntry);

    var _lsSetItem = localStorage.setItem.bind(localStorage);
    localStorage.setItem = function(key, value) {
        _lsSetItem(key, value);
        if (key !== STORE_KEY || housekeeping) return;
        try {
            var cfg = JSON.parse(value || '[]');
            if (!cfg.length) {
                savedToggle = apoToggle.checked;
                savedZone   = apoZone  ? apoZone.value  : '';
                savedToken  = apoToken ? apoToken.value : '';
            }
        } catch (e) {}
    };

    window.lwsop_cf_apo_sync_lock_state = function(baseActive) {
        var notice = document.getElementById('lwsop_cf_apo_locked_notice');
        var fields = document.getElementById('lwsop_cf_apo_fields');
        if (notice) notice.style.display = baseActive ? 'none' : '';
        if (fields) fields.style.display = baseActive ? '' : 'none';
        apoToggle.disabled = !baseActive;
    };
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
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php esc_html_e('Close', 'lws-optimize'); ?></button>
                <button type="button" class="lws_optimize_cloudflare_next" data-dismiss="modal" id="lwsop_goto_cloudflare_integration"><?php esc_html_e('Go to the option', 'lws-optimize'); ?></button>
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
                    <button class="lwsop_closebutton" data-dismiss="modal"><?php esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button class="lws_optimize_cloudflare_next" onclick="lws_optimize_disconnect_cloudflare(this)"><?php esc_html_e('Deactivate', 'lws-optimize'); ?></button>
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
                    <button class="lwsop_closebutton" data-dismiss="modal"><?php esc_html_e('Abort', 'lws-optimize'); ?></button>
                    <button class="lws_optimize_cloudflare_next" onclick="lws_optimize_verify_cloudflare_connexion(this)"><?php esc_html_e('Verify', 'lws-optimize'); ?></button>
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
            success: function(returnData) {
                button.disabled = false;
                button.innerHTML = originalText;

                if (!isValidResponse(returnData)) {
                    console.error('Invalid AJAX response', returnData);
                    return;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        callPopup('success', "<?php esc_html_e("Cloudflare integration has been deactivated", "lws-optimize"); ?>");
                        // Update the checkbox state
                        let checkbox = document.getElementById('lwsop_cloudflare_manage');
                        checkbox.checked = false;

                        // Re-lock the APO section immediately (no refresh needed)
                        if (typeof window.lwsop_cf_apo_sync_lock_state === 'function') {
                            window.lwsop_cf_apo_sync_lock_state(false);
                        }

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
            success: function(returnData) {
                button.disabled = false;
                button.innerHTML = originalText;

                if (!isValidResponse(returnData)) {
                    console.error('Invalid AJAX response', returnData);
                    return;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        let infos = returnData['data'];
                        // The token is never returned by the server; re-attach the one held here.
                        infos.api_token = token_api;
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
                <button class="lwsop_closebutton" data-dismiss="modal"><?php esc_html_e('Abort', 'lws-optimize'); ?></button>
                <button class="lws_optimize_cloudflare_next" id="lws_optimize_cloudflare_finish"><?php esc_html_e('Finish', 'lws-optimize'); ?></button>
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
                    success: function(returnData) {
                        button.disabled = false;
                        button.innerHTML = originalText;

                        if (!isValidResponse(returnData)) {
                            console.error('Invalid AJAX response', returnData);
                            return;
                        }

                        switch (returnData['code']) {
                            case 'SUCCESS':
                                callPopup('success', `<?php esc_html_e('Cloudflare integration has been activated', 'lws-optimize'); ?>`);
                                // Update the checkbox state
                                let checkbox = document.getElementById('lwsop_cloudflare_manage');
                                checkbox.checked = true;

                                // Unlock the APO section immediately (no refresh needed) and
                                // prefill its Zone ID from the zone we just verified, if empty.
                                if (typeof window.lwsop_cf_apo_sync_lock_state === 'function') {
                                    window.lwsop_cf_apo_sync_lock_state(true);
                                }
                                let apoZoneField = document.getElementById('lwsop_cf_apo_zone_id');
                                if (apoZoneField && !apoZoneField.value) {
                                    apoZoneField.value = zone.id;
                                }

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
