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
    'minify_html' => [
        'name' => __('HTML Minification', 'lws-optimize'),
        'description' => __('Reduce the size of HTML files by removing superfluous spaces, comments and characters', 'lws-optimize'),
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

// Get the state of the Memcached option, checking for the optimize option and the module state
$memcache_state = ($memcache_state && isset($config_array["memcached"]['state']) && $config_array["memcached"]['state'] == "true") ? true : false;

// Check server cache state using environment variables
$cache_state = "false";
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
    'lws-optimize' => array('LWS Optimize', __('This plugin lets you boost your website\'s <strong>loading times</strong> thanks to our tools: caching, media optimisation, files minification and concatenation...', 'lws-optimize'), true),
    'lws-cleaner' => array('LWS Cleaner', __('This plugin lets you <strong>clean your WordPress website</strong> in a few clics to gain speed: posts, comments, terms, users, settings, plugins, medias, files.', 'lws-optimize'), true),
    //'lws-sms' => array('LWS SMS', __('This plugin, designed specifically for WooCommerce, lets you <strong>send SMS automatically to your customers</strong>. You will need an account at LWS and enough credits to send SMS. Create personnalized templates, manage your SMS and sender IDs and more!', 'lws-optimize'), false),
    'lws-affiliation' => array('LWS Affiliation', __('With this plugin, you can add banners and widgets on your website and use those with your <strong>affiliate account LWS</strong>. Earn money and follow the evolution of your gains on your website.', 'lws-optimize'), false),
    //'lwscache' => array('LWSCache', __('Based on the Varnich cache technology and NGINX, LWSCache let you <strong>speed up the loading of your pages</strong>. This plugin helps you automatically manage your LWSCache when editing pages, posts... and purging all your cache. Works only if your server use this cache.', 'lws-optimize'), false),
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

    <div class="lwsop_title_banner">
        <div class="lwsop_top_banner" <?php if ($cache_state !== null) : ?>style="max-width: none;" <?php endif ?>>
            <img src="<?php echo esc_url(plugins_url('images/plugin_lws_optimize_logo.svg', __DIR__)) ?>" alt="LWS Optimize Logo" width="80px" height="80px">
            <div class="lwsop_top_banner_text">
                <div class="lwsop_top_title_block">
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

        <?php if ($cache_state === null) : ?>
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

    <?php if (sanitize_text_field($_GET['page']) === 'lws-op-config') : ?>
    <div class="lwsop_oneclickconfig_main">
        <div class="lwsop_oneclickconfig_block">
            <h2 class="lwsop_oneclickconfig_title">
                <div class="lwsop_oneclickconfig_title_left">
                    <span class="lwsop_oneclickconfig_title_left_main"><?php esc_html_e('Speed up your website in 1 clic', 'lws-optimize'); ?></span>
                    <span class="lwsop_oneclickconfig_title_left_sub"><?php esc_html_e('Choose an optimization level with one click and/or access advanced settings to customize.', 'lws-optimize'); ?></span>
                </div>
                <button class="lwsop_oneclickconfig_button" id="lwsop_oneclickconfig" onclick="lwsop_change_settings_group(this)">
                    <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
                    <?php esc_html_e('Save', 'lws-optimize'); ?>
                </button>
            </h2>

            <div class="lwsop_oneclickconfig_table">
                <div class="lwsop_oneclickconfig_table_column">
                    <div class="lwsop_oneclickconfig_floating_bubble">
                        <?php esc_html_e('Personnalized in advanced mode', 'lws-optimize'); ?>
                    </div>
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
        </div>

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
                <?php foreach ($caches as $type => $cache) : ?>
                    <div class="lwsop_oneclickconfig_cachestats_element">
                        <img src="<?php echo esc_url(plugins_url("images/{$cache['image_file']}", __DIR__)) ?>" alt="<?php echo esc_attr($cache['image_alt']); ?>" width="25px" height="25px">
                        <span><?php echo esc_html($cache["alt_title"]); ?> : </span>
                        <span><?php echo "<b>" . esc_html($cache['size']) . "</b> / " . esc_html($cache['amount']); ?> <?php esc_html_e('elements', 'lws-optimize'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 class="lwsop_oneclickconfig_subtitle"><?php esc_html_e('Cache state', 'lws-optimize'); ?></h3>

            <div class="lwosp_oneclickconfig_cachestate_group">
                <span class="lwosp_oneclickconfig_cachestate_line">
                    <?php if ($filecache_state == "true") : ?>
                        <img src="<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>" alt="Active" width="12px" height="12px">
                    <?php else : ?>
                        <img src="<?php echo esc_url(plugins_url('images/inactif.svg', __DIR__)) ?>" alt="Inactive" width="12px" height="12px">
                    <?php endif; ?>
                    <span class="lwsop_oneclickconfig_cachestate_text"><?php esc_html_e('Filecache', 'lws-optimize'); ?></span>
                </span>

                <span class="lwosp_oneclickconfig_cachestate_line">
                    <?php if ($memcache_state) : ?>
                        <img src="<?php echo esc_url(plugins_url('images/actif.svg', __DIR__)) ?>" alt="Active" width="12px" height="12px">
                    <?php else : ?>
                        <img src="<?php echo esc_url(plugins_url('images/inactif.svg', __DIR__)) ?>" alt="Inactive" width="12px" height="12px">
                    <?php endif; ?>
                    <span class="lwsop_oneclickconfig_cachestate_text"><?php esc_html_e('Memcached', 'lws-optimize'); ?></span>
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

    <button type="button" class="lwsop_darkblue_button" onclick="window.location.href='?page=lws-op-config-advanced'">
        <span>
            <img src="<?php echo esc_url(plugins_url('images/avance.svg', __DIR__)) ?>" alt="Logo poubelle" width="20px">
            <?php esc_html_e('Go to advanced mode', 'lws-optimize'); ?>
        </span>
    </button>

    <div class="tab-pane main-tab-pane lws_op_configpage" style="margin-top: 30px; border-radius: 10px;">
        <?php require_once 'image_optimize_pro_small.php'; ?>
    </div>

    <?php elseif (sanitize_text_field($_GET['page']) === 'lws-op-config-advanced') : ?>
        <?php require_once 'tabs.php'; ?>
    <?php endif; ?>

</div>

<?php if (sanitize_text_field($_GET['page']) === 'lws-op-config-advanced') : ?>
    <div class="lwsoptimize_validate_changes">
        <button class="lws_op_return_to_dashboard" onclick="window.location.href='?page=lws-op-config'">
        <img src="<?php echo esc_url(plugins_url('images/fleche_precedent.svg', __DIR__)) ?>" alt="Logo Retour" width="12px" height="12px">
        <?php esc_html_e('Back to simple mode', 'lws-optimize'); ?>
        <button id="lws_optimize_validate_changes" class="lwsop_oneclickconfig_button" disabled onclick="lws_op_update_configuration(this)">
        <img src="<?php echo esc_url(plugins_url('images/enregistrer.svg', __DIR__)) ?>" alt="Logo Disquette" width="20px" height="20px">
        <?php esc_html_e('Save new configuration', 'lws-optimize'); ?>
    </div>
<?php endif; ?>

<div class="lws_made_with_heart"><?php esc_html_e('Created with ❤️ by ', 'lws-optimize'); ?><a href="http://lws.fr" target="_blank" rel="noopener">LWS.fr</a></div>

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

<div id="lwsop_popup_alerting"></div>

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
        navigator.clipboard.writeText(input.innerText.trim());
        setTimeout(function() {
            jQuery('#copied_tip').remove();
        }, 500);
        jQuery(input).append("<div class='tip' id='copied_tip'>" +
            "<?php esc_html_e('Copied!', 'lws-optimize'); ?>" +
            "</div>");
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

                        if (cache_stats) {
                            let cacheStatsHtml = `
                                    <div class="lwsop_loading_overlay" id="cache_stats_loading_overlay" style="display: none;">
                                        <div class="lwsop_loading_spinner"></div>
                                    </div>
                            `;

                            for (let type in stats) {
                                cacheStatsHtml += `
                                    <div class="lwsop_oneclickconfig_cachestats_element">
                                        <img src="${stats[type].image_file}" alt="${stats[type].image_alt}" width="25px" height="25px">
                                        <span>${stats[type].alt_title} : </span>
                                        <span><b>${stats[type].size}</b> / ${stats[type].amount} elements</span>
                                    </div>
                                `;
                            }

                            cache_stats.innerHTML = cacheStatsHtml;
                        }
                        callPopup('success', "<?php esc_html_e("File-based cache statistics have been synchronized", "lws-optimize"); ?>");
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Unknown data returned."); ?>");
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
                action: "lws_clear_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
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
                        break;
                    default:
                        callPopup('error', "<?php esc_html_e("Failed to empty cache"); ?>");
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
</script>