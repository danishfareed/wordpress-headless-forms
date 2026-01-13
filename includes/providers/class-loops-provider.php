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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) || empty( $settings['transactional_id'] ) ) {
            return false;
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

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
        $sent = $this->send( $to, __( 'Loops Test', 'headless-forms' ), '<p>Test email.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
