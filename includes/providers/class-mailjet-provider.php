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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
            return false;
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

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $secret_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code = wp_remote_retrieve_response_code( $response );
        return ! is_wp_error( $response ) && $code === 200;
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
        $sent = $this->send( $to, __( 'Mailjet Test', 'headless-forms' ), '<p>Test email.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
