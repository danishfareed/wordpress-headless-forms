<?php
/**
 * WordPress Mail Provider.
 *
 * Uses the native WordPress wp_mail() function.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP Mail Provider class.
 *
 * Default email provider using WordPress wp_mail function.
 *
 * @since 1.0.0
 */
class WP_Mail_Provider implements Email_Provider_Interface {

    /**
     * Send an email.
     *
     * @since 1.0.0
     * @since 1.1.0 Added $attachments parameter.
     * @param string       $to          Recipient email address.
     * @param string       $subject     Email subject.
     * @param string       $message     Email body.
     * @param array|string $headers     Optional headers.
     * @param array        $attachments Optional file attachments.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $last_error = '';
        $capture_error = function( $error ) use ( &$last_error ) {
            if ( is_wp_error( $error ) ) {
                $last_error = $error->get_error_message();
            }
        };
        
        // Capture wp_mail errors.
        add_action( 'wp_mail_failed', $capture_error );

        // Prepare attachments for wp_mail (expects array of file paths).
        $wp_attachments = array();
        if ( ! empty( $attachments ) ) {
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $wp_attachments[] = $attachment['path'];
                }
            }
        }

        $sent = wp_mail( $to, $subject, $message, $headers, $wp_attachments );

        remove_action( 'wp_mail_failed', $capture_error );

        return array(
            'success'    => $sent,
            'message_id' => $sent ? 'wp_mail_' . time() : '',
            'error'      => $sent ? '' : $this->get_helpful_error( $last_error ),
        );
    }

    /**
     * Provide more context for common WordPress mail errors.
     *
     * @since 1.0.0
     * @param string $error Original error message.
     * @return string
     */
    private function get_helpful_error( $error ) {
        if ( empty( $error ) ) {
            return __( 'WordPress was unable to send the email. This usually happens when the server has the PHP mail() function disabled.', 'headless-forms' );
        }

        if ( stripos( $error, 'Could not instantiate mail function' ) !== false ) {
            return $error . ' ' . __( 'Reason: Your server is not configured to send emails using the default method. Tip: Use an SMTP or API-based provider (like SendGrid or Mailgun) to fix this.', 'headless-forms' );
        }

        return $error;
    }

    /**
     * Get provider name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name() {
        return __( 'WordPress Mail', 'headless-forms' );
    }

    /**
     * Get provider slug.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_slug() {
        return 'wp_mail';
    }

    /**
     * Get settings fields.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'description' => __( 'Optional. Override the default WordPress admin email.', 'headless-forms' ),
            ),
            array(
                'id'          => 'from_name',
                'label'       => __( 'From Name', 'headless-forms' ),
                'type'        => 'text',
                'description' => __( 'Optional. Override the default WordPress site name.', 'headless-forms' ),
            ),
        );
    }

    /**
     * Validate credentials.
     *
     * @since 1.0.0
     * @return bool
     */
    public function validate_credentials() {
        // WP Mail is always available.
        return true;
    }

    /**
     * Get help URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_help_url() {
        return 'https://developer.wordpress.org/reference/functions/wp_mail/';
    }

    /**
     * Send test email.
     *
     * @since 1.0.0
     * @param string $to Recipient.
     * @return array
     */
    public function send_test( $to ) {
        $subject = __( 'Headless Forms - Test Email', 'headless-forms' );
        $message = sprintf(
            '<p>%s</p><p>%s</p>',
            __( 'This is a test email from Headless Forms.', 'headless-forms' ),
            __( 'If you received this, your email configuration is working correctly!', 'headless-forms' )
        );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $result  = $this->send( $to, $subject, $message, $headers );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'Test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
