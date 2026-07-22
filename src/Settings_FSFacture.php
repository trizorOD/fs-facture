<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_FSFacture extends AbstractClassFSFacture {
    const PAGE_SLUG = 'fs-facture-settings';
    const IMPORT_TAG_NAME = 'Import';
    const IMPORT_TAG_SLUG = 'import';
    const AJAX_SEARCH_ACTION = 'fs_facture_import_product_search';
    const AJAX_ADD_ACTION = 'fs_facture_import_product_add';
    const AJAX_REMOVE_ACTION = 'fs_facture_import_product_remove';

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_' . self::AJAX_SEARCH_ACTION, [$this, 'ajax_search_products']);
        add_action('wp_ajax_' . self::AJAX_ADD_ACTION, [$this, 'ajax_add_import_product']);
        add_action('wp_ajax_' . self::AJAX_REMOVE_ACTION, [$this, 'ajax_remove_import_product']);
    }

    public function register_admin_page() {
        add_submenu_page(
            'edit.php?post_type=factures',
            __('Settings', 'fs-facture'),
            __('Settings', 'fs-facture'),
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

        wp_enqueue_style(
            'fs-facture-margin-admin',
            FS_FACTURE_PLUGIN_URL . 'assets/styles/fs-margin.css',
            [],
            '1.0.8'
        );

        wp_enqueue_script(
            'fs-facture-settings-admin',
            FS_FACTURE_PLUGIN_URL . 'assets/admin/settings.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('fs-facture-settings-admin', 'fsFactureSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::PAGE_SLUG),
            'actions' => [
                'search' => self::AJAX_SEARCH_ACTION,
                'add' => self::AJAX_ADD_ACTION,
                'remove' => self::AJAX_REMOVE_ACTION,
            ],
            'i18n' => [
                'placeholder' => __('Search product...', 'fs-facture'),
                'searching' => __('Searching products...', 'fs-facture'),
                'inputTooShort' => __('Type at least 2 characters.', 'fs-facture'),
                'empty' => __('No products found.', 'fs-facture'),
                'emptyList' => __('No products have the Import tag yet.', 'fs-facture'),
                'addError' => __('Could not add the product.', 'fs-facture'),
                'removeError' => __('Could not remove the product.', 'fs-facture'),
                'confirmRemove' => __('Remove Import tag from this product?', 'fs-facture'),
            ],
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'fs-facture'));
        }

        $products = $this->get_import_products();
        ?>
        <div class="wrap fs-margin-page fs-settings-page">
            <div class="fs-margin-hero">
                <div>
                    <p class="fs-margin-eyebrow"><?php esc_html_e('FS Facture settings', 'fs-facture'); ?></p>
                    <h1><?php esc_html_e('Settings', 'fs-facture'); ?></h1>
                    <p><?php esc_html_e('Manage products marked with the Import tag and use this group as a report filter.', 'fs-facture'); ?></p>
                </div>
                <div class="fs-margin-hero-stat">
                    <span id="fs-settings-import-count"><?php echo esc_html(number_format_i18n(count($products))); ?></span>
                    <small><?php esc_html_e('import products', 'fs-facture'); ?></small>
                </div>
            </div>

            <div class="fs-margin-toolbar fs-settings-panel">
                <label for="fs-settings-product-search"><?php esc_html_e('Import products', 'fs-facture'); ?></label>
                <div class="fs-margin-controls fs-settings-add-row">
                    <select id="fs-settings-product-search"></select>
                    <button type="button" class="button button-primary" id="fs-settings-add-product" disabled>
                        <?php esc_html_e('Add', 'fs-facture'); ?>
                    </button>
                </div>
                <p class="description"><?php esc_html_e('Search products that do not have the Import tag yet, then add them to the group.', 'fs-facture'); ?></p>
            </div>

            <div class="fs-margin-message" id="fs-settings-message" hidden></div>

            <div class="fs-margin-table-wrap fs-settings-table-wrap">
                <table class="widefat striped fs-margin-table fs-settings-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('SKU', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Status', 'fs-facture'); ?></th>
                            <th><?php esc_html_e('Action', 'fs-facture'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="fs-settings-products">
                        <?php if (empty($products)) : ?>
                            <tr class="fs-settings-empty-row">
                                <td colspan="4"><?php esc_html_e('No products have the Import tag yet.', 'fs-facture'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($products as $product) : ?>
                                <?php echo $this->render_product_row($product); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function ajax_search_products() {
        $this->guard_ajax_request();

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        if (strlen($term) < 2) {
            wp_send_json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $import_term = $this->get_import_term(false);
        $tax_query = [];
        if ($import_term) {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => [(int) $import_term->term_id],
                'operator' => 'NOT IN',
            ];
        }

        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            's' => $term,
            'posts_per_page' => 20,
            'paged' => $page,
            'fields' => 'ids',
            'tax_query' => $tax_query,
            'no_found_rows' => false,
        ]);

        $results = [];
        foreach ($query->posts as $product_id) {
            $product = $this->format_product((int) $product_id);
            if (!$product) {
                continue;
            }

            $results[] = [
                'id' => $product['id'],
                'text' => $product['label'],
            ];
        }

        wp_send_json([
            'results' => $results,
            'pagination' => [
                'more' => $page < (int) $query->max_num_pages,
            ],
        ]);
    }

    public function ajax_add_import_product() {
        $this->guard_ajax_request();

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$this->is_valid_product($product_id)) {
            wp_send_json_error(['message' => __('Product not found.', 'fs-facture')], 404);
        }

        $term = $this->get_import_term(true);
        if (!$term) {
            wp_send_json_error(['message' => __('Could not create Import tag.', 'fs-facture')], 500);
        }

        $result = wp_set_object_terms($product_id, [(int) $term->term_id], 'product_tag', true);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        clean_object_term_cache($product_id, 'product');
        wp_send_json_success([
            'product' => $this->format_product($product_id),
            'row' => $this->render_product_row($this->format_product($product_id)),
            'count' => count($this->get_import_products()),
        ]);
    }

    public function ajax_remove_import_product() {
        $this->guard_ajax_request();

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        if (!$this->is_valid_product($product_id)) {
            wp_send_json_error(['message' => __('Product not found.', 'fs-facture')], 404);
        }

        $term = $this->get_import_term(false);
        if ($term) {
            $result = wp_remove_object_terms($product_id, [(int) $term->term_id], 'product_tag');
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()], 500);
            }
            clean_object_term_cache($product_id, 'product');
        }

        wp_send_json_success([
            'product_id' => $product_id,
            'count' => count($this->get_import_products()),
        ]);
    }

    private function guard_ajax_request() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('No permission.', 'fs-facture')], 403);
        }

        check_ajax_referer(self::PAGE_SLUG, 'nonce');
    }

    private function get_import_term($create = false) {
        $term = get_term_by('name', self::IMPORT_TAG_NAME, 'product_tag');
        if (!$term) {
            $term = get_term_by('slug', self::IMPORT_TAG_SLUG, 'product_tag');
        }

        if ($term || !$create) {
            return $term ?: null;
        }

        $created = wp_insert_term(self::IMPORT_TAG_NAME, 'product_tag', [
            'slug' => self::IMPORT_TAG_SLUG,
        ]);

        if (is_wp_error($created) || empty($created['term_id'])) {
            return null;
        }

        return get_term((int) $created['term_id'], 'product_tag');
    }

    private function get_import_products() {
        $term = $this->get_import_term(false);
        if (!$term) {
            return [];
        }

        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'product_tag',
                    'field' => 'term_id',
                    'terms' => [(int) $term->term_id],
                ],
            ],
        ]);

        $products = [];
        foreach ($ids as $product_id) {
            $product = $this->format_product((int) $product_id);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }

    private function format_product($product_id) {
        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'product') {
            return null;
        }

        $wc_product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
        $sku = $wc_product ? $wc_product->get_sku() : get_post_meta($product_id, '_sku', true);
        $status = get_post_status_object($post->post_status);
        $label = $post->post_title;
        if ($sku) {
            $label .= ' - SKU: ' . $sku;
        }

        return [
            'id' => (int) $product_id,
            'name' => $post->post_title,
            'sku' => $sku,
            'status' => $status ? $status->label : $post->post_status,
            'label' => $label . ' (#' . (int) $product_id . ')',
        ];
    }

    private function render_product_row($product) {
        if (!$product) {
            return '';
        }

        ob_start();
        ?>
        <tr data-product-id="<?php echo esc_attr($product['id']); ?>">
            <td>
                <strong><?php echo esc_html($product['name']); ?></strong>
                <small>ID <?php echo esc_html($product['id']); ?></small>
            </td>
            <td><?php echo esc_html($product['sku'] ?: '-'); ?></td>
            <td><?php echo esc_html($product['status']); ?></td>
            <td>
                <button type="button" class="button fs-settings-remove-product" data-product-id="<?php echo esc_attr($product['id']); ?>">
                    <?php esc_html_e('Remove', 'fs-facture'); ?>
                </button>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function is_valid_product($product_id) {
        $post = $product_id ? get_post($product_id) : null;

        return $post && $post->post_type === 'product';
    }
}
