<?php
/**
 * Email Logs View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-email-alt"></span>
        <?php esc_html_e( 'Email Logs', 'headless-forms' ); ?>
    </h2>
</div>

<div class="hf-card">
    <form method="get">
        <input type="hidden" name="page" value="headless-forms">
        <input type="hidden" name="view" value="email-logs">
        <?php $logs_table->display(); ?>
    </form>
</div>
