<?php
/**
 * Email Factory Class.
 *
 * Factory pattern implementation for creating email provider instances.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Email Factory class.
 *
 * Manages registration and instantiation of email providers.
 * Provides a central point for sending emails through configured provider.
 *
 * @since 1.0.0
 */
class Email_Factory {

    /**
     * Registered providers.
     *
     * @since 1.0.0
     * @var array
     */
    private $providers = array();

    /**
     * Provider instances cache.
     *
     * @since 1.0.0
     * @var array
     */
    private $instances = array();

    /**
     * Constructor.
     *
     * Registers all available email providers.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->register_default_providers();
    }

    /**
     * Register all default email providers.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_default_providers() {
        // WordPress native mail.
        $this->register( 'wp_mail', WP_Mail_Provider::class );

        // SMTP.
        $this->register( 'smtp', SMTP_Provider::class );

        // AWS SES.
        $this->register( 'aws_ses', AWS_SES_Provider::class );

        // SendGrid.
        $this->register( 'sendgrid', SendGrid_Provider::class );

        // Resend.
        $this->register( 'resend', Resend_Provider::class );

        // Mailgun.
        $this->register( 'mailgun', Mailgun_Provider::class );

        // Postmark.
        $this->register( 'postmark', Postmark_Provider::class );

        // SparkPost.
        $this->register( 'sparkpost', SparkPost_Provider::class );

        // Mandrill.
        $this->register( 'mandrill', Mandrill_Provider::class );

        // Elastic Email.
        $this->register( 'elastic_email', Elastic_Email_Provider::class );

        // Brevo (Sendinblue).
        $this->register( 'brevo', Brevo_Provider::class );

        // MailerSend.
        $this->register( 'mailersend', MailerSend_Provider::class );

        // Mailjet.
        $this->register( 'mailjet', Mailjet_Provider::class );

        // SMTP2GO.
        $this->register( 'smtp2go', SMTP2GO_Provider::class );

        // Moosend.
        $this->register( 'moosend', Moosend_Provider::class );

        // Loops.
        $this->register( 'loops', Loops_Provider::class );

        /**
         * Action to register custom email providers.
         *
         * @since 1.0.0
         * @param Email_Factory $factory The factory instance.
         */
        do_action( 'headless_forms_register_providers', $this );
    }

    /**
     * Register a provider.
     *
     * @since 1.0.0
     * @param string $slug  Provider slug/identifier.
     * @param string $class Provider class name.
     * @return void
     */
    public function register( $slug, $class ) {
        $this->providers[ $slug ] = $class;
    }

    /**
     * Get a provider instance.
     *
     * @since 1.0.0
     * @param string $slug Provider slug.
     * @return Email_Provider_Interface|null Provider instance or null.
     */
    public function get_provider( $slug ) {
        if ( ! isset( $this->providers[ $slug ] ) ) {
            return null;
        }

        if ( ! isset( $this->instances[ $slug ] ) ) {
            $class = $this->providers[ $slug ];
            $this->instances[ $slug ] = new $class();
        }

        return $this->instances[ $slug ];
    }

    /**
     * Get the currently active provider.
     *
     * @since 1.0.0
     * @return Email_Provider_Interface The active provider.
     */
    public function get_active_provider() {
        $active_slug = get_option( 'headless_forms_email_provider', 'wp_mail' );
        $provider = $this->get_provider( $active_slug );

        // Fallback to wp_mail if provider not found.
        if ( null === $provider ) {
            $provider = $this->get_provider( 'wp_mail' );
        }

        return $provider;
    }

    /**
     * Get all registered providers.
     *
     * @since 1.0.0
     * @return array Array of provider instances.
     */
    public function get_all_providers() {
        $all = array();

        foreach ( array_keys( $this->providers ) as $slug ) {
            $provider = $this->get_provider( $slug );
            if ( $provider ) {
                $all[ $slug ] = array(
                    'name'   => $provider->get_name(),
                    'slug'   => $provider->get_slug(),
                    'fields' => $provider->get_settings_fields(),
                    'help'   => $provider->get_help_url(),
                );
            }
        }

        return $all;
    }

    /**
     * Send an email using the active provider.
     *
     * @since 1.0.0
     * @since 1.1.0 Added $attachments parameter.
     * @param string       $to          Recipient email.
     * @param string       $subject     Subject.
     * @param string       $message     Message body.
     * @param array|string $headers     Optional headers.
     * @param array        $attachments Optional file attachments.
     * @return bool True if sent.
     */
    public function send( $to, $subject, $message, $headers = array(), $attachments = array() ) {
        $provider = $this->get_active_provider();
        return $provider->send( $to, $subject, $message, $headers, $attachments );
    }

    /**
     * Send a test email.
     *
     * @since 1.0.0
     * @param string $to           Recipient email.
     * @param string $provider_slug Optional. Specific provider to test.
     * @return array Result with success and message.
     */
    public function send_test( $to, $provider_slug = null ) {
        if ( $provider_slug ) {
            $provider = $this->get_provider( $provider_slug );
        } else {
            $provider = $this->get_active_provider();
        }

        if ( ! $provider ) {
            return array(
                'success' => false,
                'message' => __( 'Provider not found.', 'headless-forms' ),
            );
        }

        return $provider->send_test( $to );
    }

    /**
     * Validate provider credentials.
     *
     * @since 1.0.0
     * @param string $provider_slug Optional. Specific provider to validate.
     * @return bool True if valid.
     */
    public function validate( $provider_slug = null ) {
        if ( $provider_slug ) {
            $provider = $this->get_provider( $provider_slug );
        } else {
            $provider = $this->get_active_provider();
        }

        if ( ! $provider ) {
            return false;
        }

        return $provider->validate_credentials();
    }
}
