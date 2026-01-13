<?php
/**
 * AWS SES Provider.
 *
 * Sends email via Amazon Simple Email Service API.
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
 * AWS SES Provider class.
 *
 * Implements email sending via Amazon SES REST API.
 *
 * @since 1.0.0
 */
class AWS_SES_Provider implements Email_Provider_Interface {

    /**
     * Security instance.
     *
     * @var Security
     */
    private $security;

    /**
     * AWS SES regions and endpoints.
     *
     * @var array
     */
    private $regions = array(
        'us-east-1'      => 'email.us-east-1.amazonaws.com',
        'us-east-2'      => 'email.us-east-2.amazonaws.com',
        'us-west-1'      => 'email.us-west-1.amazonaws.com',
        'us-west-2'      => 'email.us-west-2.amazonaws.com',
        'eu-west-1'      => 'email.eu-west-1.amazonaws.com',
        'eu-west-2'      => 'email.eu-west-2.amazonaws.com',
        'eu-west-3'      => 'email.eu-west-3.amazonaws.com',
        'eu-central-1'   => 'email.eu-central-1.amazonaws.com',
        'ap-south-1'     => 'email.ap-south-1.amazonaws.com',
        'ap-southeast-1' => 'email.ap-southeast-1.amazonaws.com',
        'ap-southeast-2' => 'email.ap-southeast-2.amazonaws.com',
        'ap-northeast-1' => 'email.ap-northeast-1.amazonaws.com',
        'ap-northeast-2' => 'email.ap-northeast-2.amazonaws.com',
        'sa-east-1'      => 'email.sa-east-1.amazonaws.com',
        'ca-central-1'   => 'email.ca-central-1.amazonaws.com',
        'me-south-1'     => 'email.me-south-1.amazonaws.com',
        'af-south-1'     => 'email.af-south-1.amazonaws.com',
    );

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
     * @param string       $to      Recipient.
     * @param string       $subject Subject.
     * @param string       $message Message.
     * @param array|string $headers Headers.
     * @return bool
     */
    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();

        if ( empty( $settings['access_key'] ) || empty( $settings['secret_key'] ) || empty( $settings['region'] ) ) {
            return false;
        }

        $access_key = $settings['access_key'];
        $secret_key = $this->security->decrypt( $settings['secret_key'] );
        $region     = $settings['region'];
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
        $return_path = ! empty( $settings['return_path'] ) ? $settings['return_path'] : $from_email;

        // Build raw email.
        $boundary = uniqid( 'boundary_', true );
        $date     = gmdate( 'D, d M Y H:i:s O' );

        $raw_message = "From: {$from_name} <{$from_email}>\r\n";
        $raw_message .= "To: {$to}\r\n";
        $raw_message .= "Subject: {$subject}\r\n";
        $raw_message .= "Date: {$date}\r\n";
        $raw_message .= "Return-Path: {$return_path}\r\n";
        $raw_message .= "MIME-Version: 1.0\r\n";
        $raw_message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $raw_message .= "\r\n";
        $raw_message .= $message;

        // AWS SES v2 API endpoint.
        $host     = "email.{$region}.amazonaws.com";
        $endpoint = "https://{$host}/v2/email/outbound-emails";

        // Request body.
        $body = array(
            'Content' => array(
                'Raw' => array(
                    'Data' => base64_encode( $raw_message ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                ),
            ),
        );

        // Sign request with AWS Signature Version 4.
        $response = $this->make_signed_request( $endpoint, $body, $access_key, $secret_key, $region, $host );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'    => false,
                'message_id' => null,
                'error'      => $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && isset( $body['MessageId'] ) ) {
            return array(
                'success'    => true,
                'message_id' => $body['MessageId'],
                'error'      => '',
            );
        }

        return array(
            'success'    => false,
            'message_id' => null,
            'error'      => isset( $body['message'] ) ? $body['message'] : 'Unknown AWS SES error',
        );
    }

    /**
     * Make AWS signed request.
     *
     * @since 1.0.0
     * @param string $endpoint   API endpoint.
     * @param array  $body       Request body.
     * @param string $access_key AWS access key.
     * @param string $secret_key AWS secret key.
     * @param string $region     AWS region.
     * @param string $host       API host.
     * @return array|\WP_Error
     */
    private function make_signed_request( $endpoint, $body, $access_key, $secret_key, $region, $host ) {
        $service = 'ses';
        $method  = 'POST';
        $payload = wp_json_encode( $body );

        // Current time.
        $amz_date   = gmdate( 'Ymd\THis\Z' );
        $date_stamp = gmdate( 'Ymd' );

        // Create canonical request.
        $canonical_uri = '/v2/email/outbound-emails';
        $canonical_querystring = '';
        $canonical_headers = "content-type:application/json\nhost:{$host}\nx-amz-date:{$amz_date}\n";
        $signed_headers = 'content-type;host;x-amz-date';
        $payload_hash = hash( 'sha256', $payload );

        $canonical_request = "{$method}\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";

        // Create string to sign.
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date_stamp}/{$region}/{$service}/aws4_request";
        $string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );

        // Calculate signature.
        $signing_key = $this->get_signature_key( $secret_key, $date_stamp, $region, $service );
        $signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        // Create authorization header.
        $authorization_header = "{$algorithm} Credential={$access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        return wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'X-Amz-Date'    => $amz_date,
                'Authorization' => $authorization_header,
            ),
            'body'    => $payload,
            'timeout' => 30,
        ) );
    }

    /**
     * Get AWS signature key.
     *
     * @since 1.0.0
     * @param string $key         Secret key.
     * @param string $date_stamp  Date stamp.
     * @param string $region      Region.
     * @param string $service     Service name.
     * @return string
     */
    private function get_signature_key( $key, $date_stamp, $region, $service ) {
        $k_date    = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $key, true );
        $k_region  = hash_hmac( 'sha256', $region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $service, $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
        return $k_signing;
    }

    /**
     * Get saved settings.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['aws_ses'] ) ? $settings['aws_ses'] : array();
    }

    /**
     * Get provider name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name() {
        return __( 'Amazon SES', 'headless-forms' );
    }

    /**
     * Get provider slug.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_slug() {
        return 'aws_ses';
    }

    /**
     * Get settings fields.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields() {
        $region_options = array();
        foreach ( array_keys( $this->regions ) as $region ) {
            $region_options[ $region ] = $region;
        }

        return array(
            array(
                'id'       => 'access_key',
                'label'    => __( 'Access Key ID', 'headless-forms' ),
                'type'     => 'text',
                'required' => true,
            ),
            array(
                'id'       => 'secret_key',
                'label'    => __( 'Secret Access Key', 'headless-forms' ),
                'type'     => 'password',
                'required' => true,
            ),
            array(
                'id'       => 'region',
                'label'    => __( 'AWS Region', 'headless-forms' ),
                'type'     => 'select',
                'options'  => $region_options,
                'required' => true,
            ),
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'required'    => true,
                'description' => __( 'Must be verified in AWS SES.', 'headless-forms' ),
            ),
            array(
                'id'    => 'from_name',
                'label' => __( 'From Name', 'headless-forms' ),
                'type'  => 'text',
            ),
            array(
                'id'          => 'return_path',
                'label'       => __( 'Return-Path', 'headless-forms' ),
                'type'        => 'email',
                'description' => __( 'For bounce handling. Must be verified in AWS SES.', 'headless-forms' ),
            ),
        );
    }

    /**
     * Validate credentials.
     *
     * @since 1.0.0
     * @return bool
     */
    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['access_key'] ) 
            && ! empty( $settings['secret_key'] ) 
            && ! empty( $settings['region'] );
    }

    /**
     * Get help URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_help_url() {
        return 'https://docs.aws.amazon.com/ses/latest/dg/send-email-api.html';
    }

    /**
     * Send test email.
     *
     * @since 1.0.0
     * @param string $to Recipient.
     * @return array
     */
    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array(
                'success' => false,
                'message' => __( 'Please configure AWS SES settings first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - AWS SES Test', 'headless-forms' );
        $message = sprintf(
            '<p>%s</p><p><strong>%s:</strong> %s</p>',
            __( 'This is a test email from Headless Forms via AWS SES.', 'headless-forms' ),
            __( 'Region', 'headless-forms' ),
            $this->get_saved_settings()['region']
        );

        $sent = $this->send( $to, $subject, $message );

        return array(
            'success' => $sent,
            'message' => $sent
                ? __( 'AWS SES test email sent successfully!', 'headless-forms' )
                : __( 'Failed to send via AWS SES. Check your credentials and ensure the sender is verified.', 'headless-forms' ),
        );
    }
}
