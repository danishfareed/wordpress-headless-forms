<?php
/**
 * Form Edit View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$is_new = empty( $form );
$email_settings = $form ? json_decode( $form->email_settings, true ) : array();
$auto_settings = $form ? json_decode( $form->auto_responder_settings, true ) : array();

// Fetch webhooks if form exists.
$webhooks = array();
if ( $form ) {
    global $wpdb;
    $webhooks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}headless_webhooks WHERE form_id = %d", $form->id ) );
}
?>

<div class="hf-page-title">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms&view=forms' ) ); ?>" class="hf-button hf-button-secondary" style="padding: 6px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
        <span><?php echo $is_new ? esc_html__( 'Create New Form', 'headless-forms' ) : esc_html__( 'Edit Form', 'headless-forms' ); ?></span>
    </div>
    
    <?php if ( ! $is_new ) : ?>
    <div class="hf-tabs" style="margin-left: auto;">
        <a href="#" class="hf-tab-link active" data-tab="settings"><?php esc_html_e( 'Settings', 'headless-forms' ); ?></a>
        <a href="#" class="hf-tab-link" data-tab="integrations"><?php esc_html_e( 'Integrations', 'headless-forms' ); ?></a>
    </div>
    <?php endif; ?>
</div>

<div id="tab-settings" class="hf-tab-content active">

<form method="post" action="" class="hf-form-edit">
    <?php wp_nonce_field( 'headless_forms_save_form', 'headless_forms_nonce' ); ?>
    <input type="hidden" name="form_id" value="<?php echo esc_attr( $form ? $form->id : '' ); ?>">

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        
        <!-- Left Column: Main Settings -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Basic Info -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'General Information', 'headless-forms' ); ?></h2>
                
                <div class="hf-form-group">
                    <label class="hf-label" for="form_name"><?php esc_html_e( 'Form Name', 'headless-forms' ); ?> <span style="color:#ef4444">*</span></label>
                    <input type="text" id="form_name" name="form_name" placeholder="e.g. Contact Us"
                           value="<?php echo esc_attr( $form ? $form->form_name : '' ); ?>" required>
                </div>

                <div class="hf-form-group">
                    <label class="hf-label" for="form_description"><?php esc_html_e( 'Description', 'headless-forms' ); ?></label>
                    <textarea id="form_description" name="form_description" rows="3" placeholder="Internal notes about this form..."><?php echo esc_textarea( $form ? $form->form_description : '' ); ?></textarea>
                </div>

                <div class="hf-form-group">
                    <label class="hf-label" for="form_slug"><?php esc_html_e( 'API Endpoint Slug', 'headless-forms' ); ?></label>
                    <input type="text" id="form_slug" name="form_slug" 
                           value="<?php echo esc_attr( $form ? $form->form_slug : '' ); ?>"
                           placeholder="Auto-generated if left empty">
                    <?php if ( $form ) : ?>
                        <div class="hf-form-hint">
                            Endpoint: <code style="background:#f1f5f9; padding:2px 4px; border-radius:4px;"><?php echo esc_html( rest_url( 'headless-forms/v1/submit/' . $form->form_slug ) ); ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Email Notifications', 'headless-forms' ); ?></h2>
                
                <div class="hf-form-group">
                    <label class="hf-label"><?php esc_html_e( 'Status', 'headless-forms' ); ?></label>
                    <div class="hf-checkbox-group">
                        <input type="checkbox" id="notification_enabled" name="notification_enabled" value="1" 
                               <?php checked( $form ? $form->notification_enabled : 1 ); ?>>
                        <label for="notification_enabled" class="hf-checkbox-label">
                            <?php esc_html_e( 'Send me an email when this form is submitted', 'headless-forms' ); ?>
                        </label>
                    </div>
                </div>

                <div class="hf-form-group">
                    <label class="hf-label" for="email_recipients"><?php esc_html_e( 'Recipient(s)', 'headless-forms' ); ?></label>
                    <input type="text" id="email_recipients" name="email_recipients" 
                           value="<?php echo esc_attr( isset( $email_settings['recipients'] ) ? $email_settings['recipients'] : get_option( 'admin_email' ) ); ?>">
                    <div class="hf-form-hint"><?php esc_html_e( 'Separate multiple emails with commas.', 'headless-forms' ); ?></div>
                </div>

                <div class="hf-form-group">
                    <label class="hf-label" for="email_subject"><?php esc_html_e( 'Email Subject', 'headless-forms' ); ?></label>
                    <input type="text" id="email_subject" name="email_subject" 
                           value="<?php echo esc_attr( isset( $email_settings['subject'] ) ? $email_settings['subject'] : '' ); ?>"
                           placeholder="New submission from {{form_name}}">
                    <div class="hf-form-hint"><?php esc_html_e( 'You can use {{field_name}} dynamic tags.', 'headless-forms' ); ?></div>
                </div>
            </div>

            <!-- Auto Responder -->
            <div class="hf-card">
                 <h2><?php esc_html_e( 'Automated Reply', 'headless-forms' ); ?></h2>
                 
                 <div class="hf-form-group">
                    <div class="hf-checkbox-group">
                        <input type="checkbox" id="auto_responder_enabled" name="auto_responder_enabled" value="1" 
                               <?php checked( $form ? $form->auto_responder_enabled : 0 ); ?>>
                        <label for="auto_responder_enabled" class="hf-checkbox-label">
                            <?php esc_html_e( 'Send a confirmation email to the user', 'headless-forms' ); ?>
                        </label>
                    </div>
                </div>

                <div id="auto-responder-settings" style="<?php echo ( $form && $form->auto_responder_enabled ) ? '' : 'display:none;'; ?> margin-top: 16px;">
                    <div class="hf-form-group">
                        <label class="hf-label" for="auto_responder_subject"><?php esc_html_e( 'Subject Line', 'headless-forms' ); ?></label>
                        <input type="text" id="auto_responder_subject" name="auto_responder_subject" 
                               value="<?php echo esc_attr( isset( $auto_settings['subject'] ) ? $auto_settings['subject'] : '' ); ?>"
                               placeholder="We received your message">
                    </div>
                    
                    <div class="hf-form-group">
                        <label class="hf-label" for="auto_responder_message"><?php esc_html_e( 'Message Body', 'headless-forms' ); ?></label>
                        <?php
                        wp_editor(
                            isset( $auto_settings['message'] ) ? $auto_settings['message'] : '',
                            'auto_responder_message',
                            array(
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => false,
                                'editor_class' => 'hf-editor'
                            )
                        );
                        ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Actions & Configuration -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Publish Actions -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Publish', 'headless-forms' ); ?></h2>
                
                <div class="hf-form-group">
                    <label class="hf-label" for="status"><?php esc_html_e( 'Form Status', 'headless-forms' ); ?></label>
                    <select id="status" name="status">
                        <option value="active" <?php selected( $form ? $form->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'headless-forms' ); ?></option>
                        <option value="inactive" <?php selected( $form ? $form->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'headless-forms' ); ?></option>
                    </select>
                </div>

                <div style="border-top: 1px solid var(--hf-border); margin: 16px -24px 16px -24px;"></div>

                <button type="submit" name="headless_forms_save_form" class="hf-button hf-button-primary" style="width: 100%; justify-content: center;">
                    <?php echo $is_new ? esc_html__( 'Create Form', 'headless-forms' ) : esc_html__( 'Save Changes', 'headless-forms' ); ?>
                </button>
            </div>

            <!-- Post-Submission -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'After Submission', 'headless-forms' ); ?></h2>
                
                <div class="hf-form-group">
                    <label class="hf-label" for="success_message"><?php esc_html_e( 'Success Message', 'headless-forms' ); ?></label>
                    <textarea id="success_message" name="success_message" rows="2" placeholder="Thank you..."><?php echo esc_textarea( $form ? $form->success_message : '' ); ?></textarea>
                    <div class="hf-form-hint"><?php esc_html_e( 'Returned in JSON response.', 'headless-forms' ); ?></div>
                </div>

                <div class="hf-form-group">
                    <label class="hf-label" for="redirect_url"><?php esc_html_e( 'Redirect URL (Optional)', 'headless-forms' ); ?></label>
                    <input type="url" id="redirect_url" name="redirect_url" placeholder="https://"
                           value="<?php echo esc_url( $form ? $form->redirect_url : '' ); ?>">
                </div>
            </div>

        </div>
    </div>
</form>
</div><!-- End Settings Tab -->

<div id="tab-integrations" class="hf-tab-content">
    <div class="hf-card">
        <div style="display:flex; justify-content:space-between; align-items:center; mb-4">
            <h2><?php esc_html_e( 'Active Integrations', 'headless-forms' ); ?></h2>
            <button type="button" class="hf-button hf-button-primary" id="hf-add-integration-btn">
                <?php esc_html_e( 'Add Integration', 'headless-forms' ); ?>
            </button>
        </div>
        
        <div id="hf-integrations-list">
            <?php if ( empty( $webhooks ) ) : ?>
                <div style="text-align:center; padding: 40px; color: #64748b;" id="hf-no-integrations">
                    <p><?php esc_html_e( 'Connect your form to Slack, Google Sheets, Zapier, and more.', 'headless-forms' ); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ( $webhooks as $webhook ) : ?>
                    <div class="hf-integration-item" data-id="<?php echo esc_attr( $webhook->id ); ?>" style="border:1px solid #e2e8f0; padding:16px; border-radius:6px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="display:block; font-size:14px;"><?php echo esc_html( $webhook->webhook_name ); ?></strong>
                            <small style="color:#64748b;"><?php echo esc_html( $webhook->webhook_url ); ?></small>
                            <div style="font-size:11px; margin-top:4px;">
                                <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo esc_html( $webhook->trigger_event ); ?></span>
                                <span style="margin-left:8px; color: <?php echo $webhook->last_status === 'success' ? '#22c55e' : ( $webhook->last_status === 'failed' ? '#ef4444' : '#64748b' ); ?>">
                                    <?php echo $webhook->last_status ? ucfirst( $webhook->last_status ) : 'Never triggered'; ?>
                                </span>
                            </div>
                        </div>
                        <button type="button" class="hf-button hf-button-danger hf-delete-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>" style="padding:4px 8px; font-size:12px;">Delete</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Integration Modal -->
<div id="hf-integration-modal" class="hf-modal" style="display:none;">
    <div class="hf-modal-content">
        <div class="hf-modal-header">
            <h3><?php esc_html_e( 'Add Integration', 'headless-forms' ); ?></h3>
            <span class="hf-close-modal">&times;</span>
        </div>
        <div class="hf-modal-body">
            <div class="hf-form-group">
                <label class="hf-label"><?php esc_html_e( 'Integration Type', 'headless-forms' ); ?></label>
                <div class="hf-grid-options">
                    <label class="hf-grid-option selected">
                        <input type="radio" name="integration_preset" value="custom" checked>
                        <span><?php esc_html_e( 'Custom Webhook', 'headless-forms' ); ?></span>
                    </label>
                    <label class="hf-grid-option">
                        <input type="radio" name="integration_preset" value="slack">
                        <span>Slack</span>
                    </label>
                    <label class="hf-grid-option">
                        <input type="radio" name="integration_preset" value="zapier">
                        <span>Zapier</span>
                    </label>
                    <label class="hf-grid-option">
                        <input type="radio" name="integration_preset" value="sheets">
                        <span>Google Sheets</span>
                    </label>
                </div>
            </div>

            <div class="hf-form-group">
                <label class="hf-label" for="int_name"><?php esc_html_e( 'Name', 'headless-forms' ); ?></label>
                <input type="text" id="int_name" placeholder="My Integration">
            </div>

            <div class="hf-form-group">
                <label class="hf-label" for="int_url"><?php esc_html_e( 'Webhook URL', 'headless-forms' ); ?></label>
                <input type="url" id="int_url" placeholder="https://hooks.slack.com/...">
            </div>

            <div class="hf-form-group" id="int-payload-group" style="display:none;">
                <label class="hf-label" for="int_payload"><?php esc_html_e( 'Custom JSON Payload (Optional)', 'headless-forms' ); ?></label>
                <textarea id="int_payload" rows="4" style="font-family:monospace; font-size:12px;"></textarea>
                <div class="hf-form-hint"><?php esc_html_e( 'Leave empty for default submission data.', 'headless-forms' ); ?></div>
            </div>

            <input type="hidden" id="int_form_id" value="<?php echo esc_attr( $form ? $form->id : '' ); ?>">
        </div>
        <div class="hf-modal-footer">
            <button type="button" class="hf-button hf-button-secondary hf-close-modal"><?php esc_html_e( 'Cancel', 'headless-forms' ); ?></button>
            <button type="button" class="hf-button hf-button-primary" id="hf-save-integration"><?php esc_html_e( 'Save Integration', 'headless-forms' ); ?></button>
        </div>
    </div>
</div>

<style>
.hf-tabs { display: flex; gap: 20px; border-bottom: 2px solid transparent; }
.hf-tab-link { text-decoration: none; color: #64748b; padding: 8px 12px; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; }
.hf-tab-link.active { color: #000; border-bottom-color: #000; }
.hf-tab-content { display: none; }
.hf-tab-content.active { display: block; }
.hf-grid-options { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 8px; }
.hf-grid-option { border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
.hf-grid-option:hover { border-color: #cbd5e1; background: #f8fafc; }
.hf-grid-option input { margin: 0; }
.hf-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.hf-modal-content { background: #fff; width: 500px; max-width: 90%; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; }
.hf-modal-header { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.hf-modal-header h3 { margin: 0; font-size: 18px; }
.hf-close-modal { cursor: pointer; font-size: 20px; line-height: 1; }
.hf-modal-body { padding: 24px; }
.hf-modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const autoResponderCheck = document.getElementById('auto_responder_enabled');
    const autoResponderSettings = document.getElementById('auto-responder-settings');
    
    if(autoResponderCheck && autoResponderSettings) {
        autoResponderCheck.addEventListener('change', function() {
            autoResponderSettings.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
