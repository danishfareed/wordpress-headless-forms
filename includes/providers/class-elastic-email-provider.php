<?php
/**
 * Elastic Email Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Elastic_Email_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.elasticemail.com/v2/email/send';

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
            'apikey'    => $api_key,
            'from'      => $from_email,
            'fromName'  => $from_name,
            'to'        => $to,
            'subject'   => $subject,
            'bodyHtml'  => $message,
            'isTransactional' => true,
        );

        $response = wp_remote_post( self::API_ENDPOINT, array(
            'body'    => $body,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $result['success'] ) && $result['success'] === true;
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['elastic_email'] ) ? $settings['elastic_email'] : array();
    }

    public function get_name() { return 'Elastic Email'; }
    public function get_slug() { return 'elastic_email'; }

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

    public function get_help_url() { return 'https://elasticemail.com/developers/api-documentation/rest-api-methods/sending-emails'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Elastic Email first.', 'headless-forms' ) );
        }
        $sent = $this->send( $to, __( 'Elastic Email Test', 'headless-forms' ), '<p>Test email.</p>' );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
