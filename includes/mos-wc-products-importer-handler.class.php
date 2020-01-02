<?php

defined('ABSPATH') || exit;

require_once(ABSPATH . 'wp-admin/includes/image.php');

if( !class_exists('MOS_WC_Handler') ) :
    class MOS_WC_Handler {
        /**
         * Init
         */
        public static function init() {
            add_action('init', __CLASS__ .'::createAttributes');
            add_action('wp_ajax_nopriv_getProductTaxonomies', __CLASS__ .'::getProductTaxonomies');
            add_action('wp_ajax_getProductTaxonomies', __CLASS__ .'::getProductTaxonomies');
            add_action('wp_ajax_nopriv_getProductCategories', __CLASS__ .'::getProductCategories');
            add_action('wp_ajax_getProductCategories', __CLASS__ .'::getProductCategories');
            add_action('wp_ajax_nopriv_getProductBrands', __CLASS__ .'::getProductBrands');
            add_action('wp_ajax_getProductBrands', __CLASS__ .'::getProductBrands');
            add_action('wp_ajax_nopriv_ajaxImport', __CLASS__ .'::ajaxImport');
            add_action('wp_ajax_ajaxImport', __CLASS__ .'::ajaxImport');
            add_action('wp_ajax_nopriv_ajaxGetUnimported', __CLASS__ .'::ajaxGetUnimported');
            add_action('wp_ajax_ajaxGetUnimported', __CLASS__ .'::ajaxGetUnimported');
            add_action('wp_ajax_nopriv_ajaxDeleteUnimported', __CLASS__ .'::ajaxDeleteUnimported');
            add_action('wp_ajax_ajaxDeleteUnimported', __CLASS__ .'::ajaxDeleteUnimported');
            add_action('wp_ajax_nopriv_ajaxDeleteUnassignedCategories', __CLASS__ .'::ajaxDeleteUnassignedCategories');
            add_action('wp_ajax_ajaxDeleteUnassignedCategories', __CLASS__ .'::ajaxDeleteUnassignedCategories');
            add_action('wp_ajax_nopriv_ajaxDeleteUnassignedBrands', __CLASS__ .'::ajaxDeleteUnassignedBrands');
            add_action('wp_ajax_ajaxDeleteUnassignedBrands', __CLASS__ .'::ajaxDeleteUnassignedBrands');

            self::addTaxClass('GST');
        }

        /**
         * Search supplier part number by delimiters.
         */
        public static function searchSupplierPartNumber($where, $wp_query) {
            global $wpdb;

            if($searchTerm = $wp_query->get('supplier_part_number')) :
                $delimiters = MOS_WC_Settings::getOption('delimiters');
                $delimitersPattern = "/(";
                if( $delimiters ) :
                    foreach( $delimiters as $delimiter ) :
                        $delimitersPattern .= "\\". $delimiter ."|";
                    endforeach;
                endif;
                $delimitersPattern = rtrim($delimitersPattern, "|") .")/";
                $searchTerm = preg_split($delimitersPattern, $searchTerm);

                if( MOS_WC_Settings::getOption('findFirst') == 'true' ) :
                    $where .= " OR ". $wpdb->posts .".post_title LIKE '". $searchTerm[0] ."%'";
                else :
                    $where .= " OR ". $wpdb->posts .".post_title IN (";
                    foreach( $searchTerm as $term ) :
                        $where .= "'". $term ."',";
                    endforeach; 
                    $where = substr($where, 0, -1) ."))";
                endif;
            endif;

            return $where;
        }

        /**
         * Create attributes.
         * 
         * @return void
         */
        public static function createAttributes() {
            // Create 'Parts Per Unit' attribute
            wc_create_attribute([
                'name'  => 'Parts Per Unit',
                'slug'  => 'ppu'
            ]);

            // Create 'Supplier Part Number' attribute
            wc_create_attribute([
                'name'  => 'Supplier Part Number',
                'slug'  => 'supplier-part-no'
            ]);
        }

        /**
         * Create attribute.
         * 
         * @return array
         */
        public static function createAttribute($name, $options, $position, $isVisible = 0, $isVariation = 0) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(wc_attribute_taxonomy_id_by_name($name));
            $attribute->set_name($name);
            $attribute->set_options($options);
            $attribute->set_position($position);
            $attribute->set_visible($isVisible);
            $attribute->set_variation($isVariation);
            
            return $attribute;
        }

        /**
         * Add tax class.
         * 
         * @param string    Tax class.
         * 
         * @return void
         */
        public static function addTaxClass($taxClass) {
            $taxClasses = explode("\n", get_option('woocommerce_tax_classes'));
            if( !in_array($taxClass, $taxClasses) ) :
                $taxClasses[] = $taxClass;
            endif;
            $taxClasses = implode("\n", $taxClasses);

            update_option('woocommerce_tax_classes', $taxClasses);
        }

        /**
         * Get product.
         * 
         * @param string    Key.
         * @param string    Value.
         * @param bool      Is meta data.
         * 
         * 
         * @return object|bool
         */
        public static function getProductBy($key = 'title', $value, $isMeta = FALSE) {
            $args = [
                'post_type'         => 'product',
                'posts_per_page'    => 1,
                'post_status'       => 'publish',
                'fields'            => 'ids'
            ];

            if( $isMeta ) :
                $args['meta_query'] = [
                    'relation'      => 'AND',
                    [
                        'key'           => $key,
                        'value'         => $value
                    ]
                ];
            elseif( $key == 'title' ) :
                $args['s'] = $value;
            endif;

            $query = new WP_Query($args);
            wp_reset_postdata();
            
            return 0 < count($query->posts) ? $query->posts[0] : FALSE;
        }

        /**
         * Get product by taxonomy and delete taxonomy if none exists.
         * 
         * @return void
         */
        public static function deleteTaxonmyWithEmptyProducts($termID, $taxonomy) {
            $products = new WP_Query([
                'post_type'         => 'product',
                'posts_per_page'    => -1,
                'tax_query'         => [
                    [
                        'taxonomy'      => $taxonomy,
                        'field'         => 'term_id',
                        'terms'         => $termID
                    ]
                ]
            ]);

            if( !$products->have_posts() ) :
                wp_delete_term($termID, $taxonomy);
            endif;
            wp_reset_postdata();
        }
        

        /**
         * Ajax get taxonomies.
         * 
         * @return json
         */
        public static function getProductTaxonomies() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !post_type_exists('product') )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $data = @$_POST['data'];
            $taxonomies = [];
            $excludedTaxonomies = ['product_type', 'product_visibility', 'product_shipping_class', 'product_cat', 'product_tag'];
            $productTaxonomies = get_object_taxonomies('product');
            if( $productTaxonomies ) :
                foreach( $productTaxonomies as $taxonomy ) :
                    if( (isset($data['builtin']) && $data['builtin'] == 'false') && 
                        in_array($taxonomy, $excludedTaxonomies) ) 
                        continue;

                    $taxonomies[] = $taxonomy;
                endforeach;
            endif;

            return wp_send_json([
                'taxonomies'    => $taxonomies
            ]);
        }

        /**
         * Ajax get product categories.
         * 
         * @return json
         */
        public static function getProductCategories() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $data = @$_POST['data'];

            $categories = get_terms([
                'taxonomy'      => 'product_cat',
                'hide_empty'    => FALSE,
                'parent'        => isset($data['parent']) ? intval($data['parent']) : 0
            ]);

            wp_send_json($categories);
        }

        /**
         * Ajax get product brands.
         * 
         * @return json
         */
        public static function getProductBrands() {
            if( !defined('DOING_AJAX') && !DOING_AJAX ) 
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            $brands = get_terms([
                'taxonomy'      => 'pwb-brand',
                'hide_empty'    => FALSE
            ]);

            wp_send_json($brands);
        }

        /**
         * Add GST.
         * 
         * @param string    Gst amount.
         * 
         * @return string
         */
        public static function addGST($amount) {
            $taxClass = '';
            $taxClasses = WC_Tax::get_tax_classes();

            if( in_array('GST', $taxClasses) ) :
                $gstRates = [];
                $gstTaxRates = WC_Tax::get_rates_for_tax_class('GST');

                $gstRates = array_filter($gstTaxRates, function($rate) use ($amount) {
                    return wc_format_decimal($rate->tax_rate, 0) == wc_format_decimal($amount);
                });
                $gstRates = array_values($gstRates);

                if( !count($gstRates) ) :
                    $gstTaxRateID = WC_Tax::_insert_tax_rate([
                        'tax_rate_class'    => 'GST',
                        'tax_rate_country'  => '',
                        'tax_rate_state'    => '',
                        'tax_rate'          => wc_format_decimal($amount),
                        'tax_rate_name'     => 'GST',
                        'tax_rate_priority' => 1,
                        'tax_rate_compound' => 1,
                        'tax_rate_shipping' => 1,
                        'tax_rate_order'    => 1,
                    ]);
                    $gstTaxRate = WC_Tax::_get_tax_rate($gstTaxRateID);

                    $taxClass = $gstTaxRate['tax_rate_class'];
                else :
                    $taxClass = $gstRates[0]->tax_rate_class;
                endif;
            endif;

            return $taxClass;
        }

        /**
         * Ajax import
         * 
         * @return json
         */
        public static function ajaxImport() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !class_exists('WC_Product') )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) ) 
                die();

            // Set import  last update 
            MOS_WC_Settings::saveOption('last_import_performed', $_POST['data']['time']);
            $lastImportPerformed = MOS_WC_Settings::getOption('last_import_performed');

            $response = [
                'result'    => FALSE,
				'skipped'	=> FALSE,
                'items'     => []
            ];
            $data = @$_POST['data'];
            $legend = wp_list_pluck($data['map']['legend'], 'selected', 'key');
			
			$data_store = WC_Data_Store::load( 'product' );

            if( 0 < count($data['items']) ) :
                $response['result'] = TRUE;
                foreach( $data['items'] as $item ) :
                    if( !$data['importProducts'] &&
                        (isset($legend['image_link']) && !empty($item[$legend['image_link']])) ) :
                        if( isset($legend['supplier_part_number']) && !empty($item[$legend['supplier_part_number']]) ) :
                            $uploadedFromURL = MOS_WC_Files::uploadFile($item[$legend['image_link']], FALSE, FALSE, '', TRUE, TRUE, $item[$legend['supplier_part_number']]);
                        else :
                            $uploadedFromURL = MOS_WC_Files::uploadFile($item[$legend['image_link']], FALSE, FALSE, '', TRUE, TRUE);
                        endif;
                        
                        if( $uploadedFromURL ) :
                            $response['skipped'] = FALSE;
                            $response['items'][] = [
                                'categories'            => [],
                                'tags'                  => [],
                                'brand'                 => 0,
                                'id'                    => $uploadedFromURL['fileID'],
                                'title'                 => $uploadedFromURL['title'],
                                'sku'                   => '',
                                'short_description'     => '',
                                'description'           => '',
                                'regular_price'         => 0,
                                'sale_price'            => 0,
                                'stock'                 => 0,
                                'attributes'            => '',
                                'url'                   => '',
                                'image'                 => $uploadedFromURL['url'],
                                'edit_url'              => '',
                                'is_image'              => TRUE
                            ];
                        endif;
                    else :
                        $productID = self::getProductBy('title', $item[$legend['title']]);

                        if( !$productID && 
                            (isset($legend['sku']) && !empty($item[$legend['sku']])) &&
                            $productIDBySku = wc_get_product_id_by_sku($item[$legend['sku']]) ) :
                            $productID = $productIDBySku;
                        endif;

                        $product = $productID ? wc_get_product($productID) : new WC_Product();
                        $productDescription = '';
                        $productCategories = [];
                        $productAttrs = [];
                
                        if( $productID && $data_store->is_existing_sku($productID, $item[$legend['sku']]) ) :
                            $response['skipped'] = TRUE;
                            $response['items'][] = [
                                'categories'            => [],
                                'tags'                  => [],
                                'brand'                 => 0,
                                'id'                    => 0,
                                'title'                 => '',
                                'sku'                   => '',
                                'short_description'     => '',
                                'description'           => '',
                                'regular_price'         => 0,
                                'sale_price'            => 0,
                                'stock'                 => 0,
                                'attributes'            => '',
                                'url'                   => '',
                                'image'                 => '',
                                'edit_url'              => '',
                                'is_image'              => FALSE
                            ];
                        else :
                            // Product category
                            if( isset($legend['category']) && 
                                !empty($item[$legend['category']]) ) :
                                $category = term_exists($item[$legend['category']], 'product_cat');

                                if( $category ) :
                                    $category = intval($category['term_id']);
                                else :
                                    $category = wp_insert_category([
                                        'taxonomy'  => 'product_cat',
                                        'cat_name'  => strtoupper($item[$legend['category']])
                                    ]);
                                endif;

                                $productCategories[0] = $category;
                            endif;

                            // Product child category
                            if( isset($category) && 
                                isset($legend['category_child']) && 
                                !empty($item[$legend['category_child']]) ) :
                                $childCategory = term_exists($item[$legend['category_child']], 'product_cat', $category);

                                if( $childCategory ) :
                                    $childCategory = intval($childCategory['term_id']);
                                else :
                                    $childCategory = wp_insert_category([
                                        'taxonomy'          => 'product_cat',
                                        'cat_name'          => strtoupper($item[$legend['category_child']]),
                                        'category_parent'   => $category
                                    ]);
                                endif;

                                unset($productCategories[0]);
                                $productCategories[0] = $childCategory;
                            endif;

                            // Product grandchild category
                            if( isset($category) && 
                                isset($childCategory) && 
                                isset($legend['category_grandchild']) && 
                                !empty($item[$legend['category_grandchild']]) ) :
                                $grandchildCategory = term_exists($item[$legend['category_grandchild']], 'product_cat', $childCategory);

                                if( $grandchildCategory ) :
                                    $grandchildCategory = intval($grandchildCategory['term_id']);
                                else :
                                    $grandchildCategory = wp_insert_category([
                                        'taxonomy'          => 'product_cat',
                                        'cat_name'          => strtoupper($item[$legend['category_grandchild']]),
                                        'category_parent'   => $childCategory
                                    ]);
                                endif;

                                unset($productCategories[0]);
                                $productCategories[0] = $grandchildCategory;
                            endif;

                            // Product brand
                            if( isset($legend['pwb-brand']) && 
                                !empty($item[$legend['pwb-brand']]) ) :
                                $brand = term_exists($item[$legend['pwb-brand']], 'pwb-brand');

                                if( $brand ) :
                                    $brand = intval($brand['term_id']);
                                else :
                                    $brand = wp_insert_category([
                                        'taxonomy'  => 'pwb-brand',
                                        'cat_name'  => strtoupper($item[$legend['pwb-brand']])
                                    ]);
                                endif;
                            endif;

                            $product->set_category_ids($productCategories);

                            // Product title
                            if( isset($legend['title']) && 
                                !empty($item[$legend['title']]) &&
                                $product->get_name() != $item[$legend['title']] ) :
                                $product->set_name($item[$legend['title']]);
                            endif;

                            // Product slug
                            if( isset($legend['slug']) &&
                                !empty($item[$legend['slug']]) &&
                                $product->get_name() != $item[$legend['title']] ) :
                                $product->set_slug($item[$legend['slug']]);
                            endif;

                            // Product short description
                            if( isset($legend['description']) &&
                                !empty($item[$legend['description']]) && 
                                $product->get_short_description() != $item[$legend['description']] ) :
                                $product->set_short_description($item[$legend['description']]);
                            endif;

                            // Product sku
                            if( isset($legend['sku']) &&
                                !empty($item[$legend['sku']]) && 
                                $product->get_sku() != $item[$legend['sku']] ) :
                                $product->set_sku($item[$legend['sku']]);
                            endif;

                            // Product regular price
                            if( isset($legend['regular_price']) && 
                                !empty($item[$legend['regular_price']]) && 
                                $product->get_regular_price() != $item[$legend['regular_price']] ) :
                                $product->set_regular_price($item[$legend['regular_price']]);
                            endif;

                            // Product sale price
                            if( isset($legend['sale_price']) && 
                                !empty($item[$legend['sale_price']]) && 
                                $product->get_sale_price() != $item[$legend['sale_price']] ) :
                                $product->set_sale_price($item[$legend['sale_price']]);
                            endif;

                            // Product stock
                            if( isset($legend['stock']) && 
                                !empty($item[$legend['stock']]) && 
                                $product->get_stock_quantity() != $item[$legend['stock']] ) :
                                $product->set_stock_quantity($item[$legend['stock']]);
                            endif;

                            // Product tax class
                            if( isset($legend['tax_class']) && 
                                !empty($item[$legend['tax_class']]) ) :
                                $product->set_tax_class(self::addGST($item[$legend['tax_class']]));
                            endif;

                            // Product description : barcode
                            if( isset($legend['barcode']) && 
                                !empty($item[$legend['barcode']]) && 
                                $product->get_meta('_barcode') != $item[$legend['barcode']] ) :
                                $product->update_meta_data('_barcode', $item[$legend['barcode']]);
                            endif;

                            // Product description : inner barcode
                            if( isset($legend['inner_barcode']) && 
                                !empty($item[$legend['inner_barcode']]) && 
                                $product->get_meta('_inner_barcode') != $item[$legend['inner_barcode']] ) :
                                $product->update_meta_data('_inner_barcode', $item[$legend['inner_barcode']]);
                            endif;

                            // Product meta : _woo_uom_input
                            if( isset($legend['uom']) && 
                                !empty($item[$legend['uom']]) && 
                                $product->get_meta('_woo_uom_input') != $item[$legend['uom']] ) :
                                $product->update_meta_data('_woo_uom_input', $item[$legend['uom']]);

                                $productDescription .= '<p><strong>Each unit of this product is available in the quantity:</strong> '. $item[$legend['uom']] .'</p>';
                            endif;

                            // Prodcut attr : pa_ppu
                            if( isset($legend['ppu']) && 
                                !empty($item[$legend['ppu']]) ) :
                                $productAttrs[] = self::createAttribute('pa_ppu', [$item[$legend['ppu']]], 1, 1, 0);

                                $productDescription .= '<p><strong>Number of items per unit:</strong> '. $item[$legend['ppu']] .'</p>';

                                $product->update_meta_data('_ppu', $item[$legend['ppu']]);
                            endif;
                            
                            // Image link
                            if( isset($legend['image_link']) && !empty($item[$legend['image_link']]) ) :
                                if( isset($legend['supplier_part_number']) && !empty($item[$legend['supplier_part_number']]) ) :
                                    $uploadedFromURL = MOS_WC_Files::uploadFile($item[$legend['image_link']], FALSE, TRUE, '', FALSE, TRUE, $item[$legend['supplier_part_number']]);
                                else :
                                    $uploadedFromURL = MOS_WC_Files::uploadFile($item[$legend['image_link']], FALSE, TRUE, '', FALSE, TRUE);
                                endif;
                                
                                if( $uploadedFromURL ) :
                                    $product->set_image_id($uploadedFromURL['fileID']);
                                endif;
                            endif;

                            // Product attr : pa_supplier-part-no
                            if( (isset($legend['supplier_part_number']) && !empty($item[$legend['supplier_part_number']])) && 
                                (!isset($legend['image_link']) || empty($legend['image_link'])) ) :
                                $product->update_meta_data('_supplier_part_number', $item[$legend['supplier_part_number']]);

                                $productAttrs[] = self::createAttribute('pa_supplier-part-no', [$item[$legend['supplier_part_number']]], 2, 0, 0);

                                // Get SPU
                                $delimiters = MOS_WC_Settings::getOption('delimiters');
                                $delimitersPattern = "/(";
                                if( $delimiters ) :
                                    foreach( $delimiters as $delimiter ) :
                                        $delimitersPattern .= "\\". $delimiter ."|";
                                    endforeach;
                                endif;
                                $delimitersPattern = rtrim($delimitersPattern, "|") .")/";
                                $name = preg_split($delimitersPattern, $item[$legend['supplier_part_number']]);

                                if( MOS_WC_Settings::getOption('findFirst') == 'true' ) :
                                    $name = $name[0];
                                endif;

                                $args = [
                                    'post_type'             => 'attachment',
                                    'posts_per_page'        => 1,
                                    'post_status'           => ['inherit'],
                                    'supplier_part_number'  => $item[$legend['supplier_part_number']],
                                    'meta_query'            => [
                                        'relation'              => 'AND',
                                        [
                                            'key'               => '_mos_file',
                                            'value'             => TRUE
                                        ],
                                        [
                                            'key'               => '_mos_file_attached',
                                            'value'             => FALSE
                                        ]
                                    ],
                                    's'                     => $item[$legend['supplier_part_number']]
                                ];
                                if( $data['imageSources'] != '' && 0 < count($data['imageSources']) ) :
                                    foreach( $data['imageSources'] as $source ) :
                                        $args['meta_query'][] = [
                                            'key'       => '_mos_file_folder',
                                            'value'     => $source
                                        ];
                                    endforeach;
                                endif;

                                add_filter('posts_where', __CLASS__ .'::searchSupplierPartNumber', 10, 2);
                                $query = new WP_Query($args);
                                remove_filter('posts_where', __CLASS__ .'::searchSupplierPartNumber', 10);

                                if( $query->have_posts() ) :
                                    while( $query->have_posts() ) :
                                        $query->the_post();

                                        $product->set_image_id(get_the_ID());
                                        update_post_meta(get_the_ID(), '_mos_file_attached', TRUE);
                                    endwhile;
                                    wp_reset_postdata();
                                endif;
                            endif;

                            // Set product description
                            if( !empty($productDescription) && 
                                $product->get_description() != $productDescription ) :    
                                $product->set_description($productDescription);
                            endif;

                            // Set product attributes
                            if( 0 < count($productAttrs) ) :
                                $product->set_attributes($productAttrs);
                            endif;

                            // Update last import performed metadata
                            $product->update_meta_data('_last_import_update', $lastImportPerformed);

                            // Save
                            $product->save();

                            // Set product brand
                            if( isset($brand) ) :
                                wp_set_object_terms($product->get_id(), [$brand], 'pwb-brand');
                            endif;

                            $response['items'][] = [
                                'categories'            => $product->get_category_ids(),
                                'tags'                  => $product->get_tag_ids(),
                                'brand'                 => isset($brand) ? $brand : 0,
                                'id'                    => $product->get_id(),
                                'title'                 => $product->get_name(),
                                'sku'                   => $product->get_sku(),
                                'short_description'     => $product->get_short_description(),
                                'description'           => $product->get_description(),
                                'regular_price'         => $product->get_regular_price(),
                                'sale_price'            => $product->get_sale_price(),
                                'stock'                 => $product->get_stock_quantity(),
                                'attributes'            => $product->get_attributes(),
                                'url'                   => $product->get_permalink(),
                                'image'                 => $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'full') : wc_placeholder_img_src('full'),
                                'edit_url'              => $product->get_id() ? esc_url_raw('post.php?post='. $product->get_id() .'&action=edit') : FALSE,
                                'is_image'              => FALSE
                            ];
                        endif;
                    endif;
                endforeach;
            endif;

            wp_send_json($response);
        }

        /**
         * Ajax get unimported products.
         * 
         * @return json
         */
        public static function ajaxGetUnimported() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) )
                die();

            $response = [
                'items'     => []
            ];
            $lastImportPerformed = MOS_WC_Settings::getOption('last_import_performed');

            // Get products
            $products = new WP_Query([
                'post_type'         => 'product',
                'posts_per_page'    => -1,
                'meta_query'        => [
                    'relation'          => 'OR',
                    [
                        'key'           => '_last_import_update',
                        'compare'       => 'NOT EXISTS'
                    ],
                    [
                        'key'           => '_last_import_update',
                        'value'         => $lastImportPerformed,
                        'compare'       => '!='
                    ]
                ]
            ]);
            
            if( $products->have_posts() ) :
                while( $products->have_posts() ) :
                    $products->the_post();

                    $response['items'][] = get_the_ID();
                endwhile;
            endif;

            wp_send_json($response);
        }

        /**
         * Ajax delete unimported products
         * 
         * @return json
         */
        public static function ajaxDeleteUnimported() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) )
                die();

            $data = @$_POST['data'];

            // Delete products
            if( 0 < count($data['items']) ) :
                foreach( $data['items'] as $item ) :
                    $product = wc_get_product($item);

                    if( $product ) :
                        wp_delete_attachment($product->get_image_id(), TRUE);
                        wp_delete_post($product->get_id(), TRUE);
                    endif;
                endforeach;
            endif;
            
            wp_send_json([
                'success'    => TRUE
            ]);
        }

        /**
         * Ajax delete unassigned categories.
         * 
         * @return json
         */
        public static function ajaxDeleteUnassignedCategories() {
            if( !defined('DOING_AJAX') && !DOING_AJAX ) 
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) )
                die();

            $data = @$_POST['data'];

            // Delete categories
            if( isset($data['categories']) && 
                is_array($data['categories']) && 
                0 < count($data['categories']) ) :
                $childCount = 0;
                foreach( $data['categories'] as $category ) :
                    $childCategories = get_terms([
                        'taxonomy'      => 'product_cat',
                        'hide_empty'    => FALSE,
                        'parent'        => $category['term_id']
                    ]);

                    if( !$category['count'] && !$childCategories ) :
                        wp_delete_term($category['term_id'], 'product_cat');
                        continue;
                    endif;

                    if( 0 < count($childCategories) ) :
                        $childCount++;
                        foreach( $childCategories as $childCategory ) :
                            $grandchildCount = 0;
                            $grandchildCategories = get_terms([
                                'taxonomy'      => 'product_cat',
                                'hide_empty'    => FALSE,
                                'parent'        => $childCategory->term_id
                            ]);

                            if( !$childCategory->count && !$grandchildCategories ) :
                                wp_delete_term($childCategory->term_id, 'product_cat');
                                continue;
                            endif;

                            if( 0 < count($grandchildCategories) ) :
                                foreach( $grandchildCategories as $grandchildCategory ) :
                                    if( !$grandchildCategory->count ) :
                                        wp_delete_term($grandchildCategory->term_id, 'product_cat');
                                        continue;
                                    endif;

                                    // Check for products assigned with the category
                                    self::deleteTaxonmyWithEmptyProducts($grandchildCategory->term_id, 'product_cat');

                                    $grandchildCount++;
                                endforeach;
                            endif;

                            if( !$grandchildCount ) :
                                wp_delete_term($childCategory->term_id, 'product_cat');
                                continue;
                            endif;

                            // Check for products assigned with the category
                            self::deleteTaxonmyWithEmptyProducts($childCategory->term_id, 'product_cat');
                        endforeach;
                    endif;

                    if( !$childCount ) :
                        wp_delete_term($category['term_id'], 'product_cat');
                        continue;
                    endif;

                    // Check for products assigned with the category
                    self::deleteTaxonmyWithEmptyProducts($category['term_id'], 'product_cat');
                endforeach;
            endif;

            wp_send_json([
                'success'   => TRUE
            ]);
        }

        /**
         * Ajax delete unassigned brands.
         * 
         * @return json
         */
        public static function ajaxDeleteUnassignedBrands() {
            if( !defined('DOING_AJAX') && !DOING_AJAX )
                die();

            if( !wp_verify_nonce($_POST['data']['nonce'], MOS_WC_NONCE_KEY) )
                die();

            $data = @$_POST['data'];

            // Delete brands
            if( isset($data['brands']) && 
                is_array($data['brands']) && 
                0 < count($data['brands']) ) :
                foreach( $data['brands'] as $brand ) :
                    if( !$brand['count'] ) :
                        wp_delete_term($brand['term_id'], 'pwb-brand');
                        continue;
                    endif;

                    // Check for products assigned with the brand
                    self::deleteTaxonmyWithEmptyProducts($brand['term_id'], 'pwb-brand');                  
                endforeach;
            endif;

            wp_send_json([
                'success'   => TRUE
            ]);
        }
    }
    
    MOS_WC_Handler::init();
endif;