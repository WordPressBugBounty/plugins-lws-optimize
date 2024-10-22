<?php
// Check whether Memcached id available on this hosting or not.
$memcached_locked = false;

if (class_exists('Memcached')) {
    $memcached = new Memcached();
    if (empty($memcached->getServerList())) {
        $memcached->addServer('localhost', 11211);
    }

    if ($memcached->getVersion() === false) {
        $memcached_locked = true;
    }
} else {
    $memcached_locked = true;
}

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

$arr = array('strong' => array());
$plugins = array(
    'lws-hide-login' => array('LWS Hide Login', __('This plugin <strong>hide your administration page</strong> (wp-admin) and lets you <strong>change your login page</strong> (wp-login). It offers better security as hackers will have more trouble finding the page.', 'lws-optimize'), true),
    'lws-optimize' => array('LWS Optimize', __('This plugin lets you boost your website\'s <strong>loading times</strong> thanks to our tools: caching, media optimisation, files minification and concatenation...', 'lws-optimize'), true),
    'lws-cleaner' => array('LWS Cleaner', __('This plugin lets you <strong>clean your WordPress website</strong> in a few clics to gain speed: posts, comments, terms, users, settings, plugins, medias, files.', 'lws-optimize'), true),
    'lws-sms' => array('LWS SMS', __('This plugin, designed specifically for WooCommerce, lets you <strong>send SMS automatically to your customers</strong>. You will need an account at LWS and enough credits to send SMS. Create personnalized templates, manage your SMS and sender IDs and more!', 'lws-optimize'), false),
    'lws-affiliation' => array('LWS Affiliation', __('With this plugin, you can add banners and widgets on your website and use those with your <strong>affiliate account LWS</strong>. Earn money and follow the evolution of your gains on your website.', 'lws-optimize'), false),
    'lwscache' => array('LWSCache', __('Based on the Varnich cache technology and NGINX, LWSCache let you <strong>speed up the loading of your pages</strong>. This plugin helps you automatically manage your LWSCache when editing pages, posts... and purging all your cache. Works only if your server use this cache.', 'lws-optimize'), false),
    'lws-tools' => array('LWS Tools', __('This plugin provides you with several tools and shortcuts to manage, secure and optimise your WordPress website. Updating plugins and themes, accessing informations about your server, managing your website parameters, etc... Personnalize every aspect of your website!', 'lws-optimize'), false)
);

//Adapt the array to change which plugins are featured as ads
$plugins_showcased = array('lws-hide-login', 'lws-tools', 'lwscache');

$plugins_activated = array();
$all_plugins = get_plugins();

foreach ($plugins as $slug => $plugin) {
    if (is_plugin_active($slug . '/' . $slug . '.php')) {
        $plugins_activated[$slug] = "full";
    } elseif (array_key_exists($slug . '/' . $slug . '.php', $all_plugins)) {
        $plugins_activated[$slug] = "half";
    }
}

// Fetch the configuration for each elements of LWSOptimize
$config_array = $GLOBALS['lws_optimize']->optimize_options;
?>

<script>
    var function_ok = true;
</script>

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
        <?php if (get_option('lws_optimize_offline', null) !== null) : ?>
            <!-- <div class="lwsoptimize_main_content_fogged"></div> -->
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
                <div id="post-body" class="<?php echo $tab[0] == 'plugins' ? esc_attr('lws_op_configpage_plugin') : esc_attr('lws_op_configpage'); ?> ">
                    <?php if (get_option("lws_optimize_offline")) : ?>
                        <?php if ($tab[0] == "plugins" || $tab[0] == 'pagespeed'); ?>
                        <?php echo ($tab[0] == 'plugins' || $tab[0] == 'pagespeed') ? '' : '<div class="deactivated_plugin_state"></div>'; ?>
                    <?php endif ?>
                    <?php include plugin_dir_path(__FILE__) . $tab[0] . '.php'; ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
<div class="lws_made_with_heart"><?php esc_html_e('Created with ❤️ by LWS.fr', 'lws-optimize'); ?></div>

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

<div class="modal fade" id="lwsop_preconfigurate_plugin" tabindex='-1' role='dialog' aria-hidden='true'>
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

<div id='modal_popup' class='modal fade' data-result="warning" tabindex='-1' role='dialog' aria-labelledby='myModalLabel' aria-hidden='true' style="display: none;">
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

    document.getElementById('manage_plugin_state').addEventListener('change', function(event) {
        var data = {
            action: "lws_optimize_manage_state",
            checked: this.checked,
            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('nonce_lws_optimize_activate_config')); ?>',
        };
        jQuery.post(ajaxurl, data, function(response) {
            location.reload();
        });
    })

    function lwsoptimize_copy_clipboard(input) {
        navigator.clipboard.writeText(input.innerText.trim());
        setTimeout(function() {
            jQuery('#copied_tip').remove();
        }, 500);
        jQuery(input).append("<div class='tip' id='copied_tip'>" +
            "<?php esc_html_e('Copied!', 'lws-optimize'); ?>" +
            "</div>");
    }
</script>


<!-- Here, need to change id of the selector and tabs -->
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
</script>

<script>
    jQuery(document).ready(function() {
        <?php foreach ($plugins_activated as $slug => $activated) : ?>
            <?php if ($activated == "full") : ?>
                /**/
                var button = jQuery(
                    "<?php echo esc_attr("#bis_" . $slug); ?>"
                );
                button.children()[3].classList.remove('hidden');
                button.children()[0].classList.add('hidden');
                button.prop('onclick', false);
                button.addClass('lws_op_button_ad_block_validated');

            <?php elseif ($activated == "half") : ?>
                /**/
                var button = jQuery(
                    "<?php echo esc_attr("#bis_" . $slug); ?>"
                );
                button.children()[2].classList.remove('hidden');
                button.children()[0].classList.add('hidden');
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

<!-- If need a select -->
<!-- Change lws_op! -->
<!-- <script>
    if (window.innerWidth <= 500) {
        jQuery('#tab_lws_op').addClass("hidden");
        jQuery('#tab_lws_op_select').parent().removeClass("hidden");
    }

    jQuery(window).on('resize', function() {
        if (window.innerWidth <= 500) {
            jQuery('#tab_lws_op').addClass("hidden");
            jQuery('#tab_lws_op_select').parent().removeClass("hidden");
            document.getElementById('tab_lws_op_select').value = document.querySelector(
                '.tab_nav_lwsoptimize[aria-selected="true"]').id;
        } else {
            jQuery('#tab_lws_op').removeClass("hidden");
            jQuery('#tab_lws_op_select').parent().addClass("hidden");
            const target = document.getElementById(document.getElementById('tab_lws_op_select').value);
            lwsoptimize_selectorMove(target, target.parentNode);
        }
    });

    jQuery('#tab_lws_op_select').on('change', function() {
        const target = document.getElementById(this.value);
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
    });
</script> -->

<!-- NEW VERSION -->

<div class="modal fade" id="lws_optimize_exclusion_modale" tabindex='-1' role='dialog' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title" id="lws_optimize_exclusion_modale_title"></h2>
            <form method="POST" id="lws_optimize_exclusion_modale_form"></form>
            <div class="lwsop_modal_buttons" id="lws_optimize_exclusion_modale_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                <button type="button" id="lws_optimize_exclusion_form_fe" class="lwsop_validatebutton">
                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                    <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (get_option('lws_optimize_offline', null) === null) : ?>
    <script>
        // All checkbox, not the buttons (like preload fonts)
        document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function(event) {
                let element = this;
                element.disabled = true;
                let state = element.checked;
                let type = element.getAttribute('id');

                let data = {
                    'state': state,
                    'type': type
                };

                document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                    checks.disabled = true;
                });

                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("nonce_lws_optimize_checkboxes_config")); ?>",
                        action: "lws_optimize_checkboxes_action",
                        data: data
                    },

                    success: function(data) {
                        element.disabled = false;
                        document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                            checks.disabled = false;
                        });

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
                                callPopup('success', "<?php esc_html_e('The action has been ', 'lws-optimize'); ?> " + status);
                                break;
                            case 'MEMCACHE_NOT_WORK':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e("Memcached could not be activated. Make sure it is activated on your server.", "lws-optimize"); ?>");
                                break;
                            case 'MEMCACHE_NOT_FOUND':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e("Memcached could not be found. Maybe your website is not compatible with it.", "lws-optimize"); ?>");
                                break;
                            case 'REDIS_ALREADY_HERE':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e("Redis Cache is already active on this website and may cause incompatibilities with Memcached. Please deactivate Redis Cache to use Memcached.", "lws-optimize"); ?>");
                                break;
                            case 'PANEL_CACHE_OFF':
                                element.checked = !state;
                                callPopup('warning', "<?php esc_html_e('LWSCache is not activated on this hosting. Please go to your LWSPanel and activate it.', 'lws-optimize'); ?>");
                                break;
                            case 'CPANEL_CACHE_OFF':
                                element.checked = !state;
                                callPopup('warning', "<?php esc_html_e('FastestCache is not activated on this cPanel. Please go to your cPanel and activate it.', 'lws-optimize'); ?>");
                                break;
                            case 'INCOMPATIBLE':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e('LWSCache is not available on this hosting. Please migrate to a LWS hosting to use this action.', 'lws-optimize'); ?>");
                                break;
                            case 'NOT_JSON':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e('Bad server response. Could not change action state.', 'lws-optimize'); ?>");
                                break;
                            case 'DATA_MISSING':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e('Not enough informations were sent to the server, please refresh and try again. Could not change action state.', 'lws-optimize'); ?>");
                            case 'UNKNOWN_ID':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e('No matching action bearing this ID, please refresh and retry. Could not change action state.', 'lws-optimize'); ?>");
                            case 'FAILURE':
                                element.checked = !state;
                                callPopup('error', "<?php esc_html_e('Could not save change to action state in the database.', 'lws-optimize'); ?>");
                            default:
                                break;
                        }
                    },
                    error: function(error) {
                        element.disabled = false;
                        document.querySelectorAll('input[id^="lws_optimize_"]').forEach(function(checks) {
                            checks.disabled = false;
                        });

                        element.checked = !state;
                        callPopup("error", "Une erreur inconnue est survenue. Impossible d'activer cette option.");
                        console.log(error);
                        return 1;
                    }
                });
            });
        });

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
    </script>

    <script>
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

        document.querySelectorAll('input[name="lwsop_configuration[]"]').forEach(function(element) {
            element.addEventListener('change', function() {
                document.querySelectorAll('.lwsop_configuration_block_sub.selected').forEach(function(element) {
                    element.classList.remove('selected')
                });
                element.parentElement.parentElement.classList.add('selected')
            })
        })


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