<?php
/**
 * Forms List View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hf-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'headless-forms' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New', 'headless-forms' ); ?>
    </a>
    <hr class="wp-header-end">

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

    <form method="get">
        <input type="hidden" name="page" value="headless-forms-forms">
        <?php
        $forms_table->search_box( __( 'Search Forms', 'headless-forms' ), 'form' );
        $forms_table->display();
        ?>
    </form>
</div>
