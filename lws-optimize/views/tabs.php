<div class="lwsoptimize_main_content">
    <?php if ($is_deactivated) : ?>
        <div class="lwsoptimize_main_content_fogged"></div>
    <?php endif ?>
    <div class="tab_lwsoptimize" id='tab_lwsoptimize_block'>
        <div id="tab_lwsoptimize" role="tablist" aria-label="Onglets_lwsoptimize">
            <?php foreach ($tabs_list as $tab) : ?>
                <button id="<?php echo esc_attr('nav-' . $tab[0]); ?>" class="tab_nav_lwsoptimize <?php echo $tab[0] == 'frontend' ? esc_attr('active') : ''; ?>" data-toggle="tab" role="tab" aria-controls="<?php echo esc_attr($tab[0]); ?>" aria-selected="<?php echo $tab[0] == 'frontend' ? esc_attr('true') : esc_attr('false'); ?>" tabindex="<?php echo $tab[0] == 'frontend' ? esc_attr('0') : '-1'; ?>">
                    <?php echo esc_html($tab[1]); ?>
                </button>
            <?php endforeach ?>
            <div id="selector" class="selector_tab"></div>
        </div>

        <div class="tab_lws_op_select hidden">
            <select name="tab_lws_op_select" id="tab_lws_op_select" style="text-align:center">
                <?php foreach ($tabs_list as $tab) : ?>
                    <option value="<?php echo esc_attr("nav-" . $tab[0]); ?>">
                        <?php echo esc_html($tab[1]); ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
    </div>

    <?php foreach ($tabs_list as $tab) : ?>
        <div class="tab-pane main-tab-pane" id="<?php echo esc_attr($tab[0]) ?>" role="tabpanel" aria-labelledby="nav-<?php echo esc_attr($tab[0]) ?>" <?php echo $tab[0] == 'frontend' ? esc_attr('tabindex="0"') : esc_attr('tabindex="-1" hidden') ?>>
            <div id="post-body-<?php echo $tab[0]; ?>" class="<?php echo $tab[0] == 'plugins' ? esc_attr('lws_op_configpage_plugin') : esc_attr('lws_op_configpage'); ?> ">
                <?php if ($is_deactivated) : ?>
                    <?php echo ($tab[0] == 'plugins' || $tab[0] == 'pagespeed') ? '' : '<div class="deactivated_plugin_state"></div>'; ?>
                <?php endif ?>
                <?php include_once plugin_dir_path(__FILE__) . $tab[0] . '.php'; ?>
            </div>
        </div>
    <?php endforeach ?>
</div>

<script>
    const tabs = document.querySelectorAll('.tab_nav_lwsoptimize[role="tab"]');

    // Add a click event handler to each tab
    tabs.forEach((tab) => {
        tab.addEventListener('click', lwsoptimize_changeTabs);
    });

    lwsoptimize_selectorMove(document.getElementById('nav-frontend'), document.getElementById('nav-frontend').parentNode);

    function lwsoptimize_selectorMove(target, parent) {
        const cursor = document.getElementById('selector');
        var element = target.getBoundingClientRect();
        var bloc = parent.getBoundingClientRect();

        var padding = parseInt((window.getComputedStyle(target, null).getPropertyValue('padding-left')).slice(0, -2));
        var margin = parseInt((window.getComputedStyle(target, null).getPropertyValue('margin-left')).slice(0, -2));
        var begin = (element.left - bloc.left) - margin;
        var ending = target.clientWidth + 2 * margin;

        cursor.style.width = ending + "px";
        cursor.style.left = begin + "px";
    }

    function lwsoptimize_changeTabs(e) {
        var target;
        if (e.target === undefined) {
            target = e;
        } else {
            target = e.target;
        }
        const parent = target.parentNode;
        const grandparent = parent.parentNode.parentNode;

        // Remove all current selected tabs
        parent
            .querySelectorAll('.tab_nav_lwsoptimize[aria-selected="true"]')
            .forEach(function(t) {
                t.setAttribute('aria-selected', false);
                t.classList.remove("active")
            });

        // Set this tab as selected
        target.setAttribute('aria-selected', true);
        target.classList.add('active');

        // Hide all tab panels
        grandparent
            .querySelectorAll('.tab-pane.main-tab-pane[role="tabpanel"]')
            .forEach((p) => p.setAttribute('hidden', true));

        // Show the selected panel
        grandparent.parentNode
            .querySelector(`#${target.getAttribute('aria-controls')}`)
            .removeAttribute('hidden');


        lwsoptimize_selectorMove(target, parent);
    }

    jQuery(document).ready(function() {
        <?php foreach ($plugins_activated as $slug => $activated) : ?>
            <?php if ($activated == "full") : ?>
                /**/
                var button = jQuery(
                    "<?php echo esc_attr("#bis_" . $slug); ?>"
                );
                if (button){
                    button.children()[3].classList.remove('hidden');
                    button.children()[0].classList.add('hidden');
                    button.prop('onclick', false);
                    button.addClass('lws_op_button_ad_block_validated');
                }

            <?php elseif ($activated == "half") : ?>
                /**/
                var button = jQuery(
                    "<?php echo esc_attr("#bis_" . $slug); ?>"
                );
                if (button) {
                    button.children()[2].classList.remove('hidden');
                    button.children()[0].classList.add('hidden');
                }
            <?php endif ?>
        <?php endforeach ?>
    });

    function install_plugin(button) {
        var newthis = this;
        if (this.function_ok) {
            this.function_ok = false;
            const regex = /bis_/;
            bouton_id = button.id;
            bouton_sec = "";
            if (bouton_id.match(regex)) {
                bouton_sec = bouton_id.substring(4);
            } else {
                bouton_sec = "bis_" + bouton_id;
            }

            button_sec = document.getElementById(bouton_sec);

            button.children[0].classList.add('hidden');
            button.children[3].classList.add('hidden');
            button.children[2].classList.add('hidden');
            button.children[1].classList.remove('hidden');
            button.classList.remove('lws_op_button_ad_block_validated');
            button.setAttribute('disabled', true);

            if (button_sec !== null) {
                button_sec.children[0].classList.add('hidden');
                button_sec.children[3].classList.add('hidden');
                button_sec.children[2].classList.add('hidden');
                button_sec.children[1].classList.remove('hidden');
                button_sec.classList.remove('lws_op_button_ad_block_validated');
                button_sec.setAttribute('disabled', true);
            }

            var data = {
                action: "lws_op_downloadPlugin",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('updates')); ?>',
                slug: button.getAttribute('value'),
            };
            jQuery.post(ajaxurl, data, function(response) {
                if (!response.success) {
                    if (response.data.errorCode == 'folder_exists') {
                        var data = {
                            action: "lws_op_activatePlugin",
                            ajax_slug: response.data.slug,
                            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('activate_plugin')); ?>',
                        };
                        jQuery.post(ajaxurl, data, function(response) {
                            jQuery('#' + bouton_id).children()[1].classList.add('hidden');
                            jQuery('#' + bouton_id).children()[2].classList.add('hidden');
                            jQuery('#' + bouton_id).children()[3].classList.remove('hidden');
                            jQuery('#' + bouton_id).addClass('lws_op_button_ad_block_validated');
                            newthis.function_ok = true;

                            if (button_sec !== null) {
                                jQuery('#' + bouton_sec).children()[1].classList.add('hidden');
                                jQuery('#' + bouton_sec).children()[2].classList.add('hidden');
                                jQuery('#' + bouton_sec).children()[3].classList.remove('hidden');
                                jQuery('#' + bouton_sec).addClass(
                                    'lws_op_button_ad_block_validated');
                                newthis.function_ok = true;
                            }
                        });

                    } else {
                        jQuery('#' + bouton_id).children()[1].classList.add('hidden');
                        jQuery('#' + bouton_id).children()[2].classList.add('hidden');
                        jQuery('#' + bouton_id).children()[3].classList.add('hidden');
                        jQuery('#' + bouton_id).children()[0].classList.add('hidden');
                        jQuery('#' + bouton_id).children()[4].classList.remove('hidden');
                        jQuery('#' + bouton_id).addClass('lws_op_button_ad_block_failed');
                        setTimeout(() => {
                            jQuery('#' + bouton_id).removeClass('lws_op_button_ad_block_failed');
                            jQuery('#' + bouton_id).prop('disabled', false);
                            jQuery('#' + bouton_id).children()[0].classList.remove('hidden');
                            jQuery('#' + bouton_id).children()[4].classList.add('hidden');
                            newthis.function_ok = true;
                        }, 2500);

                        if (button_sec !== null) {
                            jQuery('#' + bouton_sec).children()[1].classList.add('hidden');
                            jQuery('#' + bouton_sec).children()[2].classList.add('hidden');
                            jQuery('#' + bouton_sec).children()[3].classList.add('hidden');
                            jQuery('#' + bouton_sec).children()[0].classList.add('hidden');
                            jQuery('#' + bouton_sec).children()[4].classList.remove('hidden');
                            jQuery('#' + bouton_sec).addClass('lws_op_button_ad_block_failed');
                            setTimeout(() => {
                                jQuery('#' + bouton_sec).removeClass(
                                    'lws_op_button_ad_block_failed');
                                jQuery('#' + bouton_sec).prop('disabled', false);
                                jQuery('#' + bouton_sec).children()[0].classList.remove('hidden');
                                jQuery('#' + bouton_sec).children()[4].classList.add('hidden');
                                newthis.function_ok = true;
                            }, 2500);
                        }
                    }
                } else {
                    jQuery('#' + bouton_id).children()[1].classList.add('hidden');
                    jQuery('#' + bouton_id).children()[2].classList.remove('hidden');
                    jQuery('#' + bouton_id).prop('disabled', false);
                    newthis.function_ok = true;

                    if (button_sec !== null) {
                        jQuery('#' + bouton_sec).children()[1].classList.add('hidden');
                        jQuery('#' + bouton_sec).children()[2].classList.remove('hidden');
                        jQuery('#' + bouton_sec).prop('disabled', false);
                        newthis.function_ok = true;
                    }
                }
            });
        }
    }
</script>

<?php if (!$is_deactivated) : ?>
    <script>

        localStorage.setItem('lws_optimize_current_configuration_changes', JSON.stringify([]));

        // All checkbox, not the buttons (like preload fonts)
        document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function(event) {
                let element = this;
                let state = element.checked;
                let type = element.getAttribute('id');
                let button = document.getElementById("lws_optimize_validate_changes");

                let data = {
                    'state': state,
                    'type': type,
                };

                let current_configuration = JSON.parse(localStorage.getItem('lws_optimize_current_configuration_changes'));
                // Find existing entry with same type
                let existingIndex = current_configuration.findIndex(item => item.type === type);

                if (existingIndex !== -1) {
                    // Remove if exists
                    current_configuration.splice(existingIndex, 1);
                } else {
                    // Add if doesn't exist
                    current_configuration.push({
                        'type': type,
                        'state': state
                    });
                }
                localStorage.setItem('lws_optimize_current_configuration_changes', JSON.stringify(current_configuration));

                // Amount of configuration elements
                let amount_elements = current_configuration.length;
                let amount_elements_text = document.getElementById('lws_optimize_amount_configuration_elements');
                if (amount_elements_text) {
                    amount_elements_text.innerHTML = amount_elements;
                }

                if (button) {
                    if (amount_elements > 0) {
                        button.disabled = false;
                    } else {
                        button.disabled = true;
                    }
                }


                // document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                //     checks.disabled = true;
                // });

                // let ajaxRequest = jQuery.ajax({
                //     url: ajaxurl,
                //     type: "POST",
                //     timeout: 120000,
                //     context: document.body,
                //     data: {
                //         _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_checkboxes_config")); ?>",
                //         action: "lws_optimize_checkboxes_action",
                //         data: data
                //     },

                //     success: function(data) {
                //         element.disabled = false;
                //         document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                //             checks.disabled = false;
                //         });

                //         if (data === null || typeof data != 'string') {
                //             return 0;
                //         }

                //         try {
                //             var returnData = JSON.parse(data);
                //         } catch (e) {
                //             console.log(e);
                //             returnData = {
                //                 'code': "NOT_JSON",
                //                 'data': "FAIL"
                //             };
                //         }

                //         switch (returnData['code']) {
                //             case 'SUCCESS':
                //                 let status = returnData['data'] == "true" ? "<?php esc_html_e('activated', 'lws-optimize'); ?>" : "<?php esc_html_e('deactivated', 'lws-optimize'); ?>";
                //                 if (returnData['type'] == "maintenance_db") {
                //                     // Update the "Next conversion" value
                //                     lws_op_update_database_cleaner();
                //                 }
                //                 callPopup('success', "<?php esc_html_e('Option ', 'lws-optimize'); ?> " + status);
                //                 break;
                //             case 'MEMCACHE_NOT_WORK':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e("Memcached could not be activated. Make sure it is activated on your server.", "lws-optimize"); ?>");
                //                 break;
                //             case 'MEMCACHE_NOT_FOUND':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e("Memcached could not be found. Maybe your website is not compatible with it.", "lws-optimize"); ?>");
                //                 break;
                //             case 'REDIS_ALREADY_HERE':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e("Redis Cache is already active on this website and may cause incompatibilities with Memcached. Please deactivate Redis Cache to use Memcached.", "lws-optimize"); ?>");
                //                 break;
                //             case 'PANEL_CACHE_OFF':
                //                 element.checked = !state;
                //                 callPopup('warning', "<?php esc_html_e('LWSCache is not activated on this hosting. Please go to your LWSPanel and activate it.', 'lws-optimize'); ?>");
                //                 break;
                //             case 'CPANEL_CACHE_OFF':
                //                 element.checked = !state;
                //                 callPopup('warning', "<?php esc_html_e('FastestCache is not activated on this cPanel. Please go to your cPanel and activate it.', 'lws-optimize'); ?>");
                //                 break;
                //             case 'INCOMPATIBLE':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e('LWSCache is not available on this hosting. Please migrate to a LWS hosting to use this action.', 'lws-optimize'); ?>");
                //                 break;
                //             case 'NOT_JSON':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e('Bad server response. Could not change action state.', 'lws-optimize'); ?>");
                //                 break;
                //             case 'DATA_MISSING':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e('Not enough informations were sent to the server, please refresh and try again. Could not change action state.', 'lws-optimize'); ?>");
                //             case 'UNKNOWN_ID':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e('No matching action bearing this ID, please refresh and retry. Could not change action state.', 'lws-optimize'); ?>");
                //             case 'FAILURE':
                //                 element.checked = !state;
                //                 callPopup('error', "<?php esc_html_e('Could not save change to action state in the database.', 'lws-optimize'); ?>");
                //             default:
                //                 break;
                //         }
                //     },
                //     error: function(error) {
                //         element.disabled = false;
                //         document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                //             checks.disabled = false;
                //         });

                //         element.checked = !state;
                //         callPopup("error", "Une erreur inconnue est survenue. Impossible d'activer cette option.");
                //         console.log(error);
                //         return 1;
                //     }
                // });
            });
        });

        function lws_op_update_configuration(button) {
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
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_checkboxes_config")); ?>",
                    action: "lws_optimize_checkboxes_action_delayed",
                    data: JSON.parse(localStorage.getItem('lws_optimize_current_configuration_changes'))
                },

                success: function(data) {
                    button.disabled = true;
                    button.innerHTML = originalText;

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
                            let status = returnData['data'] == "true" ? "<?php esc_html_e('activated', 'lws-optimize'); ?>" : "<?php esc_html_e('deactivated', 'lws-optimize'); ?>";
                            callPopup('success', "<?php esc_html_e('Plugin configuration updated', 'lws-optimize'); ?>");


                            let current_configuration = JSON.parse(localStorage.getItem('lws_optimize_current_configuration_changes'));
                            localStorage.setItem('lws_optimize_current_configuration_changes', JSON.stringify([]));

                            let amount_elements_text = document.getElementById('lws_optimize_amount_configuration_elements');
                            if (amount_elements_text) {
                                amount_elements_text.innerHTML = 0;
                            }

                            if (returnData['errors'] && returnData['errors'].length > 0) {
                                let errors = returnData['errors'];
                                for (let i = 0; i < errors.length; i++) {
                                    switch (errors[i]) {
                                        case 'MEMCACHE_NOT_WORK':
                                            callPopup('error', "<?php esc_html_e("Memcached has been found but a connexion could not be made: Memcached server is not responding and cannot be activated.", "lws-optimize"); ?>");
                                            break;
                                        case 'MEMCACHE_NOT_FOUND':
                                            callPopup('error', "<?php esc_html_e("The Memcached module could not be found and no connexions could be made. Please make sure Memcached is activated on your server.", "lws-optimize"); ?>");
                                            break;
                                        case 'REDIS_ALREADY_HERE':
                                            callPopup('error', "<?php esc_html_e("Redis Cache is already active on this website and may cause incompatibilities with Memcached. Please deactivate Redis Cache to use Memcached.", "lws-optimize"); ?>");
                                            break;
                                        case 'PANEL_CACHE_OFF':
                                            callPopup('warning', "<?php esc_html_e('LWSCache is not activated on this hosting. Please go to your LWSPanel and activate it.', 'lws-optimize'); ?>");
                                            break;
                                        case 'CPANEL_CACHE_OFF':
                                            callPopup('warning', "<?php esc_html_e('VarnishCache is not activated on this cPanel. Please go to your cPanel and activate it.', 'lws-optimize'); ?>");
                                            break;
                                        case 'INCOMPATIBLE':
                                            callPopup('error', "<?php esc_html_e('LWSCache is not available on this hosting. Please migrate to a LWS hosting to use this action.', 'lws-optimize'); ?>");
                                            break;
                                        case 'HTACCESS_NOT_WRITABLE':
                                            callPopup('error', "<?php esc_html_e('The .htaccess file is not writable. Please check the permissions of this file.', 'lws-optimize'); ?>");
                                            break;
                                        case 'HTACCESS_WRITE_FAILED':
                                            callPopup('error', "<?php esc_html_e('The .htaccess file could not be updated. Please check the permissions of this file.', 'lws-optimize'); ?>");
                                            break;
                                        case 'HTACCESS_OPEN_FAILED':
                                            callPopup('error', "<?php esc_html_e('The .htaccess file could not be opened. Please check the permissions of this file.', 'lws-optimize'); ?>");
                                            break;
                                        case 'HTACCESS_UPDATE_FAILED':
                                            callPopup('error', "<?php esc_html_e('The .htaccess file could not be updated. Please check the permissions of this file.', 'lws-optimize'); ?>");
                                            break;
                                        default:
                                            break;
                                    }
                                }
                            }

                            // Check if preload cache is enabled and update the interface
                            let preload_cache_config = current_configuration.find(item => item.type === "lws_optimize_preload_cache_check");
                            if (preload_cache_config) {
                                let value = preload_cache_config.state;

                                if (value) {
                                    callPopup('success', "<?php esc_html_e("File-based cache is now preloading. Depending on the amount of URLs, it may take a few minutes for the process to be done.", "lws-optimize"); ?>");
                                    let p_info = document.getElementById('lwsop_current_preload_info');
                                    let p_done = document.getElementById('lwsop_current_preload_done');
                                    let p_next = document.getElementById('lwsop_next_preload_info');

                                    if (p_info != null) {
                                        p_info.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                    }
                                    var currentdate = new Date();
                                    var datetime = currentdate.getDate() + "-" +
                                        (currentdate.getMonth() + 1) + "-" +
                                        currentdate.getFullYear() + " " +
                                        currentdate.getHours() + ":" +
                                        currentdate.getMinutes() + ":" +
                                        currentdate.getSeconds();
                                    if (p_next != null) {
                                        p_next.innerHTML = datetime;
                                    }

                                    let block = document.getElementById('lwsop_preloading_status_block');
                                    if (block != null) {
                                        block.classList.remove('hidden');
                                    }

                                    if (p_done != null) {
                                        p_done.innerHTML = returnData['data']['filebased_cache']['preload_done'] + "/" + returnData['data']['filebased_cache']['preload_quantity']
                                    }
                                } else {
                                    let p_info = document.getElementById('lwsop_current_preload_info');
                                    let p_done = document.getElementById('lwsop_current_preload_done');


                                    if (p_info != null) {
                                        p_info.innerHTML = "<?php esc_html_e("Done", "lws-optimize"); ?>";
                                    }

                                    let block = document.getElementById('lwsop_preloading_status_block');
                                    if (block != null) {
                                        block.classList.add('hidden');
                                    }

                                    if (p_done != null) {
                                        p_done.innerHTML = returnData['data']['filebased_cache']['preload_done'] + "/" + returnData['data']['filebased_cache']['preload_quantity']
                                    }
                                    callPopup('success', "<?php esc_html_e("Preloading is now deactivated.", "lws-optimize"); ?>");
                                }
                            }
                            break;
                        case 'MEMCACHE_NOT_WORK':

                            callPopup('error', "<?php esc_html_e("Memcached could not be activated. Make sure it is activated on your server.", "lws-optimize"); ?>");
                            break;
                        case 'MEMCACHE_NOT_FOUND':

                            callPopup('error', "<?php esc_html_e("Memcached could not be found. Maybe your website is not compatible with it.", "lws-optimize"); ?>");
                            break;
                        case 'REDIS_ALREADY_HERE':

                            callPopup('error', "<?php esc_html_e("Redis Cache is already active on this website and may cause incompatibilities with Memcached. Please deactivate Redis Cache to use Memcached.", "lws-optimize"); ?>");
                            break;
                        case 'PANEL_CACHE_OFF':

                            callPopup('warning', "<?php esc_html_e('LWSCache is not activated on this hosting. Please go to your LWSPanel and activate it.', 'lws-optimize'); ?>");
                            break;
                        case 'CPANEL_CACHE_OFF':

                            callPopup('warning', "<?php esc_html_e('FastestCache is not activated on this cPanel. Please go to your cPanel and activate it.', 'lws-optimize'); ?>");
                            break;
                        case 'INCOMPATIBLE':

                            callPopup('error', "<?php esc_html_e('LWSCache is not available on this hosting. Please migrate to a LWS hosting to use this action.', 'lws-optimize'); ?>");
                            break;
                        case 'NOT_JSON':

                            callPopup('error', "<?php esc_html_e('Bad server response. Could not change action state.', 'lws-optimize'); ?>");
                            break;
                        case 'DATA_MISSING':

                            callPopup('error', "<?php esc_html_e('Not enough informations were sent to the server, please refresh and try again. Could not change action state.', 'lws-optimize'); ?>");
                        case 'UNKNOWN_ID':

                            callPopup('error', "<?php esc_html_e('No matching action bearing this ID, please refresh and retry. Could not change action state.', 'lws-optimize'); ?>");
                        case 'FAILURE':

                            callPopup('error', "<?php esc_html_e('Could not save change to action state in the database.', 'lws-optimize'); ?>");
                        default:
                            break;
                    }
                },
                error: function(error) {
                    button.disabled = false;
                    button.innerHTML = originalText;

                    callPopup("error", "Une erreur inconnue est survenue. Impossible d'activer cette option.");
                    console.log(error);
                    return 1;
                }
            });

        }

        // Open "exclude files" modal
        document.querySelectorAll('button[id$="_exclusion"]').forEach(function(button) {
            button.addEventListener('click', function(event) {
                let element = this;
                let type = element.getAttribute('id');
                let name = element.getAttribute('value');

                let data = {
                    'type': type,
                    'name': name
                };

                // Show the modale on screen with loading animation & modified title
                let title = document.getElementById('lws_optimize_exclusion_modale_title');
                let buttons = document.getElementById('lws_optimize_exclusion_modale_buttons');
                let form = document.getElementById('lws_optimize_exclusion_modale_form');
                form.innerHTML = `
                <div class="loading_animation">
                    <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                </div>
            `;
                buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            `;

                title.innerHTML = "<?php esc_html_e('Exclude from: ', 'lws-optimize'); ?>" + name;
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
                                buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_exclusion_form_fe" class="lwsop_validatebutton">
                                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                    <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                                </button>
                            `;

                                let urls = returnData['data'];
                                let site_url = returnData['domain'];
                                if (type == "lws_optimize_minify_html_exclusion") {
                                    form.innerHTML = `
                                    <input type="hidden" name="lwsoptimize_exclude_url_id" value="` + type + `">
                                    <div class="lwsop_modal_infobubble">
                                        <?php esc_html_e('Here, you can exclude URLs you do not want minified. Example : "my-site.fr/holidays/*" will exclude all subpages of "holidays" from the minification.', 'lws-optimize'); ?>
                                    </div>
                                `;
                                } else {
                                    form.innerHTML = `
                                    <input type="hidden" name="lwsoptimize_exclude_url_id" value="` + type + `">
                                    <div class="lwsop_modal_infobubble">
                                        <?php esc_html_e('Enter the full URL, or part of it, of the scripts/stylesheets you want to exclude from the process. Example : "plugins/woocommerce/*" will exclude all files in the directory "woocommerce" in plugins.', 'lws-optimize'); ?>
                                    </div>
                                `;
                                }

                                if (!urls.length) {
                                    form.insertAdjacentHTML('beforeend', `
                                    <div class="lwsoptimize_exclude_element">
                                        <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="">
                                        <div class="lwsoptimize_exclude_action_buttons">
                                            <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                            <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                        </div>
                                    </div>
                                `);
                                } else {
                                    for (var i in urls) {
                                        form.insertAdjacentHTML('beforeend', `
                                        <div class="lwsoptimize_exclude_element">
                                            <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="` + urls[i] + `">
                                            <div class="lwsoptimize_exclude_action_buttons">
                                                <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                                <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                            </div>
                                        </div>
                                    `);
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

                jQuery("#lws_optimize_exclusion_modale").modal('show');
            });
        });

        // Open "preloading files" modal
        if (document.getElementById('lws_op_add_to_preload_files') !== null) {
            document.getElementById('lws_op_add_to_preload_files').addEventListener('click', function(event) {
                let element = this;

                // Show the modale on screen with loading animation & modified title
                let title = document.getElementById('lws_optimize_exclusion_modale_title');
                let buttons = document.getElementById('lws_optimize_exclusion_modale_buttons');
                let form = document.getElementById('lws_optimize_exclusion_modale_form');
                form.innerHTML = `
                <div class="loading_animation">
                    <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                </div>
            `;
                buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            `;

                title.innerHTML = "<?php esc_html_e('Add files to preload', 'lws-optimize'); ?>";
                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_preloading_url_files")); ?>",
                        action: "lws_optimize_add_url_to_preload",
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
                                buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_exclusion_form_fe" class="lwsop_validatebutton">
                                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                    <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                                </button>
                            `;

                                let urls = returnData['data'];
                                let site_url = returnData['domain'];
                                form.innerHTML = `
                                <input type="hidden" id="lwsop_is_preload_actually">
                                <div class="lwsop_modal_infobubble">
                                    <?php esc_html_e('Enter the complete URL to the file you wish to preload. The file can only be a CSS stylesheet. Example : "https://example.fr/wp-content/plugins/myplugin/css/bootstrap.min.css?ver=6.5.3" would preload a BootStrap stylesheet from the "myplugin" plugin', 'lws-optimize'); ?>
                                </div>
                            `;

                                if (!urls.length) {
                                    form.insertAdjacentHTML('beforeend', `
                                    <div class="lwsoptimize_exclude_element">
                                        <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="">
                                        <div class="lwsoptimize_exclude_action_buttons">
                                            <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                            <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                        </div>
                                    </div>
                                `);
                                } else {
                                    for (var i in urls) {
                                        form.insertAdjacentHTML('beforeend', `
                                        <div class="lwsoptimize_exclude_element">
                                            <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="` + urls[i] + `">
                                            <div class="lwsoptimize_exclude_action_buttons">
                                                <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                                <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                            </div>
                                        </div>
                                    `);
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

                jQuery("#lws_optimize_exclusion_modale").modal('show');
            });
        }

        // Global event listener
        document.addEventListener("click", function(event) {
            let domain = "<?php echo esc_url(site_url()); ?>"
            var element = event.target;

            // Remove exception
            if (element.getAttribute('name') == "lwsoptimize_less_urls") {
                let amount_element = document.getElementsByName("lwsoptimize_exclude_url").length;
                if (amount_element > 1) {
                    let element_remove = element.closest('div.lwsoptimize_exclude_element');
                    element_remove.remove();
                } else {
                    // Empty the last remaining field instead of removing it
                    element.parentNode.parentNode.querySelector('input[name="lwsoptimize_exclude_url"]').value = ""
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
            }

            // Add new exception
            if (element.getAttribute('name') == "lwsoptimize_more_urls") {
                let amount_element = document.getElementsByName("lwsoptimize_exclude_url").length;
                let element_create = element.closest('div.lwsoptimize_exclude_element');

                let new_element = document.createElement("div");
                new_element.insertAdjacentHTML("afterbegin", `
                <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="">
                <div class="lwsoptimize_exclude_action_buttons">
                    <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                    <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                </div>
            `);
                new_element.classList.add('lwsoptimize_exclude_element');

                element_create.after(new_element);

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
            }

            // Save exceptions
            if (element.getAttribute('id') == "lws_optimize_exclusion_form_fe") {
                let form = document.getElementById('lws_optimize_exclusion_modale_form');
                if (form !== null) {
                    form.dispatchEvent(new Event('submit'));
                }
            }

            // Save exceptions (media)
            if (element.getAttribute('id') == "lws_optimize_exclusion_form_media") {
                let form = document.getElementById('lws_optimize_exclusion_lazyload_form');
                if (form !== null) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
        });

        // Fetch exceptions and send them to the server
        if (document.getElementById('lws_optimize_exclusion_modale_form') !== null) {
            document.getElementById('lws_optimize_exclusion_modale_form').addEventListener("submit", function(event) {
                var element = event.target;

                if (element.getAttribute('id') == "lws_optimize_exclusion_modale_form") {
                    event.preventDefault();
                    document.body.style.pointerEvents = "none";
                    let formData = jQuery(element).serializeArray();

                    let is_exclusions = element.children[0].id ?? null;

                    element.innerHTML = `
                    <div class="loading_animation">
                            <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                        </div>
                    `;
                    let buttons = element.parentNode.children[2];
                    buttons.innerHTML = '';

                    if (is_exclusions == "lwsop_is_preload_actually") {
                        let ajaxRequest = jQuery.ajax({
                            url: ajaxurl,
                            type: "POST",
                            timeout: 120000,
                            context: document.body,
                            data: {
                                data: formData,
                                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_preloading_url_files_set")); ?>",
                                action: "lws_optimize_set_url_to_preload",
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

                                jQuery(document.getElementById('lws_optimize_exclusion_modale')).modal('hide');
                                switch (returnData['code']) {
                                    case 'SUCCESS':
                                        callPopup('success', "<?php esc_html_e('Preloads have been successfully saved.', 'lws-optimize'); ?>");

                                        // Update "exclusions" count
                                        let id = "lws_op_add_to_preload_files";
                                        let bubble = document.getElementById(id + "_files");

                                        if (returnData['data'].length > 0) {
                                            if (bubble == null) {
                                                document.getElementById(id).parentNode.insertAdjacentHTML('afterbegin', `
                                            <div id="` + id + `_files"  name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                                                <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('files', 'lws-optimize'); ?></span>
                                            </div>
                                            `);
                                            } else {
                                                bubble.innerHTML = `
                                                <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('files', 'lws-optimize'); ?></span>
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
                    } else if (is_exclusions == "lwsop_is_font_preload_actually") {
                        let ajaxRequest = jQuery.ajax({
                            url: ajaxurl,
                            type: "POST",
                            timeout: 120000,
                            context: document.body,
                            data: {
                                data: formData,
                                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_preloading_url_fonts_set")); ?>",
                                action: "lws_optimize_set_url_to_preload_font",
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

                                jQuery(document.getElementById('lws_optimize_exclusion_modale')).modal('hide');
                                switch (returnData['code']) {
                                    case 'SUCCESS':
                                        callPopup('success', "<?php esc_html_e('Preloads have been successfully saved.', 'lws-optimize'); ?>");

                                        // Update "exclusions" count
                                        let id = "lws_op_add_to_preload_font";
                                        let bubble = document.getElementById(id + "_files");

                                        if (returnData['data'].length > 0) {
                                            if (bubble == null) {
                                                document.getElementById(id).parentNode.insertAdjacentHTML('afterbegin', `
                                            <div id="` + id + `_files"  name="exclusion_bubble" class="lwsop_exclusion_infobubble">
                                                <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('files', 'lws-optimize'); ?></span>
                                            </div>
                                            `);
                                            } else {
                                                bubble.innerHTML = `
                                                <span>` + returnData['data'].length + `</span> <span><?php esc_html_e('files', 'lws-optimize'); ?></span>
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
                    } else {
                        let ajaxRequest = jQuery.ajax({
                            url: ajaxurl,
                            type: "POST",
                            timeout: 120000,
                            context: document.body,
                            data: {
                                data: formData,
                                _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_exclusions_config")); ?>",
                                action: "lws_optimize_exclusions_changes_action",
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

                                jQuery(document.getElementById('lws_optimize_exclusion_modale')).modal('hide');
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
                }
            });
        }

        // Open "preloading fonts" modal
        if (document.getElementById('lws_op_add_to_preload_font') !== null) {
            document.getElementById('lws_op_add_to_preload_font').addEventListener('click', function(event) {
                let element = this;

                // Show the modale on screen with loading animation & modified title
                let title = document.getElementById('lws_optimize_exclusion_modale_title');
                let buttons = document.getElementById('lws_optimize_exclusion_modale_buttons');
                let form = document.getElementById('lws_optimize_exclusion_modale_form');
                form.innerHTML = `
                <div class="loading_animation">
                    <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
                </div>
            `;
                buttons.innerHTML = `
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            `;

                title.innerHTML = "<?php esc_html_e('Add fonts to preload', 'lws-optimize'); ?>";
                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_preloading_url_fonts")); ?>",
                        action: "lws_optimize_add_font_to_preload",
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
                                buttons.innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                                <button type="button" id="lws_optimize_exclusion_form_fe" class="lwsop_validatebutton">
                                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                    <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                                </button>
                            `;

                                let urls = returnData['data'];
                                let site_url = returnData['domain'];
                                form.innerHTML = `
                                <input type="hidden" id="lwsop_is_font_preload_actually">
                                <div class="lwsop_modal_infobubble">
                                    <?php esc_html_e('Enter the complete URL of the font you wish to preload. Example : "https://example.fr/wp-content/plugins/myplugin/css/myfont.woff2" would preload a font from the "myplugin" plugin', 'lws-optimize'); ?>
                                </div>
                            `;

                                if (!urls.length) {
                                    form.insertAdjacentHTML('beforeend', `
                                    <div class="lwsoptimize_exclude_element">
                                        <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="">
                                        <div class="lwsoptimize_exclude_action_buttons">
                                            <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                            <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                        </div>
                                    </div>
                                `);
                                } else {
                                    for (var i in urls) {
                                        form.insertAdjacentHTML('beforeend', `
                                        <div class="lwsoptimize_exclude_element">
                                            <input type="text" class="lwsoptimize_exclude_input" name="lwsoptimize_exclude_url" value="` + urls[i] + `">
                                            <div class="lwsoptimize_exclude_action_buttons">
                                                <div class="lwsoptimize_exclude_action_button red" name="lwsoptimize_less_urls">-</div>
                                                <div class="lwsoptimize_exclude_action_button green" name="lwsoptimize_more_urls">+</div>
                                            </div>
                                        </div>
                                    `);
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

                jQuery("#lws_optimize_exclusion_modale").modal('show');
            });
        }
    </script>
<?php endif ?>