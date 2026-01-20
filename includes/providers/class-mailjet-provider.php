<?php
/**
 * Mailjet Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Mailjet_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.mailjet.com/v3.1/send';

    private $security;

    public function __construct() {
        $this->security = new Security();
    }

    /**
     * Send an email.
     *
     * @param string       $to      Recipient.
     * @param string       $subject Subject.
     * @param string       $message Message.
     * @param array|string $headers Headers.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Key or Secret Token not configured.', 'headless-forms' ),
            );
        }

        $api_key    = $settings['api_key'];
        $secret_key = $this->security->decrypt( $settings['secret_key'] );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $body = array(
            'Messages' => array(
                array(
                    'From'     => array( 'Email' => $from_email, 'Name' => $from_name ),
                    'To'       => array( array( 'Email' => $to ) ),
                    'Subject'  => $subject,
                    'HTMLPart' => $message,
                ),
            ),
        );

        if ( ! empty( $attachments ) ) {
            $body['Messages'][0]['Attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['Messages'][0]['Attachments'][] = array(
                        'ContentType'   => $attachment['mime_type'] ?? 'application/octet-stream',
                        'Filename'      => $attachment['name'] ?? basename( $attachment['path'] ),
                        'Base64Content' => base64_encode( file_get_contents( $attachment['path'] ) ),
                    );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $secret_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success   = ! is_wp_error( $response ) && $code === 200;
        $error     = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            $error = isset( $data['Messages'][0]['Errors'][0]['ErrorMessage'] ) 
                ? $data['Messages'][0]['Errors'][0]['ErrorMessage'] 
                : ( isset( $data['ErrorMessage'] ) ? $data['ErrorMessage'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code ) );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['Messages'][0]['To'][0]['MessageID'] ?? 'mj_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['mailjet'] ) ? $settings['mailjet'] : array();
    }

    public function get_name() { return 'Mailjet'; }
    public function get_slug() { return 'mailjet'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_key', 'label' => __( 'API Key', 'headless-forms' ), 'type' => 'text', 'required' => true ),
            array( 'id' => 'secret_key', 'label' => __( 'Secret Key', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'from_email', 'label' => __( 'From Email', 'headless-forms' ), 'type' => 'email', 'required' => true ),
            array( 'id' => 'from_name', 'label' => __( 'From Name', 'headless-forms' ), 'type' => 'text' ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] ) && ! empty( $settings['secret_key'] );
    }

    public function get_help_url() { return 'https://dev.mailjet.com/email/guides/send-api-v31/'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Mailjet first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'Mailjet Test', 'headless-forms' ), '<p>This is a test email via Mailjet from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'Mailjet test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
