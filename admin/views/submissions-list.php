<?php
/**
 * Submissions List View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e( 'Submissions', 'headless-forms' ); ?>
    </h2>
</div>

<div class="hf-card">
    <form method="get">
        <input type="hidden" name="page" value="headless-forms">
        <input type="hidden" name="view" value="submissions">
        <div class="hf-table-container">
            <?php $submissions_table->display(); ?>
        </div>
    </form>
</div>
