<?php
/**
 * Resend Provider.
 *
 * Sends email via Resend API.
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
 * Resend Provider class.
 *
 * Modern email API for developers.
 *
 * @since 1.0.0
 */
class Resend_Provider implements Email_Provider_Interface {

    /**
     * API endpoint.
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.resend.com/emails';

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
     * @since 1.1.0 Added $attachments parameter.
     * @param string       $to          Recipient.
     * @param string       $subject     Subject.
     * @param string       $message     Message.
     * @param array|string $headers     Headers.
     * @param array        $attachments Optional file attachments.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $settings = $this->get_saved_settings();

        if ( empty( $settings['api_key'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Key not configured.', 'headless-forms' ),
            );
        }

        $api_key    = $this->security->decrypt( $settings['api_key'] );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $body = array(
            'from'    => "{$from_name} <{$from_email}>",
            'to'      => array( $to ),
            'subject' => $subject,
            'html'    => $message,
        );

        // Add reply-to from headers.
        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( stripos( $header, 'Reply-To:' ) === 0 ) {
                    $reply_to = trim( str_ireplace( 'Reply-To:', '', $header ) );
                    $body['reply_to'] = $reply_to;
                }
            }
        }

        // Add attachments if provided.
        if ( ! empty( $attachments ) ) {
            $body['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                    $content = base64_encode( file_get_contents( $attachment['path'] ) );
                    $body['attachments'][] = array(
                        'content'  => $content,
                        'filename' => isset( $attachment['name'] ) ? $attachment['name'] : basename( $attachment['path'] ),
                    );
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

        $code    = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success = ! is_wp_error( $response ) && ( $code >= 200 && $code < 300 );
        $error   = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            $error = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['id'] ?? 're_' . time() ) : '',
            'error'      => $error,
        );
    }

    /**
     * Get saved settings.
     *
     * @return array
     */
    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['resend'] ) ? $settings['resend'] : array();
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name() {
        return 'Resend';
    }

    /**
     * Get provider slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'resend';
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
                'description' => __( 'Must be from a verified domain in Resend.', 'headless-forms' ),
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
        return 'https://resend.com/docs/api-reference/emails/send-email';
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
                'message' => __( 'Please configure Resend API key first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - Resend Test', 'headless-forms' );
        $message = '<p>' . __( 'This is a test email from Headless Forms via Resend.', 'headless-forms' ) . '</p>';

        $result = $this->send( $to, $subject, $message );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'Resend test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
