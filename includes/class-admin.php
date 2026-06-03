<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Admin {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_list_column' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_list_column' ), 10, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_list_column' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_list_column' ), 10, 2 );

        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_action' ) );
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_action' ) );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_action' ), 10, 3 );

        add_action( 'admin_notices', array( $this, 'bulk_print_notice' ) );

        add_action( 'wp_ajax_wooprint_get_bulk_receipts', array( $this, 'ajax_get_bulk_receipts' ) );
    }

    public function enqueue_assets( $hook ) {
        $screen = get_current_screen();
        $is_order_page = $screen && (
            ( 'shop_order' === $screen->post_type && in_array( $screen->base, array( 'edit', 'post' ), true ) )
            || 'woocommerce_page_wc-orders' === $screen->id
        );
        $is_settings_page = $screen && 'woocommerce_page_wooprint-settings' === $screen->base;
        $is_printer_page = $screen && 'wooprint_printer' === $screen->post_type;
        $is_rule_page = $screen && 'wooprint_rule' === $screen->post_type;

        if ( $is_order_page ) {
            wp_enqueue_style( 'wooprint-admin', SI_WOOPRINT_URL . 'assets/css/admin.css', array(), SI_WOOPRINT_VERSION );
        }

        if ( $is_order_page || $is_settings_page || $is_printer_page || $is_rule_page ) {
            wp_enqueue_media();
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

        if ( $is_order_page || $is_printer_page || $is_rule_page ) {
            $this->enqueue_stream_assets();
        }

        if ( $is_rule_page ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
        }
    }

    private function enqueue_stream_assets() {
        if ( !current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        $printers = WooPrint_Printer::get_all_printers();
        $assigned = false;
        foreach ( $printers as $printer ) {
            $users = WooPrint_Printer::get_users_for_printer( $printer->ID );
            if ( in_array( $user_id, $users ) ) {
                $assigned = true;
                break;
            }
        }

        if ( !$assigned ) {
            return;
        }

        wp_enqueue_script( 'wooprint-stream', SI_WOOPRINT_URL . 'assets/js/print-stream.js', array( 'jquery' ), SI_WOOPRINT_VERSION, true );
        wp_localize_script( 'wooprint-stream', 'wooprintStream', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wooprint_print' ),
            'interval' => apply_filters( 'wooprint_poll_interval', 5000 ),
            'enabled'  => '1',
        ) );
    }

    public function add_meta_box() {
        add_meta_box(
            'wooprint_receipt',
            __( 'WooPrint Receipt', 'si-wooprint-receipts' ),
            array( $this, 'render_meta_box' ),
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_meta_box( $post_or_order ) {
        $order_id = $post_or_order instanceof WC_Order ? $post_or_order->get_id() : $post_or_order->ID;
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            echo '<p>' . esc_html__( 'Order not found.', 'si-wooprint-receipts' ) . '</p>';
            return;
        }
        ?>
        <p>
            <button type="button" class="button button-primary wooprint-print-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>" style="width:100%;text-align:center;">
                <span class="dashicons dashicons-printer" style="margin-top:3px;"></span>
                <?php esc_html_e( 'Print Receipt', 'si-wooprint-receipts' ); ?>
            </button>
        </p>
        <p>
            <button type="button" class="button wooprint-preview-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>" style="width:100%;text-align:center;">
                <?php esc_html_e( 'Preview Receipt', 'si-wooprint-receipts' ); ?>
            </button>
        </p>
        <?php
    }

    public function add_order_list_column( $columns ) {
        $reordered = array();
        foreach ( $columns as $key => $label ) {
            $reordered[ $key ] = $label;
            if ( 'order_status' === $key ) {
                $reordered['wooprint_actions'] = '<span class="dashicons dashicons-printer" title="' . esc_attr__( 'Print Receipt', 'si-wooprint-receipts' ) . '"></span>';
            }
        }
        return $reordered;
    }

    public function render_order_list_column( $column, $post_or_order ) {
        if ( 'wooprint_actions' !== $column ) {
            return;
        }
        $order_id = $post_or_order instanceof WC_Order ? $post_or_order->get_id() : intval( $post_or_order );
        ?>
        <button type="button" class="button wooprint-print-btn-small" data-order-id="<?php echo esc_attr( $order_id ); ?>" title="<?php esc_attr_e( 'Print Receipt', 'si-wooprint-receipts' ); ?>">
            <span class="dashicons dashicons-printer"></span>
        </button>
        <?php
    }

    public function add_bulk_action( $actions ) {
        $actions['wooprint_bulk_print'] = __( 'Print Receipts', 'si-wooprint-receipts' );
        return $actions;
    }

    public function handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
        if ( 'wooprint_bulk_print' !== $doaction ) {
            return $redirect_to;
        }

        $ids = implode( ',', array_map( 'absint', $post_ids ) );
        $redirect_to = add_query_arg( array(
            'wooprint_bulk' => $ids,
        ), $redirect_to );

        return $redirect_to;
    }

    public function bulk_print_notice() {
        $screen = get_current_screen();
        if ( !$screen || !in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
            return;
        }
        if ( empty( $_GET['wooprint_bulk'] ) ) {
            return;
        }
        $ids = array_map( 'absint', explode( ',', $_GET['wooprint_bulk'] ) );
        $ids = array_filter( $ids );
        if ( empty( $ids ) ) {
            return;
        }
        $count = count( $ids );
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php echo esc_html( sprintf( _n( '%d order ready for printing.', '%d orders ready for printing.', $count, 'si-wooprint-receipts' ), $count ) ); ?></strong>
            </p>
            <p>
                <button type="button" class="button button-primary" id="wooprint-bulk-print-btn" data-order-ids="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
                    <span class="dashicons dashicons-printer" style="margin-top:3px;"></span>
                    <?php esc_html_e( 'Print All Receipts', 'si-wooprint-receipts' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    public function ajax_get_bulk_receipts() {
        WooPrint_Renderer::instance()->ajax_get_bulk_receipts();
    }
}
