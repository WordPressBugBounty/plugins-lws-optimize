<?php

namespace Lws\Classes\Images;

class LwsOptimizeImageOptimization
{
    public $mimetypes;

    public function __construct($autoupdate = false)
    {
        global $wp_version;

        if ($autoupdate) {
            add_filter('wp_handle_upload_prefilter', [$this, 'lws_optimize_custom_upload_filter']);
        }

        $this->mimetypes = [
            'webp' => "WebP",
            'jpeg' => "JPEG",
            'png'  => "PNG",
        ];

        if (class_exists("Imagick")) {
            $img = new \Imagick();
            $supported_formats = $img->queryFormats();

            if (floatval($wp_version) > 6.5 && in_array("AVIF", $supported_formats)) {
                $this->mimetypes = array_merge(['avif' => "AVIF"], $this->mimetypes);
            }
        }

        add_filter('the_content', [$this, 'replace_images_with_newtype']);
        add_filter('wp_filter_content_tags', [$this, 'replace_images_with_newtype']);
        add_filter('post_thumbnail_html', [$this, 'replace_images_with_newtype']);
    }

    /**
     * Hijack the uploading process to create a new version of the given $file
     */
    public function lws_optimize_custom_upload_filter($file)
    {
        $timer = microtime(true);
        // Get the chosen mime-type from the database. If none found, default to webp convertion
        $config_array = get_option('lws_optimize_config_array', ['auto_update' => [
            'state' => false,
            'auto_convertion_format' => "auto",
            'auto_convertion_quality' => "balanced",
            'auto_image_format' => [],
            'auto_image_maxsize' => 2560,
            'auto_convertion_exceptions' => [],
        ]]);

        // Return the normal data if the auto_update is unset or false
        if ($config_array == null || !isset($config_array['auto_update']['state']) || $config_array['auto_update']['state'] == "false") {
            return $file;
        }

        // Get the accepted Mime-types ; Only images from this array can be converted
        // if nothing is present, do not convert
        $convertion_type = $config_array['auto_update']['auto_image_format'] ?? [];
        if (empty($convertion_type) || !is_array($convertion_type)) {
            return $file;
        }

        if (in_array("jpg", $convertion_type) && !in_array("jpeg", $convertion_type)) {
            $convertion_type[] = "jpeg";
        }
        if (in_array("jpeg", $convertion_type) && !in_array("jpg", $convertion_type)) {
            $convertion_type[] = "jpg";
        }


        $quality = $config_array['auto_update']['auto_convertion_quality'] ?? "balanced";
        switch ($quality) {
            case 'balanced':
                $quality = 64;
                break;
            case 'low':
                $quality = 30;
                break;
            case 'high':
                $quality = 90;
                break;
            default:
                $quality = 64;
                break;
        }

        $convert_type = $config_array['auto_update']['auto_convertion_format'] ?? "auto";


        // Only convert if the file type is image ; otherwise just return the untouched $file array
        if (substr($file['type'], 0, 5) === "image") {

            // If AVIF, do not convert large files
            if ($file['size'] / 1000 > 402 && $convert_type == "avif") {
                return $file;
            }

            // Create a new version of the image in the new image type and overwrite the original file
            // On error, give up on the convertion
            if (!$this->lws_optimize_convert_image($file['tmp_name'], $file['tmp_name'], $quality, $file['type'], "image/$convert_type", $convertion_type)) {
                $failed_convertion = get_option('lws_optimize_autooptimize_errors', []);
                $failed_convertion[] = ['error_type' => "AUTOCONVERT", 'time' => time(), 'quality' => $quality, 'type' => $file['type'], 'convert' => $convert_type, 'file' => $file['name']];
                update_option('lws_optimize_autooptimize_errors', $failed_convertion);

                error_log(json_encode(['code' => 'CONVERT_FAIL', 'message' => 'File optimisation has failed.', 'data' => $file, 'time' => microtime(true) - $timer]));
                return $file;
            }


            // The image has been excluded and should not be converted
            if (in_array($file['name'], $config_array['auto_update']['auto_convertion_exceptions'])) {
                return $file;
            }

            // Get the original type of the image to add it in the name
            // This will make it easier to convert back to the original typing
            $tmp = explode("/", $file['type']);
            $starting_type = $tmp[0] == "image" ? $tmp[1] : null;
            if ($starting_type === null) {
                $failed_convertion = get_option('lws_optimize_autooptimize_errors', []);
                $failed_convertion[] = ['error_type' => "AUTOCONVERT", 'time' => time(), 'quality' => $quality, 'type' => $file['type'], 'convert' => $convert_type, 'file' => $file['name']];
                update_option('lws_optimize_autooptimize_errors', $failed_convertion);

                error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid.', 'data' => $file, 'time' => microtime(true) - $timer]));
                return $file;
            }

            // Add the new extension on top of the current one (e.g. : Flowers.png => Flowers.png.webp)
            // Also, check for the new filesize of the file and update the value

            // Replace the name and PATH to remove the old extension
            $output_name = $file['name'];
            $tmp = explode('.', $output_name);
            array_pop($tmp);
            $output_name = implode('.', $tmp) . ".$convert_type";

            $output_path = $file['full_path'];
            $tmp = explode('.', $output_path);
            array_pop($tmp);
            $output_path = implode('.', $tmp) . ".$convert_type";

            // Update data
            $file['type'] = "image/$convert_type";
            $file['name'] = "$output_name";
            $file['full_path'] = "$output_path";

            // Get and update de filesize of the file
            $size = filesize($file['tmp_name']);
            if ($size) {
                $file['size'] = $size;
            }

            return $file;
        }

        return $file;
    }

    /**
     * Create a copy of the given $image, convert it to the $end type from the current $origin.
     * The image will then be saved to $output. $output and $image can be the same to replace the image.
     *
     * @param string $image The PATH to the image to convert
     * @param string $origin The mime-type in which the image currently is. Format : image/png
     * @param string $end The mime-type in which the image needs to be converted. Format : image/webp
     * @param string $output The PATH where to save the newly converted image
     *
     * @return bool Either true on success or false on error
     */
    public function lws_optimize_convert_image(string $image, string $output, int $quality = 64, string $origin = "jpeg", string $end = "webp", $max_size = 2560)
    {
        try {
            $timer = microtime(true);

            // Abort if any parameters are null
            if ($image === null || $origin === null || $end === null || $output === null) {
                error_log(json_encode(['code' => 'NO_PARAMETERS', 'message' => 'No/missing parameters. Cannot proceed.', 'data' => null, 'time' => microtime(true) - $timer]));
                return false;
            }

            // Abort if the Imagick class is not installed
            if (!class_exists("Imagick")) {
                error_log(json_encode(['code' => 'IMAGICK_NOT_FOUND', 'message' => 'Imagick was not found on this server. This plugin relies on Imagick to optimize images and cannot work without.', 'data' => null, 'time' => microtime(true) - $timer]));
                return false;
            }

            // Create an Imagick instance to create the new image
            $img = new \Imagick();
            // Get the list of all image format supported by Imagick. The most likely at present of not being found is AVIF
            // but we check and abort if the type is not supported
            $supported_formats = $img->queryFormats();

            // Get the image type of the given image (and make sure it IS a image/)
            $tmp = explode("/", $origin);
            $starting_type = $tmp[0] == "image" ? $tmp[1] : $tmp[0];
            if ($starting_type === null) {
                error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid.', 'data' => $origin, 'time' => microtime(true) - $timer]));
                return false;
            }

            // Get the image type into which the image needs to be converted
            $tmp = explode("/", $end);
            $ending_type = $tmp[0] == "image" ? $tmp[1] : $tmp[0];

            if ($ending_type === null) {
                error_log(json_encode(['code' => 'INVALID_DESTINATION', 'message' => 'Destination type is not an image or mime-type is invalid.', 'data' => $end, 'time' => microtime(true) - $timer]));
                return false;
            }

            // If the current image type or the wanted image type are not supported by this version of Imagick, then abort
            if (!in_array(strtoupper($starting_type), $supported_formats) || !in_array(strtoupper($ending_type), $supported_formats)) {
                error_log(json_encode(['code' => 'UNSUPPORTED_FORMAT', 'message' => 'Selected image type is not usable with this version of Imagick. Either choose another type or update to a newer Imagick version.', 'data' => ['origin' => in_array(strtoupper($starting_type), $supported_formats), 'destination' => in_array(strtoupper($ending_type), $supported_formats)], 'time' => microtime(true) - $timer]));
                return false;
            }

            // Try to read the given image ; If it fails, the image may be corrupted
            if (!$img->readImage($image)) {
                error_log(json_encode(['code' => 'IMAGE_UNREADABLE', 'message' => 'Could not read given image. Make sure the image exists and is readable.', 'data' => $image, 'time' => microtime(true) - $timer]));
                return false;
            }

            // Get current dimensions
            $width = $img->getImageWidth();
            $height = $img->getImageHeight();

            // Check if the image width exceeds the maximum width
            if ($width > $max_size) {
                // Calculate the new dimensions while maintaining the aspect ratio
                $newHeight = ($max_size / $width) * $height;

                // Resize the image
                $img->resizeImage($max_size, $newHeight, \Imagick::FILTER_LANCZOS, 1);
            }

            // Change the compression quality of the new image. Between 0-100, 100 is better
            // By default set to 75/100
            $img->setImageCompressionQuality($quality);
            if (!$img->setImageFormat($ending_type)) {
                error_log(json_encode(['code' => 'CONVERTION_FAIL', 'message' => 'Could not convert the image into the given type.', 'data' => ['image' => $image, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
                return false;
            }

            // Create the new image $img
            // If the first time fail, try again using another function. If if fails again, abort
            try {
                if (!$img->writeImage($output)) {
                    error_log(json_encode(['code' => 'WRITE_FAIL', 'message' => 'Failed to write the new image using writeImage', 'data' => ['path' => $output, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
                    if (!$img->writeImageFile(fopen($output, "wb"))) {
                        error_log(json_encode(['code' => 'WRITE_IMAGE_FAIL', 'message' => 'Failed to write the new image using writeImageFile. Abort.', 'data' => ['path' => $output, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
                        return false;
                    }
                }
            } catch (\Exception $e) {
                error_log(json_encode(['code' => 'UNKNOWN_FUNCTION', 'message' => 'Imagick::writeImage or Imagick::writeImageFile not found. Abort.', 'data' => ['path' => $output, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
                return false;
            }

            // Clean up resources
            $img->clear();
            $img->destroy();

            return true;
        } catch (\Exception $e) {
            error_log(json_encode(['code' => 'UNKNOWN', 'message' => $e->getMessage(), 'data' => func_get_args(), 'time' => microtime(true) - $timer]));
            return false;
        }
    }

    public function lws_optimize_refresh_attachments()
    {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => null,
            'post_parent' => null
        );
        $attachments = get_posts($args);
        foreach ($attachments as $attachment) {
            $filepath = get_attached_file($attachment->ID);
            wp_generate_attachment_metadata($attachment->ID, $filepath);
        }
    }

    public function convert_all_medias($quality = "balanced", $amount_per_run = 10, $max_size = 2560)
    {
        global $wpdb;

        // Get all images to convert
        $images_to_convert = get_option('lws_optimize_images_convertion', []);

        // Counter of attachments that successfully got converted
        $converted = 0;

        // Get the maximum amount of successful convertion per run
        $amount_per_run = intval($amount_per_run);
        if ($amount_per_run < 0 || $amount_per_run > 20) {
            $amount_per_run = 10;
        }

        // Assure the quality will always be an int, no matter what
        // Also always between 1 and 100
        switch ($quality) {
            case 'balanced':
                $quality = 64;
                break;
            case 'low':
                $quality = 30;
                break;
            case 'high':
                $quality = 90;
                break;
            default:
                $quality = 64;
                break;
        }

        foreach ($images_to_convert as $id => $image) {
            if ($converted >= $amount_per_run) {
                break;
            }

            if ($image['converted']) {
                // If the original file does not exist, we remove the file from the convertion
                if (!file_exists($image['original_path'])) {
                    unset($image[$id]);
                    continue;
                }

                // If we can't find the converted image, then it is not converted
                if (!file_exists($image['path'])) {
                    $image['converted'] = false;
                } else {
                    continue;
                }
            }

            // The file is not considered converted but a converted already exists
            // We remove the file already there
            if (file_exists($image['path'])) {
                unlink($image['path']);
            }

            // User want us to choose which, between original, AVIF and WebP, is the best for the given image
            if (isset($image['extension']) && $image['extension'] == "auto") {
                if ($image['avif_capability'] && (filesize($image['original_path'])  / 1000) <= 402) {
                    $best_type = $this->check_best_type($image['original_path'], $image['original_extension'], $quality, ['webp']);
                } else {
                    $best_type = $this->check_best_type($image['original_path'], $image['original_extension'], $quality, ['webp']);
                }

                // Remove the current extension and replace it with the one to convert into
                $attachment_url_converted = explode('.', $image['original_url']);
                array_pop($attachment_url_converted);
                $attachment_url_converted = implode('.', $attachment_url_converted) . ".$best_type";

                $attachment_path_converted = explode('.', $image['original_path']);
                array_pop($attachment_path_converted);
                $attachment_path_converted = implode('.', $attachment_path_converted) . ".$best_type";

                $image['url'] = $attachment_url_converted;
                $image['path'] = $attachment_path_converted;
                $image['mime'] = "image/$best_type";
                $image['extension'] = $best_type;
            }

            // Get the metadata of the file
            $metadata = wp_get_attachment_metadata($id);


            // $image_width = $metadata['width'];
            // $image_height = $metadata['height'];

            // // Check if the image width exceeds the maximum width
            // if ($image_width > $max_size) {
            //     // Calculate the new dimensions while maintaining the aspect ratio
            //     $image_height = ($max_size / $image_width) * $image_height;

            //     $metadata['width'] = $image_width;
            //     $metadata['height'] = $image_height;
            // }

            $size_to_remove = [];

            if (isset($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $sizes) {
                    foreach ($sizes as $key => $type_img) {
                        if ($key == "file") {

                            // Get the original file of the current size and remove it
                            $filename = explode('/', $image['original_path']);
                            array_pop($filename);
                            $filename = implode('/', $filename) . "/$type_img";
                            $size_to_remove[] = $filename;

                            // Create the URL to the current size
                            $url = explode('/', $image['original_url']);
                            array_pop($url);
                            $url = implode('/', $url) . "/$type_img";

                            // Create the URL to the current size with the new MIME-Type
                            $new_url = explode('.', $url);
                            array_pop($new_url);
                            $new_url = implode(".", $new_url) . ".{$image['extension']}";
                        }
                    }
                }
            }

            // Convert image to WebP using your preferred library (e.g., GD, Imagick)
            $created = $this->lws_optimize_convert_image($image['original_path'], $image['path'], $quality, $image['original_mime'], $image['mime'], $max_size);

            // No image created, an error occured
            if (!$created) {
                $images_to_convert[$id]['error_on_convertion'] = true;
                continue;
            }

            // Update the file sizes
            wp_update_attachment_metadata($id, $metadata);

            $images_to_convert[$id]['converted'] = true;
            $images_to_convert[$id]['date_convertion'] = time();
            $images_to_convert[$id]['compression'] = number_format((filesize($image['original_path']) - filesize($image['path'])) * 100 / filesize($image['original_path']), 2, ".", '') . "%" . esc_html__(' smaller', 'lws-optimize');
            $images_to_convert[$id]['size'] = filesize($image['path']);

            // Remove the original file if we do not keep it
            if (!$image['to_keep']) {
                unlink($image['original_path']);
                // Only remove the small sizes if the file got converted
                foreach($size_to_remove as $remove) {
                    if (file_exists($remove)) {
                        unlink($remove);
                    }
                }
            }

            // Change attachment data with new image and regenerate thumbnails
            $attachment = array(
                'ID' => $id,
                'post_mime_type' => $image['mime']
            );
            wp_insert_attachment($attachment, $image['path']);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $image['path']));

            $converted++;
        }

        update_option('lws_optimize_images_convertion', $images_to_convert);

        $stats = get_option('lws_optimize_current_convertion_stats', []);
        $stats['converted'] += $converted;
        update_option('lws_optimize_current_convertion_stats', $stats);

        return json_encode(array('code' => 'SUCCESS', 'data' => $stats, 'evolution' => $converted), JSON_PRETTY_PRINT);
    }

    /**
     * Take all images stored in the database ('lws_optimize_original_image') and revert them to their original state
     */
    public function revertOptimization()
    {
        global $wpdb;

        $state = [];
        $media_data = get_option('lws_optimize_images_convertion', []);

        if (empty($media_data)) {
            wp_unschedule_event(wp_next_scheduled("lwsop_revertOptimization"), "lwsop_revertOptimization");
        }

        $images = [];
        $done = 0;

        foreach ($media_data as $key => $media) {
            if ($done == 20) {
                break;
            }

            // Do not deconvert if it is not converted or has not been converted before
            if (!$media['converted'] && !$media['previously_converted']) {
                continue;
            }

            $base_path = $media['original_path'] ?? '';

            // If the original file does not exists anymore, we cannot revert it
            if (!file_exists($base_path)) {
                // We delete it from the database (it will then be considered originally in $type)
                unset($media_data[$key]);
                $state[] = ['id' => $key, 'state' => "NOT_EXISTS"];
                continue;
            }

            $metadata = wp_get_attachment_metadata($key);

            foreach ($metadata['sizes'] as $sizes) {
                foreach ($sizes as $key_file => $type) {
                    if ($key_file == "file") {

                        $tmp = explode('/', $base_path);
                        array_pop($tmp);
                        $file_name = implode('/', $tmp) . "/$type";
                        if (file_exists($file_name)) {
                            unlink($file_name);
                        }

                        $tmp = explode('/', $media['original_url']);
                        array_pop($tmp);

                        $actual_url = implode('/', $tmp) . "/$type";
                        $original_url = explode('.', $actual_url);
                        array_pop($original_url);
                        $original_url = implode('.', $original_url) . ".{$media['original_extension']}";

                        $images[] = ['original' => $original_url, 'current' => $actual_url];
                    }
                }
            }

            // Remove the converted file
            if (file_exists($media['path'])) {
                unlink($media['path']);
            }

            // Replace the attachment with the old data
            $attachment = array(
                'ID' => $key,
                'post_title' => $media['name'],
                'post_content' => '',
                'post_mime_type' => $media['original_mime'],
            );

            // Modify the attachment
            if (!wp_insert_attachment($attachment, $media['original_path'])) {
                $state[] = ['id' => $key, 'state' => "FAIL_INSERT_NEW", $attachment];
                continue;
            }

            wp_update_attachment_metadata($key, wp_generate_attachment_metadata($key, $base_path));
            $media_data[$key]['converted'] = false;
            $media_data[$key]['previously_converted'] = false;
            $state[] = ['id' => $key, 'state' => "REVERTED"];
            $done++;
        }

        update_option('lws_optimize_images_convertion', $media_data);
        return json_encode(array('code' => 'SUCCESS', 'data' => $state), JSON_PRETTY_PRINT);
    }

    public function regenerate_thumbnails_for_all_images()
    {
        // Get all attachments (images)
        $args = ['post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => -1, 'post_status' => 'inherit'];
        $attachments = get_posts($args);

        if ($attachments) {
            foreach ($attachments as $attachment) {
                $attachment_id = $attachment->ID;

                // Regenerate the thumbnail
                $fullsizepath = get_attached_file($attachment_id);
                if (false === $fullsizepath || !file_exists($fullsizepath)) {
                    continue;
                }

                // Generate the new sizes based on current settings
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $fullsizepath));
            }
        }
    }

    private function check_best_type(string $original_path = null, string $original_extension = null, int $quality = 64, array $types = ['webp', 'avif']) {
        try {
            $timer = microtime(true);

            // Default to WebP if we cannot proceed
            if ($original_path === null || $original_extension === null) {
                error_log(json_encode(['code' => 'NO_PARAMETERS', 'message' => 'No/missing parameters. Cannot proceed.', 'data' => null, 'time' => microtime(true) - $timer]));
                return "webp";
            }

            // Abort if the Imagick class is not installed
            if (!class_exists("Imagick")) {
                error_log(json_encode(['code' => 'IMAGICK_NOT_FOUND', 'message' => 'Imagick was not found on this server. This plugin relies on Imagick to optimize images and cannot work without.', 'data' => null, 'time' => microtime(true) - $timer]));
                return "webp";
            }

            // Create an Imagick instance to create the new image
            $img = new \Imagick();
            // Get the list of all image format supported by Imagick. The most likely at present of not being found is AVIF
            // but we check and abort if the type is not supported
            $supported_formats = $img->queryFormats();

            // If the current image type or the wanted image type are not supported by this version of Imagick, then abort
            if (!in_array(strtoupper($original_extension), $supported_formats)) {
                return "webp";
            }

            // Try to read the given image ; If it fails, the image may be corrupted
            if (!$img->readImage($original_path)) {
                return "webp";
            }

            // Change the compression quality of the new image. Between 0-100, 100 is better
            $img->setImageCompressionQuality($quality);

            $sizes = [$original_extension => filesize($original_path)];
            foreach ($types as $type) {
                if (!in_array(strtoupper($type), $supported_formats)) {
                    continue;
                }

                if (!$img->setImageFormat($type)) {
                    continue;
                }


                $temp = tmpfile();

                $meta_data = stream_get_meta_data($temp);
                $filename = $meta_data["uri"];

                if (!$img->writeImage($filename)) {
                    if (!$img->writeImageFile($temp, "wb")) {
                        continue;
                    }
                }

                $sizes[$type] = filesize($filename);
                fclose($temp);
            }

            // Clean up resources
            $img->clear();
            $img->destroy();

            // Return the most efficient format for the given image
            $lowest_size = min($sizes);
            return array_keys($sizes, $lowest_size)[0];


        } catch (\Exception $e) {
            error_log(json_encode(['code' => 'UNKNOWN', 'message' => $e->getMessage(), 'data' => func_get_args(), 'time' => microtime(true) - $timer]));
            return "webp";
        }
    }

    function replace_images_with_newtype($content) {
        // Get the format to change images into
        $convertion_data = get_option('lws_optimize_all_media_convertion', []);
        $type = $convertion_data['convertion_format'] ?? null;
        if ($type == null) {
            return $content;
        }

        // Use a regular expression to find all image URLs in the content
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $image_url) {
                // Get the file path from the URL
                $image_path = str_replace(home_url('/'), ABSPATH, $image_url);

                // Change the extension to .webp
                $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.' . $type, $image_path);

                // Check if the .webp file exists
                if (file_exists($webp_path)) {
                    // Replace the original image URL with the .webp URL
                    $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.' . $type, $image_url);
                    $content = str_replace($image_url, $webp_url, $content);
                }
            }
        }

        return $content;
    }
}
