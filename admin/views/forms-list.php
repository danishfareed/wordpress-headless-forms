<?php
/**
 * Forms List View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-clipboard"></span>
        <?php esc_html_e( 'Forms', 'headless-forms' ); ?>
    </h2>
    <div class="hf-page-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=new-form' ) ); ?>" class="hf-button hf-button-primary">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Create Form', 'headless-forms' ); ?>
        </a>
    </div>
</div>

<?php if ( isset( $_GET['deleted'] ) ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Form deleted successfully.', 'headless-forms' ); ?></p>
    </div>
<?php endif; ?>

<?php if ( isset( $_GET['duplicated'] ) ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Form duplicated successfully.', 'headless-forms' ); ?></p>
    </div>
<?php endif; ?>

<div class="hf-card">
    <form method="get">
        <input type="hidden" name="page" value="headless-forms">
        <input type="hidden" name="view" value="forms">
        <?php $forms_table->search_box( __( 'Search Forms', 'headless-forms' ), 'form' ); ?>
        <div class="hf-table-container">
            <?php $forms_table->display(); ?>
        </div>
    </form>
</div>
