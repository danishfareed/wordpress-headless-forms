<?php
/**
 * Plugin Name:       Headless Forms
 * Plugin URI:        https://codewithdanish.dev/headless-forms
 * Description:       A production-ready headless form handler for WordPress. Receive form submissions via REST API, store in custom tables, and route emails via 16+ providers including AWS SES, SendGrid, Resend, Mailgun, and more.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Danish Mohammed
 * Author URI:        https://codewithdanish.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       headless-forms
 * Domain Path:       /languages
 *
 * @package HeadlessForms
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version constant.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_VERSION', '1.0.0' );

/**
 * Plugin database version for migrations.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_DB_VERSION', '1.0.0' );

/**
 * Plugin file path constant.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_FILE', __FILE__ );

/**
 * Plugin directory path constant.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL constant.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename constant.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_MIN_PHP', '7.4' );

/**
 * Minimum WordPress version required.
 *
 * @since 1.0.0
 */
define( 'HEADLESS_FORMS_MIN_WP', '5.8' );

/**
 * Check PHP version compatibility.
 *
 * @since 1.0.0
 * @return bool True if compatible, false otherwise.
 */
function headless_forms_check_php_version() {
    return version_compare( PHP_VERSION, HEADLESS_FORMS_MIN_PHP, '>=' );
}

/**
 * Check WordPress version compatibility.
 *
 * @since 1.0.0
 * @return bool True if compatible, false otherwise.
 */
function headless_forms_check_wp_version() {
    return version_compare( get_bloginfo( 'version' ), HEADLESS_FORMS_MIN_WP, '>=' );
}

/**
 * Display admin notice for PHP version incompatibility.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_php_version_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__( 'Headless Forms requires PHP version %1$s or higher. Your current version is %2$s. Please upgrade PHP to use this plugin.', 'headless-forms' ),
                esc_html( HEADLESS_FORMS_MIN_PHP ),
                esc_html( PHP_VERSION )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Display admin notice for WordPress version incompatibility.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_wp_version_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                esc_html__( 'Headless Forms requires WordPress version %1$s or higher. Your current version is %2$s. Please upgrade WordPress to use this plugin.', 'headless-forms' ),
                esc_html( HEADLESS_FORMS_MIN_WP ),
                esc_html( get_bloginfo( 'version' ) )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Check system requirements and initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_init() {
    // Check PHP version.
    if ( ! headless_forms_check_php_version() ) {
        add_action( 'admin_notices', 'headless_forms_php_version_notice' );
        return;
    }

    // Check WordPress version.
    if ( ! headless_forms_check_wp_version() ) {
        add_action( 'admin_notices', 'headless_forms_wp_version_notice' );
        return;
    }

    // Load the autoloader.
    require_once HEADLESS_FORMS_PATH . 'includes/class-autoloader.php';

    // Initialize the autoloader.
    $autoloader = new HeadlessForms\Autoloader();
    $autoloader->register();

    // Initialize the main plugin class.
    $plugin = HeadlessForms\Plugin::get_instance();
    $plugin->init();
}

// Initialize the plugin after all plugins are loaded.
add_action( 'plugins_loaded', 'headless_forms_init' );

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_activate() {
    // Check PHP version before activation.
    if ( ! headless_forms_check_php_version() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: %s: Required PHP version */
                esc_html__( 'Headless Forms requires PHP version %s or higher.', 'headless-forms' ),
                esc_html( HEADLESS_FORMS_MIN_PHP )
            ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }

    // Check WordPress version before activation.
    if ( ! headless_forms_check_wp_version() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: %s: Required WordPress version */
                esc_html__( 'Headless Forms requires WordPress version %s or higher.', 'headless-forms' ),
                esc_html( HEADLESS_FORMS_MIN_WP )
            ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }

    // Load the autoloader for activation.
    require_once HEADLESS_FORMS_PATH . 'includes/class-autoloader.php';
    $autoloader = new HeadlessForms\Autoloader();
    $autoloader->register();

    // Run database installation.
    $db_installer = new HeadlessForms\DB_Installer();
    $db_installer->install();

    // Generate API key if not exists.
    if ( ! get_option( 'headless_forms_api_key' ) ) {
        $security = new HeadlessForms\Security();
        update_option( 'headless_forms_api_key', $security->generate_api_key() );
    }

    // Set default options.
    $default_options = array(
        'email_provider'      => 'wp_mail',
        'rate_limit'          => 5,
        'rate_limit_window'   => 60,
        'honeypot_field'      => '_honey',
        'keep_data_on_delete' => false,
        'cors_origins'        => '',
        'data_retention_days' => 0, // 0 = keep forever
    );

    foreach ( $default_options as $key => $value ) {
        if ( get_option( 'headless_forms_' . $key ) === false ) {
            update_option( 'headless_forms_' . $key, $value );
        }
    }

    // Store db version.
    update_option( 'headless_forms_db_version', HEADLESS_FORMS_DB_VERSION );

    // Set activation flag for welcome notice.
    set_transient( 'headless_forms_activated', true, 60 );

    // Flush rewrite rules for REST API.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'headless_forms_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_deactivate() {
    // Clear scheduled events.
    wp_clear_scheduled_hook( 'headless_forms_cleanup_logs' );
    wp_clear_scheduled_hook( 'headless_forms_retry_failed_emails' );
    wp_clear_scheduled_hook( 'headless_forms_data_retention' );

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'headless_forms_deactivate' );

/**
 * Load plugin text domain for translations.
 *
 * @since 1.0.0
 * @return void
 */
function headless_forms_load_textdomain() {
    load_plugin_textdomain(
        'headless-forms',
        false,
        dirname( HEADLESS_FORMS_BASENAME ) . '/languages'
    );
}
add_action( 'init', 'headless_forms_load_textdomain' );
