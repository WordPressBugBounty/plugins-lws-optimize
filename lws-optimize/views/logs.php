<?php if (!defined('ABSPATH')) exit; ?>
<div class="lwsop_bluebanner_logs">
    <div>
        <h2 class="lwsop_bluebanner_title">
            <?php esc_html_e('Cron Logs', 'lws-optimize'); ?>
        </h2>
        <div class="lwsop_bluebanner_subtitle">
            <?php esc_html_e('This section lets you easily view the latest logs from this plugin cron jobs. Those includes crons related to image optimization and cache preloading.', 'lws-optimize'); ?>
            <br>
            <?php esc_html_e('Log files have a maximum size of 5MB. Once this size is reached, a new file will be created. Older logs can be found in /uploads/lwsoptimize/.', 'lws-optimize'); ?>
        </div>
    </div>
    <button type="button" class="lws_optimize_image_conversion_refresh" id="lws_op_regenerate_logs" name="lws_op_regenerate_logs">
        <img src="<?php echo esc_url(plugins_url('images/rafraichir.svg', __DIR__)) ?>" alt="Logo MàJ" width="12px">
        <span><?php esc_html_e('Refresh', 'lws-optimize'); ?></span>
    </button>
</div>

<?php
$dir = wp_upload_dir();
$file = $dir['basedir'] . '/lwsoptimize/debug.log';
if (!file_exists($file)) {
    $content = __('No log file found.', 'lws-optimize');
} else {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $content = $lines === false ? __('No log file found.', 'lws-optimize') : implode("\n", array_reverse($lines));
}
?>

<div class="lwsop_contentblock">
    <pre id="log_dir" style="max-height: 450px;"><?php echo esc_html($content); ?></pre>
</div>

<script>
    let regen_logs = document.getElementById('lws_op_regenerate_logs');
    if (regen_logs) {
        regen_logs.addEventListener('click', function(){
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
                    action: "lwsop_regenerate_logs",
                    _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('lws_regenerate_nonce_logs')); ?>'
                },
                success: function(returnData) {
                    button.disabled = false;
                    button.innerHTML = old_text;

                    if (!isValidResponse(returnData)) {
                        console.error('Invalid AJAX response', returnData);
                        return;
                    }

                    switch (returnData['code']) {
                        case 'SUCCESS':
                            if (document.getElementById('log_dir')) {
                                callPopup('success', "<?php esc_html_e("Logs have been synchronized", "lws-optimize"); ?>");
                                document.getElementById('log_dir').innerHTML = returnData['data'];
                            } else {
                                callPopup('error', "<?php esc_html_e("Logs could not be found", "lws-optimize"); ?>");
                            }
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
</script>