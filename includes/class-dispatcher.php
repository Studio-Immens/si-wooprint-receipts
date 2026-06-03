<?php
defined( 'ABSPATH' ) || exit;

abstract class WooPrint_Dispatcher {

    abstract public function dispatch( $job );

    protected function get_receipt_html( $order_id ) {
        return WooPrint_Renderer::instance()->get_print_view( $order_id );
    }

    protected function get_printer_setting( $printer_id, $key, $default = '' ) {
        return get_post_meta( $printer_id, '_wooprint_' . $key, true ) ?: $default;
    }

    public static function factory( $connection_type ) {
        $class_map = array(
            'browser' => 'WooPrint_Dispatcher_Browser',
        );

        if ( isset( $class_map[ $connection_type ] ) ) {
            return new $class_map[ $connection_type ]();
        }

        return new WooPrint_Dispatcher_Browser();
    }
}
