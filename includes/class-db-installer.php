<?php
/**
 * Database Installer Class.
 *
 * Handles creation and updates of custom database tables.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database installer class.
 *
 * Creates and manages custom database tables for forms, submissions,
 * email logs, and webhooks.
 *
 * @since 1.0.0
 */
class DB_Installer {

    /**
     * WordPress database object.
     *
     * @since 1.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Database table prefix.
     *
     * @since 1.0.0
     * @var string
     */
    private $prefix;

    /**
     * Character set and collation for tables.
     *
     * @since 1.0.0
     * @var string
     */
    private $charset_collate;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb            = $wpdb;
        $this->prefix          = $wpdb->prefix;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Install database tables.
     *
     * Creates all required tables using dbDelta for safe updates.
     *
     * @since 1.0.0
     * @return void
     */
    public function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->create_forms_table();
        $this->create_submissions_table();
        $this->create_email_logs_table();
        $this->create_webhooks_table();

        // Update database version.
        update_option( 'headless_forms_db_version', HEADLESS_FORMS_DB_VERSION );
    }

    /**
     * Create the forms table.
     *
     * Stores form configurations including name, slug, email settings,
     * and notification preferences.
     *
     * @since 1.0.0
     * @return void
     */
    private function create_forms_table() {
        $table_name = $this->prefix . 'headless_forms';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_name varchar(255) NOT NULL,
            form_slug varchar(255) NOT NULL,
            form_description text DEFAULT NULL,
            email_settings longtext DEFAULT NULL,
            notification_enabled tinyint(1) NOT NULL DEFAULT 1,
            auto_responder_enabled tinyint(1) NOT NULL DEFAULT 0,
            auto_responder_settings longtext DEFAULT NULL,
            success_message text DEFAULT NULL,
            error_message text DEFAULT NULL,
            redirect_url varchar(500) DEFAULT NULL,
            allowed_origins text DEFAULT NULL,
            field_mapping longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY form_slug (form_slug),
            KEY status (status),
            KEY created_at (created_at)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Create the submissions table.
     *
     * Stores form submission data, metadata (IP, user agent),
     * and processing status.
     *
     * @since 1.0.0
     * @return void
     */
    private function create_submissions_table() {
        $table_name = $this->prefix . 'headless_submissions';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            submission_data longtext NOT NULL,
            meta_data longtext DEFAULT NULL,
            submitter_email varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            referrer_url varchar(500) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'new',
            is_starred tinyint(1) NOT NULL DEFAULT 0,
            email_sent tinyint(1) NOT NULL DEFAULT 0,
            auto_response_sent tinyint(1) NOT NULL DEFAULT 0,
            notes text DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY submitter_email (submitter_email),
            KEY ip_address (ip_address),
            KEY is_starred (is_starred),
            KEY created_at (created_at)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Create the email logs table.
     *
     * Tracks email delivery attempts including status, provider used,
     * and any error messages for debugging.
     *
     * @since 1.0.0
     * @return void
     */
    private function create_email_logs_table() {
        $table_name = $this->prefix . 'headless_email_logs';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) unsigned DEFAULT NULL,
            form_id bigint(20) unsigned DEFAULT NULL,
            email_type varchar(50) NOT NULL DEFAULT 'notification',
            provider varchar(50) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            message_body longtext DEFAULT NULL,
            headers text DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            error_message text DEFAULT NULL,
            error_code varchar(100) DEFAULT NULL,
            retry_count int(11) NOT NULL DEFAULT 0,
            max_retries int(11) NOT NULL DEFAULT 3,
            provider_response longtext DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            next_retry_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY form_id (form_id),
            KEY status (status),
            KEY provider (provider),
            KEY email_type (email_type),
            KEY created_at (created_at)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Create the webhooks table.
     *
     * Stores webhook configurations per form including URL,
     * authentication, and delivery settings.
     *
     * @since 1.0.0
     * @return void
     */
    private function create_webhooks_table() {
        $table_name = $this->prefix . 'headless_webhooks';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            webhook_name varchar(255) NOT NULL,
            webhook_url varchar(500) NOT NULL,
            http_method varchar(10) NOT NULL DEFAULT 'POST',
            content_type varchar(100) NOT NULL DEFAULT 'application/json',
            headers longtext DEFAULT NULL,
            auth_type varchar(50) DEFAULT NULL,
            auth_credentials longtext DEFAULT NULL,
            payload_template longtext DEFAULT NULL,
            trigger_event varchar(50) NOT NULL DEFAULT 'submission.created',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            retry_enabled tinyint(1) NOT NULL DEFAULT 1,
            max_retries int(11) NOT NULL DEFAULT 3,
            timeout_seconds int(11) NOT NULL DEFAULT 30,
            last_triggered_at datetime DEFAULT NULL,
            last_status varchar(50) DEFAULT NULL,
            last_response_code int(11) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY is_active (is_active),
            KEY trigger_event (trigger_event)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Check if upgrade is needed.
     *
     * Compares stored DB version with current version.
     *
     * @since 1.0.0
     * @return bool True if upgrade is needed.
     */
    public function needs_upgrade() {
        $installed_version = get_option( 'headless_forms_db_version', '0.0.0' );
        return version_compare( $installed_version, HEADLESS_FORMS_DB_VERSION, '<' );
    }

    /**
     * Perform database upgrade.
     *
     * @since 1.0.0
     * @return void
     */
    public function upgrade() {
        if ( $this->needs_upgrade() ) {
            $this->install();
        }
    }

    /**
     * Get forms table name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_forms_table() {
        return $this->prefix . 'headless_forms';
    }

    /**
     * Get submissions table name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_submissions_table() {
        return $this->prefix . 'headless_submissions';
    }

    /**
     * Get email logs table name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_email_logs_table() {
        return $this->prefix . 'headless_email_logs';
    }

    /**
     * Get webhooks table name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_webhooks_table() {
        return $this->prefix . 'headless_webhooks';
    }

    /**
     * Check if tables exist.
     *
     * @since 1.0.0
     * @return bool True if all tables exist.
     */
    public function tables_exist() {
        $tables = array(
            $this->get_forms_table(),
            $this->get_submissions_table(),
            $this->get_email_logs_table(),
            $this->get_webhooks_table(),
        );

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $this->wpdb->get_var(
                $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
            );
            if ( $result !== $table ) {
                return false;
            }
        }

        return true;
    }
}
