<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Renderer {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function get_print_view( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return '';
        }

        $settings = WooPrint_Settings::instance()->get_option();
        $data     = $this->build_data( $order, $settings );
        $html     = $this->render_html( $data, $settings );

        return $html;
    }

    public function build_data( $order, $settings = array() ) {
        $data = array(
            'order_id'      => $order->get_id(),
            'order_date'    => $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y H:i' ) : current_time( 'd/m/Y H:i' ),
            'order_status'  => wc_get_order_status_name( $order->get_status() ),
            'order_total'   => $order->get_formatted_order_total(),
            'payment_method'=> $order->get_payment_method_title(),
            'header_image'  => '',
            'chest_text'    => '',
            'foot_text'     => '',
            'items'         => array(),
            'billing'       => array(),
            'order_notes'   => array(),
        );

        if ( !empty( $settings['header_image'] ) ) {
            $src = wp_get_attachment_image_url( $settings['header_image'], 'full' );
            if ( $src ) {
                $data['header_image'] = $src;
            }
        }

        $data['chest_text'] = $settings['chest_text'] ?? '';
        $data['foot_text']  = $settings['foot_text'] ?? '';

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $line_total = wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );

            $data['items'][] = array(
                'id'       => $product ? $product->get_id() : 0,
                'sku'      => $product ? $product->get_sku() : '',
                'name'     => $item->get_name(),
                'qty'      => $item->get_quantity(),
                'total'    => $line_total,
                'raw_total'=> $item->get_total(),
            );
        }

        if ( 'yes' === ( $settings['show_billing_info'] ?? 'no' ) ) {
            $data['billing'] = array(
                'name'    => $order->get_formatted_billing_full_name(),
                'email'   => $order->get_billing_email(),
                'phone'   => $order->get_billing_phone(),
                'address' => $order->get_billing_address_1(),
                'city'    => $order->get_billing_city(),
            );
        }

        if ( 'yes' === ( $settings['show_order_notes'] ?? 'no' ) ) {
            $notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
            foreach ( $notes as $note ) {
                if ( $note->customer_note ) {
                    $data['order_notes'][] = $note->content;
                }
            }
        }

        return $data;
    }

    public function render_html( $data, $settings = array() ) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php esc_html_e( 'Receipt', 'si-wooprint-receipts' ); ?> - #<?php echo esc_html( $data['order_id'] ); ?></title>
            <style>
                <?php $this->render_inline_css( $settings ); ?>
            </style>
        </head>
        <body>
            <?php $this->render_receipt( $data, $settings ); ?>
            <script>
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    public function render_receipt( $data, $settings = array() ) {
        $template = SI_WOOPRINT_PATH . 'templates/receipt.php';
        if ( file_exists( get_stylesheet_directory() . '/si-wooprint-receipts/receipt.php' ) ) {
            $template = get_stylesheet_directory() . '/si-wooprint-receipts/receipt.php';
        }
        include $template;
    }

    private function render_inline_css( $settings ) {
        $width = isset( $settings['paper_width'] ) ? $settings['paper_width'] : '80mm';
        $css_file = SI_WOOPRINT_PATH . 'assets/css/print.css';
        if ( file_exists( $css_file ) ) {
            $css = file_get_contents( $css_file );
            $css = str_replace( '{paper_width}', $width, $css );
            echo $css;
        }
    }

    public function ajax_get_receipt() {
        check_ajax_referer( 'wooprint_print', 'nonce' );

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( !$order_id ) {
            wp_die( -1 );
        }

        if ( !current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $html = $this->get_print_view( $order_id );
        if ( empty( $html ) ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'si-wooprint-receipts' ) ) );
        }

        wp_send_json_success( array( 'html' => $html ) );
    }

    public function ajax_get_bulk_receipts() {
        check_ajax_referer( 'wooprint_print', 'nonce' );

        if ( !current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $order_ids = isset( $_POST['order_ids'] ) ? array_map( 'absint', $_POST['order_ids'] ) : array();

        $html_parts = array();
        foreach ( $order_ids as $oid ) {
            $part = $this->get_print_view( $oid );
            if ( !empty( $part ) ) {
                $html_parts[] = $part;
            }
        }

        if ( empty( $html_parts ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid orders found.', 'si-wooprint-receipts' ) ) );
        }

        $combined = implode( '<div class="page-break"></div>', $html_parts );

        wp_send_json_success( array( 'html' => $combined ) );
    }
}
