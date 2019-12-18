<?php

defined('ABSPATH') || exit;

if( !class_exists('MOS_WC_Files') ) :
    class MOS_WC_Files {
        private static $wpUploadDir;

        /**
         * Init.
         */
        public static function init() {
            self::$wpUploadDir = wp_get_upload_dir();

            add_action('ajax_query_attachments_args', __CLASS__ .'::hideFilesFromWPMedia');            
            add_action('wp_ajax_nopriv_ajaxUpload', __CLASS__ .'::ajaxUpload');
            add_action('wp_ajax_ajaxUpload', __CLASS__ .'::ajaxUpload');
            add_action('wp_ajax_nopriv_ajaxGetUploads', __CLASS__ .'::ajaxGetUploads');
            add_action('wp_ajax_ajaxGetUploads', __CLASS__ .'::ajaxGetUploads');
            add_action('wp_ajax_nopriv_ajaxDeleteUpload', __CLASS__ .'::ajaxDeleteUpload');
            add_action('wp_ajax_ajaxDeleteUpload', __CLASS__ .'::ajaxDeleteUpload');
            add_action('wp_ajax_nopriv_ajaxRestoreUpload', __CLASS__ .'::ajaxRestoreUpload');
            add_action('wp_ajax_ajaxRestoreUpload', __CLASS__ .'::ajaxRestoreUpload');
        }

        /**
         * Get mos file IDs.
         * 
         * @return array
         */
        public static function getFileIDs($attached = FALSE, $status = 'inherit', $folder = FALSE) {
            global $wpdb;
            $fileIDs = [];
            
            $args = "SELECT ID FROM ". $wpdb->posts;
            $args .= " LEFT JOIN ". $wpdb->postmeta;
            $args .= " ON (". $wpdb->posts .".ID = ". $wpdb->postmeta .".post_id)";
            $args .= " WHERE ". $wpdb->posts .".post_type = 'attachment'";
            $args .= " AND ". $wpdb->posts .".post_status = '". $status ."'";
            $args .= " AND (". $wpdb->postmeta .".meta_key = '_mos_file' AND ". $wpdb->postmeta .".meta_value = 1)";

            if( $attached ) :
                $args .= " AND (". $wpdb->postmeta .".meta_key = '_mos_file_attached' AND ". $wpdb->postmeta .".meta_value = '". $attached ."')";
            endif;

            if( $folder !== FALSE ) :
                $args .= " AND (". $wpdb->postmeta .".meta_key = '_mos_file_folder' AND ". $wpdb->postmeta .".meta_value = '". $folder ."')";
            endif;

            $results = $wpdb->get_results($args);

            if( $results ) :
                foreach( $results as $result ) :
                    $fileIDs[] = $result->ID;
                endforeach;
            endif;

            return $fileIDs;
        }

        /**
         * Hide files from media.
         * 
         * @param array    Query args
         * 
         * @return array    Query args
         */
        public static function hideFilesFromWPMedia($query) {
            if( $query['post_type'] != 'attachment' ) :
                return $query;
            endif;

            $query['post__not_in'] = self::getFileIDs();

            return $query;
        }

        /**
         * Get file.
         * 
         * @param int       File ID.
         * @param mixed     Custom status.
         * 
         * @return array
         */
        public static function getFile($id, $customStatus = FALSE) {
            $file = get_post($id);

            if( $file ) :
                $fileMeta = wp_get_attachment_metadata($file->ID);

                return [
                    'fileID'    => $file->ID,
                    'title'     => $file->post_title,
                    'name'      => $file->post_name,
                    'url'       => wp_get_attachment_image_url($file->ID),
                    'dir'       => get_post_meta($file->ID, '_mos_file_folder', TRUE),
                    'type'      => '',
                    'size'      => filesize(self::$wpUploadDir['basedir'] . $fileMeta['size']['file']),
                    'status'    => !$customStatus ? $file->post_status : $customStatus,
                    'meta'      => $fileMeta
                ];
            else :
                return FALSE;
            endif;
        }

        /**
         * Get folders.
         * 
         * @return array 
         */
        public static function getFolders() {
            global $wpdb;

            $folders = [];

            $args = "SELECT meta_value FROM ". $wpdb->postmeta;
            $args .= " WHERE ". $wpdb->postmeta .".meta_key = '_mos_file_folder'";
            $args .= " AND NOT ". $wpdb->postmeta .".meta_value = ''";

            $results = $wpdb->get_results($args);

            if( $results ) :
                foreach( $results as $result ) :
                    if( !in_array($result->meta_value, $folders) ) :
                        $folders[] = $result->meta_value;
                    endif;
                endforeach;
            endif;

            return $folders;
        }

        /**
         * @param string    Filename.
         * @param int       Attachment ID.
         * 
         * @return void
         */
        public static function findAndUpdateProductsImageByFilename($filename, $attachmentID) {
            $wcProducts = wc_get_products([
                'limit'     => -1
            ]);

            if( !$wcProducts )
                return;

            $delimiters = MOS_WC_Settings::getOption('delimiters');
            $delimitersPattern = "/(";
            if( $delimiters ) :
                foreach( $delimiters as $delimiter ) :
                    $delimitersPattern .= "\\". $delimiter ."|";
                endforeach;
            endif;
            $delimitersPattern = rtrim($delimitersPattern, "|") .")/";
            $name = preg_split($delimitersPattern, $filename);

            if( MOS_WC_Settings::getOption('findFirst') == 'true' ) :
                $name = $name[0];
            endif;

            $args = "SELECT ID FROM ". $wpdb->posts;
            $args .= " LEFT JOIN ". $wpdb->postmeta;
            $args .= " ON (". $wpdb->posts .".ID = ". $wpdb->postmeta .".post_id)";
            $args .= " WHERE ". $wpdb->posts .".post_type = 'product'";
            $args .= " AND ". $wpdb->postmeta .".meta_key = '_supplier_part_number'";
            if( is_array($name) ) :
                $args .= " AND $wpdb->postmeta.meta_value IN (". implode(',', $name) .")";
            else :
                $args .= " AND $wpdb->postmeta.meta_value LIKE '". $name ."%'";
            endif; 
            
            $results = $wpdb->get_results($args);
            if( $results ) :
                foreach( $results as $result ) :
                    $product = wc_get_product($result);

                    if( $product ) :
                        $product->set_image_id($attachmentID);
                        $product->save();
                    endif;
                endforeach;
            endif;
        }

        /**
         * Upload file to media.
         * 
         * @param file  File
         */
        public static function uploadFile($file, $extracted = FALSE, $attached = FALSE, $folder = '', $updateProducts = FALSE) {
            global $wpdb;

            if( !function_exists('wp_handle_upload') ) 
                require_once(ABSPATH .'wp-admin/includes/file.php');

            if( !$extracted ) :
                $uploadedFile = wp_handle_sideload($file, [
                    'test_form'     => FALSE,
                    'test_size'     => TRUE,
                    'test_upload'   => TRUE
                ]);
                $file = !$uploadedFile['error'] ? $uploadedFile['file'] : FALSE;               
            endif;

            $fileBasename = basename($file);
            $fileType = wp_check_filetype($fileBasename, NULL);
            $fileName = preg_replace('/\.[^.]+$/', '', $fileBasename);

            if( $file ) :
                $attachment = [
                    'post_mime_type'    => $fileType['type'],
                    'post_title'        => $fileName,
                    'post_parent'       => NULL,
                    'post_status'       => 'inherit',
                    'post_content'      => '',
                    'meta_input'        => [
                        '_mos_file'             => TRUE,
                        '_mos_file_attached'    => $attached,
                        '_mos_file_folder'      => $folder
                    ]
                ];
                $attachmentID = wp_insert_attachment($attachment, $file);

                if( !is_wp_error($attachmentID) ) :
                    require_once(ABSPATH .'wp-admin/includes/image.php');
                    
                    $attachmentData = wp_generate_attachment_metadata($attachmentID, $file);
                    wp_update_attachment_metadata($attachmentID, $attachmentData);

                    if( $updateProducts ) :
                        self::findAndUpdateProductsImageByFilename($fileName, $attachmentID);
                    endif;

                    return self::getFile($attachmentID);
                endif;
            endif;
        }

        /**
         * Ajax upload.
         * 
         * @return json
         */
        public static function ajaxUpload() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();
                
            $response = [
                'result'    => FALSE,
                'file'      => [
                    'id'        => FALSE,
                    'folder'    => '',
                    'files'     => []
                ]
            ];
            $updateProducts = isset($_POST['updateProducts']) && $_POST['updateProducts'] == 'true' ? TRUE : FALSE;

            $folder = strpos($_FILES['file']['name'], ':') !== FALSE ? explode(':', $_FILES['file']['name'])[0] : '';
            $_FILES['file']['name'] = !empty($folder) ? str_replace($folder, '', $_FILES['file']['name']) : $_FILES['file']['name'];
            
            $response['file']['files'][]['file'] = self::uploadFile($_FILES['file'], FALSE, FALSE, $folder, $updateProducts);

            wp_send_json($response);
        }

        /**
         * Ajax get uploads.
         * 
         * @return json
         */
        public static function ajaxGetUploads() {
            if( !defined('DOING_AJAX') && !DOING_AJAX ) 
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $response = [
                'result'    => FALSE,
                'files'     => []
            ];
            $files = self::getFileIDs(FALSE, $_POST['status']);

            if( 0 < count($files) ) :
                $response['result'] = TRUE;
                foreach( $files as $key => $file ) :
                    $response['files'][$key] = [
                        'id'        => $file,
                        'folder'    => get_post_meta($file, '_mos_file_folder', TRUE),
                        'file'      => NULL
                    ];

                    // if( get_post_meta($file, '_mos_file_folder', TRUE) == '' ) :
                        $response['files'][$key]['folder'] = '';
                        $response['files'][$key]['file'] = self::getFile($file);
                    // endif;
                endforeach;
            endif;
            wp_send_json($response);
        }

        /**
         * Ajax delete uploaded file.
         * 
         * @return json
         */
        public static function ajaxDeleteUpload() {
            if( !defined('DOING_AJAX') && !DOING_AJAX ) 
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $response = [
                'result'    => FALSE,
                'files'     => []
            ];
            $data = @$_POST['data'];
            $mosFiles = !is_array($data['id']) ? [$data['id']] : $data['id'];

            // Check for folders
            // foreach( $mosFiles as $file ) :
            //     if( $mosFile = get_post(intval($file)) ) :
            //         if( $mosFile->__get('_mos_file_type') == 'folder' ) :
            //             $folderFiles = new WP_Query([
            //                 'post_type'         => 'mos_files',
            //                 'posts_per_page'    => -1,
            //                 'post_status'       => $data['permanent'] == 'true' ? ['trash'] : ['publish'],
            //                 'meta_query'        => [
            //                     'relation'          => 'AND',
            //                     [
            //                         'key'           => '_mos_file_dir',
            //                         'value'         => $mosFile->post_title
            //                     ]
            //                 ]
            //             ]);

            //             if( $folderFiles->have_posts() ) :
            //                 while( $folderFiles->have_posts() ) :
            //                     $folderFiles->the_post();

            //                     $mosFiles[] = get_the_ID();
            //                 endwhile;
            //                 wp_reset_postdata();
            //             endif;
            //         endif;
            //     endif;
            // endforeach;

            // Run delete
            foreach( $mosFiles as $file ) :
                if( $mosFile = self::getFile($file) ) :
                    $response['files'][] = [
                        'id'        => $file,
                        'folder'    => '',
                        'file'      => $mosFile
                    ];

                    if( isset($data['permanent']) && $data['permanent'] == 'true' ) :
                        $response['result'] = wp_delete_attachment($file, TRUE) ? TRUE : FALSE;
                    else :
                        $response['result'] = wp_trash_post($file) ? TRUE : FALSE;
                    endif;
                endif;
            endforeach;

            wp_send_json($response);
        }

        /**
         * Ajax restore uploaded file.
         * 
         * @return json
         */
        public static function ajaxRestoreUpload() {
            if( !defined('DOING_AJAX') && !DOING_AJAX ) 
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $response = [
                'result'    => FALSE,
                'files'     => []
            ];
            $data = @$_POST['data'];

            $mosFiles = !is_array($data['id']) ? [$data['id']] : $data['id'];

            // Check for folders
            // foreach( $mosFiles as $file ) :
            //     if( $mosFile = get_post(intval($file)) ) :
            //         if( $mosFile->__get('_mos_file_type') == 'folder' ) :
            //             $folderFiles = new WP_Query([
            //                 'post_type'         => 'mos_files',
            //                 'posts_per_page'    => -1,
            //                 'post_status'       => 'trash',
            //                 'meta_query'        => [
            //                     'relation'          => 'AND',
            //                     [
            //                         'key'           => '_mos_file_dir',
            //                         'value'         => str_replace('__trashed', '', $mosFile->post_name)
            //                     ]
            //                 ]
            //             ]);

            //             if( $folderFiles->have_posts() ) :
            //                 while( $folderFiles->have_posts() ) :
            //                     $folderFiles->the_post();

            //                     $mosFiles[] = get_the_ID();
            //                 endwhile;
            //                 wp_reset_postdata();
            //             endif;
            //         endif;
            //     endif;
            // endforeach;

            // Run restore
            foreach( $mosFiles as $file ) :
                if( wp_untrash_post($file) ) :
                    $response['result'] = TRUE;
                    $response['files'][] = [
                        'id'        => $file,
                        'folder'    => '',
                        'file'      => self::getFile($file)
                    ];
                endif;
            endforeach;

            wp_send_json($response);
        }
    }

    MOS_WC_Files::init();
endif;