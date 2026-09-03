<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Margin_FSFacture extends AbstractClassFSFacture {
    const PAGE_SLUG = 'fs-facture-margin';
    const AJAX_ACTION = 'fs_facture_margin_report';
    const PURCHASE_PRICE_META_KEY = '_purchase_price_without_vat';

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_margin_report']);
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=factures',
            __('Margin Report', 'fs-facture'),
            __('Margin Report', 'fs-facture'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'factures_page_' . self::PAGE_SLUG) {
            return;
        }

        if (function_exists('acf_enqueue_scripts')) {
            acf_enqueue_scripts();
        }

        $style_path = dirname(__DIR__) . '/assets/styles/fs-margin.css';
        $script_path = dirname(__DIR__) . '/assets/admin/margin.js';

        wp_enqueue_style(
            'fs-facture-margin-admin',
            FS_FACTURE_PLUGIN_URL . 'assets/styles/fs-margin.css',
            [],
            file_exists($style_path) ? filemtime($style_path) : '1.0.7'
        );

        wp_enqueue_script(
            'fs-facture-margin-admin',
            FS_FACTURE_PLUGIN_URL . 'assets/admin/margin.js',
            ['jquery'],
            file_exists($script_path) ? filemtime($script_path) : '1.0.7',
            true
        );

        wp_localize_script('fs-facture-margin-admin', 'fsFactureMargin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::AJAX_ACTION),
            'action' => self::AJAX_ACTION,
            'i18n' => [
                'loading' => __('Collecting facture products...', 'fs-facture'),
                'empty' => __('No products found for this organization.', 'fs-facture'),
                'error' => __('Could not build the margin report.', 'fs-facture'),
            ],
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'fs-facture'));
        }

        $organizations = $this->get_buyer_organization_groups();
        ?>
        <div class="wrap fs-margin-page">
            <div class="fs-margin-hero">
                <div>
                    <p class="fs-margin-eyebrow"><?php esc_html_e('FS Facture analytics', 'fs-facture'); ?></p>
                    <h1><?php esc_html_e('Margin Report', 'fs-facture'); ?></h1>
                    <p><?php esc_html_e('Choose a buyer organization, set the facture date range, and calculate product margin from saved factures and purchase prices.', 'fs-facture'); ?></p>
                </div>
                <div class="fs-margin-hero-stat">
                    <span><?php echo esc_html(number_format_i18n(count($organizations))); ?></span>
                    <small><?php esc_html_e('buyer organizations', 'fs-facture'); ?></small>
                </div>
            </div>

            <div class="fs-margin-toolbar">
                <label for="fs-margin-organization"><?php esc_html_e('Buyer organization', 'fs-facture'); ?></label>
                <div class="fs-margin-controls">
                    <select id="fs-margin-organization">
                        <option value=""><?php esc_html_e('All organizations', 'fs-facture'); ?></option>
                        <?php foreach ($organizations as $organization) : ?>
                            <option value="<?php echo esc_attr($organization['key']); ?>">
                                <?php
                                echo esc_html($organization['label']);
                                if ($organization['variant_count'] > 1) {
                                    echo esc_html(' (' . $organization['variant_count'] . ' variants)');
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="fs-margin-date-field">
                        <span><?php esc_html_e('From', 'fs-facture'); ?></span>
                        <input type="date" id="fs-margin-date-from">
                    </div>
                    <div class="fs-margin-date-field">
                        <span><?php esc_html_e('To', 'fs-facture'); ?></span>
                        <input type="date" id="fs-margin-date-to">
                    </div>
                    <label class="fs-margin-checkbox-field" for="fs-margin-import-only">
                        <input type="checkbox" id="fs-margin-import-only" value="1">
                        <span><?php esc_html_e('Only import products', 'fs-facture'); ?></span>
                    </label>
                    <button type="button" class="button button-primary" id="fs-margin-run">
                        <?php esc_html_e('Build report', 'fs-facture'); ?>
                    </button>
                    <button type="button" class="button" id="fs-margin-copy" disabled>
                        <?php esc_html_e('Copy CSV', 'fs-facture'); ?>
                    </button>
                </div>
            </div>

            <div class="fs-margin-loader" id="fs-margin-loader" aria-live="polite" hidden>
                <span></span>
                <strong><?php esc_html_e('Collecting facture products...', 'fs-facture'); ?></strong>
            </div>

            <div class="fs-margin-message" id="fs-margin-message" hidden></div>

            <div class="fs-margin-summary" id="fs-margin-summary" hidden></div>
            <div class="fs-margin-details" id="fs-margin-details" hidden></div>

            <div class="fs-margin-table-wrap" id="fs-margin-table-wrap" hidden>
                <table class="widefat striped fs-margin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('SKU', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Qty', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Sale Net Price', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Avg sale gross', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Purchase net', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Revenue net', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Revenue gross', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Profit', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Margin', 'fs-facture'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fs-margin-results"></tbody>
                    <tfoot id="fs-margin-total"></tfoot>
                </table>
            </div>
        </div>
        <?php
    }

    public function ajax_margin_report() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('No permission.', 'fs-facture')], 403);
        }

        check_ajax_referer(self::AJAX_ACTION, 'nonce');

        $organization = isset($_POST['organization'])
            ? sanitize_text_field(wp_unslash($_POST['organization']))
            : '';
        $date_from = isset($_POST['date_from'])
            ? sanitize_text_field(wp_unslash($_POST['date_from']))
            : '';
        $date_to = isset($_POST['date_to'])
            ? sanitize_text_field(wp_unslash($_POST['date_to']))
            : '';
        $import_only = !empty($_POST['import_only']);

        $report = $this->build_margin_report($organization, $date_from, $date_to, $import_only);

        wp_send_json_success($report);
    }

    private function get_buyer_organization_groups() {
        $groups = [];

        foreach (Buyer_Data_Helper::get_facture_ids() as $post_id) {
            $facture_data = Buyer_Data_Helper::get_facture_data($post_id);
            $buyer_group = isset($facture_data['buyer_group']) && is_array($facture_data['buyer_group'])
                ? $facture_data['buyer_group']
                : [];
            $group_key = Buyer_Data_Helper::get_buyer_group_key($buyer_group);
            $organization = isset($buyer_group['buyer_organization']) ? Buyer_Data_Helper::normalize_organization_label($buyer_group['buyer_organization']) : '';

            if ($group_key === '' || $organization === '') {
                continue;
            }

            if (!isset($groups[$group_key])) {
                $groups[$group_key] = [
                    'key' => $group_key,
                    'label' => $organization,
                    'variants' => [],
                    'variant_count' => 0,
                    'facture_count' => 0,
                ];
            }

            if (strlen($organization) < strlen($groups[$group_key]['label'])) {
                $groups[$group_key]['label'] = $organization;
            }

            $groups[$group_key]['variants'][$organization] = $organization;
            $groups[$group_key]['facture_count']++;
        }

        foreach ($groups as $key => $group) {
            ksort($groups[$key]['variants'], SORT_NATURAL | SORT_FLAG_CASE);
            $groups[$key]['variants'] = array_values($groups[$key]['variants']);
            $groups[$key]['variant_count'] = count($groups[$key]['variants']);
        }

        uasort($groups, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return array_values($groups);
    }

    private function build_margin_report($organization = '', $date_from = '', $date_to = '', $import_only = false) {
        $factures = [];
        $facture_count = 0;
        $line_count = 0;
        $skipped_factures = 0;
        $buyer_details = [];
        $import_product_ids = $import_only ? $this->get_import_product_ids() : [];
        $date_from = Buyer_Data_Helper::normalize_date($date_from);
        $date_to = Buyer_Data_Helper::normalize_date($date_to);

        if ($date_from && $date_to && $date_from > $date_to) {
            $tmp = $date_from;
            $date_from = $date_to;
            $date_to = $tmp;
        }

        $facture_ids = Buyer_Data_Helper::get_facture_ids();
        $corrected_facture_signatures = $this->get_corrected_facture_signatures($facture_ids);
        $original_facture_dates = $this->get_original_facture_dates($facture_ids);

        foreach ($facture_ids as $post_id) {
            $facture = get_post($post_id);
            $facture_data = Buyer_Data_Helper::get_facture_data($post_id);

            if (!$facture || empty($facture_data)) {
                $skipped_factures++;
                continue;
            }

            $buyer_group = isset($facture_data['buyer_group']) && is_array($facture_data['buyer_group'])
                ? $facture_data['buyer_group']
                : [];
            $facture_date = $this->get_report_facture_date($facture, $facture_data, $original_facture_dates);

            if ($facture->post_status !== 'facture_corrective') {
                $facture_signature = $this->get_facture_signature($facture, $facture_data);
                if ($facture_signature !== '' && isset($corrected_facture_signatures[$facture_signature])) {
                    continue;
                }
            }

            if ($organization !== '' && !$this->buyer_matches_filter($organization, $buyer_group)) {
                continue;
            }

            if ($date_from && $facture_date && $facture_date < $date_from) {
                continue;
            }

            if ($date_to && $facture_date && $facture_date > $date_to) {
                continue;
            }

            $products_group = isset($facture_data['products_group']) && is_array($facture_data['products_group'])
                ? $facture_data['products_group']
                : [];
            $products_list = isset($products_group['products_list']) && is_array($products_group['products_list'])
                ? $products_group['products_list']
                : [];

            if (empty($products_list)) {
                continue;
            }

            $invoice_number = $this->get_invoice_number($facture, $facture_data);
            $buyer_label = isset($buyer_group['buyer_organization'])
                ? Buyer_Data_Helper::normalize_organization_label($buyer_group['buyer_organization'])
                : '';
            $global_discount = isset($products_group['discount_to_all_products'])
                ? $this->to_float($products_group['discount_to_all_products'])
                : 0.0;

            foreach ($products_list as $item) {
                if (!is_array($item) || empty($item['product_item_facture'])) {
                    continue;
                }

                $product_id = is_object($item['product_item_facture'])
                    ? (int) $item['product_item_facture']->ID
                    : (int) $item['product_item_facture'];

                if (!$product_id) {
                    continue;
                }

                if ($import_only && !isset($import_product_ids[$product_id])) {
                    continue;
                }

                if (!isset($factures[$post_id])) {
                    $factures[$post_id] = [
                        'id' => $post_id,
                        'invoice' => $invoice_number,
                        'date' => $facture_date,
                        'buyer' => $buyer_label,
                        'status' => $facture->post_status,
                        'is_corrective' => $facture->post_status === 'facture_corrective',
                        'products' => [],
                        'subtotal' => [
                            'quantity' => 0.0,
                            'purchase_cost' => 0.0,
                            'revenue' => 0.0,
                            'gross_revenue' => 0.0,
                            'profit' => 0.0,
                            'margin_percent' => 0.0,
                            'active_products' => 0,
                            'inactive_products' => 0,
                            'excluded_products' => 0,
                        ],
                        'line_count' => 0,
                    ];

                    $facture_count++;
                    if ($organization !== '') {
                        $this->add_buyer_details($buyer_details, $buyer_group, $facture, $facture_data, $facture_date);
                    }
                }

                $product_post = is_object($item['product_item_facture'])
                    ? $item['product_item_facture']
                    : get_post($product_id);
                $wc_product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;

                $quantity = isset($item['quantity_product_item_facture']) ? $this->to_float($item['quantity_product_item_facture']) : 0.0;
                $is_excluded_from_calculation = $quantity < 0;
                $sale_price = isset($item['price_product_item_facture']) ? $this->to_float($item['price_product_item_facture']) : 0.0;
                $discount = isset($item['discount_product_item_facture']) ? $this->to_float($item['discount_product_item_facture']) : 0.0;
                $vat_rate = isset($item['vat_rate_product_item_facture']) ? $this->to_float($item['vat_rate_product_item_facture']) : 0.0;
                $sale_price = $this->apply_discount($sale_price, $discount);
                $sale_price = $this->apply_discount($sale_price, $global_discount);
                $line_revenue = round($sale_price * $quantity, 2);
                $sale_price_gross = round($sale_price + ($sale_price * $vat_rate / 100), 2);
                $line_gross_revenue = round($sale_price_gross * $quantity, 2);

                $purchase_product_id = $this->get_default_language_product_id($product_id);
                $purchase_meta = get_post_meta($purchase_product_id, self::PURCHASE_PRICE_META_KEY, true);
                $has_purchase_price = $purchase_meta !== '' && is_numeric(str_replace(',', '.', (string) $purchase_meta));
                $purchase_price = $has_purchase_price ? $this->to_float($purchase_meta) : null;

                if (!isset($factures[$post_id]['products'][$product_id])) {
                    $factures[$post_id]['products'][$product_id] = [
                        'product_id' => $product_id,
                        'name' => $product_post ? $product_post->post_title : sprintf(__('Product #%d', 'fs-facture'), $product_id),
                        'sku' => $wc_product ? $wc_product->get_sku() : get_post_meta($product_id, '_sku', true),
                        'quantity' => 0.0,
                        'purchase_cost' => 0.0,
                        'revenue' => 0.0,
                        'gross_revenue' => 0.0,
                        'purchase_price' => $purchase_price,
                        'has_purchase_price' => $has_purchase_price,
                        'profit' => 0.0,
                        'excluded_from_calculation' => $is_excluded_from_calculation,
                        'line_count' => 0,
                    ];
                }

                $factures[$post_id]['products'][$product_id]['quantity'] += $quantity;
                $factures[$post_id]['products'][$product_id]['revenue'] += $line_revenue;
                $factures[$post_id]['products'][$product_id]['gross_revenue'] += $line_gross_revenue;
                $factures[$post_id]['products'][$product_id]['line_count']++;
                $factures[$post_id]['line_count']++;

                if ($is_excluded_from_calculation) {
                    $factures[$post_id]['products'][$product_id]['excluded_from_calculation'] = true;
                }

                if ($has_purchase_price && !$is_excluded_from_calculation) {
                    $factures[$post_id]['products'][$product_id]['has_purchase_price'] = true;
                    $factures[$post_id]['products'][$product_id]['purchase_price'] = $purchase_price;
                    $factures[$post_id]['products'][$product_id]['purchase_cost'] += round($purchase_price * $quantity, 2);
                    $factures[$post_id]['products'][$product_id]['profit'] += round(($sale_price - $purchase_price) * $quantity, 2);
                }

                $line_count++;
            }
        }

        $invoice_rows = [];
        $totals = [
            'quantity' => 0.0,
            'purchase_cost' => 0.0,
            'revenue' => 0.0,
            'gross_revenue' => 0.0,
            'profit' => 0.0,
            'inactive_products' => 0,
            'active_products' => 0,
            'excluded_products' => 0,
        ];
        $product_count = 0;

        foreach ($factures as $facture_id => $facture_row) {
            if (empty($facture_row['products'])) {
                continue;
            }

            $products = [];

            foreach ($facture_row['products'] as $product) {
                $quantity = round($product['quantity'], 4);
                $revenue = round($product['revenue'], 2);
                $gross_revenue = round($product['gross_revenue'], 2);
                $avg_sale_price = $quantity != 0 ? round($revenue / $quantity, 2) : 0.0;
                $avg_sale_price_gross = $quantity != 0 ? round($gross_revenue / $quantity, 2) : 0.0;
                $purchase_cost = round($product['purchase_cost'], 2);
                $profit = round($product['profit'], 2);
                $excluded_from_calculation = !empty($product['excluded_from_calculation']);
                $margin_percent = ($product['has_purchase_price'] && !$excluded_from_calculation && $revenue > 0)
                    ? round($profit / $revenue * 100, 2)
                    : null;

                if ($excluded_from_calculation) {
                    $facture_row['subtotal']['excluded_products']++;
                    $totals['excluded_products']++;
                } elseif ($product['has_purchase_price']) {
                    $facture_row['subtotal']['quantity'] += $quantity;
                    $facture_row['subtotal']['purchase_cost'] += $purchase_cost;
                    $facture_row['subtotal']['revenue'] += $revenue;
                    $facture_row['subtotal']['gross_revenue'] += $gross_revenue;
                    $facture_row['subtotal']['profit'] += $profit;
                    $facture_row['subtotal']['active_products']++;
                    $totals['quantity'] += $quantity;
                    $totals['purchase_cost'] += $purchase_cost;
                    $totals['revenue'] += $revenue;
                    $totals['gross_revenue'] += $gross_revenue;
                    $totals['profit'] += $profit;
                    $totals['active_products']++;
                } else {
                    $facture_row['subtotal']['inactive_products']++;
                    $totals['inactive_products']++;
                }

                $products[] = [
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'quantity' => $quantity,
                    'avg_sale_price' => $avg_sale_price,
                    'avg_sale_price_gross' => $avg_sale_price_gross,
                    'purchase_price' => $product['purchase_price'],
                    'has_purchase_price' => $product['has_purchase_price'],
                    'excluded_from_calculation' => $excluded_from_calculation,
                    'revenue' => $revenue,
                    'gross_revenue' => $gross_revenue,
                    'profit' => $profit,
                    'margin_percent' => $margin_percent,
                    'line_count' => $product['line_count'],
                ];

                $product_count++;
            }

            usort($products, function ($a, $b) {
                if ($a['has_purchase_price'] !== $b['has_purchase_price']) {
                    return $a['has_purchase_price'] ? -1 : 1;
                }

                return $b['profit'] <=> $a['profit'];
            });

            $facture_row['subtotal']['quantity'] = round($facture_row['subtotal']['quantity'], 4);
            $facture_row['subtotal']['purchase_cost'] = round($facture_row['subtotal']['purchase_cost'], 2);
            $facture_row['subtotal']['revenue'] = round($facture_row['subtotal']['revenue'], 2);
            $facture_row['subtotal']['gross_revenue'] = round($facture_row['subtotal']['gross_revenue'], 2);
            $facture_row['subtotal']['profit'] = round($facture_row['subtotal']['profit'], 2);
            $facture_row['subtotal']['margin_percent'] = $facture_row['subtotal']['revenue'] > 0
                ? round($facture_row['subtotal']['profit'] / $facture_row['subtotal']['revenue'] * 100, 2)
                : 0.0;
            $facture_row['products'] = $products;

            $invoice_rows[] = $facture_row;
        }

        usort($invoice_rows, function ($a, $b) {
            $date_compare = strcmp((string) $b['date'], (string) $a['date']);
            if ($date_compare !== 0) {
                return $date_compare;
            }

            return strcasecmp((string) $a['invoice'], (string) $b['invoice']);
        });

        $facture_count = count($invoice_rows);

        $totals['quantity'] = round($totals['quantity'], 4);
        $totals['purchase_cost'] = round($totals['purchase_cost'], 2);
        $totals['revenue'] = round($totals['revenue'], 2);
        $totals['gross_revenue'] = round($totals['gross_revenue'], 2);
        $totals['profit'] = round($totals['profit'], 2);
        $totals['margin_percent'] = $totals['revenue'] > 0
            ? round($totals['profit'] / $totals['revenue'] * 100, 2)
            : 0.0;

        return [
            'organization' => $organization,
            'buyer_details' => $this->format_buyer_details($buyer_details),
            'date_range' => [
                'from' => $date_from,
                'to' => $date_to,
            ],
            'filters' => [
                'import_only' => (bool) $import_only,
            ],
            'factures' => $invoice_rows,
            'rows' => [],
            'totals' => $totals,
            'meta' => [
                'facture_count' => $facture_count,
                'line_count' => $line_count,
                'product_count' => $product_count,
                'skipped_factures' => $skipped_factures,
            ],
        ];
    }

    private function get_import_product_ids() {
        $term = get_term_by('name', 'Import', 'product_tag');
        if (!$term) {
            $term = get_term_by('slug', 'import', 'product_tag');
        }

        if (!$term) {
            return [];
        }

        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'product_tag',
                    'field' => 'term_id',
                    'terms' => [(int) $term->term_id],
                ],
            ],
        ]);

        return array_fill_keys(array_map('intval', $ids), true);
    }

    private function get_corrected_facture_signatures($facture_ids) {
        $signatures = [];

        foreach ($facture_ids as $post_id) {
            $facture = get_post($post_id);

            if (!$facture || $facture->post_status !== 'facture_corrective') {
                continue;
            }

            $cloned_data = get_post_meta($post_id, 'cloned_acf_data', true);
            if (!is_array($cloned_data) || empty($cloned_data)) {
                continue;
            }

            $signature = $this->get_facture_signature($facture, $cloned_data);
            if ($signature !== '') {
                $signatures[$signature] = true;
            }
        }

        return $signatures;
    }

    /**
     * Maps each non-corrective facture's signature to its own facture date,
     * so corrective factures can look up the date of the invoice they correct.
     */
    private function get_original_facture_dates($facture_ids) {
        $dates = [];

        foreach ($facture_ids as $post_id) {
            $facture = get_post($post_id);

            if (!$facture || $facture->post_status === 'facture_corrective') {
                continue;
            }

            $facture_data = Buyer_Data_Helper::get_facture_data($post_id);
            if (empty($facture_data)) {
                continue;
            }

            $signature = $this->get_facture_signature($facture, $facture_data);
            if ($signature !== '') {
                $dates[$signature] = Buyer_Data_Helper::get_facture_date($facture, $facture_data);
            }
        }

        return $dates;
    }

    /**
     * A corrective facture should be reported under the date of the invoice
     * it corrects, not its own creation date.
     */
    private function get_report_facture_date($facture, $facture_data, $original_facture_dates) {
        if (!$facture) {
            return '';
        }

        if ($facture->post_status !== 'facture_corrective') {
            return Buyer_Data_Helper::get_facture_date($facture, $facture_data);
        }

        $original_data = get_post_meta($facture->ID, 'cloned_acf_data', true);
        if (is_array($original_data) && !empty($original_data)) {
            $original_signature = $this->get_facture_signature($facture, $original_data);

            if ($original_signature !== '' && isset($original_facture_dates[$original_signature])) {
                return $original_facture_dates[$original_signature];
            }

            $general_group = isset($original_data['general_group']) && is_array($original_data['general_group'])
                ? $original_data['general_group']
                : [];
            $original_date = !empty($general_group['general_facture_date'])
                ? Buyer_Data_Helper::normalize_date($general_group['general_facture_date'])
                : '';

            if ($original_date !== '') {
                return $original_date;
            }
        }

        return Buyer_Data_Helper::get_facture_date($facture, $facture_data);
    }

    private function get_facture_signature($facture, $facture_data) {
        if (!$facture || !is_array($facture_data)) {
            return '';
        }

        $buyer_group = isset($facture_data['buyer_group']) && is_array($facture_data['buyer_group'])
            ? $facture_data['buyer_group']
            : [];
        $buyer_key = Buyer_Data_Helper::get_buyer_group_key($buyer_group);
        $invoice_number = $this->get_invoice_number($facture, $facture_data);

        if ($buyer_key === '' || $invoice_number === '') {
            return '';
        }

        return strtolower(trim($invoice_number)) . '|' . $buyer_key;
    }

    private function get_invoice_number($facture, $facture_data) {
        $general_group = isset($facture_data['general_group']) && is_array($facture_data['general_group'])
            ? $facture_data['general_group']
            : [];

        if (!empty($general_group['id_facture'])) {
            return (string) $general_group['id_facture'];
        }

        return $facture->ID . '/' . date('Y', strtotime($facture->post_date));
    }

    private function buyer_matches_filter($filter, $buyer_group) {
        $filter = trim((string) $filter);

        if ($filter === '') {
            return true;
        }

        if (!is_array($buyer_group)) {
            return false;
        }

        $group_key = Buyer_Data_Helper::get_buyer_group_key($buyer_group);
        if ($group_key !== '' && hash_equals($group_key, $filter)) {
            return true;
        }

        $filter_tax_id = Buyer_Data_Helper::clean_tax_id($filter);
        $buyer_tax_id = isset($buyer_group['buyer_nip']) ? Buyer_Data_Helper::clean_tax_id($buyer_group['buyer_nip']) : '';
        if ($filter_tax_id !== '' && $buyer_tax_id !== '' && $filter_tax_id === $buyer_tax_id) {
            return true;
        }

        $buyer_organization = isset($buyer_group['buyer_organization'])
            ? Buyer_Data_Helper::normalize_organization_key($buyer_group['buyer_organization'])
            : '';
        $filter_organization = Buyer_Data_Helper::normalize_organization_key($filter);

        return $buyer_organization !== '' && $filter_organization !== '' && $buyer_organization === $filter_organization;
    }

    private function add_buyer_details(&$details, $buyer_group, $facture, $facture_data, $facture_date) {
        if (!is_array($buyer_group)) {
            return;
        }

        $organization = isset($buyer_group['buyer_organization']) ? Buyer_Data_Helper::normalize_organization_label($buyer_group['buyer_organization']) : '';
        $nip = isset($buyer_group['buyer_nip']) ? Buyer_Data_Helper::clean_tax_id($buyer_group['buyer_nip']) : '';
        $country = isset($buyer_group['buyer_country_code']) ? strtoupper(trim((string) $buyer_group['buyer_country_code'])) : '';
        $address_parts = [
            $buyer_group['buyer_street'] ?? ($buyer_group['buyer_address'] ?? ''),
            trim(($buyer_group['buyer_postal_code'] ?? '') . ' ' . ($buyer_group['buyer_city'] ?? '')),
        ];
        $address = Buyer_Data_Helper::normalize_organization_label(implode(' ', array_filter($address_parts)));
        $invoice = $this->get_invoice_number($facture, $facture_data);

        if ($organization !== '') {
            $details['organizations'][$organization] = $organization;
        }

        if ($nip !== '') {
            $details['nips'][$nip] = $nip;
        }

        if ($country !== '') {
            $details['countries'][$country] = $country;
        }

        if ($address !== '') {
            $details['addresses'][$address] = $address;
        }

        $details['factures'][$facture->ID] = [
            'id' => $facture->ID,
            'invoice' => $invoice,
            'date' => $facture_date,
        ];
    }

    private function format_buyer_details($details) {
        foreach (['organizations', 'nips', 'countries', 'addresses'] as $key) {
            if (!isset($details[$key])) {
                $details[$key] = [];
            }

            ksort($details[$key], SORT_NATURAL | SORT_FLAG_CASE);
            $details[$key] = array_values($details[$key]);
        }

        if (!isset($details['factures'])) {
            $details['factures'] = [];
        }

        $details['factures'] = array_values($details['factures']);

        usort($details['factures'], function ($a, $b) {
            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return [
            'organizations' => $details['organizations'],
            'nips' => $details['nips'],
            'countries' => $details['countries'],
            'addresses' => $details['addresses'],
            'factures' => $details['factures'],
        ];
    }

    private function apply_discount($price, $discount) {
        $price = (float) $price;
        $discount = (float) $discount;

        if ($discount <= 0) {
            return $price;
        }

        return round($price - ($price * $discount / 100), 2);
    }

    private function to_float($value) {
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return (float) $value;
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
}
