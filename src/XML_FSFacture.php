<?php

namespace Finespirits\FsFacture;

use Finespirits\FsFacture\AbstractClassFSFacture;

if (!defined('ABSPATH')) {
    exit;
}

class XML_FSFacture extends AbstractClassFSFacture {

    const NS     = 'http://crd.gov.pl/wzor/2025/06/25/13775/';
    const NS_ETD = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/';
    const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** @var \DOMDocument */
    private $dom;

    public function __construct() {
        $this->init();
    }

    public function init() {
        add_action('admin_post_generate_ksef_xml', array($this, 'generate_xml'));
    }

    public function generate_xml() {
        if (!$this->get('facture_id')) {
            wp_die('Not found Facture');
        }

        $facture = get_post((int) $this->get('facture_id'));
        if (!$facture || $facture->post_type !== 'factures') {
            wp_die('Not found Facture');
        }

        $data = get_field('facture_group', $facture->ID);
        if (!$data) {
            wp_die('No facture data');
        }

        $xml_content = $this->build_xml($facture, $data);

        $facture_year   = date('Y', strtotime($facture->post_date));
        $general_group  = get_data_arr('general_group', $data);
        $invoice_number = check_data_arr('id_facture', $general_group)
            ? $general_group['id_facture']
            : $facture->ID . '/' . $facture_year;

        $filename = sanitize_file_name(str_replace('/', '_', $invoice_number)) . '.xml';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $xml_content;
        exit();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Create element in KSeF namespace */
    private function el(string $tag, string $value = ''): \DOMElement {
        $el = $this->dom->createElementNS(self::NS, $tag);
        if ($value !== '') {
            // Strip carriage returns and other control characters that break KSeF validation
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\r]/', ' ', $value);
            $clean = trim(preg_replace('/\s+/', ' ', $clean));
            $el->appendChild($this->dom->createTextNode($clean));
        }
        return $el;
    }

    /**
     * Normalize to Polish NRB format (26 digits) — FA(3) NrRB field expects NRB, not IBAN.
     * Strips country code prefix if stored as IBAN (e.g. PL + 26 digits).
     */
    private function normalize_nrb(string $nrb): string {
        $nrb = preg_replace('/[^A-Za-z0-9]/', '', $nrb);
        if (strlen($nrb) === 28 && ctype_alpha(substr($nrb, 0, 2))) {
            $nrb = substr($nrb, 2);
        }
        return preg_replace('/\D/', '', $nrb);
    }

    private function format_date(string $date): string {
        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : $date;
    }

    private function clean_nip(string $nip): string {
        return preg_replace('/[^0-9]/', '', $nip);
    }

    private function normalize_invoice_number(string $invoice_number, string $facture_year): string {
        $invoice_number = trim($invoice_number);
        // KSeF accepts free text, but many integrations reject bare numeric values in P_2.
        if ($invoice_number !== '' && preg_match('/^\d+$/', $invoice_number)) {
            return 'FV/' . $facture_year . '/' . $invoice_number;
        }
        return $invoice_number;
    }

    // -----------------------------------------------------------------------
    // Main builder
    // -----------------------------------------------------------------------

    private function build_xml(\WP_Post $facture, array $data): string {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        $general_group  = get_data_arr('general_group',  $data) ?: [];
        $seller_group   = get_data_arr('seller_group',   $data) ?: [];
        $buyer_group    = get_data_arr('buyer_group',    $data) ?: [];
        $products_group = get_data_arr('products_group', $data) ?: [];
        $payment_group  = get_data_arr('payment_group',  $data) ?: [];
        $notes_group    = get_data_arr('notes_group',    $data) ?: [];
        $products_list  = get_data_arr('products_list',  $products_group) ?: [];

        $facture_year   = date('Y', strtotime($facture->post_date));
        $is_corrective  = ($facture->post_status === 'facture_corrective');
        $cloned_data    = $is_corrective ? get_post_meta($facture->ID, 'cloned_acf_data', true) : null;

        $invoice_number = check_data_arr('id_facture', $general_group)
            ? $general_group['id_facture']
            : $facture->ID . '/' . $facture_year;
        $invoice_number = $this->normalize_invoice_number((string) $invoice_number, $facture_year);

        $issue_date = check_data_arr('general_facture_date', $general_group)
            ? $this->format_date($general_group['general_facture_date'])
            : date('Y-m-d', strtotime($facture->post_date));

        $sale_date    = check_data_arr('general_date_sale', $general_group)
            ? $this->format_date($general_group['general_date_sale'])
            : '';

        $payment_date = check_data_arr('general_date_payment', $general_group)
            ? $this->format_date($general_group['general_date_payment'])
            : '';

        $global_discount = (isset($products_group['discount_to_all_products']) && $products_group['discount_to_all_products'] > 0)
            ? floatval($products_group['discount_to_all_products'])
            : 0;

        // --- Wiersze + sumy wg stawki ---
        $fa_rows     = [];
        $vat_summary = [];
        $nr          = 1;

        foreach ($products_list as $product) {
            if (empty($product['product_item_facture'])) {
                continue;
            }

            $product_name = is_object($product['product_item_facture'])
                ? $product['product_item_facture']->post_title
                : get_the_title((int) $product['product_item_facture']);

            $price        = floatval($product['price_product_item_facture'] ?? 0);
            $quantity     = floatval($product['quantity_product_item_facture'] ?? 0);
            $item_disc    = floatval($product['discount_product_item_facture'] ?? 0);
            $vat_rate_raw = strval($product['vat_rate_product_item_facture'] ?? '23');
            $vat_rate     = trim(preg_replace('/\s+/', ' ', strtolower($vat_rate_raw)));
            if ($vat_rate === 'zw.') {
                $vat_rate = 'zw';
            }

            $sale_price = $item_disc > 0
                ? round($price - ($price * $item_disc / 100), 2)
                : $price;

            if ($global_discount > 0) {
                $sale_price = round($sale_price - ($sale_price * $global_discount / 100), 2);
            }

            $line_netto = round($quantity * $sale_price, 2);
            $vat_num    = is_numeric(str_replace(',', '.', $vat_rate)) ? floatval(str_replace(',', '.', $vat_rate)) : null;
            $vat_amount = ($vat_num !== null) ? round($line_netto * $vat_num / 100, 2) : 0.0;

            $p12_value_map = [
                '0 wdt' => '0 WDT',
                '0 ex'  => '0 EX',
                'zw'    => 'zw',
            ];
            $p12_value = $p12_value_map[$vat_rate] ?? $vat_rate_raw;

            $fa_rows[] = [
                'nr'       => $nr++,
                'name'     => $product_name,
                'quantity' => $quantity,
                'price'    => $price,
                'netto'    => $line_netto,
                'vat_rate' => $vat_rate,
                'p12'      => $p12_value,
                'gtu'      => isset($product['gtu_product_item_facture']) ? (string) $product['gtu_product_item_facture'] : '',
            ];

            if (!isset($vat_summary[$vat_rate])) {
                $vat_summary[$vat_rate] = ['netto' => 0.0, 'vat' => 0.0];
            }
            $vat_summary[$vat_rate]['netto'] += $line_netto;
            $vat_summary[$vat_rate]['vat']   += $vat_amount;
        }

        // KSeF recomputes P_13-P_15 itself from the sum of every FaWiersz row
        // in the document — it does not net a StanPrzed "before" row against
        // its "after" counterpart, it adds both magnitudes. So a corrective
        // invoice must only ever list its own current product rows (whatever
        // sign they carry), never a duplicated "before" state.
        $total_brutto = 0.0;
        foreach ($vat_summary as $s) {
            $total_brutto += round($s['netto'] + $s['vat'], 2);
        }

        // --- XML root ---
        $root = $this->dom->createElementNS(self::NS, 'Faktura');
        $this->dom->appendChild($root);
        // Required namespace declarations (as in official FA(3) examples)
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:etd', self::NS_ETD);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);

        // Nagłówek
        $nag = $this->el('Naglowek');
        $root->appendChild($nag);

        $kod = $this->el('KodFormularza', 'FA');
        $kod->setAttribute('kodSystemowy', 'FA (3)');
        $kod->setAttribute('wersjaSchemy', '1-0E');
        $nag->appendChild($kod);
        $nag->appendChild($this->el('WariantFormularza', '3'));
        $nag->appendChild($this->el('DataWytworzeniaFa', gmdate('Y-m-d\TH:i:s\Z')));
        $nag->appendChild($this->el('SystemInfo', 'FS Facture 1.0'));

        // Podmiot1 – Sprzedawca
        $p1 = $this->el('Podmiot1');
        $root->appendChild($p1);
        $this->append_podmiot($p1, [
            'nip'    => $this->clean_nip($seller_group['seller_nip'] ?? ''),
            'name'   => $seller_group['seller_organization'] ?? '',
            'country'=> $seller_group['seller_country_code'] ?? 'PL',
            'street' => $seller_group['seller_street'] ?? ($seller_group['seller_adress'] ?? ''),
            'city'   => trim(($seller_group['seller_postal_code'] ?? '') . ' ' . ($seller_group['seller_city'] ?? '')),
        ]);

        // Podmiot2 – Nabywca
        $p2 = $this->el('Podmiot2');
        $root->appendChild($p2);
        $this->append_podmiot($p2, [
            'nip'    => $this->clean_nip($buyer_group['buyer_nip'] ?? ''),
            'name'   => $buyer_group['buyer_organization'] ?? '',
            'country'=> $buyer_group['buyer_country_code'] ?? 'PL',
            'street' => $buyer_group['buyer_street'] ?? ($buyer_group['buyer_address'] ?? ''),
            'city'   => trim(($buyer_group['buyer_postal_code'] ?? '') . ' ' . ($buyer_group['buyer_city'] ?? '')),
        ]);
        // FA(3): JST and GV are required in Podmiot2
        $p2->appendChild($this->el('JST', '2'));
        $p2->appendChild($this->el('GV', '2'));

        // Fa
        $fa = $this->el('Fa');
        $root->appendChild($fa);

        // FA(3) element order: KodWaluty, P_1, P_1M, P_2, P_6,
        //   VAT sums, P_15, Adnotacje, RodzajFaktury, [korekta], FaWiersz, Platnosc

        $fa->appendChild($this->el('KodWaluty', 'PLN'));
        $fa->appendChild($this->el('P_1', $issue_date));

        if (!empty($general_group['general_adress'])) {
            $fa->appendChild($this->el('P_1M', $general_group['general_adress']));
        }

        $fa->appendChild($this->el('P_2', $invoice_number));

        // Emit P_6 always: either provided sale date or issue date fallback.
        $fa->appendChild($this->el('P_6', $sale_date ?: $issue_date));

        // Sumy wg stawki VAT
        $vat_field_map = [
            '23' => ['P_13_1', 'P_14_1'],
            '8'  => ['P_13_2', 'P_14_2'],
            '5'  => ['P_13_3', 'P_14_3'],
            '0'  => ['P_13_4', 'P_14_4'],
            'zw' => ['P_13_5', null],
            '0 wdt' => ['P_13_6_2', null],
            '0 ex'  => ['P_13_6_3', null],
        ];

        foreach ($vat_summary as $rate => $sums) {
            if (!isset($vat_field_map[$rate])) {
                continue;
            }
            [$netto_field, $vat_field] = $vat_field_map[$rate];
            $fa->appendChild($this->el($netto_field, number_format(round($sums['netto'], 2), 2, '.', '')));
            if ($vat_field !== null) {
                $fa->appendChild($this->el($vat_field, number_format(round($sums['vat'], 2), 2, '.', '')));
            }
        }

        $fa->appendChild($this->el('P_15', number_format(round($total_brutto, 2), 2, '.', '')));

        // Adnotacje — FA(3) structure with wrapper elements
        $adnotacje = $this->el('Adnotacje');
        $fa->appendChild($adnotacje);

        foreach (['P_16', 'P_17', 'P_18', 'P_18A'] as $ann) {
            $adnotacje->appendChild($this->el($ann, '2'));
        }

        $zwolnienie = $this->el('Zwolnienie');
        $adnotacje->appendChild($zwolnienie);
        $zwolnienie->appendChild($this->el('P_19N', '1'));

        $nst = $this->el('NoweSrodkiTransportu');
        $adnotacje->appendChild($nst);
        $nst->appendChild($this->el('P_22N', '1'));

        $adnotacje->appendChild($this->el('P_23', '2'));

        $pmarzy = $this->el('PMarzy');
        $adnotacje->appendChild($pmarzy);
        $pmarzy->appendChild($this->el('P_PMarzyN', '1'));

        // RodzajFaktury — after Adnotacje in FA(3)
        $fa->appendChild($this->el('RodzajFaktury', $is_corrective ? 'KOR' : 'VAT'));

        // Korekta
        if ($is_corrective) {
            $reason = $notes_group['notes_corrective_reason'] ?? '';
            if ($reason) {
                $fa->appendChild($this->el('PrzyczynaKorekty', $reason));
            }
            $typ_korekty = trim((string) ($notes_group['notes_corrective_type'] ?? $notes_group['type_corrective'] ?? ''));
            if ($typ_korekty === '') {
                $typ_korekty = '3';
            }
            $fa->appendChild($this->el('TypKorekty', $typ_korekty));

            if (!empty($cloned_data)) {
                $orig_general = get_data_arr('general_group', $cloned_data) ?: [];
                $orig_number  = $orig_general['id_facture'] ?? '';
                $orig_date    = check_data_arr('general_facture_date', $orig_general)
                    ? $this->format_date($orig_general['general_facture_date'])
                    : $issue_date;

                if ($orig_number) {
                    $dane_kor = $this->el('DaneFaKorygowanej');
                    $fa->appendChild($dane_kor);
                    $dane_kor->appendChild($this->el('DataWystFaKorygowanej', $orig_date));
                    $dane_kor->appendChild($this->el('NrFaKorygowanej', $orig_number));
                }
            }
        }

        // FaWiersz — FA(3) uses NrWierszaFa. Always the current product rows
        // only (see note above the totals block for why no "before" rows).
        foreach ($fa_rows as $row) {
            $wiersz = $this->el('FaWiersz');
            $fa->appendChild($wiersz);

            $wiersz->appendChild($this->el('NrWierszaFa', (string) $row['nr']));
            $wiersz->appendChild($this->el('P_7', $row['name']));
            $wiersz->appendChild($this->el('P_8A', 'szt.'));
            $wiersz->appendChild($this->el('P_8B', number_format($row['quantity'], 2, '.', '')));
            $wiersz->appendChild($this->el('P_9A', number_format($row['price'], 2, '.', '')));
            $wiersz->appendChild($this->el('P_11', number_format($row['netto'], 2, '.', '')));
            $wiersz->appendChild($this->el('P_12', $row['p12']));
            if (!empty($row['gtu'])) {
                $wiersz->appendChild($this->el('GTU', $row['gtu']));
            }
        }

        // Platnosc — FA(3): Zaplacono only when paid; order: [Zaplacono], [TerminPlatnosci], FormaPlatnosci, RachunekBankowy
        $platnosc = null;

        $is_paid = isset($payment_group['payment_status_pay']) && $payment_group['payment_status_pay'] == 1;
        $has_payment_method = !empty($payment_group['payment_method']);
        $has_payment_nrb = !empty($payment_group['payment_account_number']);
        $has_payment_deadline = ($payment_date && !$is_paid);

        if ($is_paid || $has_payment_deadline || $has_payment_method || $has_payment_nrb) {
            $platnosc = $this->el('Platnosc');
            $fa->appendChild($platnosc);
        }

        if ($platnosc && $is_paid) {
            $platnosc->appendChild($this->el('Zaplacono', '1'));
            if ($payment_date) {
                $platnosc->appendChild($this->el('DataZaplaty', $payment_date));
            }
        }

        if ($platnosc && $payment_date && !$is_paid) {
            $termin = $this->el('TerminPlatnosci');
            $platnosc->appendChild($termin);
            $termin->appendChild($this->el('Termin', $payment_date));
        }

        if ($platnosc && !empty($payment_group['payment_method'])) {
            $platnosc->appendChild($this->el('FormaPlatnosci', (string) $payment_group['payment_method']));
        }

        if ($platnosc && !empty($payment_group['payment_account_number'])) {
            $nrb      = $this->normalize_nrb($payment_group['payment_account_number']);
            if ($nrb !== '') {
                $rachunek = $this->el('RachunekBankowy');
                $platnosc->appendChild($rachunek);
                $rachunek->appendChild($this->el('NrRB', $nrb));
            }
        }

        return $this->dom->saveXML();
    }

    // -----------------------------------------------------------------------

    private function append_podmiot(\DOMElement $parent, array $d): void {
        $dane = $this->el('DaneIdentyfikacyjne');
        $has_dane = false;
        if (!empty($d['nip'])) {
            $dane->appendChild($this->el('NIP', $d['nip']));
            $has_dane = true;
        }
        if (!empty($d['name'])) {
            $dane->appendChild($this->el('Nazwa', $d['name']));
            $has_dane = true;
        }
        if ($has_dane) {
            $parent->appendChild($dane);
        }

        $adres = $this->el('Adres');
        $has_adres = false;
        if (!empty($d['country'])) {
            $adres->appendChild($this->el('KodKraju', strtoupper((string) $d['country'])));
            $has_adres = true;
        }

        if (!empty($d['street'])) {
            $adres->appendChild($this->el('AdresL1', $d['street']));
            $has_adres = true;
        }
        if (!empty(trim($d['city']))) {
            $adres->appendChild($this->el('AdresL2', trim($d['city'])));
            $has_adres = true;
        }
        if ($has_adres) {
            $parent->appendChild($adres);
        }
    }
}
