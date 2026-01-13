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
     * @return bool
     */
    public function send( $to, $subject, $message, $headers = array() ) {
        // Configure PHPMailer via WordPress hooks.
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

        $result = wp_mail( $to, $subject, $message, $headers );

        // Remove the filter after sending.
        remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );

        return $result;
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
        $sent = $this->send( $to, $subject, $message, $headers );

        return array(
            'success' => $sent,
            'message' => $sent
                ? __( 'SMTP test email sent successfully!', 'headless-forms' )
                : __( 'Failed to send via SMTP. Check your server settings.', 'headless-forms' ),
        );
    }
}
