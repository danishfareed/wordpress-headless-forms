<?php
/**
 * Loops Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loops_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://app.loops.so/api/v1/transactional';

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
        if ( empty( $settings['api_key'] ) || empty( $settings['transactional_id'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Key or Transactional ID not configured.', 'headless-forms' ),
            );
        }

        $api_key = $this->security->decrypt( $settings['api_key'] );

        $body = array(
            'transactionalId' => $settings['transactional_id'],
            'email'           => $to,
            'dataVariables'   => array(
                'subject' => $subject,
                'content' => $message,
            ),
        );

        if ( ! empty( $attachments ) ) {
            $body['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['attachments'][] = array(
                        'filename'    => $attachment['name'] ?? basename( $attachment['path'] ),
                        'contentType' => $attachment['mime_type'] ?? 'application/octet-stream',
                        'data'        => base64_encode( file_get_contents( $attachment['path'] ) ),
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

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success   = ! is_wp_error( $response ) && $code === 200;
        $error     = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            $error = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['id'] ?? 'lp_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['loops'] ) ? $settings['loops'] : array();
    }

    public function get_name() { return 'Loops'; }
    public function get_slug() { return 'loops'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_key', 'label' => __( 'API Key', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'transactional_id', 'label' => __( 'Transactional ID', 'headless-forms' ), 'type' => 'text', 'required' => true, 'description' => __( 'The ID of your transactional email template in Loops.', 'headless-forms' ) ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] ) && ! empty( $settings['transactional_id'] );
    }

    public function get_help_url() { return 'https://loops.so/docs/api-reference/send-transactional-email'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Loops first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'Loops Test', 'headless-forms' ), '<p>This is a test email via Loops from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'Loops test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
