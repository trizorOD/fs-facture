<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Organization_FSFacture extends AbstractClassFSFacture {
    const CPT_SLUG = 'fs_organization';
    const AJAX_ACTION_AUTOFILL = 'fs_facture_get_organization_data';

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_' . self::AJAX_ACTION_AUTOFILL, [$this, 'ajax_get_organization_data']);
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
        if (!$screen || $screen->post_type !== 'factures' || $screen->base !== 'post') {
            return;
        }

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
