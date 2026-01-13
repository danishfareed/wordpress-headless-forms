<?php
/**
 * Main Admin Layout - Uses WordPress native admin structure.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Use $current_view from render_app() if set, otherwise get from query parameter.
if ( ! isset( $current_view ) ) {
    $current_view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'dashboard';
}

// Map views to files.
$view_files = array(
    'dashboard'         => 'dashboard.php',
    'forms'             => 'forms-list.php',
    'new-form'          => 'form-edit.php',
    'edit-form'         => 'form-edit.php',
    'submissions'       => 'submissions-list.php',
    'submission-detail' => 'submission-detail.php',
    'settings'          => 'settings.php',
    'how-to'            => 'how-to-use.php',
    'email-logs'        => 'email-logs.php',
);

$file_to_include = isset( $view_files[ $current_view ] ) ? $view_files[ $current_view ] : 'dashboard.php';
?>

<div class="wrap hf-wrap">
    <!-- Page Navigation Tabs -->
    <div class="hf-admin-header">
        <div class="hf-admin-title">
            <span class="dashicons dashicons-feedback"></span>
            <h1><?php esc_html_e( 'Headless Forms', 'headless-forms' ); ?></h1>
        </div>
        <nav class="hf-admin-tabs">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=dashboard' ) ); ?>" 
               class="hf-tab <?php echo $current_view === 'dashboard' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-dashboard"></span>
                <?php esc_html_e( 'Dashboard', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=forms' ) ); ?>" 
               class="hf-tab <?php echo in_array( $current_view, array( 'forms', 'new-form', 'edit-form' ), true ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Forms', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=submissions' ) ); ?>" 
               class="hf-tab <?php echo in_array( $current_view, array( 'submissions', 'submission-detail' ), true ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e( 'Submissions', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=email-logs' ) ); ?>" 
               class="hf-tab <?php echo $current_view === 'email-logs' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e( 'Email Logs', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=settings' ) ); ?>" 
               class="hf-tab <?php echo $current_view === 'settings' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'Settings', 'headless-forms' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=how-to' ) ); ?>" 
               class="hf-tab <?php echo $current_view === 'how-to' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-book"></span>
                <?php esc_html_e( 'Documentation', 'headless-forms' ); ?>
            </a>
        </nav>
    </div>

    <!-- Main Content Area -->
    <div class="hf-admin-content">
        <?php
        $view_path = HEADLESS_FORMS_PATH . 'admin/views/' . $file_to_include;
        if ( file_exists( $view_path ) ) {
            include $view_path;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'View not found.', 'headless-forms' ) . '</p></div>';
        }
        ?>
    </div>

    <!-- Footer -->
    <div class="hf-admin-footer">
        <p>
            <?php esc_html_e( 'Headless Forms v1.0.0', 'headless-forms' ); ?> | 
            <?php esc_html_e( 'Created by', 'headless-forms' ); ?> <a href="https://codewithdanish.dev" target="_blank">Danish Mohammed</a> | 
            <a href="https://wordpress.org/support/plugin/headless-forms/" target="_blank"><?php esc_html_e( 'Support', 'headless-forms' ); ?></a>
        </p>
    </div>
</div>
