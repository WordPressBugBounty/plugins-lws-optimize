<?php
$fb_preloaddata = [
    'state' => $config_array['filebased_cache']['preload_ongoing'] ?? "false",
    'quantity' => $config_array['filebased_cache']['preload_quantity'] ?? 0,
    'done' => $config_array['filebased_cache']['preload_done'] ?? 0,
];

$filebased_cache_options = $GLOBALS['lws_optimize']->lwsop_check_option("filebased_cache");
$filebased_timer = $filebased_cache_options['data']['timer'] ?? "lws_thrice_monthly";

$specified = "0";
if ($filebased_cache_options['state'] === "true" && !empty($filebased_cache_options['data']['specified'])) {
    $specified = count($filebased_cache_options['data']['specified']);
}

$preload_state = $filebased_cache_options['data']['preload'] ?? "false";
$preload_amount =  intval($filebased_cache_options['data']['preload_amount'] ?? 5);
$next_preload = wp_next_scheduled("lws_optimize_start_filebased_preload");
$local_timestamp = get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_preload), 'Y-m-d H:i:s');

$autopurge_options = $GLOBALS['lws_optimize']->lwsop_check_option("autopurge");
$htaccess_options = $GLOBALS['lws_optimize']->lwsop_check_option("htaccess_rules");
$memcached_force_off = false;
?>

<div class="lwsop_bluebanner" style="justify-content: space-between;">
    <h2 class="lwsop_bluebanner_title"><?php esc_html_e('Cache stats', 'lws-optimize'); ?></h2>
    <button type="button" class="lws_optimize_image_conversion_refresh" id="lws_op_regenerate_stats" name="lws_op_regenerate_stats">
        <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo MàJ" width="12px">
        <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
    </button>
    <!-- <button class="lwsop_blue_button" id="lwsop_refresh_stats"><?php //esc_html_e('Refresh', 'lws-optimize'); ?></button> -->
</div>

<div class="lwsop_contentblock_stats">
    <?php foreach ($caches as $type => $cache) : ?>
        <div class="lwsop_stat_block" id="<?php echo esc_attr($cache['id']); ?>">
            <img src="<?php echo esc_url(plugins_url("images/{$cache['image_file']}", __DIR__)) ?>" alt="<?php echo esc_attr($cache['image_alt']); ?>" width="<?php echo esc_attr($cache['width']); ?>" height="<?php echo esc_attr($cache['height']); ?>">
            <span><?php echo esc_html($cache["title"]); ?></span>
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

<div class="lwop_alert lwop_alert_warning">
    <i class="dashicons dashicons-warning"></i>
    <div>
        <span><?php esc_html_e('You are currently using WP-Cron, which means the preloading will only be executed when there is activity on your website and will use your website resources, slowing it down.', 'lws-optimize'); ?></span>
        <span><?php esc_html_e('We recommend using a server cron, which will execute tasks at a specified time and without hogging resources, no matter what is happening on your website.', 'lws-optimize'); ?></span>
        <span>
            <?php
                switch ($used_cache) {
                    case 'varnish':
                        esc_html_e('For more informations on how to setup server crons, follow this ', 'lws-optimize');
                        ?><a href="https://support.cpanel.net/hc/en-us/articles/10687844130199-How-to-replace-wp-cron-with-cron-job-without-WP-Toolkit" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
                        break;
                    case 'lws':
                        esc_html_e('For more informations on how to setup server crons by using the WPManager, follow this ', 'lws-optimize');
                        ?><a href="https://tutoriels.lws.fr/wordpress/wp-manager-de-lws-gerer-son-site-wordpress#Gerer_la_securite_et_les_parametres_generaux_de_votre_site_WordPress_avec_WP_Manager_LWS" rel="noopener" target="_blank"><?php esc_html_e('documentation.', 'lws-optimize'); ?></a><?php
                        break;
                    case 'unsupported':
                    default:
                        esc_html_e('For more informations on how to setup server crons, contact your hosting provider.', 'lws-optimize');
                        break;
                } ?>
        </span>
    </div>
</div>
<?php endif; ?>


<div class="lwsop_bluebanner"><h2 class="lwsop_bluebanner_title"><?php esc_html_e('Cache types', 'lws-optimize'); ?></h2></div>
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
            <a href="https://aide.lws.fr/a/1565" rel="noopener" target="_blank"><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" title="<?php esc_html_e("Learn more", "lws-optimize"); ?>"></a>
        </h2>
        <div class="lwsop_contentblock_description">
            <span><?php esc_html_e('Each time a page or post is modified on your website, their cache (LWSCache or Varnish Cache) will be purged automatically to always serve the most recent one to the users. For best performances, we recommend using a LWS-hosted website.', 'lws-optimize'); ?></span>
            <span><?php esc_html_e('You can also manually clear the cache at any moment.', 'lws-optimize'); ?></span>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <?php if ($used_cache == "unsupported" || $cache_state == "unsupported") : ?>
            <button type="button" class="lwsop_blue_button" disabled>
                <span>
                    <img src="<?php echo esc_url(plugins_url('images/supprimer.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                    <?php esc_html_e('Clear cache', 'lws-optimize'); ?>
                </span>
            </button>

        <?php else : ?>
            <button type="button" class="lwsop_blue_button" id="lws_op_clear_dynamic_cache" name="lws_op_clear_dynamic_cache">
                <span>
                    <img src="<?php echo esc_url(plugins_url('images/supprimer.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                    <?php esc_html_e('Clear cache', 'lws-optimize'); ?>
                </span>
            </button>
        <?php endif; ?>
    </div>

    <div class="lws_optimize_conversion_bar">
        <div class="lws_optimize_conversion_bar_element">
            <span class="lws_optimize_conversion_bar_element_title">
                <?php echo esc_html__('Cache type: ', 'lws-optimize'); ?>
            </span>
            <span class="lws_optimize_conversion_bar_dynamic_element">
                <?php switch ($used_cache) {
                    case 'varnish':
                        echo esc_html('Varnish Cache');
                        break;
                    case 'lws':
                        echo esc_html('LWSCache');
                        break;
                    case 'unsupported':
                    default:
                        esc_html_e('No supported cache found', 'lws-optimize');
                        ?><img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="modal" data-target="#lws_optimize_lws_prom" style="cursor: pointer;"><?php
                        break;
                } ?>
            </span>
        </div>
        <div class="lws_optimize_conversion_bar_element">
            <span class="lws_optimize_conversion_bar_element_title">
                <?php echo esc_html__('Cache status: ', 'lws-optimize'); ?>
            </span>
            <span class="lws_optimize_conversion_bar_dynamic_element">
                <?php switch ($cache_state) {
                    case "false":
                        esc_html_e('Deactivated', 'lws-optimize');
                        break;
                    case "true":
                        esc_html_e('Activated', 'lws-optimize');
                        break;
                    case null:
                    default:
                        esc_html_e('Unknown', 'lws-optimize');
                        break;
                } ?>
            </span>
        </div>
    </div>
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
            <button type="button" class="lwsop_darkblue_button" id="lws_op_fb_cache_exclusion_cookie_manage" data-toggle="modal" data-target="#lwsop_exclude_cookies">
                <span>
                    <?php esc_html_e('Exclude Cookies', 'lws-optimize'); ?>
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
            <?php esc_html_e('Use .htaccess for caching', 'lws-optimize'); ?>
            <span class="lwsop_recommended"><?php esc_html_e('recommended', 'lws-optimize'); ?></span>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Using .htaccess rules to manage the caching of your website will result in a decreased memory usage by PHP, improving performances, as well as faster loading times than with the default method.', 'lws-optimize'); ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_htaccess_rules_check">
            <input type="checkbox" name="lws_optimize_htaccess_rules_check" id="lws_optimize_htaccess_rules_check" <?php echo $htaccess_options['state'] === "true" ? esc_html("checked") : ""; ?>>
            <span class="slider round"></span>
        </label>
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
            <br><br>
            <?php esc_html_e( 'This option uses WordPress sitemap to work. If you encounter issues such as the preloading not starting, make sure the sitemap is activated.', 'lws-optimize' );  ?>
        </div>
        <div class="lwsop_contentblock_fbcache_input_preload_block">
            <input class="lwsop_contentblock_fbcache_input_preload" type="number" min="1" max="15" name="lws_op_fb_cache_preload_amount" id="lws_op_fb_cache_preload_amount" value="<?php echo esc_attr($preload_amount); ?>" onkeydown="return false">
            <div class="lwsop_contentblock_input_preload_label"><?php esc_html_e('pages per minutes cached', 'lws-optimize'); ?></div>
        </div>
        <div id="preload_amount_warning" class="lwop_alert lwop_alert_warning" style="display: none; margin-top: 10px; margin-left: 0px; font-size: 13px; max-width: 900px;">
            <i class="dashicons dashicons-warning"></i>
            <div>
                <span><?php esc_html_e('Setting a high preload value may cause performance issues on your website. For most sites, a value of 1-2 pages per minute is recommended.', 'lws-optimize'); ?></span>
            </div>
        </div>

        <script>
            // Show warning when preload amount is above 2
            document.getElementById('lws_op_fb_cache_preload_amount').addEventListener('change', function() {
                let warningElement = document.getElementById('preload_amount_warning');
                if (parseInt(this.value) > 2) {
                    warningElement.style.display = 'flex';
                } else {
                    warningElement.style.display = 'none';
                }
            });

            // Check initial value on page load
            document.addEventListener('DOMContentLoaded', function() {
                let preloadAmount = document.getElementById('lws_op_fb_cache_preload_amount');
                let warningElement = document.getElementById('preload_amount_warning');
                if (parseInt(preloadAmount.value) > 2) {
                    warningElement.style.display = 'flex';
                }
            });
        </script>

        <div id="lwsop_preloading_status_block" class="lwsop_contentblock_fbcache_preload <?php echo $preload_state == "false" ? esc_attr('hidden') : ''; ?>">
            <span class="lwsop_contentblock_fbcache_preload_label">
                <?php esc_html_e('Preloading status: ', 'lws-optimize'); ?>
                <button id="lwsop_update_preloading_value" class="lws_optimize_image_conversion_refresh">
                    <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo Refresh" width="15px" height="15px">
                    <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
                </button>
            </span>


            <div class="lws_optimize_conversion_bar">
                <div class="lws_optimize_conversion_bar_element">
                    <span class="lws_optimize_conversion_bar_element_title">
                        <?php echo esc_html__('Preloading state: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsop_current_preload_info"><?php echo $fb_preloaddata['state'] == "true" ? esc_html__('Ongoing', 'lws-optimize')  : esc_html__('Done', 'lws-optimize'); ?></span>
                </div>
                <div class="lws_optimize_conversion_bar_element">
                    <span class="lws_optimize_conversion_bar_element_title">
                        <img src="<?php echo esc_url(plugins_url('images/horloge.svg', __DIR__)); ?>" alt="Logo Horloge" width="15px" height="15px">
                        <?php echo esc_html__('Next preloading: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsop_next_preload_info"><?php echo $next_preload ? esc_attr($local_timestamp) : esc_html__('/', 'lws-optimize'); ?></span>
                </div>
                <div class="lws_optimize_conversion_bar_element">
                    <span class="lws_optimize_conversion_bar_element_title">
                        <img src="<?php echo esc_url(plugins_url('images/page.svg', __DIR__)); ?>" alt="Logo Page" width="15px" height="15px">
                        <?php esc_html_e('Page cached / Total pages: ', 'lws-optimize'); ?>
                    </span>
                    <span class="lws_optimize_conversion_bar_dynamic_element" id="lwsop_current_preload_done"><?php echo esc_html($fb_preloaddata['done'] . "/" . $fb_preloaddata['quantity']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_preload_cache_check">
            <input type="checkbox" name="lws_optimize_preload_cache_check" id="lws_optimize_preload_cache_check" <?php echo isset($config_array['filebased_cache']['preload']) && $config_array['filebased_cache']['preload'] == "true" ? esc_attr("checked") : ""; ?>>
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

<div class="lwsop_contentblock">
    <div class="lwsop_contentblock_leftside">
        <h2 class="lwsop_contentblock_title">
            <?php esc_html_e('Cache dynamic pages', 'lws-optimize');
            ?>
        </h2>
        <div class="lwsop_contentblock_description">
            <?php esc_html_e('Pages with URLs containing parameters (e.g., ma_page/?lang=en) will be cached. By default, these pages are excluded because they serve dynamic content that needs to be constantly up-to-date.', 'lws-optimize');
            ?>
        </div>
    </div>
    <div class="lwsop_contentblock_rightside">
        <label class="lwsop_checkbox" for="lws_optimize_no_parameters_check">
            <input type="checkbox" name="lws_optimize_no_parameters_check" id="lws_optimize_no_parameters_check" <?php echo isset($config_array['no_parameters']) && $config_array['no_parameters']['state'] == "true" ? esc_attr("checked") : ""; ?>>
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

<div class="modal fade" id="lwsop_exclude_cookies" tabindex='-1' aria-hidden='true'>
    <div class="modal-dialog">
        <div class="modal-content">
            <h2 class="lwsop_exclude_title"><?php echo esc_html_e('Exclude Cookies from the cache', 'lws-optimize'); ?></h2>
            <form method="POST" id="lwsop_form_exclude_cookies"></form>
            <div class="lwsop_modal_buttons" id="lwsop_exclude_cookies_modal_buttons">
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

        let originalLoading = checkbox.previousElementSibling;
        if (!originalLoading || !originalLoading.classList.contains('loading-spinner')) {
            let loadingSpan = document.createElement('span');
            loadingSpan.classList.add('loading-spinner');
            loadingSpan.innerHTML = `
            <span name="loading" style="padding-left:5px">
                <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
            </span>
            `;
            checkbox.parentNode.insertBefore(loadingSpan, checkbox);
        }

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
                let originalLoading = checkbox.previousElementSibling;
                if (originalLoading && originalLoading.classList.contains('loading-spinner')) {
                    originalLoading.remove();
                }

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
                let originalLoading = checkbox.previousElementSibling;
                if (originalLoading && originalLoading.classList.contains('loading-spinner')) {
                    originalLoading.remove();
                }
                callPopup('error', "<?php esc_html_e("Unknown error. Cannot change cache.", "lws-optimize"); ?>");
                console.log(error);
            }
        });

    });

    document.getElementById('lws_op_filebased_cache_timer').addEventListener('change', function() {
        let select = this;
        let checkbox = document.getElementById('lws_op_filebased_cache_manage');
        checkbox.disabled = true;

        let originalLoading = checkbox.previousElementSibling;
        if (!originalLoading || !originalLoading.classList.contains('loading-spinner')) {
            let loadingSpan = document.createElement('span');
            loadingSpan.classList.add('loading-spinner');
            loadingSpan.innerHTML = `
            <span name="loading" style="padding-left:5px">
                <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
            </span>
            `;
            checkbox.parentNode.insertBefore(loadingSpan, checkbox);
        }

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
                let originalLoading = checkbox.previousElementSibling;
                if (originalLoading && originalLoading.classList.contains('loading-spinner')) {
                    originalLoading.remove();
                }


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
                let originalLoading = checkbox.previousElementSibling;
                if (originalLoading && originalLoading.classList.contains('loading-spinner')) {
                    originalLoading.remove();
                }

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
            let isCookieModal = element.closest('#lwsop_exclude_cookies') !== null;

            let htmlContent = isCookieModal ? `
            <input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="">
            <div class="lwsop_exclude_action_buttons">
                <div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
                <div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
            </div>
            ` : `
            <div class="lwsop_exclude_url">
                ` + domain + `/
            </div>
            <input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="">
            <div class="lwsop_exclude_action_buttons">
                <div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
                <div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
            </div>
            `;

            new_element.insertAdjacentHTML("afterbegin", htmlContent);
            new_element.classList.add('lwsop_exclude_element');

            element_create.after(new_element);
        }

        let originalText = "";
        if (element.getAttribute('id') == "lwsop_submit_excluded_form") {
            originalText = element.innerHTML;
            element.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
            element.disabled = true;
            setTimeout(function() {
                if (originalText) {
                    element.innerHTML = originalText;
                    element.disabled = false;
                }
            }, 10000);

            let form = document.getElementById('lwsop_form_exclude_urls');
            if (form !== null) {
                form.dispatchEvent(new Event('submit'));
            }
        }

        if (element.getAttribute('id') == "lwsop_submit_excluded_cookies_form") {
            originalText = element.innerHTML;
            element.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
            element.disabled = true;
            setTimeout(function() {
                if (originalText) {
                    element.innerHTML = originalText;
                    element.disabled = false;
                }
            }, 10000);

            let form = document.getElementById('lwsop_form_exclude_cookies');
            if (form !== null) {
                form.dispatchEvent(new Event('submit'));
            }
        }

        if (element.getAttribute('id') == "lwsop_submit_specified_form") {
            originalText = element.innerHTML;
            element.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;
            element.disabled = true;
            setTimeout(function() {
                if (originalText) {
                    element.innerHTML = originalText;
                    element.disabled = false;
                }
            }, 10000);

            let form = document.getElementById('lwsop_form_specify_urls');
            if (form !== null) {
                form.dispatchEvent(new Event('submit'));
            }
        }
    });

    document.getElementById('lwsop_form_specify_urls').addEventListener("submit", function(event) {
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
    });

    if (document.getElementById('lws_op_fb_cache_manage_specificurl')) {
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


    if (document.getElementById('lwsop_form_exclude_cookies')) {
        document.getElementById('lwsop_form_exclude_cookies').addEventListener("submit", function(event) {
            event.preventDefault();
            let formData = jQuery(this).serializeArray();
            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    data: formData,
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_save_excluded_cookies_nonce')); ?>',
                    action: "lwsop_save_excluded_cookies"
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

                    jQuery(document.getElementById('lwsop_exclude_cookies')).modal('hide');
                    switch (returnData['code']) {
                        case 'SUCCESS':
                            callPopup('success', "Les cookies ont bien été sauvegardés");
                            break;
                        case 'FAILED':
                            callPopup('error', "Les cookies n'ont pas pu être sauvegardés");
                            break;
                        case 'NO_DATA':
                            callPopup('error', "Les cookies n'ont pas pu être sauvegardés car aucune donnée n'a été trouvée");
                            break;
                        default:
                            callPopup('error', "Les cookies n'ont pas pu être sauvegardés car une erreur est survenue");
                            break;
                    }
                },
                error: function(error) {
                    console.log(error);
                }
            });
        });
    }

    if (document.getElementById('lwsop_form_exclude_urls')) {
        document.getElementById('lwsop_form_exclude_urls').addEventListener("submit", function(event) {
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
                            callPopup('success', "Les URLs à exclure ont bien été sauvegardés");
                            break;
                        case 'FAILED':
                            callPopup('error', "Les URLs à exclure n'ont pas pu être sauvegardés");
                            break;
                        case 'NO_DATA':
                            callPopup('error', "Les URLs à exclure n'ont pas pu être sauvegardés car aucune donnée n'a été trouvée");
                            break;
                        default:
                            callPopup('error', "Les URLs à exclure n'ont pas pu être sauvegardés car une erreur est survenue");
                            break;
                    }
                },
                error: function(error) {
                    console.log(error);
                }
            });
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

                switch (returnData['code']) {
                    case 'SUCCESS':
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

    document.getElementById('lws_op_fb_cache_exclusion_cookie_manage').addEventListener('click', function() {
        let form = document.getElementById('lwsop_form_exclude_cookies');
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
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_get_excluded_cookies_nonce')); ?>',
                action: "lwsop_get_excluded_cookies"
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

                document.getElementById('lwsop_exclude_cookies_modal_buttons').innerHTML = `
                    <button type="button" class="lwsop_closebutton" data-dismiss="modal"><?php echo esc_html_e('Close', 'lws-optimize'); ?></button>
                    <button type="button" id="lwsop_submit_excluded_cookies_form" class="lwsop_validatebutton">
                        <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                        <?php echo esc_html_e('Save', 'lws-optimize'); ?>
                    </button>
                `;

                switch (returnData['code']) {
                    case 'SUCCESS':
                        let cookies = returnData['data'];
                        let domain = returnData['domain'];
                        form.innerHTML = `
                        <div class="lwsop_modal_infobubble">
                            <?php esc_html_e('You can exclude specifics Cookies from the caching process. To exclude cookies starting with the same base, use ".*", such as : "wordpress_logged_in_.*"', 'lws-optimize'); ?>
                        </div>`;
                        if (!cookies.length) {
                            form.insertAdjacentHTML('beforeend', `
								<div class="lwsop_exclude_element">
									<input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="">
									<div class="lwsop_exclude_action_buttons">
										<div class="lwsop_exclude_action_button red" name="lwsop_less_urls">-</div>
										<div class="lwsop_exclude_action_button green" name="lwsop_more_urls">+</div>
									</div>
								</div>
							`);
                        } else {
                            for (var i in cookies) {
                                form.insertAdjacentHTML('beforeend', `
									<div class="lwsop_exclude_element">
										<input type="text" class="lwsop_exclude_input" name="lwsop_exclude_url" value="` + cookies[i] + `">
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

    let fbcache_remove = document.getElementById('lws_op_fb_cache_remove');
    if (fbcache_remove) {
        fbcache_remove.addEventListener('click', function() {
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
                            callPopup('error', "<?php esc_html_e("Unknown data returned. Cache cannot be emptied.", "lws-optimize"); ?>");
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
    }

    let regen_cache = document.getElementById('lws_op_regenerate_stats');
    if (regen_cache) {
        regen_cache.addEventListener('click', function(){
            let button = this;
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading" style="padding-left:5px">
                    <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
                </span>
            `;

            this.disabled = true;

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    action: "lwsop_regenerate_cache",
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lws_regenerate_nonce_cache_fb')); ?>'
                },
                success: function(data) {
                    button.disabled = false;
                    button.innerHTML = old_text;

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
                            if (document.getElementById('lwsop_refresh_stats') !== null) {
                                document.getElementById('lwsop_refresh_stats').click();
                            }

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

                            callPopup('success', "<?php esc_html_e("File-based cache statistics have been synchronized", "lws-optimize"); ?>");
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e("Unknown data returned.", "lws-optimize"); ?>");
                            break;
                    }
                },
                error: function(error) {
                    button.disabled = false;
                    button.innerHTML = old_text;
                    callPopup('error', "<?php esc_html_e("Unknown error.", "lws-optimize"); ?>");
                    console.log(error);
                }
            });
        });
    }

    if (document.getElementById('lws_op_clear_dynamic_cache')) {
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
            let checkbox_preload = document.getElementById('lws_optimize_preload_cache_check');
            if (checkbox_preload.checked != true) {
                return 0;
            }

            let button = this;
            let old_text = this.innerHTML;
            this.innerHTML = `
                <span name="loading">
                    <img style="vertical-align:sub;" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
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
            }, 80000);
    }
</script>

<script>
    jQuery(document).ready(function() {
        jQuery('[data-toggle="tooltip"]').tooltip();
    });
</script>


<script>
    // Auto refresh for preload counter and cache stats every 2 minutes
    document.addEventListener('DOMContentLoaded', function() {
        // Set up auto-refresh for preload status
        if (document.getElementById('lwsop_update_preloading_value')) {
            setInterval(function() {
                document.getElementById('lwsop_update_preloading_value').click();
            }, 120000); // 2 minutes in milliseconds
        }

        // Set up auto-refresh for cache statistics
        if (document.getElementById('lwsop_refresh_stats')) {
            setInterval(function() {
                document.getElementById('lwsop_refresh_stats').click();
            }, 120000); // 2 minutes in milliseconds
        }
    });
</script>
