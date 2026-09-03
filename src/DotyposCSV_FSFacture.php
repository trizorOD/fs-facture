<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class DotyposCSV_FSFacture extends AbstractClassFSFacture {
    const AJAX_ACTION  = 'fs_facture_dotypos_csv';
    const WAREHOUSE_ID = 151951505;

    public function __construct() {
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_download_csv']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'factures') {
            return;
        }

        wp_enqueue_script(
            'fs-facture-dotypos-csv',
            FS_FACTURE_PLUGIN_URL . 'assets/admin/dotypos-csv.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('fs-facture-dotypos-csv', 'fsDotyposCSV', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::AJAX_ACTION),
            'action'  => self::AJAX_ACTION,
        ]);
    }

    public function ajax_download_csv() {
        check_ajax_referer(self::AJAX_ACTION, 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'No permission.'], 403);
        }

        $facture_id = isset($_POST['facture_id']) ? absint($_POST['facture_id']) : 0;
        if (!$facture_id) {
            wp_send_json_error(['message' => 'Invalid facture ID.'], 400);
        }

        $facture = get_post($facture_id);
        if (!$facture || $facture->post_type !== 'factures') {
            wp_send_json_error(['message' => 'Facture not found.'], 404);
        }

        $facture_data = function_exists('get_field') ? get_field('facture_group', $facture_id) : null;
        if (!$facture_data) {
            wp_send_json_error(['message' => 'No facture data found.'], 400);
        }

        $products_list = $facture_data['products_group']['products_list'] ?? [];
        if (empty($products_list)) {
            wp_send_json_error(['message' => 'No products in facture.'], 400);
        }

        $items = $this->parse_facture_items($products_list);
        if (empty($items)) {
            wp_send_json_error(['message' => 'No valid products (quantity > 0) in facture.'], 400);
        }

        $missing = $this->resolve_dotypos_ids($items);
        if (!empty($missing)) {
            wp_send_json_error([
                'message' => 'No DWP mapping found for: ' . implode(', ', $missing),
            ], 422);
        }

        $warnings = $this->fetch_stock($items);
        $csv      = $this->build_csv($items);

        if ($csv === null) {
            wp_send_json_error([
                'message' => 'Could not read stock for any product: ' . implode('; ', $warnings),
            ]);
        }

        wp_send_json_success([
            'csv'      => $csv,
            'filename' => 'facture-' . $facture_id . '-stock.csv',
            'warnings' => $warnings,
        ]);
    }

    private function parse_facture_items(array $products_list) {
        $items = [];

        foreach ($products_list as $item) {
            if (empty($item['product_item_facture'])) {
                continue;
            }

            $product_post = is_object($item['product_item_facture'])
                ? $item['product_item_facture']
                : get_post((int) $item['product_item_facture']);

            if (!$product_post) {
                continue;
            }

            $wc_id    = (int) $product_post->ID;
            $quantity = isset($item['quantity_product_item_facture'])
                ? (float) $item['quantity_product_item_facture']
                : 0.0;

            if ($wc_id <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = [
                'wc_id'      => $wc_id,
                'name'       => $product_post->post_title,
                'quantity'   => $quantity,
                'dotypos_id' => null,
                'stock'      => null,
                'new_qty'    => null,
            ];
        }

        return $items;
    }

    private function resolve_dotypos_ids(array &$items) {
        global $wpdb;
        $table   = \DWP_DB::mapping_table();
        $missing = [];

        foreach ($items as &$item) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT dotypos_id FROM $table WHERE woocommerce_id = %d AND status = 'matched' LIMIT 1",
                $item['wc_id']
            ));

            if (!$row || !$row->dotypos_id) {
                $missing[] = $item['name'] . ' (WC #' . $item['wc_id'] . ')';
            } else {
                $item['dotypos_id'] = (int) $row->dotypos_id;
            }
        }
        unset($item);

        return $missing;
    }

    private function fetch_stock(array &$items) {
        $api      = new \DWP_API();
        $warnings = [];

        foreach ($items as &$item) {
            $response = $api->get_warehouse_product(self::WAREHOUSE_ID, $item['dotypos_id']);

            if (isset($response['error'])) {
                $warnings[] = $item['name'] . ' (Dotypos #' . $item['dotypos_id'] . '): ' . $response['error'];
                $item['skip'] = true;
                continue;
            }

            $stock           = isset($response['data']['stockQuantityStatus'])
                ? (float) $response['data']['stockQuantityStatus']
                : 0.0;
            $item['stock']   = $stock;
            $item['new_qty'] = $stock - $item['quantity'];
        }
        unset($item);

        return $warnings;
    }

    private function build_csv(array $items) {
        $rows = ['name,ean,plu,externalId,productId,newQuantity'];

        foreach ($items as $item) {
            if (!empty($item['skip'])) {
                continue;
            }

            $rows[] = implode(',', [
                '',                           // name
                '',                           // ean
                '',                           // plu
                '',                           // externalId
                $item['dotypos_id'],          // productId
                $this->fmt($item['new_qty']), // newQuantity
            ]);
        }

        return count($rows) > 1 ? implode("\r\n", $rows) : null;
    }

    private function fmt($value) {
        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
