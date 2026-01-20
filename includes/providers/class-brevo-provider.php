<?php
/**
 * Brevo (Sendinblue) Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Brevo_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

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
            'sender'      => array( 'email' => $from_email, 'name' => $from_name ),
            'to'          => array( array( 'email' => $to ) ),
            'subject'     => $subject,
            'htmlContent' => $message,
        );

        if ( ! empty( $attachments ) ) {
            $body['attachment'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['attachment'][] = array(
                        'content' => base64_encode( file_get_contents( $attachment['path'] ) ),
                        'name'    => $attachment['name'] ?? basename( $attachment['path'] ),
                    );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success   = ! is_wp_error( $response ) && ( $code >= 200 && $code < 300 );
        $error     = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            $error = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['messageId'] ?? 'br_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['brevo'] ) ? $settings['brevo'] : array();
    }

    public function get_name() { return 'Brevo (Sendinblue)'; }
    public function get_slug() { return 'brevo'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_key', 'label' => __( 'API Key', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'from_email', 'label' => __( 'From Email', 'headless-forms' ), 'type' => 'email', 'required' => true ),
            array( 'id' => 'from_name', 'label' => __( 'From Name', 'headless-forms' ), 'type' => 'text' ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] );
    }

    public function get_help_url() { return 'https://developers.brevo.com/docs/send-a-transactional-email'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Brevo first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'Brevo Test', 'headless-forms' ), '<p>This is a test email via Brevo from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'Brevo test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
