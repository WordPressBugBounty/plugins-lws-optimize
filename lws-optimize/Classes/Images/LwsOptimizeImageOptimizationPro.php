<?php

namespace Lws\Classes\Images;

class LwsOptimizeImageOptimizationPro
{
    private $log_file;
    private $format = ['jpg', 'jpeg', 'jpe', 'png'];

    public function __construct()
    {
        $state = get_transient('lws_optimize_deactivate_temporarily');

        // Create log file in uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/lwsoptimize';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        $this->log_file = $log_dir . '/debug.log';
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
        }

        // Refresh all informations on the conversion
        add_action('wp_ajax_lws_optimize_image_conversion_data_fetch', [$this, 'lws_optimize_refresh_conversion_data']);

        // Start the conversion cron (using the API)
        add_action('wp_ajax_lws_optimize_start_conversion_api', [$this, 'lws_optimize_start_conversion_api']);

        // Start the conversion cron (using Imagick)
        add_action('wp_ajax_lws_optimize_start_conversion_standard', [$this, 'lws_optimize_start_conversion_standard']);

        // Start the autoconversion (using the API)
        add_action('wp_ajax_lws_optimize_start_autoconversion_api', [$this, 'lws_optimize_start_autoconversion_api']);

        // Start the autoconversion (using Imagick)
        add_action('wp_ajax_lws_optimize_start_autoconversion_standard', [$this, 'lws_optimize_start_autoconversion_standard']);

        // Start the deconversion cron
        add_action('wp_ajax_lws_optimize_start_deconversion', [$this, 'lws_optimize_start_deconversion']);

        // Deactivate all crons
        add_action('wp_ajax_lws_optimize_stop_all_conversions', [$this, 'lws_optimize_stop_all_conversions']);

        if (!$state) {
            // Get the autoconversion options from the DB (if any)
            $autoconversion_options = get_option('lws_optimize_image_autoconversion_options', []);

            // Launch hook to autoconvert on upload images
            if (isset($autoconversion_options['state']) && $autoconversion_options['state']) {
                add_filter('wp_handle_upload_prefilter', [$this, 'lws_optimize_autoupload_images']);
            }


            // Replace images in the content
            add_filter('the_content', [$this, 'lws_optimize_replace_images_api']);
            add_filter('wp_filter_content_tags', [$this, 'lws_optimize_replace_images_api']);
            add_filter('post_thumbnail_html', [$this, 'lws_optimize_replace_images_api']);
            add_filter('widget_text_content', [$this, 'lws_optimize_replace_images_api']);
            add_filter('widget_custom_html_content', [$this, 'lws_optimize_replace_images_api']);
            add_action('template_redirect', [$this, 'lws_optimize_start_output_buffer']);

            // Cron to convert or deconvert images
            add_action('lws_optimize_pro_image_conversion_cron', [$this, 'lws_optimize_pro_image_conversion_cron']);
            add_action("lws_optimize_image_conversion_cron", [$this, "lws_optimize_image_conversion_cron"]);
            add_action('lws_optimize_image_deconversion_cron', [$this, 'lws_optimize_image_deconversion_cron']);
        }
    }


    /**
     * Refresh the images to be converted listing
     * and check the status of the conversion processes
     */
    public function lws_optimize_refresh_conversion_data() {

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Refreshing conversion data' . PHP_EOL);
        fclose($logger);

        // Format allowed to be converted
        $format = $this->format;

        $standard_data = [
            // All standard informations
            'conversion_status' => false,
            'deconversion_status' => false,
            'autoconversion_status' => false,
            'next_conversion' => 0,
            'next_deconversion' => 0,
            'images_to_convert' => 0,
            'images_converted' => 0,
            'images_left_to_convert' => 0,
            'size_reduction' => 0,
            'images_listing' => [],
            'remaining_credits' => "-",
            'api_key' => "",

            // All informations used in the on-website conversion
            'images_per_run' => 30,
            'images_quality' => 'balanced',
            'images_size' => 2560,
        ];

        // Get the conversion options from the DB (if any) and merge with the standard data to make sure all basic info is there
        $conversion_options = get_option('lws_optimize_image_conversion_options', []);
        $conversion_options = array_merge($standard_data, $conversion_options);

        $images_listing = $conversion_options['images_listing'] ?? [];

        // Get all images from the media library
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        );
        $query = new \WP_Query($args);
        $images = $query->posts;

        foreach ($images as $image) {
            $id = $image->ID;
            $file_path = get_attached_file($id);

            // Fix potential duplicate path segments in file path
            if (strpos($file_path, '/wp-content/uploads/wp-content/uploads/') !== false) {
                $file_path = preg_replace('|(.*?/wp-content/uploads)/wp-content/uploads/|', '$1/', $file_path);
                update_post_meta($id, '_wp_attached_file', str_replace(ABSPATH, '', $file_path));
            }

            // Get file extension and path info
            $path_info = pathinfo($file_path);
            $extension = strtolower($path_info['extension'] ?? '');


            // Check the file extension against the allowed formats array
            // If the extension is in there, then we have to convert it
            $to_convert = in_array($extension, $format);

            if ($to_convert) {
                // Create path for the optimized version with _lwsoptimized suffix
                $webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '_lwsoptimized.webp';
                $avif_path = $path_info['dirname'] . '/' . $path_info['filename'] . '_lwsoptimized.avif';

                // If WordPress recognizes the file as NOT WebP/AVIF
                // but the files somehow exist, we delete them
                // as it may means the conversion failed
                if (file_exists($webp_path)) {
                    unlink($webp_path);
                }
                if (file_exists($avif_path)) {
                    unlink($avif_path);
                }

                if (!file_exists($file_path)) {
                    // If the file does not exist, we skip it
                    continue;
                }


                // Add the image to the listing
                $images_listing[$id] = [
                    'name' => $image->post_title,
                    'path' => $file_path,
                    'format' => $extension,
                    'size' => filesize($file_path),
                    'converted' => false,
                ];
            }
            // If the file is in WebP or AVIF, we need to check if it was converted with our plugin
            // by checking the filename for the _lwsoptimized suffix
            else if (in_array($extension, ['webp', 'avif'])) {
                if (preg_match('/_lwsoptimized$/', $path_info['filename'])) {
                    // If the image is in our array already
                    if (!empty($images_listing[$id])) {
                        // If the file does not exist but there is an original, we consider it not converted
                        if (!file_exists($file_path)) {
                            if (file_exists($images_listing[$id]['path'])) {
                                array_merge($images_listing[$id], [
                                    'converted' => false,
                                    'converted_path' => null,
                                    'converted_format' => null,
                                    'converted_size' => null,
                                    'compression' => 0,
                                ]);

                                continue;
                            } else {
                                unlink($images_listing[$id]);
                            }
                        }

                        $original_size = $images_listing[$id]['size'] ?? 0;
                        $converted_size = filesize($file_path);

                        // Update the array with fresh data about the converted image without overriding the informations about the original
                        array_merge($images_listing[$id], [
                            'converted' => true,
                            'converted_path' => $file_path,
                            'converted_format' => $extension,
                            'converted_size' => $converted_size,
                            'compression' => $converted_size > 0 ? round($converted_size / $original_size, 2) : 0,
                        ]);
                    }
                    else {
                        // Try to find the original image by removing _lwsoptimized suffix and testing different extensions
                        $original_filename = str_replace('_lwsoptimized', '', $path_info['filename']);
                        $original_found = false;
                        $original_path = '';
                        $original_extension = '';

                        // Check each possible format extension to find the original file
                        foreach ($format as $ext) {
                            $possible_original = $path_info['dirname'] . '/' . $original_filename . '.' . $ext;
                            if (file_exists($possible_original)) {
                                $original_path = $possible_original;
                                $original_extension = $ext;
                                $original_found = true;
                                break;
                            }
                        }

                        // If the original has been found, we can store it in the array
                        if ($original_found) {
                            $original_size = filesize($original_path);
                            $converted_size = filesize($file_path);

                            // Add a new entry for this converted image
                            $images_listing[$id] = [
                                'name' => $image->post_title,
                                'path' => $original_path,
                                'format' => $original_extension,
                                'size' => $original_size,
                                'converted' => true,
                                'converted_path' => $file_path,
                                'converted_format' => $extension,
                                'converted_size' => $converted_size,
                                'compression' => $original_size > 0 ? round($converted_size / $original_size, 2) : 0,
                            ];
                        }

                    }
                }
            }
        }


        // Count images with 'converted' set to true and calculate size reduction
        $converted_count = 0;
        $total_compression = 0;

        foreach ($images_listing as $image) {
            if (isset($image['converted']) && $image['converted'] === true) {
                $converted_count++;

                if (isset($image['compression'])) {
                    $total_compression += $image['compression'];
                }
            }
        }

        if ($converted_count > 0) {
            $size_reduction = round($total_compression / $converted_count, 2) * 100;
        } else {
            $size_reduction = 0;
        }


        // Update the conversion options with the new data
        $conversion_options['images_listing'] = $images_listing;
        $conversion_options['images_to_convert'] = count($images_listing);
        $conversion_options['images_converted'] = $converted_count;
        $conversion_options['images_left_to_convert'] = count($images_listing) - $converted_count;
        $conversion_options['size_reduction'] = $size_reduction;

        // Manage the different crons. Only one cron can be active at any given time and priority is given to the pro version

        // Check for scheduled tasks
        $standard_conversion = wp_next_scheduled("lws_optimize_image_conversion_cron");
        $pro_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
        $deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");

        // Resolve conflicts between standard and pro versions
        if ($standard_conversion && $pro_conversion) {
            wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
            $standard_conversion = false;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Conflict : Both conversion crons activated at the same time. Both removed.' . PHP_EOL);
            fclose($logger);
        }


        // Resolve conflicts between conversion and deconversion
        if ($pro_conversion && $deconversion) {
            wp_unschedule_event($pro_conversion, "lws_optimize_pro_image_conversion_cron");
            wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");
            $deconversion = false;
            $standard_deconversion = false;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Conflict : Both pro conversion and deconversion activated at the same time. Both removed.' . PHP_EOL);
            fclose($logger);
        }

        if ($standard_conversion && $deconversion) {
            wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
            wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");
            $standard_conversion = false;
            $deconversion = false;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Conflict : Both standard conversion and deconversion activated at the same time. Both removed.' . PHP_EOL);
            fclose($logger);
        }

        // Determine active processes
        $active_conversion = $standard_conversion ?: $pro_conversion;

        // Update options with current status
        $conversion_options['conversion_status'] = (bool)$active_conversion;
        $conversion_options['deconversion_status'] = (bool)$deconversion;
        $conversion_options['next_conversion'] = $active_conversion;
        $conversion_options['next_deconversion'] = $deconversion;

        // Get the state of the autoconversion
        $autoconversion_options = get_option('lws_optimize_image_autoconversion_options', []);
        (isset($autoconversion_options['state']) && $autoconversion_options['state']) ? $conversion_options['autoconversion_status'] = true : $conversion_options['autoconversion_status'] = false;

        $response = $this->get_remaining_credits();

        $result = json_decode($response, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]));
        }

        // Check for API errors
        if (isset($result['code']) && $result['code'] == 'SUCCESS') {
            $conversion_options['remaining_credits'] = $result['data']['credits'] ?? 2000;
            $conversion_options['api_key'] = $result['data']['api_key'] ?? '';
        } else {
            // error_log(json_encode(['code' => 'API_ERROR', 'message' => 'Failed to get remaining credits', 'data' => $response]));
            $conversion_options['remaining_credits'] = "2000";
        }

        // Save the updated options
        update_option('lws_optimize_image_conversion_options', $conversion_options);

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => $conversion_options), JSON_PRETTY_PRINT));
    }


    /**
     * Convert all images on the WordPress website using an external API
     */
    public function lws_optimize_start_conversion_api() {
        check_ajax_referer('nonce_for_lws_optimize_start_conversion_api', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Starting pro conversion (API)...' . PHP_EOL);
        fclose($logger);

        $scheduled = false;
        // Deactivate every cron beforehand...
        $standard_conversion = wp_next_scheduled("lws_optimize_image_conversion_cron");
        $pro_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
        $deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");

        wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
        wp_unschedule_event($pro_conversion, "lws_optimize_pro_image_conversion_cron");
        wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Deactivating all conversion crons' . PHP_EOL);
        fclose($logger);

        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Conversion lock removed when starting pro conversion" . PHP_EOL);
        fclose($logger);

        // ...and then schedule the cron for the pro version
        $scheduled = wp_schedule_event(time() + 10, 'lws_minute', 'lws_optimize_pro_image_conversion_cron');
        $conversion_options = $this->lws_optimize_refresh_conversion_data();

        if ($scheduled) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Pro conversion activated. Next run: ' . $scheduled . PHP_EOL);
            fclose($logger);

            wp_die(json_encode(array('code' => 'SUCCESS', 'scheduled' => $scheduled, 'data' => $conversion_options, JSON_PRETTY_PRINT)));
        } else {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to start pro conversion cron' . PHP_EOL);
            fclose($logger);

            wp_die(json_encode(array('code' => 'FAILURE', JSON_PRETTY_PRINT)));
        }
    }

    /**
     * Convert all images on the WordPress website using Imagick in PHP
     */
    public function lws_optimize_start_conversion_standard() {
        check_ajax_referer('nonce_for_lws_optimize_start_conversion_standard', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Starting standard conversion...' . PHP_EOL);
        fclose($logger);

        $quality = sanitize_text_field($_POST['quality'] ?? 'balanced');
        $size = intval($_POST['size'] ?? 2560);
        $images_per_run = intval($_POST['images_per_run']) ?? 30;

        // Get the conversion options from the DB (if any) and update the values for the standard convertion
        $conversion_options = get_option('lws_optimize_image_conversion_options', []);
        $conversion_options['images_quality'] = $quality;
        $conversion_options['images_size'] = $size;
        $conversion_options['images_per_run'] = $images_per_run;
        update_option('lws_optimize_image_conversion_options', $conversion_options);

        $scheduled = false;
        // Deactivate every cron beforehand...
        $standard_conversion = wp_next_scheduled("lws_optimize_image_conversion_cron");
        $pro_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
        $deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");

        wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
        wp_unschedule_event($pro_conversion, "lws_optimize_pro_image_conversion_cron");
        wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Deactivating all conversion crons' . PHP_EOL);
        fclose($logger);

        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Conversion lock removed when starting standard conversion" . PHP_EOL);
        fclose($logger);

        // ...and then schedule the cron
        $scheduled = wp_schedule_event(time() + 10, 'lws_minute', 'lws_optimize_image_conversion_cron');
        $conversion_options = $this->lws_optimize_refresh_conversion_data();

        if ($scheduled) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Standard conversion activated. Next run: ' . $scheduled . PHP_EOL);
            fclose($logger);

            wp_die(json_encode(array('code' => 'SUCCESS', 'scheduled' => $scheduled, 'data' => $conversion_options, JSON_PRETTY_PRINT)));
        } else {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to start standard conversion cron' . PHP_EOL);
            fclose($logger);

            wp_die(json_encode(array('code' => 'FAILURE', JSON_PRETTY_PRINT)));
        }
    }

    /**
     * Deconvert all images on the WordPress website that have been converted using this plugin
     */
    public function lws_optimize_start_deconversion() {
        check_ajax_referer('nonce_for_lws_optimize_start_deconversion', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Starting Image Deconversion cron...' . PHP_EOL);
        fclose($logger);

        $scheduled = false;
        // Deactivate every cron beforehand...
        $standard_conversion = wp_next_scheduled("lws_optimize_image_conversion_cron");
        $pro_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
        $deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");

        wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
        wp_unschedule_event($pro_conversion, "lws_optimize_pro_image_conversion_cron");
        wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Deactivating all conversion crons' . PHP_EOL);
        fclose($logger);

        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Conversion lock removed when starting deconversion" . PHP_EOL);
        fclose($logger);

        // ...and then schedule the cron for the deconversion
        $scheduled = wp_schedule_event(time() + 10, 'lws_minute', 'lws_optimize_image_deconversion_cron');
        $conversion_options = $this->lws_optimize_refresh_conversion_data();

        if ($scheduled) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Image deconversion activated. Next run: ' . $scheduled . PHP_EOL);
            fclose($logger);

            wp_die(json_encode(array('code' => 'SUCCESS', 'scheduled' => $scheduled, 'data' => $conversion_options, JSON_PRETTY_PRINT)));
        } else {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Failed to start Image deconversion cron' . PHP_EOL);
            fclose($logger);


            wp_die(json_encode(array('code' => 'FAILURE', JSON_PRETTY_PRINT)));
        }
    }

    /**
     * Stop every ongoing conversion process.
     * As only one should be activated at any given time, the function can deactivate them all
     * without issues, which is simpler than 1 function per cron.
     */
    public function lws_optimize_stop_all_conversions() {
        check_ajax_referer('nonce_for_lws_optimize_stop_all_conversions', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Deactivating all conversion crons...' . PHP_EOL);
        fclose($logger);


        $standard_conversion = wp_next_scheduled("lws_optimize_image_conversion_cron");
        $pro_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
        $deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");

        wp_unschedule_event($standard_conversion, "lws_optimize_image_conversion_cron");
        wp_unschedule_event($pro_conversion, "lws_optimize_pro_image_conversion_cron");
        wp_unschedule_event($deconversion, "lws_optimize_image_deconversion_cron");

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] All conversion crons deactivated' . PHP_EOL);
        fclose($logger);

        // Since we're stopping all conversions, we should clear the lock as well
        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Conversion lock removed when stopping all conversions" . PHP_EOL);
        fclose($logger);

        wp_die(json_encode(array('code' => 'SUCCESS', JSON_PRETTY_PRINT)));
    }


    /**
     * Cron job to convert images in the background using the API
     * It will only process a limited number of images at a time to avoid timeouts
     * and will schedule the next run if there are still images to convert.
     *
     * @return string JSON response with the number of images processed, or error message
     */
    public function lws_optimize_pro_image_conversion_cron() {

        // Check if another conversion process is already running
        $conversion_lock = get_transient('lws_optimize_conversion_lock');

        // If lock exists, check if it's stale (older than 10 minutes)
        if ($conversion_lock) {
            $lock_time = is_array($conversion_lock) ? ($conversion_lock['time'] ?? 0) : 0;
            $stale_threshold = 600; // 10 minutes in seconds

            if (time() - $lock_time < $stale_threshold) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron already ongoing. Waiting to convert' . PHP_EOL);
                fclose($logger);

                // Process is already running and not stale, exit
                return;
            } else {
                // Lock is stale, log it and continue
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Detected stale lock (created ' . (time() - $lock_time) . ' seconds ago). Overriding.' . PHP_EOL);
                fclose($logger);
            }
        }

        // Set a lock with timestamp that expires in 5 minutes (300 seconds)
        set_transient('lws_optimize_conversion_lock', ['time' => time()], 300);

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron lock now in place' . PHP_EOL);
        fclose($logger);


        // Process up to 10 images per cron run to avoid timeouts
        $images_processed = 0;
        $max_images_per_run = 30;

        // Get all format allowed to be converted
        $format = $this->format;

        $conversion_options = get_option('lws_optimize_image_conversion_options', []);

        $images_to_process = $conversion_options['images_listing'] ?? [];

        $unconverted_images = 0;
        // Check if there are any images that need conversion and are available
        foreach ($images_to_process as $image) {
            if (empty($image['converted']) || $image['converted'] === false) {
                $unconverted_images++;
            }
        }


        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Images to process using the API: ' . $unconverted_images . PHP_EOL);
        fclose($logger);


        foreach ($images_to_process as $key => $image) {
            // Check if we have reached the maximum number of images to process
            if ($images_processed >= $max_images_per_run) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Maximum reached. Stopping cron at ' . $max_images_per_run . ' images' . PHP_EOL);
                fclose($logger);

                break;
            }

            // If there is no converted key, then consider the image as not converted
            if (empty($image['converted'])) {
                $image['converted'] = false;
            }

            // If the image is already converted...
            if ($image['converted']) {
                // ... but the converted image cannot be found, convert it again
                if (empty($image['converted_path']) || !file_exists($image['converted_path'])) {
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image at [{$image['converted_path']}] does not exist. Converting again" . PHP_EOL);
                    fclose($logger);

                    $image['converted'] = false;
                } else {
                    // ... otherwise skip it
                    continue;
                }
            }

            // If the image has no PATH or does not exists, skip it and mark it as unavailable
            if ((empty($image['path']) || !file_exists($image['path']))) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Original image at [{$image['path']}] not found. No conversion can be done" . PHP_EOL);
                fclose($logger);

                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            try {
                $response = $this->convert_image($image['path']);
            } catch (\Exception $e) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to convert image [{$image['path']}]. Error: {$e->getMessage()}" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'CONVERSION_ERROR', 'message' => 'Error during image conversion: ' . $e->getMessage(), 'data' => $image]));
                continue;
            }

            $result = json_decode($response, true);

            // Check for JSON decoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to decode JSON after converting [{$image['path']}]. Error: [" . json_last_error_msg() ."]" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]));
                continue;
            }

            // Check for API errors
            if (!isset($result['code']) || $result['code'] !== 'SUCCESS') {
                error_log($response);

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to convert image [{$image['path']}]. Error code: {$result['code']}" . PHP_EOL);
                fclose($logger);

                if ($result["code"] == "NO_CREDITS") {
                    // If there are no credits left, stop the conversion process
                    $conversion_records['status'] = false;

                    // Delete lock
                    delete_transient('lws_optimize_conversion_lock');

                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Removing cron lock" . PHP_EOL);
                    fclose($logger);

                    return $response;
                }
                continue;
            }

            $converted_path = $result['data']['optimized_path'] ?? '';
            $converted_format = $result['data']['format'] ?? '';

            if (!file_exists($converted_path)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] does not exist. Conversion has failed and will not be attempted again" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'MISSING_PATH', 'message' => 'No optimized path returned or file does not exist', 'data' => $result]));
                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            if (empty($converted_format)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] has no format returned. Conversion has failed and will not be attempted again" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'MISSING_FORMAT', 'message' => 'No format returned', 'data' => $result]));
                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            $size = $image['size'];
            $converted_size = filesize($converted_path) ?? 0;
            $compression = $converted_size > 0 ? round($converted_size / $size, 2) : 0;

            $image['converted'] = true;
            $image['converted_path'] = $converted_path;
            $image['converted_format'] = $converted_format;
            $image['converted_size'] = $converted_size;
            $image['compression'] = $compression;

            // Update the attachment in WordPress to use the new optimized file
            $attachment = array(
                'ID' => $key,
                'post_mime_type' => 'image/' . $converted_format,
            );

            // Update the attachment with the new mime type
            wp_update_attachment_metadata($key, wp_generate_attachment_metadata($key, $result['data']['optimized_path']));
            update_post_meta($key, '_wp_attached_file', str_replace(ABSPATH, '', $result['data']['optimized_path']));
            wp_update_post($attachment);

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] has been updated in WordPress. Conversion successful" . PHP_EOL);
            fclose($logger);

            // Regenerate the image thumbnails for the optimized image
            if (function_exists('wp_generate_attachment_metadata')) {
                $metadata = wp_generate_attachment_metadata($key, $result['data']['optimized_path']);
                wp_update_attachment_metadata($key, $metadata);

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}]'s thumbnails are being regenerated" . PHP_EOL);
                fclose($logger);
            }

            // Store the changes to the images
            $images_to_process[$key] = $image;

            // Increment the processed images count
            $images_processed++;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, "---------------------------" . PHP_EOL);
            fclose($logger);
        }

        // If no images were processed in this run and there are no images left to convert
        // or if all remaining images are unavailable, stop the cron
        if ($images_processed == 0) {
            $all_unavailable = true;
            $unconverted_images = 0;

            // Check if there are any images that need conversion and are available
            foreach ($images_to_process as $image) {
                if (empty($image['converted']) || $image['converted'] === false) {
                    $unconverted_images++;
                    // If at least one image is not marked as unavailable, we still have work to do
                    if (empty($image['unavailable']) || $image['unavailable'] === false) {
                        $all_unavailable = false;
                        break;
                    }
                }
            }

            // If there are no unconverted images or all remaining ones are unavailable
            if ($unconverted_images == 0 || $all_unavailable) {
                // Unschedule the cron
                $next_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
                if ($next_conversion) {
                    wp_unschedule_event($next_conversion, "lws_optimize_pro_image_conversion_cron");

                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] No more images to convert or all remaining images are unavailable. Stopping cron.' . PHP_EOL);
                    fclose($logger);

                    // Update the conversion status
                    $conversion_options['conversion_status'] = false;
                    $conversion_options['next_conversion'] = 0;
                }
            }
        }

        // Store the changes made to the images data
        $conversion_options['images_listing'] = $images_to_process;

        // Update the conversion options in the database
        update_option('lws_optimize_image_conversion_options', $conversion_options);

        // Delete lock for next cron
        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Removing cron lock. Data updated" . PHP_EOL);
        fclose($logger);

        // Return the $conversion_options and the amount of processed images
        return json_encode(array('code' => 'SUCCESS', 'data' => array('options' => $conversion_options, 'processed' => $images_processed), JSON_PRETTY_PRINT));
    }

    /**
     * Cron job to convert images in the background using Imagick
     * It will only process a limited number of images at a time to avoid timeouts
     * and will schedule the next run if there are still images to convert.
     *
     * @return string JSON response with the number of images processed
     */
    public function lws_optimize_image_conversion_cron()
    {
        // Check if another conversion process is already running
        $conversion_lock = get_transient('lws_optimize_conversion_lock');

        // If lock exists, check if it's stale (older than 10 minutes)
        if ($conversion_lock) {
            $lock_time = is_array($conversion_lock) ? ($conversion_lock['time'] ?? 0) : 0;
            $stale_threshold = 600; // 10 minutes in seconds

            if (time() - $lock_time < $stale_threshold) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron already ongoing. Waiting to convert' . PHP_EOL);
                fclose($logger);

                // Process is already running and not stale, exit
                return json_encode(['code' => 'RUNNING', 'message' => 'Conversion process already running']);
            } else {
                // Lock is stale, log it and continue
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Detected stale lock (created ' . (time() - $lock_time) . ' seconds ago). Overriding.' . PHP_EOL);
                fclose($logger);
            }
        }

        // Set a lock with timestamp that expires in 5 minutes (300 seconds)
        set_transient('lws_optimize_conversion_lock', ['time' => time()], 300);

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron lock now in place' . PHP_EOL);
        fclose($logger);

        // Process up to the specified number of images per cron run
        $images_processed = 0;

        // Get conversion options and image listing
        $conversion_options = get_option('lws_optimize_image_conversion_options', []);
        $images_to_process = $conversion_options['images_listing'] ?? [];

        // Get the defined quality and size
        $quality = $conversion_options['images_quality'] ?? 'balanced';
        $max_size = $conversion_options['images_size'] ?? 2560;

        // Determine max_images_per_run based on PHP's max_execution_time
        $max_execution_time = ini_get('max_execution_time');
        // If max_execution_time is 0 (unlimited) or high, use a reasonable default
        if ($max_execution_time == 0 || $max_execution_time > 90) {
            $max_images_per_run = 10;
        } else {
            // Estimate approximately 5-6 seconds per image conversion
            // For 30 second timeout, process 5 images; for 60 seconds, process 10
            $max_images_per_run = max(1, min(10, floor($max_execution_time / 6)));
        }
        //$max_images_per_run = $conversion_options['images_per_run'] ?? 30;



        // Check if there are any images that need conversion and are available
        $unconverted_images = 0;
        foreach ($images_to_process as $image) {
            if (empty($image['converted']) || $image['converted'] === false) {
                $unconverted_images++;
            }
        }


        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Images to process using standard method: ' . $unconverted_images . PHP_EOL);
        fclose($logger);

        foreach ($images_to_process as $key => $image) {
            // Check if we have reached the maximum number of images to process
            if ($images_processed >= $max_images_per_run) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Maximum reached. Stopping cron at ' . $max_images_per_run . ' images' . PHP_EOL);
                fclose($logger);

                break;
            }

            // If there is no converted key, then consider the image as not converted
            if (empty($image['converted'])) {
                $image['converted'] = false;
            }

            // If the image is already converted...
            if ($image['converted']) {
                // ... but the converted image cannot be found, convert it again
                if (empty($image['converted_path']) || !file_exists($image['converted_path'])) {
                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image at [{$image['converted_path']}] does not exist. Converting again" . PHP_EOL);
                    fclose($logger);

                    $image['converted'] = false;
                } else {
                    // ... otherwise skip it
                    continue;
                }
            }

            // If the image has no PATH or does not exists, skip it and mark it as unavailable
            if ((empty($image['path']) || !file_exists($image['path']))) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Original image at [{$image['path']}] not found. No conversion can be done" . PHP_EOL);
                fclose($logger);

                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            try {
                // Save the image in the same directory as the original, replacing the original extension with the new one
                // ONLY IN WEBP
                $pathInfo = pathinfo($image['path']);
                $conversion_path = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_lwsoptimized.webp';

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Converting image [{$image['path']}] to WebP format" . PHP_EOL);
                fclose($logger);

                // Convert image using the standard method
                $response = $this->convert_image_standard($image['path'], $conversion_path, $quality, $image['format'], 'webp', $max_size);
            } catch (\Exception $e) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to convert image [{$image['path']}]. Error: {$e->getMessage()}" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'CONVERSION_ERROR', 'message' => 'Error during image conversion: ' . $e->getMessage(), 'data' => $image]));
                continue;
            }

            $result = json_decode($response, true);

            // Check for JSON decoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to decode JSON after converting [{$image['path']}]. Error: [" . json_last_error_msg() ."]" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]));
                continue;
            }

            // Check for API errors
            if (!isset($result['code']) || $result['code'] !== 'SUCCESS') {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to convert image [{$image['path']}]. Error code: {$result['code']}" . PHP_EOL);
                fclose($logger);

                error_log($response);
                continue;
            }

            $converted_path = $result['data']['optimized_path'] ?? '';
            $converted_format = $result['data']['format'] ?? '';

            if (!file_exists($converted_path)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] does not exist. Conversion has failed and will not be attempted again" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'MISSING_PATH', 'message' => 'No optimized path returned or file does not exist', 'data' => $result]));
                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            if (empty($converted_format)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] has no format returned. Conversion has failed and will not be attempted again" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'MISSING_FORMAT', 'message' => 'No format returned', 'data' => $result]));
                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            $size = $image['size'];
            $converted_size = filesize($converted_path) ?? 0;
            $compression = $converted_size > 0 ? round($converted_size / $size, 2) : 0;

            $image['converted'] = true;
            $image['converted_path'] = $converted_path;
            $image['converted_format'] = $converted_format;
            $image['converted_size'] = $converted_size;
            $image['compression'] = $compression;

            // Update the attachment in WordPress to use the new optimized file
            $attachment = array(
                'ID' => $key,
                'post_mime_type' => 'image/' . $converted_format,
            );

            // Update the attachment with the new mime type
            wp_update_attachment_metadata($key, wp_generate_attachment_metadata($key, $converted_path));
            update_post_meta($key, '_wp_attached_file', str_replace(ABSPATH, '', $converted_path));
            wp_update_post($attachment);

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}] has been updated in WordPress. Conversion successful" . PHP_EOL);
            fclose($logger);

            // Regenerate the image thumbnails for the optimized image
            if (function_exists('wp_generate_attachment_metadata')) {
                $metadata = wp_generate_attachment_metadata($key, $converted_path);
                wp_update_attachment_metadata($key, $metadata);

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$converted_path}]'s thumbnails are being regenerated" . PHP_EOL);
                fclose($logger);
            }

            // Store the changes to the images
            $images_to_process[$key] = $image;

            // Increment the processed images count
            $images_processed++;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, "---------------------------" . PHP_EOL);
            fclose($logger);
        }

        // Store the changes made to the images data
        $conversion_options['images_listing'] = $images_to_process;

        // If no images were processed in this run and there are no images left to convert
        // or if all remaining images are unavailable, stop the cron
        if ($images_processed == 0) {
            $all_unavailable = true;
            $unconverted_images = 0;

            // Check if there are any images that need conversion and are available
            foreach ($images_to_process as $image) {
                if (empty($image['converted']) || $image['converted'] === false) {
                    $unconverted_images++;
                    // If at least one image is not marked as unavailable, we still have work to do
                    if (empty($image['unavailable']) || $image['unavailable'] === false) {
                        $all_unavailable = false;
                        break;
                    }
                }
            }

            // If there are no unconverted images or all remaining ones are unavailable
            if ($unconverted_images == 0 || $all_unavailable) {
                // Unschedule the cron
                $next_conversion = wp_next_scheduled("lws_optimize_pro_image_conversion_cron");
                if ($next_conversion) {
                    wp_unschedule_event($next_conversion, "lws_optimize_pro_image_conversion_cron");

                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] No more images to convert or all remaining images are unavailable. Stopping cron.' . PHP_EOL);
                    fclose($logger);

                    // Update the conversion status
                    $conversion_options['conversion_status'] = false;
                    $conversion_options['next_conversion'] = 0;
                }
            }
        }

        // Update the conversion options in the database
        update_option('lws_optimize_image_conversion_options', $conversion_options);

        // Delete lock for next cron
        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Removing cron lock. Data updated" . PHP_EOL);
        fclose($logger);

        // Return the $conversion_options and the amount of processed images
        return json_encode(array('code' => 'SUCCESS', 'data' => array('options' => $conversion_options, 'processed' => $images_processed), JSON_PRETTY_PRINT));
    }

    /**
     * Take all images stored in the database and revert them to their original state
     *
     * @return string JSON response with the number of images processed, or error message
     */
    public function lws_optimize_image_deconversion_cron()
    {
        // Check if another conversion process is already running
        $conversion_lock = get_transient('lws_optimize_conversion_lock');
        if ($conversion_lock) {
            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron already ongoing. Waiting to deconvert' . PHP_EOL);
            fclose($logger);

            // Process is already running, exit
            return;
        }

        // Set a lock that expires in 5 minutes (300 seconds)
        // This ensures only one cron job runs at a time
        set_transient('lws_optimize_conversion_lock', true, 300);

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Cron lock now in place' . PHP_EOL);
        fclose($logger);

        // Process up to 10 images per cron run to avoid timeouts
        $images_processed = 0;

        // Determine max_images_per_run based on PHP's max_execution_time
        $max_execution_time = ini_get('max_execution_time');
        // If max_execution_time is 0 (unlimited) or high, use a reasonable default
        if ($max_execution_time == 0 || $max_execution_time > 90) {
            $max_images_per_run = 20;
        } else {
            // Estimate approximately 5-6 seconds per image conversion
            // For 30 second timeout, process 5 images; for 60 seconds, process 10
            $max_images_per_run = max(1, min(10, floor($max_execution_time / 3)));
        }

        // Get all format allowed to be converted
        $format = $this->format;

        $conversion_options = get_option('lws_optimize_image_conversion_options', []);

        $images_to_process = $conversion_options['images_listing'] ?? [];
        $converted_images = 0;
        // Check if there are any images that need deconversion and are available
        foreach ($images_to_process as $image) {
            if (!empty($image['converted']) && $image['converted']) {
                $converted_images++;
            }
        }

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Images to deconvert: ' . $converted_images . PHP_EOL);
        fclose($logger);

        foreach ($images_to_process as $key => $image) {
            // Check if we have reached the maximum number of images to process
            if ($images_processed >= $max_images_per_run) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Maximum reached. Stopping cron at ' . $max_images_per_run . ' images deconverted' . PHP_EOL);
                fclose($logger);
                break;
            }

            // If there is no converted key, then consider the image as not converted and as such ignore it
            if (empty($image['converted'])) {
                continue;
            }

            // Do not deconvert images that are not converted
            if (!$image['converted']) {
                continue;
            }

            // If the original image cannot be found, then consider this image as unavailable
            if (empty($image['path']) || !file_exists($image['path'])) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Original image at [{$image['path']}] not found. No deconversion can be done" . PHP_EOL);
                fclose($logger);

                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            $format = $image['format'];
            if (empty($format)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Original image at [{$image['path']}] has no format. No deconversion can be done" . PHP_EOL);
                fclose($logger);

                error_log(json_encode(['code' => 'MISSING_FORMAT', 'message' => 'No format found for the original image']));
                $images_to_process[$key]['unavailable'] = true;
                continue;
            }

            // Update the attachment in WordPress to use the old file
            $attachment = array(
                'ID' => $key,
                'post_mime_type' => 'image/' . $format,
            );

            // Update the attachment with the new mime type
            wp_update_attachment_metadata($key, wp_generate_attachment_metadata($key, $image['path']));
            update_post_meta($key, '_wp_attached_file', str_replace(ABSPATH, '', $image['path']));
            wp_update_post($attachment);

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$image['path']}] has been updated in WordPress. Deconversion successful" . PHP_EOL);
            fclose($logger);

            // Regenerate the image thumbnails for the optimized image
            if (function_exists('wp_generate_attachment_metadata')) {
                $metadata = wp_generate_attachment_metadata($key, $image['path']);
                wp_update_attachment_metadata($key, $metadata);

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$image['path']}]'s thumbnails are being regenerated" . PHP_EOL);
                fclose($logger);
            }

            if (file_exists($image['converted_path'])) {
                // Delete the converted image
                unlink($image['converted_path']);

                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Converted image [{$image['converted_path']}] deleted" . PHP_EOL);
                fclose($logger);
            }

            $image['converted'] = false;
            $image['converted_path'] = '';
            $image['converted_format'] = '';
            $image['converted_size'] = 0;
            $image['compression'] = 0;
            $image['unavailable'] = false;

            // Store the changes to the images
            $images_to_process[$key] = $image;

            // Increment the processed images count
            $images_processed++;

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, "---------------------------" . PHP_EOL);
            fclose($logger);
        }

        // If no images were processed in this run, stop the cron
        if ($images_processed == 0) {
            // Check if there are any remaining converted images that need to be deconverted
            $remaining_converted = false;
            foreach ($images_to_process as $image) {
                if (!empty($image['converted']) && $image['converted'] === true) {
                    $remaining_converted = true;
                    break;
                }
            }

            // If there are no remaining converted images, stop the cron
            if (!$remaining_converted) {
                // Unschedule the cron
                $next_deconversion = wp_next_scheduled("lws_optimize_image_deconversion_cron");
                if ($next_deconversion) {
                    wp_unschedule_event($next_deconversion, "lws_optimize_image_deconversion_cron");

                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . '] No more images to deconvert. Stopping cron.' . PHP_EOL);
                    fclose($logger);

                    // Update the deconversion status
                    $conversion_options['deconversion_status'] = false;
                    $conversion_options['next_deconversion'] = 0;
                }
            }
        }

        // Store the changes made to the images data
        $conversion_options['images_listing'] = $images_to_process;

        // Update the conversion options in the database
        update_option('lws_optimize_image_conversion_options', $conversion_options);

        // Delete lock for next cron
        delete_transient('lws_optimize_conversion_lock');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Removing cron lock. Data updated" . PHP_EOL);
        fclose($logger);

        // Return the $conversion_options and the amount of processed images
        return json_encode(array('code' => 'SUCCESS', 'data' => array('options' => $conversion_options, 'processed' => $images_processed), JSON_PRETTY_PRINT));
    }


    /**
     * On the homepage of most WordPress websites, the other function will not work natively
     * as images are loaded differently, generally by the theme.
     * As such we are forced to use ob_start to get and replace images
     */
    public function lws_optimize_start_output_buffer()
    {
        // Apply to all pages, not just homepage
        if (is_front_page() || is_home()) {
            ob_start([$this, 'lws_optimize_replace_images_api']);
        }
    }

    /**
     * Hijack the uploading process to create a new version of the given $file.
     * This will be called when the user uploads a new image, converting it before it gets added by WordPress.
     * It will not upload the original file at all
     */
    public function lws_optimize_autoupload_images($file)
    {
        // Only convert if the file type is image ; otherwise just return the untouched $file array
        if (substr($file['type'], 0, 5) === "image") {
            // Only convert JPGs and PNGs
            $format = $this->format;

            $format_string = implode('|', $format);

            // Extract file information
            $file_path = $file['tmp_name'];
            $file_type = $file['type'];

            $tmp = explode("/", $file_type);
            $mime_type = $tmp[0] == "image" ? $tmp[1] : null;

            // If the file has no mime-type or the type is not supported, return the original file
            if (empty($mime_type) || !in_array($mime_type, $format)) {
                error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid/not supported.', 'data' => $file]));
                return $file;
            }

            // Depending on which autoconversion type has been chosen, we will use the API or the standard method
            // If nothing is found, default to using the standard method
            $autoconversion_options = get_option('lws_optimize_image_autoconversion_options', []);
            if (isset($autoconversion_options['type']) && $autoconversion_options['type'] == "api") {
                $response = $this->convert_image($file_path, $file_path);
            } else {
                $quality = $autoconversion_options['quality'] ?? 'balanced';
                $max_size = $autoconversion_options['size'] ?? 2560;
                $response = $this->convert_image_standard($file_path, $file_path, $quality, $mime_type, "webp", $max_size);
            }

            $result = json_decode($response, true);

            // Check for JSON decoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log(json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]));
                return $file;
            }

            // Check for API errors
            if (!isset($result['code']) || $result['code'] !== 'SUCCESS') {
                error_log($response);
                return $file;
            }

            // Get the chosen format (AVIF or WebP), the new PATH and the remaining credits
            $output_format = $result['data']['format'] ?? null;
            $optimized_path = $result['data']['optimized_path'] ?? null;

            // Check if an optimized path was returned
            if (empty($optimized_path) || !file_exists($optimized_path)) {
                error_log(json_encode(['code' => 'MISSING_PATH', 'message' => 'No optimized path returned or file does not exist', 'data' => $result]));
                return $file;
            }

            // Change the parameters of the uploaded file
            $type = strtolower($output_format);
            $file['type'] = "image/$type";
            $file['name'] = preg_replace("/\.($format_string)$/i", ".$type", $file['name']);
            // Set the full path correctly, including directory if it exists
            $file['full_path'] = isset($file['full_path']) ? pathinfo($file['full_path'], PATHINFO_DIRNAME) . '/' . $file['name'] : $file['name'];
            // Update size to match the new file
            $file['size'] = filesize($optimized_path);
        }

        return $file;
    }

    /**
     * Replace images in the content with their optimized versions
     * Enhanced to handle more complex cases and edge cases
     */
    public function lws_optimize_replace_images_api($content)
    {
        // Skip if content is empty or doesn't contain any images
        if (empty($content) || (strpos($content, '<img') === false && strpos($content, 'background') === false)) {
            return $content;
        }

        // Store site URL to avoid repeated calls
        static $site_url = null;
        static $upload_dir = null;
        static $optimized_cache = [];

        if ($site_url === null) {
            $site_url = site_url();
            $uploads = wp_upload_dir();
            $upload_dir = $uploads['basedir'];
        }

        // First pass: Replace <img> tags
        $content = preg_replace_callback(
            '/<img[^>]+src=([\'"])([^\'"]*)\\1[^>]*>/i',
            function ($matches) use ($site_url, $upload_dir, &$optimized_cache) {
                $img_tag = $matches[0];
                $image_url = $matches[2];

                // Skip external images or already optimized images
                if (strpos($image_url, $site_url) === false ||
                    strpos($image_url, '_lwsoptimized') !== false) {
                    return $img_tag;
                }

                // Get server path from URL
                $image_path = $this->url_to_path($image_url);
                if (!$image_path || !file_exists($image_path)) {
                    return $img_tag;
                }

                // Look for optimized versions
                $pathInfo = pathinfo($image_path);
                $filename = $pathInfo['filename'];
                $dirname = $pathInfo['dirname'];

                // First check AVIF (higher priority)
                $optimized_avif = "$dirname/{$filename}_lwsoptimized.avif";
                if (file_exists($optimized_avif)) {
                    $optimized_url = str_replace($image_path, $optimized_avif, $image_url);
                    $optimized_cache[$image_path] = $optimized_url;

                    // Add type attribute for browsers
                    $img_tag = str_replace($image_url, $optimized_url, $img_tag);
                    if (strpos($img_tag, 'type=') === false) {
                        $img_tag = str_replace('<img ', '<img type="image/avif" ', $img_tag);
                    }
                    return $img_tag;
                }

                // Then check WebP
                $optimized_webp = "$dirname/{$filename}_lwsoptimized.webp";
                if (file_exists($optimized_webp)) {
                    $optimized_url = str_replace($image_path, $optimized_webp, $image_url);
                    $optimized_cache[$image_path] = $optimized_url;

                    // Add type attribute for browsers
                    $img_tag = str_replace($image_url, $optimized_url, $img_tag);
                    if (strpos($img_tag, 'type=') === false) {
                        $img_tag = str_replace('<img ', '<img type="image/webp" ', $img_tag);
                    }
                    return $img_tag;
                }

                // No optimized version found
                $optimized_cache[$image_path] = false;
                return $img_tag;
            },
            $content
        );

        // Second pass: Replace CSS background images
        $content = preg_replace_callback(
            '/url\(([\'"]?)([^\'"\)]+)\\1\)/i',
            function ($matches) use ($site_url, $upload_dir, &$optimized_cache) {
                $full_match = $matches[0];
                $image_url = $matches[2];

                // Skip external images, SVGs, or already optimized images
                if (strpos($image_url, $site_url) === false ||
                    strpos($image_url, '.svg') !== false ||
                    strpos($image_url, '_lwsoptimized') !== false) {
                    return $full_match;
                }

                // Get server path from URL
                $image_path = $this->url_to_path($image_url);
                if (!$image_path || !file_exists($image_path)) {
                    return $full_match;
                }

                // Check cache first
                if (isset($optimized_cache[$image_path])) {
                    if ($optimized_cache[$image_path]) {
                        return str_replace($image_url, $optimized_cache[$image_path], $full_match);
                    }
                    return $full_match;
                }

                // Look for optimized versions
                $pathInfo = pathinfo($image_path);
                $filename = $pathInfo['filename'];
                $dirname = $pathInfo['dirname'];

                // First check AVIF (higher priority)
                $optimized_avif = "$dirname/{$filename}_lwsoptimized.avif";
                if (file_exists($optimized_avif)) {
                    $optimized_url = str_replace($image_path, $optimized_avif, $image_url);
                    $optimized_cache[$image_path] = $optimized_url;
                    return str_replace($image_url, $optimized_url, $full_match);
                }

                // Then check WebP
                $optimized_webp = "$dirname/{$filename}_lwsoptimized.webp";
                if (file_exists($optimized_webp)) {
                    $optimized_url = str_replace($image_path, $optimized_webp, $image_url);
                    $optimized_cache[$image_path] = $optimized_url;
                    return str_replace($image_url, $optimized_url, $full_match);
                }

                // No optimized version found
                $optimized_cache[$image_path] = false;
                return $full_match;
            },
            $content
        );

        return $content;
    }

    /**
     * Helper function to convert image URLs to server paths
     */
    private function url_to_path($url) {
        // Remove query strings
        $url = preg_replace('/\?.*/', '', $url);

        // Convert URL to server path
        $site_url = site_url();
        $home_url = home_url();
        $upload_url = wp_upload_dir()['baseurl'];
        $upload_dir = wp_upload_dir()['basedir'];

        // Try multiple methods to find the file path
        if (strpos($url, $upload_url) !== false) {
            // Standard uploads folder
            return str_replace($upload_url, $upload_dir, $url);
        } elseif (strpos($url, $home_url) !== false) {
            // Main home URL
            return str_replace($home_url, ABSPATH, $url);
        } elseif (strpos($url, $site_url) !== false) {
            // Site URL (might be different from home)
            return str_replace($site_url, ABSPATH, $url);
        } else {
            // Relative URL
            if (strpos($url, '/') === 0) {
                return ABSPATH . ltrim($url, '/');
            }
        }

        return false;
    }

    /**
     * Convert the given image to WebP or AVIF (taking the best of the 2).
     * This uses an external API to convert the image
     *
     * @param string $path The path to the image
     * @param string|null $endpath The PATH where to save the new image (mainly to use with the on-upload conversion)
     * @return string JSON response from the API, either an error or the converted image
     */
    public function convert_image($path, $endpath = null) {
        // The API Key is unique to each domain and generated by the API the first time.
        // If using a key not corresponding with the domain, then the API will return an error.
        $api_key = get_option('lws_optimize_image_api_key', false);

        // Check for the existence of the file at $path
        if (!file_exists($path)) {
            return json_encode(['code' => 'FILE_NOT_FOUND', 'message' => 'File not found', 'data' => $path]);
        }

        // Create the file to be sent via cURL
        $mime = mime_content_type($path);
        $cfile = new \CURLFile($path, $mime, basename($path));

        // Requête cURL
        $ch = curl_init('https://compress.lwspanel.com/compress-image');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'image' => $cfile,
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin: ' . site_url() ?: '',
            $api_key ? "X-Api-Key: $api_key" : null
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 402) {
            $result = json_decode($response, true);
            $api_key = $result['apiKey'] ?? null;
            update_option('lws_optimize_image_api_key', $api_key);


            return json_encode(['code' => 'NO_CREDITS', 'message' => 'No credits left for this domain', 'data' => $response]);
        }

        if ($code !== 200) {
            return json_encode(['code' => 'HTTP_ERROR', 'message' => "API returned code $code", 'data' => $response]);
        }

        $result = json_decode($response, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]);
        }

        $image = $result['base64'] ?? null;
        $format = $result['format'] ?? null;
        $remaining_credits = $result['remainingCredits'] ?? null;
        $api_key = $result['apiKey'] ?? null;

        update_option('lws_optimize_image_api_key', $api_key);

        if (empty($image) || empty($format)) {
            return json_encode(['code' => 'FAIL_OPTIMIZE', 'message' => 'The given image could not be optimized', 'data' => $response]);
        }

        // Save the image in the same directory as the original, replacing the original extension with the new one
        // If the $endpath is provided, save the image there instead
        $pathInfo = pathinfo($path);
        $outputPath = !empty($endpath) ? $endpath : $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_lwsoptimized.' . $format;

        // Save the optimized image
        if (file_put_contents($outputPath, base64_decode($image)) === false) {
            return json_encode(['code' => 'SAVE_ERROR', 'message' => 'Failed to save optimized image', 'data' => $outputPath]);
        }

        return json_encode(['code' => 'SUCCESS', 'message' => 'Image successfully optimized','data' => [
                'image_path' => $path,
                'optimized_path' => $outputPath,
                'format' => $format,
                'remaining_credits' => $remaining_credits
            ]
        ]);
    }

    /**
     * Create a copy of the given $image, convert it to the $end type from the current $origin.
     * The image will then be saved to $output. $output and $image can be the same to replace the image.
     * This is free and does not use any API or credits.
     *
     * @param string $image The PATH to the image to convert
     * @param string $origin The mime-type in which the image currently is. Format : image/png
     * @param string $end The mime-type in which the image needs to be converted. Format : image/webp
     * @param string $output The PATH where to save the newly converted image
     *
     * @return bool Either true on success or false on error
     */
    public function convert_image_standard(string $image, string $output, string $quality = 'balanced', string $origin = "jpeg", string $end = "webp", $max_size = 2560)
    {
        $timer = microtime(true);

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Starting process to convert image [{$image}} with Imagick". PHP_EOL);
        fclose($logger);

        try {
            // Validate parameters
            if (empty($image) || empty($output) || empty($origin) || empty($end)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Missing parameters [" . $image ?? 'NO IMAGE' . "] [" . $output ?? 'NO OUTPUT' . "] [" . $origin ?? 'NO ORIGIN' . "][" . $end ?? 'NO END' . "]". PHP_EOL);
                fclose($logger);
                return json_encode(['code' => 'NO_PARAMETERS', 'message' => 'Missing required parameters', 'time' => microtime(true) - $timer]);
            }

            // Check for Imagick availability
            if (!extension_loaded('imagick') || !class_exists('Imagick')) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Imagick not found. Aborting". PHP_EOL);
                fclose($logger);
                return json_encode(['code' => 'IMAGICK_NOT_FOUND', 'message' => 'Imagick extension required for image optimization', 'time' => microtime(true) - $timer]);
            }

            // Parse image types
            $starting_type = preg_replace('/^image\//', '', strtolower($origin));
            $ending_type = preg_replace('/^image\//', '', strtolower($end));

            // Create Imagick instance
            $img = new \Imagick();
            $supported_formats = $img->queryFormats();

            // Check format support
            if (!in_array(strtoupper($starting_type), $supported_formats) || !in_array(strtoupper($ending_type), $supported_formats)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$image}} cannot be converted as either {$starting_type} or {$ending_type} is not supported". PHP_EOL);
                fclose($logger);

                return json_encode([
                    'code' => 'UNSUPPORTED_FORMAT',
                    'message' => 'Image format not supported by Imagick',
                    'data' => ['source' => $starting_type, 'target' => $ending_type],
                    'time' => microtime(true) - $timer
                ]);
            }

            // Read source image
            if (!file_exists($image) || !$img->readImage($image)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to read [{$image}}, cannot convert". PHP_EOL);
                fclose($logger);
                return json_encode(['code' => 'IMAGE_UNREADABLE', 'message' => 'Source image not readable', 'data' => $image, 'time' => microtime(true) - $timer]);
            }

            // Resize if needed
            $width = $img->getImageWidth();
            $height = $img->getImageHeight();

            if ($width > $max_size) {
                $newHeight = intval(($max_size / $width) * $height);
                $img->resizeImage($max_size, $newHeight, \Imagick::FILTER_LANCZOS, 1);
            }

            // Strip metadata to reduce file size
            $img->stripImage();

            // Set compression quality
            switch ($quality) {
                case 'low':
                    $quality = 30;
                    break;
                case 'balanced':
                    $quality = 64;
                    break;
                case 'high':
                    $quality = 90;
                    break;
                default:
                    $quality = 64;
            }
            $img->setImageCompressionQuality($quality);

            // Handle format conversion
            if (!$img->setImageFormat($ending_type)) {
                $logger = fopen($this->log_file, 'a');
                fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to convert [{$image}} top {$ending_type}". PHP_EOL);
                fclose($logger);
                return json_encode(['code' => 'CONVERSION_FAIL', 'message' => 'Format conversion failed', 'time' => microtime(true) - $timer]);
            }

            // Optimize for specific formats
            if ($ending_type === 'webp') {
                $img->setOption('webp:method', '6'); // Better compression
            }

            // Write output image
            if (!$img->writeImage($output)) {
                // Fallback to alternative write method
                $fp = fopen($output, "wb");
                if (!$fp || !$img->writeImageFile($fp)) {
                    if ($fp) {
                        fclose($fp);
                    }

                    $logger = fopen($this->log_file, 'a');
                    fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Failed to write image [{$image}}, conversion failed". PHP_EOL);
                    fclose($logger);

                    return json_encode(['code' => 'WRITE_FAIL', 'message' => 'Failed to write output image', 'time' => microtime(true) - $timer]);
                }
                fclose($fp);
            }

            // Clean up resources
            $img->clear();
            $img->destroy();

            $logger = fopen($this->log_file, 'a');
            fwrite($logger, '[' . date('Y-m-d H:i:s') . "] Image [{$image}} converted from {$starting_type} to {$ending_type}". PHP_EOL);
            fclose($logger);

            return json_encode(['code' => 'SUCCESS', 'message' => 'Image successfully optimized','data' => [
                'image_path' => $image,
                'optimized_path' => $output,
                'format' => $end,
            ]]);
        } catch (\Exception $e) {
            return json_encode([
                'code' => 'ERROR',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'time' => microtime(true) - $timer
            ]);
        }
    }

    /**
     * Start or stop the autoconversion process using the API
     */
    public function lws_optimize_start_autoconversion_api() {
        check_ajax_referer('nonce_for_lws_optimize_start_autoconversion_api', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Activating API autoconversion on upload' . PHP_EOL);
        fclose($logger);

        $state = boolval($_POST['state'] ?? false);

        // Get the autoconversion options from the DB (if any)
        $autoconversion_options = get_option('lws_optimize_image_autoconversion_options', []);

        $autoconversion_options = [
            'state' => $state,
            'type' => 'api',
        ];

        // Update the autoconversion options in the database
        update_option('lws_optimize_image_autoconversion_options', $autoconversion_options);

        $logger = fopen($this->log_file, 'a');
        if ($state) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Autoconversion has been activated using API' . PHP_EOL);
        } else {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Autoconversion has been deactivated' . PHP_EOL);
        }
        fclose($logger);

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => $autoconversion_options, JSON_PRETTY_PRINT)));
    }

    /**
     * Start or stop the autoconversion process using Imagick
     */
    public function lws_optimize_start_autoconversion_standard() {
        check_ajax_referer('nonce_for_lws_optimize_start_autoconversion_standard', '_ajax_nonce');

        $logger = fopen($this->log_file, 'a');
        fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Activating standard autoconversion on upload' . PHP_EOL);
        fclose($logger);

        $quality = sanitize_text_field($_POST['quality'] ?? 'balanced');
        $size = intval($_POST['size'] ?? 2560);
        $state = sanitize_text_field($_POST['state'] ?? "false");

        // Get the autoconversion options from the DB (if any)
        $autoconversion_options = get_option('lws_optimize_image_autoconversion_options', []);

        $autoconversion_options = [
            'quality' => $quality,
            'size' => $size,
            'state' => $state == "false" ? false : true,
            'type' => 'standard',
        ];

        // Update the autoconversion options in the database
        update_option('lws_optimize_image_autoconversion_options', $autoconversion_options);

        $logger = fopen($this->log_file, 'a');
        if ($state) {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Autoconversion has been activated using Imagick' . PHP_EOL);
        } else {
            fwrite($logger, '[' . date('Y-m-d H:i:s') . '] Autoconversion has been deactivated' . PHP_EOL);
        }
        fclose($logger);

        wp_die(json_encode(array('code' => 'SUCCESS', 'data' => $autoconversion_options, JSON_PRETTY_PRINT)));
    }

    /**
     * Get the remaining credits for the current domain
     * @return string JSON response with the remaining credits or an error message. Also contains the APi Key and the domains
     */
    public function get_remaining_credits() {
        // Get the API Key saved in database ; If there is none, we cannot check credits
        $api_key = get_option('lws_optimize_image_api_key', false);

        $ch = curl_init('https://compress.lwspanel.com/credits');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Origin: ' . site_url() ?: '',
            $api_key ? "X-Api-Key: $api_key" : null
        ]);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return json_encode(['code' => 'HTTP_ERROR', 'message' => "API returned code $code", 'data' => $response]);
        }

        $result = json_decode($response, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(['code' => 'JSON_ERROR', 'message' => 'Failed to decode JSON response: ' . json_last_error_msg(), 'data' => $response]);
        }

        $credits = $result['credits'] ?? 2000;
        $domains = $result['domains'] ?? null;
        $api_key = $result['apiKey'] ?? null;

        update_option('lws_optimize_image_api_key', $api_key);

        if ($credits === null) {
            return json_encode(['code' => 'FAIL_GET_CREDITS', 'message' => 'Failed to retrieve credits for the domain', 'data' => $response]);
        }

        return json_encode(['code' => 'SUCCESS', 'message' => 'Credits retrieved successfully', 'data' => [
                'api_key' => $api_key,
                'returned_api_key' => $api_key,
                'credits' => $credits,
                'domains' => $domains
            ]
        ]);
    }
}