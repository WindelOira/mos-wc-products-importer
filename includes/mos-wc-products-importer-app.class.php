<?php

defined('ABSPATH') || exit;

if( !class_exists('MOS_WC_App') ) :
    class MOS_WC_App {
        /**
         * Init.
         */
        public static function init() {
            add_action('admin_menu', __CLASS__ .'::adminMenu');
        }

        /**
         * Admin menu.
         */
        public static function adminMenu() {
            add_menu_page(
                __('MOS', MOS_WC_TEXT_DOMAIN),
                __('MOS', MOS_WC_TEXT_DOMAIN),
                'manage_options',
                'mos-wc-importer-settings',
                __CLASS__ .'::adminMenuCallback'
            );
        }

        /**
         * Admin menu callback.
         */
        public static function adminMenuCallback() {
        ?>
            <div id="mos-wc-app">
                <v-app>
                    <mos-app></mos-app>
                </v-app>
            </div>
        <?php
        }
    }

    MOS_WC_App::init();
endif;