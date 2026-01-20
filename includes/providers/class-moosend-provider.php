<?php
/**
 * Moosend Provider.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

use HeadlessForms\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Moosend_Provider implements Email_Provider_Interface {

    const API_ENDPOINT = 'https://api.moosend.com/v3/campaigns/send';

    private $security;

    public function __construct() {
        $this->security = new Security();
    }

    /**
     * Send an email.
     *
     * @param string       $to      Recipient.
     * @param string       $subject Subject.
     * @param string       $message Message.
     * @param array|string $headers Headers.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) ) {
            return array(
                'success' => false,
                'error'   => __( 'API Key not configured.', 'headless-forms' ),
            );
        }

        $error_message = '';
        $capture_error = function( $error ) use ( &$error_message ) {
            $error_message = $error->get_error_message();
        };

        add_action( 'wp_mail_failed', $capture_error );
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
        
        $success = wp_mail( $to, $subject, $message, $headers, $attachments );
        
        remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
        remove_action( 'wp_mail_failed', $capture_error );

        return array(
            'success'    => $success,
            'message_id' => $success ? 'ms_' . time() : '',
            'error'      => $success ? '' : $this->get_helpful_error( $error_message ),
        );
    }

    /**
     * Provide more context for common Moosend SMTP errors.
     *
     * @since 1.0.0
     * @param string $error Original error message.
     * @return string
     */
    private function get_helpful_error( $error ) {
        if ( empty( $error ) ) {
            return __( 'WordPress was unable to send via Moosend. Check if your hosting provider allows external SMTP connections.', 'headless-forms' );
        }

        if ( stripos( $error, 'Could not connect to SMTP host' ) !== false ) {
            return $error . ' ' . __( 'Tip: Your server might be blocking outgoing connections to smtp.moosend.com. Contact your host.', 'headless-forms' );
        }

        if ( stripos( $error, 'Password not accepted' ) !== false || stripos( $error, 'Authentication failed' ) !== false ) {
            return $error . ' ' . __( 'Tip: Verify your Moosend API Key.', 'headless-forms' );
        }

        return $error;
    }

    public function configure_phpmailer( $phpmailer ) {
        $settings = $this->get_saved_settings();
        $api_key  = $this->security->decrypt( $settings['api_key'] );

        $phpmailer->isSMTP();
        $phpmailer->Host       = 'smtp.moosend.com';
        $phpmailer->Port       = 587;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = ! empty( $settings['smtp_username'] ) ? $settings['smtp_username'] : $api_key;
        $phpmailer->Password   = $api_key;
        $phpmailer->SMTPSecure = 'tls';

        if ( ! empty( $settings['from_email'] ) ) {
            $phpmailer->From = $settings['from_email'];
        }
        if ( ! empty( $settings['from_name'] ) ) {
            $phpmailer->FromName = $settings['from_name'];
        }
    }

    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['moosend'] ) ? $settings['moosend'] : array();
    }

    public function get_name() { return 'Moosend'; }
    public function get_slug() { return 'moosend'; }

    public function get_settings_fields() {
        return array(
            array( 'id' => 'api_key', 'label' => __( 'API Key', 'headless-forms' ), 'type' => 'password', 'required' => true ),
            array( 'id' => 'smtp_username', 'label' => __( 'SMTP Username', 'headless-forms' ), 'type' => 'text', 'description' => __( 'Usually your email address.', 'headless-forms' ) ),
            array( 'id' => 'from_email', 'label' => __( 'From Email', 'headless-forms' ), 'type' => 'email', 'required' => true ),
            array( 'id' => 'from_name', 'label' => __( 'From Name', 'headless-forms' ), 'type' => 'text' ),
        );
    }

    public function validate_credentials() {
        $settings = $this->get_saved_settings();
        return ! empty( $settings['api_key'] );
    }

    public function get_help_url() { return 'https://help.moosend.com/hc/en-us/articles/360002124331-How-to-use-SMTP-Relay'; }

    public function send_test( $to ) {
        if ( ! $this->validate_credentials() ) {
            return array( 'success' => false, 'message' => __( 'Configure Moosend first.', 'headless-forms' ) );
        }
        $result = $this->send( $to, __( 'Moosend Test', 'headless-forms' ), '<p>This is a test email via Moosend from Headless Forms.</p>', array( 'Content-Type: text/html' ) );
        
        return array( 
            'success' => $result['success'], 
            'message' => $result['success'] 
                ? __( 'Moosend test email sent successfully!', 'headless-forms' ) 
                : $result['error'],
        );
    }
}
