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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();

        if ( empty( $settings['server_token'] ) ) {
            return false;
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

        $code = wp_remote_retrieve_response_code( $response );
        return ! is_wp_error( $response ) && $code === 200;
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
        $message = '<p>' . __( 'Test email via Postmark.', 'headless-forms' ) . '</p>';
        $sent = $this->send( $to, $subject, $message );

        return array(
            'success' => $sent,
            'message' => $sent
                ? __( 'Postmark test email sent!', 'headless-forms' )
                : __( 'Failed to send via Postmark.', 'headless-forms' ),
        );
    }
}
