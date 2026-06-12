<?php
defined( 'ABSPATH' ) || exit;

class WooPrint_Queue {

    private static $instance = null;
    private $table_name;

    const DB_VERSION = '1.0';
    const LOCK_SECONDS = 30;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wooprint_queue';

        add_action( 'admin_init', array( $this, 'maybe_create_table' ) );

        add_action( 'wp_ajax_wooprint_poll', array( $this, 'ajax_poll' ) );
        add_action( 'wp_ajax_wooprint_mark_printed', array( $this, 'ajax_mark_printed' ) );
    }

    public function maybe_create_table() {
        $version = get_option( 'wooprint_queue_db_version', '' );
        if ( $version === self::DB_VERSION ) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $this->table_name;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id    bigint(20) unsigned NOT NULL,
            rule_id     bigint(20) unsigned DEFAULT 0,
            printer_id  bigint(20) unsigned DEFAULT 0,
            user_id     bigint(20) unsigned DEFAULT 0,
            status      varchar(20) DEFAULT 'pending',
            data        longtext,
            created_at  datetime DEFAULT CURRENT_TIMESTAMP,
            locked_at   datetime DEFAULT NULL,
            printed_at  datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY printer_id (printer_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wooprint_queue_db_version', self::DB_VERSION );
    }

    public function add_job( $order_id, $rule_id, $printer_id ) {
        global $wpdb;

        $printer_users = WooPrint_Printer::get_users_for_printer( $printer_id );
        if ( empty( $printer_users ) ) {
            return false;
        }

        foreach ( $printer_users as $user_id ) {
            $wpdb->insert( $this->table_name, array(
                'order_id'   => intval( $order_id ),
                'rule_id'    => intval( $rule_id ),
                'printer_id' => intval( $printer_id ),
                'user_id'    => intval( $user_id ),
                'status'     => 'pending',
                'created_at' => current_time( 'mysql' ),
            ) );
        }

        return true;
    }

    public function get_pending_for_user( $user_id ) {
        global $wpdb;

        $locked_since = gmdate( 'Y-m-d H:i:s', time() - self::LOCK_SECONDS );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE user_id = %d
               AND status = 'pending'
               AND (locked_at IS NULL OR locked_at < %s)
             ORDER BY id ASC
             LIMIT 1",
            $user_id,
            $locked_since
        ) );
        // phpcs:enable
    }

    public function lock_job( $job_id ) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            array(
                'status'    => 'printing',
                'locked_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $job_id, 'status' => 'pending' )
        );
    }

    public function mark_printed( $job_id ) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            array(
                'status'     => 'printed',
                'printed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $job_id )
        );
    }

    public function ajax_poll() {
        check_ajax_referer( 'wooprint_print', 'nonce' );

        if ( !current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $user_id = get_current_user_id();
        $job     = $this->get_pending_for_user( $user_id );

        if ( empty( $job ) ) {
            wp_send_json_success( array( 'html' => '' ) );
        }

        $job = $job[0];

        $locked = $this->lock_job( $job->id );
        if ( !$locked ) {
            wp_send_json_success( array( 'html' => '' ) );
        }

        $html = WooPrint_Renderer::instance()->get_print_view( $job->order_id );
        if ( empty( $html ) ) {
            $this->mark_printed( $job->id );
            wp_send_json_success( array( 'html' => '' ) );
        }

        wp_send_json_success( array(
            'html'      => $html,
            'job_id'    => $job->id,
            'printer_id'=> $job->printer_id,
        ) );
    }

    public function ajax_mark_printed() {
        check_ajax_referer( 'wooprint_print', 'nonce' );

        if ( !current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
        if ( $job_id ) {
            $this->mark_printed( $job_id );
        }

        wp_send_json_success();
    }
}
