<?php
wp_cache_flush();

function lwsOpSizeConvert($size)
{
    $unit = array(__('b', 'lws-optimize'), __('K', 'lws-optimize'), __('M', 'lws-optimize'), __('G', 'lws-optimize'), __('T', 'lws-optimize'), __('P', 'lws-optimize'));
    if ($size <= 0) {
        return '0 ' . $unit[1];
    }
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . '' . $unit[$i];
}

// Fetch the configuration for each elements of LWSOptimize
$config_array = get_option('lws_optimize_config_array', []);

$personnalized = $config_array['personnalized'] ?? "false";
$autosetup  = $config_array['autosetup_type'] ?? "essential";
if (!in_array($autosetup, ['essential', 'optimized', 'max'])) {
    $autosetup = "essential";
}

// Check whether the plugin is deactivated temporarily or not
$is_deactivated = get_option('lws_optimize_deactivate_temporarily');
if ($is_deactivated) {
    $time = $is_deactivated - time();
    if ($time > 0) {
        $is_deactivated = $time . __(' seconds', 'lws-optimize');
    }
}

// Tabs to show
$tabs_list = array(
    array('frontend', __('Frontend', 'lws-optimize')),
    array('caching', __('Caching', 'lws-optimize')),
    array('medias', __('Medias', 'lws-optimize')),
    array('image_optimize_pro', __('Images', 'lws-optimize')),
    array('cdn', __('CDN', 'lws-optimize')),
    array('database', __('Database', 'lws-optimize')),
    array('pagespeed', __('Pagespeed test', 'lws-optimize')),
    ['logs', __('Logs', 'lws-optimize')],
    array('plugins', __('Our others plugins', 'lws-optimize')),
);

// Options that will be shown in the 3 tabs of the table
$essential_options = [
    'filecache' => [
        'name' => __('File caching', 'lws-optimize'),
        'description' => __('Reduce loading times and alleviate the load on the server, boosting performances', 'lws-optimize'),
        'safe' => true,
    ],
    'preloading' => [
        'name' => __('Cache Preloading', 'lws-optimize'),
        'description' => __('Preload the filecache for your pages before they are accessed to drastically improve performances', 'lws-optimize'),
        'safe' => true,
    ],
    'automatic_purge' => [
        'name' => __('Automatic Purge', 'lws-optimize'),
        'description' => __('Automatically purge the cache when you update your website to always keep it up to date', 'lws-optimize'),
        'safe' => true,
    ],
    'htaccess_rules' => [
        'name' => __('Caching via .htaccess', 'lws-optimize'),
        'description' => __('Add rules to your htaccess file to take over loading cache files, resulting in faster loading times', 'lws-optimize'),
        'safe' => true,
    ],
    'minify_css' => [
        'name' => __('CSS Minification', 'lws-optimize'),
        'description' => __('Reduce the size of CSS files to improve loading times', 'lws-optimize'),
        'safe' => true,
    ],
    'minify_js' => [
        'name' => __('JavaScript Minification', 'lws-optimize'),
        'description' => __('Reduce the size of JavaScript files to improve loading times', 'lws-optimize'),
        'safe' => true,
    ],
    'gzip_compression' => [
        'name' => __('Gzip Compression', 'lws-optimize'),
        'description' => __('Compress files to reduce their size, making them faster to download', 'lws-optimize'),
        'safe' => true,
    ],
    'image_lazyload' => [
        'name' => __('Lazy Loading', 'lws-optimize'),
        'description' => __('Load images/iframes/videos only when they are visible on your screen, reducing the amount of files to download on page load', 'lws-optimize'),
        'safe' => true,
    ],
];

$optimized_options = [
    'combine_css' => [
        'name' => __('CSS Combination', 'lws-optimize'),
        'description' => __('Combine CSS files together to reduce the number of requests made to the server', 'lws-optimize'),
        'safe' => true,
    ],
    'preload_css' => [
        'name' => __('CSS Preloading', 'lws-optimize'),
        'description' => __('Preload CSS files to improve page rendering by loading important CSS first', 'lws-optimize'),
        'safe' => true,
    ],
    'preload_fonts' => [
        'name' => __('Font Preloading', 'lws-optimize'),
        'description' => __('Preload fonts to improve page rendering by loading important fonts first', 'lws-optimize'),
        'safe' => true,
    ],
    'image_dimension' => [
        'name' => __('Image Dimensions', 'lws-optimize'),
        'description' => __('Add width and height attributes to images, to reduce layout shifts. Even if images load slowly, the space will be reserved for them on the page.', 'lws-optimize'),
        'safe' => true,
    ],
    'combine_js' => [
        'name' => __('JavaScript Combination', 'lws-optimize'),
        'description' => __('Combine JavaScript files together to reduce the number of requests made to the server. Some plugins or themes may be incompatible with this option', 'lws-optimize'),
        'safe' => false,
    ],
    'differ_js' => [
        'name' => __('Defer JavaScript', 'lws-optimize'),
        'description' => __('Load JavaScript files after the page has loaded to accelerate the rendering. May cause issues on JS-heavy websites', 'lws-optimize'),
        'safe' => false,
    ],
    'minify_html' => [
        'name' => __('HTML Minification', 'lws-optimize'),
        'description' => __('Reduce the size of HTML files by removing superfluous spaces, comments and characters', 'lws-optimize'),
        'safe' => false,
    ],
];

$max_options = [
    'deactivate_emoji' => [
        'name' => __('Emoji Removal', 'lws-optimize'),
        'description' => __('Remove the emoji script from your website to reduce loading times', 'lws-optimize'),
        'safe' => true,
    ],
    'remove_query_string' => [
        'name' => __('Query String Removal', 'lws-optimize'),
        'description' => __('Remove query strings from static resources to improve caching', 'lws-optimize'),
        'safe' => true,
    ],
    'unused_css' => [
        'name' => __('Unused CSS Removal', 'lws-optimize'),
        'description' => __('Remove unused CSS from your website to reduce the size of CSS files. This may be incompatible with some plugins or themes and will increase preloading times due to having to call an API', 'lws-optimize'),
        'safe' => false,
    ],
    'critical_css' => [
        'name' => __('Critical CSS', 'lws-optimize'),
        'description' => __('Generate critical CSS for your website to improve loading times. This may be incompatible with some plugins or themes and will increase preloading times due to having to call an API', 'lws-optimize'),
        'safe' => false,
    ],
    'delay_js' => [
        'name' => __('Delay JavaScript', 'lws-optimize'),
        'description' => __('Load JavaScript only after user interaction (mouse movement, keyboard press, touch), reducing initial load time and improving page speed scores', 'lws-optimize'),
        'safe' => false,
    ],
];

// Check whether Memcached id available on this hosting or not.
$memcached_locked = false;
$memcache_state = false;

if (class_exists('Memcached')) {
    $memcached = new Memcached();
    if (empty($memcached->getServerList())) {
        $memcached->addServer('localhost', 11211);
    }

    if ($memcached->getVersion() === false) {
        $memcached_locked = true;
    } else {
        $memcache_state = true;
    }
}

$filecache_state = $config_array["filebased_cache"]['state'] ? $config_array['filebased_cache']['state'] : "false";

// Save whether Memcached server is actually reachable (before the config-state override below).
// true  → PHP class exists + server responds → CAN be activated
// false → either PHP extension missing or server unreachable → CANNOT be activated
$memcache_available = $memcache_state;

// Build a human-readable reason for why Memcached cannot be activated (used in the UI below).
if (!class_exists('Memcached')) {
    $memcache_unavailable_reason = __("The PHP « Memcached » extension is not installed on this server. Contact your hosting provider to enable it.", 'lws-optimize');
} elseif ($memcached_locked) {
    $memcache_unavailable_reason = __("The Memcached server is not reachable (localhost:11211). The service may be stopped or blocked by the firewall. Contact your hosting provider.", 'lws-optimize');
} else {
    $memcache_unavailable_reason = '';
}

// Get the state of the Memcached option, checking for the optimize option and the module state
$memcache_state = ($memcache_state && isset($config_array["memcached"]['state']) && $config_array["memcached"]['state'] == "true") ? true : false;

// Check server cache state using environment variables
$cache_state = null;
$used_cache = "unsupported";
$clean_used_cache = "";

// Check for LWSCache
if (!empty($_SERVER['lwscache']) || !empty($_ENV['lwscache'])) {
    $used_cache = "lws";
    $clean_used_cache = "LWSCache";
    $server_value = !empty($_SERVER['lwscache']) ? $_SERVER['lwscache'] : $_ENV['lwscache'];
    $cache_state = (strtolower($server_value) == "on" || $server_value == "1" || $server_value === true) ? "true" : "false";
}
// Check for Varnish cache
elseif (!empty($_SERVER['HTTP_X_VARNISH'])) {
    $used_cache = "varnish";
    $clean_used_cache = "VarnishCache";
    // Check if Varnish is active through any of the possible headers
    foreach (['HTTP_X_CACHE_ENABLED', 'HTTP_EDGE_CACHE_ENGINE_ENABLED', 'HTTP_EDGE_CACHE_ENGINE_ENABLE'] as $header) {
        if (!empty($_SERVER[$header])) {
            $cache_state = ($_SERVER[$header] == "1" || strtolower($_SERVER[$header]) == "on" || $_SERVER[$header] === true) ? "true" : "false";
            break;
        }
    }
}
// Check for LiteSpeed or other Edge cache engines
elseif (isset($_SERVER['HTTP_X_CACHE_ENABLED']) && isset($_SERVER['HTTP_EDGE_CACHE_ENGINE'])) {
    $engine = strtolower($_SERVER['HTTP_EDGE_CACHE_ENGINE']);
    if ($engine == 'litespeed') {
        $used_cache = "litespeed";
        $clean_used_cache = "LiteSpeed";
    } elseif ($engine == 'varnish') {
        $used_cache = "varnish";
        $clean_used_cache = "VarnishCache";
    }

    if ($used_cache !== "unsupported") {
        $cache_state = ($_SERVER['HTTP_X_CACHE_ENABLED'] == "1" || strtolower($_SERVER['HTTP_X_CACHE_ENABLED']) == "on" || $_SERVER['HTTP_X_CACHE_ENABLED'] === true) ? "true" : "false";
    }
}

// Get the cache statistics from base
$cache_stats = get_option('lws_optimize_cache_statistics', []);
$cache_stats = array_merge([
    'desktop' => ['amount' => 0, 'size' => 0],
    'mobile' => ['amount' => 0, 'size' => 0],
    'css' => ['amount' => 0, 'size' => 0],
    'js' => ['amount' => 0, 'size' => 0],
], $cache_stats);

// Get the specifics values
$file_cache = $cache_stats['desktop']['amount'];
$file_cache_size = lwsOpSizeConvert($cache_stats['desktop']['size']);

$mobile_cache = $cache_stats['mobile']['amount'] ?? 0;
$mobile_cache_size = lwsOpSizeConvert($cache_stats['mobile']['size']);

$css_cache = $cache_stats['css']['amount'] ?? 0;
$css_cache_size = lwsOpSizeConvert($cache_stats['css']['size']);

$js_cache = $cache_stats['js']['amount'] ?? 0;
$js_cache_size = lwsOpSizeConvert($cache_stats['js']['size']);

$caches = [
    'files' => [
        'size' => $file_cache_size,
        'title' => __('Computer Cache', 'lws-optimize'),
        'alt_title' => __('Computer', 'lws-optimize'),
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
        'alt_title' => __('Mobile', 'lws-optimize'),
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
        'alt_title' => __('CSS', 'lws-optimize'),
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
        'alt_title' => __('JS', 'lws-optimize'),
        'amount' => $js_cache,
        'id' => "lws_optimize_js_cache",
        'image_file' => "js.svg",
        'image_alt' => "js logo in a window icon",
        'width' => "60px",
        'height' => "60px",

    ],
];

$arr = array('strong' => array());
$plugins = array(
    'lws-hide-login' => array('LWS Hide Login', __('This plugin <strong>hide your administration page</strong> (wp-admin) and lets you <strong>change your login page</strong> (wp-login). It offers better security as hackers will have more trouble finding the page.', 'lws-optimize'), true),
    'lws-cleaner' => array('LWS Cleaner', __('This plugin lets you <strong>clean your WordPress website</strong> in a few clics to gain speed: posts, comments, terms, users, settings, plugins, medias, files.', 'lws-optimize'), true),
    'lws-affiliation' => array('LWS Affiliation', __('With this plugin, you can add banners and widgets on your website and use those with your <strong>affiliate account LWS</strong>. Earn money and follow the evolution of your gains on your website.', 'lws-optimize'), false),
    'lws-tools' => array('LWS Tools', __('This plugin provides you with several tools and shortcuts to manage, secure and optimise your WordPress website. Updating plugins and themes, accessing informations about your server, managing your website parameters, etc... Personnalize every aspect of your website!', 'lws-optimize'), false)
);

$plugins_activated = array();
$all_plugins = get_plugins();

foreach ($plugins as $slug => $plugin) {
    if (is_plugin_active($slug . '/' . $slug . '.php')) {
        $plugins_activated[$slug] = "full";
    } elseif (array_key_exists($slug . '/' . $slug . '.php', $all_plugins)) {
        $plugins_activated[$slug] = "half";
    }
}
?>

<script>
    var function_ok = true;
</script>

<div class="lwsoptimize_container">
    <?php if ($is_deactivated) : ?>
        <div class="lwsoptimize_main_content_fogged"></div>
    <?php endif ?>
    <div class="lwsop_title_banner">
        <div class="lwsop_top_banner">
            <img src="<?php echo esc_url(plugins_url('images/plugin_lws_optimize_logo.svg', __DIR__)) ?>" alt="LWS Optimize Logo" width="80px" height="80px">
            <div class="lwsop_top_banner_text">
                <div class="lwsop_top_title_block">
                    <div class="lwsop_top_title_col">
                        <div class="lwsop_top_title">
                            <span><?php echo esc_html('LWS Optimize'); ?></span>
                            <span><?php esc_html_e('by', 'lws-optimize'); ?></span>
                            <span class="logo_lws"></span>

                            <button class="lwsop_dropdown_button">
                                <span class="lwsop_dropdown_text">
                                    <?php if ($is_deactivated) : ?>
                                        <?php echo esc_html__('Deactivated for: ', 'lws-optimize') . $is_deactivated; ?>
                                    <?php else : ?>
                                        <?php esc_html_e('Deactivate temporarily: ', 'lws-optimize'); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="lwsop_dropdown_arrow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </span>
                                <div class="lwsop_dropdown_content">
                                    <?php if ($is_deactivated) : ?>
                                        <a href="#" data-config="0"><?php esc_html_e('Activate', 'lws-optimize'); ?></a>
                                    <?php else : ?>
                                        <a href="#" data-config="300"><?php esc_html_e('5 minutes', 'lws-optimize'); ?></a>
                                        <a href="#" data-config="1800"><?php esc_html_e('30 minutes', 'lws-optimize'); ?></a>
                                        <a href="#" data-config="3600"><?php esc_html_e('1 hour', 'lws-optimize'); ?></a>
                                        <a href="#" data-config="86400"><?php esc_html_e('1 day', 'lws-optimize'); ?></a>
                                    <?php endif; ?>
                                </div>
                            </button>
                        </div>

                        <div class="lwsop_top_description">
                            <?php echo esc_html_e('Your WordPress website, faster, lighter, smoother. LWS Optimize improves loading speed through caching, media optimization, minification, file concatenation...', 'lws-optimize'); ?>
                        </div>
                    </div>
                    <div class="lwsop_rate_block">
                        <div class="lwsop_top_rateus">
                            <?php echo esc_html_e('You like this plugin ? ', 'lws-optimize'); ?>
                            <?php echo wp_kses(__('A <a href="https://wordpress.org/support/plugin/lws-optimize/reviews/#new-post" target="_blank" class="link_to_rating_with_stars"><div class="lwsop_stars">★★★★★</div> rating</a> will motivate us a lot.', 'lws-optimize'), ['a' => ['class' => [], 'href' => [], 'target' => []], 'div' => ['class' => []]]); ?>
                        </div>
                        <div class="lwsop_bottom_rateus">
                            <img src="<?php echo esc_url(plugins_url('images/flamme.svg', __DIR__)) ?>" alt="Flamme Logo" width="16px" height="20px">
                            <?php echo wp_kses(__('<b>-15%</b> on our <a href="https://www.lws.fr/hebergement_wordpress.php" target="_blank" class="link_to_support">WordPress hostings</a> with the code', 'lws-optimize'), ['b' => [], 'a' => ['class' => [], 'href' => [], 'target' => []]]); ?>
                            <div class="lwsop_top_code">
                                WPEXT15
                                <img src="<?php echo esc_url(plugins_url('images/copier_new.svg', __DIR__)) ?>" alt="Logo Copy Element" width="15px" height="18px" onclick="lwsoptimize_copy_clipboard(this)" readonly text="WPEXT15">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (sanitize_text_field($_GET['page']) === 'lws-op-config') : ?>
    <div class="lwsop_oneclickconfig_main">
        <div class="lwsop_dashboard_block" style="flex: 70%;">
            <div class="lwsop_oneclickconfig_block">
                <h2 class="lwsop_oneclickconfig_title">
                    <div class="lwsop_oneclickconfig_title_left">
                        <span class="lwsop_oneclickconfig_title_left_main"><?php esc_html_e('Speed up your website in 1 clic', 'lws-optimize'); ?></span>
                        <span class="lwsop_oneclickconfig_title_left_sub"><?php esc_html_e('Choose an optimization level with one click and/or access advanced settings to customize.', 'lws-optimize'); ?></span>
                    </div>
                    <div class="lwsop_oneclickconfig_save_row">
                        <button class="lwsop_oneclickconfig_button" id="lwsop_oneclickconfig" onclick="lwsop_change_settings_group(this)">
                            <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                            <?php esc_html_e('Save', 'lws-optimize'); ?>
                        </button>
                    </div>
                </h2>

                <div class="lwsop_oneclickconfig_table">
                    <div class="lwsop_oneclickconfig_table_column">
                        <?php if ($personnalized == "true" && $autosetup == "essential") : ?>
                        <div class="lwsop_oneclickconfig_floating_bubble">
                            <?php esc_html_e('Personnalized in advanced mode', 'lws-optimize'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="lwsop_oneclickconfig_table_column_header">
                            <div class="lwsop_oneclickconfig_table_column_header_radio">
                                <input class="lwsop_oneclickconfig_radiobutton" type="radio" name="lwsop_oneclickconfig_radio[]" value="essential">
                                <span class="lwsop_oneclickconfig_table_column_header_title"><?php esc_html_e('Essential', 'lws-optimize'); ?></span>
                            </div>
                            <span class="lwsop_oneclickconfig_table_column_header_description"><?php esc_html_e('Beginner-friendly', 'lws-optimize'); ?></span>
                        </div>
                        <div class="lwsop_oneclickconfig_table_column_content">
                            <ul class="lwsop_oneclickconfig_table_column_content_list">
                                <?php foreach ($essential_options as $option => $value) : ?>
                                    <li>
                                        <div class="lwsop_oneclickconfig_option">
                                            <div class="lwsop_oneclickconfig_option_left">
                                                <?php if ($value['safe']) : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/check_vert.svg', dirname(__FILE__))); ?>" alt="Safe option" width="12px" height="12px">
                                                <?php else : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/attention.svg', dirname(__FILE__))); ?>" alt="Warning" width="12px" height="12px">
                                                <?php endif; ?>
                                                <span class="lwsop_oneclickconfig_table_column_content_title"><?php echo esc_html($value['name']); ?></span>
                                            </div>
                                            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo esc_html($value['description']); ?>">
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="lwsop_oneclickconfig_table_column">
                        <?php if ($personnalized == "true" && $autosetup == "optimized") : ?>
                        <div class="lwsop_oneclickconfig_floating_bubble">
                            <?php esc_html_e('Personnalized in advanced mode', 'lws-optimize'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="lwsop_oneclickconfig_table_column_header">
                            <div class="lwsop_oneclickconfig_table_column_header_radio">
                                <input class="lwsop_oneclickconfig_radiobutton" type="radio" name="lwsop_oneclickconfig_radio[]" value="optimized">
                                <span class="lwsop_oneclickconfig_table_column_header_title"><?php esc_html_e('Optimized', 'lws-optimize'); ?></span>
                            </div>
                            <span class="lwsop_oneclickconfig_table_column_header_description"><?php esc_html_e('Balance performance and stability', 'lws-optimize'); ?></span>
                        </div>
                        <div class="lwsop_oneclickconfig_table_column_content">
                            <ul class="lwsop_oneclickconfig_table_column_content_list">
                                <li>
                                    <div class="lwsop_oneclickconfig_option">
                                        <div class="lwsop_oneclickconfig_option_left">
                                            <img src="<?php echo esc_url(plugins_url('images/check_noir.svg', dirname(__FILE__))); ?>" alt="Safe option" width="12px" height="12px">
                                            <span class="lwsop_oneclickconfig_table_column_content_title bold"><?php esc_html_e('Everything in Essential', 'lws-optimize'); ?></span>
                                        </div>
                                    </div>
                                </li>
                                <?php foreach ($optimized_options as $option => $value) : ?>
                                    <li>
                                        <div class="lwsop_oneclickconfig_option">
                                            <div class="lwsop_oneclickconfig_option_left">
                                                <?php if ($value['safe']) : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/check_vert.svg', dirname(__FILE__))); ?>" alt="Safe option" width="12px" height="12px">
                                                <?php else : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/attention.svg', dirname(__FILE__))); ?>" alt="Warning" width="12px" height="12px">
                                                <?php endif; ?>
                                                <span class="lwsop_oneclickconfig_table_column_content_title"><?php echo esc_html($value['name']); ?></span>
                                            </div>
                                            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo esc_html($value['description']); ?>">
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="lwsop_oneclickconfig_table_column">
                        <?php if ($personnalized == "true" && $autosetup == "max") : ?>
                        <div class="lwsop_oneclickconfig_floating_bubble">
                            <?php esc_html_e('Personnalized in advanced mode', 'lws-optimize'); ?>
                        </div>
                        <?php endif; ?>
                        <div class="lwsop_oneclickconfig_table_column_header">
                            <div class="lwsop_oneclickconfig_table_column_header_radio">
                                <input class="lwsop_oneclickconfig_radiobutton" type="radio" name="lwsop_oneclickconfig_radio[]" value="max">
                                <span class="lwsop_oneclickconfig_table_column_header_title"><?php esc_html_e('Max', 'lws-optimize'); ?></span>
                            </div>
                            <span class="lwsop_oneclickconfig_table_column_header_description"><?php esc_html_e('Advanced, for confirmed users', 'lws-optimize'); ?></span>
                        </div>
                        <div class="lwsop_oneclickconfig_table_column_content">
                            <ul class="lwsop_oneclickconfig_table_column_content_list">
                                <li>
                                    <div class="lwsop_oneclickconfig_option">
                                        <div class="lwsop_oneclickconfig_option_left">
                                            <img src="<?php echo esc_url(plugins_url('images/check_noir.svg', dirname(__FILE__))); ?>" alt="Safe option" width="12px" height="12px">
                                            <span class="lwsop_oneclickconfig_table_column_content_title bold"><?php esc_html_e('Everything in Optimized', 'lws-optimize'); ?></span>
                                        </div>
                                    </div>
                                </li>
                                <?php foreach ($max_options as $option => $value) : ?>
                                    <li>
                                        <div class="lwsop_oneclickconfig_option">
                                            <div class="lwsop_oneclickconfig_option_left">
                                                <?php if ($value['safe']) : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/check_vert.svg', dirname(__FILE__))); ?>" alt="Safe option" width="12px" height="12px">
                                                <?php else : ?>
                                                    <img src="<?php echo esc_url(plugins_url('images/attention.svg', dirname(__FILE__))); ?>" alt="Warning" width="12px" height="12px">
                                                <?php endif; ?>
                                                <span class="lwsop_oneclickconfig_table_column_content_title"><?php echo esc_html($value['name']); ?></span>
                                            </div>
                                            <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo esc_html($value['description']); ?>">
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <button type="button" class="lwsop_darkblue_button lwsop_mt_20" onclick="window.location.href='?page=lws-op-config-advanced'">
                    <span>
                        <img src="<?php echo esc_url(plugins_url('images/avance.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                        <?php esc_html_e('Go to advanced mode', 'lws-optimize'); ?>
                    </span>
                </button>
            </div>
            <div class="lwsop_oneclickconfig_block">
                <?php
                // 4.5.0 — Bloc Utilisation du cache : montre les vraies stats (hits/misses/bytes)
                // collectées depuis stats.json. Si pas encore de données → message explicatif.
                $htaccess_on       = ($config_array['htaccess_rules']['state']          ?? 'false') === 'true';
                $intermediary_on   = ($config_array['htaccess_php_intermediary']['state'] ?? 'false') === 'true';
                if ($htaccess_on && !$intermediary_on) :
                ?>
                <div class="lwop_alert lwop_alert_warning" style="margin-bottom: 12px; font-size: 13px;">
                    <i class="dashicons dashicons-warning"></i>
                    <div>
                        <?php echo wp_kses(
                            __('<strong>Statistics are incomplete.</strong> .htaccess caching is active but the PHP stats intermediary is disabled. Cache hits served directly by Apache are not counted. Enable the <em>PHP stats intermediary</em> option in the Caching tab to restore full hit tracking.', 'lws-optimize'),
                            ['strong' => [], 'em' => []]
                        ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (class_exists('\\Lws\\Classes\\FileCache\\LwsOptimizeUsageStats')) {
                    $usage_stats = \Lws\Classes\FileCache\LwsOptimizeUsageStats::read();
                    $u24h = $usage_stats['totals_24h'];
                    $u7d  = $usage_stats['totals_7d'];
                    $u30d = $usage_stats['totals_30d'];
                    $has_data = ($u24h['hits'] + $u24h['misses'] + $u7d['hits'] + $u7d['misses']) > 0;
                    $sparkline_svg = \Lws\Classes\Admin\LwsOptimizeDashboardWidget::sparkline_svg($usage_stats['sparkline'], 600, 60);
                ?>
                <div class="lwsop_mt_20" id="lwsop_usage_stats_wrap">
                    <span class="lwsop_oneclickconfig_title_left_main"><?php echo esc_html(__('Real cache usage', 'lws-optimize')); ?></span>
                    <h3 class="lwsop_oneclickconfig_subtitle">
                        <span><?php echo esc_html(__('Measures hits/misses served to your visitors', 'lws-optimize')); ?></span>
                    </h3>

                    <div id="lwsop_usage_no_data" <?php if ($has_data) echo 'style="display:none"'; ?>>
                        <div class="lwsop_no_data_notice">
                            <strong><?php echo esc_html(__('No data yet', 'lws-optimize')); ?></strong><br>
                            <?php echo esc_html(__('Statistics are collected on each visit. Wait for a few visitors to browse the site, then come back here (or refresh the page).', 'lws-optimize')); ?>
                        </div>
                    </div>

                    <div id="lwsop_usage_data_section" <?php if (!$has_data) echo 'style="display:none"'; ?>>
                        <div class="lwsop_usage_grid">
                            <div class="lwsop_usage_card">
                                <h5><?php echo esc_html(__('Last 24 hours', 'lws-optimize')); ?></h5>
                                <div class="lwsop_usage_hitrate">
                                    <div class="big" id="lwsop_ustat_24h_rate" style="color:<?php echo $u24h['hit_rate'] >= 80 ? '#16a34a' : ($u24h['hit_rate'] >= 50 ? '#f59e0b' : '#dc2626'); ?>"><?php echo esc_html($u24h['hit_rate']); ?>%</div>
                                    <div class="lbl"><?php echo esc_html(__('Hit rate', 'lws-optimize')); ?></div>
                                </div>
                                <div class="lwsop_usage_metric"><span class="ok"><?php echo esc_html(__('Hits', 'lws-optimize')); ?></span><strong id="lwsop_ustat_24h_hits"><?php echo esc_html(number_format_i18n($u24h['hits'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span class="ko"><?php echo esc_html(__('Misses', 'lws-optimize')); ?></span><strong id="lwsop_ustat_24h_misses"><?php echo esc_html(number_format_i18n($u24h['misses'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span><?php echo esc_html(__('Data', 'lws-optimize')); ?></span><strong id="lwsop_ustat_24h_bytes"><?php echo esc_html(\Lws\Classes\Admin\LwsOptimizeDashboardWidget::format_bytes($u24h['bytes_saved'])); ?></strong></div>
                            </div>

                            <div class="lwsop_usage_card">
                                <h5><?php echo esc_html(__('Last 7 days', 'lws-optimize')); ?></h5>
                                <div class="lwsop_usage_hitrate">
                                    <div class="big" id="lwsop_ustat_7d_rate" style="color:<?php echo $u7d['hit_rate'] >= 80 ? '#16a34a' : ($u7d['hit_rate'] >= 50 ? '#f59e0b' : '#dc2626'); ?>"><?php echo esc_html($u7d['hit_rate']); ?>%</div>
                                    <div class="lbl"><?php echo esc_html(__('Hit rate', 'lws-optimize')); ?></div>
                                </div>
                                <div class="lwsop_usage_metric"><span class="ok"><?php echo esc_html(__('Hits', 'lws-optimize')); ?></span><strong id="lwsop_ustat_7d_hits"><?php echo esc_html(number_format_i18n($u7d['hits'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span class="ko"><?php echo esc_html(__('Misses', 'lws-optimize')); ?></span><strong id="lwsop_ustat_7d_misses"><?php echo esc_html(number_format_i18n($u7d['misses'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span><?php echo esc_html(__('Data', 'lws-optimize')); ?></span><strong id="lwsop_ustat_7d_bytes"><?php echo esc_html(\Lws\Classes\Admin\LwsOptimizeDashboardWidget::format_bytes($u7d['bytes_saved'])); ?></strong></div>
                            </div>

                            <div class="lwsop_usage_card">
                                <h5><?php echo esc_html(__('Last 30 days', 'lws-optimize')); ?></h5>
                                <div class="lwsop_usage_hitrate">
                                    <div class="big" id="lwsop_ustat_30d_rate" style="color:<?php echo $u30d['hit_rate'] >= 80 ? '#16a34a' : ($u30d['hit_rate'] >= 50 ? '#f59e0b' : '#dc2626'); ?>"><?php echo esc_html($u30d['hit_rate']); ?>%</div>
                                    <div class="lbl"><?php echo esc_html(__('Hit rate', 'lws-optimize')); ?></div>
                                </div>
                                <div class="lwsop_usage_metric"><span class="ok"><?php echo esc_html(__('Hits', 'lws-optimize')); ?></span><strong id="lwsop_ustat_30d_hits"><?php echo esc_html(number_format_i18n($u30d['hits'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span class="ko"><?php echo esc_html(__('Misses', 'lws-optimize')); ?></span><strong id="lwsop_ustat_30d_misses"><?php echo esc_html(number_format_i18n($u30d['misses'])); ?></strong></div>
                                <div class="lwsop_usage_metric"><span><?php echo esc_html(__('Data', 'lws-optimize')); ?></span><strong id="lwsop_ustat_30d_bytes"><?php echo esc_html(\Lws\Classes\Admin\LwsOptimizeDashboardWidget::format_bytes($u30d['bytes_saved'])); ?></strong></div>
                            </div>
                        </div>

                        <!-- Sparkline 30j -->
                        <div class="lwsop_usage_card">
                            <h5><?php echo esc_html(__('Hits trend over 30 days', 'lws-optimize')); ?></h5>
                            <div id="lwsop_ustat_sparkline"><?php echo $sparkline_svg; // SVG inline, contenu trusted ?></div>
                        </div>
                    </div>
                </div>
                <?php } // ferme le if (class_exists LwsOptimizeUsageStats) du bloc utilisation ?>
            </div>
        </div>

        <div class="lwsop_dashboard_block" style="flex: 30%;">
            <div class="lwsop_oneclickconfig_block">
                <span class="lwsop_oneclickconfig_title_left_main"><?php esc_html_e('Caching', 'lws-optimize'); ?></span>
                <h3 class="lwsop_oneclickconfig_subtitle">
                    <span><?php esc_html_e('Cache statistics', 'lws-optimize'); ?></span>
                    <button class="lwsop_oneclickconfig_button_whiteblue" onclick="lwsop_refresh_global_stats(this)">
                        <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo MàJ" width="12px">
                        <?php esc_html_e('Refresh', 'lws-optimize'); ?>
                    </button>
                </h3>

                <div class="lwsop_oneclickconfig_cachestats" id="cache_stats_element">
                    <div class="lwsop_loading_overlay" id="cache_stats_loading_overlay" style="display: none;">
                        <div class="lwsop_loading_spinner"></div>
                    </div>
                    <?php
                    // 4.4.1 — Vraie couverture cache basée sur les URLs sitemap réellement
                    // sur disque (au lieu du ratio cached/target confus quand cached > target).
                    //
                    // Méthode : on lit lws_optimize_sitemap_urls (les URLs publiques que le
                    // preload doit chauffer) et on vérifie pour chacune si le fichier
                    // cache/<path>/index_0.html existe (desktop) et cache-mobile/<path>/index_0.html
                    // existe (mobile). Cache transient 60s pour ne pas faire 128 file_exists()
                    // à chaque chargement de la page admin.
                    $preload_on = ($config_array['filebased_cache']['preload'] ?? 'false') === 'true';

                    // 4.4.2 — Fix bug 0/64 : LWS_OP_UPLOADS = ".../cache/lwsoptimize/"
                    // (SANS le sous-dossier /cache). Donc on ne peut PAS l'utiliser comme root —
                    // on hardcode WP_CONTENT_DIR + /cache/lwsoptimize/cache (et /cache-mobile).
                    $coverage = get_transient('lwsop_coverage_cache_v2');
                    if ($coverage === false) {
                        $sitemap = get_option('lws_optimize_sitemap_urls', ['urls' => []]);
                        $urls    = is_array($sitemap['urls'] ?? null) ? array_values($sitemap['urls']) : [];
                        $cache_root_d = WP_CONTENT_DIR . '/cache/lwsoptimize/cache';
                        $cache_root_m = WP_CONTENT_DIR . '/cache/lwsoptimize/cache-mobile';
                        $total = count($urls);
                        $hit_d = 0; $hit_m = 0;
                        foreach ($urls as $u) {
                            $path = trim(parse_url($u, PHP_URL_PATH) ?: '/', '/');
                            $dir_d = $path === '' ? $cache_root_d : $cache_root_d . '/' . $path;
                            $dir_m = $path === '' ? $cache_root_m : $cache_root_m . '/' . $path;
                            if (!empty(glob($dir_d . '/index_*.html'))) $hit_d++;
                            if (!empty(glob($dir_m . '/index_*.html'))) $hit_m++;
                        }
                        $coverage = ['total' => $total, 'desktop' => $hit_d, 'mobile' => $hit_m];
                        set_transient('lwsop_coverage_cache_v2', $coverage, 60);
                    }

                    $cov_total   = (int) $coverage['total'] ?? 0;
                    $cov_desktop = (int) $coverage['desktop'];
                    $cov_mobile  = (int) $coverage['mobile'];
                    $cov_d_pct   = $cov_total > 0 ? round(($cov_desktop / $cov_total) * 100) : 0;
                    $cov_m_pct   = $cov_total > 0 ? round(($cov_mobile  / $cov_total) * 100) : 0;
                    $cov_complete= $cov_total > 0 && $cov_desktop >= $cov_total && $cov_mobile >= $cov_total;

                    // 4.4.7 — Calcul ETA du préchauffe (basé sur preload_amount=cron rate).
                    // Chaque tick du cron (1/min) traite preload_amount URLs, et chaque URL
                    // génère desktop+mobile (2 fichiers). Donc fichiers/min = preload_amount * 2.
                    $preload_rate    = max(1, (int) ($config_array['filebased_cache']['preload_amount'] ?? 3));
                    $missing_files   = max(0, ($cov_total - $cov_desktop)) + max(0, ($cov_total - $cov_mobile));
                    $eta_seconds     = ($missing_files > 0) ? (int) ceil(($missing_files / ($preload_rate * 2)) * 60) : 0;

                    ?>
                    <?php
                    // 4.4.2 — Unification : un SEUL indicateur de couverture par variante,
                    // intégré directement dans chaque ligne (Ordinateur / Mobile). Plus de
                    // section "Couverture" séparée qui dit la même chose autrement.
                    // Header explicatif unique en haut du bloc.
                    ?>
                    <!-- Header explicatif unique -->
                    <div class="lwsop_cache_stats_header_row">
                        <span class="lwsop_cache_stats_header_label">
                            <?php if ($preload_on) : ?>
                                <span class="lwsop_pulse_dot<?php echo $cov_complete ? ' done' : ''; ?>"></span>
                            <?php else : ?>
                                <span class="lwsop_pulse_dot off"></span>
                            <?php endif; ?>
                            <span id="lwsop_cache_stats_total"><?php echo esc_html($cov_total ?? '0'); ?></span>
                            <?php echo esc_html_e(' public URLs (Desktop + Mobile) covered', 'lws-optimize'); ?>
                        </span>
                    </div>

                    <!-- 4.4.3 — data-lwsop-row attribute pour ciblage AJAX refresh
                        sans casser le innerHTML global. -->
                    <!-- Ordinateur -->
                    <div class="lwsop_cache_row" data-lwsop-row="desktop">
                        <div class="lwsop_cache_row_header">
                            <img src="<?php echo esc_url(plugins_url("images/ordinateur.svg", __DIR__)) ?>" alt="" width="22" height="22">
                            <span><?php echo esc_html(__('Computer', 'lws-optimize')); ?></span>
                        </div>
                        <div class="lwsop_cache_progress">
                            <div class="lwsop_cache_row_stats_text">
                                <span><strong class="lwsop_cache_count_blue" data-lwsop-cov-count>
                                    <span data-lwsop-cov-hit><?php echo esc_html($cov_desktop); ?></span> / <span data-lwsop-cov-total><?php echo esc_html($cov_total); ?></span>
                                </strong> <?php echo esc_html(__('preheated', 'lws-optimize')); ?> (<span data-lwsop-cov-pct><?php echo esc_html($cov_d_pct); ?></span>%)</span>
                                <span class="lwsop_cache_row_file_info">
                                    <span data-lwsop-stat-amount><?php echo esc_html($cache_stats['desktop']['amount'] ?? 0); ?></span>
                                    <?php echo esc_html(__('total files', 'lws-optimize')); ?> ·
                                    <span data-lwsop-stat-size><?php echo esc_html(lwsOpSizeConvert($cache_stats['desktop']['size'] ?? 0)); ?></span>
                                </span>
                            </div>
                            <div class="lwsop_cache_bar<?php echo ($preload_on && !$cov_complete) ? ' preheating' : ''; ?>"><div data-lwsop-cov-bar style="width:<?php echo esc_attr($cov_d_pct); ?>%"></div></div>
                        </div>
                    </div>

                    <!-- Mobile -->
                    <div class="lwsop_cache_row" data-lwsop-row="mobile">
                        <div class="lwsop_cache_row_header">
                            <img src="<?php echo esc_url(plugins_url("images/mobile.svg", __DIR__)) ?>" alt="" width="22" height="22">
                            <span><?php echo esc_html(__('Mobile', 'lws-optimize')); ?></span>
                        </div>
                        <div class="lwsop_cache_progress">
                            <div class="lwsop_cache_row_stats_text">
                                <span><strong class="lwsop_cache_count_green" data-lwsop-cov-count>
                                    <span data-lwsop-cov-hit><?php echo esc_html($cov_mobile); ?></span> / <span data-lwsop-cov-total><?php echo esc_html($cov_total); ?></span>
                                </strong> <?php echo esc_html(__('preheated', 'lws-optimize')); ?> (<span data-lwsop-cov-pct><?php echo esc_html($cov_m_pct); ?></span>%)</span>
                                <span class="lwsop_cache_row_file_info">
                                    <span data-lwsop-stat-amount><?php echo esc_html($cache_stats['mobile']['amount'] ?? 0); ?></span>
                                    <?php echo esc_html(__('total files', 'lws-optimize')); ?> ·
                                    <span data-lwsop-stat-size><?php echo esc_html(lwsOpSizeConvert($cache_stats['mobile']['size'] ?? 0)); ?></span>
                                </span>
                            </div>
                            <div class="lwsop_cache_bar<?php echo ($preload_on && !$cov_complete) ? ' preheating' : ''; ?>"><div data-lwsop-cov-bar style="width:<?php echo esc_attr($cov_m_pct); ?>%"></div></div>
                        </div>
                    </div>

                    <!-- CSS & JS (assets, pas concernés par couverture URL) -->
                    <div class="lwsop_cache_row" data-lwsop-row="css">
                        <div class="lwsop_cache_row_header">
                            <img src="<?php echo esc_url(plugins_url("images/css.svg", __DIR__)) ?>" alt="" width="22" height="22">
                            <span>CSS</span>
                        </div>
                        <div class="lwsop_cache_progress alt">
                            <div class="lwsop_cache_asset_stats">
                                <strong class="lwsop_cache_count_purple"><span data-lwsop-stat-amount><?php echo esc_html($cache_stats['css']['amount'] ?? 0); ?></span></strong> <?php echo esc_html(__('minified files', 'lws-optimize')); ?>
                                <span class="lwsop_cache_asset_size" data-lwsop-stat-size><?php echo esc_html(lwsOpSizeConvert($cache_stats['css']['size'] ?? 0)); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="lwsop_cache_row" data-lwsop-row="js">
                        <div class="lwsop_cache_row_header">
                            <img src="<?php echo esc_url(plugins_url("images/js.svg", __DIR__)) ?>" alt="" width="22" height="22">
                            <span>JS</span>
                        </div>
                        <div class="lwsop_cache_progress alt">
                            <div class="lwsop_cache_asset_stats">
                                <strong class="lwsop_cache_count_orange"><span data-lwsop-stat-amount><?php echo esc_html($cache_stats['js']['amount'] ?? 0); ?></span></strong> <?php echo esc_html(__('minified files', 'lws-optimize')); ?>
                                <span class="lwsop_cache_asset_size" data-lwsop-stat-size><?php echo esc_html(lwsOpSizeConvert($cache_stats['js']['size'] ?? 0)); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 4.4.7 — Message d'état + countdown ETA live -->
                    <?php if ($preload_on) : ?>
                        <div id="lwsop_preheating_status" class="<?php echo $cov_complete ? 'complete' : 'pending'; ?>">
                            <span data-lwsop-status-text><?php echo esc_html($cov_complete ? __('All public pages (Computer + Mobile) are cached.', 'lws-optimize') : __('Preload runs in the background...', 'lws-optimize')); ?></span>
                            <?php if (!$cov_complete && $eta_seconds > 0) : ?>
                                <div class="lwsop_eta_countdown">
                                    <span class="lwsop_eta_label"><?php echo esc_html(__('Estimated time left', 'lws-optimize')); ?> :</span>
                                    <span data-lwsop-eta-display class="lwsop_eta_value">…</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <script>
                            // 4.4.7 — Countdown ETA live qui tick toutes les secondes.
                            // Source de vérité : data-lwsop-eta-seconds (réinjectée par
                            // l'AJAX refresh toutes les 30s pour recalibrer le rythme réel).
                            (function(){
                                var etaSeconds = <?php echo (int) $eta_seconds; ?>;
                                var endTs = etaSeconds > 0 ? (Date.now() + etaSeconds * 1000) : 0;
                                window.lwsopSetEta = function(newSeconds){
                                    etaSeconds = parseInt(newSeconds, 10) || 0;
                                    endTs = etaSeconds > 0 ? (Date.now() + etaSeconds * 1000) : 0;
                                    renderEta();
                                };
                                function formatHMS(totalSec){
                                    if (totalSec <= 0) return '<?php echo esc_js(__('Almost done…', 'lws-optimize')); ?>';
                                    var h = Math.floor(totalSec / 3600);
                                    var m = Math.floor((totalSec % 3600) / 60);
                                    var s = totalSec % 60;
                                    var pad = function(n){ return n < 10 ? '0' + n : '' + n; };
                                    if (h > 0) return h + 'h ' + pad(m) + 'min ' + pad(s) + 's';
                                    if (m > 0) return m + 'min ' + pad(s) + 's';
                                    return s + 's';
                                }
                                function renderEta(){
                                    var el = document.querySelector('[data-lwsop-eta-display]');
                                    if (!el) return;
                                    if (!endTs) { el.textContent = '—'; return; }
                                    var remaining = Math.max(0, Math.round((endTs - Date.now()) / 1000));
                                    el.textContent = formatHMS(remaining);
                                }
                                if (etaSeconds > 0) {
                                    renderEta();
                                    setInterval(renderEta, 1000);
                                }
                            })();
                        </script>
                    <?php endif; ?>
                    <div id="lwsop_hint_fetch_urls" class="lwop_alert lwop_alert_warning lwop_alert_no_margin"<?php echo ($preload_on && $cov_total === 0) ? '' : ' style="display:none"'; ?>>
                        <i class="dashicons dashicons-warning"></i>
                        <div>
                            <p class="lwsop_preload_hint_text"><?php esc_html_e('No URLs were indexed from the sitemap. Click below to fetch them and start caching.', 'lws-optimize'); ?></p>
                            <button type="button" class="lwsop_oneclickconfig_button_whiteblue" onclick="lwsop_main_preload_action(this, 'fetch')">
                                <?php esc_html_e('Fetch URLs to cache', 'lws-optimize'); ?>
                            </button>
                        </div>
                    </div>
                    <div id="lwsop_hint_preload_disabled" class="lwop_alert lwop_alert_warning lwop_alert_no_margin"<?php echo !$preload_on ? '' : ' style="display:none"'; ?>>
                        <i class="dashicons dashicons-warning"></i>
                        <div>
                            <p class="lwsop_preload_hint_text"><?php esc_html_e('Cache preloading is disabled. Activate it to warm up your cache automatically before visitor requests.', 'lws-optimize'); ?></p>
                            <button type="button" class="lwsop_oneclickconfig_button_whiteblue" onclick="lwsop_main_preload_action(this, 'activate')">
                                <?php esc_html_e('Activate preloading', 'lws-optimize'); ?>
                            </button>
                        </div>
                    </div>
                    <script>
                    function lwsop_main_preload_action(btn, type) {
                        var originalHTML = btn.innerHTML;
                        btn.disabled = true;
                        btn.innerHTML = '<img class="lwsop_btn_spinner" src="<?php echo esc_js(plugins_url('images/loading_blue.svg', __DIR__)); ?>" alt="" width="14" height="14"> '
                            + (type === 'activate'
                                ? '<?php echo esc_js(__('Activating...', 'lws-optimize')); ?>'
                                : '<?php echo esc_js(__('Fetching...', 'lws-optimize')); ?>');
                        jQuery.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            timeout: 60000,
                            data: {
                                action: 'lwsop_start_preload_fb',
                                state: 'true',
                                amount: <?php echo (int) $preload_rate; ?>,
                                _ajax_nonce: '<?php echo esc_js(wp_create_nonce('update_fb_preload')); ?>'
                            },
                            success: function(data) {
                                var r;
                                try { r = JSON.parse(data); } catch(e) { r = {code: 'NOT_JSON'}; }
                                if (r && r.code === 'SUCCESS') {
                                    callPopup('success', type === 'activate'
                                        ? '<?php echo esc_js(__('Preloading activated successfully.', 'lws-optimize')); ?>'
                                        : '<?php echo esc_js(__('URLs fetched successfully. Caching will start shortly.', 'lws-optimize')); ?>'
                                    );
                                    var alertBlock = btn.closest('.lwop_alert');
                                    if (alertBlock) alertBlock.style.display = 'none';
                                    if (typeof lwsopRefreshAllStats === 'function') lwsopRefreshAllStats();
                                } else {
                                    btn.disabled = false;
                                    btn.innerHTML = originalHTML;
                                    callPopup('error', '<?php echo esc_js(__('Action failed. Please retry or use advanced settings.', 'lws-optimize')); ?>');
                                }
                            },
                            error: function() {
                                btn.disabled = false;
                                btn.innerHTML = originalHTML;
                                callPopup('error', '<?php echo esc_js(__('Request failed. Please try again.', 'lws-optimize')); ?>');
                            }
                        });
                    }
                    </script>
                </div>
            </div>

            <div class="lwsop_oneclickconfig_block">
                <h3 class="lwsop_oneclickconfig_subtitle"><?php esc_html_e('Cache state', 'lws-optimize'); ?></h3>

                <div class="lwosp_oneclickconfig_cachestate_group">
                    <span class="lwosp_oneclickconfig_cachestate_line">
                        <?php if ($filecache_state == "true") : ?>
                            <img src="<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>" alt="Active" width="12px" height="12px">
                        <?php else : ?>
                            <img src="<?php echo esc_url(plugins_url('images/inactif.svg', __DIR__)) ?>" alt="Inactive" width="12px" height="12px">
                        <?php endif; ?>
                        <span class="lwsop_oneclickconfig_cachestate_text"><?php esc_html_e('Filecache', 'lws-optimize'); ?></span>
                        <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                        data-original-title="<?php echo esc_html_e('File caching helps improve website performance by storing static files locally, reducing server load and decreasing page load times for subsequent visits. It stores copies of static files like images, CSS, and JavaScript in a temporary storage.', 'lws-optimize'); ?>">
                    </span>

                    <span class="lwosp_oneclickconfig_cachestate_line">
                        <?php if ($memcache_state) : ?>
                            <img src="<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>" alt="Active" width="12px" height="12px">
                        <?php else : ?>
                            <img src="<?php echo esc_url(plugins_url('images/inactif.svg', __DIR__)) ?>" alt="Inactive" width="12px" height="12px">
                        <?php endif; ?>
                        <span class="lwsop_oneclickconfig_cachestate_text"><?php esc_html_e('Memcached', 'lws-optimize'); ?></span>
                        <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                        data-original-title="<?php echo esc_html_e('Memcached is a high-performance caching system that speeds up websites by storing frequently used database queries and API calls in memory', 'lws-optimize'); ?>">
                    </span>

                    <span class="lwosp_oneclickconfig_cachestate_line">
                        <?php if ($cache_state === "true") : ?>
                            <img src="<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>" alt="Active" width="12px" height="12px">
                        <?php else : ?>
                            <img src="<?php echo esc_url(plugins_url('images/inactif.svg', __DIR__)) ?>" alt="Inactive" width="12px" height="12px">
                        <?php endif; ?>
                        <span class="lwsop_oneclickconfig_cachestate_text">
                            <?php esc_html_e('Server cache', 'lws-optimize'); ?>
                            <span class="lwsop_oneclickconfig_cachestate_text_sub">
                                <?php if ($cache_state === null) : ?>
                                    (<?php esc_html_e('Not detected', 'lws-optimize'); ?>)
                                <?php else : ?>
                                    (<?php echo esc_html($clean_used_cache); ?>)
                                <?php endif; ?>
                        </span>
                        <img src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/infobulle.svg') ?>" alt="icône infobulle" width="16px" height="16px" data-toggle="tooltip" data-placement="top"
                        data-original-title="<?php echo esc_html_e('A server cache stores static copies of web pages to reduce server load and improve performance by serving those copies instead of fetching the page each request', 'lws-optimize'); ?>">
                    </span>
                </div>

                <div class="lwsop_oneclickconfig_cachestate_bottomtext">
                    <?php esc_html_e('To manage the cache, autopurge, preload and more, go to the ', 'lws-optimize'); ?>
                    <span class="lwsop_oneclickconfig_cachestate_link" onclick="window.location.href='?page=lws-op-config-advanced'"><?php esc_html_e('advanced mode', 'lws-optimize'); ?></span>.
                </div>

                <button type="button" class="lwsop_blue_button" onclick="lwsop_clear_all_cache(this)">
                    <span>
                        <img src="<?php echo esc_url(plugins_url('images/supprimer.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
                        <?php esc_html_e('Clear all cache', 'lws-optimize'); ?>
                    </span>
                </button>
            </div>
        </div>
    </div>
    <?php
    // 4.5.1 — Bloc dédié Memcached : mémoire, items, hit rate.
    // Affiché uniquement si Memcached est actif (sinon ça pollue l'UI).
    // Note : on évite le if/elseif PHP-tag style ici car la page entière est dans
    // une chaîne if/elseif globale (page=config vs page=config-advanced) qui se
    // ferait kidnapper par notre elseif. On utilise donc 2 if séparés.
    $memcached_stats = \Lws\Classes\Admin\LwsOptimizeDashboardWidget::memcached_stats();
    $memcached_inactive = !$memcached_stats['active'] && (($config_array['memcached']['state'] ?? 'false') !== 'true');
    if ($memcached_stats['active']) :
        $mem_pct = $memcached_stats['limit_maxbytes'] > 0
            ? round(($memcached_stats['bytes'] / $memcached_stats['limit_maxbytes']) * 100, 1)
            : 0;
    ?>
    <div class="lwsop_oneclickconfig_block lwsop_mt_20">
        <span class="lwsop_oneclickconfig_title_left_main"><?php echo esc_html(__('Memcached (object cache)', 'lws-optimize')); ?></span>
        <h3 class="lwsop_oneclickconfig_subtitle">
            <span><?php echo esc_html(__('In-memory cache state (WordPress queries)', 'lws-optimize')); ?></span>
        </h3>

        <div class="lwsop_usage_grid">
            <div class="lwsop_usage_card">
                <h5><?php echo esc_html(__('Memory used', 'lws-optimize')); ?></h5>
                <div class="lwsop_usage_hitrate">
                    <div class="big" style="color:<?php echo $mem_pct < 70 ? '#16a34a' : ($mem_pct < 90 ? '#f59e0b' : '#dc2626'); ?>"><?php echo esc_html($mem_pct); ?>%</div>
                    <div class="lbl"><?php echo esc_html(\Lws\Classes\Admin\LwsOptimizeDashboardWidget::format_bytes($memcached_stats['bytes'])); ?> / <?php echo esc_html(\Lws\Classes\Admin\LwsOptimizeDashboardWidget::format_bytes($memcached_stats['limit_maxbytes'])); ?></div>
                </div>
                <div class="lwsop_cache_bar lwsop_mt_8">
                    <div class="lwsop_memcached_bar" style="width:<?php echo esc_attr($mem_pct); ?>%"></div>
                </div>
            </div>

            <div class="lwsop_usage_card">
                <h5><?php echo esc_html(__('Items in memory', 'lws-optimize')); ?></h5>
                <div class="lwsop_usage_hitrate">
                    <div class="big lwsop_cache_count_indigo"><?php echo esc_html(number_format_i18n($memcached_stats['curr_items'])); ?></div>
                    <div class="lbl"><?php echo esc_html(__('cached objects', 'lws-optimize')); ?></div>
                </div>
            </div>

            <div class="lwsop_usage_card">
                <h5><?php echo esc_html(__('Memcached hit rate', 'lws-optimize')); ?></h5>
                <div class="lwsop_usage_hitrate">
                    <div class="big" style="color:<?php echo $memcached_stats['hit_rate'] >= 80 ? '#16a34a' : ($memcached_stats['hit_rate'] >= 50 ? '#f59e0b' : '#dc2626'); ?>"><?php echo esc_html($memcached_stats['hit_rate']); ?>%</div>
                    <div class="lbl"><?php echo esc_html(__('queries served from memory', 'lws-optimize')); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($memcached_inactive) : ?>
    <div class="lwsop_oneclickconfig_block lwsop_mt_20">
        <div class="lwsop_memcached_inactive_header">
            <span class="lwsop_oneclickconfig_title_left_main"><?php echo esc_html(__('Memcached (object cache)', 'lws-optimize')); ?></span>
            <span class="lwsop_memcached_status_badge"><?php esc_html_e('Inactive', 'lws-optimize'); ?></span>
        </div>

        <?php if ($memcache_available) : ?>
            <h3 class="lwsop_oneclickconfig_subtitle">
                <span><?php echo esc_html(__('Memcached is available on this server — activate it to speed up WordPress', 'lws-optimize')); ?></span>
            </h3>

            <ul class="lwsop_memcached_benefits_list">
                <li><?php echo esc_html(__('Stores WordPress database queries and API calls in RAM — drastically reduces page generation time', 'lws-optimize')); ?></li>
                <li><?php echo esc_html(__('Reduces database load: repeated queries are served from memory instead of hitting MySQL', 'lws-optimize')); ?></li>
                <li><?php echo esc_html(__('Speeds up the WordPress admin dashboard and authenticated pages that are not served by the file cache', 'lws-optimize')); ?></li>
            </ul>

            <button type="button" class="lwsop_blue_button lwsop_mt_10" id="lwsop_quick_activate_memcached" onclick="lwsop_quick_activate_memcached(this)">
                <span><?php esc_html_e('Activate Memcached', 'lws-optimize'); ?></span>
            </button>

            <script>
            function lwsop_quick_activate_memcached(btn) {
                btn.disabled = true;
                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 30000,
                    data: {
                        _ajax_nonce: "<?php echo esc_js(wp_create_nonce('nonce_lws_optimize_checkboxes_config')); ?>",
                        action: "lws_optimize_checkboxes_action",
                        data: {
                            type: "lws_optimize_memcached_check",
                            state: "true",
                            tab: "caching"
                        }
                    },
                    success: function(data) {
                        btn.disabled = false;
                        var returnData;
                        try { returnData = JSON.parse(data); } catch(e) { returnData = { code: 'NOT_JSON' }; }

                        if (returnData.code === 'SUCCESS' && returnData.data === 'true') {
                            callPopup('success', "<?php echo esc_js(__('Memcached activated successfully.', 'lws-optimize')); ?>");
                            setTimeout(function() { window.location.reload(); }, 800);
                        } else if (returnData.errors && returnData.errors.length) {
                            var detail = returnData.lws_memcached_detail || '';
                            callPopup('error', "<?php echo esc_js(__('Memcached could not be activated.', 'lws-optimize')); ?>" + (detail ? '<br><small>' + detail + '</small>' : ''));
                        } else {
                            callPopup('error', "<?php echo esc_js(__('Unexpected error. Please try again or use the advanced mode.', 'lws-optimize')); ?>");
                        }
                    },
                    error: function() {
                        btn.disabled = false;
                        callPopup('error', "<?php echo esc_js(__('Request failed. Please try again.', 'lws-optimize')); ?>");
                    }
                });
            }
            </script>

        <?php else : ?>
            <h3 class="lwsop_oneclickconfig_subtitle">
                <span><?php echo esc_html(__('Memcached is not available on this server', 'lws-optimize')); ?></span>
            </h3>
            <p class="lwsop_memcached_unavailable_reason">
                <?php echo esc_html($memcache_unavailable_reason); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="tab-pane main-tab-pane lws_op_configpage lwsop_imagepro_section">
        <?php require_once 'image_optimize_pro_small.php'; ?>
    </div>

    <?php elseif (sanitize_text_field($_GET['page']) === 'lws-op-config-advanced') : ?>
        <?php require_once 'tabs.php'; ?>
    <?php endif; ?>

</div>

<?php if (sanitize_text_field($_GET['page']) === 'lws-op-config-advanced') : ?>
    <div class="lwsoptimize_validate_changes">
        <div class="lwsoptimize_validate_changes_inner">
            <button class="lws_op_return_to_dashboard" onclick="window.location.href='?page=lws-op-config'">
                <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="" width="12px" height="12px">
                <?php esc_html_e('Back to simple mode', 'lws-optimize'); ?>
            </button>
            <button id="lws_optimize_validate_changes" class="lwsop_oneclickconfig_button" disabled onclick="lws_op_update_configuration(this)">
                <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="" width="20px" height="20px">
                <?php esc_html_e('Save new configuration', 'lws-optimize'); ?>
                <div class="lws_op_config_button_amounts" id="lws_optimize_amount_configuration_elements">0</div>
            </button>
        </div>
    </div>
<?php endif; ?>

<div class="lws_made_with_heart"><?php esc_html_e('Created with ❤️ by ', 'lws-optimize'); ?><a href="http://lws.fr" target="_blank" rel="noopener">LWS.fr</a></div>

<div class="modal fade" id="lwsop_preconfigurate_plugin" tabindex='-1' role='dialog' aria-hidden='true'>
    <div class="modal-dialog">
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

<div id="lwsop_popup_alerting"></div>

<script>
    // Execute the function callback after ms milliseconds unless delay() is called again
    function delay(callback, ms) {
        var timer = 0;
        return function() {
            var context = this,
                args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                callback.apply(context, args);
            }, ms || 0);
        };
    }

    function callPopup(type, content) {
        // Get the element containing all popups
        let alerting = document.getElementById('lwsop_popup_alerting');
        if (alerting == null) {
            console.log(JSON.stringify({
                'code': "POPUP_FAIL",
                'data': "Failed to find alerting"
            }));
            return -1;
        }

        if (content == null) {
            console.log(JSON.stringify({
                'code': "POPUP_FAIL",
                'data': "Failed to find content"
            }));
            return -1;
        }

        if (type == null) {
            console.log(JSON.stringify({
                'code': "POPUP_FAIL",
                'data': "Failed to find type"
            }));
            return -1;
        }

        // No more than 4 popups at a time. Remove the oldest one
        if (alerting.children.length > 4) {
            let amount_popups = alerting.children;
            let last = amount_popups.item(amount_popups.length - 1);
            if (last != null) {
                jQuery(last).animate({
                    'left': '150%'
                }, 500, function() {
                    last.remove();
                });
            }
        }

        let number = alerting.children.length ?? 5;

        alerting.insertAdjacentHTML('afterbegin', `<div class="lwsop_information_popup" style="left: 150%;" id="lwsop_information_popup_` + number + `"></div>`);
        let popup = document.getElementById('lwsop_information_popup_' + number);

        if (popup == null) {
            console.log(JSON.stringify({
                'code': "POPUP_NOT_CREATED",
                'data': "Failed to create the popup"
            }));
            return -1;
        }

        animation = ``;
        switch (type) {
            case 'success':
                animation = `<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" /><path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" /></svg>`;
                break;
            case 'error':
                animation = `
                <svg class="crossmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"> <circle class="crossmark__circle" cx="26" cy="26" r="25" fill="none" stroke="red" stroke-width="2"></circle> <path class="crossmark__cross" fill="none" stroke="red" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="36" stroke-dashoffset="36" d="M16 16 36 36 M36 16 16 36"> <animate attributeName="stroke-dashoffset" from="36" to="0" dur="0.5s" fill="freeze" /> </path></svg>`
                break;
            case 'warning':
                animation = `<svg class="exclamation" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"> <circle class="exclamation__circle" cx="26" cy="26" r="25" fill="none" stroke="#FFD700" stroke-width="2"></circle> <text class="exclamation__mark" x="26" y="30" font-size="26" font-family="Arial" text-anchor="middle" fill="#FFD700" dominant-baseline="middle">!</text> <style> .exclamation__mark { animation: blink 1s ease-in-out 3; } @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } } </style> </svg>`;
                break;
            default:
                animation = `<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" /><path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" /></svg>`;
                break;
        }

        popup.insertAdjacentHTML('beforeend', `
            <div class="lwsop_information_popup_animation">` + animation + `</div>
            <div class="lwsop_information_popup_content">` + content + `</div>
            <div id="lwsop_close_popup_` + number + `" class="lwsop_information_popup_close"><img src="<?php echo esc_url(plugins_url('images/fermer.svg', __DIR__)) ?>" alt="close button" width="10px" height="10px">
        `)

        jQuery(popup).animate({
            'left': '0%'
        }, 500);

        popup.classList.add('popup_' + type);

        let popup_button = document.getElementById('lwsop_close_popup_' + number);
        if (popup_button != null) {
            popup_button.addEventListener('click', function() {
                this.parentNode.remove();
            })
        }

        popup.addEventListener('mouseover', delay(function() {
            if (popup.matches(':hover')) {
                return 0;
            }
            jQuery(this).animate({
                'left': '150%'
            }, 500, function() {
                this.remove();
            });
        }, 5000));

        popup.dispatchEvent(new Event('mouseover'));
    }

    function lwsoptimize_copy_clipboard(input) {
        let text = input.getAttribute('text');
        let element = input.parentNode;
        navigator.clipboard.writeText(text);
        jQuery(element).append("<div class='tip' id='copied_tip'>" +
            "<?php esc_html_e('Copied!', 'lws-optimize'); ?>" +
            "</div>");

        setTimeout(function() {
            jQuery('#copied_tip').remove();
        }, 500);
    }

    // Toggle dropdown when hovering or clicking the button
    document.querySelectorAll('.lwsop_dropdown_button').forEach(button => {
        // button.addEventListener('mouseenter', function() {
        //     this.querySelector('.lwsop_dropdown_content').classList.add('active');
        //     this.querySelector('.lwsop_dropdown_arrow svg').style.transform = 'rotate(180deg)';
        // });

        // Handle mouseleave
        button.addEventListener('mouseleave', function(e) {
            // Check if mouse is moving to the dropdown content
            const relatedTarget = e.relatedTarget;
            if (!relatedTarget || !relatedTarget.closest('.lwsop_dropdown_content')) {
                this.querySelector('.lwsop_dropdown_content').classList.remove('active');
                this.querySelector('.lwsop_dropdown_arrow svg').style.transform = 'rotate(0)';
            }
        });
    });

    // Keep dropdown open when hovering the dropdown content
    document.querySelectorAll('.lwsop_dropdown_content').forEach(dropdown => {
        dropdown.addEventListener('mouseenter', function() {
            this.classList.add('active');
            this.parentNode.querySelector('.lwsop_dropdown_arrow svg').style.transform = 'rotate(180deg)';
        });

        // Close dropdown when mouse leaves the dropdown content
        dropdown.addEventListener('mouseleave', function() {
            this.classList.remove('active');
            this.parentNode.querySelector('.lwsop_dropdown_arrow svg').style.transform = 'rotate(0)';
        });

        // Handle clicks on dropdown options
        dropdown.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const config = this.getAttribute('data-config');
                const dropdownButton = this.closest('.lwsop_dropdown_button');
                const dropdownText = dropdownButton.querySelector('.lwsop_dropdown_text');

                dropdownText.textContent = this.textContent;
                dropdown.classList.remove('active');
                dropdownButton.querySelector('.lwsop_dropdown_arrow svg').style.transform = 'rotate(0)';

                // Send AJAX request to temporarily deactivate the plugin
                dropdownButton.classList.add('loading');
                if (config == 0) {
                    dropdownText.textContent = '<?php esc_html_e("Activating...", "lws-optimize"); ?>';
                } else {
                    dropdownText.textContent = '<?php esc_html_e("Deactivating...", "lws-optimize"); ?>';
                }

                let ajaxRequest = jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    timeout: 120000,
                    context: document.body,
                    data: {
                        _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_deactivate_temporarily_nonce")); ?>",
                        action: "lwsop_deactivate_temporarily",
                        duration: config,
                    },
                    success: function(data) {
                        document.body.style.pointerEvents = "all";
                        dropdownButton.classList.remove('loading');

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

                        dropdownText.textContent = '<?php esc_html_e("Deactivate for: ", "lws-optimize"); ?>';

                        switch (returnData['code']) {
                            case 'SUCCESS':
                                callPopup('success', "<?php esc_html_e('Plugin state successfully changed', 'lws-optimize'); ?>");

                                if (config == 0) {
                                    dropdownText.textContent = '<?php esc_html_e("Activated", "lws-optimize"); ?>';
                                } else {
                                    // Update button text to show deactivation duration
                                    dropdownText.textContent = '<?php esc_html_e("Deactivated for: ", "lws-optimize"); ?>' + link.textContent;
                                }

                                // Reload page after a short delay
                                setTimeout(function() {
                                    window.location.reload();
                                }, 500);
                                break;
                            case 'NOT_JSON':
                                callPopup('error', "<?php esc_html_e('Bad server response. Could not deactivate plugin.', 'lws-optimize'); ?>");
                                break;
                            case 'NO_PARAM':
                                callPopup('error', "<?php esc_html_e('No data sent to the server. Please try again.', 'lws-optimize'); ?>");
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
            });
        });
    });

    // Also support click functionality on the dropdown arrow
    document.querySelectorAll('.lwsop_dropdown_button').forEach(arrow => {
        arrow.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.parentNode.querySelector('.lwsop_dropdown_content');
            dropdown.classList.toggle('active');
            this.querySelector('svg').style.transform = dropdown.classList.contains('active')
                ? 'rotate(180deg)'
                : 'rotate(0)';
        });
    });

    // Close dropdown when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.lwsop_dropdown_button')) {
            document.querySelectorAll('.lwsop_dropdown_content').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
            document.querySelectorAll('.lwsop_dropdown_arrow svg').forEach(svg => {
                svg.style.transform = 'rotate(0)';
            });
        }
    });

    <?php if (!$is_deactivated) : ?>
        // 4.4.3 — Helpers refresh ciblé : met à jour les data-attrs des rows
        // sans toucher au innerHTML global (préserve les progress bars de coverage).
        function lwsopUpdateStatRow(type, stats) {
            if (!stats || !stats[type]) return;
            var row = document.querySelector('[data-lwsop-row="' + type + '"]');
            if (!row) return;
            var amount = row.querySelector('[data-lwsop-stat-amount]');
            var size   = row.querySelector('[data-lwsop-stat-size]');
            if (amount) amount.textContent = stats[type].amount != null ? stats[type].amount : 0;
            if (size)   size.textContent   = stats[type].size   != null ? stats[type].size   : '0K';
        }
        // Endpoint dédié coverage (réutilise lwsop_get_coverage côté backend, voir LwsOptimizeManageAdmin)
        function lwsopRefreshCoverage() {
            jQuery.ajax({
                url: ajaxurl, type: 'POST', timeout: 30000,
                data: {
                    action: 'lwsop_get_coverage',
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lwsop_get_coverage_nonce')); ?>'
                },
                success: function(data){
                    try {
                        var r = (typeof data === 'string') ? JSON.parse(data) : data;
                        if (r && r.code === 'SUCCESS' && r.data) {
                            var anyIncomplete = false;
                            ['desktop','mobile'].forEach(function(t){
                                var row = document.querySelector('[data-lwsop-row="' + t + '"]');
                                if (!row || !r.data[t]) return;
                                var hit = r.data[t].hit, tot = r.data.total, pct = tot > 0 ? Math.round((hit/tot)*100) : 0;
                                if (pct < 100) anyIncomplete = true;
                                var hitEl = row.querySelector('[data-lwsop-cov-hit]');
                                var totEl = row.querySelector('[data-lwsop-cov-total]');
                                var pctEl = row.querySelector('[data-lwsop-cov-pct]');
                                var bar   = row.querySelector('[data-lwsop-cov-bar]');
                                if (hitEl) hitEl.textContent = hit;
                                if (totEl) totEl.textContent = tot;
                                if (pctEl) pctEl.textContent = pct;
                                if (bar) {
                                    bar.style.width = pct + '%';
                                    // 4.4.4 — Toggle classe .preheating sur le parent .lwsop_cache_bar
                                    var parent = bar.parentNode;
                                    if (parent) parent.classList.toggle('preheating', pct < 100 && <?php echo $preload_on ? 'true' : 'false'; ?>);
                                }
                            });

                            let stats_total = document.getElementById('lwsop_cache_stats_total');
                            if (stats_total) {
                                stats_total.innerHTML = r.data.total ?? '0';
                            }

                            // 4.4.4 — Met à jour le dot pulse + message d'état du header
                            var dot = document.querySelector('.lwsop_pulse_dot');
                            if (dot) {
                                dot.classList.toggle('done', !anyIncomplete);
                            }
                            // 4.4.7 — Recalibre le countdown ETA avec la valeur fraîche
                            // calculée serveur-side (basée sur le rythme réel observé).
                            if (typeof window.lwsopSetEta === 'function' && typeof r.data.eta_seconds !== 'undefined') {
                                window.lwsopSetEta(r.data.eta_seconds);
                            }
                            // Sync hint alerts with refreshed preload + coverage state
                            var hintFetch = document.getElementById('lwsop_hint_fetch_urls');
                            if (hintFetch) hintFetch.style.display = (r.data.preload_on && r.data.total === 0) ? '' : 'none';
                            var hintPreload = document.getElementById('lwsop_hint_preload_disabled');
                            if (hintPreload) hintPreload.style.display = r.data.preload_on ? 'none' : '';
                            // Si tout est complet, on stoppe l'auto-refresh
                            if (!anyIncomplete && lwsopAutoRefreshTimer) {
                                clearInterval(lwsopAutoRefreshTimer);
                                lwsopAutoRefreshTimer = null;
                            }
                        }
                    } catch(e) { console.log('coverage refresh fail', e); }
                }
            });
        }
        // 4.4.4 — Auto-refresh toutes les 30s tant que le préchauffe est en cours.
        // S'arrête tout seul quand coverage atteint 100%. Démarre à load si préchauffe ON et coverage < 100%.
        var lwsopAutoRefreshTimer = null;
        function lwsopStartAutoRefresh() {
            <?php if ($preload_on && !$cov_complete) : ?>
            if (lwsopAutoRefreshTimer) clearInterval(lwsopAutoRefreshTimer);
            lwsopAutoRefreshTimer = setInterval(function(){
                lwsopRefreshAllStats();
            }, 30000);
            <?php endif; ?>
        }
        if (document.readyState !== 'loading') lwsopStartAutoRefresh();
        else document.addEventListener('DOMContentLoaded', lwsopStartAutoRefresh);
        // Compose : refresh stats classiques + coverage en un appel
        function lwsopRefreshAllStats() {
            // Réutilise l'AJAX existant pour les stats fichiers, puis appelle coverage
            jQuery.ajax({
                url: ajaxurl, type: 'POST', timeout: 30000,
                data: {
                    action: 'lwsop_regenerate_cache_general',
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lws_regenerate_nonce_cache_fb')); ?>'
                },
                success: function(data){
                    try {
                        var r = (typeof data === 'string') ? JSON.parse(data) : data;
                        if (r && r.code === 'SUCCESS' && r.data) {
                            ['desktop','mobile','css','js'].forEach(function(t){ lwsopUpdateStatRow(t, r.data); });
                        }
                    } catch(e) {}
                    lwsopRefreshCoverage();
                }
            });
        }

        function lwsop_refresh_global_stats(button) {
            let originalText = '';
            if (button) {
                button.disabled = true;
                originalText = button.innerHTML;
                button.innerHTML = `
                    <span name="loading" style="padding-left:5px">
                        <img style="vertical-align:sub; margin-right:5px" src="<?php echo esc_url(dirname(plugin_dir_url(__FILE__)) . '/images/loading_blue.svg') ?>" alt="" width="18px" height="18px">
                    </span>
                `;
            }

            let cache_stats = document.getElementById('cache_stats_element');
            let overlay = document.getElementById('cache_stats_loading_overlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    action: "lwsop_regenerate_cache_general",
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lws_regenerate_nonce_cache_fb')); ?>'
                },
                success: function(data) {
                    if (overlay) {
                        overlay.style.display = 'none';
                    }

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
                            let stats = returnData['data'];
                            // 4.4.3 — Refresh ciblé sur les data-attrs au lieu de remplacer
                            // tout le innerHTML (qui cassait les progress bars de coverage).
                            lwsopUpdateStatRow('desktop', stats);
                            lwsopUpdateStatRow('mobile',  stats);
                            lwsopUpdateStatRow('css',     stats);
                            lwsopUpdateStatRow('js',      stats);
                            // Refresh aussi la couverture (URLs sitemap réellement en cache)
                            lwsopRefreshCoverage();
                            // 4.5.0 — Refresh du bloc UsageStats (hits/misses)
                            if (returnData['usage'] && typeof returnData['usage'] === 'object') {
                                lwsopUpdateUsageStats(returnData['usage']);
                            }
                            callPopup('success', "<?php esc_html_e("File-based cache statistics have been synchronized", "lws-optimize"); ?>");
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e("Unknown data returned.", "lws-optimize"); ?>");
                            break;
                    }
                },
                error: function(error) {
                    if (overlay) {
                        overlay.style.display = 'none';
                    }

                    button.disabled = false;
                    button.innerHTML = originalText;
                    callPopup('error', "<?php esc_html_e("Unknown error.", "lws-optimize"); ?>");
                    console.log(error);
                }
            });

        }

        function lwsopUpdateUsageStats(usage) {
            let noData = document.getElementById('lwsop_usage_no_data');
            let dataSection = document.getElementById('lwsop_usage_data_section');
            if (!noData || !dataSection) return;

            if (!usage.has_data) {
                noData.style.display = '';
                dataSection.style.display = 'none';
                return;
            }

            noData.style.display = 'none';
            dataSection.style.display = '';

            let rateColor = function(rate) {
                return rate >= 80 ? '#16a34a' : (rate >= 50 ? '#f59e0b' : '#dc2626');
            };

            let periods = {
                '24h': usage['totals_24h'],
                '7d':  usage['totals_7d'],
                '30d': usage['totals_30d'],
            };

            for (let [key, t] of Object.entries(periods)) {
                let rateEl = document.getElementById('lwsop_ustat_' + key + '_rate');
                if (rateEl) { rateEl.textContent = t.hit_rate + '%'; rateEl.style.color = rateColor(t.hit_rate); }
                let hitsEl = document.getElementById('lwsop_ustat_' + key + '_hits');
                if (hitsEl) hitsEl.textContent = t.hits;
                let missesEl = document.getElementById('lwsop_ustat_' + key + '_misses');
                if (missesEl) missesEl.textContent = t.misses;
                let bytesEl = document.getElementById('lwsop_ustat_' + key + '_bytes');
                if (bytesEl) bytesEl.textContent = t.bytes;
            }

            let sparklineEl = document.getElementById('lwsop_ustat_sparkline');
            if (sparklineEl && usage.sparkline) {
                sparklineEl.innerHTML = usage.sparkline;
            }
        }

        function lwsop_clear_all_cache(button) {
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
                    action: "lws_op_clear_all_caches",
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lws_op_clear_all_caches_nonce')); ?>'
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
                            callPopup('success', "<?php esc_html_e("All caches have been deleted", "lws-optimize"); ?>");
                            // 4.4.3 — refresh auto des stats + coverage après purge
                            lwsopRefreshAllStats();
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e("Failed to empty cache", "lws-optimize"); ?>");
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

        function lwsop_change_settings_group(button) {
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

            let radios = document.querySelectorAll("input[name='lwsop_oneclickconfig_radio[]']");
            let value = '';

            radios.forEach(function(radio) {
                if (radio.checked) {
                    value = radio.value;
                }
            })

            let ajaxRequest = jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                timeout: 120000,
                context: document.body,
                data: {
                    value: value,
                    _ajax_nonce: "<?php echo esc_html(wp_create_nonce("lwsop_change_optimize_configuration_nonce")); ?>",
                    action: "lwsop_change_optimize_configuration",
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
                            callPopup('success', "<?php esc_html_e('New configuration applied.', 'lws-optimize'); ?>");
                            location.reload();
                            break;
                        default:
                            callPopup('error', "<?php esc_html_e('Failed to configurate the plugin.', 'lws-optimize'); ?>");
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

        let radio_config = document.querySelector("input[value='<?php echo $config_array['autosetup_type'] ?? ''; ?>']");
        if (radio_config) {
            radio_config.checked = true;
        }

        jQuery(document).ready(function() {
            jQuery('[data-toggle="tooltip"]').tooltip();
        });
    <?php endif; ?>
</script>