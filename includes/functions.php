<?php

use NumberToWords\NumberToWords;



if (!function_exists('print_r_e')) { 

    function print_r_e($arr) {

        echo "<pre class='print'>";

        print_r($arr);

        echo "</pre>";

    }

}



if (!function_exists('get_data_arr')) {

    function get_data_arr($key, $arr = []) {

        return !empty($arr) && !empty($key) && isset($arr[$key]) && !empty($arr[$key]) ? $arr[$key] : false;

    }

}



if (!function_exists('check_data_arr')) {

    function check_data_arr($key, $arr = []) {

        return !empty($arr) && !empty($key) && isset($arr[$key]) && !empty($arr[$key]) ? true : false;

    }

}



if (!function_exists('fs_price_format')) {

    function fs_price_format($price, $currency = '') {

        if(!empty($currency)) {

            return number_format((float)$price, 2, ',', ' ').' '.$currency;

        }

        return number_format((float)$price, 2, ',', ' ');

    }



}



function priceToPolishWords(string $price): string {

    $price = str_replace(' ', '', $price);

    $price = str_replace(',', '.', $price);



    $parts = explode('.', $price);

    $integerPart = (int)$parts[0];

    $fractionPart = $parts[1] ?? '00';



    $fractionPart = substr($fractionPart . '00', 0, 2);



    $numberToWords = new NumberToWords();

    $numberTransformer = $numberToWords->getNumberTransformer('pl');



    $integerWords = $numberTransformer->toWords($integerPart);



    return $integerWords . ' i 0,' . $fractionPart;

}

add_action('admin_init', function () {
    if (
        !isset($_GET['action']) ||
        $_GET['action'] !== 'clone_facture_post' ||
        !isset($_GET['post']) ||
        !current_user_can('edit_posts') ||
        !wp_verify_nonce($_GET['_wpnonce'], 'clone_facture_post')
    ) {
        return;
    }

    $post_id = (int) $_GET['post'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'factures') {
        wp_die("Don't clone this post");
    }

    $new_post = [
        'post_title'     => $post->post_title . ' (Corrective)',
        'post_content'   => $post->post_content,
        'post_excerpt'   => $post->post_excerpt,
        'post_status'    => 'facture_corrective',
        'post_type'      => $post->post_type,
        'post_author'    => get_current_user_id(),
    ];
    $new_post_id = wp_insert_post($new_post);

    $meta = get_post_meta($post_id);

    $data_fields = get_field('facture_group', $post_id);
    update_post_meta($new_post_id, 'cloned_acf_data', $data_fields);

    foreach ($meta as $key => $values) {
        foreach ($values as $value) {
            update_post_meta($new_post_id, $key, maybe_unserialize($value));
        }
    }

    /*$taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        wp_set_object_terms($new_post_id, $terms, $taxonomy);
    }*/

    wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
    exit;
});



