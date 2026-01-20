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
?><div class="hf-page-title">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=submissions' ) ); ?>" class="hf-button hf-button-secondary" style="padding: 6px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <span><?php echo esc_html( $submission->form_name ); ?> <span style="color:var(--hf-text-tertiary); font-weight:400;">#<?php echo esc_html( $submission->id ); ?></span></span>
    </div>
</div>

<div class="hf-submission-detail">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Submission Data -->
        <div class="hf-card hf-submission-data">
            <h2><?php esc_html_e( 'Submission Data', 'headless-forms' ); ?></h2>
            
            <table class="hf-table">
                <tbody>
                    <?php if ( is_array( $submission->submission_data ) ) : ?>
                        <?php foreach ( $submission->submission_data as $key => $value ) : ?>
                            <tr>
                                <th style="width: 30%;"><?php echo esc_html( ucfirst( str_replace( array( '_', '-' ), ' ', $key ) ) ); ?></th>
                                <td>
                                    <?php
                                    if ( is_array( $value ) ) {
                                        echo esc_html( implode( ', ', $value ) );
                                    } elseif ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                                        echo '<a href="mailto:' . esc_attr( $value ) . '" style="color:var(--hf-brand);">' . esc_html( $value ) . '</a>';
                                    } elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                                        echo '<a href="' . esc_url( $value ) . '" target="_blank" style="color:var(--hf-brand);">' . esc_html( $value ) . '</a>';
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

        <?php if ( ! empty( $submission->uploaded_files ) ) : ?>
            <div class="hf-card hf-submission-uploads" style="margin-top: 24px;">
                <h2><?php esc_html_e( 'Uploaded Files', 'headless-forms' ); ?></h2>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ( $submission->uploaded_files as $file ) : ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="dashicons dashicons-media-default" style="color: #64748b;"></span>
                                <div>
                                    <div style="font-weight: 500; font-size: 14px;"><?php echo esc_html( $file['file_name'] ); ?></div>
                                    <div style="font-size: 12px; color: #94a3b8;"><?php echo esc_html( round( $file['file_size'] / 1024, 2 ) ); ?> KB</div>
                                </div>
                            </div>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=headless_forms_download&file_id=' . $file['id'] ), 'download_file_' . $file['id'] ) ); ?>" class="hf-button hf-button-small hf-button-secondary">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Download', 'headless-forms' ); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Actions -->
            <div class="hf-card hf-submission-actions">
                <h2><?php esc_html_e( 'Actions', 'headless-forms' ); ?></h2>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if ( $submission->submitter_email ) : ?>
                        <a href="mailto:<?php echo esc_attr( $submission->submitter_email ); ?>" class="hf-button hf-button-secondary" style="justify-content: center;">
                            <span class="dashicons dashicons-email" style="margin-top:2px;"></span>
                            <?php esc_html_e( 'Reply via Email', 'headless-forms' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=headless-forms&view=submissions&action=mark_spam&submission_id=' . $submission->id ), 'mark_spam_' . $submission->id ) ); ?>" class="hf-button hf-button-secondary" style="justify-content: center;">
                        <span class="dashicons dashicons-flag" style="margin-top:2px;"></span>
                        <?php esc_html_e( 'Mark as Spam', 'headless-forms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=headless-forms&view=submissions&action=delete&submission_id=' . $submission->id ), 'delete_submission_' . $submission->id ) ); ?>" class="hf-button hf-button-secondary" style="color:#ef4444; border-color: #fee2e2; background: #fef2f2; justify-content: center;">
                        <span class="dashicons dashicons-trash" style="margin-top:2px;"></span>
                        <?php esc_html_e( 'Delete Submission', 'headless-forms' ); ?>
                    </a>
                </div>
            </div>

            <!-- Meta Data -->
            <div class="hf-card hf-submission-meta">
                <h2><?php esc_html_e( 'Meta Information', 'headless-forms' ); ?></h2>
                
                <div style="font-size: 13px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color:var(--hf-text-secondary);"><?php esc_html_e( 'Status', 'headless-forms' ); ?></span>
                        <span class="hf-status hf-status-<?php echo esc_attr( $submission->status ); ?>" style="font-weight:600;">
                            <?php echo esc_html( ucfirst( $submission->status ) ); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color:var(--hf-text-secondary);"><?php esc_html_e( 'Submitted', 'headless-forms' ); ?></span>
                        <span style="text-align:right;"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $submission->created_at ) ) ); ?></span>
                    </div>
                    <?php if ( $submission->ip_address ) : ?>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color:var(--hf-text-secondary);"><?php esc_html_e( 'IP Address', 'headless-forms' ); ?></span>
                            <span><?php echo esc_html( $submission->ip_address ); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div style="border-top: 1px solid var(--hf-border); margin: 4px 0;"></div>

                    <div style="display: flex; justify-content: space-between;">
                        <span style="color:var(--hf-text-secondary);"><?php esc_html_e( 'Email Sent', 'headless-forms' ); ?></span>
                        <span><?php echo $submission->email_sent ? 'Yes' : 'No'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
