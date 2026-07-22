<?php
/*
Plugin Name: FS Facture
Description: Generates factures
Version: 1.0.0
Author: finespirits
Text Domain: https://finespirits.pl/
*/

define( 'FS_FACTURE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

use Finespirits\FsFacture\App_FSFacture;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php'; 
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';

class FS_Facture_Plugin {
    public $appFsFacture;
    public $acfInitFsFacture;

    public function __construct() {
        add_action('admin_init', array($this, 'init_app_admin'));
        $this->appFsFacture = new App_FSFacture();
        
    }

    public function init_app_admin() {
        if (!is_plugin_active('advanced-custom-fields-pro/acf.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p><strong>FS Factura plugin</strong> requires an installed and active <strong>Advanced Custom Fields </strong>.</p></div>'.plugin_basename(__FILE__);
            });
        }
    }
}

if (class_exists('FS_Facture_Plugin')) {
    $fs_factura_plugin = new FS_Facture_Plugin();
}

?>