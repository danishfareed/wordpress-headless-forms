<?php
/**
 * SMTP2GO Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMTP2GO_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.smtp2go.com/v3/email/send';

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
            'api_key' => $api_key,
            'to'      => array( $to ),
            'sender'  => "{$from_name} <{$from_email}>",
            'subject' => $subject,
            'html_body' => $message,
        );

        if ( ! empty( $attachments ) ) {
            $body['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['attachments'][] = array(
                        'filename' => $attachment['name'] ?? basename( $attachment['path'] ),
                        'fileblob' => base64_encode( file_get_contents( $attachment['path'] ) ),
                        'mimetype' => $attachment['mime_type'] ?? 'application/octet-stream',
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
        $success   = isset( $result['data']['succeeded'] ) && $result['data']['succeeded'] > 0;
        $error     = '';

        if ( ! $success ) {
            if ( isset( $result['data']['error'] ) ) {
                $error = $result['data']['error'];
            } elseif ( isset( $result['data']['failures'][0] ) ) {
                $error = $result['data']['failures'][0];
            } else {
                $error = sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
            }
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( $result['data']['email_id'] ?? 's2g_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['smtp2go'] ) ? $settings['smtp2go'] : array();
    }

    public function get_name() { return 'SMTP2GO'; }
    public function get_slug() { return 'smtp2go'; }

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

    public function get_help_url() { return 'https://www.smtp2go.com/docs/'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure SMTP2GO first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'SMTP2GO Test', 'headless-forms' ), '<p>This is a test email via SMTP2GO from Headless Forms.</p>' );

        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'SMTP2GO test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
