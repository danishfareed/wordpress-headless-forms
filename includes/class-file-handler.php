<?php
/**
 * File Handler Class.
 *
 * Handles file upload processing, validation, and storage.
 *
 * @package HeadlessForms
 * @since   1.1.0
 */

namespace HeadlessForms;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * File Handler class.
 *
 * Processes file uploads for form submissions with security validation,
 * size limits, and organized storage.
 *
 * @since 1.1.0
 */
class File_Handler {

    /**
     * Maximum file size in bytes (10MB).
     *
     * @since 1.1.0
     * @var int
     */
    const MAX_FILE_SIZE = 10485760; // 10 * 1024 * 1024

    /**
     * Default allowed MIME types.
     *
     * @since 1.1.0
     * @var array
     */
    private $default_allowed_types = array(
        'image/jpeg'                                                              => 'jpg',
        'image/png'                                                               => 'png',
        'image/gif'                                                               => 'gif',
        'application/pdf'                                                         => 'pdf',
        'application/msword'                                                      => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                                => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    );

    /**
     * Upload base directory.
     *
     * @since 1.1.0
     * @var string
     */
    private $upload_dir;

    /**
     * Upload base URL.
     *
     * @since 1.1.0
     * @var string
     */
    private $upload_url;

    /**
     * Constructor.
     *
     * @since 1.1.0
     */
    public function __construct() {
        $wp_upload_dir    = wp_upload_dir();
        $this->upload_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'headless-forms';
        $this->upload_url = trailingslashit( $wp_upload_dir['baseurl'] ) . 'headless-forms';
    }

    /**
     * Process uploaded files from a form submission.
     *
     * @since 1.1.0
     * @param array $files       The $_FILES array.
     * @param int   $form_id     The form ID.
     * @param int   $max_files   Maximum number of files allowed (0 = unlimited).
     * @return array|WP_Error Array of processed file data or error.
     */
    public function process_uploads( $files, $form_id, $max_files = 0 ) {
        if ( empty( $files ) ) {
            return array();
        }

        // Normalize files array (handle both single and multiple uploads).
        $normalized = $this->normalize_files_array( $files );

        if ( empty( $normalized ) ) {
            return array();
        }

        // Check file count limit.
        if ( $max_files > 0 && count( $normalized ) > $max_files ) {
            return new \WP_Error(
                'too_many_files',
                sprintf(
                    /* translators: %d: Maximum number of files allowed */
                    __( 'Too many files. Maximum %d files allowed.', 'headless-forms' ),
                    $max_files
                ),
                array( 'status' => 400 )
            );
        }

        $processed_files = array();

        foreach ( $normalized as $field_name => $file_data ) {
            // Skip empty uploads.
            if ( empty( $file_data['name'] ) || $file_data['error'] === UPLOAD_ERR_NO_FILE ) {
                continue;
            }

            // Validate file.
            $validation = $this->validate_file( $file_data );
            if ( is_wp_error( $validation ) ) {
                return $validation;
            }

            // Save file.
            $saved = $this->save_file( $file_data, $form_id );
            if ( is_wp_error( $saved ) ) {
                return $saved;
            }

            $saved['field_name'] = $field_name;
            $processed_files[]   = $saved;
        }

        return $processed_files;
    }

    /**
     * Normalize the $_FILES array for consistent processing.
     *
     * @since 1.1.0
     * @param array $files The $_FILES array.
     * @return array Normalized array of file data.
     */
    private function normalize_files_array( $files ) {
        $normalized = array();

        foreach ( $files as $field_name => $file_data ) {
            // Handle multiple file upload (name is array).
            if ( is_array( $file_data['name'] ) ) {
                foreach ( $file_data['name'] as $index => $name ) {
                    if ( ! empty( $name ) ) {
                        $normalized[ $field_name . '_' . $index ] = array(
                            'name'     => $name,
                            'type'     => $file_data['type'][ $index ],
                            'tmp_name' => $file_data['tmp_name'][ $index ],
                            'error'    => $file_data['error'][ $index ],
                            'size'     => $file_data['size'][ $index ],
                        );
                    }
                }
            } else {
                // Single file upload.
                if ( ! empty( $file_data['name'] ) ) {
                    $normalized[ $field_name ] = $file_data;
                }
            }
        }

        return $normalized;
    }

    /**
     * Validate a single file upload.
     *
     * @since 1.1.0
     * @param array $file File data from $_FILES.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    public function validate_file( $file ) {
        // Check for upload errors.
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error(
                'upload_error',
                $this->get_upload_error_message( $file['error'] ),
                array( 'status' => 400 )
            );
        }

        // Check file size (10MB max).
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: Maximum file size */
                    __( 'File exceeds maximum size of %s.', 'headless-forms' ),
                    size_format( self::MAX_FILE_SIZE )
                ),
                array( 'status' => 400 )
            );
        }

        // Verify MIME type.
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        $allowed_types = $this->get_allowed_types();

        if ( ! isset( $allowed_types[ $mime_type ] ) ) {
            return new \WP_Error(
                'invalid_file_type',
                sprintf(
                    /* translators: %s: File type */
                    __( 'File type "%s" is not allowed.', 'headless-forms' ),
                    $mime_type
                ),
                array( 'status' => 400 )
            );
        }

        // Check if it's a real uploaded file.
        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new \WP_Error(
                'invalid_upload',
                __( 'Invalid file upload.', 'headless-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Save a file to the uploads directory.
     *
     * @since 1.1.0
     * @param array $file    File data from $_FILES.
     * @param int   $form_id The form ID.
     * @return array|WP_Error Saved file data or error.
     */
    public function save_file( $file, $form_id ) {
        // Create directory structure: uploads/headless-forms/YYYY/MM/.
        $year_month_dir = gmdate( 'Y' ) . '/' . gmdate( 'm' );
        $target_dir     = trailingslashit( $this->upload_dir ) . $year_month_dir;

        // Create directory if it doesn't exist.
        if ( ! $this->ensure_directory( $target_dir ) ) {
            return new \WP_Error(
                'directory_error',
                __( 'Failed to create upload directory.', 'headless-forms' ),
                array( 'status' => 500 )
            );
        }

        // Generate unique filename.
        $extension   = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $stored_name = wp_generate_uuid4() . '.' . strtolower( $extension );
        $target_path = trailingslashit( $target_dir ) . $stored_name;

        // Move uploaded file.
        if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
            return new \WP_Error(
                'move_failed',
                __( 'Failed to save uploaded file.', 'headless-forms' ),
                array( 'status' => 500 )
            );
        }

        // Set proper permissions.
        chmod( $target_path, 0644 );

        // Get actual MIME type.
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $target_path );
        finfo_close( $finfo );

        return array(
            'original_name' => sanitize_file_name( $file['name'] ),
            'stored_name'   => $stored_name,
            'file_path'     => $year_month_dir . '/' . $stored_name,
            'file_size'     => $file['size'],
            'mime_type'     => $mime_type,
        );
    }

    /**
     * Ensure upload directory exists with proper security.
     *
     * @since 1.1.0
     * @param string $dir Directory path.
     * @return bool True if directory exists or was created.
     */
    private function ensure_directory( $dir ) {
        if ( ! file_exists( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return false;
            }
        }

        // Create .htaccess to block direct access.
        $htaccess_path = trailingslashit( $this->upload_dir ) . '.htaccess';
        if ( ! file_exists( $htaccess_path ) ) {
            $htaccess_content = "# Headless Forms - Block direct file access\n";
            $htaccess_content .= "Order deny,allow\n";
            $htaccess_content .= "Deny from all\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $htaccess_path, $htaccess_content );
        }

        // Create index.php to prevent directory listing.
        $index_path = trailingslashit( $dir ) . 'index.php';
        if ( ! file_exists( $index_path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $index_path, '<?php // Silence is golden.' );
        }

        return true;
    }

    /**
     * Get allowed MIME types.
     *
     * @since 1.1.0
     * @return array Allowed MIME types.
     */
    public function get_allowed_types() {
        /**
         * Filter allowed file upload types.
         *
         * @since 1.1.0
         * @param array $types Allowed MIME types (mime_type => extension).
         */
        return apply_filters( 'headless_forms_allowed_file_types', $this->default_allowed_types );
    }

    /**
     * Get human-readable list of allowed extensions.
     *
     * @since 1.1.0
     * @return string Comma-separated list of extensions.
     */
    public function get_allowed_extensions_string() {
        $types      = $this->get_allowed_types();
        $extensions = array_unique( array_values( $types ) );
        return implode( ', ', $extensions );
    }

    /**
     * Get upload error message.
     *
     * @since 1.1.0
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message( $error_code ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'headless-forms' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'headless-forms' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'headless-forms' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'headless-forms' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server missing temporary folder.', 'headless-forms' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'headless-forms' ),
            UPLOAD_ERR_EXTENSION  => __( 'File upload blocked by server.', 'headless-forms' ),
        );

        return isset( $messages[ $error_code ] )
            ? $messages[ $error_code ]
            : __( 'Unknown upload error.', 'headless-forms' );
    }

    /**
     * Get full path to an uploaded file.
     *
     * @since 1.1.0
     * @param string $file_path Relative file path.
     * @return string Full file path.
     */
    public function get_full_path( $file_path ) {
        return trailingslashit( $this->upload_dir ) . $file_path;
    }

    /**
     * Delete files for a submission.
     *
     * @since 1.1.0
     * @param array $file_paths Array of relative file paths.
     * @return bool True if all files deleted.
     */
    public function delete_files( $file_paths ) {
        $success = true;

        foreach ( $file_paths as $path ) {
            $full_path = $this->get_full_path( $path );
            if ( file_exists( $full_path ) ) {
                if ( ! wp_delete_file( $full_path ) ) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Get file contents for email attachment.
     *
     * @since 1.1.0
     * @param string $file_path Relative file path.
     * @return string|false File contents or false on failure.
     */
    public function get_file_contents( $file_path ) {
        $full_path = $this->get_full_path( $file_path );

        if ( ! file_exists( $full_path ) ) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return file_get_contents( $full_path );
    }

    /**
     * Get base64 encoded file contents for API attachments.
     *
     * @since 1.1.0
     * @param string $file_path Relative file path.
     * @return string|false Base64 encoded contents or false.
     */
    public function get_file_base64( $file_path ) {
        $contents = $this->get_file_contents( $file_path );

        if ( false === $contents ) {
            return false;
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return base64_encode( $contents );
    }

    /**
     * Clean up old upload directories (for data retention).
     *
     * @since 1.1.0
     * @return void
     */
    public function cleanup_empty_directories() {
        $this->remove_empty_directories( $this->upload_dir );
    }

    /**
     * Recursively remove empty directories.
     *
     * @since 1.1.0
     * @param string $dir Directory path.
     * @return void
     */
    private function remove_empty_directories( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( scandir( $dir ), array( '.', '..', '.htaccess', 'index.php' ) );

        foreach ( $files as $file ) {
            $path = trailingslashit( $dir ) . $file;
            if ( is_dir( $path ) ) {
                $this->remove_empty_directories( $path );
            }
        }

        // Remove directory if empty (except base and containing index/htaccess).
        if ( $dir !== $this->upload_dir ) {
            $remaining = array_diff( scandir( $dir ), array( '.', '..', 'index.php' ) );
            if ( empty( $remaining ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
                rmdir( $dir );
            }
        }
    }

    /**
     * Get upload directory path.
     *
     * @since 1.1.0
     * @return string Upload directory path.
     */
    public function get_upload_dir() {
        return $this->upload_dir;
    }
}
