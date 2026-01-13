<?php
/**
 * GDPR Handler Class.
 *
 * Handles GDPR data export, deletion, and retention.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GDPR Handler class.
 *
 * @since 1.0.0
 */
class GDPR_Handler {

    /**
     * Export user data by email.
     *
     * @since 1.0.0
     * @param string $email User email.
     * @return array Exported data.
     */
    public function export_data( $email ) {
        global $wpdb;

        $email = sanitize_email( $email );

        if ( ! is_email( $email ) ) {
            return array();
        }

        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $forms_table = $wpdb->prefix . 'headless_forms';

        // Get all submissions for this email.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, f.form_name 
                 FROM {$submissions_table} s 
                 LEFT JOIN {$forms_table} f ON s.form_id = f.id
                 WHERE s.submitter_email = %s
                 ORDER BY s.created_at DESC",
                $email
            )
        );

        $export = array(
            'email'            => $email,
            'export_date'      => current_time( 'c' ),
            'submissions'      => array(),
            'total_submissions' => count( $submissions ),
        );

        foreach ( $submissions as $submission ) {
            $export['submissions'][] = array(
                'id'              => $submission->id,
                'form_name'       => $submission->form_name,
                'data'            => json_decode( $submission->submission_data, true ),
                'meta'            => json_decode( $submission->meta_data, true ),
                'status'          => $submission->status,
                'submitted_at'    => $submission->created_at,
            );
        }

        return $export;
    }

    /**
     * Delete user data by email.
     *
     * @since 1.0.0
     * @param string $email User email.
     * @return int Number of records deleted.
     */
    public function delete_data( $email ) {
        global $wpdb;

        $email = sanitize_email( $email );

        if ( ! is_email( $email ) ) {
            return 0;
        }

        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $email_logs_table = $wpdb->prefix . 'headless_email_logs';

        // Get submission IDs first.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submission_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$submissions_table} WHERE submitter_email = %s",
                $email
            )
        );

        $deleted = 0;

        if ( ! empty( $submission_ids ) ) {
            $ids_placeholder = implode( ',', array_map( 'intval', $submission_ids ) );

            // Delete email logs for these submissions.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query(
                "DELETE FROM {$email_logs_table} WHERE submission_id IN ({$ids_placeholder})"
            );

            // Delete submissions.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$submissions_table} WHERE submitter_email = %s",
                    $email
                )
            );
        }

        /**
         * Action after GDPR data deletion.
         *
         * @since 1.0.0
         * @param string $email   The email address.
         * @param int    $deleted Number of records deleted.
         */
        do_action( 'headless_forms_gdpr_data_deleted', $email, $deleted );

        return $deleted;
    }

    /**
     * Process data retention cleanup.
     *
     * @since 1.0.0
     * @return int Number of submissions deleted.
     */
    public function process_data_retention() {
        $retention_days = (int) get_option( 'headless_forms_data_retention_days', 0 );

        // 0 means keep forever.
        if ( $retention_days <= 0 ) {
            return 0;
        }

        global $wpdb;

        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $email_logs_table = $wpdb->prefix . 'headless_email_logs';

        // Get old submission IDs.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $old_submissions = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$submissions_table} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );

        if ( empty( $old_submissions ) ) {
            return 0;
        }

        $ids_placeholder = implode( ',', array_map( 'intval', $old_submissions ) );

        // Delete email logs.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            "DELETE FROM {$email_logs_table} WHERE submission_id IN ({$ids_placeholder})"
        );

        // Delete old submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$submissions_table} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );

        return $deleted;
    }

    /**
     * Anonymize submission data.
     *
     * @since 1.0.0
     * @param int $submission_id Submission ID.
     * @return bool
     */
    public function anonymize_submission( $submission_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'headless_submissions';

        // Get current submission.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $submission_id
            )
        );

        if ( ! $submission ) {
            return false;
        }

        $data = json_decode( $submission->submission_data, true );

        // Anonymize email and personal fields.
        $anonymized_data = array();
        foreach ( $data as $key => $value ) {
            if ( is_email( $value ) ) {
                $anonymized_data[ $key ] = '[REDACTED]';
            } elseif ( in_array( strtolower( $key ), array( 'name', 'phone', 'address', 'first_name', 'last_name' ), true ) ) {
                $anonymized_data[ $key ] = '[REDACTED]';
            } else {
                $anonymized_data[ $key ] = $value;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $table,
            array(
                'submission_data' => wp_json_encode( $anonymized_data ),
                'submitter_email' => null,
                'ip_address'      => '0.0.0.0',
                'user_agent'      => '[ANONYMIZED]',
            ),
            array( 'id' => $submission_id )
        ) !== false;
    }

    /**
     * Generate privacy policy text.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_privacy_policy_text() {
        $retention_days = (int) get_option( 'headless_forms_data_retention_days', 0 );
        $retention_text = $retention_days > 0 
            ? sprintf( __( 'Form submission data is retained for %d days.', 'headless-forms' ), $retention_days )
            : __( 'Form submission data is retained indefinitely.', 'headless-forms' );

        return sprintf(
            '<h3>%s</h3>
            <p>%s</p>
            <p>%s</p>
            <p>%s</p>',
            __( 'Form Submissions', 'headless-forms' ),
            __( 'When you submit a form on our website, we collect the information you provide along with your IP address and browser information.', 'headless-forms' ),
            $retention_text,
            __( 'You may request export or deletion of your data by contacting us.', 'headless-forms' )
        );
    }
}
