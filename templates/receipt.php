<?php
/**
 * Receipt template
 *
 * Available variables:
 * $data     - Array of receipt data
 * $settings - Array of plugin settings
 *
 * $data keys:
 *   order_id, order_date, order_status, order_total, payment_method,
 *   header_image, chest_text, foot_text, items[], billing[], order_notes[]
 */
defined( 'ABSPATH' ) || exit;
?>
<div id="WOP_Receipt">
    <button class="no-print wooprint-print-action" onclick="window.print()">
        <?php esc_html_e( 'Print', 'si-wooprint-receipts' ); ?>
    </button>

    <?php if ( !empty( $data['header_image'] ) ) : ?>
        <div class="header">
            <img src="<?php echo esc_url( $data['header_image'] ); ?>" alt="<?php esc_attr_e( 'Store Logo', 'si-wooprint-receipts' ); ?>">
        </div>
    <?php endif; ?>

    <?php if ( !empty( $data['chest_text'] ) ) : ?>
        <div class="full chest-text">
            <?php echo wp_kses_post( $data['chest_text'] ); ?>
        </div>
    <?php endif; ?>

    <div class="full">
        <p><?php esc_html_e( 'Order', 'si-wooprint-receipts' ); ?> #<?php echo esc_html( $data['order_id'] ); ?></p>
    </div>

    <div class="full date-row">
        <p><?php echo esc_html( $data['order_date'] ); ?></p>
    </div>

    <?php if ( !empty( $data['billing'] ) ) : ?>
        <div class="full billing-row">
            <p><strong><?php esc_html_e( 'Customer', 'si-wooprint-receipts' ); ?>:</strong> <?php echo esc_html( $data['billing']['name'] ); ?></p>
        </div>
    <?php endif; ?>

    <table class="full">
        <thead>
            <tr>
                <?php if ( 'yes' === ( $settings['show_column_id'] ?? 'yes' ) ) : ?>
                    <th class="col-id"><?php esc_html_e( 'ID', 'si-wooprint-receipts' ); ?></th>
                <?php endif; ?>
                <?php if ( 'yes' === ( $settings['show_column_qty'] ?? 'yes' ) ) : ?>
                    <th class="col-qty"><?php esc_html_e( 'QTY', 'si-wooprint-receipts' ); ?></th>
                <?php endif; ?>
                <?php if ( 'yes' === ( $settings['show_column_name'] ?? 'yes' ) ) : ?>
                    <th class="col-name"><?php esc_html_e( 'ITEM', 'si-wooprint-receipts' ); ?></th>
                <?php endif; ?>
                <?php if ( 'yes' === ( $settings['show_column_total'] ?? 'yes' ) ) : ?>
                    <th class="col-total"><?php esc_html_e( 'TOTAL', 'si-wooprint-receipts' ); ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $data['items'] as $item ) : ?>
                <?php
                $truncate = intval( $settings['name_truncate'] ?? 30 );
                $name = $item['name'];
                if ( $truncate > 0 && mb_strlen( $name ) > $truncate ) {
                    $name = mb_substr( $name, 0, $truncate ) . '..';
                }
                ?>
                <tr>
                    <?php if ( 'yes' === ( $settings['show_column_id'] ?? 'yes' ) ) : ?>
                        <td class="col-id"><?php echo esc_html( $item['id'] ); ?></td>
                    <?php endif; ?>
                    <?php if ( 'yes' === ( $settings['show_column_qty'] ?? 'yes' ) ) : ?>
                        <td class="col-qty"><?php echo esc_html( $item['qty'] ); ?></td>
                    <?php endif; ?>
                    <?php if ( 'yes' === ( $settings['show_column_name'] ?? 'yes' ) ) : ?>
                        <td class="col-name"><?php echo esc_html( $name ); ?></td>
                    <?php endif; ?>
                    <?php if ( 'yes' === ( $settings['show_column_total'] ?? 'yes' ) ) : ?>
                        <td class="col-total"><?php echo wp_kses_post( $item['total'] ); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="full total-row">
        <p><strong><?php esc_html_e( 'Total', 'si-wooprint-receipts' ); ?>:</strong> <?php echo wp_kses_post( $data['order_total'] ); ?></p>
    </div>

    <?php if ( !empty( $data['payment_method'] ) ) : ?>
        <div class="full payment-row">
            <p><?php esc_html_e( 'Payment', 'si-wooprint-receipts' ); ?>: <?php echo esc_html( $data['payment_method'] ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( !empty( $data['order_notes'] ) ) : ?>
        <div class="full notes-row">
            <p><strong><?php esc_html_e( 'Notes', 'si-wooprint-receipts' ); ?>:</strong></p>
            <?php foreach ( $data['order_notes'] as $note ) : ?>
                <p><?php echo esc_html( $note ); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( !empty( $data['foot_text'] ) ) : ?>
        <div class="full foot-text">
            <?php echo wp_kses_post( $data['foot_text'] ); ?>
        </div>
    <?php endif; ?>
</div>
