<?php

$list_time = array(
    '' => '--- ---',
    '3600' => __('One hour', 'lws-optimize'),
    '10800' => __('Two hours', 'lws-optimize'),
    '18000' => __('Five hours', 'lws-optimize'),
    '86400' => __('A day', 'lws-optimize'),
    '432000' => __('A week', 'lws-optimize'),
    '2629800' => __('A month', 'lws-optimize'),
    '5259600' => __('Two months', 'lws-optimize'),
);


$is_activated = true;
$memcached_can_work = false;
if (class_exists('Memcached')) {
    global $memcached;
    $memcached = new Memcached();
    if (empty($memcached->getServerList())) {
        $memcached->addServer('localhost', 11211);
    }
    if ($memcached->getVersion() === false) {
        $memcached_is_activated = array(__('Memcached is not active on your server. Your website is compatible with Memcached but is not configurated to use it. If your website is hosted at LWS or on a cPanel, please refer to the documentation below for help.', 'lws-optimize'));
        $is_activated = false;
    } elseif (get_option('lws_opti_memcaching_on')) {
        $memcached_is_activated = array(__('Memcached is currently activated and working on your website.', 'lws-optimize'));
        $memcached_can_work = true;
    } else {
        $memcached_is_activated = array(__('Memcached is currently activated on your server but not working on your website.', 'lws-optimize'));
        $memcached_can_work = true;
    }
} else {
    $memcached_is_activated = array(__('Memcached is not active on your server. It may not be compatible with this service or need to be activated manually. Please contact your hosting provider for more help.', 'lws-optimize'));
}
?>

<div class="lws_op_div_file_based_cache">
    <div class="lws_op_media_bloc_title_general">
        <div class="lws_op_media_bloc_title_left">
            <span class="lws_op_media_title">
                <?php esc_html_e('File-based caching', 'lws-optimize'); ?>
            </span>
            <br>
            <span class="lws_op_media_text">
                <?php esc_html_e('File-based caching helps boosting pages loading and performances by creating static HTML versions of the pages. Since the PHP dynamic code is already executed, pages are then loaded faster to visitors. Those cached files are stocked, allowing for faster retrieving of the content.', 'lws-optimize'); ?>
            </span>
            <br>
            <span class="lws_op_span_file_based_cache">
                <?php esc_html_e('Cleanup interval for the cache: ', 'lws-optimize'); ?>
            </span>
            <select name="lws_op_select_file_based" id="lws_op_select_file_based" class="">
                <?php foreach ($list_time as $key => $list) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php echo get_option('lws_op_fb_cache') == esc_attr($key) ? esc_attr('selected') : ''; ?>>
                    <?php echo esc_html($list); ?>
                </option>
                <?php endforeach ?>
            </select>
        </div>



        <div class="lws_op_media_bloc_title_right">
            <button
                class="lws_op_button_media <?php echo get_option('lws_op_fb_cache') ? esc_attr('hidden') : esc_attr('') ?>"
                value="" id="lws_op_fb_cache_activate">
                <span>
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Activate', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php esc_html_e('Activated', 'lws-optimize'); ?></span>
            </button>

            <button
                class="lws_op_button_media <?php echo get_option('lws_op_fb_cache') ? esc_attr('') : esc_attr('hidden') ?>"
                value="" id="lws_op_fb_cache_deactivate">
                <span>
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Deactivate', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php esc_html_e('Deactivated', 'lws-optimize'); ?></span>
            </button>

            <?php if (!$is_cache_empty) : ?>
            <button class="lws_op_button_media" style="margin-top:10px" value="" id="lws_op_fb_cache_remove">
                <span>
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/supprimer.svg')?>"
                        alt="Maj" width="20px" height="20px">
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Clear the cache', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php esc_html_e('Cleared', 'lws-optimize'); ?></span>
            </button>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="lws_op_div_file_based_cache">
    <div class="lws_op_media_bloc_title_general">
        <div class="lws_op_media_bloc_title_left">
            <span class="lws_op_media_title">
                <?php esc_html_e('Memcached', 'lws-optimize'); ?>
            </span>
            <br>
            <span class="lws_op_media_text">
                <?php esc_html_e('Memcached is a powerful caching solution for your website. It stores frequently used queries to your database. It helps reducing the number of calls to the database, effectively bettering your website\'s performances.', 'lws-optimize'); ?>
            </span>
            <br>
            <span class="lws_op_media_text" id="lws_op_memcached_state_line">
                <br>
                <?php echo esc_html($memcached_is_activated[0]) ?>
            </span>
            <?php if ($is_activated === false) : ?>
            <ul class="lws_op_media_text">
                <li>
                    <a href="https://aide.lws.fr/a/1496#content-1"
                        target="_blank"><?php esc_html_e('Activating Memcached on a cPanel', 'lws-optimize'); ?></a>
                </li>
                <li>
                    <a href="https://aide.lws.fr/a/1542#content-3"
                        target="_blank"><?php esc_html_e('Activating Memcached with LWS Panel', 'lws-optimize'); ?></a>
                </li>
            </ul>
            <?php endif ?>
        </div>

        <div class="lws_op_media_bloc_title_right">
            <?php if ($memcached_can_work === true) : ?>
            <button
                class="lws_op_button_media <?php echo get_option('lws_opti_memcaching_on') ? esc_attr('hidden') : esc_attr('') ?>"
                value="" id="lws_op_slider_memcaching_on">
                <span>
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Activate', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php echo esc_html_e('Activated', 'lws-optimize'); ?></span>
            </button>

            <button
                class="lws_op_button_media <?php echo get_option('lws_opti_memcaching_on') ? esc_attr('') : esc_attr('hidden') ?>"
                value="" id="lws_op_slider_memcaching_off">
                <span>
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Deactivate', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php esc_html_e('Deactivated', 'lws-optimize'); ?></span>
            </button>
            <?php else : ?>
            <?php get_option('lws_opti_memcaching_on') ? delete_option('lws_opti_memcaching_on') : '';?>
            <?php endif ?>

            <button class="lws_op_button_media" style="margin-top:10px" value="" id="lws_op_memcached_remove">
                <span>
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/supprimer.svg')?>"
                        alt="Maj" width="20px" height="20px">
                    <span
                        class="lws_aff_button_text"><?php esc_html_e('Clear the cache', 'lws-optimize'); ?></span>
                </span>
                <span class="hidden" name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg')?>"
                        alt="" width="18px" height="18px">
                </span>
                <span class="hidden" name="cleared">
                    <img style="vertical-align:sub; margin-right:5px" width="18px" height="18px"
                        src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/check_blanc.svg')?>">
                    <?php esc_html_e('Cleared', 'lws-optimize'); ?></span>
            </button>
        </div>
    </div>
</div>

<script>

jQuery('#lws_op_select_file_based').on('change',
    function() {
        var data = {
            action: "lws_optimize_fb_cache",
            timer: this.value,
            _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('update_fb_activate')); ?>',
        };
        jQuery.post(ajaxurl, data);
    });


    jQuery('#lws_op_fb_cache_activate').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_optimize_fb_cache",
                timer: document.getElementById('lws_op_select_file_based').value,
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('update_fb_activate')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
                setTimeout(function() {
                        b.removeAttribute('disabled');
                        b.nextElementSibling.classList.remove('hidden');
                        b.classList.remove('lws_op_validated_button');
                        b.classList.add('hidden');
                        b.children[0].classList.remove('hidden');
                        b.children[2].classList.add('hidden');
                    },
                    1500);
            });
        });


    jQuery('#lws_op_fb_cache_deactivate').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_no_optimize_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('update_fb_deactivate')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
                setTimeout(function() {
                        b.removeAttribute('disabled');
                        b.previousElementSibling.classList.remove('hidden');
                        b.classList.remove('lws_op_validated_button');
                        b.classList.add('hidden');
                        b.children[0].classList.remove('hidden');
                        b.children[2].classList.add('hidden');
                    },
                    1500);
            });
        });

    jQuery('#lws_op_fb_cache_remove').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_clear_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
            });
        });

    jQuery('#lws_op_memcached_remove').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_clear_memcached",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_memc_caching')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
                b.removeAttribute('disabled');
            });
        });

    jQuery('#lws_op_slider_memcaching_on').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_start_memcached",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('starting_memc_caching')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
                jQuery('#lws_op_memcached_state_line')
                    .text(
                        "<?php esc_html_e('Memcached is currently activated and working on your server.', 'lws-optimize'); ?>"
                    );
                setTimeout(function() {
                        b.removeAttribute('disabled');
                        b.nextElementSibling.classList.remove('hidden');
                        b.classList.remove('lws_op_validated_button');
                        b.classList.add('hidden');
                        b.children[0].classList.remove('hidden');
                        b.children[2].classList.add('hidden');
                    },
                    1500);
            });
        });

    jQuery('#lws_op_slider_memcaching_off').on('click',
        function() {
            var b = this;

            b.children[0].classList.add('hidden');
            b.children[2].classList.add('hidden');
            b.children[1].classList.remove('hidden');
            b.classList.remove('lws_op_validated_button');
            b.setAttribute('disabled', true);

            var data = {
                action: "lws_stop_memcached",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('turn_off_memc_caching')); ?>',
            };
            jQuery.post(ajaxurl, data, function(response) {
                b.children[0].classList.add('hidden');
                b.children[2].classList.remove('hidden');
                b.children[1].classList.add('hidden');
                b.classList.add('lws_op_validated_button');
                jQuery('#lws_op_memcached_state_line')
                    .text(
                        "<?php esc_html_e('Memcached is currently activated but not working on your server.', 'lws-optimize'); ?>"
                    );
                setTimeout(function() {
                        b.removeAttribute('disabled');
                        b.previousElementSibling.classList.remove('hidden');
                        b.classList.remove('lws_op_validated_button');
                        b.classList.add('hidden');
                        b.children[0].classList.remove('hidden');
                        b.children[2].classList.add('hidden');
                    },
                    1500);
            });
        });
</script>