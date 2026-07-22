<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Organization_FSFacture extends AbstractClassFSFacture {
    const CPT_SLUG = 'fs_organization';
    const AJAX_ACTION_AUTOFILL = 'fs_facture_get_organization_data';
    const AJAX_ACTION_IMPORT = 'fs_facture_import_organizations';
    const IMPORT_PAGE_SLUG = 'fs-facture-organization-import';

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_menu', [$this, 'register_import_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_' . self::AJAX_ACTION_AUTOFILL, [$this, 'ajax_get_organization_data']);
        add_action('wp_ajax_' . self::AJAX_ACTION_IMPORT, [$this, 'ajax_import_organizations']);
    }

    public function register_post_type() {
        $labels = [
            'name'               => __('Organizations', 'fs-facture'),
            'singular_name'      => __('Organization', 'fs-facture'),
            'menu_name'          => __('Organizations', 'fs-facture'),
            'name_admin_bar'     => __('Organization', 'fs-facture'),
            'add_new'            => __('Add Organization', 'fs-facture'),
            'add_new_item'       => __('Add Organization', 'fs-facture'),
            'new_item'           => __('New Organization', 'fs-facture'),
            'edit_item'          => __('Edit Organization', 'fs-facture'),
            'view_item'          => __('View Organization', 'fs-facture'),
            'all_items'          => __('All Organizations', 'fs-facture'),
            'search_items'       => __('Find Organizations', 'fs-facture'),
            'not_found'          => __('No organizations found', 'fs-facture'),
            'not_found_in_trash' => __('No organizations found in the trash', 'fs-facture'),
        ];

        register_post_type(self::CPT_SLUG, [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-building',
            'supports'           => ['title'],
            'show_in_rest'       => false,
        ]);
    }

    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();

        if ($screen && $screen->post_type === 'factures' && $screen->base === 'post') {
            $script_path = dirname(__DIR__) . '/assets/acf/organization/organization.js';

            wp_enqueue_script(
                'fs-facture-organization-admin-script',
                FS_FACTURE_PLUGIN_URL . 'assets/acf/organization/organization.js',
                ['jquery'],
                file_exists($script_path) ? filemtime($script_path) : '1.0.0',
                true
            );

            wp_localize_script('fs-facture-organization-admin-script', 'fsFactureOrganization', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::AJAX_ACTION_AUTOFILL),
                'action'  => self::AJAX_ACTION_AUTOFILL,
            ]);
        }

        if ($hook === self::CPT_SLUG . '_page_' . self::IMPORT_PAGE_SLUG) {
            $script_path = dirname(__DIR__) . '/assets/admin/organization-import.js';

            wp_enqueue_script(
                'fs-facture-organization-import-script',
                FS_FACTURE_PLUGIN_URL . 'assets/admin/organization-import.js',
                ['jquery'],
                file_exists($script_path) ? filemtime($script_path) : '1.0.0',
                true
            );

            wp_localize_script('fs-facture-organization-import-script', 'fsFactureOrganizationImport', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::AJAX_ACTION_IMPORT),
                'action'  => self::AJAX_ACTION_IMPORT,
                'i18n'    => [
                    'loading' => __('Importing…', 'fs-facture'),
                    'done'    => __('Done. Created: %created%, skipped (already in directory): %skipped%.', 'fs-facture'),
                    'error'   => __('Import failed.', 'fs-facture'),
                ],
            ]);
        }
    }

    public function register_import_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT_SLUG,
            __('Import from Factures', 'fs-facture'),
            __('Import from Factures', 'fs-facture'),
            'edit_posts',
            self::IMPORT_PAGE_SLUG,
            [$this, 'render_import_page']
        );
    }

    public function render_import_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'fs-facture'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import Organizations from Factures', 'fs-facture'); ?></h1>
            <p><?php esc_html_e('Scans all factures and creates an organization record for each unique buyer that is not already in the directory. Existing organizations are never modified.', 'fs-facture'); ?></p>
            <button type="button" class="button button-primary" id="fs-facture-import-organizations">
                <?php esc_html_e('Start Import', 'fs-facture'); ?>
            </button>
            <p id="fs-facture-import-result"></p>
        </div>
        <?php
    }

    public function ajax_import_organizations() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('No permission.', 'fs-facture')], 403);
        }

        check_ajax_referer(self::AJAX_ACTION_IMPORT, 'nonce');

        $groups = [];

        foreach (Buyer_Data_Helper::get_facture_ids() as $post_id) {
            $facture = get_post($post_id);
            $facture_data = Buyer_Data_Helper::get_facture_data($post_id);

            if (!$facture || empty($facture_data)) {
                continue;
            }

            $buyer_group = isset($facture_data['buyer_group']) && is_array($facture_data['buyer_group'])
                ? $facture_data['buyer_group']
                : [];
            $key = Buyer_Data_Helper::get_buyer_group_key($buyer_group);

            if ($key === '') {
                continue;
            }

            $facture_date = Buyer_Data_Helper::get_facture_date($facture, $facture_data);

            if (!isset($groups[$key]) || $facture_date > $groups[$key]['date']) {
                $groups[$key] = [
                    'date' => $facture_date,
                    'buyer_group' => $buyer_group,
                ];
            }
        }

        $existing_keys = [];
        $existing_ids = get_posts([
            'post_type' => self::CPT_SLUG,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($existing_ids as $org_id) {
            $key = Buyer_Data_Helper::get_buyer_group_key([
                'buyer_nip' => get_field('org_nip', $org_id),
                'buyer_organization' => get_the_title($org_id),
            ]);

            if ($key !== '') {
                $existing_keys[$key] = true;
            }
        }

        $created = 0;
        $skipped = 0;

        foreach ($groups as $key => $group) {
            if (isset($existing_keys[$key])) {
                $skipped++;
                continue;
            }

            $buyer_group = $group['buyer_group'];
            $label = isset($buyer_group['buyer_organization'])
                ? Buyer_Data_Helper::normalize_organization_label($buyer_group['buyer_organization'])
                : '';

            if ($label === '') {
                $skipped++;
                continue;
            }

            $org_id = wp_insert_post([
                'post_type'   => self::CPT_SLUG,
                'post_title'  => $label,
                'post_status' => 'publish',
            ]);

            if (!$org_id || is_wp_error($org_id)) {
                $skipped++;
                continue;
            }

            update_field('org_nip', $buyer_group['buyer_nip'] ?? '', $org_id);
            update_field('org_country_code', $buyer_group['buyer_country_code'] ?? '', $org_id);
            update_field('org_street', $buyer_group['buyer_street'] ?? ($buyer_group['buyer_address'] ?? ''), $org_id);
            update_field('org_city', $buyer_group['buyer_city'] ?? '', $org_id);
            update_field('org_postal_code', $buyer_group['buyer_postal_code'] ?? '', $org_id);

            $created++;
        }

        wp_send_json_success([
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function ajax_get_organization_data() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('No permission.', 'fs-facture')], 403);
        }

        check_ajax_referer(self::AJAX_ACTION_AUTOFILL, 'nonce');

        $organization_id = isset($_POST['organization_id']) ? intval($_POST['organization_id']) : 0;

        if (!$organization_id || get_post_type($organization_id) !== self::CPT_SLUG) {
            wp_send_json_error(['message' => __('Organization not found.', 'fs-facture')]);
        }

        wp_send_json_success([
            'organization' => get_the_title($organization_id),
            'nip'          => (string) get_field('org_nip', $organization_id),
            'country_code' => (string) get_field('org_country_code', $organization_id),
            'street'       => (string) get_field('org_street', $organization_id),
            'city'         => (string) get_field('org_city', $organization_id),
            'postal_code'  => (string) get_field('org_postal_code', $organization_id),
        ]);
    }
}
