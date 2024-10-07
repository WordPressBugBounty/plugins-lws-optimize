<?php

class ImageOptimization {

    public function __construct($autoupdate = false){
        if ($autoupdate) {
            add_filter('image_editor_output_format', 'filter_image_editor_output_format');
            add_filter('wp_handle_upload_prefilter', 'lws_optimize_custom_upload_filter');
        }
    }


    /** Change the thumbnails image format to $convert_type. Default to webP
     * AVIF only works with WordPress 6.5+
     */
    public function filter_image_editor_output_format($formats)
    {
        $config_array = get_option('lws_optimize_config_array', []);
        $convert_type = $config_array['media_optimize']['convert_type'] ?? NULL;
        if ($convert_type === NULL) {
            $convert_type = "webp";
        }

        $formats["image/jpeg"] = "image/$convert_type";
        $formats["image/png"] = "image/$convert_type";
        $formats["image/gif"] = "image/$convert_type";
        $formats["image/avif"] = "image/$convert_type";
        $formats["image/webp"] = "image/$convert_type";
        return $formats;
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
    public function lws_optimize_convert_image(string $image, string $output, int $quality = 75, string $origin = "jpeg", string $end = "webp")
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
        $starting_type = $tmp[0] == "image" ? $tmp[1] : $tmp[0];
        if ($starting_type === NULL) {
            error_log(json_encode(['code' => 'INVALID_ORIGIN', 'message' => 'Given file is not an image or mime-type is invalid.', 'data' => $origin, 'time' => microtime(true) - $timer]));
            return false;
        }
        

        // Get the image type into which the image needs to be converted
        $tmp = explode("/", $end);
        $ending_type = $tmp[0] == "image" ? $tmp[1] : $tmp[0];
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
        } catch (Exception $e) {
            error_log(json_encode(['code' => 'UNKNOWN_FUNCTION', 'message' => 'Imagick::writeImage or Imagick::writeImageFile not found. Abort.', 'data' => ['path' => $output, 'type' => $ending_type], 'time' => microtime(true) - $timer]));
            return false;
        }

        return true;
    }

    /**
     * Hijack the uploading process to create a new version of the given $file
     * 
     */
    public function lws_optimize_custom_upload_filter($file)
    {
        $timer = microtime(true);
        // Get the chosen mime-type from the database. If none found, default to webp convertion
        $config_array = get_option('lws_optimize_config_array', []);
        $convert_type = $config_array['media_optimize']['convert_type'] ?? NULL;
        $keep_copy = $config_array['media_optimize']['keep_copy'] ?? NULL;
        if ($convert_type === NULL) {
            $convert_type = "webp";
        }
        if ($keep_copy === NULL) {
            $keep_copy = true;
        }

        $output_path = $file['tmp_name'];
        if ($keep_copy) {
            $output_path = "{$file['tmp_name']}.{$file['type']}";
        }

        // Only convert if the file type is image ; otherwise just return the untouched $file array
        if (substr($file['type'], 0, 5) === "image") {
            // Create a new version of the image in the new image type and overwrite the original file
            // On error, give up on the convertion
            if (!$this->lws_optimize_convert_image($file['tmp_name'], $output_path, $file['type'], "image/$convert_type")) {
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
            $size = filesize($output_path);
            $file['type'] = "image/$convert_type";
            $file['name'] .= ".$convert_type";
            $file['full_path'] .= ".$convert_type";
            if ($size) {
                $file['size'] = $size;
            }

            return $file;
        }

        return $file;
    }

    public function lws_optimize_refresh_attachments()
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

    public function convert_all_medias($type = "webp", $quality = 75, $keepcopy = true, $exceptions = [], $amount_per_run = 10) {
        global $wpdb;

        $done_attachments = 0;

        // Get all image attachments
        $args = array(
            'number_posts' => $amount_per_run,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
        );

        // Assure the quality will always be an int, no matter what
        // Also always between 1 and 100
        $quality = intval($quality);
        if ($quality < 1 || $quality > 100) {
            $quality = 75;
        }
        
        // Update each attachment data to reflect the new mime-type
        // Also create a new image with reduced quality to replace the current one
        $attachments = get_posts($args);
        foreach ($attachments as $attachment) {
            // Invalid attachment, continue onto the next
            if ($attachment->ID == null) {
                error_log("LWSOptimize | ImageOptimization | Attachment ID not found");
                continue;
            }

            // Get the URL to the attachment
            $attachment_url = wp_get_attachment_url($attachment->ID);
            // The URL was not found, continue onto the next
            if ($attachment_url == false) {
                error_log("LWSOptimize | ImageOptimization | Attachment not found : " . $attachment->ID);
                continue;
            }

            // Get PATH to the current attachment
            $full_size_path = get_attached_file($attachment->ID);
            // The attachment does not exists, continue onto the next
            if (!file_exists($full_size_path)) {
                error_log("LWSOptimize | ImageOptimization | File does not exists for attachment " . $attachment->ID);
                continue;
            }

            // Break the PATH in multiple parts
            $file_info = pathinfo($full_size_path);
            $filename = $file_info['filename'] ?? NULL;
            $basename = $file_info['basename'] ?? NULL;
            $dirname = $file_info['dirname'] ?? NULL;
            $extension = $file_info['extension'] ?? NULL;

            // Should not be touched, is an exception
            if (in_array($filename, $exceptions)) {
                continue;
            }

            // Cannot get PATH, continue onto the next
            if ($filename == NULL || $dirname == NULL || $extension == NULL) {
                error_log("LWSOptimize | ImageOptimization | No PATH for the attachment " . $attachment->ID);
                continue;
            }

            // Do not convert images that already are in the $type MIME
            if ($extension == $type) {
                continue;
            }
            
            $webp_path = "$dirname/$filename.$type";
            $webp_url = str_replace(".$extension", ".$type", $attachment_url);

            if ($keepcopy) {
                $original_data_array['media_optimize']['original_media'][$attachment->ID] = [
                    'original_url' => $attachment_url,
                    'original_path' => $full_size_path,
                    'original_name' => $filename,
                    'original_mime' => $extension,
                    'url' => $webp_url,
                    'path' => $webp_path,
                    'mime' => "image/$type",
                    'data' => $attachment,
                ];
            }

            // Convert image to WebP using your preferred library (e.g., GD, Imagick)
            $created = $this->lws_optimize_convert_image($full_size_path, $webp_path, $quality, $extension, $type);

            // No image created, an error occured
            if (!$created) {
                error_log("LWSOptimize | ImageOptimization | Converted version of $filename.$extension could not be created");
                continue;
            }
            

            // Change attachment data with new image
            $attachment = array(
                'ID' => $attachment->ID,
                'post_title' => $filename,
                'post_content' => '',
                'post_mime_type' => "image/$type",
                'post_parent' => $attachment->post_parent,
                'guid' => $webp_url,
            );

            // Create the new attachment
            $attach_id = wp_insert_attachment($attachment, $webp_path);
            wp_generate_attachment_metadata($attach_id, $webp_path);



            // Replace image URLs in post content (optimize this for performance)
            $args = array(
                'post_type' => 'any',
            );

            $posts = get_posts($args);
            foreach ($posts as $post) {
                $content = str_replace($attachment_url, $webp_url, $post->post_content);
                $data = [ 'post_content' => $content ];
                $where = [ 'ID' => $post->ID ];
                $wpdb->update( $wpdb->prefix . 'posts', $data, $where );
            }

            $done_attachments++;
        }

        if (!empty($original_data_array)) {
            update_option('lws_optimize_original_image', $original_data_array);
        }

        return json_encode(array('code' => 'SUCCESS', 'data' => "$done_attachments/$amount_per_run", 'done' => $done_attachments), JSON_PRETTY_PRINT);
    }

    /**
     * Take all images stored in the database ('lws_optimize_original_image') and 
     */
    public function revertOptimization() {
        global $wpdb;
    
        $state = [];
        $original_data_array = get_option('lws_optimize_original_image', []);
        $media_data = $original_data_array['media_optimize']['original_media'] ?? [];

        foreach ($media_data as $key => $media) {
            $base_path = $media['original_path'] ?? '';
            // If the original file does not exists anymore, we cannot revert it
            if (!file_exists($base_path)) {
                // We delete it from the database
                unset($original_data_array['media_optimize']['original_media'][$key]);
                $state[] = ['id' => $key, 'state' => "NOT_EXISTS"];
                continue;
            }

            // Replace the attachment with the old data
            $attachment = array(
                'ID' => $key,
                'post_title' => $media['original_name'],
                'post_content' => '',
                'post_mime_type' => "image/{$media['original_mime']}",
                'post_parent' => $media['data']->post_parent,
                'guid' => $media['data']->guid,
            );

            // Modify the attachment
            if ($attach_id = wp_insert_attachment($attachment, $media['original_path']) === false) {
                $state[] = ['id' => $key, 'state' => "FAIL_INSERT_NEW"];
                continue;
            }
            wp_generate_attachment_metadata($attach_id, $media['original_path']);

            // Replace image URLs in post content (optimize this for performance)
            $args = array(
                'post_type' => 'any',
            );

            $posts = get_posts($args);
            foreach ($posts as $post) {
                $content = str_replace($media['url'], $media['original_url'], $post->post_content);
                $data = [ 'post_content' => $content ];
                $where = [ 'ID' => $post->ID ];
                $wpdb->update( $wpdb->prefix . 'posts', $data, $where );
            }

            unset($original_data_array['media_optimize']['original_media'][$key]);
            $state[] = ['id' => $key, 'state' => "REVERTED"];
        }

        update_option('lws_optimize_original_image', $original_data_array);
        return $state;
    }
}

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


/**
 * 
 * Choix entre optimiser les images (garde le même type, juste qualité réduite), convertir en webP ou convertir en AVIF
 * Possibilité de garder, ou non, les originaux
 * Si gardé, fichier nommé [original].[ext].avif
 * Sinon, fichier nommé [original].avif
 * 
 * Stocker dans BDD les infos sur quelles images sont originales ou non
 * si désactivé, l'optimisation ne supprime *pas* les webp/avif, seulement à l'appui sur un bouton. 
 * si les originales ont été supprimées, désactiver/supprimer les optis ne les supprime pas (i.e. elles ne sont plus considérées comme des optimisations)
 * 
 * A la suppression du média, retirer les originales si disponibles
 */

/**
 * Ajouter une option dans wp_options contenant le listing de toutes les PATHs vers les images originales ainsi que le PATH vers la nouvelle image
 * Lors de la désactivation de la convertion, liste les images originales de la base, retrouve la copie et la supprime puis remet les données de l'originale
 */