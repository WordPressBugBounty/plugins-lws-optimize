<?php

$first_bloc_array = array(
    'maintenance_db' => array(
        'has_logo' => false,
        'title' => __('Programmed Database Maintenance', 'lws-optimize'),
        'desc' => __('Enable this option to clean and optimize your database automatically each week.', 'lws-optimize'),
        'recommended' => false,
        'has_button' => true,
        'button_title' => __('Manage', 'lws-optimize'),
        'button_id' => "lws_optimize_maintenance_db_manage",
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_maintenance_db_check",
    ),
    'lwscleaner' => array(
        'has_logo' => true,
        'logo' => "plugin_lws_cleaner_logo.svg",
        'logo_size' => ['height' => "30px", 'width' => "30px"],
        'logo_alt' => "",
        'title' => __('LWS Cleaner Plugin', 'lws-optimize'),
        'desc' => __('This plugin lets you <b>clean your WordPress</b> website, notably its database, in a few clicks to improve speed: posts, comments, terms, users, parameters, plugins, medias, files.', 'lws-optimize'),
        'recommended' => true,
        'has_button' => true,
        'button_title' => __('Manage', 'lws-optimize'),
        'button_id' => "lws_optimize_lwscleaner_manage",
        'has_checkbox' => true,
        'checkbox_id' => "lws_optimize_lwscleaner_check",
    ),
);

$maintenance_options = array(
    'myisam' => __('Database optimisation for MyISAM tables', 'lws-optimize'),
    'drafts' => __('Automatically remove drafts from posts and pages', 'lws-optimize'),
    'revisions' => __('Remove all revisions from posts and pages', 'lws-optimize'),
    'deleted_posts' => __('Remove all trashed posts and pages', 'lws-optimize'),
    'spam_comments' => __('Remove all spam comments', 'lws-optimize'),
    'deleted_comments' => __('Remove all trashed comments', 'lws-optimize'),
    'expired_transients' => __('Remove all expired transients', 'lws-optimize')
);

foreach ($first_bloc_array as $key => $array) {
    $first_bloc_array[$key]['state'] = isset($config_array[$key]['state']) && $config_array[$key]['state'] == "true" ? true : false;
}

if (!is_plugin_active("lws-cleaner/lws-cleaner.php")) {
    $first_bloc_array['lwscleaner']['state'] = false;
    $first_bloc_array['lwscleaner']['has_button'] = false;
} else {
    $first_bloc_array['lwscleaner']['state'] = true;
    $first_bloc_array['lwscleaner']['has_button'] = true;
}

?>

<?php foreach ($first_bloc_array as $key => $data) : ?>
    <div class="lwsop_contentblock">
        <div class="lwsop_contentblock_leftside">
            <h2 class="lwsop_contentblock_title">
                <?php if ($data['has_logo']) : ?>
                    <img alt="<?php echo esc_html($data['logo_alt']); ?>" src="<?php echo esc_url(plugins_url('images/' . $data['logo'], __DIR__)); ?> " height="<?php echo esc_html($data['logo_size']['height']); ?>" width="<?php echo esc_html($data['logo_size']['width']); ?>">
                <?php endif ?>
                <?php echo esc_html($data['title']); ?>
                <?php if ($data['recommended']) : ?>
                    <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
                <?php endif ?>
                <a href="https://aide.lws.fr/a/1891" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
            </h2>
            <div class="lwsop_contentblock_description">
                <?php echo wp_kses($data['desc'], ['b' => []]); ?>
            </div>
        </div>
        <div class="lwsop_contentblock_rightside" <?php if ($key == "lwscleaner") : ?> id='lwsop_button_side' <?php endif; ?>>
            <?php if ($data['has_button']) : ?>
                <button type="button" class="lwsop_darkblue_button" value="<?php echo esc_html($data['title']); ?>" id="<?php echo esc_html($data['button_id']); ?>" name="<?php echo esc_html($data['button_id']); ?>" <?php if ($key == "maintenance_db") : ?> data-toggle="modal" data-target="#lws_optimize_manage_maintenance_modal" <?php endif ?>>
                    <span>
                        <?php esc_html_e($data['button_title'], 'lws-optimize'); ?>
                    </span>
                </button>
            <?php endif ?>
            <?php if ($data['has_checkbox']) : ?>
                <label class="lwsop_checkbox">
                    <input type="checkbox" name="<?php echo esc_html($data['checkbox_id']); ?>" id="<?php echo esc_html($data['checkbox_id']); ?>" <?php echo $data['state'] ? esc_html('checked') : esc_html(''); ?>>
                    <span class="slider round"></span>
                </label>
            <?php endif ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="lws_optimize_manage_maintenance_modal" tabindex='-1' role='dialog' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Database Maintenance Options', 'lws-optimize'); ?></h2>
            <form method="POST" id="lwsop_form_maintenance_db"></form>
            <div class="lwsop_modal_buttons" id="lwsop_maintenance_db_modal_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('lws_optimize_maintenance_db_manage').addEventListener('click', function() {
        let form = document.getElementById('lwsop_form_maintenance_db');
        form.innerHTML = `
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
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_get_maintenance_db_nonce')); ?>',
                action: "lws_optimize_get_maintenance_db_options"
            },
            success: function(data) {
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
                        let options = returnData['data'];
                        let domain = returnData['domain'];

                        document.getElementById('lwsop_maintenance_db_modal_buttons').innerHTML = `
                            <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                            <button type="button" id="lwsop_submit_maintenance_db_form" class="lwsop_validatebutton">
                                <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                            </button>
                        `;
                        form.innerHTML = `
                        <div class="lwsop_modal_infobubble">
                            <?php echo wp_kses(__('Check tasks you wish to <b>automatically execute each week</b> to optimise your database.', 'lws-optimize'), ['b' => []]); ?>
                        </div>
                        <div class="lwsop_maintenance_db_options" id="lwsop_maintenance_db_options"></div>`;

                        <?php foreach ($maintenance_options as $name => $desc) : ?>
                            document.getElementById('lwsop_maintenance_db_options').insertAdjacentHTML('beforeend', `
                                <label class="lwsop_maintenance_checkbox">
                                    <input type="checkbox" id="<?php esc_html_e($name); ?>" name="<?php esc_html_e($name); ?>">
                                    <div><?php esc_html_e($desc); ?></div>
                                </label>
                            `);
                        <?php endforeach; ?>

                        for (var i in options) {
                            var element = document.getElementById(options[i]);
                            if (element != null) {
                                element.checked = true;
                            }
                        }
                        break;
                    default:
                        document.getElementById('lwsop_maintenance_db_modal_buttons').innerHTML = `
                            <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                            <button type="button" id="lwsop_submit_specified_form" class="lwsop_validatebutton">
                                <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                            </button>
                        `;
                        form.innerHTML = `
                        <div class="lwsop_modal_infobubble">
                            <?php echo wp_kses(__('Check tasks you wish to <b>automatically execute each week</b> to optimise your database.', 'lws-optimize'), ['b' => []]); ?>
                        </div>`;

                        <?php foreach ($maintenance_options as $name => $desc) : ?>
                            form.insertAdjacentHTML('beforeend', `
                                <div id="lwsop_maintenance_db_options">
                                    <label class="lwsop_maintenance_check_lab">
                                        <input class="lwsop_maintenance_check" type="checkbox" id="<?php esc_html_e($name); ?>" name="<?php esc_html_e($name); ?>">
                                        <div class"=lwsop_maintenance_check_text"><?php esc_html_e($desc); ?></div>
                                    </label>
                                </div>
                            `);
                        <?php endforeach; ?>
                        break;
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });

    function updateMaintenanceOptions(data) {
        var form = document.getElementById('lwsop_form_maintenance_db');
        var old_form = form.innerHTML;

        var buttons = document.getElementById('lwsop_maintenance_db_modal_buttons');
        var old_buttons = buttons.innerHTML;

        buttons.innerHTML = '';
        form.innerHTML = `
            <div class="loading_animation">
                <img class="loading_animation_image" alt="Logo Loading" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/chargement.svg') ?>" width="120px" height="105px">
            </div>
        `;

        document.body.style.pointerEvents = "none";

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_set_maintenance_db_nonce')); ?>',
                action: "lws_optimize_set_maintenance_db_options",
                formdata: data
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
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        form.innerHTML = old_form;
                        buttons.innerHTML = old_buttons;
                        document.querySelectorAll('button[data-dismiss="modal"]').forEach(function(b) {
                            b.click()
                        });
                        callPopup('success', "Les options ont bien été mises à jour");
                        break;
                    case 'FAILURE':
                        jQuery("#lws_optimize_manage_maintenance_modal").modal('hide')
                        callPopup('error', `<?php esc_html_e('An error occured: could not set maintenance options', 'lws-optimize'); ?>`)
                        break;
                    case 'NO_DATA':
                        jQuery("#lws_optimize_manage_maintenance_modal").modal('hide')
                        callPopup('error', `<?php esc_html_e('An error occured: no data sent', 'lws-optimize'); ?>`)
                        break;
                    default:
                        console.log(returnData['code']);
                        callPopup('error', "Une erreur est survenue");
                        form.innerHTML = old_form.innerHTML;
                        buttons.innerHTML = old_buttons.innerHTML;
                        break;
                }
            },
            error: function(error) {
                document.querySelectorAll('button[data-dismiss="modal"]').forEach(function(b) {
                    b.click()
                });
                document.body.style.pointerEvents = "all";
                callPopup('error', "Une erreur inconnue est survenue");
                console.log(error);
            }
        });
    }

    document.addEventListener('click', function(event) {
        var element = event.target;

        if (element.id == "lwsop_submit_maintenance_db_form") {
            var form = document.getElementById('lwsop_form_maintenance_db');
            if (form !== undefined) {
                var formData = new FormData(form);
                var data = [];
                for (const [key, value] of formData) {
                    if (value == "on") {
                        data.push(key);
                    }
                }
            }

            updateMaintenanceOptions(data);
        }
    });

    document.getElementById('lws_optimize_lwscleaner_check').addEventListener('click', function() {
        document.getElementById('wpcontent').style.pointerEvents = "none";
        let state = this.checked;
        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_activate_cleaner_nonce')); ?>',
                action: "lws_optimize_activate_cleaner",
                state
            },
            success: function(data) {
                document.getElementById('wpcontent').style.pointerEvents = "all";
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
                        if (returnData['state'] == "true") {
                            document.getElementById('lwsop_button_side').insertAdjacentHTML('afterbegin', `
                                <button type="button" class="lwsop_darkblue_button" value="LWS Cleaner Plugin" id="lws_optimize_lwscleaner_manage" name="lws_optimize_lwscleaner_manage">
                                    <span>
                                        <?php esc_html_e("Manage", 'lws-optimize'); ?>
                                    </span>
                                </button>
                            `);
                        } else {
                            document.getElementById('lws_optimize_lwscleaner_manage').remove();
                        }
                        break;
                    default:
                        console.log(returnData['code']);
                        break;
                }
            },
            error: function(error) {
                document.getElementById('wpcontent').style.pointerEvents = "all";
                console.log(error);
            }
        });
    })

    document.addEventListener('click', function(event) {
        if (event.target.id == "lws_optimize_lwscleaner_manage") {
            window.location.href = "<?php echo esc_url(admin_url("admin.php?page=lws-cl-config")); ?>";
        }
    });
</script>