<?php
/**
 * Analytics Class.
 *
 * Provides analytics and statistics for form submissions.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Analytics class.
 *
 * @since 1.0.0
 */
class Analytics {

    /**
     * Get dashboard statistics.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_dashboard_stats() {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'headless_forms';
        $submissions_table = $wpdb->prefix . 'headless_submissions';

        // Total forms.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_forms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" );

        // Total submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_submissions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$submissions_table}" );

        // Today's submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $today_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$submissions_table} WHERE DATE(created_at) = CURDATE()"
        );

        // This week's submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $week_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$submissions_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Unread submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $unread_submissions = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$submissions_table} WHERE status = %s",
                'new'
            )
        );

        return array(
            'total_forms'        => $total_forms,
            'total_submissions'  => $total_submissions,
            'today_submissions'  => $today_submissions,
            'week_submissions'   => $week_submissions,
            'unread_submissions' => $unread_submissions,
        );
    }

    /**
     * Get submissions over time.
     *
     * @since 1.0.0
     * @param int    $days    Number of days.
     * @param int    $form_id Optional form ID.
     * @return array
     */
    public function get_submissions_chart( $days = 30, $form_id = null ) {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'headless_submissions';

        $where = $form_id 
            ? $wpdb->prepare( 'AND form_id = %d', $form_id ) 
            : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                 FROM {$submissions_table} 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) {$where}
                 GROUP BY DATE(created_at) 
                 ORDER BY date ASC",
                $days
            )
        );

        // Fill in missing dates.
        $chart_data = array();
        $current = strtotime( "-{$days} days" );
        $end = strtotime( 'today' );

        while ( $current <= $end ) {
            $date = gmdate( 'Y-m-d', $current );
            $chart_data[ $date ] = 0;
            $current = strtotime( '+1 day', $current );
        }

        foreach ( $results as $row ) {
            $chart_data[ $row->date ] = (int) $row->count;
        }

        return array(
            'labels' => array_keys( $chart_data ),
            'data'   => array_values( $chart_data ),
        );
    }

    /**
     * Get submissions by form.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_submissions_by_form() {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'headless_forms';
        $submissions_table = $wpdb->prefix . 'headless_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            "SELECT f.id, f.form_name, COUNT(s.id) as submission_count 
             FROM {$forms_table} f
             LEFT JOIN {$submissions_table} s ON f.id = s.form_id
             GROUP BY f.id
             ORDER BY submission_count DESC"
        );
    }

    /**
     * Get top referrers.
     *
     * @since 1.0.0
     * @param int $limit Number of results.
     * @return array
     */
    public function get_top_referrers( $limit = 10 ) {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'headless_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referrer_url, COUNT(*) as count 
                 FROM {$submissions_table} 
                 WHERE referrer_url IS NOT NULL AND referrer_url != ''
                 GROUP BY referrer_url 
                 ORDER BY count DESC 
                 LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get submission status breakdown.
     *
     * @since 1.0.0
     * @param int $form_id Optional form ID.
     * @return array
     */
    public function get_status_breakdown( $form_id = null ) {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'headless_submissions';

        $where = $form_id 
            ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) 
            : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$submissions_table} {$where}
             GROUP BY status"
        );

        $breakdown = array(
            'new'      => 0,
            'read'     => 0,
            'spam'     => 0,
            'trash'    => 0,
            'archived' => 0,
        );

        foreach ( $results as $row ) {
            $breakdown[ $row->status ] = (int) $row->count;
        }

        return $breakdown;
    }

    /**
     * Get recent submissions.
     *
     * @since 1.0.0
     * @param int $limit Number of results.
     * @return array
     */
    public function get_recent_submissions( $limit = 5 ) {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'headless_forms';
        $submissions_table = $wpdb->prefix . 'headless_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, f.form_name 
                 FROM {$submissions_table} s
                 LEFT JOIN {$forms_table} f ON s.form_id = f.id
                 ORDER BY s.created_at DESC 
                 LIMIT %d",
                $limit
            )
        );

        foreach ( $submissions as &$submission ) {
            $submission->submission_data = json_decode( $submission->submission_data, true );
        }

        return $submissions;
    }
}
