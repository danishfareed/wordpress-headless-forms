<?php
/**
 * Submissions List View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hf-wrap">
    <h1><?php esc_html_e( 'Submissions', 'headless-forms' ); ?></h1>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="headless-forms-submissions">
        <?php $submissions_table->display(); ?>
    </form>
</div>
