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
        'us-east-1'      => 'US East (N. Virginia)',
        'us-east-2'      => 'US East (Ohio)',
        'us-west-1'      => 'US West (N. California)',
        'us-west-2'      => 'US West (Oregon)',
        'af-south-1'     => 'Africa (Cape Town)',
        'ap-east-1'      => 'Asia Pacific (Hong Kong)',
        'ap-south-1'     => 'Asia Pacific (Mumbai)',
        'ap-south-2'     => 'Asia Pacific (Hyderabad)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-southeast-3' => 'Asia Pacific (Jakarta)',
        'ap-southeast-4' => 'Asia Pacific (Melbourne)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-northeast-3' => 'Asia Pacific (Osaka)',
        'ca-central-1'   => 'Canada (Central)',
        'ca-west-1'      => 'Canada (West)',
        'eu-central-1'   => 'Europe (Frankfurt)',
        'eu-central-2'   => 'Europe (Zurich)',
        'eu-west-1'      => 'Europe (Ireland)',
        'eu-west-2'      => 'Europe (London)',
        'eu-west-3'      => 'Europe (Paris)',
        'eu-north-1'     => 'Europe (Stockholm)',
        'eu-south-1'     => 'Europe (Milan)',
        'eu-south-2'     => 'Europe (Spain)',
        'il-central-1'   => 'Israel (Tel Aviv)',
        'me-south-1'     => 'Middle East (Bahrain)',
        'me-central-1'   => 'Middle East (UAE)',
        'sa-east-1'      => 'South America (São Paulo)',
        'us-gov-east-1'  => 'AWS GovCloud (US-East)',
        'us-gov-west-1'  => 'AWS GovCloud (US-West)',
        'custom'         => 'Custom Region...',
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

        if ( empty( $settings['access_key'] ) || empty( $settings['secret_key'] ) || empty( $settings['region'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'AWS credentials or region not configured.', 'headless-forms' ),
            );
        }

        $access_key = $this->security->decrypt( $settings['access_key'] );
        $secret_key = $this->security->decrypt( $settings['secret_key'] );
        $region     = ( isset( $settings['region'] ) && $settings['region'] === 'custom' && ! empty( $settings['custom_region'] ) ) ? $settings['custom_region'] : $settings['region'];
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );
        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
        $return_path = ! empty( $settings['return_path'] ) ? $settings['return_path'] : $from_email;

        // Build raw email (multipart/mixed if attachments exist).
        $boundary = uniqid( 'boundary_', true );
        $date     = gmdate( 'D, d M Y H:i:s O' );

        $raw_message = "From: {$from_name} <{$from_email}>\r\n";
        $raw_message .= "To: {$to}\r\n";
        $raw_message .= "Subject: {$subject}\r\n";
        $raw_message .= "Date: {$date}\r\n";
        $raw_message .= "Return-Path: {$return_path}\r\n";
        $raw_message .= "MIME-Version: 1.0\r\n";

        if ( empty( $attachments ) ) {
            $raw_message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $raw_message .= "\r\n";
            $raw_message .= $message;
        } else {
            $raw_message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $raw_message .= "\r\n";
            $raw_message .= "--{$boundary}\r\n";
            $raw_message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $raw_message .= "\r\n";
            $raw_message .= $message . "\r\n";

            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['path'] ) && file_exists( $attachment['path'] ) ) {
                    $filename = $attachment['name'] ?? basename( $attachment['path'] );
                    $mime_type = $attachment['mime_type'] ?? 'application/octet-stream';
                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                    $content = chunk_split( base64_encode( file_get_contents( $attachment['path'] ) ) );

                    $raw_message .= "--{$boundary}\r\n";
                    $raw_message .= "Content-Type: {$mime_type}; name=\"{$filename}\"\r\n";
                    $raw_message .= "Content-Description: {$filename}\r\n";
                    $raw_message .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . filesize( $attachment['path'] ) . ";\r\n";
                    $raw_message .= "Content-Transfer-Encoding: base64\r\n";
                    $raw_message .= "\r\n";
                    $raw_message .= $content . "\r\n";
                }
            }
            $raw_message .= "--{$boundary}--";
        }

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
            'error'      => isset( $body['message'] ) ? $body['message'] : ( isset( $body['Message'] ) ? $body['Message'] : sprintf( __( 'API returned code %d', 'headless-forms' ), $code ) ),
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
        $region_options = $this->regions;

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
                'options'  => $this->regions,
                'required' => true,
            ),
            array(
                'id'          => 'custom_region',
                'label'       => __( 'Custom Region Name', 'headless-forms' ),
                'type'        => 'text',
                'placeholder' => 'e.g. us-east-1',
                'description' => __( 'Only used if "Custom Region..." is selected above.', 'headless-forms' ),
            ),
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'required'    => true,
                'description' => __( 'Must be verified in your AWS SES console. If you are in "Sandbox Mode", you can ONLY send to verified emails.', 'headless-forms' ),
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
        if ( empty( $settings['access_key'] ) || empty( $settings['secret_key'] ) || empty( $settings['region'] ) ) {
            return false;
        }

        if ( $settings['region'] === 'custom' && empty( $settings['custom_region'] ) ) {
            return false;
        }

        return true;
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

        $result = $this->send( $to, $subject, $message );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'AWS SES test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
