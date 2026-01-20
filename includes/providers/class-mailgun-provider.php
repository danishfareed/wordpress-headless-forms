<?php
/**
 * Mailgun Provider.
 *
 * Sends email via Mailgun API.
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
 * Mailgun Provider class.
 *
 * @since 1.0.0
 */
class Mailgun_Provider implements Email_Provider_Interface {

    /**
     * Security instance.
     *
     * @var Security
     */
    private $security;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->security = new Security();
    }

    /**
     * Send an email.
     *
     * @since 1.0.0
     * @since 1.1.0 Added $attachments parameter.
     * @param string       $to          Recipient.
     * @param string       $subject     Subject.
     * @param string       $message     Message.
     * @param array|string $headers     Headers.
     * @param array        $attachments Optional file attachments.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $settings = $this->get_saved_settings();

        if ( empty( $settings['api_key'] ) || empty( $settings['domain'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Key or Domain not configured.', 'headless-forms' ),
            );
        }

        $api_key    = $this->security->decrypt( $settings['api_key'] );
        $domain     = $settings['domain'];
        $region     = ! empty( $settings['region'] ) ? $settings['region'] : 'us';
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );

        $base_url = $region === 'eu' 
            ? 'https://api.eu.mailgun.net/v3/' 
            : 'https://api.mailgun.net/v3/';

        $endpoint = $base_url . $domain . '/messages';

        $body = array(
            'from'    => "{$from_name} <{$from_email}>",
            'to'      => $to,
            'subject' => $subject,
            'html'    => $message,
        );

        // Add reply-to.
        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( stripos( $header, 'Reply-To:' ) === 0 ) {
                    $body['h:Reply-To'] = trim( str_ireplace( 'Reply-To:', '', $header ) );
                }
            }
        }

        // Add attachments if provided.
        if ( ! empty( $attachments ) ) {
            $i = 1;
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                    $body[ 'attachment[' . $i . ']' ] = curl_file_create( $attachment['path'], $attachment['mime_type'], $attachment['name'] );
                    $i++;
                }
            }
        }

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            ),
            'body'    => $body,
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
            $error = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code );
        }

        return array(
            'success'    => $success,
            'message_id' => $success ? ( json_decode( $resp_body, true )['id'] ?? 'mg_' . time() ) : '',
            'error'      => $error,
        );
    }

    /**
     * Get saved settings.
     *
     * @return array
     */
    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['mailgun'] ) ? $settings['mailgun'] : array();
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function get_name() {
        return 'Mailgun';
    }

    /**
     * Get provider slug.
     *
     * @return string
     */
    public function get_slug() {
        return 'mailgun';
    }

    /**
     * Get settings fields.
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'       => 'api_key',
                'label'    => __( 'API Key', 'headless-forms' ),
                'type'     => 'password',
                'required' => true,
            ),
            array(
                'id'          => 'domain',
                'label'       => __( 'Domain', 'headless-forms' ),
                'type'        => 'text',
                'required'    => true,
                'placeholder' => 'mg.example.com',
            ),
            array(
                'id'      => 'region',
                'label'   => __( 'Region', 'headless-forms' ),
                'type'    => 'select',
                'options' => array(
                    'us' => 'US',
                    'eu' => 'EU',
                ),
            ),
            array(
                'id'       => 'from_email',
                'label'    => __( 'From Email', 'headless-forms' ),
                'type'     => 'email',
                'required' => true,
            ),
            array(
                'id'    => 'from_name',
                'label' => __( 'From Name', 'headless-forms' ),
                'type'  => 'text',
            ),
        );
    }

    /**
     * Validate credentials.
     *
     * @return bool
     */
    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] ) && ! empty( $settings['domain'] );
    }

    /**
     * Get help URL.
     *
     * @return string
     */
    public function get_help_url() {
        return 'https://documentation.mailgun.com/en/latest/api-sending-messages.html';
    }

    /**
     * Send test email.
     *
     * @param string $to Recipient.
     * @return array
     */
    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array(
                'success' => false,
                'message' => __( 'Please configure Mailgun settings first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - Mailgun Test', 'headless-forms' );
        $message = '<p>' . __( 'This is a test email from Headless Forms via Mailgun.', 'headless-forms' ) . '</p>';

        $result = $this->send( $to, $subject, $message );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'Mailgun test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
