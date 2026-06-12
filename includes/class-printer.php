<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Printer {

    private static $instance = null;
    const POST_TYPE = 'wooprint_printer';

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_data' ), 10, 2 );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels'          => array(
                'name'               => __( 'Printers', 'si-wooprint-receipts' ),
                'singular_name'      => __( 'Printer', 'si-wooprint-receipts' ),
                'add_new'            => __( 'Add Printer', 'si-wooprint-receipts' ),
                'add_new_item'       => __( 'Add New Printer', 'si-wooprint-receipts' ),
                'edit_item'          => __( 'Edit Printer', 'si-wooprint-receipts' ),
                'view_item'          => __( 'View Printer', 'si-wooprint-receipts' ),
                'search_items'       => __( 'Search Printers', 'si-wooprint-receipts' ),
                'not_found'          => __( 'No printers found.', 'si-wooprint-receipts' ),
                'not_found_in_trash' => __( 'No printers found in trash.', 'si-wooprint-receipts' ),
                'menu_name'          => __( 'Printers', 'si-wooprint-receipts' ),
            ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'woocommerce',
            'supports'        => array( 'title' ),
            'capability_type' => 'shop_order',
            'map_meta_cap'    => true,
            'show_in_rest'    => false,
        ) );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wooprint_printer_settings',
            __( 'Printer Settings', 'si-wooprint-receipts' ),
            array( $this, 'render_settings_metabox' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'wooprint_printer_users',
            __( 'Assigned Users', 'si-wooprint-receipts' ),
            array( $this, 'render_users_metabox' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_settings_metabox( $post ) {
        wp_nonce_field( 'wooprint_printer_save', 'wooprint_printer_nonce' );

        $connection = get_post_meta( $post->ID, '_wooprint_connection', true ) ?: 'browser';
        $paper      = get_post_meta( $post->ID, '_wooprint_paper_width', true ) ?: '80mm';
        $auto_print = get_post_meta( $post->ID, '_wooprint_auto_print', true ) ?: 'no';
        $copies     = get_post_meta( $post->ID, '_wooprint_copies', true ) ?: 1;
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Connection Type', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_connection" id="wooprint_connection">
                        <option value="browser" <?php selected( $connection, 'browser' ); ?>>
                            <?php esc_html_e( 'Browser (opens print dialog)', 'si-wooprint-receipts' ); ?>
                        </option>
                        <option value="network" <?php selected( $connection, 'network' ); ?> disabled>
                            <?php esc_html_e( 'Network ESC/POS (PRO)', 'si-wooprint-receipts' ); ?>
                        </option>
                        <option value="usb" <?php selected( $connection, 'usb' ); ?> disabled>
                            <?php esc_html_e( 'USB/Print Node (PRO)', 'si-wooprint-receipts' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Paper Width', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_paper_width">
                        <option value="80mm" <?php selected( $paper, '80mm' ); ?>>80mm</option>
                        <option value="58mm" <?php selected( $paper, '58mm' ); ?>>58mm</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto Print', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="wooprint_auto_print" value="yes" <?php checked( $auto_print, 'yes' ); ?>>
                        <?php esc_html_e( 'Print automatically when job arrives (no confirmation dialog)', 'si-wooprint-receipts' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Browser will open print dialog automatically. Works only when the staff user is logged in.', 'si-wooprint-receipts' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Copies', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <input type="number" name="wooprint_copies" value="<?php echo esc_attr( $copies ); ?>" min="1" max="10" step="1" class="small-text">
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_users_metabox( $post ) {
        $assigned = get_post_meta( $post->ID, '_wooprint_assigned_users', true ) ?: array();
        if ( !is_array( $assigned ) ) {
            $assigned = array();
        }

        $users = get_users( array(
            'role__in' => array( 'administrator', 'shop_manager' ),
            'fields'   => array( 'ID', 'display_name' ),
        ) );

        ?>
        <p><?php esc_html_e( 'Select users who will receive print jobs from this printer:', 'si-wooprint-receipts' ); ?></p>
        <div style="max-height:200px;overflow-y:auto;">
            <?php foreach ( $users as $user ) : ?>
                <label style="display:block;margin:4px 0;">
                    <input type="checkbox" name="wooprint_assigned_users[]" value="<?php echo esc_attr( $user->ID ); ?>" <?php checked( in_array( $user->ID, $assigned ) ); ?>>
                    <?php echo esc_html( $user->display_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( !isset( $_POST['wooprint_printer_nonce'] ) || !wp_verify_nonce( wp_unslash( $_POST['wooprint_printer_nonce'] ), 'wooprint_printer_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        update_post_meta( $post_id, '_wooprint_connection', sanitize_text_field( wp_unslash( $_POST['wooprint_connection'] ?? 'browser' ) ) );
        update_post_meta( $post_id, '_wooprint_paper_width', sanitize_text_field( wp_unslash( $_POST['wooprint_paper_width'] ?? '80mm' ) ) );
        update_post_meta( $post_id, '_wooprint_auto_print', isset( $_POST['wooprint_auto_print'] ) ? 'yes' : 'no' );
        update_post_meta( $post_id, '_wooprint_copies', absint( $_POST['wooprint_copies'] ?? 1 ) );

        $users = isset( $_POST['wooprint_assigned_users'] ) ? array_map( 'absint', $_POST['wooprint_assigned_users'] ) : array();
        update_post_meta( $post_id, '_wooprint_assigned_users', $users );
    }

    public function columns( $columns ) {
        $cols = array();
        foreach ( $columns as $k => $v ) {
            $cols[ $k ] = $v;
            if ( 'title' === $k ) {
                $cols['connection'] = __( 'Connection', 'si-wooprint-receipts' );
                $cols['paper']      = __( 'Paper', 'si-wooprint-receipts' );
                $cols['users']      = __( 'Users', 'si-wooprint-receipts' );
            }
        }
        return $cols;
    }

    public function column_data( $column, $post_id ) {
        if ( 'connection' === $column ) {
            $c = get_post_meta( $post_id, '_wooprint_connection', true );
            echo esc_html( $c ?: 'browser' );
        }
        if ( 'paper' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wooprint_paper_width', true ) ?: '80mm' );
        }
        if ( 'users' === $column ) {
            $users = get_post_meta( $post_id, '_wooprint_assigned_users', true ) ?: array();
            echo count( $users ) ? esc_html( count( $users ) . ' ' . __( 'users', 'si-wooprint-receipts' ) ) : '—';
        }
    }

    public static function get_all_printers() {
        return get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
    }

    public static function get_users_for_printer( $printer_id ) {
        $users = get_post_meta( $printer_id, '_wooprint_assigned_users', true );
        return is_array( $users ) ? $users : array();
    }
}
