<?php

namespace Finespirits\FsFacture;

use Finespirits\FsFacture\PDF_FSFacture;
use Finespirits\FsFacture\ACF_FSFacture;
use Finespirits\FsFacture\XML_FSFacture;
use Finespirits\FsFacture\Margin_FSFacture;
use Finespirits\FsFacture\Settings_FSFacture;
use Finespirits\FsFacture\Organization_FSFacture;
use Finespirits\FsFacture\AbstractClassFSFacture;

if (!defined('ABSPATH')) {
    exit;
}

class App_FSFacture extends AbstractClassFSFacture {
    public $pdf_generate;
    public $acf_fields;
    public $xml_generate;
    public $margin_report;
    public $settings_page;
    public $organization_directory;
    public $dotypos_csv;
    const PURCHASE_PRICE_META_KEY = '_purchase_price_without_vat';

    public function __construct() {
        $this->init(); 
    }

    public function init() {
        add_action('init', array($this, 'init_app'), 10);
        add_action('add_meta_boxes', array($this, 'download_pdf_button_meta_box'));
        add_action('add_meta_boxes_product', [$this, 'add_product_purchase_price_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('acf/save_post', [$this, 'process_facture_products'], 20);
        add_action('post_submitbox_misc_actions', [$this, 'add_factures_statuses']);
        add_action('save_post_product', [$this, 'save_product_purchase_price_meta_box'], 10, 3);
        
        $this->run_filters();

        $this->pdf_generate = new PDF_FSFacture();
        $this->acf_fields = new ACF_FSFacture();
        $this->xml_generate = new XML_FSFacture();
        $this->margin_report = new Margin_FSFacture();
        $this->settings_page = new Settings_FSFacture();
        $this->organization_directory = new Organization_FSFacture();

        if (class_exists('DWP_API')) {
            $this->dotypos_csv = new DotyposCSV_FSFacture();
        }
    }

    public function init_app() {
        $labels = array(
            'name'               => __('Factures'),
            'singular_name'      => __('Facture'),
            'menu_name'          => __('Factures'),
            'name_admin_bar'     => __('Factures'),
            'add_new'            => __('Add Factures'),
            'add_new_item'       => __('Add Facture'),
            'new_item'           => __('Add Facture'),
            'edit_item'          => __('Edit Factures'),
            'view_item'          => __('View Facture'),
            'all_items'          => __('All Factures'),
            'search_items'       => __('Find Factures'),
            'parent_item_colon'  => __('Parent Facture'),
            'not_found'          => __('Factures not found'),
            'not_found_in_trash' => __('Factures not found in the trash'),
        );
    
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'factures', 'with_front' => false), 
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-media-document',
            'supports'           => array('title'),
            'show_in_rest'       => true,
        );
        register_post_type('factures', $args); 

        register_post_status('facture_current', [ 
                'label'                     => __('Current', 'factures'),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Current <span class="count">(%s)</span>', 'Current <span class="count">(%s)</span>'),
            ]
        );
        register_post_status('facture_corrective', [
                'label'                     => __('Corrective', 'factures'),
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Corrective <span class="count">(%s)</span>', 'Corrective <span class="count">(%s)</span>'),
            ]
        );
    }

    public function run_filters() {
        add_filter('wp_insert_post_data', [$this, 'set_current_default_factures_status'], 10, 2);
    }

    public function set_current_default_factures_status($data, $postarr) {
        $custom_statuses = ['facture_current', 'facture_corrective'];

        if (
            $data['post_type'] === 'factures' &&
            $data['post_status'] === 'publish'
        ) { 
            $data['post_status'] = 'facture_current'; 
        }

        if (
            $data['post_type'] === 'factures' &&
            isset($_POST['post_type_custom']) &&
            in_array($_POST['post_type_custom'], $custom_statuses)
        ) {
            $data['post_status'] = $_POST['post_type_custom'];
        }

        return $data;
    }

    public function add_factures_statuses() {
        global $post;

        if($post->post_type !== 'factures') { 
            return;
        }

        $allowed_statuses  = [
            'facture_current'      => __('Current', 'factures'),
            'facture_corrective'   => __('Corrective', 'factures'),
        ];

        $current_post_status = $post->post_status;

        $allowed_keys = json_encode(array_keys($allowed_statuses));

        echo "<script>
            jQuery(document).ready(function($) {
                const allowedStatuses = $allowed_keys;
                const current_post_status = '$current_post_status';
                const by_default_status = 'facture_current'
                $('#post_status option').each(function() {
                    if (!allowedStatuses.includes($(this).val())) {
                        $(this).remove();
                    }
                });
                const statusMap = " . json_encode($allowed_statuses) . ";
                let set_status = false;

                console.log(current_post_status)

                $.each(statusMap, function(value, label) {
                    if ($('#post_status option[value=\"' + value + '\"]').length === 0) {
                        const selected = (current_post_status === value) ? 'selected' : '';
                        const option_data = {
                            value: value,
                            text: label
                        }
                        if(selected) {
                            option_data.selected = 'selected'
                            set_status = true
                        }
                            
                        $('#post_status').append($('<option>', option_data));
                    }
                });

                if(!set_status) {
                    $('#post_status option:first').val()
                }

                if (statusMap[current_post_status]) {
                    $('#post-status-display').text(statusMap[current_post_status]);
                } else {
                    $('#post-status-display').text(statusMap[by_default_status]);
                }

                $('#publish').before('<input type=\"hidden\" id=\"post_type_custom\" name=\"post_type_custom\" value=\"publish\" />')

                $('#publish').on('click', function(){
                    var selectedStatus = $('#post_status').val();
                    $('#post_type_custom').val(selectedStatus)
                });
            });
            </script>";
        }

    public function download_pdf_button_meta_box() {
        add_meta_box(
            'download_pdf_button_box',
            'Actions',
            array($this->pdf_generate, 'download_pdf_button_render'),
            'factures',
            'side',
            'high'
        );
    }

    public function add_product_purchase_price_meta_box($post) {
        add_meta_box(
            'fs_facture_purchase_price_box',
            __('Purchase price net', 'fs-facture'),
            [$this, 'render_product_purchase_price_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    public function render_product_purchase_price_meta_box($post) {
        $default_product_id = $this->get_default_language_product_id((int) $post->ID);
        $value = get_post_meta($default_product_id, self::PURCHASE_PRICE_META_KEY, true);
        wp_nonce_field('fs_facture_save_purchase_price', 'fs_facture_purchase_price_nonce');
        ?>
        <p>
            <label for="fs-facture-purchase-price">
                <?php esc_html_e('Purchase price without VAT', 'fs-facture'); ?>
            </label>
        </p>
        <input
            type="text"
            id="fs-facture-purchase-price"
            name="fs_facture_purchase_price_without_vat"
            value="<?php echo esc_attr($value); ?>"
            class="widefat"
            inputmode="decimal"
            placeholder="0.00"
        />
        <?php
    }

    public function save_product_purchase_price_meta_box($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (
            !isset($_POST['fs_facture_purchase_price_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fs_facture_purchase_price_nonce'])), 'fs_facture_save_purchase_price')
        ) {
            return;
        }

        if (!isset($_POST['fs_facture_purchase_price_without_vat'])) {
            return;
        }

        $default_product_id = $this->get_default_language_product_id((int) $post_id);
        if (!current_user_can('edit_post', $default_product_id)) {
            return;
        }

        $raw_value = sanitize_text_field(wp_unslash($_POST['fs_facture_purchase_price_without_vat']));
        $normalized_value = str_replace(',', '.', $raw_value);

        if ($normalized_value === '') {
            delete_post_meta($default_product_id, self::PURCHASE_PRICE_META_KEY);
            return;
        }

        if (!is_numeric($normalized_value)) {
            return;
        }

        update_post_meta($default_product_id, self::PURCHASE_PRICE_META_KEY, round((float) $normalized_value, 2));
    }

    private function get_default_language_product_id($product_id) {
        $product_id = (int) $product_id;

        if (!$product_id) {
            return 0;
        }

        $default_language = apply_filters('wpml_default_language', null);
        if (!$default_language) {
            return $product_id;
        }

        $default_product_id = apply_filters('wpml_object_id', $product_id, 'product', true, $default_language);

        return $default_product_id ? (int) $default_product_id : $product_id;
    }

    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        global $pagenow;
        global $post;
        if (
            $screen->post_type === 'factures' &&
            $screen->base === 'post'
        ) {
            wp_enqueue_style(
                'fs-admin-style',
                FS_FACTURE_PLUGIN_URL . 'assets/styles/fs-styles.css',
                [],
                '1.0.3'
            );

            if (
                ($this->get('action') && $this->get('action') === 'edit') || 
                ($pagenow === 'post-new.php' && ($this->get('post_type') && $this->get('post_type') === 'factures'))
            ) {
                wp_register_style('fs-inline-admin-style', false);
                wp_enqueue_style('fs-inline-admin-style');

                $custom_css = '
                    #minor-publishing-actions,
                    .misc-pub-visibility,
                    .misc-pub-curtime,
                    .misc-pub-post-status {
                        display:none;
                    }
                ';
                wp_add_inline_style('fs-inline-admin-style', $custom_css);
            }
            
            if ($this->get('action') && $this->get('action') === 'edit') {
                wp_register_style('fs-inline-admin-style', false);
                wp_enqueue_style('fs-inline-admin-style');

                $edit_css = '
                    #minor-publishing .misc-pub-post-status, 
                    #minor-publishing .edit-post-status {
                        display:block!important;
                    }
                    #minor-publishing .edit-post-status {
                        display:none!important;
                    }
                    /*#major-publishing-actions {
                        display:none!important;
                    }*/
                ';
                if($post->post_status === 'facture_current' || $post->post_status === 'publish') {
                   $edit_css .= '
                        #publishing-action,
                        .edit-post-status,
                        #minor-publishing .misc-pub-post-status {
                            display:none!important;
                        }
                    '; 
                }
                wp_add_inline_style('fs-inline-admin-style', $edit_css);

            }
        }
    }

    public function save_to_logs($data) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(__DIR__ . '/../logs/wp-posts-export.json', $json);
    }

    public function process_facture_products($post_id) {
        global $post;

        if (
            wp_is_post_autosave($post_id) ||
            wp_is_post_revision($post_id)
        ) {
            return;
        }

        if (get_post_type($post_id) !== 'factures') {
            return;
        }

        $facture = get_post($post_id);
        $facture_data = get_field('facture_group', $post_id);

        if (!$facture || !$facture_data) {
            error_log('FS Facture: No facture data found in facture ID ' . $post_id);
            return;
        }

        $event = get_post_meta($post_id, '_fs_facture_document_saved_once', true) ? 'updated' : 'created';
        $payload = $this->build_facture_document_payload($facture, $facture_data, $event);

        /**
         * Filters the normalized facture payload before consumers receive it.
         *
         * This hook is intentionally integration-neutral. Erply, accounting,
         * inventory, CRM, or other integrations can all listen to the same
         * document event without coupling FS Facture to a specific plugin.
         */
        $payload = apply_filters('facture_document_payload', $payload, $post_id, $facture);

        /**
         * Fires after a facture document has been saved through ACF.
         *
         * @param array    $payload Normalized facture data plus raw ACF groups.
         * @param int      $post_id Facture post ID.
         * @param \WP_Post $facture Facture post object.
         */
        do_action('facture_document_saved', $payload, $post_id, $facture);

        update_post_meta($post_id, '_fs_facture_document_saved_once', current_time('mysql'));

        if($facture->post_status === 'facture_corrective') {
            return;
        }

        if (!isset($facture_data['products_group']['products_list'])) {
            error_log('FS Facture: No product items found in facture ID ' . $post_id);
            return;
        }

        $facture_year = date('Y', strtotime(get_post($post_id)->post_date));
        $general_group = isset($facture_data['general_group']) ? $facture_data['general_group'] : [];
        $invoice_number = isset($general_group['id_facture']) ? $general_group['id_facture'] : $post_id . '/' . $facture_year;

        $products = [];
        $products_list = $facture_data['products_group']['products_list'];
        foreach ($products_list as $item) {
            if (!isset($item['product_item_facture']) || !isset($item['quantity_product_item_facture'])) {
                error_log('FS Facture: Missing product_item_facture or quantity_product_item_facture for item in facture ID ' . $post_id);
                continue;
            }

            $product_id = is_object($item['product_item_facture']) ? $item['product_item_facture']->ID : intval($item['product_item_facture']);
            $quantity = floatval($item['quantity_product_item_facture']);

            if ($product_id && $quantity > 0) {
                $products[] = [
                    'product_id' => strval($product_id),
                    'quantity' => $quantity,
                ];
            }
        }
        if (empty($products)) {
            error_log('FS Facture: No valid products to process for facture ID ' . $post_id);
            return;
        }

        $response = apply_filters('process_facture_products', $invoice_number, $products);

        if (is_array($response) && isset($response['status'])) {
            if ($response['status'] === 'error') {
                error_log('FS Facture: Dotypos stock update failed for facture ID ' . $post_id . ': ' . ($response['message'] ?? 'Unknown error'));
            } else {
                error_log('FS Facture: Dotypos stock updated successfully for facture ID ' . $post_id);
            }
        }
    }

    private function build_facture_document_payload($facture, $facture_data, $event) {
        $post_id = $facture->ID;
        $facture_year = date('Y', strtotime($facture->post_date));

        $general_group = isset($facture_data['general_group']) && is_array($facture_data['general_group']) ? $facture_data['general_group'] : [];
        $seller_group = isset($facture_data['seller_group']) && is_array($facture_data['seller_group']) ? $facture_data['seller_group'] : [];
        $buyer_group = isset($facture_data['buyer_group']) && is_array($facture_data['buyer_group']) ? $facture_data['buyer_group'] : [];
        $products_group = isset($facture_data['products_group']) && is_array($facture_data['products_group']) ? $facture_data['products_group'] : [];
        $payment_group = isset($facture_data['payment_group']) && is_array($facture_data['payment_group']) ? $facture_data['payment_group'] : [];
        $notes_group = isset($facture_data['notes_group']) && is_array($facture_data['notes_group']) ? $facture_data['notes_group'] : [];

        $invoice_number = !empty($general_group['id_facture']) ? (string) $general_group['id_facture'] : $post_id . '/' . $facture_year;
        $products = $this->normalize_facture_products($products_group);
        $totals = $this->calculate_facture_totals($products, $products_group);

        $payload = [
            'event' => $event,
            'source' => 'fs-facture',
            'document_type' => 'facture',
            'post' => [
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'status' => $facture->post_status,
                'created_at' => $facture->post_date,
                'modified_at' => $facture->post_modified,
                'year' => $facture_year,
                'is_corrective' => $facture->post_status === 'facture_corrective',
            ],
            'invoice' => [
                'number' => $invoice_number,
                'issue_date' => !empty($general_group['general_facture_date']) ? $general_group['general_facture_date'] : date('Y-m-d', strtotime($facture->post_date)),
                'sale_date' => !empty($general_group['general_date_sale']) ? $general_group['general_date_sale'] : '',
                'payment_date' => !empty($general_group['general_date_payment']) ? $general_group['general_date_payment'] : '',
                'place' => !empty($general_group['general_adress']) ? $general_group['general_adress'] : '',
                'currency' => 'PLN',
            ],
            'seller' => $seller_group,
            'buyer' => $buyer_group,
            'payment' => $payment_group,
            'notes' => $notes_group,
            'products' => $products,
            'totals' => $totals,
            'raw' => [
                'facture_group' => $facture_data,
            ],
        ];

        if ($facture->post_status === 'facture_corrective') {
            $payload['corrective'] = [
                'original_facture_data' => get_post_meta($post_id, 'cloned_acf_data', true),
            ];
        }

        return $payload;
    }

    private function normalize_facture_products($products_group) {
        $products = [];
        $products_list = isset($products_group['products_list']) && is_array($products_group['products_list']) ? $products_group['products_list'] : [];
        $global_discount = isset($products_group['discount_to_all_products']) ? floatval($products_group['discount_to_all_products']) : 0;

        foreach ($products_list as $index => $item) {
            if (!is_array($item) || empty($item['product_item_facture'])) {
                continue;
            }

            $product_post = is_object($item['product_item_facture']) ? $item['product_item_facture'] : get_post((int) $item['product_item_facture']);
            $product_id = $product_post ? (int) $product_post->ID : (int) $item['product_item_facture'];

            $wc_product = function_exists('wc_get_product') && $product_id ? wc_get_product($product_id) : false;
            $quantity = isset($item['quantity_product_item_facture']) ? floatval($item['quantity_product_item_facture']) : 0;
            $price = isset($item['price_product_item_facture']) ? floatval($item['price_product_item_facture']) : 0;
            $discount = isset($item['discount_product_item_facture']) ? floatval($item['discount_product_item_facture']) : 0;
            $vat_rate = isset($item['vat_rate_product_item_facture']) ? (string) $item['vat_rate_product_item_facture'] : '';
            $line_discounted_price = $this->apply_discount($price, $discount);
            $line_discounted_price = $this->apply_discount($line_discounted_price, $global_discount);
            $net_total = round($quantity * $line_discounted_price, 2);
            $vat_amount = is_numeric(str_replace(',', '.', $vat_rate)) ? round($net_total * floatval(str_replace(',', '.', $vat_rate)) / 100, 2) : 0;

            $products[] = [
                'row' => $index + 1,
                'wc_product_id' => $product_id,
                'sku' => $wc_product ? $wc_product->get_sku() : get_post_meta($product_id, '_sku', true),
                'name' => $product_post ? $product_post->post_title : '',
                'quantity' => $quantity,
                'price' => $price,
                'current_price' => isset($item['current_price_product_item_facture']) ? $item['current_price_product_item_facture'] : '',
                'stock_quantity' => isset($item['stock_product_item_facture']) ? $item['stock_product_item_facture'] : '',
                'gtu' => isset($item['gtu_product_item_facture']) ? (string) $item['gtu_product_item_facture'] : '',
                'vat_rate' => $vat_rate,
                'discount' => $discount,
                'global_discount' => $global_discount,
                'net_total' => $net_total,
                'vat_amount' => $vat_amount,
                'gross_total' => round($net_total + $vat_amount, 2),
                'raw' => $item,
            ];
        }

        return $products;
    }

    private function calculate_facture_totals($products, $products_group) {
        $totals = [
            'net' => 0.0,
            'vat' => 0.0,
            'gross' => 0.0,
            'discount_to_all_products' => isset($products_group['discount_to_all_products']) ? floatval($products_group['discount_to_all_products']) : 0,
            'by_vat_rate' => [],
        ];

        foreach ($products as $product) {
            $vat_key = (string) $product['vat_rate'];
            if (!isset($totals['by_vat_rate'][$vat_key])) {
                $totals['by_vat_rate'][$vat_key] = [
                    'net' => 0.0,
                    'vat' => 0.0,
                    'gross' => 0.0,
                ];
            }

            $totals['net'] += $product['net_total'];
            $totals['vat'] += $product['vat_amount'];
            $totals['gross'] += $product['gross_total'];

            $totals['by_vat_rate'][$vat_key]['net'] += $product['net_total'];
            $totals['by_vat_rate'][$vat_key]['vat'] += $product['vat_amount'];
            $totals['by_vat_rate'][$vat_key]['gross'] += $product['gross_total'];
        }

        $totals['net'] = round($totals['net'], 2);
        $totals['vat'] = round($totals['vat'], 2);
        $totals['gross'] = round($totals['gross'], 2);

        foreach ($totals['by_vat_rate'] as $rate => $sum) {
            $totals['by_vat_rate'][$rate]['net'] = round($sum['net'], 2);
            $totals['by_vat_rate'][$rate]['vat'] = round($sum['vat'], 2);
            $totals['by_vat_rate'][$rate]['gross'] = round($sum['gross'], 2);
        }

        return $totals;
    }

    private function apply_discount($price, $discount) {
        $price = floatval($price);
        $discount = floatval($discount);

        if ($discount <= 0) {
            return $price;
        }

        return round($price - ($price * $discount / 100), 2);
    }
}
