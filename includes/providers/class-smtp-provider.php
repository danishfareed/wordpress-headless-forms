<?php
/**
 * SMTP Provider.
 *
 * Sends email via custom SMTP server configuration.
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
 * SMTP Provider class.
 *
 * Configures WordPress to use custom SMTP server.
 *
 * @since 1.0.0
 */
class SMTP_Provider implements Email_Provider_Interface {

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
     * @param string       $to      Recipient.
     * @param string       $subject Subject.
     * @param string       $message Message.
     * @param array|string $headers Headers.
     * @return array Result array ['success' => bool, 'message_id' => string, 'error' => string].
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
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
            'message_id' => $success ? 'smtp_' . time() : '',
            'error'      => $success ? '' : $this->get_helpful_error( $error_message ),
        );
    }

    /**
     * Provide more context for common SMTP errors.
     *
     * @since 1.0.0
     * @param string $error Original error message.
     * @return string
     */
    private function get_helpful_error( $error ) {
        if ( empty( $error ) ) {
            return __( 'WordPress was unable to send via SMTP. Check if your hosting provider allows external SMTP connections.', 'headless-forms' );
        }

        if ( stripos( $error, 'Could not connect to SMTP host' ) !== false ) {
            return $error . ' ' . __( 'Tip: Verify your SMTP Host, Port, and Encryption (SSL/TLS) settings. Some hosts block port 25 or 465.', 'headless-forms' );
        }

        if ( stripos( $error, 'Password not accepted' ) !== false || stripos( $error, 'Authentication failed' ) !== false ) {
            return $error . ' ' . __( 'Tip: Double-check your SMTP Username and Password. If using Gmail, you may need an App Password.', 'headless-forms' );
        }

        return $error;
    }

    /**
     * Configure PHPMailer for SMTP.
     *
     * @since 1.0.0
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
     * @return void
     */
    public function configure_phpmailer( $phpmailer ) {
        $settings = $this->get_saved_settings();

        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings['host'];
        $phpmailer->Port       = (int) $settings['port'];
        $phpmailer->SMTPAuth   = ! empty( $settings['username'] );

        if ( $phpmailer->SMTPAuth ) {
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = $this->security->decrypt( $settings['password'] );
        }

        // Encryption.
        if ( ! empty( $settings['encryption'] ) && $settings['encryption'] !== 'none' ) {
            $phpmailer->SMTPSecure = $settings['encryption'];
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        // From address.
        if ( ! empty( $settings['from_email'] ) ) {
            $phpmailer->From = $settings['from_email'];
        }
        if ( ! empty( $settings['from_name'] ) ) {
            $phpmailer->FromName = $settings['from_name'];
        }
    }

    /**
     * Get saved settings.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_saved_settings() {
        $settings = get_option( 'headless_forms_provider_settings', array() );
        return isset( $settings['smtp'] ) ? $settings['smtp'] : array();
    }

    /**
     * Get provider name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name() {
        return __( 'SMTP', 'headless-forms' );
    }

    /**
     * Get provider slug.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_slug() {
        return 'smtp';
    }

    /**
     * Get settings fields.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id'          => 'host',
                'label'       => __( 'SMTP Host', 'headless-forms' ),
                'type'        => 'text',
                'placeholder' => 'smtp.example.com',
                'required'    => true,
            ),
            array(
                'id'          => 'port',
                'label'       => __( 'SMTP Port', 'headless-forms' ),
                'type'        => 'number',
                'placeholder' => '587',
                'required'    => true,
            ),
            array(
                'id'      => 'encryption',
                'label'   => __( 'Encryption', 'headless-forms' ),
                'type'    => 'select',
                'options' => array(
                    'none' => __( 'None', 'headless-forms' ),
                    'ssl'  => 'SSL',
                    'tls'  => 'TLS',
                ),
            ),
            array(
                'id'          => 'username',
                'label'       => __( 'Username', 'headless-forms' ),
                'type'        => 'text',
                'description' => __( 'SMTP authentication username.', 'headless-forms' ),
            ),
            array(
                'id'    => 'password',
                'label' => __( 'Password', 'headless-forms' ),
                'type'  => 'password',
            ),
            array(
                'id'          => 'from_email',
                'label'       => __( 'From Email', 'headless-forms' ),
                'type'        => 'email',
                'placeholder' => 'noreply@example.com',
            ),
            array(
                'id'          => 'from_name',
                'label'       => __( 'From Name', 'headless-forms' ),
                'type'        => 'text',
                'placeholder' => get_bloginfo( 'name' ),
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
        return ! empty( $settings['host'] ) && ! empty( $settings['port'] );
    }

    /**
     * Get help URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_help_url() {
        return 'https://codewithdanish.dev/headless-forms/docs/smtp';
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
                'message' => __( 'Please configure SMTP settings first.', 'headless-forms' ),
            );
        }

        $subject = __( 'Headless Forms - SMTP Test', 'headless-forms' );
        $message = sprintf(
            '<p>%s</p><p><strong>%s:</strong> %s</p>',
            __( 'This is a test email from Headless Forms SMTP provider.', 'headless-forms' ),
            __( 'Provider', 'headless-forms' ),
            'SMTP'
        );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $result  = $this->send( $to, $subject, $message, $headers );

        return array(
            'success' => $result['success'],
            'message' => $result['success']
                ? __( 'SMTP test email sent successfully!', 'headless-forms' )
                : $result['error'],
        );
    }
}
