<div class="lwsop_bluebanner_alt">
    <h2 class="lwsop_bluebanner_title">
        <?php esc_html_e('Cron Logs', 'lws-optimize'); ?>
    </h2>
    <div class="lwsop_bluebanner_subtitle">
        <?php esc_html_e('This section lets you easily view the latest logs from this plugin cron jobs. Those includes crons related to image optimization and cache preloading.', 'lws-optimize'); ?>
        <br>
        <?php esc_html_e('Log files have a maximum size of 5MB. Once this size is reached, a new file will be created. Older logs can be found in /uploads/lwsoptimize/.', 'lws-optimize'); ?>
    </div>
</div>

<?php
$dir = wp_upload_dir();
$file = $dir['basedir'] . '/lwsoptimize/debug.log';
if (empty($file)) {
    $content = __('No log file found.', 'lws-optimize');
} else {
    $content = esc_html(implode("\n", array_reverse(file($file, FILE_IGNORE_NEW_LINES))));
}
?>

<div class="lwsop_contentblock">
    <pre style="max-height: 450px;"><?php echo $content; ?></pre>
</div>