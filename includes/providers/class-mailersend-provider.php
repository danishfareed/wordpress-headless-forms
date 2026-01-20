<?php
/**
 * MailerSend Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MailerSend_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.mailersend.com/v1/email';

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
        if ( empty( $settings['api_token'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Token not configured.', 'headless-forms' ),
            );
        }

        $api_token  = $this->security->decrypt( $settings['api_token'] );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $body = array(
            'from' => array( 'email' => $from_email, 'name' => $from_name ),
            'to'   => array( array( 'email' => $to ) ),
            'subject' => $subject,
            'html'    => $message,
        );

        if ( ! empty( $attachments ) ) {
            $body['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['attachments'][] = array(
                        'content'     => base64_encode( file_get_contents( $attachment['path'] ) ),
                        'filename'    => $attachment['name'] ?? basename( $attachment['path'] ),
                        'disposition' => 'attachment',
                    );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success   = ! is_wp_error( $response ) && ( $code === 200 || $code === 202 );
        $error     = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            $error = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( wp_remote_retrieve_header( $response, 'x-message-id' ) ?: 'ms_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['mailersend'] ) ? $settings['mailersend'] : array();
    }

    public function get_name() { return 'MailerSend'; }
    public function get_slug() { return 'mailersend'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_token', 'label' => __( 'API Token', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'from_email', 'label' => __( 'From Email', 'headless-forms' ), 'type' => 'email', 'required' => true ),
            array( 'id' => 'from_name', 'label' => __( 'From Name', 'headless-forms' ), 'type' => 'text' ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_token'] );
    }

    public function get_help_url() { return 'https://developers.mailersend.com/api/v1/email.html'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure MailerSend first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'MailerSend Test', 'headless-forms' ), '<p>This is a test email via MailerSend from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'MailerSend test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
