<?php
    global $post;

    //print_r_e($cloned_acf_data);
    //exit();

    $general_group = get_data_arr('general_group', $data);
    

    $seller_group = get_data_arr('seller_group', $data);

    $buyer_group = get_data_arr('buyer_group', $data);

    $products_group = get_data_arr('products_group', $data);

    $payment_group = get_data_arr('payment_group', $data);

    $products_list = get_data_arr('products_list', $products_group);

    $notes_group = get_data_arr('notes_group', $data);
    $currency = 'zł';

    $order_by_vat_rate = []; 

    $total_order_price = 0;
    $total_order_price_before_discount = 0;

    $total_order_price_with_payed = 0;

    $vat_rate = 0;



    $discount = 0;

    if(isset($products_group['discount_to_all_products']) && $products_group['discount_to_all_products'] > 0) {

        $discount = $products_group['discount_to_all_products'];

    }
    
    if($products_list && !empty($products_list)) {

        foreach($products_list as $product_key => $product) {

            $sale_price = $product['price_product_item_facture'];

            if(

                isset($product['discount_product_item_facture']) &&

                check_data_arr('price_product_item_facture', $product) &&

                check_data_arr('quantity_product_item_facture', $product)

            ) {

                $sale_price = $product['price_product_item_facture'] - ($product['price_product_item_facture'] * ($product['discount_product_item_facture'] / 100));

            }

            $products_list[$product_key]['total_product_price'] = $product['quantity_product_item_facture'] * $sale_price;

            /*if(check_data_arr('vat_rate_product_item_facture', $product)) {

                if(isset($order_by_vat_rate[$product['vat_rate_product_item_facture']])) {

                    $order_by_vat_rate[$product['vat_rate_product_item_facture']] += $products_list[$product_key]['total_product_price'];

                } else {

                    $order_by_vat_rate[$product['vat_rate_product_item_facture']] = $products_list[$product_key]['total_product_price'];

                }

            }*/ 

            $total_order_price += $products_list[$product_key]['total_product_price'];

            $products_list[$product_key]['total_product_price_brutto'] = $products_list[$product_key]['total_product_price'];
            
            if(check_data_arr('vat_rate_product_item_facture', $product)) {
                $products_list[$product_key]['total_product_price_brutto'] = (($product['vat_rate_product_item_facture'] / 100) * $products_list[$product_key]['total_product_price_brutto']) + $products_list[$product_key]['total_product_price_brutto'];
            }

            if(!$vat_rate && check_data_arr('vat_rate_product_item_facture', $product)) {
                $vat_rate = $product['vat_rate_product_item_facture'];
            }
            

        }

    }

    $total_order_price_before_discount = $total_order_price;
    $total_order_price_before_discount_without_rate = $total_order_price;

    $vat_rate_price = ($vat_rate / 100) * $total_order_price_before_discount;
    $total_order_price_before = $total_order_price_before_discount;
    $total_order_price_before_discount = $total_order_price_before_discount + $vat_rate_price;

    $total_order_price = $total_order_price_before_discount;

    if($discount > 0) {

        $total_order_price = $total_order_price - ($total_order_price * ($discount / 100));

    }


    $total_order_price_with_payed = $total_order_price;

    $payed_price = 0;

    if(check_data_arr('payment_payed', $payment_group)) {
        $total_order_price_with_payed = $total_order_price - $payment_group['payment_payed'];
        $payed_price = $payment_group['payment_payed'];
    }

    //Calculate on cloned order

    $products_list_cloned = false;
    $total_order_clone_price = 0;
    if(
        $facture_corrective_status &&
        check_data_arr('products_group', $cloned_acf_data) && 
        check_data_arr('products_list', $cloned_acf_data['products_group'])
    ) {
        $products_group_clone = $cloned_acf_data['products_group'];

        $discount_clone = 0;
        if(isset($products_group_clone['discount_to_all_products']) && $products_group_clone['discount_to_all_products'] > 0) {
            $discount_clone = $products_group_clone['discount_to_all_products'];
        }

        $products_list_cloned = $products_group_clone['products_list'];
        foreach($products_list_cloned as $product_key => $product) {
            $sale_price = $product['price_product_item_facture'];
            if(
                isset($product['discount_product_item_facture']) &&
                check_data_arr('price_product_item_facture', $product) &&
                check_data_arr('quantity_product_item_facture', $product)

            ) {
                $sale_price = $product['price_product_item_facture'] - ($product['price_product_item_facture'] * ($product['discount_product_item_facture'] / 100));
            }
            $products_list_cloned[$product_key]['total_product_price'] = $product['quantity_product_item_facture'] * $sale_price;
            $total_order_clone_price += $products_list_cloned[$product_key]['total_product_price'];

            $products_list_cloned[$product_key]['total_product_price_brutto'] = $products_list_cloned[$product_key]['total_product_price'];
            
            if(check_data_arr('vat_rate_product_item_facture', $product)) {
                $products_list_cloned[$product_key]['total_product_price_brutto'] = (($product['vat_rate_product_item_facture'] / 100) * $products_list_cloned[$product_key]['total_product_price_brutto']) + $products_list_cloned[$product_key]['total_product_price_brutto'];
            }
        }

        $total_order_cloned_price_discount = $total_order_clone_price;
        $total_order_price_current_discount = $total_order_price_before_discount_without_rate;

        if($discount_clone > 0) {
            $total_order_cloned_price_discount = $total_order_cloned_price_discount - ($total_order_cloned_price_discount * ($discount_clone / 100));
        }
        if($discount > 0) {
            $total_order_price_current_discount = $total_order_price_current_discount - ($total_order_price_current_discount * ($discount / 100));
        }

        $total_order_price_before = $total_order_price_current_discount - $total_order_cloned_price_discount;

        $vat_rate_price = ($vat_rate / 100) * $total_order_price_before;
        $total_order_price_before_discount = $vat_rate_price + $total_order_price_before;

    }

?>



<!DOCTYPE html>

<html>

    <head>

        <meta charset="UTF-8">

        <title><?php echo $document_name ?></title>

            <style>

                body {

                    color: #040404;

                    background:#ffffff;

                    font-size:12px;

                    font-family: 'roboto', sans-serif;

                }

                .wrap-in {

                    padding-left:20px;

                    padding-right:20px;

                }

                .f-number {

                    border-bottom:2px solid #89989B;

                }

                .f-date {

                    text-align:right;

                    padding:10px 0;

                }

                .f-head-number {

                   color:#646464;

                   font-size:32px;

                   text-align:center;

                }

                table {

                    width:100%;

                }

                .td-f-logo {

                    width:10%;

                }

                .td-f-head-number {

                    width:90%;

                    border-top:2px solid #89989B;

                    border-bottom:2px solid #89989B;

                    text-align:center;

                }

                .f-date-sale, .f-date-payment, .f-note-reason {

                    text-align:center;

                    font-weight:bold;

                    font-size:12px;

                }

                .header_bottom {

                    margin-top:5px;

                }

                .text-right {

                    text-align:right;

                }

                .thead td {

                    font-weight:bold;

                }

                table {

                    width:100%;

                }

                .f-contacts td {

                    width:50%;

                }

                .p {

                    margin-bottom:20px;

                }

                .f-products-title, .f-total-title, .f-payment-title {

                    font-weight:bold;

                    margin-bottom:10px;

                }

                .f-products, .f-total, .f-payment {

                    margin-top:35px;

                }

                .f-table th td {

                  color: #6C6470;

                }

                .f-table {

                    border-collapse: collapse;

                }

                .f-products-name {

                    font-size:12px;

                    line-height:20px;

                }

                .pr-td {

                    border-bottom: 2px solid #90989A;

                }

                .f-table tr:nth-child(2) .pr-td {

                    border-top: 2px solid #90989A;

                }

                .f-table th td, .f-table th {

                  color: #6C6470;

                }

                .sign-company {

                    font-weight:bold;

                    font-size:14px;

                    line-height:25px;

                }

                .f-sign-first-td {

                    width:70%;

                }

                .f-sign-last-td {

                    width:30%;

                }

                .f-sign-table {

                    margin-top:20px;

                }

                .f-table tr:nth-child(even) {

                    background:#B0B0B0;

                }

                



            </style>

        </style>

    </head>     

    <body>

        <div class="header">

            <div class="header_top">

                <div class="f-number"><?php esc_html_e('Faktura VAT numer', 'fs-facture') ?> <?php echo $document_name ?></div>

                <?php if(

                    check_data_arr('general_adress', $general_group) &&

                    check_data_arr('general_facture_date', $general_group)

                ) { ?>

                    <div class="f-date wrap-in"><?php esc_html_e('Wystawiono dnia', 'fs-facture') ?> <?php echo $general_group['general_facture_date'] ?> <?php echo $general_group['general_adress'] ?>.</div>

                <?php } ?>

            </div>  

            <div class="header_middle">

                <table width="100%">

                    <tr>

                        <td class="td-f-logo"><div class="f-logo"><img src="<?php echo FS_FACTURE_PLUGIN_URL ?>assets/images/f-logo.jpg" alt="logo" /></div></td>

                        <td class="td-f-head-number"><div class="f-head-number" style="width: 100%;">
                            <?php if($facture_corrective_status) { ?>
                                <?php esc_html_e('Faktura korygująca numer', 'fs-facture') ?> <?php echo $document_name ?> 
                                <?php esc_html_e(' do ', 'fs-facture') ?><br><?php esc_html_e('faktury numer', 'fs-facture') ?> <?php echo $document_name_main ?> </div>
                            <?php } else { ?>
                                <?php esc_html_e('Faktura VAT numer', 'fs-facture') ?> <?php echo $document_name ?></div>
                            <?php } ?>    

                        </td>

                    </tr>

                </table>

            </div>  

            <div class="header_bottom">

                <?php if(check_data_arr('general_date_sale', $general_group)) { ?>

                    <div class="f-date-sale"><?php esc_html_e('Data sprzedaży', 'fs-facture') ?>: <?php echo $general_group['general_date_sale'] ?></div>

                <?php } ?>

                <?php if(check_data_arr('general_date_payment', $general_group)) { ?>

                    <div class="f-date-payment"><?php esc_html_e('Termin płatności', 'fs-facture') ?>: <?php echo $general_group['general_date_payment'] ?></div>

                <?php } ?>

                <?php if($facture_corrective_status && check_data_arr('notes_corrective_reason', $notes_group)) { ?>
                    <div class="f-note-reason"><?php esc_html_e('Powód') ?>: <?php echo $notes_group['notes_corrective_reason'] ?></div>
                <?php } ?>

            </div>

        </div>

        <div class="content">

            <div class="f-contacts">

                <table width="100%">

                    <tbody>

                        <tr class="thead">

                            <td width="50%"><?php esc_html_e('Sprzedawca', 'fs-facture') ?>:</td>

                            <td width="50%" class="text-right"><?php esc_html_e('Nabywca', 'fs-facture') ?>:</td>

                        </tr>

                        <tr>

                            <td width="50%" class="f-contacts-seller" valign="top">

                                <table width="100%" cellpadding="1" cellspacing="0">

                                    <?php  if(check_data_arr('seller_organization', $seller_group)) { ?>

                                        <tr><td class="f-seller-organization"><?php echo $seller_group['seller_organization'] ?></td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('seller_street', $seller_group)) { ?>

                                        <tr><td class="f-seller-street"><?php echo $seller_group['seller_street'] ?></td></tr>

                                    <?php } elseif(check_data_arr('seller_adress', $seller_group)) { ?>

                                        <tr><td class="f-seller-adderss"><?php echo $seller_group['seller_adress'] ?></td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('seller_postal_code', $seller_group) || check_data_arr('seller_city', $seller_group)) { ?>

                                        <tr><td class="f-seller-city">
                                            <?php echo check_data_arr('seller_postal_code', $seller_group) ? $seller_group['seller_postal_code'].' ' : '' ?>
                                            <?php echo check_data_arr('seller_city', $seller_group) ? $seller_group['seller_city'] : '' ?>
                                        </td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('seller_country_code', $seller_group)) { ?>

                                        <tr><td class="f-seller-country"><?php esc_html_e('Kraj', 'fs-facture') ?>: <?php echo $seller_group['seller_country_code'] ?></td></tr>

                                    <?php } ?>

                                    <?php  if(check_data_arr('seller_nip', $seller_group)) { ?>

                                        <tr><td class="f-seller-nip"><?php esc_html_e('NIP', 'fs-facture') ?>: <?php echo $seller_group['seller_nip'] ?></td></tr>

                                    <?php } ?>

                                    <?php  if(check_data_arr('seller_regon', $seller_group)) { ?>

                                        <tr><td class="f-seller-regon"><?php esc_html_e('REGON', 'fs-facture') ?>: <?php echo $seller_group['seller_regon'] ?></td></tr>

                                    <?php } ?>

                                    <?php  if(check_data_arr('seller_email', $seller_group)) { ?>

                                        <tr><td class="f-seller-email"><?php esc_html_e('Email', 'fs-facture') ?>: <?php echo $seller_group['seller_email'] ?></td></tr>

                                    <?php } ?>

                                    <?php  if(check_data_arr('seller_phone', $seller_group)) { ?>

                                        <tr><td class="f-seller-phone"><?php esc_html_e('Numer telefonu', 'fs-facture') ?>: <?php echo $seller_group['seller_phone'] ?></td></tr>

                                    <?php } ?>

                                    </tr>

                                </table>

                            </td>

                            <td width="50%" class="text-right f-contacts-buyer" valign="top">

                                <table width="100%">

                                    <?php  if(check_data_arr('buyer_organization', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-organization"><?php echo $buyer_group['buyer_organization'] ?></td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('buyer_street', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-street"><?php echo $buyer_group['buyer_street'] ?></td></tr>

                                    <?php } elseif(check_data_arr('buyer_address', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-adderss"><?php echo $buyer_group['buyer_address'] ?></td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('buyer_postal_code', $buyer_group) || check_data_arr('buyer_city', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-city">
                                            <?php echo check_data_arr('buyer_postal_code', $buyer_group) ? $buyer_group['buyer_postal_code'].' ' : '' ?>
                                            <?php echo check_data_arr('buyer_city', $buyer_group) ? $buyer_group['buyer_city'] : '' ?>
                                        </td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('buyer_nip', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-nip"><?php esc_html_e('NIP', 'fs-facture') ?>: <?php echo $buyer_group['buyer_nip'] ?></td></tr>

                                    <?php } ?>

                                    <?php if(check_data_arr('buyer_country_code', $buyer_group)) { ?>

                                        <tr><td class="f-buyer-country"><?php esc_html_e('Kraj', 'fs-facture') ?>: <?php echo $buyer_group['buyer_country_code'] ?></td></tr>

                                    <?php } ?>

                                </table>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <?php if($facture_corrective_status && $products_list_cloned) { ?>

                <?php if(!empty($products_list)) { ?>

                    <div class="f-products">

                        <div class="f-products-title"><?php esc_html_e('Było', 'fs-facture') ?>:</div>

                        <table class="f-table" cellpadding="5">

                            <tr>

                                <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Lp.', 'fs-facture') ?></td></tr></table></th>

                                <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Nazwa towaru lub usługi', 'fs-facture') ?></td></tr></table></th>

                                <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('PKWiU', 'fs-facture') ?></td></tr></table></th>

                                <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('GTU', 'fs-facture') ?></td></tr></table></th>

                                <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Ilość', 'fs-facture') ?></td></tr></table></th>

                                <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Jedn.', 'fs-facture') ?></td></tr></table></th>

                                <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Cena jedn. netto', 'fs-facture') ?></td></tr></table></th>

                                <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Wartość netto', 'fs-facture') ?></td></tr></table></th>

                                <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Stawka VAT', 'fs-facture') ?></td></tr></table></th> 

                                <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Wartość brutto', 'fs-facture') ?></td></tr></table></th> 

                            </tr>

                            <?php foreach($products_list_cloned as $product_key => $product) { ?>

                                <?php $even =  (($product_key + 1) % 2 !== 0) ? 'even' : ''; ?>

                                <?php $first_td = $product_key === 0 ? 'first-td' : ''; ?>

                                <tr class="<?php echo $even ?> <?php echo $first_td ?>">

                                    <td class="pr-td" align="center"><?php echo $product_key + 1 ?></td>

                                    <td class="pr-td f-products-name" align="left">

                                        <?php if(check_data_arr('product_item_facture', $product)) { ?>

                                            <?php echo $product['product_item_facture']->post_title ?>

                                        <?php } ?>

                                    </td>

                                    <td class="pr-td"></td>

                                    <td class="pr-td" align="center"><?php echo !empty($product['gtu_product_item_facture']) ? esc_html($product['gtu_product_item_facture']) : '' ?></td>

                                    <td class="pr-td" align="center">

                                        <?php if(
                                        isset ($product['quantity_product_item_facture']) &&
                                            (!empty($product['quantity_product_item_facture']) || $product['quantity_product_item_facture'] == 0)
                                        ) { ?>

                                            <?php echo $product['quantity_product_item_facture'] ?>.00

                                        <?php } ?>

                                    </td>

                                    <td class="pr-td" align="center">szt.</td>

                                    <td class="pr-td" align="center">

                                        <?php if(
                                            isset ($product['price_product_item_facture']) &&
                                            (!empty($product['price_product_item_facture']) || $product['price_product_item_facture'] == 0)
                                        ) { ?>
                                            <?php echo fs_price_format($product['price_product_item_facture'], $currency) ?>

                                        <?php } ?>

                                    </td>

                                    <td class="pr-td" align="center">

                                        <?php if(
                                            isset ($product['total_product_price']) &&
                                            (!empty($product['total_product_price']) || $product['total_product_price'] == 0)
                                        ) { ?>

                                            <?php

                                                //$sale_price = $product['discount_product_item_facture'] !== 0 ? $product['price_product_item_facture'] - ($product['price_product_item_facture'] * ($product['discount_product_item_facture'] / 100)) : $product['price_product_item_facture'];

                                                echo fs_price_format($product['total_product_price'], $currency)

                                            ?>

                                        <?php } ?>

                                    </td>

                                    <td class="pr-td" align="center">

                                        <?php if(check_data_arr('vat_rate_product_item_facture', $product)) { ?>

                                            <?php echo $product['vat_rate_product_item_facture'] ?>%

                                        <?php } ?>

                                    </td>

                                    <td class="pr-td" align="center">

                                        <?php if(check_data_arr('vat_rate_product_item_facture', $product)) { ?>

                                            <?php echo fs_price_format($product['total_product_price_brutto'], $currency) ?>

                                        <?php } ?>

                                    </td>

                                </tr>

                            <?php } ?>

                        </table>

                    </div>

                <?php } ?>
            <?php } ?>

            <?php if(!empty($products_list)) { ?>

                <div class="f-products">

                    <?php if($facture_corrective_status && $products_list_cloned) { ?>
                        <div class="f-products-title"><?php esc_html_e('Powinno być', 'fs-facture') ?>:</div>
                    <?php } else { ?>
                        <div class="f-products-title"><?php esc_html_e('Elementy', 'fs-facture') ?>:</div>
                    <?php } ?>    

                    <table class="f-table" cellpadding="5">

                        <tr>

                            <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Lp.', 'fs-facture') ?></td></tr></table></th>

                            <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Nazwa towaru lub usługi', 'fs-facture') ?></td></tr></table></th>

                            <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('PKWiU', 'fs-facture') ?></td></tr></table></th>

                            <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('GTU', 'fs-facture') ?></td></tr></table></th>

                            <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Ilość', 'fs-facture') ?></td></tr></table></th>

                            <th align="left"><table cellpadding="10"><tr><td><?php esc_html_e('Jedn.', 'fs-facture') ?></td></tr></table></th>

                            <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Cena jedn. netto', 'fs-facture') ?></td></tr></table></th>

                            <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Wartość netto', 'fs-facture') ?></td></tr></table></th>

                            <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Stawka VAT', 'fs-facture') ?></td></tr></table></th> 

                            <th align="center"><table cellpadding="10"><tr><td><?php esc_html_e('Wartość brutto', 'fs-facture') ?></td></tr></table></th> 

                        </tr>

                        <?php foreach($products_list as $product_key => $product) { ?>

                            <?php $even =  (($product_key + 1) % 2 !== 0) ? 'even' : ''; ?>

                            <?php $first_td = $product_key === 0 ? 'first-td' : ''; ?>

                            <tr class="<?php echo $even ?> <?php echo $first_td ?>">

                                <td class="pr-td" align="center"><?php echo $product_key + 1 ?></td>

                                <td class="pr-td f-products-name" align="left">

                                    <?php if(check_data_arr('product_item_facture', $product)) { ?>

                                        <?php echo $product['product_item_facture']->post_title ?>

                                    <?php } ?>

                                </td>

                                <td class="pr-td"></td>

                                <td class="pr-td" align="center"><?php echo !empty($product['gtu_product_item_facture']) ? esc_html($product['gtu_product_item_facture']) : '' ?></td>

                                <td class="pr-td" align="center">

                                    <?php if(
                                       isset ($product['quantity_product_item_facture']) &&
                                        (!empty($product['quantity_product_item_facture']) || $product['quantity_product_item_facture'] == 0)
                                    ) { ?>

                                        <?php echo $product['quantity_product_item_facture'] ?>.00

                                    <?php } ?>

                                </td>

                                <td class="pr-td" align="center">szt.</td>

                                <td class="pr-td" align="center">

                                    <?php if(
                                        isset ($product['price_product_item_facture']) && 
                                        (!empty($product['price_product_item_facture']) || $product['price_product_item_facture'] == 0)
                                    ) { ?>
                                        <?php echo fs_price_format($product['price_product_item_facture'], $currency) ?>

                                    <?php } ?>

                                </td>

                                <td class="pr-td" align="center">

                                    <?php if(
                                        isset ($product['total_product_price']) && 
                                        (!empty($product['total_product_price']) || $product['total_product_price'] == 0)
                                    ) { ?>

                                        <?php

                                            //$sale_price = $product['discount_product_item_facture'] !== 0 ? $product['price_product_item_facture'] - ($product['price_product_item_facture'] * ($product['discount_product_item_facture'] / 100)) : $product['price_product_item_facture'];

                                            echo fs_price_format($product['total_product_price'], $currency)

                                        ?>

                                    <?php } ?>

                                </td>

                                <td class="pr-td" align="center">

                                    <?php if(check_data_arr('vat_rate_product_item_facture', $product)) { ?>

                                        <?php echo $product['vat_rate_product_item_facture'] ?>%

                                    <?php } ?>

                                </td>

                                <td class="pr-td" align="center">

                                    <?php if(check_data_arr('vat_rate_product_item_facture', $product)) { ?>
                                        
                                        <?php echo fs_price_format($product['total_product_price_brutto'], $currency) ?>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                    </table>

                </div>

            <?php } ?>

            <div class="f-total">

                <div class="f-total-title"><?php esc_html_e('Podsumowanie:', 'fs-facture') ?></div>

                <table class="f-table" cellpadding="5">
                    <tr>
                        <th width="30%"></th> 
                        <th align="right"><?php esc_html_e('Wartość netto', 'fs-facture') ?></th>
                        <th align="right"><?php esc_html_e('Stawka VAT', 'fs-facture') ?></th>
                        <th align="right"><?php esc_html_e('Kwota VAT', 'fs-facture') ?></th>
                        <th align="right"><?php esc_html_e('Wartość brutto', 'fs-facture') ?></th>
                    </tr>
                    <tr>
                        <td width="30%"></td> 
                        <td align="right"><?php echo fs_price_format($total_order_price_before, $currency) ?></td>
                        <td align="right"><?php echo $vat_rate ?>%</td>
                        <td align="right"><?php echo fs_price_format($vat_rate_price, $currency) ?></td>
                        <td align="right"><?php echo fs_price_format($total_order_price_before_discount, $currency) ?></td>
                    </tr>
                    <tr>
                        <td class="pr-td" align="left"><?php esc_html_e('RAZEM', 'fs-facture') ?></td>
                        <td class="pr-td" align="left"></td>
                        <td class="pr-td" align="left"></td>
                        <td class="pr-td" align="left"></td>
                        <td class="pr-td" align="right"><?php echo fs_price_format($total_order_price_before_discount, $currency) ?></td>
                    </tr>
                </table>
                <table class="f-table" style="margin-top:-12px" cellpadding="5">

                    <tr>

                        <th align="left"></th>

                        <th align="right"></th>

                    </tr>

                    <?php if($facture_corrective_status && $products_list_cloned) { ?>
                        <?php
                            $type_text = $total_order_price_before > 0 ? 'zwiększająca ' : 'zmniejszająca' ;
                        ?>
                        <tr class="first-td">
                            <td class="pr-td" align="left"><?php esc_html_e('Kwota '.$type_text.' podstawę opodatkowania', 'fs-facture') ?></td>
                            <td class="pr-td" align="right"><?php echo fs_price_format(abs($total_order_price_before), $currency) ?></td>
                        </tr>
                        <tr>
                            <td class="pr-td" align="left"><?php esc_html_e('Kwota '.$type_text.' podatek VAT', 'fs-facture') ?></td>
                            <td class="pr-td" align="right"><?php echo fs_price_format(abs($vat_rate_price), $currency) ?></td>
                        </tr>
                    <?php } ?>    


                    <?php if($discount > 0) { ?>

                        <tr class="first-td">

                            <td class="pr-td" align="left"><?php esc_html_e('Rabat', 'fs-facture') ?></td>

                            <td class="pr-td" align="right"><?php echo $discount ?>%</td>

                        </tr>

                    <?php } ?>

                    <!--<tr>

                        <td class="pr-td" align="left"><?php esc_html_e('RAZEM', 'fs-facture') ?></td>

                        <td class="pr-td" align="right"><?php echo fs_price_format($total_order_price, $currency) ?></td>

                    </tr>-->

                    <tr>

                        <td class="pr-td" align="left"><?php esc_html_e('Zapłacono', 'fs-facture') ?></td>

                        <td class="pr-td" align="right"><?php echo fs_price_format($payed_price, $currency) ?></td>

                    </tr>

                    <tr>

                        <td class="pr-td" align="left"><?php esc_html_e('Pozostało do zapłaty', 'fs-facture') ?></td>

                        <td class="pr-td" align="right"><?php echo fs_price_format($total_order_price_with_payed, $currency) ?></td>

                    </tr>

                    <tr>

                        <td class="pr-td" align="left"><?php esc_html_e('Słownie', 'fs-facture') ?></td>

                        <td class="pr-td" align="right"><?php echo priceToPolishWords(fs_price_format($total_order_price_with_payed)).' '.$currency ?></td>

                    </tr>

                    <tr>

                        <td class="pr-td" align="left"><?php esc_html_e('Numer konta', 'fs-facture') ?></td>

                        <td class="pr-td" align="right">

                            <?php if(check_data_arr('payment_account_number', $payment_group)) { ?>

                                <?php echo $payment_group['payment_account_number'] ?>

                            <?php } ?>

                        </td>

                    </tr>

                </table>

            </div>

            <div class="f-payment">

                <div class="f-payment-title"><?php esc_html_e('Płatności:', 'fs-facture') ?></div>

                <table class="f-table" cellpadding="5">

                    <tr>

                        <th align="left"><?php esc_html_e('Zapłacono', 'fs-facture') ?></th>

                        <th align="center"><?php esc_html_e('Forma płatności', 'fs-facture') ?></th>

                        <th align="center"><?php esc_html_e('Termin płatności', 'fs-facture') ?></th>

                        <th align="right"><?php esc_html_e('Kwota', 'fs-facture') ?></th>

                    </tr>

                    <tr class="even first-td">

                        <td class="pr-td">

                            <?php if(isset($payment_group['payment_status_pay'])) { ?>

                                <?php echo $payment_group['payment_status_pay'] == 1 ? esc_html('Tak', 'fs-facture') : esc_html('Nie', 'fs-facture') ?>

                            <?php } ?>    

                        </td>

                        <td align="center" class="pr-td">

                            <?php if(check_data_arr('payment_method', $payment_group)) { ?>

                                <?php
                                    $payment_method_labels = [
                                        '1' => 'Gotówka',
                                        '2' => 'Karta',
                                        '3' => 'Bon',
                                        '4' => 'Czek',
                                        '5' => 'Kredyt',
                                        '6' => 'Przelew',
                                        '7' => 'Mobilna',
                                    ];
                                    $method_code = $payment_group['payment_method'];
                                    echo isset($payment_method_labels[$method_code]) ? $payment_method_labels[$method_code] : esc_html($method_code);
                                ?>

                            <?php } ?>

                        </td>

                        <td class="pr-td" align="center">

                            <?php if(check_data_arr('general_date_payment', $general_group)) { ?>

                                <?php echo $general_group['general_date_payment'] ?>

                            <?php } ?>

                        </td>

                        <td class="pr-td" align="right"><?php echo fs_price_format($total_order_price_with_payed, $currency) ?></td>

                    </tr>

                </table>

                <?php if(check_data_arr('general_sign_company', $general_group)) { ?>

                    <table class="f-table f-sign-table" cellpadding="10">

                        <tr>

                            <td valign="top" class="f-sign-first-td"><?php esc_html_e('Faktura bez podpisu odbiorcy', 'fs-facture') ?></td>

                            <td class="f-sign-last-td" align="center">

                                <?php esc_html_e('Osoba upoważniona do wystawienia faktury VAT', 'fs-facture') ?>

                                <table cellpadding="1"><tr><td align="center"><div class="sign-company"><?php echo $general_group['general_sign_company'] ?></div></td></tr></table>

                            </td>

                        </tr>

                    </table>

                <?php } ?>

            </div>    

        </div>

    </body>

</html>