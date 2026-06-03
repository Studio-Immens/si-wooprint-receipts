<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Dispatcher_Browser extends WooPrint_Dispatcher {

    public function dispatch( $job ) {
        $html = $this->get_receipt_html( $job->order_id );
        if ( empty( $html ) ) {
            return false;
        }

        $auto_print = $this->get_printer_setting( $job->printer_id, 'auto_print', 'no' );

        if ( 'yes' === $auto_print ) {
            $html = str_replace(
                'window.onload = function() { window.print(); };',
                '',
                $html
            );
        }

        return $html;
    }
}
