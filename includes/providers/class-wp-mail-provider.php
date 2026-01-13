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
     * @param string       $to      Recipient email address.
     * @param string       $subject Email subject.
     * @param string       $message Email body.
     * @param array|string $headers Optional headers.
     * @return bool True if sent.
     */
    public function send( $to, $subject, $message, $headers = array() ) {
        return wp_mail( $to, $subject, $message, $headers );
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
        $sent = $this->send( $to, $subject, $message, $headers );

        return array(
            'success' => $sent,
            'message' => $sent
                ? __( 'Test email sent successfully!', 'headless-forms' )
                : __( 'Failed to send test email. Check your WordPress mail configuration.', 'headless-forms' ),
        );
    }
}
