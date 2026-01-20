<?php
/**
 * SparkPost Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SparkPost_Provider implements Email_Provider_Interface {

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
        $region     = ! empty( $settings['region'] ) ? $settings['region'] : 'us';
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $endpoint = $region === 'eu' 
            ? 'https://api.eu.sparkpost.com/api/v1/transmissions' 
            : 'https://api.sparkpost.com/api/v1/transmissions';

        $body = array(
            'recipients' => array( array( 'address' => array( 'email' => $to ) ) ),
            'content'    => array(
                'from'    => array( 'email' => $from_email, 'name' => $from_name ),
                'subject' => $subject,
                'html'    => $message,
            ),
        );

        if ( ! empty( $attachments ) ) {
            $body['content']['attachments'] = array();
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $body['content']['attachments'][] = array(
                        'type' => $attachment['mime_type'] ?? 'application/octet-stream',
                        'name' => $attachment['name'] ?? basename( $attachment['path'] ),
                        'data' => base64_encode( file_get_contents( $attachment['path'] ) ),
                    );
                }
            }
        }

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Authorization' => $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code      = wp_remote_retrieve_response_code( $response );
        $resp_body = wp_remote_retrieve_body( $response );
        $success   = ! is_wp_error( $response ) && ( $code === 200 || $code === 201 );
        $error     = '';

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
        } elseif ( ! $success ) {
            $data  = json_decode( $resp_body, true );
            if ( isset( $data['errors'][0]['message'] ) ) {
                $error = $data['errors'][0]['message'];
            } else {
                $error = sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
            }
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['results']['id'] ?? 'sp_' . time() ) : '',
            'error'      => $error,
        );
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['sparkpost'] ) ? $settings['sparkpost'] : array();
    }

    public function get_name() { return 'SparkPost'; }
    public function get_slug() { return 'sparkpost'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_key', 'label' => __( 'API Key', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'region', 'label' => __( 'Region', 'headless-forms' ), 'type' => 'select', 'options' => array( 'us' => 'US', 'eu' => 'EU' ) ),
            array( 'id' => 'from_email', 'label' => __( 'From Email', 'headless-forms' ), 'type' => 'email', 'required' => true ),
            array( 'id' => 'from_name', 'label' => __( 'From Name', 'headless-forms' ), 'type' => 'text' ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] );
    }

    public function get_help_url() { return 'https://developers.sparkpost.com/api/transmissions/'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure SparkPost first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'SparkPost Test', 'headless-forms' ), '<p>This is a test email via SparkPost from Headless Forms.</p>' );
        
        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'SparkPost test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
