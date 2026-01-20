<?php
/**
 * Uninstall script for Headless Forms.
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It handles cleanup of plugin data based on user preference.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

// Prevent direct access and ensure this is a valid uninstall request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if user wants to keep data.
$keep_data = get_option( 'headless_forms_keep_data_on_delete', false );

if ( ! $keep_data ) {
    global $wpdb;

    // Delete custom tables.
    $tables = array(
        $wpdb->prefix . 'headless_forms',
        $wpdb->prefix . 'headless_submissions',
        $wpdb->prefix . 'headless_email_logs',
        $wpdb->prefix . 'headless_webhooks',
        $wpdb->prefix . 'headless_uploads',
    );

    foreach ( $tables as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    // Delete all plugin options.
    $options = array(
        'headless_forms_api_key',
        'headless_forms_db_version',
        'headless_forms_email_provider',
        'headless_forms_provider_settings',
        'headless_forms_rate_limit',
        'headless_forms_rate_limit_window',
        'headless_forms_honeypot_field',
        'headless_forms_keep_data_on_delete',
        'headless_forms_cors_origins',
        'headless_forms_data_retention_days',
        'headless_forms_auto_responder_enabled',
        'headless_forms_auto_responder_template',
        'headless_forms_recaptcha_enabled',
        'headless_forms_recaptcha_site_key',
        'headless_forms_recaptcha_secret_key',
        'headless_forms_ip_blocklist',
        'headless_forms_ip_allowlist',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // Delete transients.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_headless_forms_%',
            '_transient_timeout_headless_forms_%'
        )
    );

    // Delete rate limiting transients.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_hf_rate_%',
            '_transient_timeout_hf_rate_%'
        )
    );

    // Clear scheduled events.
    wp_clear_scheduled_hook( 'headless_forms_cleanup_logs' );
    wp_clear_scheduled_hook( 'headless_forms_retry_failed_emails' );
    wp_clear_scheduled_hook( 'headless_forms_data_retention' );

    // Delete user meta related to the plugin.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'headless_forms_%'
        )
    );

    // Delete uploaded files.
    $upload_dir = wp_upload_dir();
    $hf_upload_path = trailingslashit( $upload_dir['basedir'] ) . 'headless-forms';

    if ( is_dir( $hf_upload_path ) ) {
        // Simple recursive deletion helper.
        $delete_recursive = function( $path ) use ( &$delete_recursive ) {
            if ( ! is_dir( $path ) ) {
                return unlink( $path );
            }
            $items = scandir( $path );
            foreach ( $items as $item ) {
                if ( $item === '.' || $item === '..' ) {
                    continue;
                }
                $delete_recursive( $path . DIRECTORY_SEPARATOR . $item );
            }
            return rmdir( $path );
        };
        $delete_recursive( $hf_upload_path );
    }
}

// Flush rewrite rules.
flush_rewrite_rules();
