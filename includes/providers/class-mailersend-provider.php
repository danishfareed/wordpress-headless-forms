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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_token'] ) ) {
            return false;
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

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code = wp_remote_retrieve_response_code( $response );
        return ! is_wp_error( $response ) && $code === 202;
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
        $sent = $this->send( $to, __( 'MailerSend Test', 'headless-forms' ), '<p>Test email.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
