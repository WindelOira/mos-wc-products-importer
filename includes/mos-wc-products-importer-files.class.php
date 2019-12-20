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
            $fileIDs = [];

            $args = [
                'post_type'         => 'attachment',
                'post_status'       => $status,
                'posts_per_page'    => -1,
                'meta_query'        => [
                    'relation'          => 'AND',
                    [
                        'key'           => '_mos_file',
                        'value'         => 1
                    ],
                    [
                        'key'       => '_mos_file_attached',
                        'value'     => ''
                    ]
                ]
            ];

            if( $attached ) :
                $args['meta_query'][1]['value'] = 1;
            endif;

            if( $folder !== FALSE ) :
                $args['meta_query'][] = [
                    'key'       => '_mos_file_folder',
                    'value'     => $folder
                ];
            endif;

            $query = new WP_Query($args);
            wp_reset_postdata();

            return wp_list_pluck($query->posts, 'ID');
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
         * Find file by supplier part number.
         * 
         * @param string        Supplier part number.
         * 
         * @return array
         */
        public static function findFileBySPU($spu) {
            $args = [
                'post_type'         => 'attachment',
                'post_status'       => 'inherit',
                'posts_per_page'    => 1,
                'meta_query'        => [
                    'relation'          => 'AND',
                    [
                        'key'           => '_mos_file',
                        'value'         => 1
                    ],
                    [
                        'key'           => '_mos_file_spu',
                        'value'         => $spu
                    ]
                ]
            ];

            $query = new WP_Query($args);

            if( 0 < count($query->posts) ) :
                return $query->posts[0];
            else :
                return FALSE;
            endif;
        }

        /**
         * @param string    Filename.
         * @param int       Attachment ID.
         * 
         * @return void
         */
        public static function findAndUpdateProductsImageByFilename($filename, $attachmentID, $spu = '') {
            $wcProducts = wc_get_products([
                'limit'     => -1
            ]);

            if( !$wcProducts )
                return;

            $args = [
                'post_type'         => 'product',
                'posts_per_page'    => -1,
                'meta_query'        => [
                    'relation'          => 'AND'
                ]
            ];

            if( !empty($spu) ) :
                $args['meta_query'][] = [
                    'key'           => '_supplier_part_number',
                    'value'         => $spu
                ];
            else :
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

                $args['meta_query'][] = [
                    'key'           => '_supplier_part_number',
                    'value'         => is_array($name) ? $name : $name,
                    'compare'       => is_array($name) ? 'IN' : 'LIKE'
                ];
            endif;

            $query = new WP_Query($args);
            if( 0 < count($query->posts) ) :
                $products = wp_list_pluck($query->posts, 'ID');
                foreach( $products as $product ) :
                    $product = wc_get_product($product);

                    if( $product ) :
                        $product->set_image_id($attachmentID);
                        $product->save();
                    endif;
                endforeach;
                wp_reset_postdata();
            endif;
        }

        /**
         * Upload file to media.
         * 
         * @param file  File
         */
        public static function uploadFile($file, $extracted = FALSE, $attached = FALSE, $folder = '', $updateProducts = FALSE, $fromURL = FALSE, $spu = '') {
            global $wpdb;

            if( !function_exists('wp_handle_upload') ) 
                require_once(ABSPATH .'wp-admin/includes/file.php');

            if( !empty($spu) && $fileBySPU = self::findFileBySPU($spu) && $updateProducts ) :
                self::findAndUpdateProductsImageByFilename($fileBySPU['post_title'], $fileBySPU['ID'], $spu);

                return self::getFile($fileBySPU['ID']);
            endif;

            if( !$extracted && !$fromURL ) :
                $uploadedFile = wp_handle_sideload($file, [
                    'test_form'     => FALSE,
                    'test_size'     => TRUE,
                    'test_upload'   => TRUE
                ]);
                $file = !$uploadedFile['error'] ? $uploadedFile['file'] : FALSE;               
            endif;

            if( $fromURL ) :
                $tmpFile = download_url($file);

                if( !is_wp_error($tmpFile) ) :
                    $tmpFileBasename = basename($file);
                    $tmpFileType = wp_check_filetype($tmpFileBasename);

                    $file = [
                        'name'      => $tmpFileBasename,
                        'type'      => $tmpFileType ? $tmpFileType['type'] : '',
                        'tmp_name'  => $tmpFile,
                        'error'     => 0,
                        'size'      => filesize($tmpFile)
                    ];

                    $uploadedFile = wp_handle_sideload($file, [
                        'test_form'     => FALSE,
                        'test_size'     => TRUE
                    ]);
                    $file = !$uploadedFile['error'] ? $uploadedFile['file'] : FALSE;  
                else :
                    return NULL;
                endif;
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
                        '_mos_file_folder'      => $folder,
                        '_mos_file_spu'         => $spu
                    ]
                ];
                $attachmentID = wp_insert_attachment($attachment, $file);

                if( !is_wp_error($attachmentID) ) :
                    require_once(ABSPATH .'wp-admin/includes/image.php');
                    
                    $attachmentData = wp_generate_attachment_metadata($attachmentID, $file);
                    wp_update_attachment_metadata($attachmentID, $attachmentData);

                    if( $updateProducts ) :
                        self::findAndUpdateProductsImageByFilename($fileName, $attachmentID, $spu);
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