<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Rule_Engine {

    private static $instance = null;
    const POST_TYPE = 'wooprint_rule';

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

        add_action( 'woocommerce_new_order', array( $this, 'on_new_order' ), 20, 1 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_change' ), 20, 3 );

        add_action( 'wp_ajax_wooprint_save_rule_order', array( $this, 'ajax_save_order' ) );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels'          => array(
                'name'               => __( 'Print Rules', 'si-wooprint-receipts' ),
                'singular_name'      => __( 'Print Rule', 'si-wooprint-receipts' ),
                'add_new'            => __( 'Add Rule', 'si-wooprint-receipts' ),
                'add_new_item'       => __( 'Add New Rule', 'si-wooprint-receipts' ),
                'edit_item'          => __( 'Edit Rule', 'si-wooprint-receipts' ),
                'view_item'          => __( 'View Rule', 'si-wooprint-receipts' ),
                'search_items'       => __( 'Search Rules', 'si-wooprint-receipts' ),
                'not_found'          => __( 'No rules found.', 'si-wooprint-receipts' ),
                'not_found_in_trash' => __( 'No rules found in trash.', 'si-wooprint-receipts' ),
                'menu_name'          => __( 'Print Rules', 'si-wooprint-receipts' ),
            ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'woocommerce',
            'supports'        => array( 'title' ),
            'capability_type' => 'shop_order',
            'map_meta_cap'    => true,
            'show_in_rest'    => false,
            'menu_order'      => true,
        ) );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wooprint_rule_trigger',
            __( 'Trigger', 'si-wooprint-receipts' ),
            array( $this, 'render_trigger_metabox' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'wooprint_rule_filters',
            __( 'Filters (who & what)', 'si-wooprint-receipts' ),
            array( $this, 'render_filters_metabox' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'wooprint_rule_action',
            __( 'Action', 'si-wooprint-receipts' ),
            array( $this, 'render_action_metabox' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_trigger_metabox( $post ) {
        wp_nonce_field( 'wooprint_rule_save', 'wooprint_rule_nonce' );

        $trigger    = get_post_meta( $post->ID, '_wooprint_trigger', true ) ?: 'new_order';
        $statuses   = get_post_meta( $post->ID, '_wooprint_trigger_statuses', true ) ?: array();
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'When', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_trigger" id="wooprint_trigger">
                        <option value="new_order" <?php selected( $trigger, 'new_order' ); ?>>
                            <?php esc_html_e( 'New order is placed', 'si-wooprint-receipts' ); ?>
                        </option>
                        <option value="status_change" <?php selected( $trigger, 'status_change' ); ?>>
                            <?php esc_html_e( 'Order status changes', 'si-wooprint-receipts' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr id="wooprint_trigger_statuses_row" <?php echo 'status_change' !== $trigger ? 'style="display:none;"' : ''; ?>>
                <th scope="row"><?php esc_html_e( 'On status', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_trigger_statuses[]" multiple style="min-width:200px;height:120px;">
                        <?php foreach ( wc_get_order_statuses() as $key => $label ) : ?>
                            <?php $clean = str_replace( 'wc-', '', $key ); ?>
                            <option value="<?php echo esc_attr( $clean ); ?>" <?php selected( in_array( $clean, $statuses ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple statuses.', 'si-wooprint-receipts' ); ?></p>
                </td>
            </tr>
        </table>
        <script>
        jQuery('#wooprint_trigger').on('change', function() {
            jQuery('#wooprint_trigger_statuses_row').toggle('status_change' === this.value);
        });
        </script>
        <?php
    }

    public function render_filters_metabox( $post ) {
        $apply_all = get_post_meta( $post->ID, '_wooprint_apply_all', true );
        $users     = get_post_meta( $post->ID, '_wooprint_filter_users', true ) ?: array();
        $roles     = get_post_meta( $post->ID, '_wooprint_filter_roles', true ) ?: array();
        $categories = get_post_meta( $post->ID, '_wooprint_filter_categories', true ) ?: array();

        if ( !is_array( $users ) ) $users = array();
        if ( !is_array( $roles ) ) $roles = array();
        if ( !is_array( $categories ) ) $categories = array();
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Customer Users', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="wooprint_apply_all" value="1" <?php checked( $apply_all, '1' ); ?>>
                        <?php esc_html_e( 'Apply to all customers', 'si-wooprint-receipts' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'When checked, rule applies to every order regardless of who placed it.', 'si-wooprint-receipts' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Specific Users', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_filter_users[]" multiple style="min-width:250px;height:100px;">
                        <?php
                        $all_users = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'number' => 200 ) );
                        foreach ( $all_users as $u ) :
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( in_array( $u->ID, $users ) ); ?>>
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Only apply when order is placed by these users (customers).', 'si-wooprint-receipts' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Customer Roles', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_filter_roles[]" multiple style="min-width:250px;height:80px;">
                        <?php
                        foreach ( wp_roles()->get_names() as $key => $label ) :
                        ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $roles ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Product Categories (PRO)', 'si-wooprint-receipts' ); ?></th>
                <td>
                    <select name="wooprint_filter_categories[]" multiple style="min-width:250px;height:80px;" disabled>
                        <?php
                        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
                        foreach ( $terms as $t ) :
                        ?>
                            <option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( in_array( $t->term_id, $categories ) ); ?>>
                                <?php echo esc_html( $t->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Available in PRO version.', 'si-wooprint-receipts' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_action_metabox( $post ) {
        $printers = WooPrint_Printer::get_all_printers();
        $printer  = get_post_meta( $post->ID, '_wooprint_target_printer', true );
        ?>
        <p><strong><?php esc_html_e( 'Send to printer', 'si-wooprint-receipts' ); ?></strong></p>
        <p>
            <select name="wooprint_target_printer" style="width:100%;">
                <option value="">— <?php esc_html_e( 'Select Printer', 'si-wooprint-receipts' ); ?> —</option>
                <?php foreach ( $printers as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $printer, $p->ID ); ?>>
                        <?php echo esc_html( $p->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e( 'When this rule matches, a print job is sent to the selected printer.', 'si-wooprint-receipts' ); ?></p>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( !isset( $_POST['wooprint_rule_nonce'] ) || !wp_verify_nonce( wp_unslash( $_POST['wooprint_rule_nonce'] ), 'wooprint_rule_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        update_post_meta( $post_id, '_wooprint_trigger', sanitize_text_field( wp_unslash( $_POST['wooprint_trigger'] ?? 'new_order' ) ) );

        $statuses = isset( $_POST['wooprint_trigger_statuses'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wooprint_trigger_statuses'] ) ) : array();
        update_post_meta( $post_id, '_wooprint_trigger_statuses', $statuses );

        $apply_all = isset( $_POST['wooprint_apply_all'] ) ? '1' : '0';
        update_post_meta( $post_id, '_wooprint_apply_all', $apply_all );

        $users = isset( $_POST['wooprint_filter_users'] ) ? array_map( 'absint', $_POST['wooprint_filter_users'] ) : array();
        update_post_meta( $post_id, '_wooprint_filter_users', $users );

        $roles = isset( $_POST['wooprint_filter_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wooprint_filter_roles'] ) ) : array();
        update_post_meta( $post_id, '_wooprint_filter_roles', $roles );

        $printer = isset( $_POST['wooprint_target_printer'] ) ? absint( $_POST['wooprint_target_printer'] ) : 0;
        update_post_meta( $post_id, '_wooprint_target_printer', $printer );
    }

    public function columns( $columns ) {
        $cols = array();
        foreach ( $columns as $k => $v ) {
            if ( 'cb' === $k ) {
                $cols['order'] = '<span class="dashicons dashicons-menu" style="font-size:16px;width:16px;height:16px;" title="' . esc_attr__( 'Drag to reorder', 'si-wooprint-receipts' ) . '"></span>';
            }
            $cols[ $k ] = $v;
            if ( 'title' === $k ) {
                $cols['trigger']  = __( 'Trigger', 'si-wooprint-receipts' );
                $cols['printer']  = __( 'Printer', 'si-wooprint-receipts' );
                $cols['priority'] = __( 'Priority', 'si-wooprint-receipts' );
            }
        }
        return $cols;
    }

    public function column_data( $column, $post_id ) {
        if ( 'order' === $column ) {
            echo '<span class="dashicons dashicons-menu wooprint-drag-handle" style="font-size:16px;width:16px;height:16px;"></span>';
        }
        if ( 'trigger' === $column ) {
            $t = get_post_meta( $post_id, '_wooprint_trigger', true );
            echo 'new_order' === $t ? esc_html__( 'New Order', 'si-wooprint-receipts' ) : esc_html__( 'Status Change', 'si-wooprint-receipts' );
        }
        if ( 'printer' === $column ) {
            $pid = get_post_meta( $post_id, '_wooprint_target_printer', true );
            if ( $pid ) {
                $p = get_post( $pid );
                echo $p ? esc_html( $p->post_title ) : '—';
            } else {
                echo '—';
            }
        }
        if ( 'priority' === $column ) {
            echo esc_html( get_post_field( 'menu_order', $post_id ) ?: '0' );
        }
    }

    public function ajax_save_order() {
        check_ajax_referer( 'wooprint_print', 'nonce' );
        if ( !current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $rule_ids = isset( $_POST['rule_ids'] ) ? array_map( 'absint', $_POST['rule_ids'] ) : array();
        foreach ( $rule_ids as $order => $id ) {
            wp_update_post( array(
                'ID'         => $id,
                'menu_order' => $order,
            ) );
        }
        wp_send_json_success();
    }

    public function on_new_order( $order_id ) {
        $this->evaluate( $order_id, 'new_order' );
    }

    public function on_status_change( $order_id, $old_status, $new_status ) {
        $this->evaluate( $order_id, 'status_change', $new_status );
    }

    private function evaluate( $order_id, $trigger, $status = '' ) {
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return;
        }

        $rules = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ) );

        foreach ( $rules as $rule ) {
            if ( !$this->match_trigger( $rule, $trigger, $status ) ) {
                continue;
            }
            if ( !$this->match_users( $rule, $order ) ) {
                continue;
            }

            $product_match = apply_filters( 'wooprint_rule_match', true, $rule, $order );
            if ( !$product_match ) {
                continue;
            }

            $printer_id = get_post_meta( $rule->ID, '_wooprint_target_printer', true );
            if ( !$printer_id ) {
                continue;
            }

            $copies = get_post_meta( $printer_id, '_wooprint_copies', true ) ?: 1;
            for ( $i = 0; $i < intval( $copies ); $i++ ) {
                WooPrint_Queue::instance()->add_job( $order_id, $rule->ID, $printer_id );
            }
        }
    }

    private function match_trigger( $rule, $trigger, $status ) {
        $rule_trigger = get_post_meta( $rule->ID, '_wooprint_trigger', true );
        if ( $rule_trigger !== $trigger ) {
            return false;
        }
        if ( 'status_change' === $trigger && !empty( $status ) ) {
            $statuses = get_post_meta( $rule->ID, '_wooprint_trigger_statuses', true );
            if ( is_array( $statuses ) && !empty( $statuses ) && !in_array( $status, $statuses ) ) {
                return false;
            }
        }
        return true;
    }

    private function match_users( $rule, $order ) {
        $apply_all = get_post_meta( $rule->ID, '_wooprint_apply_all', true );
        if ( '1' === $apply_all ) {
            return true;
        }

        $customer_id = $order->get_customer_id();

        $users = get_post_meta( $rule->ID, '_wooprint_filter_users', true );
        if ( is_array( $users ) && !empty( $users ) && in_array( $customer_id, $users ) ) {
            return true;
        }

        $roles = get_post_meta( $rule->ID, '_wooprint_filter_roles', true );
        if ( is_array( $roles ) && !empty( $roles ) && $customer_id ) {
            $user = get_userdata( $customer_id );
            if ( $user && !empty( array_intersect( $roles, $user->roles ) ) ) {
                return true;
            }
        }

        $apply_all_meta = get_post_meta( $rule->ID, '_wooprint_apply_all', true );
        if ( '1' !== $apply_all_meta && empty( $users ) && empty( $roles ) ) {
            return true;
        }

        return false;
    }
}
