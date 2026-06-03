<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Settings {

    private static $instance = null;
    private $option_group = 'wooprint_settings';
    private $option_name  = 'wooprint_settings';
    private $page_slug    = 'wooprint-settings';

    private $defaults = array(
        'header_image'        => '',
        'chest_text'          => '',
        'foot_text'           => '',
        'show_column_id'      => 'yes',
        'show_column_qty'     => 'yes',
        'show_column_name'    => 'yes',
        'show_column_total'   => 'yes',
        'name_truncate'       => 30,
        'paper_width'         => '80mm',
        'show_billing_info'   => 'no',
        'show_order_notes'    => 'no',
    );

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 50 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'WooPrint Receipts', 'si-wooprint-receipts' ),
            __( 'WooPrint Receipts', 'si-wooprint-receipts' ),
            'manage_woocommerce',
            $this->page_slug,
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array(
                'sanitize_callback' => array( $this, 'sanitize' ),
            )
        );

        add_settings_section(
            'wooprint_general',
            __( 'General Settings', 'si-wooprint-receipts' ),
            null,
            $this->page_slug
        );

        add_settings_field(
            'header_image',
            __( 'Header Image', 'si-wooprint-receipts' ),
            array( $this, 'field_header_image' ),
            $this->page_slug,
            'wooprint_general'
        );

        add_settings_field(
            'chest_text',
            __( 'Header Text', 'si-wooprint-receipts' ),
            array( $this, 'field_textarea' ),
            $this->page_slug,
            'wooprint_general',
            array( 'key' => 'chest_text', 'desc' => __( 'Additional text displayed at the top of the receipt', 'si-wooprint-receipts' ) )
        );

        add_settings_field(
            'foot_text',
            __( 'Footer Text', 'si-wooprint-receipts' ),
            array( $this, 'field_textarea' ),
            $this->page_slug,
            'wooprint_general',
            array( 'key' => 'foot_text', 'desc' => __( 'Additional text displayed at the bottom of the receipt', 'si-wooprint-receipts' ) )
        );

        add_settings_section(
            'wooprint_columns',
            __( 'Table Columns', 'si-wooprint-receipts' ),
            null,
            $this->page_slug
        );

        $columns = array(
            'show_column_id'    => __( 'Product ID', 'si-wooprint-receipts' ),
            'show_column_qty'   => __( 'Quantity', 'si-wooprint-receipts' ),
            'show_column_name'  => __( 'Product Name', 'si-wooprint-receipts' ),
            'show_column_total' => __( 'Line Total', 'si-wooprint-receipts' ),
        );

        foreach ( $columns as $key => $label ) {
            add_settings_field(
                $key,
                $label,
                array( $this, 'field_checkbox' ),
                $this->page_slug,
                'wooprint_columns',
                array( 'key' => $key )
            );
        }

        add_settings_field(
            'name_truncate',
            __( 'Product Name Truncation', 'si-wooprint-receipts' ),
            array( $this, 'field_name_truncate' ),
            $this->page_slug,
            'wooprint_columns'
        );

        add_settings_section(
            'wooprint_advanced',
            __( 'Advanced Settings', 'si-wooprint-receipts' ),
            null,
            $this->page_slug
        );

        add_settings_field(
            'paper_width',
            __( 'Paper Width', 'si-wooprint-receipts' ),
            array( $this, 'field_paper_width' ),
            $this->page_slug,
            'wooprint_advanced'
        );

        add_settings_field(
            'show_billing_info',
            __( 'Show Billing Info', 'si-wooprint-receipts' ),
            array( $this, 'field_checkbox' ),
            $this->page_slug,
            'wooprint_advanced',
            array( 'key' => 'show_billing_info' )
        );

        add_settings_field(
            'show_order_notes',
            __( 'Show Order Notes', 'si-wooprint-receipts' ),
            array( $this, 'field_checkbox' ),
            $this->page_slug,
            'wooprint_advanced',
            array( 'key' => 'show_order_notes' )
        );
    }

    public function sanitize( $input ) {
        $output = $this->get_defaults();

        if ( isset( $input['header_image'] ) ) {
            $output['header_image'] = absint( $input['header_image'] );
        }
        if ( isset( $input['chest_text'] ) ) {
            $output['chest_text'] = wp_kses_post( $input['chest_text'] );
        }
        if ( isset( $input['foot_text'] ) ) {
            $output['foot_text'] = wp_kses_post( $input['foot_text'] );
        }

        foreach ( array( 'show_column_id', 'show_column_qty', 'show_column_name', 'show_column_total', 'show_billing_info', 'show_order_notes' ) as $cb ) {
            $output[ $cb ] = isset( $input[ $cb ] ) && '1' === $input[ $cb ] ? 'yes' : 'no';
        }

        if ( isset( $input['name_truncate'] ) ) {
            $val = intval( $input['name_truncate'] );
            $output['name_truncate'] = $val < 0 ? -1 : $val;
        }

        if ( isset( $input['paper_width'] ) && in_array( $input['paper_width'], array( '58mm', '80mm' ), true ) ) {
            $output['paper_width'] = $input['paper_width'];
        }

        return $output;
    }

    public function get_option( $key = null ) {
        $options = get_option( $this->option_name, array() );
        $options = wp_parse_args( $options, $this->get_defaults() );

        if ( is_null( $key ) ) {
            return $options;
        }
        return isset( $options[ $key ] ) ? $options[ $key ] : '';
    }

    public function get_defaults() {
        return $this->defaults;
    }

    public function render_settings_page() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->page_slug );
                submit_button();
                ?>
            </form>
            <hr>
            <h2><?php esc_html_e( 'Quick Links', 'si-wooprint-receipts' ); ?></h2>
            <p>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Orders', 'si-wooprint-receipts' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function field_header_image() {
        $value = $this->get_option( 'header_image' );
        $preview = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
        ?>
        <div class="wooprint-image-field">
            <input type="hidden" name="wooprint_settings[header_image]" id="wooprint_header_image" value="<?php echo esc_attr( $value ); ?>">
            <div id="wooprint-header-preview">
                <?php if ( $preview ) : ?>
                    <img src="<?php echo esc_url( $preview ); ?>" style="max-width:200px;height:auto;display:block;margin-bottom:8px;">
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="wooprint-upload-header">
                <?php esc_html_e( 'Select Image', 'si-wooprint-receipts' ); ?>
            </button>
            <button type="button" class="button" id="wooprint-remove-header" <?php echo $value ? '' : 'style="display:none;"'; ?>>
                <?php esc_html_e( 'Remove', 'si-wooprint-receipts' ); ?>
            </button>
            <p class="description"><?php esc_html_e( 'Recommended: 400x100px (PNG or JPG)', 'si-wooprint-receipts' ); ?></p>
        </div>
        <?php
    }

    public function field_textarea( $args ) {
        $value = $this->get_option( $args['key'] );
        ?>
        <textarea name="wooprint_settings[<?php echo esc_attr( $args['key'] ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( !empty( $args['desc'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    public function field_checkbox( $args ) {
        $value = $this->get_option( $args['key'] );
        ?>
        <label>
            <input type="checkbox" name="wooprint_settings[<?php echo esc_attr( $args['key'] ); ?>]" value="1" <?php checked( $value, 'yes' ); ?>>
            <?php esc_html_e( 'Visible', 'si-wooprint-receipts' ); ?>
        </label>
        <?php
    }

    public function field_name_truncate() {
        $value = $this->get_option( 'name_truncate' );
        ?>
        <input type="number" name="wooprint_settings[name_truncate]" value="<?php echo esc_attr( $value ); ?>" min="-1" step="1" class="small-text">
        <p class="description"><?php esc_html_e( 'Maximum characters for product name. Set -1 for no limit.', 'si-wooprint-receipts' ); ?></p>
        <?php
    }

    public function field_paper_width() {
        $value = $this->get_option( 'paper_width' );
        ?>
        <select name="wooprint_settings[paper_width]">
            <option value="80mm" <?php selected( $value, '80mm' ); ?>>80mm</option>
            <option value="58mm" <?php selected( $value, '58mm' ); ?>>58mm</option>
        </select>
        <p class="description"><?php esc_html_e( 'Select your thermal printer paper width.', 'si-wooprint-receipts' ); ?></p>
        <?php
    }
}
