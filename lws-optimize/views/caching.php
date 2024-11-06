<?php
$config_array = $GLOBALS['lws_optimize']->optimize_options;

$fb_preloaddata = [
    'state' => $config_array['filebased_cache']['preload_ongoing'] ?? "false",
    'quantity' => $config_array['filebased_cache']['preload_quantity'] ?? 0,
    'done' => $config_array['filebased_cache']['preload_done'] ?? 0,
];

$filebased_cache_options = $GLOBALS['lws_optimize']->lwsop_check_option("filebased_cache");
$filebased_timer = $filebased_cache_options['data']['timer'] ?? "lws_thrice_monthly";

if ($filebased_cache_options['state'] === "true") {
    $specified = isset($filebased_cache_options['data']['specified']) ? count($filebased_cache_options['data']['specified']) : "0";
} else {
    $specified = "0";
}

$preload_state = @$filebased_cache_options['data']['preload'] == "true" ? "true" : "false";

$preload_amount =  intval($filebased_cache_options['data']['preload_amount'] ?? 5);

$lwscache_options = $GLOBALS['lws_optimize']->lwsop_check_option("dynamic_cache");
$autopurge_options = $GLOBALS['lws_optimize']->lwsop_check_option("autopurge");

$next_preload = wp_next_scheduled("lws_optimize_start_filebased_preload");
$local_timestamp = get_date_from_gmt(date('Y-m-d H:i:s', $next_preload), 'Y-m-d H:i:s');

$memcached_force_off = false;

function lwsOpSizeConvert($size)
{
    $unit = array(__('b', 'lws-optimize'), __('K', 'lws-optimize'), __('M', 'lws-optimize'), __('G', 'lws-optimize'), __('T', 'lws-optimize'), __('P', 'lws-optimize'));
    if ($size <= 0) {
        return '0 ' . $unit[1];
    }
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . '' . $unit[$i];
}

$cache_stats = get_option('lws_optimize_cache_statistics', [
    'desktop' => ['amount' => 0, 'size' => 0],
    'mobile' => ['amount' => 0, 'size' => 0],
    'css' => ['amount' => 0, 'size' => 0],
    'js' => ['amount' => 0, 'size' => 0],
]);

$file_cache = $cache_stats['desktop']['amount'] ?? 0;
$file_cache_size = lwsOpSizeConvert($cache_stats['desktop']['size'] ?? 0);

$mobile_cache = $cache_stats['mobile']['amount'] ?? 0;
$mobile_cache_size = lwsOpSizeConvert($cache_stats['mobile']['size'] ?? 0);

$css_cache = $cache_stats['css']['amount'] ?? 0;
$css_cache_size = lwsOpSizeConvert($cache_stats['css']['size'] ?? 0);

$js_cache = $cache_stats['js']['amount'] ?? 0;
$js_cache_size = lwsOpSizeConvert($cache_stats['js']['size'] ?? 0);

$caches = [
    'files' => [
        'size' => $file_cache_size,
        'title' => __('Computer Cache', 'lws-optimize'),
        'amount' => $file_cache,
        'id' => "lws_optimize_file_cache",
        'image_file' => "ordinateur.svg",
        'image_alt' => "computer icon",
        'width' => "60px",
        'height' => "60px",
    ],
    'mobile' => [
        'size' => $mobile_cache_size,
        'title' => __('Mobile Cache', 'lws-optimize'),
        'amount' => $mobile_cache,
        'id' => "lws_optimize_mobile_cache",
        'image_file' => "mobile.svg",
        'image_alt' => "mobile icon",
        'width' => "50px",
        'height' => "60px",
    ],
    'css' => [
        'size' => $css_cache_size,
        'title' => __('CSS Cache', 'lws-optimize'),
        'amount' => $css_cache,
        'id' => "lws_optimize_css_cache",
        'image_file' => "css.svg",
        'image_alt' => "css logo in a window icon",
        'width' => "60px",
        'height' => "60px",
    ],
    'js' => [
        'size' => $js_cache_size,
        'title' => __('JS Cache', 'lws-optimize'),
        'amount' => $js_cache,
        'id' => "lws_optimize_js_cache",
        'image_file' => "js.svg",
        'image_alt' => "js logo in a window icon",
        'width' => "60px",
        'height' => "60px",

    ],
];
?>

<div class="lwsop_bluebanner" style="justify-content: space-between;">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Cache stats', 'lws-optimize'); ?></h2>
    <button class="lwsop_blue_button" id="lwsop_refresh_stats"><?php esc_html_e('Refresh', 'lws-optimize'); ?></button>
</div>

<div class="lwsop_contentblock_stats">
    <?php foreach ($caches as $type => $cache) : ?>
        <div class="lwsop_stat_block" id="<?php echo esc_attr($cache['id']); ?>">
            <img src="<?php echo esc_url(plugins_url("images/{$cache['image_file']}", __DIR__)) ?>" alt="<?php echo esc_attr($cache['image_alt']); ?>" width="<?php echo esc_attr($cache['width']); ?>" height="<?php echo esc_attr($cache['height']); ?>">
            <span><?php echo esc_html__($cache["title"]); ?></span>
            <div class="lwsop_stats_bold">
                <span>
                    <?php echo esc_html("{$cache['size']} / {$cache['amount']}"); ?>
                </span>
                <span>
                    <?php esc_html_e('elements', 'lws-optimize'); ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php // WP-Cron is inactive
if (!defined("DISABLE_WP_CRON") || !DISABLE_WP_CRON) : ?>
<div class="lwsop_wpcron_cutout" style="margin-bottom: 30px;">
    <div>
        <img class="" alt="Logo Plugins" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/warning.svg') ?>" width="35px">
    </div>
    <div>
        <span><?php esc_html_e('You are currently using WP-Cron, which means the preloading will only be executed when there is activity on your website and will use your website resources, slowing it down.', 'lws-optimize'); ?></span>
        <span><?php esc_html_e('We recommend using a server cron, which will execute tasks at a specified time and without hogging resources, no matter what is happening on your website.', 'lws-optimize'); ?></span>
        <span>
            <?php if ($lwscache_locked) {
                esc_html_e('For more informations on how to setup server crons, contact your hosting provider.', 'lws-optimize');
            }  elseif ($lwscache_status !== null) {
                esc_html_e('For more informations on how to setup server crons by using the WPManager, follow this ', 'lws-optimize');
                ?><a href="https://tutoriels.lws.fr/wordpress/wp-manager-de-lws-gerer-son-site-wordpress#Gerer_la_securite_et_les_parametres_generaux_de_votre_site_WordPress_avec_WP_Manager_LWS" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
            } elseif ($fastest_cache_status !== null) {
                esc_html_e('For more informations on how to setup server crons, follow this ', 'lws-optimize');
                ?><a href="https://support.cpanel.net/hc/en-us/articles/10687844130199-How-to-replace-wp-cron-with-cron-job-without-WP-Toolkit" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
            } ?>
        </span>
    </div>
</div>
<?php endif; ?>


<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Cache types', 'lws-optimize'); ?></h2>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('File-based caching', 'lws-optimize'); ?>
            <span class="lwsop_necessary"><?php esc_html_e('necessary', 'lws-optimize'); ?></span>
            <a href="https://aide.lws.fr/a/1887" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Activate file-based caching to create static HTML versions of your website, which will be served to future visitors. This speed up loading times while avoiding repeated executions of dynamic PHP code. This option is necessary for the front-end options to work.', 'lws-optimize'); ?>
        </div>
        <div class="lwsop_contentblock_fbcache_select">
            <span class="lwsop_contentblock_select_label"><?php esc_html_e('Cleanup interval for the cache: ', 'lws-optimize'); ?></span>
            <select name="lws_op_filebased_cache_timer" id="lws_op_filebased_cache_timer" name="lws_op_filebased_cache_timer" class="lwsop_contentblock_select">
                <?php foreach ($GLOBALS['lws_optimize_cache_timestamps'] as $key => $list) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php echo $filebased_timer == esc_attr($key) ? esc_attr('selected') : ''; ?>>
                        <?php echo esc_html_e($list[1], "lws-optimize"); ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox">
            <input type="checkbox" name="lws_op_filebased_cache_manage" id="lws_op_filebased_cache_manage" <?php echo $filebased_cache_options['state'] == "true" ? esc_html("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Memcached', 'lws-optimize'); ?>
            <a href="https://aide.lws.fr/a/1889" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Memcached optimize the cache by stocking frequent requests in a database, improving the global performances.', 'lws-optimize'); ?>
        </div>
    </div>
    <?php if ($memcached_force_off) : ?>
        <div class="lwsop_contentblock_rightside custom">
            <label class="lwsop_checkbox" for="lws_open_memcached_lws_checkbox">
                <input type="checkbox" name="" id="lws_open_memcached_lws_checkbox" data-toggle="modal" data-target="#lws_optimize_lws_memcached">
                <span class="slider round"></span>
            </label>
        </div>
    <?php elseif ($memcached_locked) : ?>
        <div class="lwsop_contentblock_rightside custom">
            <label class="lwsop_checkbox" for="lws_open_prom_lws_memcached_checkbox">
                <input type="checkbox" name="" id="lws_open_prom_lws_memcached_checkbox" data-toggle="modal" data-target="#lws_optimize_lws_prom">
                <span class="slider round"></span>
            </label>
        </div>
    <?php else : ?>
        <div class="lwsop_contentblock_rightside">
            <label class="lwsop_checkbox" for="lws_optimize_memcached_check">
                <input type="checkbox" name="lws_optimize_memcached_check" id="lws_optimize_memcached_check" <?php echo $GLOBALS['lws_optimize']->lwsop_check_option("memcached")['state'] == "true" ? esc_html("checked") : ""; ?>>
                <span class="slider round"></span>
            </label>
        </div>
    <?php endif; ?>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Server Cache', 'lws-optimize'); ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
            <a href="https://aide.lws.fr/a/1565" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('LWSCache, accessible to all clients, speed up websites by storing content on the server memory. While a manual purge is available, an autopurge is activated by default, making this option often redundant.', 'lws-optimize'); ?>
        </div>
    </div>
    <?php if ($lwscache_locked) : ?>
        <div class="lwsop_contentblock_rightside custom">
            <label class="lwsop_checkbox" for="lws_open_prom_lws_checkbox">
                <input type="checkbox" name="" id="lws_open_prom_lws_checkbox" data-toggle="modal" data-target="#lws_optimize_lws_prom">
                <span class="slider round"></span>
            </label>
        </div>
    <?php else : ?>
        <div class="lwsop_contentblock_rightside custom">
            <?php if (isset($config_array['cloudflare']['tools']['dynamic_cache']) && $config_array['cloudflare']['tools']['dynamic_cache'] === true) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by CloudFlare and cannot be activated', 'lws-optimize'); ?>"></div>
            <?php elseif(is_plugin_active("lwscache/lwscache.php")) : ?>
                <div class="lwsop_cloudflare_block" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e('This action is managed by LWSCache and cannot be activated. Deactivate LWSCache to manage the server cache from here.', 'lws-optimize'); ?>"></div>
            <?php endif ?>
            <label class="lwsop_checkbox" for="lws_optimize_dynamic_cache_check">
                <input type="checkbox" name="lws_optimize_dynamic_cache_check" id="lws_optimize_dynamic_cache_check" <?php echo $lwscache_options['state'] === "true" ? esc_html("checked") : ""; ?>>
                <span class="slider round"></span>
            </label>
            <button type="button" class="lwsop_blue_button" id="lws_op_clear_dynamic_cache" name="lws_op_clear_dynamic_cache">
                <span>
                    <img src="<?php echo esc_url(plugins_url('images/supprimer.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                    <?php esc_html_e('Clear cache', 'lws-optimize'); ?>
                </span>
            </button>
        </div>
    <?php endif ?>
</div>

<div class="lwsop_bluebanner">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('File-based cache settings', 'lws-optimize'); ?></h2>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Automatic Purge', 'lws-optimize'); ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
            <a href="https://aide.lws.fr/a/1888" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Cache is emptied smartly and automatically based on events on your WordPress website (page updated, ...)', 'lws-optimize'); ?>
        </div>
        <?php if (!empty($specified) && $specified != "0") : ?>
            <div class="lwsop_contentblock_specific_purge">
                <span id="lwsop_specified_count"><?php echo $specified; ?></span> <?php esc_html_e(' URLs specifications currently defined, those pages will get purged with every purge', 'lws-optimize'); ?>
            </div>
        <?php endif ?>
        <div class="lwsop_contentblock_button_row">
            <button type="button" class="lwsop_darkblue_button" id="lws_op_fb_cache_exclusion_manage" data-toggle="modal" data-target="#lwsop_exclude_urls">
                <span>
                    <?php esc_html_e('Exclude URLs', 'lws-optimize'); ?>
                </span>
            </button>
            <button type="button" class="lwsop_darkblue_button" id="lws_op_fb_cache_manage_specificurl" data-toggle="modal" data-target="#lwsop_specify_urls">
                <span>
                    <?php esc_html_e('Specify URLs', 'lws-optimize'); ?>
                </span>
            </button>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_autopurge_check">
            <input type="checkbox" name="lws_optimize_autopurge_check" id="lws_optimize_autopurge_check" <?php echo $autopurge_options['state'] === "true" ? esc_html("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Manual cache purge', 'lws-optimize'); ?>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Purge manually every cache to eliminate obsolete cache immediately.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <button type="button" class="lwsop_blue_button" id="lws_op_fb_cache_remove" name="lws_op_fb_cache_remove">
            <span>
                <img src="<?php echo esc_url(plugins_url('images/supprimer.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                <?php esc_html_e('Clear the cache', 'lws-optimize'); ?>
            </span>
        </button>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Preloading', 'lws-optimize'); ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Start preloading your website cache automatically and keep it up to date. Pages are guaranteed to be cached before the first user visit. Depending on the amount of pages to cache, it may take a while. Please be aware that the total amount of page may include dynamic pages that will not be cached, such as excluded URLs or WooCommerce checkout page.', 'lws-optimize'); ?>
        </div>
        <div class="lwsop_contentblock_fbcache_input_preload_block">
            <input class="lwsop_contentblock_fbcache_input_preload" type="number" min="3" max="15" name="lws_op_fb_cache_preload_amount" id="lws_op_fb_cache_preload_amount" value="<?php echo esc_attr($preload_amount); ?>" onkeydown="return false">
            <div class="lwsop_contentblock_input_preload_label"><?php esc_html_e('pages per minutes cached', 'lws-optimize'); ?></div>
        </div>

        <div id="lwsop_preloading_status_block" class="lwsop_contentblock_fbcache_preload <?php echo $preload_state == "false" ? esc_attr('hidden') : ''; ?>">
            <span class="lwsop_contentblock_fbcache_preload_label">
                <?php esc_html_e('Preloading status: ', 'lws-optimize'); ?>
                <button id="lwsop_update_preloading_value" class="lwsop_update_info_button"><?php esc_html_e('Refresh', 'lws-optimize'); ?></button>
            </span>
            <div id="lwsop_preloading_status">
                <div class="lwsop_preloading_status_info">
                    <span><?php echo esc_html__('Preloading state: ', 'lws-optimize'); ?></span>
                    <span id="lwsop_current_preload_info"><?php echo $fb_preloaddata['state'] == "true" ? esc_html__('Ongoing', 'lws-optimize')  : esc_html__('Up-to-date', 'lws-optimize'); ?>
                </div>
                <div class="lwsop_preloading_status_info">
                    <span><?php echo esc_html__('Next preloading: ', 'lws-optimize'); ?></span>
                    <span id="lwsop_next_preload_info"><?php echo $next_preload ? esc_attr($local_timestamp) : esc_html__('/', 'lws-optimize'); ?>
                </div>
                <div class="lwsop_preloading_status_info">
                    <span><?php esc_html_e('Page cached / Total pages: ', 'lws-optimize'); ?></span>
                    <span id="lwsop_current_preload_done"><?php echo esc_html($fb_preloaddata['done'] . "/" . $fb_preloaddata['quantity']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_op_fb_cache_manage_preload">
            <input type="checkbox" name="lws_op_fb_cache_manage_preload" id="lws_op_fb_cache_manage_preload" <?php echo isset($config_array['filebased_cache']['preload']) && $config_array['filebased_cache']['preload'] == "true" ? esc_attr("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('No cache for mobile user', 'lws-optimize'); ?>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Show an uncached version of the website to user on mobile devices. No cache will be created for them.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_cache_mobile_user_check">
            <input type="checkbox" name="lws_optimize_cache_mobile_user_check" id="lws_optimize_cache_mobile_user_check" <?php echo isset($config_array['cache_mobile_user']) && $config_array['cache_mobile_user']['state'] == "true" ? esc_attr("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('No cache for logged in users', 'lws-optimize');
            ?>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('All connected users will be shown the non-cached version of this website.', 'lws-optimize');
            ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_cache_logged_user_check">
            <input type="checkbox" name="lws_optimize_cache_logged_user_check" id="lws_optimize_cache_logged_user_check" <?php echo isset($config_array['cache_logged_user']) && $config_array['cache_logged_user']['state'] == "true" ? esc_attr("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
    </div>
</div>

<div class="modal fade" id="lwsop_exclude_urls" tabindex='-1' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Exclude URLs from the cache', 'lws-optimize'); ?></h2>
            <form method="POST" id="lwsop_form_exclude_urls"></form>
            <div class="lwsop_modal_buttons" id="lwsop_exclude_modal_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lwsop_specify_urls" tabindex='-1' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Specify URLs to purge along with the cache', 'lws-optimize'); ?></h2>
            <form method="POST" id="lwsop_form_specify_urls"></form>
            <div class="lwsop_modal_buttons" id="lwsop_specify_modal_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lws_optimize_lws_memcached" tabindex='-1' aria-hidden='true'>
    <div class="modal-dialog" style="width: fit-content; top: 10%; max-width: 800px;">
        <div class="modal-content" style="padding: 30px;">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Momentarily unavailable', 'lws-optimize'); ?></h2>
            <div id="lws_optimize_lws_prom_text"><?php esc_html_e('Due to many users experiencing issues with Memcached, this functionnality has been temporarily deactivated', 'lws-optimize'); ?></div>


            <div class="lwsop_modal_buttons" id="">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="lws_optimize_lws_prom" tabindex='-1' aria-hidden='true'>
    <div class="modal-dialog" style="width: fit-content; top: 10%; max-width: 800px;">
        <div class="modal-content" style="padding: 30px;">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Available on LWS hosting', 'lws-optimize'); ?></h2>
            <div id="lws_optimize_lws_prom_text"><?php esc_html_e('This function is reserved for LWS hosting and is not supported in your current environment. The LWS infrastructure, built for speed, offers exclusive features to optimize your site:', 'lws-optimize'); ?></div>
            <div class="lwsop_prom_block">
                <ul>
                    <li class="lwsop_prom_bullet_element">
                        <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/check.svg', __DIR__)) ?>" alt="Logo Check Vert" width="20px" height="16px">
                        <span class="lwsop_prom_bullet_point_text"><?php echo esc_html_e('Images optimisation tool', 'lws-optimize'); ?></span>
                        <span class="lwsop_prom_bullet_point_plugin_specific"><?php echo esc_html_e('LWS Optimize', 'lws-optimize'); ?></span>
                    </li>
                    <li class="lwsop_prom_bullet_element">
                        <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/check.svg', __DIR__)) ?>" alt="Logo Check Vert" width="20px" height="16px">
                        <span class="lwsop_prom_bullet_point_text"><?php echo esc_html_e('Memcached and NGINX Dynamic cache', 'lws-optimize'); ?></span>
                        <span class="lwsop_prom_bullet_point_plugin_specific"><?php echo esc_html_e('LWS Optimize', 'lws-optimize'); ?></span>
                    </li>
                    <li class="lwsop_prom_bullet_element">
                        <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/check.svg', __DIR__)) ?>" alt="Logo Check Vert" width="20px" height="16px">
                        <span class="lwsop_prom_bullet_point_text"><?php echo esc_html_e('WordPress Manager: One-click connexion, clone, preproduction...', 'lws-optimize'); ?></span>
                    </li>
                    <li class="lwsop_prom_bullet_element">
                        <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/check.svg', __DIR__)) ?>" alt="Logo Check Vert" width="20px" height="16px">
                        <span class="lwsop_prom_bullet_point_text"><?php echo esc_html_e('Ultra fast servers in France optimized for WordPress', 'lws-optimize'); ?></span>
                    </li>
                    <li class="lwsop_prom_bullet_element">
                        <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/check.svg', __DIR__)) ?>" alt="Logo Check Vert" width="20px" height="16px">
                        <span class="lwsop_prom_bullet_point_text"><?php echo esc_html_e('Reactive 7d/7 support', 'lws-optimize'); ?></span>
                    </li>
                </ul>
                <img class="lwsop_prom_bullet_point" src="<?php echo esc_url(plugins_url('images/plugin_lws_optimize_logo.svg', __DIR__)) ?>" alt="Logo Check Vert" width="100px" height="100px">
            </div>
            <div id="lws_optimize_lws_prom_text"><?php esc_html_e('Check out our super-fast hosting and feel the difference for yourself. Take advantage of our exclusive offer: -15% additional on all our accommodation with the code WPEXT15 which can be combined with current offers. Site transfer to LWS is free!', 'lws-optimize'); ?></div>
            <div class="lwsop_modal_buttons" id="lwsop_specify_modal_buttons">
                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Abort', 'lws-optimize'); ?></button>
                <a class="lwsop_learnmore_offers" href="https://www.lws.fr/hebergement_wordpress.php" rel="noopener" target="_blank"><?php echo esc_html_e('Learn more about LWS Offers', 'lws-optimize'); ?></a>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('lws_op_filebased_cache_manage').addEventListener('change', function() {
        let checkbox = this;
        checkbox.disabled = true;
        let state = checkbox.checked;

        let timer = document.getElementById('lws_op_filebased_cache_timer');
        timer = timer.value ?? "lws_thrice_monthly";

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_optimize_fb_cache_change_status",
                timer: timer,
                state: state,
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('change_filebased_cache_status_nonce')); ?>'
            },
            success: function(data) {
                checkbox.disabled = false;
                checkbox.checked = false;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot activate cache.", "lws-optimize"); ?>");
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot activate cache.", "lws-optimize"); ?>");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        checkbox.checked = state;
                        if (state) {
                            callPopup('success', "<?php esc_html_e("File-based cache activated", "lws-optimize"); ?>");
                        } else {
                            callPopup('success', "<?php esc_html_e("File-based cache deactivated", "lws-optimize"); ?>");
                        }
                        window.location.reload();
                        break;
                    case 'FAILURE':
                        callPopup('error', "<?php esc_html_e("File-based cache state could not be altered.", "lws-optimize"); ?>");
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned. Cache state cannot be checked.", "lws-optimize"); ?>");
                        break;
                }
            },
            error: function(error) {
                checkbox.disabled = false;
                checkbox.checked = !state;
                callPopup('error', "<?php esc_html_e("Unknown error. Cannot change cache.", "lws-optimize"); ?>");
                console.log(error);
            }
        });

    });

    document.getElementById('lws_op_filebased_cache_timer').addEventListener('change', function() {
        let select = this;
        let checkbox = document.getElementById('lws_op_filebased_cache_manage');
        checkbox.disabled = true;

        let timer = select.value ?? "lws_thrice_monthly";

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_optimize_fb_cache_change_cache_time",
                timer: timer,
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('change_filebased_cache_timer_nonce')); ?>'
            },
            success: function(data) {
                checkbox.disabled = false;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot change cache timer.", 'lws-optimize'); ?>");
                    return 0;
                }
                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot change cache timer.", "lws-optimize"); ?>");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        callPopup('success', "<?php esc_html_e("File-based cache timer changed.", "lws-optimize"); ?>");
                        break;
                    case 'NO_DATA':
                        callPopup('error', "<?php esc_html_e("Timer was not given to the plugin. Could not be changed.", "lws-optimize"); ?>");
                        break;
                    case 'FAILURE':
                        callPopup('error', "<?php esc_html_e("Timer modification could not be saved to the database.", "lws-optimize"); ?>");
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned. Cache timer cannot be changed.", "lws-optimize"); ?>");
                        break;
                }
            },
            error: function(error) {
                checkbox.disabled = false;

                callPopup('error', "<?php esc_html_e("Unknown error. Cannot change cache timer.", "lws-optimize"); ?>");
                console.log(error);
            }
        });
    });

    var timer_change_amount_cache;

    document.getElementById('lws_op_fb_cache_preload_amount').addEventListener('change', function() {
        clearTimeout(timer_change_amount_cache);
        timer_change_amount_cache = setTimeout(function() {
            let value = document.getElementById('lws_op_fb_cache_preload_amount').value;
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    action: "lwsop_change_preload_amount",
                    amount: value,
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('update_fb_preload_amount')); ?>'
                },
                success: function(data) {
                    if (data === null || typeof data != 'string') {
                        callPopup('error', "<?php esc_html_e("Bad data returned. Cannot change pages amount.", "lws-optimize"); ?>");
                        return 0;
                    }

                    try {
                        var returnData = JSON.parse(data);
                    } catch (e) {
                        callPopup('error', "<?php esc_html_e("Bad data returned. Cannot change pages amount.", "lws-optimize"); ?>");
                        console.log(e);
                        return 0;
                    }

                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "<?php esc_html_e("Amount of pages cached at a time changed to ", "lws-optimize"); ?>" + value);
                            break;
                        case 'FAILED_ACTIVATE':
                            callPopup('error', "<?php esc_html_e("Failed to changed page amount.", "lws-optimize"); ?>");
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e("Unknown data returned. Failed to changed page amount.", "lws-optimize"); ?>");
                            break;
                    }
                },
                error: function(error) {
                    callPopup('error', "<?php esc_html_e("Unknown error. Cannot change page amount.", "lws-optimize"); ?>");
                    console.log(error);
                }
            });
        }, 750);
    });

    document.getElementById('lws_op_fb_cache_manage_preload').addEventListener('change', function() {
        let button = this;
        let value = this.checked;
        this.disabled = true;
        let amount = document.getElementById('lws_op_fb_cache_preload_amount');
        amount = amount.value ?? 3;

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                action: "lwsop_start_preload_fb",
                state: value,
                amount: amount,
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('update_fb_preload')); ?>'
            },
            success: function(data) {
                button.disabled = false;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot activate preloading.", "lws-optimize"); ?>");
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "<?php esc_html_e("Bad data returned. Cannot activate preloading.", "lws-optimize"); ?>");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
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
                                p_done.innerHTML = returnData['data']['preload_done'] + "/" + returnData['data']['preload_quantity']
                            }
                        } else {
                            let p_info = document.getElementById('lwsop_current_preload_info');
                            let p_done = document.getElementById('lwsop_current_preload_done');

                            let block = document.getElementById('lwsop_preloading_status_block');
                            if (block != null) {
                                block.classList.add('hidden');
                            }

                            if (p_info != null) {
                                p_info.innerHTML = "<?php esc_html_e("Up-to-date", "lws-optimize"); ?>";
                            }

                            if (p_done != null) {
                                p_done.innerHTML = returnData['data']['preload_done'] + "/" + returnData['data']['preload_quantity']
                            }
                            callPopup('success', "<?php esc_html_e("Preloading is now deactivated.", "lws-optimize"); ?>");
                        }
                        break;
                    case 'FAILED_ACTIVATE':
                        callPopup('error', "<?php esc_html_e("Preloading failed to be modified.", "lws-optimize"); ?>");
                        button.checked = !button.checked;
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned. Preloading state cannot be verified.", "lws-optimize"); ?>");
                        break;
                }
            },
            error: function(error) {
                button.disabled = false;
                callPopup('error', "<?php esc_html_e("Unknown error. Cannot change preloading state.", "lws-optimize"); ?>");
                console.log(error);
            }
        });

    });

    // Global event listener for the modal
    document.addEventListener("click", function(event) {
        let domain = "<?php echo esc_url(site_url()); ?>"
        var element = event.target;
        if (element.getAttribute('name') == "lwsop_less_urls") {
            let amount_element = element.parentNode.parentNode.parentNode.children;
            amount_element = amount_element[0].classList.contains('lwsop_exclude_element') ? amount_element.length : amount_element.length - 1;
            if (amount_element > 1) {
                let element_remove = element.parentNode.parentNode;
                element_remove.remove();
            } else {
                // Empty the last remaining field instead of removing it
                element.parentNode.parentNode.childNodes[3].value = "";
            }
        }

        if (element.getAttribute('name') == "lwsop_more_urls") {
            let amount_element = document.getElementsByName("lwsop_exclude_url").length;
            let element_create = element.parentNode.parentNode;

            let new_element = document.createElement("div");
            new_element.insertAdjacentHTML("afterbegin", `
                <div class="lwsop_exclude_url">
                    ` + domain + `/
                </div>
                <input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="">
                <div class="lwsop_exclude_action_buttons">
                    <div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
                    <div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
                </div>
            `);
            new_element.classList.add('lwsop_exclude_element');

            element_create.after(new_element);
        }

        if (element.getAttribute('id') == "lwsop_submit_excluded_form") {
            let form = document.getElementById('lwsop_form_exclude_urls');
            if (form !== null) {
                form.dispatchEvent(new Event('submit'));
            }
        }

        if (element.getAttribute('id') == "lwsop_submit_specified_form") {
            let form = document.getElementById('lwsop_form_specify_urls');
            if (form !== null) {
                form.dispatchEvent(new Event('submit'));
            }
        }
    });

    document.getElementById('lwsop_form_specify_urls').addEventListener("submit", function(event) {
        var element = event.target;
        if (element.getAttribute('id') == "lwsop_form_specify_urls") {
            event.preventDefault();
            let formData = jQuery(this).serializeArray();
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: formData,
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_save_specified_nonce')); ?>',
                    action: "lwsop_save_specified_url"
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

                    jQuery(document.getElementById('lwsop_specify_urls')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            document.getElementById('lwsop_specified_count').innerHTML = returnData['data'].length;
                            callPopup('success', "Les URLs ont bien été sauvegardées");
                            break;
                        case 'FAILED':
                            callPopup('error', "Les URLs n'ont pas pu être sauvegardées");
                            break;
                        case 'NO_DATA':
                            callPopup('error', "Les URLs n'ont pas pu être sauvegardées car aucune donnée n'a été trouvée");
                            break;
                        default:
                            callPopup('error', "Les URLs n'ont pas pu être sauvegardées car une erreur est survenue");
                            break;
                    }
                },
                error: function(error) {
                    console.log(error);
                }
            });
        }
    });

    if (document.getElementById('lws_op_fb_cache_manage_specificurl') !== null) {
        document.getElementById('lws_op_fb_cache_manage_specificurl').addEventListener('click', function() {
            let form = document.getElementById('lwsop_form_specify_urls');
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
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_get_specified_url_nonce')); ?>',
                    action: "lwsop_get_specified_url"
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
                            let urls = returnData['data'];
                            let domain = returnData['domain'];
                            document.getElementById('lwsop_specify_modal_buttons').innerHTML = `
                                <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                                <button type="button" id="lwsop_submit_specified_form" class="lwsop_validatebutton">
                                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                                    <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                                </button>
                            `;
                            form.innerHTML = `
                            <div class="lwsop_modal_infobubble">
                                <?php esc_html_e('Configurate rules to realize an automatic cache purge of some pages on top of the normal purge.', 'lws-optimize'); ?>
                            </div>`;
                            if (!urls.length) {
                                form.insertAdjacentHTML('beforeend', `
                                    <div class="lwsop_exclude_element">
                                        <div class="lwsop_exclude_url">
                                            ` + domain + `/
                                        </div>
                                        <input type="text" class="lwsop_exclude_input" name="lwsop_specific_url" value="">
                                        <div class="lwsop_exclude_action_buttons">
                                            <div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
                                            <div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
                                        </div>
                                    </div>
                                `);
                            } else {
                                for (var i in urls) {
                                    form.insertAdjacentHTML('beforeend', `
                                        <div class="lwsop_exclude_element">
                                            <div class="lwsop_exclude_url">
                                                ` + domain + `/
                                            </div>
                                            <input type="text" class="lwsop_exclude_input" name="lwsop_specific_url" value="` + urls[i] + `">
                                            <div class="lwsop_exclude_action_buttons">
                                                <div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
                                                <div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
                                            </div>
                                        </div>
                                    `);
                                }
                            }
                            break;
                        default:
                            break;
                    }
                },
                error: function(error) {
                    console.log(error);
                }
            });
        });
    }

    if (document.getElementById('lwsop_form_exclude_urls') !== null) {
        document.getElementById('lwsop_form_exclude_urls').addEventListener("submit", function(event) {
            var element = event.target;
            if (element.getAttribute('id') == "lwsop_form_exclude_urls") {
                event.preventDefault();
                let formData = jQuery(this).serializeArray();
                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        data: formData,
                        _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_save_excluded_nonce')); ?>',
                        action: "lwsop_save_excluded_url"
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

                        jQuery(document.getElementById('lwsop_exclude_urls')).modal('hide');
                        switch (returnData['code']) {
                            case 'SUCCESS':
                                callPopup('success', "Les URLs ont bien été sauvegardées");
                                break;
                            case 'FAILED':
                                callPopup('error', "Les URLs n'ont pas pu être sauvegardées");
                                break;
                            case 'NO_DATA':
                                callPopup('error', "Les URLs n'ont pas pu être sauvegardées car aucune donnée n'a été trouvée");
                                break;
                            default:
                                callPopup('error', "Les URLs n'ont pas pu être sauvegardées car une erreur est survenue");
                                break;
                        }
                    },
                    error: function(error) {
                        console.log(error);
                    }
                });
            }
        });
    }

    document.getElementById('lws_op_fb_cache_exclusion_manage').addEventListener('click', function() {
        let form = document.getElementById('lwsop_form_exclude_urls');
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
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_get_excluded_nonce')); ?>',
                action: "lwsop_get_excluded_url"
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

                document.getElementById('lwsop_exclude_modal_buttons').innerHTML = `
                    <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                    <button type="button" id="lwsop_submit_excluded_form" class="lwsop_validatebutton">
                        <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                        <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                    </button>
                `;

                console.log(returnData);

                switch (returnData['code']) {
                    case 'SUCCESS':
                        lwsop_submit_excluded_form
                        let urls = returnData['data'];
                        let domain = returnData['domain'];
                        form.innerHTML = `
                        <div class="lwsop_modal_infobubble">
                            <?php esc_html_e('You can exclude specifics URLs from the caching process. Example on the usage of "*": "products/*" will exclude all sub-pages of "products". To exclude the homepage, exclude "/".', 'lws-optimize'); ?>
                        </div>`;
                        if (!urls.length) {
                            form.insertAdjacentHTML('beforeend', `
								<div class="lwsop_exclude_element">
									<div class="lwsop_exclude_url">
										` + domain + `/
									</div>
									<input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="">
									<div class="lwsop_exclude_action_buttons">
										<div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
										<div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
									</div>
								</div>
							`);
                        } else {
                            for (var i in urls) {
                                form.insertAdjacentHTML('beforeend', `
									<div class="lwsop_exclude_element">
										<div class="lwsop_exclude_url">
											` + domain + `/
										</div>
										<input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="` + urls[i] + `">
										<div class="lwsop_exclude_action_buttons">
											<div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
											<div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
										</div>
									</div>
								`);
                            }
                        }
                        break;
                    default:
                        break;
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });

    document.getElementById('lws_op_fb_cache_remove').addEventListener('click', function() {
        let button = this;
        let old_text = this.innerHTML;
        this.innerHTML = `
            <span name="loading" style="padding-left:5px">
                <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
            </span>
        `;

        this.disabled = true;

        let ajaxRequest = jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_clear_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
            },
            success: function(data) {
                button.disabled = false;
                button.innerHTML = old_text;

                if (data === null || typeof data != 'string') {
                    callPopup('error', "Bad data returned. Cannot empty cache.");
                    return 0;
                }

                try {
                    var returnData = JSON.parse(data);
                } catch (e) {
                    callPopup('error', "Bad data returned. Cannot empty cache.");
                    console.log(e);
                    return 0;
                }

                switch (returnData['code']) {
                    case 'SUCCESS':
                        callPopup('success', "<?php esc_html_e("File-based cache has been emptied.", "lws-optimize"); ?>");
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned. Cache cannot be emptied."); ?>");
                        break;
                }
            },
            error: function(error) {
                button.disabled = false;
                button.innerHTML = old_text;
                callPopup('error', "<?php esc_html_e("Unknown error. Cannot empty cache.", "lws-optimize"); ?>");
                console.log(error);
            }
        });

    });

    if (document.getElementById('lws_op_clear_dynamic_cache') !== null) {
        document.getElementById('lws_op_clear_dynamic_cache').addEventListener("click", function(event) {
            let button = this;
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;

            this.disabled = true;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_empty_d_cache_nonce')); ?>',
                    action: "lwsop_dump_dynamic_cache"
                },
                success: function(data) {
                    button.disabled = false;
                    button.innerHTML = old_text;

                    if (data === null || typeof data != 'string') {
                        return 0;
                    }

                    try {
                        var returnData = JSON.parse(data);
                    } catch (e) {
                        console.log(e);
                        return 0;
                    }

                    jQuery(document.getElementById('lwsop_exclude_urls')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "Le cache dynamique a bien été vidé.");
                            break;
                        default:
                            callPopup('error', "Une erreur est survenue, le cache dynamique n'a pas été vidé");
                            break;
                    }
                },
                error: function(error) {
                    button.disabled = false;
                    button.innerHTML = old_text;
                    console.log(error);
                }
            });
        });
    }

    if (document.getElementById('lws_dynamic_cache_alt') != null) {
        document.getElementById('lws_dynamic_cache_alt').addEventListener('click', function() {
            this.checked = false;
        });
    }

    if (document.getElementById('lws_open_prom_lws_checkbox') !== null) {
        document.getElementById('lws_open_prom_lws_checkbox').addEventListener('change', function() {
            this.checked = false;
        });
    }

    if (document.getElementById('lws_open_memcached_lws_checkbox') !== null) {
        document.getElementById('lws_open_memcached_lws_checkbox').addEventListener('change', function() {
            this.checked = false;
        });
    }

    if (document.getElementById('lws_open_prom_lws_memcached_checkbox') !== null) {
        document.getElementById('lws_open_prom_lws_memcached_checkbox').addEventListener('change', function() {
            this.checked = false;
        });
    }

    if (document.getElementById('lwsop_refresh_stats') !== null) {
        document.getElementById('lwsop_refresh_stats').addEventListener('click', function() {
            let button = this;
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;

            this.disabled = true;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_reloading_stats_nonce')); ?>',
                    action: "lwsop_reload_stats"
                },
                success: function(data) {
                    button.disabled = false;
                    button.innerHTML = old_text;

                    if (data === null || typeof data != 'string') {
                        return 0;
                    }

                    try {
                        var returnData = JSON.parse(data);
                    } catch (e) {
                        console.log(e);
                        return 0;
                    }

                    jQuery(document.getElementById('lwsop_exclude_urls')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            let stats = returnData['data'];
                            document.getElementById('lws_optimize_file_cache').children[2].children[0].innerHTML = `
                                <span>` + stats['desktop']['size'] + ` / ` + stats['desktop']['amount'] + `</span>
                            `;

                            document.getElementById('lws_optimize_mobile_cache').children[2].children[0].innerHTML = `
                                <span>` + stats['mobile']['size'] + ` / ` + stats['mobile']['amount'] + `</span>
                            `;

                            document.getElementById('lws_optimize_css_cache').children[2].children[0].innerHTML = `
                                <span>` + stats['css']['size'] + ` / ` + stats['css']['amount'] + `</span>
                            `;

                            document.getElementById('lws_optimize_js_cache').children[2].children[0].innerHTML = `
                                <span>` + stats['js']['size'] + ` / ` + stats['js']['amount'] + `</span>
                            `;
                            break;
                        default:
                            break;
                    }
                },
                error: function(error) {
                    button.disabled = false;
                    button.innerHTML = old_text;
                    console.log(error);
                }
            });
        });
    }
</script>

<script>
    let preload_check_button = document.getElementById('lwsop_update_preloading_value');
    if (preload_check_button != null) {
        preload_check_button.addEventListener('click', lwsop_refresh_preloading_cache);

        function lwsop_refresh_preloading_cache() {
            let checkbox_preload = document.getElementById('lws_op_fb_cache_manage_preload');
            if (checkbox_preload.checked != true) {
                return 0;
            }

            let button = this;
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;

            this.disabled = true;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_check_for_update_preload_nonce')); ?>',
                    action: "lwsop_check_preload_update"
                },
                success: function(data) {
                    button.disabled = false;
                    button.innerHTML = old_text;

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

                            let p_info = document.getElementById('lwsop_current_preload_info');
                            let p_done = document.getElementById('lwsop_current_preload_done');
                            let block = document.getElementById('lwsop_preloading_status_block');
                            let p_next = document.getElementById('lwsop_next_preload_info');

                            if (block != null) {
                                block.classList.remove('hidden');
                            }

                            if (p_info != null) {
                                if (returnData['data']['ongoing'] == "true") {
                                    p_info.innerHTML = "<?php esc_html_e("Ongoing", "lws-optimize"); ?>";
                                } else {
                                    p_info.innerHTML = "<?php esc_html_e("Done", "lws-optimize"); ?>";
                                }
                            }

                            if (p_next != null) {
                                p_next.innerHTML = returnData['data']['next'];
                            }

                            if (p_done != null) {
                                p_done.innerHTML = returnData['data']['done'] + "/" + returnData['data']['quantity']
                            }
                            break;
                        default:
                            break;
                    }
                },
                error: function(error) {
                    button.disabled = false;
                    button.innerHTML = old_text;
                    console.log(error);
                }
            });
        }

        setInterval(function(){
                preload_check_button.dispatchEvent(new Event('click'));
            }, 60000);
    }
</script>

<script>
    jQuery(document).ready(function() {
        jQuery('[data-toggle="tooltip"]').tooltip();
    });
</script>
