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
        $this->base_dir = HEADLESS_FORMS_PATH;
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
        if ( $file && file_exists( $file ) ) {
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

        // Determine the base directory based on namespace.
        $base = $this->base_dir;
        
        // Map namespaces to directories.
        if ( ! empty( $parts ) ) {
            $namespace_dir = strtolower( $parts[0] );
            
            // Admin namespace maps to admin/ folder.
            if ( $namespace_dir === 'admin' ) {
                $base = $this->base_dir . 'admin/';
                array_shift( $parts ); // Remove 'Admin' from parts.
            } else {
                // Other namespaces (like Providers) map to includes/namespace/.
                $base = $this->base_dir . 'includes/';
            }
        } else {
            $base = $this->base_dir . 'includes/';
        }

        // Convert remaining sub-namespace parts to directory path.
        $sub_dir = '';
        if ( ! empty( $parts ) ) {
            $sub_dir = strtolower( implode( '/', $parts ) ) . '/';
        }

        // Determine the file prefix based on the class type.
        $prefix = 'class-';
        $original_class_name = $class_name;
        
        // Check if it's an interface (ends with _Interface or Interface).
        if ( substr( $class_name, -10 ) === '_Interface' ) {
            $prefix = 'interface-';
            $class_name = substr( $class_name, 0, -10 ); // Remove _Interface.
        } elseif ( substr( $class_name, -9 ) === 'Interface' ) {
            $prefix = 'interface-';
            $class_name = substr( $class_name, 0, -9 ); // Remove Interface.
        } elseif ( substr( $class_name, -6 ) === '_Trait' ) {
            $prefix = 'trait-';
            $class_name = substr( $class_name, 0, -6 ); // Remove _Trait.
        } elseif ( substr( $class_name, -5 ) === 'Trait' ) {
            $prefix = 'trait-';
            $class_name = substr( $class_name, 0, -5 ); // Remove Trait.
        }

        // Convert class name to file name (WordPress style).
        // Handle empty class name edge case.
        if ( empty( $class_name ) ) {
            $class_name = $original_class_name;
            $prefix = 'class-';
        }

        $file_name = $prefix . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        return $base . $sub_dir . $file_name;
    }
}
