<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Frontend {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_print_button_my_account' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'add_print_button_thankyou' ), 10, 1 );
        add_shortcode( 'wooprint_receipt', array( $this, 'shortcode_print_button' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_ajax_wooprint_get_receipt', array( $this, 'ajax_get_receipt_frontend' ) );
        add_action( 'wp_ajax_nopriv_wooprint_get_receipt', array( $this, 'ajax_get_receipt_frontend' ) );
    }

    private function customer_can_print( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return false;
        }
        if ( current_user_can( 'edit_shop_orders' ) ) {
            return true;
        }
        if ( is_user_logged_in() && $order->get_customer_id() === get_current_user_id() ) {
            return true;
        }
        $order_received = get_query_var( 'order-received', 0 );
        if ( $order_received == $order_id ) {
            return true;
        }
        if ( $order->get_customer_id() === 0 && !is_user_logged_in() ) {
            $order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
            if ( !empty( $order_key ) && $order->get_order_key() === $order_key ) {
                return true;
            }
        }
        return false;
    }

    public function render_print_button( $order_id, $label = '' ) {
        if ( !$this->customer_can_print( $order_id ) ) {
            return '';
        }

        if ( empty( $label ) ) {
            $label = __( 'Print Receipt', 'si-wooprint-receipts' );
        }

        ob_start();
        ?>
        <button type="button" class="button wooprint-print-btn-front" data-order-id="<?php echo esc_attr( $order_id ); ?>">
            <span class="dashicons dashicons-printer"></span>
            <?php echo esc_html( $label ); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    public function add_print_button_my_account( $order ) {
        if ( !$order ) {
            return;
        }
        echo wp_kses_post( $this->render_print_button( $order->get_id() ) );
    }

    public function add_print_button_thankyou( $order_id ) {
        echo wp_kses_post( $this->render_print_button( $order_id ) );
    }

    public function shortcode_print_button( $atts ) {
        $atts = shortcode_atts( array(
            'order_id' => 0,
            'label'    => __( 'Print Receipt', 'si-wooprint-receipts' ),
        ), $atts, 'wooprint_receipt' );

        $order_id = absint( $atts['order_id'] );
        if ( !$order_id ) {
            $order_id = get_query_var( 'order-received', 0 );
        }

        return $this->render_print_button( $order_id, $atts['label'] );
    }

    public function enqueue_frontend_assets() {
        if ( wp_script_is( 'wooprint-admin', 'enqueued' ) ) {
            return;
        }
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'wooprint-frontend', SI_WOOPRINT_URL . 'assets/css/admin.css', array(), SI_WOOPRINT_VERSION );
        wp_enqueue_script( 'wooprint-admin', SI_WOOPRINT_URL . 'assets/js/admin.js', array( 'jquery' ), SI_WOOPRINT_VERSION, true );
        wp_localize_script( 'wooprint-admin', 'wooprintAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wooprint_print' ),
            'label'   => array(
                'printing' => __( 'Printing...', 'si-wooprint-receipts' ),
                'error'    => __( 'Error generating receipt.', 'si-wooprint-receipts' ),
            ),
        ) );
    }

    public function ajax_get_receipt_frontend() {
        check_ajax_referer( 'wooprint_print', 'nonce' );

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( !$order_id || !$this->customer_can_print( $order_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to print this receipt.', 'si-wooprint-receipts' ) ) );
        }

        $html = WooPrint_Renderer::instance()->get_print_view( $order_id );
        if ( empty( $html ) ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'si-wooprint-receipts' ) ) );
        }

        wp_send_json_success( array( 'html' => $html ) );
    }
}
