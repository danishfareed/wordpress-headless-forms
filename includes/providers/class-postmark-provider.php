<?php
/**
 * Postmark Provider.
 *
 * Sends email via Postmark API.
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
 * Postmark Provider class.
 *
 * @since 1.0.0
 */
class Postmark_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.postmarkapp.com/email';

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

        if ( empty( $settings['server_token'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Postmark Server Token not configured.', 'headless-forms' ),
            );
        }

        $token      = $this->security->decrypt( $settings['server_token'] );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $body = array(
            'From'     => "{$from_name} <{$from_email}>",
            'To'       => $to,
            'Subject'  => $subject,
            'HtmlBody' => $message,
        );

        if ( ! empty( $attachments ) ) {
            $body['Attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['Attachments'][] = array(
                        'Name'        => $attachment['name'] ?? basename( $attachment['path'] ),
                        'Content'     => base64_encode( file_get_contents( $attachment['path'] ) ),
                        'ContentType' => $attachment['mime_type'] ?? 'application/octet-stream',
                    );
                }
            }
        }

        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( stripos( $header, 'Reply-To:' ) === 0 ) {
                    $body['ReplyTo'] = trim( str_ireplace( 'Reply-To:', '', $header ) );
                }
            }
        }

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Accept'                  => 'application/json',
                'Content-Type'            => 'application/json',
                'X-Postmark-Server-Token' => $token,
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
            $error = isset( $data['Message'] ) ? $data['Message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['MessageID'] ?? 'pm_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['postmark'] ) ? $settings['postmark'] : array();
    }

    public function get_name() {
        return 'Postmark';
    }

    public function get_slug() {
        return 'postmark';
    }

    public function get_settings_fields() {
        return array(
            array(
                'id'       => 'server_token',
                'label'    => __( 'Server API Token', 'headless-forms' ),
                'type'     => 'password',
                'required' => true,
            ),
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'required'    => true,
                'description' => __( 'Must be a verified sender signature in Postmark.', 'headless-forms' ),
            ),
            array(
                'id'    => 'from_name',
                'label' => __( 'From Name', 'headless-forms' ),
                'type'  => 'text',
            ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['server_token'] );
    }

    public function get_help_url() {
        return 'https://postmarkapp.com/developer/api/email-api';
    }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array(
                'success' => false,
                'message' => __( 'Please configure Postmark settings first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - Postmark Test', 'headless-forms' );
        $message = '<p>' . __( 'Test email via Postmark from Headless Forms.', 'headless-forms' ) . '</p>';
        
        $result = $this->send( $to, $subject, $message );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'Postmark test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
