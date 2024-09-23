<?php


// function lws_op_delete_webp_files()
// {
//     global $wpdb;
//     $args = array(
//         'numberposts' => -1,
//         'post_type' => 'attachment',
//     );
//     $attachments = get_posts($args);

//     foreach ($attachments as $a) {
//         if (explode('/', $a->post_mime_type)[0] == 'image' && $a->post_mime_type == 'image/webp') {
//             $id = $a->ID;
//             $path = get_attached_file($id);
//             $filename = basename($path);
//             $extension = explode('.', $filename);
//             array_pop($extension);

//             $new_path = implode('.', $extension);
//             $extension = end($extension);
//             $dirname = dirname($path);
//             $new_path = $dirname . '/' . $new_path;

//             if (file_exists($new_path)) {
//                 rename($path . '.old', $path);
//                 unlink($path);

//                 $meta_value = $wpdb->get_var(
//                     $wpdb->prepare(
//                         "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
//                         $id
//                     )
//                 );
//                 $meta_value = explode('.', $meta_value);
//                 array_pop($meta_value);
//                 $meta_value = implode('.', $meta_value);
//                 wp_update_post(array('ID' => $id, 'post_mime_type' => 'image/' . $extension));
//                 $wpdb->query(
//                     $wpdb->prepare(
//                         "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = '_wp_attached_file'",
//                         $meta_value,
//                         $id
//                     )
//                 );
//             }

//             require_once(ABSPATH . 'wp-admin/includes/image.php');

//             if ($new_path && file_exists($new_path)) {
//                 wp_generate_attachment_metadata($id, $new_path);
//             }
//         }
//     }

//     lws_op_update_posts_webp("restore");
// }
// function lws_op_uncompress_all_to_jpeg()
// {
//     $args = array(
//         'numberposts' => -1,
//         'post_type' => 'attachment',
//     );
//     $attachments = get_posts($args);
//     foreach ($attachments as $a) {
//         if (explode('/', $a->post_mime_type)[0] == 'image') {
//             $metadata = wp_get_attachment_metadata($a->ID);
//             $path = get_attached_file($a->ID);
//             if (file_exists($path . '.old')) {
//                 @rename($path . '.old', $path);
//                 // if ($a->post_mime_type == 'image/webp') {
//                 //     $tmp = explode('.', $path);
//                 //     array_pop($tmp);
//                 //     $tmp = implode('.', $tmp);
//                 //     @rename($tmp. '.old', $tmp);
//                 // }

//                 foreach ($metadata['sizes'] as $size => $data) {
//                     $size_path = explode('/', $path);
//                     $size_path = implode('/', array_replace($size_path, [(count($size_path) - 1) => $data['file']]));
//                     @rename($size_path . '.old', $size_path);
//                     //if ($a->post_mime_type == 'image/webp') {
//                     //     $tmp = explode('.', $size_path);
//                     //     array_pop($tmp);
//                     //     $tmp = implode('.', $tmp);
//                     //     @rename($tmp. '.old', $tmp);
//                     // }
//                 }
//             }
//         }
//     }
// }


// Output AVIFs for uploaded JPEGs (only for the smaller images, not the original one)
// Only works with WordPress 6.5+
function filter_image_editor_output_format($formats)
{
    // $formats['image/jpeg'] = 'image/avif';
    $formats['image/jpeg'] = 'image/webp';
    return $formats;
}
// add_filter('image_editor_output_format', 'filter_image_editor_output_format');

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
function lws_optimize_convert_image(string $image, string $output, string $origin = "jpeg", string $end = "webp")
{
    $timer = microtime(true);
    // Abort if any parameters are null
    if ($image === NULL || $origin === NULL || $end === NULL || $output === NULL) {
        error_log(json_encode(['code' => 'NO_PARAMETERS', 'message' => 'No/missing parameters. Cannot proceed.', 'data' => NULL, 'time' => microtime(true) - $timer]));
        return false;
    }

    // Abort if the Imagick class is not installed
    if (!class_exists("Imagick")) {
        error_log(json_encode(['code' => 'IMAGICK_NOT_FOUND', 'message' => 'Imagick was not found on this server. This plugin relies on Imagick to optimize images and cannot work without.', 'data' => NULL, 'time' => microtime(true) - $timer]));
        return false;
    }

    // Create an Imagick instance to create the new image
    $img = new Imagick();
    // Get the list of all image format supported by Imagick. The most likely at present of not being found is AVIF
    // but we check and abort if the type is not supported
    $supported_formats = $img->queryFormats();

    // Get the image type of the given image (and make sure it IS a image/)
    $tmp = explode("/", $origin);
    $starting_type = $tmp[0] == "image" ? $tmp[1] : NULL;
    if ($starting_type === NULL) {
        error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid.', 'data' => $origin, 'time' => microtime(true) - $timer]));
        return false;
    }

    // Get the image type into which the image needs to be converted
    $tmp = explode("/", $end);
    $ending_type = $tmp[0] == "image" ? $tmp[1] : NULL;
    if ($ending_type === NULL) {
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

    // Change the compression quality of the new image. Between 0-100, 100 is better
    // By default set to 75/100
    $img->setImageCompressionQuality(75);
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
    } catch (Exception $e) {
        error_log(json_encode(['code' => 'UNKNOWN_FUNCTION', 'message' => 'Imagick::writeImage or Imagick::writeImageFile not found. Abort.', 'data' => ['path' => $output, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
        return false;
    }

    return true;
}

// add_filter('wp_handle_upload_prefilter', 'lws_optimize_custom_upload_filter');
function lws_optimize_custom_upload_filter($file)
{
    $timer = microtime(true);
    // Get the chosen mime-type from the database. If none found, default to webp convertion
    $config_array = get_option('lws_optimize_config_array', []);
    $convert_type = $config_array['media_optimize']['convert_type'] ?? NULL;
    if ($convert_type === NULL) {
        $convert_type = "webp";
    }

    // Only convert if the file type is image ; otherwise just return the untouched $file array
    if (substr($file['type'], 0, 5) === "image") {
        // Create a new version of the image in the new image type and overwrite the original file
        // On error, give up on the convertion
        if (!lws_optimize_convert_image($file['tmp_name'], $file['type'], "image/{$convert_type}", $file['tmp_name'])) {
            error_log(json_encode(['code' => 'CONVERT_FAIL', 'message' => 'File optimisation has failed.', 'data' => $file, 'time' => microtime(true) - $timer]));
            return $file;
        }

        // Get the original type of the image to add it in the name 
        // This will make it easier to convert back to the original typing
        $tmp = explode("/", $file['type']);
        $starting_type = $tmp[0] == "image" ? $tmp[1] : NULL;
        if ($starting_type === NULL) {
            error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid.', 'data' => $file, 'time' => microtime(true) - $timer]));
            return $file;
        }

        // Add the new extension on top of the current one (e.g. : Flowers.png => Flowers.png.webp)
        // This will make it easier to convert back to the original typing
        // Also, check for the new filesize of the file and update the value
        $size = filesize($file['full_path']);
        $file['type'] = "image/avif";
        $file['name'] .= $convert_type;
        $file['full_path'] .= $convert_type;
        if ($size) {
            $file['size'] = $size;
        }

        return $file;
    }

    return $file;
}

function lws_optimize_refresh_attachments()
{
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'numberposts' => -1,
        'post_status' => null,
        'post_parent' => null, // any parent
    );
    $attachments = get_posts($args);
    foreach ($attachments as $attachment) {
        $filepath = get_attached_file($attachment->ID);
        wp_generate_attachment_metadata($attachment->ID, $filepath);
    }
}

// add_filter('init', function() {
//     if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
//         include( ABSPATH . 'wp-admin/includes/image.php' );
//     }
//     $args = array(
//         'post_type' => 'attachment',
//         'post_mime_type' => 'image',
//         'numberposts' => -1,
//         'post_status' => null,
//         'post_parent' => null, // any parent
//     );         
//     $attachments = get_posts($args);
//     foreach ($attachments as $attachment) {
//         $filepath = get_attached_file($attachment->ID);
//         wp_generate_attachment_metadata($attachment->ID, $filepath);
//     }

// });
