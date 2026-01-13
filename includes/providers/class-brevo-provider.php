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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) ) {
            return false;
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

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        $code = wp_remote_retrieve_response_code( $response );
        return ! is_wp_error( $response ) && ( $code >= 200 && $code < 300 );
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
        $sent = $this->send( $to, __( 'Brevo Test', 'headless-forms' ), '<p>Test email via Brevo.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
