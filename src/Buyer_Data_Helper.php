<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

class Buyer_Data_Helper {
    public static function get_facture_ids() {
        return get_posts([
            'post_type' => 'factures',
            'post_status' => ['publish', 'facture_current', 'facture_corrective'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
    }

    public static function get_facture_data($post_id) {
        return function_exists('get_field') ? get_field('facture_group', $post_id) : [];
    }

    public static function get_facture_date($facture, $facture_data) {
        $general_group = isset($facture_data['general_group']) && is_array($facture_data['general_group'])
            ? $facture_data['general_group']
            : [];
        $date = !empty($general_group['general_facture_date'])
            ? $general_group['general_facture_date']
            : $facture->post_date;

        return self::normalize_date($date);
    }

    public static function normalize_date($date) {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    public static function get_buyer_group_key($buyer_group) {
        if (!is_array($buyer_group)) {
            return '';
        }

        $nip = isset($buyer_group['buyer_nip']) ? self::clean_tax_id($buyer_group['buyer_nip']) : '';
        if ($nip !== '') {
            return 'nip_' . $nip;
        }

        $organization = isset($buyer_group['buyer_organization'])
            ? self::normalize_organization_key($buyer_group['buyer_organization'])
            : '';

        if ($organization === '') {
            return '';
        }

        return 'org_' . md5($organization);
    }

    public static function clean_tax_id($value) {
        return preg_replace('/\D+/', '', (string) $value);
    }

    public static function normalize_organization_label($value) {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    public static function normalize_organization_key($value) {
        $value = trim((string) $value);

        if (preg_match('/\R/', $value)) {
            $lines = preg_split('/\R/u', $value);
            $has_address_line = false;

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/\d{2}-\d{3}|\d/', $line)) {
                    $has_address_line = true;
                    break;
                }
            }

            if ($has_address_line && trim($lines[0]) !== '') {
                $value = $lines[0];
            }
        }

        $value = self::normalize_organization_label($value);
        $value = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}
