# Organizations Directory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a buyer-organization directory (new CPT `fs_organization`) that lets a manager pick an organization on the facture form and auto-fill its NIP/address fields, plus a one-time/repeatable import that seeds the directory from existing factures.

**Architecture:** A new `Organization_FSFacture` class (same pattern as `Margin_FSFacture`) registers the `fs_organization` CPT, an ACF Post Object field on the facture's `buyer_group`, an AJAX autofill endpoint (mirrors `ACF_FSFacture::wp_ajax_acf_get_product_data_handle` + `assets/acf/product/product.js`), and an AJAX import endpoint reachable from a submenu page. Buyer-key/grouping logic currently duplicated only in `Margin_FSFacture` is extracted into a new static helper, `Buyer_Data_Helper`, used by both `Margin_FSFacture` and the new import feature.

**Tech Stack:** PHP 8.5 / WordPress plugin, ACF Pro (fields managed via WP Admin UI, no `acf-json` sync), jQuery for admin JS, PSR-4 autoload (`Finespirits\FsFacture\` → `src/`).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-22-organizations-directory-design.md` — every requirement in it is in scope; anything listed under its "Явно вне рамок" section is out of scope for this plan.
- Namespace `Finespirits\FsFacture`, PSR-4 autoloaded from `src/` (see `composer.json`). Every new/edited PHP file in `src/` starts with the same `if (!defined('ABSPATH')) { exit; }` guard used in every existing file there.
- Class naming follows the existing `<Name>_FSFacture` convention (e.g. `Margin_FSFacture`, `ACF_FSFacture`) for classes that register WP hooks. `Buyer_Data_Helper` is the one exception — it is a stateless static utility with no hooks, so it does not need the suffix.
- User-facing strings use `__()` / `esc_html_e()` with text domain `'fs-facture'`, matching existing code.
- **No git repository** (`git status` in the project root returns "fatal: not a git repository"). Skip every `git add` / `git commit` step below — track progress with this plan's checkboxes instead.
- **No automated test suite** in this project (no PHPUnit, no wp-env/docker, confirmed via directory listing). The verification step for each PHP change is `php -l <file>` (PHP 8.5.4 CLI is available in this environment) for syntax correctness, plus a manual functional check performed by the user in their actual WordPress admin (this sandbox has no running WP instance). Steps marked **[Manual — WP Admin]** cannot be done by an agent and must be handed to the user.
- ACF field groups in this project are configured entirely through the WP Admin "Custom Fields" screens — there is no `acf-json` auto-sync folder in the repo. Two tasks below are therefore manual WP-admin configuration steps, not code changes.
- Plugin bootstrap: `fs_facture_plugin.php` instantiates `App_FSFacture`, whose constructor (`src/App_FSFacture.php`) instantiates the plugin's feature classes (`PDF_FSFacture`, `ACF_FSFacture`, `XML_FSFacture`, `Margin_FSFacture`, `Settings_FSFacture`). The new `Organization_FSFacture` class is wired in the same way.

---

### Task 1: Register the `fs_organization` CPT

**Files:**
- Create: `src/Organization_FSFacture.php`
- Modify: `src/App_FSFacture.php:1-46`

**Interfaces:**
- Produces: class `Finespirits\FsFacture\Organization_FSFacture` with constant `Organization_FSFacture::CPT_SLUG = 'fs_organization'`, instantiated as `App_FSFacture::$organization_directory`.

- [ ] **Step 1: Create `src/Organization_FSFacture.php`**

```php
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
```

- [ ] **Step 2: Syntax-check the new file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\Organization_FSFacture.php"`
Expected: `No syntax errors detected in ...Organization_FSFacture.php`

- [ ] **Step 3: Wire the class into `App_FSFacture`**

In `src/App_FSFacture.php`, add the `use` import after the existing ones (currently ends at line 10):

```php
use Finespirits\FsFacture\Settings_FSFacture;
use Finespirits\FsFacture\Organization_FSFacture;
use Finespirits\FsFacture\AbstractClassFSFacture;
```

Add a public property next to the existing ones (currently lines 17-21):

```php
public $pdf_generate;
public $acf_fields;
public $xml_generate;
public $margin_report;
public $settings_page;
public $organization_directory;
```

Add the instantiation next to the others inside `init()` (currently lines 39-43):

```php
$this->pdf_generate = new PDF_FSFacture();
$this->acf_fields = new ACF_FSFacture();
$this->xml_generate = new XML_FSFacture();
$this->margin_report = new Margin_FSFacture();
$this->settings_page = new Settings_FSFacture();
$this->organization_directory = new Organization_FSFacture();
```

- [ ] **Step 4: Syntax-check the modified file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\App_FSFacture.php"`
Expected: `No syntax errors detected in ...App_FSFacture.php`

- [ ] **Step 5: [Manual — WP Admin] Verify the CPT is registered**

Deploy the changed files to the WordPress install, then in WP Admin:
1. Confirm a top-level **Organizations** menu item appears (building icon), positioned right after **Factures**.
2. Click **Add New** — confirm an editor with just a Title field opens (no ACF fields yet — those come in Task 2) and can be saved/published.
3. Confirm the list screen shows the saved organization by title.

---

### Task 2: [Manual — WP Admin] Add the `fs_organization` ACF field group

No code in this task — this configures ACF through the WP Admin UI, which is how every existing field group in this project (`facture_group`, etc.) is managed (no `acf-json` sync folder exists in the repo).

- [ ] **Step 1: Create the field group**

In WP Admin → Custom Fields → Add New Field Group:
- Title: `Organization`
- Location rule: `Post Type` `is equal to` `Organization` (the `fs_organization` CPT from Task 1)

- [ ] **Step 2: Add the five fields**

| Field Label   | Field Name          | Field Type |
|---------------|----------------------|------------|
| NIP           | `org_nip`            | Text       |
| Country code  | `org_country_code`   | Text       |
| Street        | `org_street`         | Text       |
| City          | `org_city`           | Text       |
| Postal code   | `org_postal_code`    | Text       |

Save the field group.

- [ ] **Step 3: Verify**

Edit the test organization created in Task 1 Step 5. Confirm all five fields render below the Title field, fill them in with test data, save, reload the page, and confirm the values persisted.

---

### Task 3: Extract `Buyer_Data_Helper` and refactor `Margin_FSFacture`

Removes duplicated buyer-grouping logic ahead of Task 6 (import), which needs the same key/grouping logic. Behavior of `Margin_FSFacture` must not change — this is a pure extraction.

**Files:**
- Create: `src/Buyer_Data_Helper.php`
- Modify: `src/Margin_FSFacture.php`

**Interfaces:**
- Produces: static methods on `Finespirits\FsFacture\Buyer_Data_Helper`:
  - `get_facture_ids(): int[]`
  - `get_facture_data(int $post_id): array`
  - `get_facture_date(\WP_Post $facture, array $facture_data): string` (returns `Y-m-d` or `''`)
  - `normalize_date($date): string` (returns `Y-m-d` or `''`)
  - `get_buyer_group_key(array $buyer_group): string`
  - `clean_tax_id($value): string`
  - `normalize_organization_label($value): string`
  - `normalize_organization_key($value): string`
- Consumed by: `Margin_FSFacture` (this task) and `Organization_FSFacture::ajax_import_organizations()` (Task 6).

- [ ] **Step 1: Create `src/Buyer_Data_Helper.php`**

```php
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
```

- [ ] **Step 2: Syntax-check the new file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\Buyer_Data_Helper.php"`
Expected: `No syntax errors detected in ...Buyer_Data_Helper.php`

- [ ] **Step 3: Delete the now-duplicated methods from `Margin_FSFacture.php`**

Delete these eight private method blocks entirely (find each by its `private function` signature — line numbers below are current, but deleting earlier ones shifts later ones, so delete bottom-up or re-locate by signature after each deletion):

1. `private function get_facture_ids() { ... }` (currently around line 547-557)
2. `private function get_facture_data($post_id) { ... }` (currently around line 586-588)
3. `private function get_facture_date($facture, $facture_data) { ... }` (currently around line 762-771)
4. `private function normalize_date($date) { ... }` (currently around line 773-785)
5. `private function get_buyer_group_key($buyer_group) { ... }` (currently around line 644-663)
6. `private function clean_tax_id($value) { ... }` (currently around line 787-789)
7. `private function normalize_organization_label($value) { ... }` (currently around line 791-796)
8. `private function normalize_organization_key($value) { ... }` (currently around line 798-823)

Each method body is byte-for-byte identical to the corresponding method already written into `Buyer_Data_Helper.php` in Step 1 (modulo `$this->` vs `self::` on internal calls), so you can diff against that file to confirm you're deleting the right block.

- [ ] **Step 4: Point remaining call sites at `Buyer_Data_Helper`**

In `src/Margin_FSFacture.php`, do eight whole-file find-and-replace passes (replace all occurrences in each pass):

| Find                                  | Replace                                          |
|----------------------------------------|---------------------------------------------------|
| `$this->get_facture_ids(`              | `Buyer_Data_Helper::get_facture_ids(`              |
| `$this->get_facture_data(`             | `Buyer_Data_Helper::get_facture_data(`             |
| `$this->get_facture_date(`             | `Buyer_Data_Helper::get_facture_date(`             |
| `$this->normalize_date(`               | `Buyer_Data_Helper::normalize_date(`               |
| `$this->get_buyer_group_key(`          | `Buyer_Data_Helper::get_buyer_group_key(`          |
| `$this->clean_tax_id(`                 | `Buyer_Data_Helper::clean_tax_id(`                 |
| `$this->normalize_organization_label(` | `Buyer_Data_Helper::normalize_organization_label(` |
| `$this->normalize_organization_key(`   | `Buyer_Data_Helper::normalize_organization_key(`   |

There are 18 call sites total across the file (in `get_buyer_organization_groups()`, `build_margin_report()`, `get_facture_signature()`, `buyer_matches_filter()`, `add_buyer_details()`); running each find/replace with "replace all" covers every one because these method names are unique in the file (verified: no other method shares any of these names).

No `use` statement is needed for `Buyer_Data_Helper` — it's in the same `Finespirits\FsFacture` namespace as `Margin_FSFacture`.

- [ ] **Step 5: Syntax-check the modified file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\Margin_FSFacture.php"`
Expected: `No syntax errors detected in ...Margin_FSFacture.php`

- [ ] **Step 6: [Manual — WP Admin] Regression-check Margin Report**

Open the Margin Report admin page (Factures → Margin Report). Run a report with no filters, then with an organization filter and a date range, on the same data used before this refactor. Confirm the output (totals, per-facture rows, buyer organization list in the filter dropdown) is identical to before the change — this task must not alter Margin Report behavior.

---

### Task 4: [Manual — WP Admin] Add the `buyer_organization_ref` field to the facture form

No code in this task — configures the existing `facture_group` ACF field group through the WP Admin UI.

- [ ] **Step 1: Add the field**

In WP Admin → Custom Fields, open the field group that contains `facture_group` (the one with the `Buyer` (`buyer_group`) sub-group described in the design spec). Inside `buyer_group`, add a new field **above** `buyer_organization`:

- Field Label: `Organization (from directory)`
- Field Name: `buyer_organization_ref`
- Field Type: `Post Object`
- Post Type: `Organization` (the `fs_organization` CPT)
- Select multiple values: No
- Return Format: Post Object (default) — the AJAX handler in Task 5 sends the numeric post ID as `organization_id`, read from the select's value, so Return Format does not affect Task 5.
- Allow Null: Yes

Save the field group.

- [ ] **Step 2: Verify**

Open an existing facture for editing (or create a new one). Confirm the new "Organization (from directory)" select appears above the "Organization" text field in the Buyer section, and that it lists the organizations created in Tasks 1-2. Selecting one and saving the facture should not error (autofill JS doesn't exist yet — that's Task 5 — so nothing should visibly happen yet beyond the field itself saving its selection).

---

### Task 5: Autofill AJAX endpoint + JS

**Files:**
- Modify: `src/Organization_FSFacture.php`
- Create: `assets/acf/organization/organization.js`

**Interfaces:**
- Consumes: `Buyer_Data_Helper` is not needed here (reads `fs_organization` ACF fields directly via `get_field()`).
- Produces: AJAX action `fs_facture_get_organization_data`, JS-localized object `window.fsFactureOrganization = { ajaxUrl, nonce, action }`.

- [ ] **Step 1: Add the AJAX handler and script enqueue to `Organization_FSFacture`**

Replace the whole class body of `src/Organization_FSFacture.php` (from Task 1) with:

```php
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
```

- [ ] **Step 2: Create `assets/acf/organization/organization.js`**

```js
(function($){

    jQuery(document).on('change', '.acf-field[data-name="buyer_organization_ref"] select', function(){

        const organizationId = $(this).val();

        const container = $(this).closest('.acf-field[data-name="buyer_group"]');

        if (!organizationId) return;

        $.post(fsFactureOrganization.ajaxUrl, {

            action: fsFactureOrganization.action,

            nonce: fsFactureOrganization.nonce,

            organization_id: organizationId

        }, function(response) {

            if (response.success) {

                container.find('.acf-field[data-name="buyer_organization"] textarea').val(response.data.organization);
                container.find('.acf-field[data-name="buyer_nip"] input').val(response.data.nip);
                container.find('.acf-field[data-name="buyer_country_code"] input').val(response.data.country_code);
                container.find('.acf-field[data-name="buyer_street"] input').val(response.data.street);
                container.find('.acf-field[data-name="buyer_city"] input').val(response.data.city);
                container.find('.acf-field[data-name="buyer_postal_code"] input').val(response.data.postal_code);

            }

        });

    });

})(jQuery);
```

- [ ] **Step 3: Syntax-check the PHP file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\Organization_FSFacture.php"`
Expected: `No syntax errors detected in ...Organization_FSFacture.php`

- [ ] **Step 4: [Manual — WP Admin] Verify autofill end-to-end**

1. Open an organization created earlier, make sure its NIP/Country/Street/City/Postal Code fields are filled with distinct test values, save.
2. Open a facture for editing, select that organization in "Organization (from directory)".
3. Confirm the six `buyer_*` text fields below it are immediately populated with the organization's data.
4. Edit one of the auto-filled fields by hand and save the facture — confirm the edit persists (fields are a snapshot, not a live binding).
5. Open the browser devtools Network tab and re-trigger the selection — confirm the POST to `admin-ajax.php?action=fs_facture_get_organization_data` returns `success: true` with the expected field values.

---

### Task 6: Import organizations from existing factures

**Files:**
- Modify: `src/Organization_FSFacture.php`
- Create: `assets/admin/organization-import.js`

**Interfaces:**
- Consumes: `Buyer_Data_Helper::get_facture_ids()`, `::get_facture_data()`, `::get_facture_date()`, `::get_buyer_group_key()`, `::normalize_organization_label()` (all from Task 3).
- Produces: AJAX action `fs_facture_import_organizations`, admin submenu page `edit.php?post_type=fs_organization` → `fs-facture-organization-import`.

- [ ] **Step 1: Add the submenu page, script enqueue, and AJAX import handler**

In `src/Organization_FSFacture.php`, add the new constant next to `AJAX_ACTION_AUTOFILL`:

```php
const AJAX_ACTION_AUTOFILL = 'fs_facture_get_organization_data';
const AJAX_ACTION_IMPORT = 'fs_facture_import_organizations';
const IMPORT_PAGE_SLUG = 'fs-facture-organization-import';
```

Update `init()` to also register the submenu page and the import AJAX action:

```php
public function init() {
    add_action('init', [$this, 'register_post_type']);
    add_action('admin_menu', [$this, 'register_import_page']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    add_action('wp_ajax_' . self::AJAX_ACTION_AUTOFILL, [$this, 'ajax_get_organization_data']);
    add_action('wp_ajax_' . self::AJAX_ACTION_IMPORT, [$this, 'ajax_import_organizations']);
}
```

Replace `enqueue_admin_scripts()` (from Task 5) with a version that also handles the import page:

```php
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
```

Add the submenu page registration and its renderer, plus the import handler, as new methods on the class:

```php
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
```

- [ ] **Step 2: Create `assets/admin/organization-import.js`**

```js
(function($){

    $(document).on('click', '#fs-facture-import-organizations', function(e){

        e.preventDefault();

        const $button = $(this);
        const $result = $('#fs-facture-import-result');

        $button.prop('disabled', true);
        $result.text(fsFactureOrganizationImport.i18n.loading);

        $.post(fsFactureOrganizationImport.ajaxUrl, {

            action: fsFactureOrganizationImport.action,

            nonce: fsFactureOrganizationImport.nonce

        }, function(response) {

            $button.prop('disabled', false);

            if (response.success) {
                $result.text(
                    fsFactureOrganizationImport.i18n.done
                        .replace('%created%', response.data.created)
                        .replace('%skipped%', response.data.skipped)
                );
            } else {
                $result.text(fsFactureOrganizationImport.i18n.error);
            }

        });

    });

})(jQuery);
```

- [ ] **Step 3: Syntax-check the PHP file**

Run: `php -l "D:\Work\finespirit\2026\factures\fs-facture\src\Organization_FSFacture.php"`
Expected: `No syntax errors detected in ...Organization_FSFacture.php`

- [ ] **Step 4: [Manual — WP Admin] Verify import end-to-end**

1. Go to Organizations → Import from Factures, click **Start Import**.
2. Confirm the result text shows plausible `created`/`skipped` counts (created ≈ number of distinct buyers across existing factures not already in the directory from Tasks 1-2's test records).
3. Open the Organizations list — confirm the new records have the expected NIP/address data from each buyer's most recent facture.
4. Manually edit one imported organization's address field, save.
5. Click **Start Import** again — confirm `created: 0` (or only newly-appeared buyers) and that the manually-edited organization's address was **not** reverted.

---

### Task 7: Final regression pass against the spec's testing checklist

No new files — this task just walks the spec's "Тестирование" section end-to-end now that everything is wired up, to catch any interaction between tasks that individual manual checks above might have missed.

- [ ] **Step 1: [Manual — WP Admin] Full walkthrough**

1. Confirm CPT `fs_organization` and its menu are present (Task 1).
2. Confirm the ACF field group and its 5 fields work on a fresh organization (Task 2).
3. Create a new facture, pick an organization via `buyer_organization_ref`, confirm the six `buyer_*` fields autofill and remain editable (Task 4-5).
4. Save that facture and confirm the PDF (`Download PDF` meta box) and XML export (`XML_FSFacture`) render with the expected buyer data, with no template errors — these templates were not modified by this plan, so output should look exactly as it did before, just populated via autofill instead of manual typing.
5. Run **Import from Factures** on real data (Task 6), confirm counts look right, and confirm running it a second time is a no-op for already-imported organizations.
6. Run a Margin Report before/after comparison (same as Task 3 Step 6) to confirm it is fully unaffected by everything added in this plan — it must keep deriving its organization list from facture data exactly as before.

- [ ] **Step 2: Note the known, intentionally-unfixed issue**

Confirm `src/KSeF_XML_Builder.php` was not touched by this plan (per explicit user decision recorded in the spec) — it still ignores `buyer_street`/`buyer_city`/`buyer_postal_code`/`buyer_country_code` and hardcodes `KodKraju = 'PL'`. This remains a separate, not-yet-scheduled fix.
