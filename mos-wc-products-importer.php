<?php
/**
 * Plugin Name:       MOS Woocommerce Products Importer
 * Plugin URI:        https://github.com/WindelOira/mos-wc-products-importer
 * Description:       Import/update products from a xlsx/csv file with the ability to match images from uploaded images in the plugin.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Windel Oira
 * Author URI:        https://github.com/WindelOira/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mos-wc-products-importer
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

!defined('MOS_WC_TEXT_DOMAIN') ? define('MOS_WC_TEXT_DOMAIN', 'mos-wc-products-importer') : '';
!defined('MOS_WC_PLUGIN_FILE') ? define('MOS_WC_PLUGIN_FILE', __FILE__) : '';

if( !class_exists('MOS_WC') ) :
    include_once dirname(__FILE__) .'/includes/mos-wc-products-importer.class.php';
endif;