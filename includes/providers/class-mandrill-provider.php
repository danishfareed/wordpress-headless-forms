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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) ) {
            return false;
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

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $result[0]['status'] ) && in_array( $result[0]['status'], array( 'sent', 'queued' ), true );
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
        $sent = $this->send( $to, __( 'Mandrill Test', 'headless-forms' ), '<p>Test email via Mandrill.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
