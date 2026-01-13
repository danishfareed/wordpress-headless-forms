<?php
/**
 * Email Logger Class.
 *
 * Logs email delivery attempts and provides retry functionality.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Email Logger class.
 *
 * @since 1.0.0
 */
class Email_Logger {

    /**
     * Log an email attempt.
     *
     * @since 1.0.0
     * @param array $data Log data.
     * @return int|false The log ID or false on failure.
     */
    public function log( $data ) {
        global $wpdb;

        $defaults = array(
            'submission_id'   => null,
            'form_id'         => null,
            'email_type'      => 'notification',
            'provider'        => 'wp_mail',
            'recipient'       => '',
            'subject'         => '',
            'message_body'    => '',
            'headers'         => '',
            'status'          => 'pending',
            'error_message'   => '',
            'error_code'      => '',
            'retry_count'     => 0,
            'max_retries'     => 3,
            'provider_response' => '',
            'sent_at'         => null,
            'created_at'      => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        if ( $data['status'] === 'sent' ) {
            $data['sent_at'] = current_time( 'mysql' );
        }

        $table = $wpdb->prefix . 'headless_email_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            array(
                'submission_id'     => $data['submission_id'],
                'form_id'           => $data['form_id'],
                'email_type'        => $data['email_type'],
                'provider'          => $data['provider'],
                'recipient'         => $data['recipient'],
                'subject'           => $data['subject'],
                'message_body'      => $data['message_body'],
                'headers'           => is_array( $data['headers'] ) ? wp_json_encode( $data['headers'] ) : $data['headers'],
                'status'            => $data['status'],
                'error_message'     => $data['error_message'],
                'error_code'        => $data['error_code'],
                'retry_count'       => $data['retry_count'],
                'max_retries'       => $data['max_retries'],
                'provider_message_id' => isset( $data['provider_message_id'] ) ? $data['provider_message_id'] : null,
                'provider_response' => $data['provider_response'],
                'sent_at'           => $data['sent_at'],
                'created_at'        => $data['created_at'],
            )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get logs with optional filters.
     *
     * @since 1.0.0
     * @param array $args Query arguments.
     * @return array
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'form_id'    => null,
            'status'     => null,
            'provider'   => null,
            'limit'      => 50,
            'offset'     => 0,
            'order_by'   => 'created_at',
            'order'      => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'headless_email_logs';

        $where = array( '1=1' );
        $values = array();

        if ( $args['form_id'] ) {
            $where[] = 'form_id = %d';
            $values[] = $args['form_id'];
        }

        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ( $args['provider'] ) {
            $where[] = 'provider = %s';
            $values[] = $args['provider'];
        }

        $where_sql = implode( ' AND ', $where );
        $order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Retry failed emails.
     *
     * @since 1.0.0
     * @return int Number of emails retried.
     */
    public function retry_failed_emails() {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_email_logs';

        // Get failed emails that haven't exceeded max retries.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $failed = $wpdb->get_results(
            "SELECT * FROM {$table} 
             WHERE status = 'failed' 
             AND retry_count < max_retries 
             AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             LIMIT 10"
        );

        $retried = 0;
        $plugin = Plugin::get_instance();
        $email_factory = $plugin->get_email_factory();

        foreach ( $failed as $log ) {
            $headers = json_decode( $log->headers, true ) ?: array();

            $result = $email_factory->send(
                $log->recipient,
                $log->subject,
                $log->message_body,
                $headers
            );

            // Handle new array return type or legacy bool.
            if ( is_array( $result ) ) {
                $sent = $result['success'];
                $error_message = $result['error'];
                $message_id = $result['message_id'];
            } else {
                $sent = (bool) $result;
                $error_message = $sent ? '' : 'Unknown error (legacy provider)';
                $message_id = null;
            }

            // Update log.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table,
                array(
                    'status'              => $sent ? 'sent' : 'failed',
                    'error_message'       => $error_message,
                    'provider_message_id' => $message_id,
                    'retry_count'         => $log->retry_count + 1,
                    'sent_at'             => $sent ? current_time( 'mysql' ) : null,
                    'next_retry_at'       => $sent ? null : gmdate( 'Y-m-d H:i:s', strtotime( '+' . pow( 2, $log->retry_count + 1 ) . ' minutes' ) ),
                ),
                array( 'id' => $log->id )
            );

            if ( $sent ) {
                $retried++;
            }
        }

        return $retried;
    }

    /**
     * Cleanup old logs.
     *
     * @since 1.0.0
     * @param int $days Number of days to keep logs.
     * @return int Number of logs deleted.
     */
    public function cleanup_old_logs( $days = 30 ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_email_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get log statistics.
     *
     * @since 1.0.0
     * @param int $form_id Optional form ID.
     * @return array
     */
    public function get_stats( $form_id = null ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_email_logs';
        $where = $form_id ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$table} {$where}"
        );

        return array(
            'total'   => (int) $stats->total,
            'sent'    => (int) $stats->sent,
            'failed'  => (int) $stats->failed,
            'pending' => (int) $stats->pending,
        );
    }
}
