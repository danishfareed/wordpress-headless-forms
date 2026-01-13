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

    public function send( $to, $subject, $message, $headers = array() ) {
        $settings = $this->get_saved_settings();
        if ( empty( $settings['api_key'] ) ) {
            return false;
        }

        // Moosend uses SMTP for transactional - configure PHPMailer.
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
        $result = wp_mail( $to, $subject, $message, $headers );
        remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

        return $result;
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
        $sent = $this->send( $to, __( 'Moosend Test', 'headless-forms' ), '<p>Test email.</p>', array( 'Content-Type: text/html' ) );
        return array( 'success' => $sent, 'message' => $sent ? __( 'Sent!', 'headless-forms' ) : __( 'Failed.', 'headless-forms' ) );
    }
}
