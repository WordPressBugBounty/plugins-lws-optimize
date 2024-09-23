if (document.getElementById("wp-admin-bar-lwsop-clear-cache") != null) {
    document.getElementById("wp-admin-bar-lwsop-clear-cache").addEventListener('click', function() {
        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            dataType: 'json',
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_clear_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_fb_caching')); ?>'
            },
            success: function(data) {
                switch (data['code']) {
                    case 'SUCCESS':
                        alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
                        break;
                    default:
                        alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                        break;
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });
}
if (document.getElementById("wp-admin-bar-lwsop-clear-subcache") != null) {
    document.getElementById("wp-admin-bar-lwsop-clear-subcache").addEventListener('click', function() {
        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            dataType: 'json',
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_clear_style_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_style_fb_caching')); ?>'
            },
            success: function(data) {
                switch (data['code']) {
                    case 'SUCCESS':
                        alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
                        break;
                    default:
                        alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                        break;
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });
}
if (document.getElementById("wp-admin-bar-lwsop-clear-htmlcache") != null) {
    document.getElementById("wp-admin-bar-lwsop-clear-htmlcache").addEventListener('click', function() {
        jQuery.ajax({
            url: ajaxurl,
            dataType: 'json',
            type: "POST",
            timeout: 120000,
            context: document.body,
            data: {
                action: "lws_clear_html_fb_cache",
                _ajax_nonce: '<?php echo esc_attr(wp_create_nonce('clear_html_fb_caching')); ?>'
            },
            success: function(data) {
                switch (data['code']) {
                    case 'SUCCESS':
                        alert("<?php esc_html_e("Cache deleted", 'lws-optimize'); ?>");
                        break;
                    default:
                        alert("<?php esc_html_e("Cache not deleted", 'lws-optimize'); ?>");
                        break;
                }
            },
            error: function(error) {
                console.log(error);
            }
        });
    });
}