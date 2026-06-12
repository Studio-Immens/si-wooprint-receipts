<?php
/**
 * @package           SI_WooPrint_Receipts
 * @author            Studio Immens
 * @link              https://studioimmens.com
 *
 * @wordpress-plugin
 * Plugin Name:       SI Print Receipts - POS Receipt & Thermal Printer for WooCommerce
 * Plugin URI:        https://studioimmens.com/si-wooprint-receipts
 * Description:       Generate professional, customizable POS receipts for WooCommerce orders. Perfect for retail stores, restaurants, and thermal printers.
 * Version:           1.0.0
 * Author:            Studio Immens
 * Author URI:        https://studioimmens.com
 * Text Domain:       si-wooprint-receipts
 * Domain Path:       /languages
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins:  woocommerce
 * Requires at least: 5.8
 * Tested up to:      7.0
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'SI_WOOPRINT_VERSION', '1.0.0' );
define( 'SI_WOOPRINT_FILE', __FILE__ );
define( 'SI_WOOPRINT_PATH', plugin_dir_path( __FILE__ ) );
define( 'SI_WOOPRINT_URL', plugin_dir_url( __FILE__ ) );
define( 'SI_WOOPRINT_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( function ( $class ) {
    $prefix = 'WooPrint_';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $class_name = str_replace( $prefix, '', $class );
    $file       = SI_WOOPRINT_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

add_action( 'plugins_loaded', 'si_wooprint_init' );

function si_wooprint_init() {
    if ( !class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'si_wooprint_woocommerce_missing_notice' );
        return;
    }
    WooPrint_Main::instance();
}

function si_wooprint_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'SI Print Receipts requires WooCommerce to be installed and active.', 'si-wooprint-receipts' ); ?></p>
    </div>
    <?php
}
