<?php
/**
 * Main Plugin Class.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

namespace HeadlessForms;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 *
 * Singleton class responsible for initializing all plugin components,
 * loading dependencies, and coordinating between different modules.
 *
 * @since 1.0.0
 */
class Plugin {

    /**
     * Single instance of the class.
     *
     * @since 1.0.0
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * API Handler instance.
     *
     * @since 1.0.0
     * @var API_Handler|null
     */
    private $api_handler = null;

    /**
     * Admin Dashboard instance.
     *
     * @since 1.0.0
     * @var Admin\Admin_Dashboard|null
     */
    private $admin_dashboard = null;

    /**
     * Email Factory instance.
     *
     * @since 1.0.0
     * @var Providers\Email_Factory|null
     */
    private $email_factory = null;

    /**
     * Webhook Handler instance.
     *
     * @since 1.0.0
     * @var Webhook_Handler|null
     */
    private $webhook_handler = null;

    /**
     * Email Logger instance.
     *
     * @since 1.0.0
     * @var Email_Logger|null
     */
    private $email_logger = null;

    /**
     * GDPR Handler instance.
     *
     * @since 1.0.0
     * @var GDPR_Handler|null
     */
    private $gdpr_handler = null;

    /**
     * Analytics instance.
     *
     * @since 1.0.0
     * @var Analytics|null
     */
    private $analytics = null;

    /**
     * Get the singleton instance.
     *
     * @since 1.0.0
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Private constructor.
    }

    /**
     * Prevent cloning of the instance.
     *
     * @since 1.0.0
     * @return void
     */
    private function __clone() {
        // Prevent cloning.
    }

    /**
     * Prevent unserializing of the instance.
     *
     * @since 1.0.0
     * @throws \Exception Always throws exception.
     * @return void
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Initialize the plugin.
     *
     * Sets up all hooks, loads components, and initializes services.
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Initialize core components.
        $this->init_components();

        // Register hooks.
        $this->register_hooks();

        // Initialize admin if in admin context.
        if ( is_admin() ) {
            $this->init_admin();
        }

        // Initialize REST API.
        add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );

        // Schedule cron events.
        $this->schedule_events();

        /**
         * Fires after the plugin has been fully initialized.
         *
         * @since 1.0.0
         * @param Plugin $plugin The plugin instance.
         */
        do_action( 'headless_forms_loaded', $this );
    }

    /**
     * Initialize plugin components.
     *
     * @since 1.0.0
     * @return void
     */
    private function init_components() {
        // Initialize Email Factory.
        $this->email_factory = new Providers\Email_Factory();

        // Initialize Email Logger.
        $this->email_logger = new Email_Logger();

        // Initialize Webhook Handler.
        $this->webhook_handler = new Webhook_Handler();

        // Initialize GDPR Handler.
        $this->gdpr_handler = new GDPR_Handler();

        // Initialize Analytics.
        $this->analytics = new Analytics();
    }

    /**
     * Register WordPress hooks.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_hooks() {
        // Add plugin action links.
        add_filter( 'plugin_action_links_' . HEADLESS_FORMS_BASENAME, array( $this, 'add_action_links' ) );

        // Add plugin row meta.
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

        // Handle cron events.
        add_action( 'headless_forms_cleanup_logs', array( $this->email_logger, 'cleanup_old_logs' ) );
        add_action( 'headless_forms_retry_failed_emails', array( $this->email_logger, 'retry_failed_emails' ) );
        add_action( 'headless_forms_data_retention', array( $this->gdpr_handler, 'process_data_retention' ) );
    }

    /**
     * Initialize admin components.
     *
     * @since 1.0.0
     * @return void
     */
    private function init_admin() {
        $this->admin_dashboard = new Admin\Admin_Dashboard();
        $this->admin_dashboard->init();

        // Show activation notice.
        if ( get_transient( 'headless_forms_activated' ) ) {
            add_action( 'admin_notices', array( $this, 'show_activation_notice' ) );
            delete_transient( 'headless_forms_activated' );
        }
    }

    /**
     * Initialize REST API.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_rest_api() {
        $this->api_handler = new API_Handler( $this->email_factory, $this->webhook_handler, $this->email_logger );
        $this->api_handler->register_routes();
    }

    /**
     * Schedule cron events.
     *
     * @since 1.0.0
     * @return void
     */
    private function schedule_events() {
        // Schedule log cleanup (daily).
        if ( ! wp_next_scheduled( 'headless_forms_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'headless_forms_cleanup_logs' );
        }

        // Schedule email retry (hourly).
        if ( ! wp_next_scheduled( 'headless_forms_retry_failed_emails' ) ) {
            wp_schedule_event( time(), 'hourly', 'headless_forms_retry_failed_emails' );
        }

        // Schedule data retention cleanup (daily).
        if ( ! wp_next_scheduled( 'headless_forms_data_retention' ) ) {
            wp_schedule_event( time(), 'daily', 'headless_forms_data_retention' );
        }
    }

    /**
     * Add action links to plugin page.
     *
     * @since 1.0.0
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . esc_url( admin_url( 'admin.php?page=headless-forms-settings' ) ) . '">' . esc_html__( 'Settings', 'headless-forms' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    /**
     * Add row meta to plugin page.
     *
     * @since 1.0.0
     * @param array  $links Existing row meta links.
     * @param string $file  Plugin file.
     * @return array Modified row meta links.
     */
    public function add_row_meta( $links, $file ) {
        if ( HEADLESS_FORMS_BASENAME !== $file ) {
            return $links;
        }

        $row_meta = array(
            'docs'    => '<a href="' . esc_url( 'https://codewithdanish.dev/headless-forms/docs' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Documentation', 'headless-forms' ) . '</a>',
            'support' => '<a href="' . esc_url( 'https://wordpress.org/support/plugin/headless-forms/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support', 'headless-forms' ) . '</a>',
        );

        return array_merge( $links, $row_meta );
    }

    /**
     * Show activation notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function show_activation_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e( 'Headless Forms activated!', 'headless-forms' ); ?></strong>
                <?php
                printf(
                    /* translators: %s: Settings page URL */
                    esc_html__( 'Get started by creating your first form in %s.', 'headless-forms' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=headless-forms' ) ) . '">' . esc_html__( 'Headless Forms', 'headless-forms' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get the Email Factory instance.
     *
     * @since 1.0.0
     * @return Providers\Email_Factory
     */
    public function get_email_factory() {
        return $this->email_factory;
    }

    /**
     * Get the Email Logger instance.
     *
     * @since 1.0.0
     * @return Email_Logger
     */
    public function get_email_logger() {
        return $this->email_logger;
    }

    /**
     * Get the Webhook Handler instance.
     *
     * @since 1.0.0
     * @return Webhook_Handler
     */
    public function get_webhook_handler() {
        return $this->webhook_handler;
    }

    /**
     * Get the GDPR Handler instance.
     *
     * @since 1.0.0
     * @return GDPR_Handler
     */
    public function get_gdpr_handler() {
        return $this->gdpr_handler;
    }

    /**
     * Get the Analytics instance.
     *
     * @since 1.0.0
     * @return Analytics
     */
    public function get_analytics() {
        return $this->analytics;
    }
}
