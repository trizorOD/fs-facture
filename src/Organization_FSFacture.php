<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Organization_FSFacture extends AbstractClassFSFacture {
    const CPT_SLUG = 'fs_organization';

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('init', [$this, 'register_post_type']);
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
}
