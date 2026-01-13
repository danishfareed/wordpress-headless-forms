<?php
/**
 * SendGrid Provider.
 *
 * Sends email via SendGrid API.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SendGrid Provider class.
 *
 * @since 1.0.0
 */
class SendGrid_Provider implements Email_Provider_Interface {

    /**
     * API endpoint.
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /**
     * Security instance.
     *
     * @var Security
     */
    private $security;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->security = new Security();
    }

    /**
     * Send an email.
     *
     * @since 1.0.0
     * @param string       $to      Recipient.
     * @param string       $subject Subject.
     * @param string       $message Message.
     * @param array|string $headers Headers.
     * @return bool
     */
    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();

        if ( empty( $settings['api_key'] ) ) {
            return false;
        }

        $api_key    = $this->security->decrypt( $settings['api_key'] );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $body = array(
            'personalizations' => array(
                array(
                    'to' => array(
                        array( 'email' => $to ),
                    ),
                ),
            ),
            'from'    => array(
                'email' => $from_email,
                'name'  => $from_name,
            ),
            'subject' => $subject,
            'content' => array(
                array(
                    'type'  => 'text/html',
                    'value' => $message,
                ),
            ),
        );

        // Add reply-to from headers.
        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( stripos( $header, 'Reply-To:' ) === 0 ) {
                    $reply_to = trim( str_ireplace( 'Reply-To:', '', $header ) );
                    $body['reply_to'] = array( 'email' => $reply_to );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code = wp_remote_retrieve_response_code( $response );
        return ! is_wp_error( $response ) && ( $code >= 200 && $code < 300 );
    }

    /**
     * Get saved settings.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['sendgrid'] ) ? $settings['sendgrid'] : array();
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name() {
        return 'SendGrid';
    }

    /**
     * Get provider slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'sendgrid';
    }

    /**
     * Get settings fields.
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'       => 'api_key',
                'label'    => __( 'API Key', 'headless-forms' ),
                'type'     => 'password',
                'required' => true,
            ),
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'required'    => true,
                'description' => __( 'Must be verified in SendGrid.', 'headless-forms' ),
            ),
            array(
                'id'    => 'from_name',
                'label' => __( 'From Name', 'headless-forms' ),
                'type'  => 'text',
            ),
        );
    }

    /**
     * Validate credentials.
     *
     * @return bool
     */
    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] );
    }

    /**
     * Get help URL.
     *
     * @return string
     */
    public function get_help_url() {
        return 'https://docs.sendgrid.com/for-developers/sending-email/api-getting-started';
    }

    /**
     * Send test email.
     *
     * @param string $to Recipient.
     * @return array
     */
    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array(
                'success' => false,
                'message' => __( 'Please configure SendGrid API key first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - SendGrid Test', 'headless-forms' );
        $message = '<p>' . __( 'This is a test email from Headless Forms via SendGrid.', 'headless-forms' ) . '</p>';

        $sent = $this->send( $to, $subject, $message );

        return array(
            'success' => $sent,
            'message' => $sent
                ? __( 'SendGrid test email sent successfully!', 'headless-forms' )
                : __( 'Failed to send via SendGrid. Check your API key.', 'headless-forms' ),
        );
    }
}
