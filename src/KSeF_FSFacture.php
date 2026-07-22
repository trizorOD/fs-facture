<?php

namespace Finespirits\FsFacture;

use Finespirits\FsFacture\AbstractClassFSFacture;
use Finespirits\FsFacture\KSeF_XML_Builder;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * KSeF (Krajowy System e-Faktur) integration for FS Facture.
 *
 * Handles:
 *  - WordPress settings page (Factures → KSeF)
 *  - KSeF Token authentication (valid until end of 2026)
 *  - Online session management
 *  - AES-256-CBC invoice encryption
 *  - Invoice submission and status polling
 *  - UPO / KSeF number retrieval
 *
 * API docs: https://api-test.ksef.mf.gov.pl/docs/v2/index.html
 * GitHub:   https://github.com/CIRFMF/ksef-docs
 *
 * NOTE on encryption:
 *   PHP's openssl_public_encrypt() with OPENSSL_PKCS1_OAEP_PADDING uses SHA-1 OAEP.
 *   KSeF spec says RSAES-OAEP (defaults to SHA-1 in most implementations).
 *   If SHA-256 OAEP is required, add phpseclib/phpseclib to composer and
 *   replace the encrypt_with_rsa_oaep() method below.
 */
class KSeF_FSFacture extends AbstractClassFSFacture {

    const OPTION_ENVIRONMENT      = 'fs_ksef_environment';
    const OPTION_NIP              = 'fs_ksef_nip';
    const OPTION_TOKEN            = 'fs_ksef_token';
    const TRANSIENT_ACCESS_TOKEN  = 'fs_ksef_access_token';
    const META_KSEF_NUMBER        = '_ksef_number';
    const META_KSEF_SESSION       = '_ksef_session_ref';
    const META_KSEF_SENT_AT       = '_ksef_sent_at';
    const META_KSEF_STATUS        = '_ksef_status';
    const META_KSEF_ERROR         = '_ksef_error';

    private array $api_bases = [
        'test' => 'https://api-test.ksef.mf.gov.pl/v2',
        'demo' => 'https://api-demo.ksef.mf.gov.pl/v2',
        'prod' => 'https://api.ksef.mf.gov.pl/v2',
    ];

    public function __construct() {
        $this->init();
    }

    public function init(): void {
        add_action('admin_menu',                        [$this, 'register_settings_page']);
        add_action('admin_post_fs_ksef_save_settings',  [$this, 'handle_save_settings']);
        add_action('wp_ajax_fs_ksef_send_invoice',      [$this, 'ajax_send_invoice']);
        add_action('admin_enqueue_scripts',             [$this, 'enqueue_ksef_scripts']);
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    public function get_settings(): array {
        return [
            'environment' => get_option(self::OPTION_ENVIRONMENT, 'test'),
            'nip'         => get_option(self::OPTION_NIP, ''),
            'token'       => get_option(self::OPTION_TOKEN, ''),
        ];
    }

    public function register_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=factures',
            __('KSeF Settings', 'fs-facture'),
            __('KSeF', 'fs-facture'),
            'manage_options',
            'fs-ksef-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page(): void {
        $settings = $this->get_settings();
        include __DIR__ . '/../templates/ksef_settings.php';
    }

    public function handle_save_settings(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('fs_ksef_save_settings')) {
            wp_die('Unauthorized');
        }

        $environment = sanitize_text_field($_POST['fs_ksef_environment'] ?? 'test');
        $nip         = preg_replace('/[^0-9]/', '', $_POST['fs_ksef_nip'] ?? '');
        $token       = sanitize_text_field($_POST['fs_ksef_token'] ?? '');

        if (!in_array($environment, ['test', 'demo', 'prod'], true)) {
            $environment = 'test';
        }

        update_option(self::OPTION_ENVIRONMENT, $environment);
        update_option(self::OPTION_NIP, $nip);

        if (!empty($token)) {
            update_option(self::OPTION_TOKEN, $token);
        }

        // Clear cached access token after settings change
        delete_transient(self::TRANSIENT_ACCESS_TOKEN);

        wp_redirect(add_query_arg(
            ['page' => 'fs-ksef-settings', 'saved' => '1'],
            admin_url('edit.php?post_type=factures')
        ));
        exit;
    }

    // ─── Admin script (nonce for AJAX) ────────────────────────────────────────

    public function enqueue_ksef_scripts(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'factures' || $screen->base !== 'post') {
            return;
        }

        wp_localize_script('jquery', 'fsKsefAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fs_ksef_send_invoice'),
        ]);
    }

    // ─── API base URL ─────────────────────────────────────────────────────────

    private function get_api_base(): string {
        $env = get_option(self::OPTION_ENVIRONMENT, 'test');
        return $this->api_bases[$env] ?? $this->api_bases['test'];
    }

    // ─── Generic HTTP helper ──────────────────────────────────────────────────

    /**
     * Make an API request. Throws RuntimeException on HTTP error.
     *
     * @param string $method  GET | POST | DELETE
     * @param string $path    Endpoint path (e.g. '/auth/challenge')
     * @param array  $args    wp_remote_request args override
     * @return array ['code' => int, 'body' => array|null, 'raw' => string]
     */
    private function api_request(string $method, string $path, array $args = []): array {
        $url = $this->get_api_base() . $path;

        $defaults = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'sslverify' => get_option(self::OPTION_ENVIRONMENT, 'test') !== 'test',
        ];

        $args = array_replace_recursive($defaults, $args);

        // Merge headers explicitly (array_replace_recursive would overwrite)
        if (isset($args['_headers_extra'])) {
            $args['headers'] = array_merge($defaults['headers'], $args['_headers_extra']);
            unset($args['_headers_extra']);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException('KSeF HTTP error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $body = json_decode($raw, true);

        if ($code === 429) {
            $retry = wp_remote_retrieve_header($response, 'retry-after');
            throw new \RuntimeException("KSeF rate limit exceeded. Retry after {$retry}s.");
        }

        if ($code >= 400) {
            $desc = '';
            if (is_array($body)) {
                $desc = $body['description'] ?? $body['message'] ?? $body['title'] ?? '';
                if (isset($body['errors']) && is_array($body['errors'])) {
                    $desc .= ' ' . implode('; ', array_column($body['errors'], 'message'));
                }
            }
            $desc = $desc ?: $raw;
            throw new \RuntimeException("KSeF API [{$code}]: {$desc}");
        }

        return ['code' => $code, 'body' => $body, 'raw' => $raw];
    }

    // ─── Public key retrieval ─────────────────────────────────────────────────

    /**
     * Fetch ministry public key certificate (Base64 DER) by usage type.
     * Usage: 'KsefTokenEncryption' | 'SymmetricKeyEncryption'
     */
    private function get_public_key_cert(string $usage): string {
        $response = $this->api_request('GET', '/security/public-key-certificates');
        $data     = $response['body'];

        // Response may be an array directly or wrapped in a key
        $certs = is_array($data) && isset($data[0]) ? $data : ($data['certificates'] ?? []);

        foreach ($certs as $cert) {
            if (($cert['usage'] ?? '') === $usage) {
                return $cert['certificate'] ?? '';
            }
        }

        throw new \RuntimeException("KSeF: public key for '{$usage}' not found in /security/public-key-certificates.");
    }

    /**
     * Load an OpenSSL public key resource from a Base64 DER certificate.
     * Returns an OpenSSLAsymmetricKey (PHP 8) or resource (PHP 7).
     *
     * @return resource|\OpenSSLAsymmetricKey
     */
    private function load_public_key(string $cert_b64) {
        // Try DER first
        $cert_der   = base64_decode($cert_b64);
        $public_key = @openssl_get_publickey($cert_der);

        if ($public_key === false) {
            // Try as PEM-wrapped certificate
            $pem      = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert_b64, 64) . "-----END CERTIFICATE-----\n";
            $cert_res = openssl_x509_read($pem);
            if ($cert_res === false) {
                throw new \RuntimeException('KSeF: cannot read X.509 certificate: ' . openssl_error_string());
            }
            $public_key = openssl_get_publickey($cert_res);
        }

        if ($public_key === false) {
            throw new \RuntimeException('KSeF: cannot extract public key: ' . openssl_error_string());
        }

        return $public_key;
    }

    /**
     * Encrypt plaintext with RSA-OAEP (PKCS#1 v2.1, SHA-1).
     * Returns Base64-encoded ciphertext.
     *
     * NOTE: PHP's OPENSSL_PKCS1_OAEP_PADDING uses SHA-1.
     * If KSeF requires SHA-256 OAEP, use phpseclib instead.
     *
     * @param string $plaintext
     * @param string $cert_b64 Base64-encoded DER certificate
     */
    private function encrypt_with_rsa_oaep(string $plaintext, string $cert_b64): string {
        $key = $this->load_public_key($cert_b64);

        $encrypted = '';
        $result    = openssl_public_encrypt($plaintext, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING);

        if (!$result) {
            throw new \RuntimeException('KSeF: RSA-OAEP encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    /**
     * Return a valid access token, reusing the cached one when possible.
     */
    private function get_access_token(): string {
        $cached = get_transient(self::TRANSIENT_ACCESS_TOKEN);
        if (!empty($cached)) {
            return $cached;
        }

        $token = $this->authenticate();

        // Access tokens are valid ~15 min; cache for 13 min to allow for clock skew
        set_transient(self::TRANSIENT_ACCESS_TOKEN, $token, 13 * MINUTE_IN_SECONDS);

        return $token;
    }

    /**
     * Full KSeF Token authentication flow:
     *  1. POST /auth/challenge
     *  2. Encrypt {ksef_token}|{timestamp_ms} with KsefTokenEncryption public key
     *  3. POST /auth/ksef-token
     *  4. Poll GET /auth/{referenceNumber} until status = Authorised
     *  5. POST /auth/token/redeem → accessToken
     */
    private function authenticate(): string {
        $settings = $this->get_settings();

        if (empty($settings['nip'])) {
            throw new \RuntimeException('KSeF: NIP not configured. Go to Factures → KSeF to configure.');
        }
        if (empty($settings['token'])) {
            throw new \RuntimeException('KSeF: Token not configured. Go to Factures → KSeF to configure.');
        }

        // Step 1: challenge
        $challenge_resp = $this->api_request('POST', '/auth/challenge');
        $challenge      = $challenge_resp['body']['challenge']  ?? '';
        $timestamp      = $challenge_resp['body']['timestamp']  ?? strval(round(microtime(true) * 1000));

        if (empty($challenge)) {
            throw new \RuntimeException('KSeF: empty challenge received.');
        }

        // Step 2: encrypt token
        $cert_b64      = $this->get_public_key_cert('KsefTokenEncryption');
        $plaintext     = $settings['token'] . '|' . $timestamp;
        $encrypted_b64 = $this->encrypt_with_rsa_oaep($plaintext, $cert_b64);

        // Step 3: authenticate
        $auth_resp = $this->api_request('POST', '/auth/ksef-token', [
            'body' => json_encode([
                'challenge'         => $challenge,
                'contextIdentifier' => [
                    'type'       => 'onip',
                    'identifier' => $settings['nip'],
                ],
                'encryptedToken' => $encrypted_b64,
            ]),
        ]);

        $auth_token = $auth_resp['body']['authenticationToken'] ?? '';
        $ref_number = $auth_resp['body']['referenceNumber']     ?? '';

        if (empty($auth_token)) {
            throw new \RuntimeException('KSeF: no authenticationToken in /auth/ksef-token response.');
        }

        // Step 4: poll for Authorised status
        $this->wait_for_auth_status($ref_number, $auth_token);

        // Step 5: redeem for access token
        $redeem_resp = $this->api_request('POST', '/auth/token/redeem', [
            '_headers_extra' => ['Authorization' => 'Bearer ' . $auth_token],
        ]);

        $access_token = $redeem_resp['body']['accessToken'] ?? '';
        if (empty($access_token)) {
            throw new \RuntimeException('KSeF: no accessToken in /auth/token/redeem response.');
        }

        return $access_token;
    }

    /**
     * Poll GET /auth/{referenceNumber} until the authentication is 'Authorised'.
     */
    private function wait_for_auth_status(string $ref_number, string $auth_token, int $max = 10): void {
        for ($i = 0; $i < $max; $i++) {
            sleep(1);

            $resp   = $this->api_request('GET', '/auth/' . urlencode($ref_number), [
                '_headers_extra' => ['Authorization' => 'Bearer ' . $auth_token],
            ]);
            $status = $resp['body']['processingCode'] ?? $resp['body']['status'] ?? '';

            // Accept both string and numeric success indicators
            if ($status === 'Authorised' || (string)$status === '200') {
                return;
            }

            if (in_array($status, ['Failed', 'Error', 'Rejected'], true)) {
                throw new \RuntimeException('KSeF auth failed: ' . json_encode($resp['body']));
            }
        }

        throw new \RuntimeException('KSeF: authentication status polling timed out.');
    }

    // ─── Encryption for invoice payload ───────────────────────────────────────

    /**
     * Generate a random 256-bit AES key and 128-bit IV.
     * @return array{key: string, iv: string}
     */
    private function generate_aes_key_iv(): array {
        return [
            'key' => openssl_random_pseudo_bytes(32),
            'iv'  => openssl_random_pseudo_bytes(16),
        ];
    }

    /**
     * Encrypt the AES symmetric key with the ministry's SymmetricKeyEncryption public key.
     * Returns Base64-encoded encrypted key.
     */
    private function encrypt_aes_key(string $aes_key): string {
        $cert_b64 = $this->get_public_key_cert('SymmetricKeyEncryption');
        return $this->encrypt_with_rsa_oaep($aes_key, $cert_b64);
    }

    /**
     * Encrypt invoice XML with AES-256-CBC.
     * Returns raw ciphertext (binary).
     */
    private function encrypt_xml(string $xml, string $aes_key, string $iv): string {
        $encrypted = openssl_encrypt($xml, 'AES-256-CBC', $aes_key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('KSeF: AES-256-CBC encryption failed: ' . openssl_error_string());
        }

        return $encrypted;
    }

    // ─── Session management ───────────────────────────────────────────────────

    /**
     * Open an interactive (online) KSeF session for FA(3) invoices.
     * Returns session reference number.
     */
    private function open_online_session(string $access_token, string $enc_key_b64, string $iv_b64): string {
        $resp = $this->api_request('POST', '/sessions/online', [
            '_headers_extra' => ['Authorization' => 'Bearer ' . $access_token],
            'body' => json_encode([
                'formCode' => [
                    'systemCode'    => 'FA (3)',
                    'schemaVersion' => '1-0E',
                    'value'         => 'FA',
                ],
                'encryption' => [
                    'encryptedSymmetricKey' => $enc_key_b64,
                    'initializationVector'  => $iv_b64,
                ],
            ]),
        ]);

        $ref = $resp['body']['referenceNumber'] ?? '';
        if (empty($ref)) {
            throw new \RuntimeException('KSeF: no referenceNumber from /sessions/online.');
        }

        return $ref;
    }

    /**
     * Upload the encrypted invoice binary to the open session.
     * Returns invoice reference number.
     */
    private function upload_invoice_binary(string $session_ref, string $encrypted_bytes, string $access_token): string {
        $resp = $this->api_request('POST', '/sessions/online/' . urlencode($session_ref) . '/invoices', [
            'headers' => [
                'Content-Type'  => 'application/octet-stream',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => $encrypted_bytes,
        ]);

        $invoice_ref = $resp['body']['referenceNumber'] ?? '';
        if (empty($invoice_ref)) {
            throw new \RuntimeException('KSeF: no invoice referenceNumber from session/invoices upload.');
        }

        return $invoice_ref;
    }

    /**
     * Close the online session (triggers asynchronous UPO generation).
     */
    private function close_online_session(string $session_ref, int $invoice_count, string $access_token): void {
        $this->api_request('POST', '/sessions/online/' . urlencode($session_ref) . '/close', [
            '_headers_extra' => ['Authorization' => 'Bearer ' . $access_token],
            'body' => json_encode(['invoicesCount' => $invoice_count]),
        ]);
    }

    /**
     * Poll GET /sessions/{referenceNumber} until status is Succeeded or Failed.
     * Returns the final session body.
     */
    private function poll_session_status(string $session_ref, string $access_token, int $max = 20): array {
        for ($i = 0; $i < $max; $i++) {
            sleep(3);

            $resp   = $this->api_request('GET', '/sessions/' . urlencode($session_ref), [
                '_headers_extra' => ['Authorization' => 'Bearer ' . $access_token],
            ]);
            $status = $resp['body']['processingCode'] ?? $resp['body']['status'] ?? '';

            if ($status === 'Succeeded' || (string)$status === '200') {
                return $resp['body'];
            }

            if ($status === 'Failed') {
                $errors = json_encode($resp['body']['errorMessages'] ?? $resp['body']);
                throw new \RuntimeException('KSeF: session processing failed: ' . $errors);
            }
        }

        throw new \RuntimeException('KSeF: session status polling timed out after ' . ($max * 3) . 's.');
    }

    /**
     * Retrieve the KSeF reference number for a specific invoice in the session.
     */
    private function get_ksef_number(string $session_ref, string $invoice_ref, string $access_token): string {
        $resp = $this->api_request(
            'GET',
            '/sessions/' . urlencode($session_ref) . '/invoices/' . urlencode($invoice_ref),
            ['_headers_extra' => ['Authorization' => 'Bearer ' . $access_token]]
        );

        return $resp['body']['ksefReferenceNumber']
            ?? $resp['body']['ksefNumber']
            ?? $resp['body']['invoiceReferenceNumber']
            ?? '';
    }

    // ─── Main send flow ───────────────────────────────────────────────────────

    /**
     * Build, encrypt and send an invoice to KSeF.
     * Stores _ksef_number, _ksef_status, _ksef_sent_at in post meta on success.
     *
     * @return array{success: bool, ksef_number?: string, error?: string}
     */
    public function send_invoice(int $post_id): array {
        set_time_limit(120);

        try {
            // 1. Validate post
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'factures') {
                throw new \RuntimeException('Invalid post ID or post type.');
            }

            // 2. Load ACF data
            $data = get_field('facture_group', $post_id);
            if (empty($data)) {
                throw new \RuntimeException('No facture_group ACF data found for post #' . $post_id);
            }

            $is_corrective = $post->post_status === 'facture_corrective';
            $cloned_data   = $is_corrective ? get_post_meta($post_id, 'cloned_acf_data', true) : null;

            // 3. Build FA(3) XML
            $xml_builder = new KSeF_XML_Builder();
            $xml         = $xml_builder->build($post_id, $data, $is_corrective, $cloned_data ?: null);

            // 4. Get access token (cached)
            $access_token = $this->get_access_token();

            // 5. Generate AES-256 key + IV
            ['key' => $aes_key, 'iv' => $iv] = $this->generate_aes_key_iv();

            // 6. Encrypt AES key with ministry's public key (for session)
            $enc_key_b64 = $this->encrypt_aes_key($aes_key);
            $iv_b64      = base64_encode($iv);

            // 7. Open online session
            $session_ref = $this->open_online_session($access_token, $enc_key_b64, $iv_b64);

            // 8. Encrypt XML
            $encrypted_bytes = $this->encrypt_xml($xml, $aes_key, $iv);

            // 9. Upload invoice
            $invoice_ref = $this->upload_invoice_binary($session_ref, $encrypted_bytes, $access_token);

            // 10. Close session
            $this->close_online_session($session_ref, 1, $access_token);

            // 11. Poll until processing complete
            $this->poll_session_status($session_ref, $access_token);

            // 12. Retrieve KSeF number
            $ksef_number = $this->get_ksef_number($session_ref, $invoice_ref, $access_token);

            // 13. Persist results
            update_post_meta($post_id, self::META_KSEF_NUMBER,  $ksef_number);
            update_post_meta($post_id, self::META_KSEF_SESSION, $session_ref);
            update_post_meta($post_id, self::META_KSEF_SENT_AT, current_time('mysql'));
            update_post_meta($post_id, self::META_KSEF_STATUS,  'sent');
            delete_post_meta($post_id, self::META_KSEF_ERROR);

            error_log("FS KSeF: invoice #{$post_id} sent. KSeF number: {$ksef_number}");

            return ['success' => true, 'ksef_number' => $ksef_number];

        } catch (\Throwable $e) {
            update_post_meta($post_id, self::META_KSEF_STATUS, 'failed');
            update_post_meta($post_id, self::META_KSEF_ERROR,  $e->getMessage());

            error_log('FS KSeF Error [post #' . $post_id . ']: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── AJAX handler ─────────────────────────────────────────────────────────

    public function ajax_send_invoice(): void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No permission');
        }

        if (!check_ajax_referer('fs_ksef_send_invoice', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id || get_post_type($post_id) !== 'factures') {
            wp_send_json_error('Invalid post ID');
        }

        // Prevent duplicate submission
        $existing = get_post_meta($post_id, self::META_KSEF_NUMBER, true);
        if (!empty($existing)) {
            wp_send_json_error(
                __('Invoice already sent to KSeF. Number: ', 'fs-facture') . esc_html($existing)
            );
        }

        $result = $this->send_invoice($post_id);

        if ($result['success']) {
            wp_send_json_success([
                'message'     => __('Sent to KSeF successfully!', 'fs-facture'),
                'ksef_number' => $result['ksef_number'],
            ]);
        } else {
            wp_send_json_error($result['error'] ?? __('Unknown error', 'fs-facture'));
        }
    }
}
