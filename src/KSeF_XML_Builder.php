<?php

namespace Finespirits\FsFacture;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds FA(3) XML invoices for KSeF submission.
 *
 * Schema: schemat_FA(3)_v1-0E.xsd
 * Namespace: http://crd.gov.pl/wzor/2025/06/25/13775/
 * Mandatory from: 2026-02-01
 */
class KSeF_XML_Builder {

    const NAMESPACE_FA3 = 'http://crd.gov.pl/wzor/2025/06/25/13775/';
    const NAMESPACE_ETD = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/';

    /**
     * VAT rate (%) → P_13_x / P_14_x index mapping.
     * 23/22% → 1, 8/7% → 2, 5% → 3, 4/3% → 4
     */
    private array $vat_rate_index = [
        23 => 1, 22 => 1,
        8  => 2, 7  => 2,
        5  => 3,
        4  => 4, 3  => 4,
    ];

    /**
     * Build FA(3) XML string from facture ACF data.
     *
     * @param int        $post_id       WP post ID of the facture
     * @param array      $data          ACF facture_group data
     * @param bool       $is_corrective Whether this is a corrective (KOR) invoice
     * @param array|null $cloned_data   ACF data from original invoice (for corrections)
     * @return string XML string
     */
    public function build(int $post_id, array $data, bool $is_corrective, ?array $cloned_data): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $post           = get_post($post_id);
        $general_group  = $data['general_group']  ?? [];
        $seller_group   = $data['seller_group']   ?? [];
        $buyer_group    = $data['buyer_group']     ?? [];
        $products_group = $data['products_group'] ?? [];
        $payment_group  = $data['payment_group']  ?? [];
        $notes_group    = $data['notes_group']    ?? [];

        $faktura = $dom->createElementNS(self::NAMESPACE_FA3, 'Faktura');
        $dom->appendChild($faktura);

        $this->append_naglowek($dom, $faktura);
        $this->append_podmiot1($dom, $faktura, $seller_group);
        $this->append_podmiot2($dom, $faktura, $buyer_group);
        $this->append_fa(
            $dom, $faktura, $post,
            $general_group, $products_group, $payment_group, $notes_group,
            $is_corrective, $cloned_data, $post_id
        );

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('KSeF XML Builder: failed to serialize XML.');
        }

        return $xml;
    }

    // ─── Header ──────────────────────────────────────────────────────────────

    private function append_naglowek(\DOMDocument $dom, \DOMElement $parent): void {
        $naglowek = $dom->createElement('Naglowek');
        $parent->appendChild($naglowek);

        $kod = $dom->createElement('KodFormularza', 'FA');
        $kod->setAttribute('kodSystemowy', 'FA (3)');
        $kod->setAttribute('wersjaSchemy', '1-0E');
        $naglowek->appendChild($kod);

        $naglowek->appendChild($dom->createElement('WariantFormularza', '3'));
        $naglowek->appendChild($dom->createElement('DataWytworzeniaFa', gmdate('Y-m-d\TH:i:s\Z')));
        $naglowek->appendChild($dom->createElement('SystemInfo', 'FS Facture 1.0.0'));
    }

    // ─── Podmiot1 (seller) ────────────────────────────────────────────────────

    private function append_podmiot1(\DOMDocument $dom, \DOMElement $parent, array $seller): void {
        $p1 = $dom->createElement('Podmiot1');
        $parent->appendChild($p1);

        $dane = $dom->createElement('DaneIdentyfikacyjne');
        $p1->appendChild($dane);

        $nip = preg_replace('/[^0-9]/', '', $seller['seller_nip'] ?? '');
        $dane->appendChild($dom->createElement('NIP', $nip));
        $dane->appendChild($dom->createElement('Nazwa', $this->sanitize_text($seller['seller_organization'] ?? '')));

        $adres = $dom->createElement('Adres');
        $p1->appendChild($adres);
        $adres->appendChild($dom->createElement('KodKraju', 'PL'));
        $adres->appendChild($dom->createElement('AdresL1', $this->sanitize_text($seller['seller_adress'] ?? '')));
    }

    // ─── Podmiot2 (buyer) ─────────────────────────────────────────────────────

    private function append_podmiot2(\DOMDocument $dom, \DOMElement $parent, array $buyer): void {
        $p2 = $dom->createElement('Podmiot2');
        $parent->appendChild($p2);

        $dane = $dom->createElement('DaneIdentyfikacyjne');
        $p2->appendChild($dane);

        $nip = preg_replace('/[^0-9]/', '', $buyer['buyer_nip'] ?? '');
        if (!empty($nip)) {
            $dane->appendChild($dom->createElement('NIP', $nip));
        } else {
            $dane->appendChild($dom->createElement('BrakID', '1'));
        }

        $name = $this->sanitize_text($buyer['buyer_organization'] ?? '');
        if (!empty($name)) {
            $dane->appendChild($dom->createElement('Nazwa', $name));
        }

        $address = $this->sanitize_text($buyer['buyer_address'] ?? '');
        if (!empty($address)) {
            $adres = $dom->createElement('Adres');
            $p2->appendChild($adres);
            $adres->appendChild($dom->createElement('KodKraju', 'PL'));
            $adres->appendChild($dom->createElement('AdresL1', $address));
        }

        $p2->appendChild($dom->createElement('JST', '2')); // not a local government unit
        $p2->appendChild($dom->createElement('GV', '2'));  // not a VAT group member
    }

    // ─── Fa (main invoice section) ────────────────────────────────────────────

    private function append_fa(
        \DOMDocument $dom,
        \DOMElement $parent,
        \WP_Post $post,
        array $general,
        array $products_group,
        array $payment,
        array $notes,
        bool $is_corrective,
        ?array $cloned_data,
        int $post_id
    ): void {
        $fa = $dom->createElement('Fa');
        $parent->appendChild($fa);

        // Currency
        $fa->appendChild($dom->createElement('KodWaluty', 'PLN'));

        // P_1 — issue date
        $raw_date   = $general['general_facture_date'] ?? '';
        $issue_date = !empty($raw_date) ? date('Y-m-d', strtotime($raw_date)) : date('Y-m-d', strtotime($post->post_date));
        $fa->appendChild($dom->createElement('P_1', $issue_date));

        // P_1M — issue place (optional)
        $place = $this->sanitize_text($general['general_adress'] ?? '');
        if (!empty($place)) {
            $fa->appendChild($dom->createElement('P_1M', $place));
        }

        // P_2 — invoice number
        $facture_year    = date('Y', strtotime($post->post_date));
        $invoice_number  = !empty($general['id_facture']) ? $general['id_facture'] : ($post->ID . '/' . $facture_year);
        $fa->appendChild($dom->createElement('P_2', $this->sanitize_text($invoice_number)));

        // P_6 — sale/delivery date (optional, if different from issue)
        $sale_date_raw = $general['general_date_sale'] ?? '';
        if (!empty($sale_date_raw)) {
            $fa->appendChild($dom->createElement('P_6', date('Y-m-d', strtotime($sale_date_raw))));
        }

        // Calculate totals grouped by VAT rate
        $products_list   = $products_group['products_list'] ?? [];
        $global_discount = isset($products_group['discount_to_all_products']) && $products_group['discount_to_all_products'] > 0
            ? floatval($products_group['discount_to_all_products']) : 0;

        $vat_groups  = [];
        $total_gross = 0.0;

        foreach ($products_list as $product) {
            $price     = floatval($product['price_product_item_facture'] ?? 0);
            $quantity  = floatval($product['quantity_product_item_facture'] ?? 0);
            $disc_pct  = floatval($product['discount_product_item_facture'] ?? 0);
            $vat_rate  = intval($product['vat_rate_product_item_facture'] ?? 0);

            $unit_after_disc = $price - ($price * $disc_pct / 100);
            $net             = $quantity * $unit_after_disc;

            if ($global_discount > 0) {
                $net -= $net * $global_discount / 100;
            }

            $net     = round($net, 2);
            $vat_amt = round($net * $vat_rate / 100, 2);
            $gross   = $net + $vat_amt;

            if (!isset($vat_groups[$vat_rate])) {
                $vat_groups[$vat_rate] = ['net' => 0.0, 'vat' => 0.0];
            }
            $vat_groups[$vat_rate]['net'] += $net;
            $vat_groups[$vat_rate]['vat'] += $vat_amt;
            $total_gross += $gross;
        }

        // Append P_13_x / P_14_x per VAT rate
        foreach ($vat_groups as $rate => $amounts) {
            $idx = $this->vat_rate_index[$rate] ?? null;
            if ($idx !== null) {
                $fa->appendChild($dom->createElement('P_13_' . $idx, $this->fmt_amount($amounts['net'])));
                $fa->appendChild($dom->createElement('P_14_' . $idx, $this->fmt_amount($amounts['vat'])));
            }
        }

        // P_15 — total gross
        $fa->appendChild($dom->createElement('P_15', $this->fmt_amount($total_gross)));

        // Adnotacje
        $this->append_adnotacje($dom, $fa);

        // RodzajFaktury
        $fa->appendChild($dom->createElement('RodzajFaktury', $is_corrective ? 'KOR' : 'VAT'));

        // Correction-specific elements
        if ($is_corrective && !empty($cloned_data)) {
            $this->append_correction_data($dom, $fa, $cloned_data, $notes, $post, $post_id);
        }

        // FaWiersz — line items
        $this->append_fa_wiersze($dom, $fa, $products_list, $global_discount);

        // Platnosc — payment terms
        $this->append_platnosc($dom, $fa, $payment, $general);
    }

    // ─── Adnotacje ────────────────────────────────────────────────────────────

    private function append_adnotacje(\DOMDocument $dom, \DOMElement $parent): void {
        $an = $dom->createElement('Adnotacje');
        $parent->appendChild($an);

        $an->appendChild($dom->createElement('P_16', '2'));  // no cash accounting method
        $an->appendChild($dom->createElement('P_17', '2'));  // no self-invoicing
        $an->appendChild($dom->createElement('P_18', '2'));  // no reverse charge
        $an->appendChild($dom->createElement('P_18A', '2')); // no split payment mechanism

        // Zwolnienie — no exempt transactions
        $zw = $dom->createElement('Zwolnienie');
        $an->appendChild($zw);
        $zw->appendChild($dom->createElement('P_19N', '1'));

        // NoweSrodkiTransportu — no new transport vehicles
        $nt = $dom->createElement('NoweSrodkiTransportu');
        $an->appendChild($nt);
        $nt->appendChild($dom->createElement('P_22', '2'));

        $an->appendChild($dom->createElement('P_23', '2')); // no simplified triangular procedure

        // PMarzy — no margin procedure
        $pm = $dom->createElement('PMarzy');
        $an->appendChild($pm);
        $pm->appendChild($dom->createElement('P_PMarzyN', '1'));
    }

    // ─── Correction data ──────────────────────────────────────────────────────

    private function append_correction_data(
        \DOMDocument $dom,
        \DOMElement $fa,
        array $cloned_data,
        array $notes,
        \WP_Post $post,
        int $post_id
    ): void {
        $reason = $this->sanitize_text($notes['notes_corrective_reason'] ?? '');
        if (!empty($reason)) {
            $fa->appendChild($dom->createElement('PrzyczynaKorekty', $reason));
        }

        // TypKorekty: 1=effective on original date, 2=effective on correction date
        $fa->appendChild($dom->createElement('TypKorekty', '2'));

        // DaneFaKorygowanej — reference to the original invoice
        $dane_kor = $dom->createElement('DaneFaKorygowanej');
        $fa->appendChild($dane_kor);

        $general_clone  = $cloned_data['general_group'] ?? [];
        $facture_year   = date('Y', strtotime($post->post_date));
        $original_number = !empty($general_clone['id_facture'])
            ? $general_clone['id_facture']
            : ($post->ID . '/' . $facture_year);

        $raw_date     = $general_clone['general_facture_date'] ?? '';
        $original_date = !empty($raw_date)
            ? date('Y-m-d', strtotime($raw_date))
            : date('Y-m-d', strtotime($post->post_date));

        $dane_kor->appendChild($dom->createElement('DataWystFaKorygowanej', $original_date));
        $dane_kor->appendChild($dom->createElement('NrFaKorygowanej', $this->sanitize_text($original_number)));

        // Look up if the original invoice has a KSeF number
        // The corrective post was cloned from a source post; find it by matching invoice number
        $original_ksef_number = $this->find_original_ksef_number($original_number, $post);

        if (!empty($original_ksef_number)) {
            $dane_kor->appendChild($dom->createElement('NrKSeF', '1'));
            $dane_kor->appendChild($dom->createElement('NrKSeFFaKorygowanej', $original_ksef_number));
        } else {
            // Original invoice was not sent via KSeF (or KSeF number unknown)
            $dane_kor->appendChild($dom->createElement('NrKSeFN', '1'));
        }
    }

    /**
     * Try to find the KSeF number of the original invoice being corrected.
     * Searches for a facture post with matching invoice number.
     */
    private function find_original_ksef_number(string $original_number, \WP_Post $corrective_post): string {
        // Search for a facture post whose id_facture ACF field matches the original number
        $posts = get_posts([
            'post_type'      => 'factures',
            'post_status'    => ['facture_current', 'publish'],
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'facture_group',  // ACF stores as serialized meta
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        foreach ($posts as $p) {
            $data    = get_field('facture_group', $p->ID);
            $gen     = $data['general_group'] ?? [];
            $inv_num = $gen['id_facture'] ?? '';
            if ($inv_num === $original_number) {
                return get_post_meta($p->ID, '_ksef_number', true) ?? '';
            }
        }

        return '';
    }

    // ─── FaWiersz (line items) ────────────────────────────────────────────────

    private function append_fa_wiersze(
        \DOMDocument $dom,
        \DOMElement $fa,
        array $products_list,
        float $global_discount
    ): void {
        foreach ($products_list as $key => $product) {
            $wiersz = $dom->createElement('FaWiersz');
            $fa->appendChild($wiersz);

            $wiersz->appendChild($dom->createElement('NrWierszaFa', strval($key + 1)));

            // P_7 — product / service name
            $name = '';
            if (!empty($product['product_item_facture'])) {
                $item = $product['product_item_facture'];
                $name = is_object($item) ? $item->post_title : strval($item);
            }
            $wiersz->appendChild($dom->createElement('P_7', $this->sanitize_text($name)));

            // P_8A — unit of measure
            $wiersz->appendChild($dom->createElement('P_8A', 'szt'));

            // P_8B — quantity
            $quantity = floatval($product['quantity_product_item_facture'] ?? 0);
            $wiersz->appendChild($dom->createElement('P_8B', number_format($quantity, 6, '.', '')));

            // P_9A — net unit price
            $price = floatval($product['price_product_item_facture'] ?? 0);
            $wiersz->appendChild($dom->createElement('P_9A', number_format($price, 2, '.', '')));

            // P_10 — line-level discount amount (if any)
            $disc_pct = floatval($product['discount_product_item_facture'] ?? 0);
            if ($disc_pct > 0) {
                $discount_amt = $price * $disc_pct / 100;
                $wiersz->appendChild($dom->createElement('P_10', number_format($discount_amt, 2, '.', '')));
            }

            // P_11 — net line total after all discounts
            $unit_after_disc = $price - ($price * $disc_pct / 100);
            $net             = $quantity * $unit_after_disc;
            if ($global_discount > 0) {
                $net -= $net * $global_discount / 100;
            }
            $wiersz->appendChild($dom->createElement('P_11', $this->fmt_amount($net)));

            // P_12 — VAT rate
            $vat_rate = intval($product['vat_rate_product_item_facture'] ?? 0);
            $wiersz->appendChild($dom->createElement('P_12', strval($vat_rate)));
        }
    }

    // ─── Platnosc (payment terms) ─────────────────────────────────────────────

    private function append_platnosc(\DOMDocument $dom, \DOMElement $fa, array $payment, array $general): void {
        $platnosc = $dom->createElement('Platnosc');
        $fa->appendChild($platnosc);

        // Payment status
        $is_paid = isset($payment['payment_status_pay']) && $payment['payment_status_pay'] == 1;

        if ($is_paid) {
            $platnosc->appendChild($dom->createElement('Zaplacono', '1'));
            $platnosc->appendChild($dom->createElement('DataZaplaty', date('Y-m-d')));
        } else {
            // Payment deadline
            $deadline_raw = $general['general_date_payment'] ?? '';
            if (!empty($deadline_raw)) {
                $termin_el = $dom->createElement('TerminPlatnosci');
                $platnosc->appendChild($termin_el);

                $termin_inner = $dom->createElement('Termin', date('Y-m-d', strtotime($deadline_raw)));
                $termin_el->appendChild($termin_inner);
            }
        }

        // Payment method
        $method    = $payment['payment_method'] ?? '';
        $form_code = $this->map_payment_method($method);
        $platnosc->appendChild($dom->createElement('FormaPlatnosci', strval($form_code)));

        // Bank account number
        $account = $payment['payment_account_number'] ?? '';
        if (!empty($account)) {
            $rb    = $dom->createElement('RachunekBankowy');
            $platnosc->appendChild($rb);

            // Strip non-alphanumeric characters (keep letters for IBAN prefix)
            $nr = preg_replace('/[^0-9A-Za-z]/', '', $account);
            $rb->appendChild($dom->createElement('NrRB', strtoupper($nr)));
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Format a monetary amount to 2 decimal places, period separator, no thousands.
     */
    private function fmt_amount(float $amount): string {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Strip disallowed control characters, trim whitespace.
     */
    private function sanitize_text(string $value): string {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value));
    }

    /**
     * Map payment method string to FA(3) TFormaPlatnosci code.
     * 1=cash, 2=card, 3=voucher, 4=check, 5=credit, 6=transfer, 7=mobile
     */
    private function map_payment_method(string $method): int {
        $lower = mb_strtolower(trim($method));

        $map = [
            'gotówka' => 1, 'gotowka' => 1, 'cash'     => 1, 'kasowo' => 1,
            'karta'   => 2, 'card'    => 2,
            'bon'     => 3, 'voucher' => 3,
            'czek'    => 4, 'check'   => 4,
            'kredyt'  => 5, 'credit'  => 5,
            'przelew' => 6, 'transfer'=> 6, 'bank'     => 6, 'bankowy' => 6,
            'mobile'  => 7, 'blik'    => 7,
        ];

        foreach ($map as $keyword => $code) {
            if (strpos($lower, $keyword) !== false) {
                return $code;
            }
        }

        return 6; // default: bank transfer
    }
}
