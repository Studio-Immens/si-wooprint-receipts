<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Main {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_textdomain();
        $this->init_hooks();
    }

    private function load_textdomain() {
        load_plugin_textdomain(
            'si-wooprint-receipts',
            false,
            dirname( plugin_basename( SI_WOOPRINT_FILE ) ) . '/languages'
        );
    }

    private function init_hooks() {
        WooPrint_Settings::instance();
        WooPrint_Renderer::instance();
        WooPrint_Printer::instance();
        WooPrint_Rule_Engine::instance();
        WooPrint_Queue::instance();

        if ( is_admin() ) {
            WooPrint_Admin::instance();
        }

        WooPrint_Frontend::instance();

        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
        add_filter( 'plugin_action_links_' . SI_WOOPRINT_BASENAME, array( $this, 'add_settings_link' ) );
    }

    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SI_WOOPRINT_FILE, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SI_WOOPRINT_FILE, true );
        }
    }

    public function add_settings_link( $links ) {
        $settings_url = admin_url( 'admin.php?page=wooprint-settings' );
        $links[] = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'si-wooprint-receipts' ) . '</a>';
        return $links;
    }
}
