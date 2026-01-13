<?php
/**
 * Admin Dashboard Class.
 *
 * Main admin controller for the plugin's dashboard, forms, submissions, and settings.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Admin;

use HeadlessForms\Plugin;
use HeadlessForms\Security;
use HeadlessForms\DB_Installer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Dashboard class.
 *
 * @since 1.0.0
 */
class Admin_Dashboard {

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Initialize the admin dashboard.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_headless_forms_test_email', array( $this, 'ajax_test_email' ) );
        add_action( 'wp_ajax_headless_forms_regenerate_key', array( $this, 'ajax_regenerate_key' ) );
        add_action( 'wp_ajax_headless_forms_save_webhook', array( $this, 'ajax_save_webhook' ) );
        add_action( 'wp_ajax_headless_forms_delete_webhook', array( $this, 'ajax_delete_webhook' ) );
    }

    /**
     * Register admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_menu() {
        // Main menu.
        add_menu_page(
            __( 'Headless Forms', 'headless-forms' ),
            __( 'Headless Forms', 'headless-forms' ),
            'manage_options',
            'headless-forms',
            array( $this, 'render_app' ),
            'dashicons-feedback',
            30
        );
        
        // We remove submenus to force SPA mode.
        // remove_submenu_page( 'headless-forms', 'headless-forms' );
    }

    /**
     * Render the main SPA app layout.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_app() {
        // Enqueue assets first.
        wp_enqueue_style( 'headless-forms-admin' );
        wp_enqueue_script( 'headless-forms-admin' );
        
        // Pass necessary data to JS.
        wp_localize_script(
            'headless-forms-admin',
            'headlessFormsAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'headless_forms_nonce' ),
                'apiUrl'  => rest_url( 'headless-forms/v1' ),
                'strings' => array(
                    'confirmDelete'    => __( 'Are you sure you want to delete this form? All data will be lost.', 'headless-forms' ),
                    'copied'           => __( 'Copied!', 'headless-forms' ),
                    'testEmailSending' => __( 'Sending...', 'headless-forms' ),
                ),
            )
        );

        // Determine current view and initialize required variables.
        $current_view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'dashboard';

        // Initialize variables based on view.
        switch ( $current_view ) {
            case 'dashboard':
                $plugin = Plugin::get_instance();
                $analytics = $plugin->get_analytics();
                $stats = $analytics->get_dashboard_stats();
                $chart_data = $analytics->get_submissions_chart( 30 );
                $recent = $analytics->get_recent_submissions( 5 );
                break;

            case 'forms':
                $forms_table = new Forms_Table();
                $forms_table->prepare_items();
                break;

            case 'new-form':
            case 'edit-form':
                $form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
                $form = null;
                if ( $form_id ) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'headless_forms';
                    $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $form_id ) );
                }
                break;

            case 'submissions':
                if ( isset( $_GET['submission_id'] ) ) {
                    $current_view = 'submission-detail';
                    global $wpdb;
                    $submission_id = (int) $_GET['submission_id'];
                    $subs_table = $wpdb->prefix . 'headless_submissions';
                    $forms_tbl = $wpdb->prefix . 'headless_forms';
                    $submission = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT s.*, f.form_name FROM {$subs_table} s LEFT JOIN {$forms_tbl} f ON s.form_id = f.id WHERE s.id = %d",
                            $submission_id
                        )
                    );
                    if ( $submission ) {
                        $submission->submission_data = json_decode( $submission->submission_data, true );
                        $submission->meta_data = json_decode( $submission->meta_data, true );
                        if ( $submission->status === 'new' ) {
                            $wpdb->update( $subs_table, array( 'status' => 'read', 'read_at' => current_time( 'mysql' ) ), array( 'id' => $submission_id ) );
                        }
                    }
                } else {
                    $submissions_table = new Submissions_Table();
                    $submissions_table->prepare_items();
                }
                break;

            case 'email-logs':
                $logs_table = new Email_Logs_Table();
                $logs_table->prepare_items();
                break;

            case 'settings':
                $plugin = Plugin::get_instance();
                $email_factory = $plugin->get_email_factory();
                $providers = $email_factory->get_all_providers();
                $current_provider = get_option( 'headless_forms_email_provider', 'wp_mail' );
                $api_key = get_option( 'headless_forms_api_key', '' );
                break;

            case 'how-to':
            default:
                // No special initialization needed.
                break;
        }

        include HEADLESS_FORMS_PATH . 'admin/views/layout.php';
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook ) {
        // Only load on our pages.
        if ( strpos( $hook, 'headless-forms' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'headless-forms-admin',
            HEADLESS_FORMS_URL . 'assets/css/admin.css',
            array(),
            HEADLESS_FORMS_VERSION
        );

        wp_enqueue_script(
            'headless-forms-admin',
            HEADLESS_FORMS_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            HEADLESS_FORMS_VERSION,
            true
        );

        wp_localize_script( 'headless-forms-admin', 'headlessFormsAdmin', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'headless_forms_admin' ),
            'restUrl'   => rest_url( 'headless-forms/v1/' ),
            'strings'   => array(
                'confirmDelete'    => __( 'Are you sure you want to delete this?', 'headless-forms' ),
                'testEmailSending' => __( 'Sending test email...', 'headless-forms' ),
                'copied'           => __( 'Copied!', 'headless-forms' ),
            ),
        ) );
    }

    /**
     * Handle admin actions.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_actions() {
        // Check for form save.
        if ( isset( $_POST['headless_forms_save_form'] ) ) {
            $this->save_form();
        }

        // Check for form delete.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['form_id'] ) ) {
            $this->delete_form();
        }

        // Check for form duplicate.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['form_id'] ) ) {
            $this->duplicate_form();
        }

        // Check for settings save.
        if ( isset( $_POST['headless_forms_save_settings'] ) ) {
            $this->save_settings();
        }

        // Handle export.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' && isset( $_GET['form_id'] ) ) {
            $this->export_submissions();
        }
    }

    /**
     * Render dashboard page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_dashboard() {
        $plugin = Plugin::get_instance();
        $analytics = $plugin->get_analytics();
        $stats = $analytics->get_dashboard_stats();
        $chart_data = $analytics->get_submissions_chart( 30 );
        $recent = $analytics->get_recent_submissions( 5 );

        include HEADLESS_FORMS_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render forms list page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_forms() {
        $forms_table = new Forms_Table();
        $forms_table->prepare_items();

        include HEADLESS_FORMS_PATH . 'admin/views/forms-list.php';
    }

    /**
     * Render form edit page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_form_edit() {
        $form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
        $form = null;

        if ( $form_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'headless_forms';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $form_id ) );
        }

        include HEADLESS_FORMS_PATH . 'admin/views/form-edit.php';
    }

    /**
     * Render submissions page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_submissions() {
        // Check if viewing single submission.
        if ( isset( $_GET['submission_id'] ) ) {
            $this->render_submission_detail();
            return;
        }

        $submissions_table = new Submissions_Table();
        $submissions_table->prepare_items();

        include HEADLESS_FORMS_PATH . 'admin/views/submissions-list.php';
    }

    /**
     * Render submission detail page.
     *
     * @since 1.0.0
     * @return void
     */
    private function render_submission_detail() {
        global $wpdb;

        $submission_id = (int) $_GET['submission_id'];
        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $forms_table = $wpdb->prefix . 'headless_forms';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, f.form_name 
                 FROM {$submissions_table} s
                 LEFT JOIN {$forms_table} f ON s.form_id = f.id
                 WHERE s.id = %d",
                $submission_id
            )
        );

        if ( $submission ) {
            $submission->submission_data = json_decode( $submission->submission_data, true );
            $submission->meta_data = json_decode( $submission->meta_data, true );

            // Mark as read.
            if ( $submission->status === 'new' ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $submissions_table,
                    array( 'status' => 'read', 'read_at' => current_time( 'mysql' ) ),
                    array( 'id' => $submission_id )
                );
            }
        }

        include HEADLESS_FORMS_PATH . 'admin/views/submission-detail.php';
    }

    /**
     * Render email logs page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_email_logs() {
        $logs_table = new Email_Logs_Table();
        $logs_table->prepare_items();

        include HEADLESS_FORMS_PATH . 'admin/views/email-logs.php';
    }

    /**
     * Render how to use page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_how_to() {
        include HEADLESS_FORMS_PATH . 'admin/views/how-to-use.php';
    }

    /**
     * Render settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings() {
        $plugin = Plugin::get_instance();
        $email_factory = $plugin->get_email_factory();
        $providers = $email_factory->get_all_providers();
        $current_provider = get_option( 'headless_forms_email_provider', 'wp_mail' );
        $api_key = get_option( 'headless_forms_api_key', '' );

        include HEADLESS_FORMS_PATH . 'admin/views/settings.php';
    }

    /**
     * Save form.
     *
     * @since 1.0.0
     * @return void
     */
    private function save_form() {
        check_admin_referer( 'headless_forms_save_form', 'headless_forms_nonce' );

        global $wpdb;
        $table = $wpdb->prefix . 'headless_forms';

        $form_id = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;

        $data = array(
            'form_name'             => sanitize_text_field( $_POST['form_name'] ),
            'form_slug'             => sanitize_title( $_POST['form_slug'] ?: $_POST['form_name'] ),
            'form_description'      => sanitize_textarea_field( $_POST['form_description'] ?? '' ),
            'notification_enabled'  => isset( $_POST['notification_enabled'] ) ? 1 : 0,
            'auto_responder_enabled' => isset( $_POST['auto_responder_enabled'] ) ? 1 : 0,
            'success_message'       => sanitize_textarea_field( $_POST['success_message'] ?? '' ),
            'redirect_url'          => esc_url_raw( $_POST['redirect_url'] ?? '' ),
            'status'                => sanitize_text_field( $_POST['status'] ?? 'active' ),
            'updated_at'            => current_time( 'mysql' ),
        );

        // Email settings.
        $email_settings = array(
            'recipients' => sanitize_textarea_field( $_POST['email_recipients'] ?? '' ),
            'subject'    => sanitize_text_field( $_POST['email_subject'] ?? '' ),
        );
        $data['email_settings'] = wp_json_encode( $email_settings );

        // Auto-responder settings.
        $auto_settings = array(
            'subject' => sanitize_text_field( $_POST['auto_responder_subject'] ?? '' ),
            'message' => wp_kses_post( $_POST['auto_responder_message'] ?? '' ),
        );
        $data['auto_responder_settings'] = wp_json_encode( $auto_settings );

        if ( $form_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table, $data, array( 'id' => $form_id ) );
            $redirect_id = $form_id;
        } else {
            $data['created_at'] = current_time( 'mysql' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert( $table, $data );
            $redirect_id = $wpdb->insert_id;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=headless-forms&view=edit-form&form_id=' . $redirect_id . '&saved=1' ) );
        exit;
    }

    /**
     * Delete form.
     *
     * @since 1.0.0
     * @return void
     */
    private function delete_form() {
        check_admin_referer( 'delete_form_' . $_GET['form_id'] );

        global $wpdb;
        $form_id = (int) $_GET['form_id'];

        // Delete form.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'headless_forms', array( 'id' => $form_id ) );

        // Delete related submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'headless_submissions', array( 'form_id' => $form_id ) );

        // Delete webhooks.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->prefix . 'headless_webhooks', array( 'form_id' => $form_id ) );

        wp_safe_redirect( admin_url( 'admin.php?page=headless-forms&view=forms&deleted=1' ) );
        exit;
    }

    /**
     * Duplicate form.
     *
     * @since 1.0.0
     * @return void
     */
    private function duplicate_form() {
        check_admin_referer( 'duplicate_form_' . $_GET['form_id'] );

        global $wpdb;
        $form_id = (int) $_GET['form_id'];
        $table = $wpdb->prefix . 'headless_forms';

        // Get original form.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $form_id ), ARRAY_A );

        if ( $form ) {
            unset( $form['id'] );
            $form['form_name'] = $form['form_name'] . ' (Copy)';
            $form['form_slug'] = $form['form_slug'] . '-copy-' . time();
            $form['created_at'] = current_time( 'mysql' );
            $form['updated_at'] = current_time( 'mysql' );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert( $table, $form );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=headless-forms&view=forms&duplicated=1' ) );
        exit;
    }

    /**
     * Save settings.
     *
     * @since 1.0.0
     * @return void
     */
    private function save_settings() {
        check_admin_referer( 'headless_forms_save_settings', 'headless_forms_nonce' );

        $security = new Security();

        // Email provider.
        update_option( 'headless_forms_email_provider', sanitize_text_field( $_POST['email_provider'] ) );

        // Provider settings.
        $provider_settings = get_option( 'headless_forms_provider_settings', array() );
        $provider = sanitize_text_field( $_POST['email_provider'] );

        if ( isset( $_POST['provider_settings'] ) && is_array( $_POST['provider_settings'] ) ) {
            $settings = array();
            foreach ( $_POST['provider_settings'] as $key => $value ) {
                // Encrypt password/key fields.
                if ( strpos( $key, 'password' ) !== false || strpos( $key, 'secret' ) !== false || strpos( $key, 'api_key' ) !== false || strpos( $key, 'token' ) !== false ) {
                    $settings[ sanitize_key( $key ) ] = $security->encrypt( sanitize_text_field( $value ) );
                } else {
                    $settings[ sanitize_key( $key ) ] = sanitize_text_field( $value );
                }
            }
            $provider_settings[ $provider ] = $settings;
            update_option( 'headless_forms_provider_settings', $provider_settings );
        }

        // Security settings.
        update_option( 'headless_forms_rate_limit', (int) $_POST['rate_limit'] );
        update_option( 'headless_forms_rate_limit_window', (int) $_POST['rate_limit_window'] );
        update_option( 'headless_forms_honeypot_field', sanitize_text_field( $_POST['honeypot_field'] ) );
        update_option( 'headless_forms_cors_origins', sanitize_textarea_field( $_POST['cors_origins'] ?? '' ) );

        // Data settings.
        update_option( 'headless_forms_keep_data_on_delete', isset( $_POST['keep_data_on_delete'] ) );
        update_option( 'headless_forms_data_retention_days', (int) $_POST['data_retention_days'] );

        wp_safe_redirect( admin_url( 'admin.php?page=headless-forms&view=settings&saved=1' ) );
        exit;
    }

    /**
     * Export submissions to CSV.
     *
     * @since 1.0.0
     * @return void
     */
    private function export_submissions() {
        check_admin_referer( 'export_submissions_' . $_GET['form_id'] );

        global $wpdb;
        $form_id = (int) $_GET['form_id'];
        $submissions_table = $wpdb->prefix . 'headless_submissions';
        $forms_table = $wpdb->prefix . 'headless_forms';

        // Get form name.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $form = $wpdb->get_row( $wpdb->prepare( "SELECT form_name FROM {$forms_table} WHERE id = %d", $form_id ) );

        // Get submissions.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$submissions_table} WHERE form_id = %d ORDER BY created_at DESC",
                $form_id
            )
        );

        // Generate CSV.
        $filename = sanitize_file_name( $form->form_name ) . '-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Get all unique fields from submissions.
        $all_fields = array();
        foreach ( $submissions as $submission ) {
            $data = json_decode( $submission->submission_data, true );
            if ( is_array( $data ) ) {
                $all_fields = array_merge( $all_fields, array_keys( $data ) );
            }
        }
        $all_fields = array_unique( $all_fields );

        // Header row.
        $headers = array_merge( array( 'ID', 'Status', 'Submitted At' ), $all_fields, array( 'IP Address', 'User Agent' ) );
        fputcsv( $output, $headers );

        // Data rows.
        foreach ( $submissions as $submission ) {
            $data = json_decode( $submission->submission_data, true ) ?: array();
            $row = array(
                $submission->id,
                $submission->status,
                $submission->created_at,
            );

            foreach ( $all_fields as $field ) {
                $value = isset( $data[ $field ] ) ? $data[ $field ] : '';
                $row[] = is_array( $value ) ? implode( ', ', $value ) : $value;
            }

            $row[] = $submission->ip_address;
            $row[] = $submission->user_agent;

            fputcsv( $output, $row );
        }

        fclose( $output );
        exit;
    }

    /**
     * AJAX: Test email.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_email() {
        check_ajax_referer( 'headless_forms_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'headless-forms' ) ) );
        }

        $to = sanitize_email( $_POST['email'] );
        $provider = sanitize_text_field( $_POST['provider'] ?? '' );

        if ( ! is_email( $to ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'headless-forms' ) ) );
        }

        $plugin = Plugin::get_instance();
        $email_factory = $plugin->get_email_factory();
        $result = $email_factory->send_test( $to, $provider ?: null );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Regenerate API key.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_regenerate_key() {
        check_ajax_referer( 'headless_forms_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'headless-forms' ) ) );
        }

        $security = new Security();
        $new_key = $security->generate_api_key();
        update_option( 'headless_forms_api_key', $new_key );

        wp_send_json_success( array( 'api_key' => $new_key ) );
    }

    /**
     * AJAX: Save webhook.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_save_webhook() {
        check_ajax_referer( 'headless_forms_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'headless-forms' ) ) );
        }

        $plugin = Plugin::get_instance();
        $handler = $plugin->get_webhook_handler();

        $data = array(
            'form_id'          => (int) $_POST['form_id'],
            'webhook_name'     => sanitize_text_field( $_POST['webhook_name'] ),
            'webhook_url'      => esc_url_raw( $_POST['webhook_url'] ),
            'trigger_event'    => sanitize_text_field( $_POST['trigger_event'] ),
            'payload_template' => isset( $_POST['payload_template'] ) ? wp_kses_post( $_POST['payload_template'] ) : '',
            'is_active'        => 1,
        );
        
        // Handle presets
        if ( isset( $_POST['preset'] ) && $_POST['preset'] === 'slack' ) {
            // Logic to format payload template if needed, or JS handles it.
        }

        $id = $handler->create( $data ); // create() handles insertion.

        if ( $id ) {
            wp_send_json_success( array( 'id' => $id, 'message' => 'Integration saved' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to save integration' ) );
        }
    }

    /**
     * AJAX: Delete webhook.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_delete_webhook() {
        check_ajax_referer( 'headless_forms_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'headless-forms' ) ) );
        }

        $plugin = Plugin::get_instance();
        $handler = $plugin->get_webhook_handler();
        
        $id = (int) $_POST['webhook_id'];
        $success = $handler->delete( $id );

        if ( $success ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( array( 'message' => 'Failed to delete' ) );
        }
    }
}
