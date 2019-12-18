<?php

defined('ABSPATH') || exit;

if( !class_exists('MOS_WC_Settings') ) :
    class MOS_WC_Settings {
        /**
         * Init.
         */
        public static function init() { 
            if( !self::getOption('delimiters') ) :
                self::saveOption('delimiters', [' ', '-', '\\', '/', '_', '-']);
            endif;

            add_action('wp_ajax_nopriv_ajaxGetSettings', __CLASS__ .'::ajaxGetSettings');
            add_action('wp_ajax_ajaxGetSettings', __CLASS__ .'::ajaxGetSettings');
            add_action('wp_ajax_nopriv_ajaxSaveSettings', __CLASS__ .'::ajaxSaveSettings');
            add_action('wp_ajax_ajaxSaveSettings', __CLASS__ .'::ajaxSaveSettings');
        }

        /**
         * Save option.
         * 
         * @param string    $key
         * @param mixed     $value
         * 
         * @return boolean
         */
        public static function saveOption($key, $value) {
            return get_option('moswc_'. $key) ? update_option('moswc_'. $key, $value) : add_option('moswc_'. $key, $value);
        }

        /**
         * Get option.
         * 
         * @param string    $key.
         * 
         * @return mixed
         */
        public static function getOption($key) {
            return get_option('moswc_'. $key);
        }

        /**
         * Ajax get settings.
         * 
         * @return json
         */
        public static function ajaxGetSettings() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $options = @$_POST['options'];
            $response = [];

            if( isset($options['images']) ) :
                foreach( $options['images'] as $key ) :
                    $response['images'][$key] = self::getOption($key);
                endforeach;
            endif;

            wp_send_json($response);
        }

        /**
         * Ajax save settings.
         * 
         * @return json
         */
        public static function ajaxSaveSettings() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $options = @$_POST['options'];
            $responses = [];

            if( isset($options['images']) ) :
                foreach( $options['images'] as $key => $value ) :
                    array_push($responses, self::saveOption($key, $value));
                endforeach;
            endif;

            wp_send_json($responses);
        }
    }

    MOS_WC_Settings::init();
endif;