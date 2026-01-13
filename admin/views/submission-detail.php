<?php
/**
 * Submission Detail View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! $submission ) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Submission not found.', 'headless-forms' ) . '</p></div></div>';
    return;
}
?>
<div class="wrap hf-wrap">
    <h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-submissions' ) ); ?>" class="hf-back-link">
            ← <?php esc_html_e( 'Back to Submissions', 'headless-forms' ); ?>
        </a>
    </h1>

    <div class="hf-submission-detail">
        <div class="hf-detail-grid">
            <!-- Submission Data -->
            <div class="hf-card hf-submission-data">
                <h2><?php echo esc_html( $submission->form_name ); ?> — #<?php echo esc_html( $submission->id ); ?></h2>
                
                <table class="hf-data-table">
                    <tbody>
                        <?php if ( is_array( $submission->submission_data ) ) : ?>
                            <?php foreach ( $submission->submission_data as $key => $value ) : ?>
                                <tr>
                                    <th><?php echo esc_html( ucfirst( str_replace( array( '_', '-' ), ' ', $key ) ) ); ?></th>
                                    <td>
                                        <?php
                                        if ( is_array( $value ) ) {
                                            echo esc_html( implode( ', ', $value ) );
                                        } elseif ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                                            echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
                                        } elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                                            echo '<a href="' . esc_url( $value ) . '" target="_blank">' . esc_html( $value ) . '</a>';
                                        } else {
                                            echo nl2br( esc_html( $value ) );
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Meta Data -->
            <div class="hf-card hf-submission-meta">
                <h2><?php esc_html_e( 'Submission Info', 'headless-forms' ); ?></h2>
                
                <ul class="hf-meta-list">
                    <li>
                        <strong><?php esc_html_e( 'Status:', 'headless-forms' ); ?></strong>
                        <span class="hf-status hf-status-<?php echo esc_attr( $submission->status ); ?>">
                            <?php echo esc_html( ucfirst( $submission->status ) ); ?>
                        </span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Submitted:', 'headless-forms' ); ?></strong>
                        <?php echo esc_html( date_i18n( 'F j, Y \a\t g:i a', strtotime( $submission->created_at ) ) ); ?>
                    </li>
                    <?php if ( $submission->ip_address ) : ?>
                        <li>
                            <strong><?php esc_html_e( 'IP Address:', 'headless-forms' ); ?></strong>
                            <?php echo esc_html( $submission->ip_address ); ?>
                        </li>
                    <?php endif; ?>
                    <?php if ( $submission->referrer_url ) : ?>
                        <li>
                            <strong><?php esc_html_e( 'Referrer:', 'headless-forms' ); ?></strong>
                            <a href="<?php echo esc_url( $submission->referrer_url ); ?>" target="_blank">
                                <?php echo esc_html( wp_parse_url( $submission->referrer_url, PHP_URL_HOST ) ); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <strong><?php esc_html_e( 'Email Sent:', 'headless-forms' ); ?></strong>
                        <?php echo $submission->email_sent ? '✓ ' . esc_html__( 'Yes', 'headless-forms' ) : '✗ ' . esc_html__( 'No', 'headless-forms' ); ?>
                    </li>
                </ul>

                <?php if ( $submission->user_agent ) : ?>
                    <h3><?php esc_html_e( 'User Agent', 'headless-forms' ); ?></h3>
                    <p class="hf-user-agent"><?php echo esc_html( $submission->user_agent ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="hf-card hf-submission-actions">
            <h2><?php esc_html_e( 'Actions', 'headless-forms' ); ?></h2>
            <div class="hf-action-buttons">
                <?php if ( $submission->submitter_email ) : ?>
                    <a href="mailto:<?php echo esc_attr( $submission->submitter_email ); ?>" class="button">
                        <span class="dashicons dashicons-email"></span>
                        <?php esc_html_e( 'Reply via Email', 'headless-forms' ); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=headless-forms-submissions&action=mark_spam&submission_id=' . $submission->id ), 'mark_spam_' . $submission->id ) ); ?>" class="button">
                    <?php esc_html_e( 'Mark as Spam', 'headless-forms' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=headless-forms-submissions&action=delete&submission_id=' . $submission->id ), 'delete_submission_' . $submission->id ) ); ?>" class="button hf-delete-link" style="color:#b32d2e;">
                    <?php esc_html_e( 'Delete', 'headless-forms' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>
