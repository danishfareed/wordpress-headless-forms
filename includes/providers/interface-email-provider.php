<?php
/**
 * Email Provider Interface.
 *
 * Defines the contract for all email provider implementations.
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
 * Email Provider Interface.
 *
 * All email providers must implement this interface to ensure
 * consistent API across different email services.
 *
 * @since 1.0.0
 */
interface Email_Provider_Interface {

    /**
     * Send an email.
     *
     * @since 1.0.0
     * @since 1.1.0 Added $attachments parameter.
     * @param string       $to          Recipient email address.
     * @param string       $subject     Email subject.
     * @param string       $message     Email body (HTML).
     * @param array|string $headers     Optional. Additional headers.
     * @param array        $attachments Optional. File attachments array.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() );

    /**
     * Get the provider display name.
     *
     * @since 1.0.0
     * @return string Provider name.
     */
    public function get_name();

    /**
     * Get the provider slug/identifier.
     *
     * @since 1.0.0
     * @return string Provider slug.
     */
    public function get_slug();

    /**
     * Get settings fields for the provider.
     *
     * @since 1.0.0
     * @return array Array of field definitions.
     */
    public function get_settings_fields();

    /**
     * Validate credentials/configuration.
     *
     * @since 1.0.0
     * @return bool True if valid, false otherwise.
     */
    public function validate_credentials();

    /**
     * Get help/documentation URL.
     *
     * @since 1.0.0
     * @return string Documentation URL.
     */
    public function get_help_url();

    /**
     * Send a test email.
     *
     * @since 1.0.0
     * @param string $to Recipient email address.
     * @return array Result with success status and message.
     */
    public function send_test( $to );
}
