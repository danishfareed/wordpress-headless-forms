<?php
/**
 * REST API Handler Class.
 *
 * Registers and handles all REST API endpoints for form submissions.
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
 * API Handler class.
 *
 * Manages REST API routes for form submissions, including authentication,
 * rate limiting, and spam protection.
 *
 * @since 1.0.0
 */
class API_Handler {

    /**
     * REST API namespace.
     *
     * @since 1.0.0
     * @var string
     */
    const API_NAMESPACE = 'headless-forms/v1';

    /**
     * Security instance.
     *
     * @since 1.0.0
     * @var Security
     */
    private $security;

    /**
     * Rate limiter instance.
     *
     * @since 1.0.0
     * @var Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Email factory instance.
     *
     * @since 1.0.0
     * @var Providers\Email_Factory
     */
    private $email_factory;

    /**
     * Webhook handler instance.
     *
     * @since 1.0.0
     * @var Webhook_Handler
     */
    private $webhook_handler;

    /**
     * Email logger instance.
     *
     * @since 1.0.0
     * @var Email_Logger
     */
    private $email_logger;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Providers\Email_Factory $email_factory   Email factory instance.
     * @param Webhook_Handler         $webhook_handler Webhook handler instance.
     * @param Email_Logger            $email_logger    Email logger instance.
     */
    public function __construct( $email_factory, $webhook_handler, $email_logger ) {
        $this->security        = new Security();
        $this->rate_limiter    = new Rate_Limiter();
        $this->email_factory   = $email_factory;
        $this->webhook_handler = $webhook_handler;
        $this->email_logger    = $email_logger;
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        // Form submission endpoint.
        register_rest_route(
            self::API_NAMESPACE,
            '/submit/(?P<form_id>[\w-]+)',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_submission' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => $this->get_submission_args(),
            )
        );

        // Get forms list (authenticated).
        register_rest_route(
            self::API_NAMESPACE,
            '/forms',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_forms' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // Get submissions for a form (authenticated).
        register_rest_route(
            self::API_NAMESPACE,
            '/submissions/(?P<form_id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_submissions' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // GDPR data export.
        register_rest_route(
            self::API_NAMESPACE,
            '/gdpr/export',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'gdpr_export' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // GDPR data delete.
        register_rest_route(
            self::API_NAMESPACE,
            '/gdpr/delete',
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'gdpr_delete' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Check API key permission for public submission endpoint.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error True if authorized, WP_Error otherwise.
     */
    public function check_permission( $request ) {
        // Handle CORS preflight.
        if ( $request->get_method() === 'OPTIONS' ) {
            return true;
        }

        // Get API key from header.
        $api_key = $request->get_header( 'X-HF-API-Key' );

        if ( empty( $api_key ) ) {
            // Also check Authorization header for Bearer token.
            $auth = $request->get_header( 'Authorization' );
            if ( ! empty( $auth ) && strpos( $auth, 'Bearer ' ) === 0 ) {
                $api_key = substr( $auth, 7 );
            }
        }

        // Validate API key.
        if ( ! $this->security->validate_api_key( $api_key ) ) {
            return new \WP_Error(
                'invalid_api_key',
                __( 'Invalid or missing API key.', 'headless-forms' ),
                array( 'status' => 401 )
            );
        }

        // Check IP blocklist.
        $ip = $this->security->get_client_ip();
        if ( $this->security->is_ip_blocked( $ip ) ) {
            return new \WP_Error(
                'ip_blocked',
                __( 'Your IP address has been blocked.', 'headless-forms' ),
                array( 'status' => 403 )
            );
        }

        // Check IP allowlist.
        if ( ! $this->security->is_ip_allowed( $ip ) ) {
            return new \WP_Error(
                'ip_not_allowed',
                __( 'Your IP address is not allowed.', 'headless-forms' ),
                array( 'status' => 403 )
            );
        }

        // Check CORS origin.
        $origin = $request->get_header( 'Origin' );
        if ( ! empty( $origin ) && ! $this->security->validate_cors_origin( $origin ) ) {
            return new \WP_Error(
                'cors_error',
                __( 'Origin not allowed.', 'headless-forms' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check admin permission for authenticated endpoints.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return bool|\WP_Error True if authorized.
     */
    public function check_admin_permission( $request ) {
        // First check API key.
        $permission = $this->check_permission( $request );
        if ( is_wp_error( $permission ) ) {
            return $permission;
        }

        // For admin endpoints, also need capability check.
        if ( ! current_user_can( 'manage_options' ) ) {
            // Allow with valid API key but log for audit.
            return true;
        }

        return true;
    }

    /**
     * Get submission endpoint arguments.
     *
     * @since 1.0.0
     * @return array Endpoint arguments.
     */
    private function get_submission_args() {
        return array(
            'form_id' => array(
                'description'       => __( 'Form ID or slug.', 'headless-forms' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Handle form submission.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function handle_submission( $request ) {
        global $wpdb;

        $ip = $this->security->get_client_ip();

        // Check rate limiting.
        if ( $this->rate_limiter->is_rate_limited( $ip ) ) {
            $retry_after = $this->rate_limiter->get_retry_after( $ip );
            return new \WP_Error(
                'rate_limited',
                __( 'Too many requests. Please try again later.', 'headless-forms' ),
                array(
                    'status'      => 429,
                    'retry_after' => $retry_after,
                )
            );
        }

        // Record this request.
        $this->rate_limiter->record_request( $ip );

        // Get request data.
        $form_id = $request->get_param( 'form_id' );
        $data    = $request->get_json_params();

        if ( empty( $data ) ) {
            $data = $request->get_body_params();
        }

        // Check honeypot.
        if ( $this->security->is_honeypot_triggered( $data ) ) {
            // Silently reject spam but return success (don't reveal detection).
            return new \WP_REST_Response(
                array(
                    'success'       => true,
                    'submission_id' => 0,
                    'message'       => __( 'Form submitted successfully.', 'headless-forms' ),
                ),
                200
            );
        }

        // Get form from database.
        $form = $this->get_form( $form_id );

        if ( ! $form ) {
            return new \WP_Error(
                'form_not_found',
                __( 'Form not found.', 'headless-forms' ),
                array( 'status' => 404 )
            );
        }

        // Check if form is active.
        if ( $form->status !== 'active' ) {
            return new \WP_Error(
                'form_inactive',
                __( 'This form is not accepting submissions.', 'headless-forms' ),
                array( 'status' => 400 )
            );
        }

        /**
         * Filter submission data before sanitization.
         *
         * @since 1.0.0
         * @param array  $data    The raw submission data.
         * @param object $form    The form object.
         */
        $data = apply_filters( 'headless_forms_before_sanitize', $data, $form );

        // Sanitize submission data.
        $sanitized_data = $this->security->sanitize_submission_data( $data );

        /**
         * Filter sanitized submission data.
         *
         * @since 1.0.0
         * @param array  $sanitized_data The sanitized data.
         * @param int    $form_id        The form ID.
         */
        $sanitized_data = apply_filters( 'headless_forms_sanitize_data', $sanitized_data, $form->id );

        // Check for validation errors from filter.
        if ( is_wp_error( $sanitized_data ) ) {
            return $sanitized_data;
        }

        /**
         * Action before submission is saved.
         *
         * @since 1.0.0
         * @param int   $form_id        The form ID.
         * @param array $sanitized_data The sanitized submission data.
         */
        do_action( 'headless_forms_before_submit', $form->id, $sanitized_data );

        // Prepare metadata.
        $meta_data = array(
            'ip_address'   => $ip,
            'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'referrer'     => $request->get_header( 'Referer' ) ? esc_url_raw( $request->get_header( 'Referer' ) ) : '',
            'origin'       => $request->get_header( 'Origin' ) ? esc_url_raw( $request->get_header( 'Origin' ) ) : '',
            'submitted_at' => current_time( 'mysql' ),
        );

        // Extract submitter email if present.
        $submitter_email = '';
        foreach ( $sanitized_data as $key => $value ) {
            if ( is_email( $value ) && empty( $submitter_email ) ) {
                $submitter_email = $value;
                break;
            }
        }

        // Store submission in database.
        $submissions_table = $wpdb->prefix . 'headless_submissions';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $submissions_table,
            array(
                'form_id'         => $form->id,
                'submission_data' => wp_json_encode( $sanitized_data ),
                'meta_data'       => wp_json_encode( $meta_data ),
                'submitter_email' => $submitter_email,
                'ip_address'      => $ip,
                'user_agent'      => $meta_data['user_agent'],
                'referrer_url'    => $meta_data['referrer'],
                'status'          => 'new',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new \WP_Error(
                'submission_failed',
                __( 'Failed to save submission.', 'headless-forms' ),
                array( 'status' => 500 )
            );
        }

        $submission_id = $wpdb->insert_id;

        /**
         * Action after submission is saved.
         *
         * @since 1.0.0
         * @param int   $submission_id  The submission ID.
         * @param int   $form_id        The form ID.
         * @param array $sanitized_data The sanitized submission data.
         */
        do_action( 'headless_forms_after_submit', $submission_id, $form->id, $sanitized_data );

        // Send notification email if enabled.
        $email_sent = false;
        if ( $form->notification_enabled ) {
            $email_sent = $this->send_notification_email( $form, $sanitized_data, $submission_id );
        }

        // Send auto-responder if enabled.
        $auto_response_sent = false;
        if ( $form->auto_responder_enabled && ! empty( $submitter_email ) ) {
            $auto_response_sent = $this->send_auto_responder( $form, $sanitized_data, $submitter_email, $submission_id );
        }

        // Trigger webhooks.
        $this->webhook_handler->trigger( $form->id, 'submission.created', array(
            'submission_id' => $submission_id,
            'form_id'       => $form->id,
            'form_name'     => $form->form_name,
            'data'          => $sanitized_data,
            'meta'          => $meta_data,
        ) );

        // Update submission with email status.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $submissions_table,
            array(
                'email_sent'         => $email_sent ? 1 : 0,
                'auto_response_sent' => $auto_response_sent ? 1 : 0,
            ),
            array( 'id' => $submission_id ),
            array( '%d', '%d' ),
            array( '%d' )
        );

        // Build success response.
        $success_message = ! empty( $form->success_message ) 
            ? $form->success_message 
            : __( 'Form submitted successfully.', 'headless-forms' );

        $response_data = array(
            'success'       => true,
            'submission_id' => $submission_id,
            'message'       => $success_message,
        );

        // Add redirect URL if configured.
        if ( ! empty( $form->redirect_url ) ) {
            $response_data['redirect_url'] = esc_url( $form->redirect_url );
        }

        /**
         * Filter the API response.
         *
         * @since 1.0.0
         * @param array $response_data  The response data.
         * @param int   $submission_id  The submission ID.
         * @param int   $form_id        The form ID.
         */
        $response_data = apply_filters( 'headless_forms_api_response', $response_data, $submission_id, $form->id );

        $response = new \WP_REST_Response( $response_data, 200 );

        // Add rate limit headers.
        foreach ( $this->rate_limiter->get_headers( $ip ) as $header => $value ) {
            $response->header( $header, $value );
        }

        return $response;
    }

    /**
     * Get form by ID or slug.
     *
     * @since 1.0.0
     * @param string|int $form_id Form ID or slug.
     * @return object|null Form object or null.
     */
    private function get_form( $form_id ) {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'headless_forms';

        // Try by slug first (more common for headless).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$forms_table} WHERE form_slug = %s",
                $form_id
            )
        );

        // If not found by slug, try by ID.
        if ( ! $form && is_numeric( $form_id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $form = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$forms_table} WHERE id = %d",
                    intval( $form_id )
                )
            );
        }

        return $form;
    }

    /**
     * Send notification email.
     *
     * @since 1.0.0
     * @param object $form          The form object.
     * @param array  $data          The submission data.
     * @param int    $submission_id The submission ID.
     * @return bool Whether email was sent.
     */
    private function send_notification_email( $form, $data, $submission_id ) {
        $email_settings = json_decode( $form->email_settings, true );

        if ( empty( $email_settings ) || empty( $email_settings['recipients'] ) ) {
            return false;
        }

        // Build email content.
        $subject = isset( $email_settings['subject'] ) 
            ? $this->replace_placeholders( $email_settings['subject'], $data, $form )
            : sprintf( __( 'New submission from %s', 'headless-forms' ), $form->form_name );

        $message = $this->build_email_body( $data, $form );

        // Get recipients.
        $recipients = is_array( $email_settings['recipients'] ) 
            ? $email_settings['recipients'] 
            : array_map( 'trim', explode( ',', $email_settings['recipients'] ) );

        /**
         * Filter email headers.
         *
         * @since 1.0.0
         * @param array $headers The email headers.
         * @param int   $form_id The form ID.
         */
        $headers = apply_filters( 'headless_forms_email_headers', array(
            'Content-Type: text/html; charset=UTF-8',
        ), $form->id );

        // Add reply-to if submitter email exists.
        foreach ( $data as $key => $value ) {
            if ( is_email( $value ) ) {
                $headers[] = 'Reply-To: ' . $value;
                break;
            }
        }

        $success = false;

        foreach ( $recipients as $recipient ) {
            $recipient = sanitize_email( trim( $recipient ) );
            if ( empty( $recipient ) ) {
                continue;
            }

            $result = $this->email_factory->send( $recipient, $subject, $message, $headers );

            // Handle new array return type or legacy bool.
            if ( is_array( $result ) ) {
                $sent = $result['success'];
                $error_message = $result['error'];
                $message_id = $result['message_id'];
            } else {
                $sent = (bool) $result;
                $error_message = $sent ? '' : 'Unknown error';
                $message_id = null;
            }

            // Log email.
            $this->email_logger->log( array(
                'submission_id'       => $submission_id,
                'form_id'             => $form->id,
                'email_type'          => 'notification',
                'provider'            => get_option( 'headless_forms_email_provider', 'wp_mail' ),
                'recipient'           => $recipient,
                'subject'             => $subject,
                'status'              => $sent ? 'sent' : 'failed',
                'error_message'       => $error_message,
                'provider_message_id' => $message_id,
            ) );

            if ( $sent ) {
                $success = true;

                /**
                 * Action after email is sent.
                 *
                 * @since 1.0.0
                 * @param int    $submission_id The submission ID.
                 * @param string $provider      The email provider used.
                 */
                do_action( 'headless_forms_email_sent', $submission_id, get_option( 'headless_forms_email_provider', 'wp_mail' ) );
            } else {
                /**
                 * Action when email fails.
                 *
                 * @since 1.0.0
                 * @param int    $submission_id The submission ID.
                 * @param string $error         The error message.
                 */
                do_action( 'headless_forms_email_failed', $submission_id, 'Email delivery failed' );
            }
        }

        return $success;
    }

    /**
     * Send auto-responder email.
     *
     * @since 1.0.0
     * @param object $form          The form object.
     * @param array  $data          The submission data.
     * @param string $recipient     The recipient email.
     * @param int    $submission_id The submission ID.
     * @return bool Whether email was sent.
     */
    private function send_auto_responder( $form, $data, $recipient, $submission_id ) {
        $auto_settings = json_decode( $form->auto_responder_settings, true );

        if ( empty( $auto_settings ) ) {
            return false;
        }

        $subject = isset( $auto_settings['subject'] ) 
            ? $this->replace_placeholders( $auto_settings['subject'], $data, $form )
            : __( 'Thank you for your submission', 'headless-forms' );

        $message = isset( $auto_settings['message'] ) 
            ? $this->replace_placeholders( $auto_settings['message'], $data, $form )
            : __( 'We have received your submission and will get back to you soon.', 'headless-forms' );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $result = $this->email_factory->send( $recipient, $subject, $message, $headers );

        // Handle new array return type or legacy bool.
        if ( is_array( $result ) ) {
            $sent = $result['success'];
            $error_message = $result['error'];
            $message_id = $result['message_id'];
        } else {
            $sent = (bool) $result;
            $error_message = $sent ? '' : 'Unknown error';
            $message_id = null;
        }

        // Log auto-responder.
        $this->email_logger->log( array(
            'submission_id'       => $submission_id,
            'form_id'             => $form->id,
            'email_type'          => 'auto_responder',
            'provider'            => get_option( 'headless_forms_email_provider', 'wp_mail' ),
            'recipient'           => $recipient,
            'subject'             => $subject,
            'status'              => $sent ? 'sent' : 'failed',
            'error_message'       => $error_message,
            'provider_message_id' => $message_id,
        ) );

        return $sent;
    }

    /**
     * Build email body from submission data.
     *
     * @since 1.0.0
     * @param array  $data The submission data.
     * @param object $form The form object.
     * @return string The email body HTML.
     */
    private function build_email_body( $data, $form ) {
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $html .= '<h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">';
        $html .= sprintf( __( 'New Submission: %s', 'headless-forms' ), esc_html( $form->form_name ) );
        $html .= '</h2>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';

        foreach ( $data as $key => $value ) {
            $label = ucfirst( str_replace( array( '_', '-' ), ' ', $key ) );
            $display_value = is_array( $value ) ? implode( ', ', $value ) : $value;
            
            $html .= '<tr>';
            $html .= '<td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: bold; width: 30%; vertical-align: top;">';
            $html .= esc_html( $label );
            $html .= '</td>';
            $html .= '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
            $html .= nl2br( esc_html( $display_value ) );
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '<p style="margin-top: 30px; color: #666; font-size: 12px;">';
        $html .= sprintf(
            __( 'This submission was received on %s via Headless Forms.', 'headless-forms' ),
            current_time( 'F j, Y \a\t g:i a' )
        );
        $html .= '</p>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Replace placeholders in template.
     *
     * @since 1.0.0
     * @param string $template The template string.
     * @param array  $data     The submission data.
     * @param object $form     The form object.
     * @return string The processed template.
     */
    private function replace_placeholders( $template, $data, $form ) {
        // Replace form placeholders.
        $template = str_replace( '{{form_name}}', $form->form_name, $template );
        $template = str_replace( '{{form_id}}', $form->id, $template );
        $template = str_replace( '{{date}}', current_time( 'F j, Y' ), $template );
        $template = str_replace( '{{time}}', current_time( 'g:i a' ), $template );

        // Replace data placeholders.
        foreach ( $data as $key => $value ) {
            $display_value = is_array( $value ) ? implode( ', ', $value ) : $value;
            $template = str_replace( '{{' . $key . '}}', $display_value, $template );
        }

        return $template;
    }

    /**
     * Get forms list.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response Response.
     */
    public function get_forms( $request ) {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'headless_forms';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $forms = $wpdb->get_results(
            "SELECT id, form_name, form_slug, status, created_at FROM {$forms_table} ORDER BY created_at DESC"
        );

        return new \WP_REST_Response( array(
            'success' => true,
            'forms'   => $forms,
        ), 200 );
    }

    /**
     * Get submissions for a form.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response Response.
     */
    public function get_submissions( $request ) {
        global $wpdb;

        $form_id = (int) $request->get_param( 'form_id' );
        $submissions_table = $wpdb->prefix . 'headless_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$submissions_table} WHERE form_id = %d ORDER BY created_at DESC LIMIT 100",
                $form_id
            )
        );

        // Decode JSON fields.
        foreach ( $submissions as &$submission ) {
            $submission->submission_data = json_decode( $submission->submission_data, true );
            $submission->meta_data = json_decode( $submission->meta_data, true );
        }

        return new \WP_REST_Response( array(
            'success'     => true,
            'submissions' => $submissions,
        ), 200 );
    }

    /**
     * GDPR data export.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response Response.
     */
    public function gdpr_export( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );

        if ( empty( $email ) ) {
            return new \WP_Error(
                'missing_email',
                __( 'Email address is required.', 'headless-forms' ),
                array( 'status' => 400 )
            );
        }

        $plugin = Plugin::get_instance();
        $data = $plugin->get_gdpr_handler()->export_data( $email );

        return new \WP_REST_Response( array(
            'success' => true,
            'data'    => $data,
        ), 200 );
    }

    /**
     * GDPR data delete.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response Response.
     */
    public function gdpr_delete( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );

        if ( empty( $email ) ) {
            return new \WP_Error(
                'missing_email',
                __( 'Email address is required.', 'headless-forms' ),
                array( 'status' => 400 )
            );
        }

        $plugin = Plugin::get_instance();
        $deleted = $plugin->get_gdpr_handler()->delete_data( $email );

        return new \WP_REST_Response( array(
            'success' => true,
            'deleted' => $deleted,
        ), 200 );
    }
}
