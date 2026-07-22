<?php

namespace Finespirits\FsFacture;

class ACF_FSFacture {

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_acf_get_product_data', array($this, 'wp_ajax_acf_get_product_data_handle'));
        $this->run_filters();
    }


    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        if(
            $screen->post_type === 'factures' &&
            $screen->base === 'post'
        ) {
            wp_enqueue_script(
                'fs-facture-product-admin-script',
                FS_FACTURE_PLUGIN_URL . 'assets/acf/product/product.js',
                ['jquery'],
                '1.0.2',
                true
            );
        }
    }

    public function run_filters() {
        add_filter('acf/prepare_field/name=current_price_product_item_facture', function($field) {
            $field['readonly'] = 1; 
            $field['disabled'] = 1;
            return $field;
        });

        add_filter('acf/prepare_field/name=stock_product_item_facture', function($field) {
            $field['readonly'] = 1; 
            $field['disabled'] = 1;
            return $field;
        });
    }

    public function wp_ajax_acf_get_product_data_handle() {
        if ( ! current_user_can('edit_posts') ) {
            wp_send_json_error('No permission');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error('No product ID');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }

        wp_send_json_success([
            'price' => $product->get_price(),
            'stock_quantity' => $product->get_stock_quantity(),
        ]); 
    }
}




