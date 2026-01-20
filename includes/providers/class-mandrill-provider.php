<?php
/**
 * Mandrill Provider (Mailchimp Transactional).
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Mandrill_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://mandrillapp.com/api/1.0/messages/send.json';

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
            'key'     => $api_key,
            'message' => array(
                'html'       => $message,
                'subject'    => $subject,
                'from_email' => $from_email,
                'from_name'  => $from_name,
                'to'         => array( array( 'email' => $to, 'type' => 'to' ) ),
            ),
        );

        if ( ! empty( $attachments ) ) {
            $body['message']['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['message']['attachments'][] = array(
                        'type'    => $attachment['mime_type'] ?? 'application/octet-stream',
                        'name'    => $attachment['name'] ?? basename( $attachment['path'] ),
                        'content' => base64_encode( file_get_contents( $attachment['path'] ) ),
                    );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'    => false,
                'message_id' => '',
                'error'      => $response->get_error_message(),
            );
        }

        $resp_body = wp_remote_retrieve_body( $response );
        $result    = json_decode( $resp_body, true );
        $code      = wp_remote_retrieve_response_code( $response );
        $success   = isset( $result[0]['status'] ) && in_array( $result[0]['status'], array( 'sent', 'queued' ), true );
        $error     = '';

        if ( ! $success ) {
            if ( isset( $result['message'] ) ) {
                $error = $result['message'];
            } elseif ( isset( $result[0]['reject_reason'] ) ) {
                $error = sprintf( __( 'Rejected: %s', 'headless-forms' ), $result[0]['reject_reason'] );
            } else {
                $error = sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
            }
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( $result[0]['_id'] ?? 'mn_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['mandrill'] ) ? $settings['mandrill'] : array();
    }

    public function get_name() { return 'Mandrill'; }
    public function get_slug() { return 'mandrill'; }

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

    public function get_help_url() { return 'https://mailchimp.com/developer/transactional/api/messages/send-new-message/'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Mandrill first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'Mandrill Test', 'headless-forms' ), '<p>This is a test email via Mandrill from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'Mandrill test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
