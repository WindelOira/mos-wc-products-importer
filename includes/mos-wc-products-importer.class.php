<?php

defined('ABSPATH') || exit;

if( !class_exists('MOS_WC') ) :
    class MOS_WC {
        /**
         * Init.
         */
        public static function init() {
            self::defineConstants();
            self::includes();

            add_action('admin_enqueue_scripts', __CLASS__ .'::enqueueScripts');
            // add_filter('script_loader_tag', __CLASS__ .'::scriptLoaderTag', 10, 3);

            register_activation_hook(__FILE__, __CLASS__ .'::activate'); 
            register_deactivation_hook(__FILE__, __CLASS__ .'::deactivate');
        }

        /**
         * Plugin active.
         */
        public static function activate() {
            
        }

        /**
         * Plugin deactivate.
         */
        public static function deactivate() {
            
        }

        /**
         * Define.
         * 
         * @param string    $constant
         * @param mixed     $value
         */
        protected static function define($constant, $value) {
            !defined($constant) ? define($constant, $value) : '';
        }

        /**
         * Define constants.
         */
        private static function defineConstants() {
            self::define('MOS_WC_VERSION', '1.0.0');
            self::define('MOS_WC_ABSPATH', dirname(MOS_WC_PLUGIN_FILE) .'/');
            self::define('MOS_WC_PLUGIN_URL', plugins_url('mos-wc-products-importer/'));
            self::define('MOS_WC_NONCE_KEY', 'mos-wc-products-importer');
        }

        /**
         * Includes.
         */
        private static function includes() {
            include MOS_WC_ABSPATH .'includes/mos-wc-products-importer-app.class.php';
            include MOS_WC_ABSPATH .'includes/mos-wc-products-importer-settings.class.php';
            include MOS_WC_ABSPATH .'includes/mos-wc-products-importer-handler.class.php';
            include MOS_WC_ABSPATH .'includes/mos-wc-products-importer-files.class.php';
        }

        /**
         * Enqueue scripts.
         */
        public static function enqueueScripts() {
            if( isset($_REQUEST['page']) && $_REQUEST['page'] == 'mos-wc-importer-settings' ) :
                wp_enqueue_style('mos-google-fonts', '//fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900', [], FALSE, 'all');
                wp_enqueue_style('mos-material-design-icons', '//cdn.jsdelivr.net/npm/@mdi/font@4.x/css/materialdesignicons.min.css', [], FALSE, 'all');
                wp_enqueue_style('mos-vuetify', '//cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css', [], FALSE, 'all');
                wp_enqueue_style('mos-vue-upload-component', '//cdn.jsdelivr.net/npm/vue-upload-component@2.8.20/dist/vue-upload-component.part.css', [], FALSE, 'all');
                wp_enqueue_style('mos-wc-products-importer', MOS_WC_PLUGIN_URL .'assets/css/mos-wc-products-importer.css', [], MOS_WC_VERSION, 'all');

                wp_register_script('mos-vue', '//cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js', [], FALSE, TRUE);
                wp_register_script('mos-vuetify', '//cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js', [], FALSE, TRUE);
                wp_register_script('mos-http-vue-loader', '//cdn.jsdelivr.net/npm/http-vue-loader@1.4.1/src/httpVueLoader.min.js', [], FALSE, TRUE);
                wp_register_script('mos-xlsx', '//cdnjs.cloudflare.com/ajax/libs/xlsx/0.15.1/xlsx.full.min.js', [], FALSE, TRUE);
                wp_register_script('mos-vue-upload-component', '//cdn.jsdelivr.net/npm/vue-upload-component', [], FALSE, TRUE);
                wp_register_script('mos-axios', '//cdnjs.cloudflare.com/ajax/libs/axios/0.19.0/axios.min.js', [], FALSE, TRUE);
                wp_register_script('mos-qs', '//cdnjs.cloudflare.com/ajax/libs/qs/6.9.0/qs.min.js', [], FALSE, TRUE);
                wp_register_script('mos-jszip', '//cdnjs.cloudflare.com/ajax/libs/jszip/3.2.2/jszip.min.js', [], FALSE, TRUE);
                wp_register_script('mos-pretty-print-bytes', '//cdn.jsdelivr.net/npm/vue-pretty-bytes-filter@1.0.2/vue.pretty-print-bytes.min.js', [], FALSE, TRUE);
                wp_register_script('mos-papaparse', MOS_WC_PLUGIN_URL .'assets/js/modules/papaparse/papaparse.min.js', [], FALSE, TRUE);
                wp_enqueue_script('mos-wc-products-importer', MOS_WC_PLUGIN_URL .'assets/js/mos-wc-products-importer.js', ['mos-vue', 'mos-vuetify', 'mos-http-vue-loader', 'mos-xlsx', 'mos-papaparse', 'mos-vue-upload-component', 'mos-axios', 'mos-qs', 'mos-jszip', 'mos-pretty-print-bytes'], MOS_WC_VERSION, TRUE);
                wp_localize_script('mos-wc-products-importer', 'mosWC', [
                    'paths'     => [
                        'abs'           => MOS_WC_ABSPATH,
                        'pluginURL'     => MOS_WC_PLUGIN_URL
                    ],
                    'ajax'      => [
                        'nonce'         => wp_create_nonce(MOS_WC_NONCE_KEY),
                        'url'           => admin_url('admin-ajax.php')
                    ]
                ]);
            endif;
        }

        /**
         * Script loader tag.
         */
        public function scriptLoaderTag($tag, $handle, $src) {
            if( $handle != 'mos-wc-products-importer' )
                return $tag;

            $tag = '<script type="module" src="'. esc_url($src) .'" id="'. $handle .'"></script>';

            return $tag;
        }
    }

    MOS_WC::init();
endif;