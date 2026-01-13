<?php
/**
 * Email Logs List Table.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Email Logs Table class.
 *
 * @since 1.0.0
 */
class Email_Logs_Table extends \WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'email_log',
            'plural'   => 'email_logs',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'id'         => __( 'ID', 'headless-forms' ),
            'email_type' => __( 'Type', 'headless-forms' ),
            'provider'   => __( 'Provider', 'headless-forms' ),
            'recipient'  => __( 'Recipient', 'headless-forms' ),
            'subject'    => __( 'Subject', 'headless-forms' ),
            'status'     => __( 'Status', 'headless-forms' ),
            'created_at' => __( 'Sent At', 'headless-forms' ),
        );
    }

    public function get_sortable_columns() {
        return array(
            'created_at' => array( 'created_at', true ),
            'status'     => array( 'status', false ),
        );
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $current_provider = isset( $_GET['provider'] ) ? sanitize_text_field( $_GET['provider'] ) : '';

        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e( 'All Statuses', 'headless-forms' ); ?></option>
                <option value="sent" <?php selected( $current_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'headless-forms' ); ?></option>
                <option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'headless-forms' ); ?></option>
                <option value="pending" <?php selected( $current_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'headless-forms' ); ?></option>
            </select>
            <?php submit_button( __( 'Filter', 'headless-forms' ), '', 'filter', false ); ?>
        </div>
        <?php
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 30;
        $current_page = $this->get_pagenum();
        $table = $wpdb->prefix . 'headless_email_logs';

        $where = array( '1=1' );
        $values = array();

        if ( ! empty( $_GET['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = sanitize_text_field( $_GET['status'] );
        }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_items = (int) $wpdb->get_var( empty( $values ) ? $count_sql : $wpdb->prepare( $count_sql, $values ) );

        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'created_at';
        $order = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( $current_page - 1 ) * $per_page;
        $query_values = $values;
        $query_values[] = $per_page;
        $query_values[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $this->items = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ) );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ) );

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
    }

    public function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
    }

    public function column_status( $item ) {
        $classes = array(
            'sent'    => 'hf-status-active',
            'failed'  => 'hf-status-spam',
            'pending' => 'hf-status-new',
        );
        $class = isset( $classes[ $item->status ] ) ? $classes[ $item->status ] : '';

        $output = sprintf( '<span class="hf-status %s">%s</span>', $class, esc_html( ucfirst( $item->status ) ) );

        if ( $item->status === 'failed' && $item->retry_count < $item->max_retries ) {
            $output .= sprintf( ' <small>(%d/%d retries)</small>', $item->retry_count, $item->max_retries );
        }

        if ( ! empty( $item->error_message ) ) {
            $output .= '<br><small class="hf-error">' . esc_html( $item->error_message ) . '</small>';
        }

        return $output;
    }

    public function column_provider( $item ) {
        $providers = array(
            'wp_mail'       => 'WP Mail',
            'smtp'          => 'SMTP',
            'aws_ses'       => 'AWS SES',
            'sendgrid'      => 'SendGrid',
            'resend'        => 'Resend',
            'mailgun'       => 'Mailgun',
            'postmark'      => 'Postmark',
            'sparkpost'     => 'SparkPost',
            'mandrill'      => 'Mandrill',
            'elastic_email' => 'Elastic Email',
            'brevo'         => 'Brevo',
            'mailersend'    => 'MailerSend',
            'mailjet'       => 'Mailjet',
            'smtp2go'       => 'SMTP2GO',
            'moosend'       => 'Moosend',
            'loops'         => 'Loops',
        );

        return isset( $providers[ $item->provider ] ) ? esc_html( $providers[ $item->provider ] ) : esc_html( $item->provider );
    }

    public function column_created_at( $item ) {
        return esc_html( date_i18n( 'M j, Y H:i', strtotime( $item->created_at ) ) );
    }

    public function no_items() {
        esc_html_e( 'No email logs found.', 'headless-forms' );
    }
}
