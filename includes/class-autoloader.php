<?php
/**
 * PSR-4 Autoloader for HeadlessForms namespace.
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
 * Autoloader class for HeadlessForms plugin.
 *
 * Implements PSR-4 autoloading standard for the HeadlessForms namespace.
 * Maps class names to file paths following WordPress naming conventions.
 *
 * @since 1.0.0
 */
class Autoloader {

    /**
     * The namespace prefix for the plugin.
     *
     * @since 1.0.0
     * @var string
     */
    private $namespace = 'HeadlessForms\\';

    /**
     * The base directory for the namespace.
     *
     * @since 1.0.0
     * @var string
     */
    private $base_dir;

    /**
     * Constructor.
     *
     * Sets up the base directory for autoloading.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->base_dir = HEADLESS_FORMS_PATH . 'includes/';
    }

    /**
     * Register the autoloader with SPL.
     *
     * @since 1.0.0
     * @return void
     */
    public function register() {
        spl_autoload_register( array( $this, 'autoload' ) );
    }

    /**
     * Autoload callback function.
     *
     * @since 1.0.0
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public function autoload( $class ) {
        // Check if the class uses our namespace.
        $len = strlen( $this->namespace );
        if ( strncmp( $this->namespace, $class, $len ) !== 0 ) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr( $class, $len );

        // Build the file path.
        $file = $this->get_file_path( $relative_class );

        // If the file exists, require it.
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    /**
     * Get the file path for a class.
     *
     * Converts class names to file paths following WordPress naming conventions:
     * - Class names are converted to lowercase
     * - Underscores in class names become hyphens
     * - Files are prefixed with 'class-'
     * - Interface files are prefixed with 'interface-'
     * - Trait files are prefixed with 'trait-'
     *
     * @since 1.0.0
     * @param string $relative_class The relative class name (without namespace prefix).
     * @return string The file path.
     */
    private function get_file_path( $relative_class ) {
        // Split the class name by backslash to handle sub-namespaces.
        $parts = explode( '\\', $relative_class );
        $class_name = array_pop( $parts );

        // Convert sub-namespace parts to directory path.
        $sub_dir = '';
        if ( ! empty( $parts ) ) {
            $sub_dir = strtolower( implode( '/', $parts ) ) . '/';
        }

        // Determine the file prefix based on the class type.
        $prefix = 'class-';
        if ( strpos( $class_name, 'Interface' ) !== false || substr( $class_name, -9 ) === 'Interface' ) {
            $prefix = 'interface-';
            $class_name = str_replace( 'Interface', '', $class_name );
        } elseif ( strpos( $class_name, 'Trait' ) !== false || substr( $class_name, -5 ) === 'Trait' ) {
            $prefix = 'trait-';
            $class_name = str_replace( 'Trait', '', $class_name );
        }

        // Convert class name to file name (WordPress style).
        $file_name = $prefix . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        return $this->base_dir . $sub_dir . $file_name;
    }
}
