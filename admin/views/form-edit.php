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

<?php if ( isset( $_GET['saved'] ) ) : ?>
    <div class="notice notice-success is-dismissible" style="margin-left: 0; margin-top: 16px;">
        <p><?php esc_html_e( 'Form saved successfully.', 'headless-forms' ); ?></p>
    </div>
<?php endif; ?>

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

            <!-- File Uploads -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'File Uploads', 'headless-forms' ); ?></h2>
                
                <div class="hf-form-group">
                    <div class="hf-checkbox-group">
                        <input type="checkbox" id="file_uploads_enabled" name="file_uploads_enabled" value="1" 
                               <?php checked( $form ? $form->file_uploads_enabled : 1 ); ?>>
                        <label for="file_uploads_enabled" class="hf-checkbox-label">
                            <?php esc_html_e( 'Enable file uploads for this form', 'headless-forms' ); ?>
                        </label>
                    </div>
                    <div class="hf-form-hint">
                        <?php esc_html_e( 'Allows users to submit files along with form data. Files are sent as attachments.', 'headless-forms' ); ?>
                    </div>
                </div>

                <div id="file-upload-settings" style="<?php echo ( $form ? ( $form->file_uploads_enabled ? '' : 'display:none;' ) : '' ); ?> margin-top: 16px;">
                    <div class="hf-form-group">
                        <label class="hf-label" for="max_file_uploads"><?php esc_html_e( 'Max Files per Submission', 'headless-forms' ); ?></label>
                        <input type="number" id="max_file_uploads" name="max_file_uploads" min="1" max="10" step="1"
                               value="<?php echo esc_attr( $form ? $form->max_file_uploads : 5 ); ?>">
                        <div class="hf-form-hint"><?php esc_html_e( 'Maximum number of files allowed in a single submission (max 10).', 'headless-forms' ); ?></div>
                    </div>

                    <div class="hf-form-group" style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <label class="hf-label" style="font-size: 12px; margin-bottom: 4px;"><?php esc_html_e( 'Attachment Info', 'headless-forms' ); ?></label>
                        <div style="font-size: 13px; color: #64748b;">
                            <p style="margin: 0 0 4px 0;"><strong><?php esc_html_e( 'Max Size:', 'headless-forms' ); ?></strong> 10MB <?php esc_html_e( 'per file', 'headless-forms' ); ?></p>
                            <p style="margin: 0;"><strong><?php esc_html_e( 'Allowed Types:', 'headless-forms' ); ?></strong> <?php esc_html_e( 'Images, PDFs, Documents (excluding .zip)', 'headless-forms' ); ?></p>
                        </div>
                    </div>
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

            <!-- Google Sheets Help -->
            <div id="hf-sheets-help" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; padding:16px; border-radius:8px; margin-top:16px;">
                <h4 style="margin:0 0 8px 0; color:#0369a1; font-size:14px;"><?php esc_html_e( 'How to connect Google Sheets:', 'headless-forms' ); ?></h4>
                <ol style="margin:0; padding-left:20px; font-size:12px; color:#0c4a6e;">
                    <li>Create a Google Sheet and name it.</li>
                    <li>Go to <strong>Extensions > Apps Script</strong>.</li>
                    <li>Paste the connection script and click <strong>Deploy > Web App</strong>.</li>
                    <li>Set access to <strong>"Anyone"</strong> and copy the URL.</li>
                </ol>
                <button type="button" class="hf-button hf-button-small hf-button-secondary hf-copy-btn" data-copy="hf-sheets-script" style="margin-top:12px;">
                    <span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Copy Connection Script', 'headless-forms' ); ?>
                </button>
                <textarea id="hf-sheets-script" style="display:none;">function doPost(e) {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  var data = JSON.parse(e.postData.contents);
  var row = [new Date()];
  
  // Flatten data into columns
  for (var key in data.data) {
    row.push(data.data[key]);
  }
  
  sheet.appendRow(row);
  return ContentService.createTextOutput(JSON.stringify({result: "success"})).setMimeType(ContentService.MimeType.JSON);
}</textarea>
            </div>

            <input type="hidden" id="int_form_id" value="<?php echo esc_attr( $form ? $form->id : '' ); ?>">
        </div>
        <div class="hf-modal-footer">
            <button type="button" class="hf-button hf-button-secondary hf-cancel-btn"><?php esc_html_e( 'Cancel', 'headless-forms' ); ?></button>
            <button type="button" class="hf-button hf-button-primary" id="hf-save-integration"><?php esc_html_e( 'Save Integration', 'headless-forms' ); ?></button>
        </div>
    </div>
</div>

<style>
/* Remove conflicting inline styles that are already in admin.css */
.hf-tabs { display: flex; gap: 20px; border-bottom: 2px solid transparent; }
.hf-tab-link { text-decoration: none; color: #64748b; padding: 8px 12px; border-bottom: 2px solid transparent; font-weight: 500; transition: all 0.2s; }
.hf-tab-link.active { color: #000; border-bottom-color: #000; }
.hf-tab-content { display: none; }
.hf-tab-content.active { display: block; }
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

    const fileUploadsCheck = document.getElementById('file_uploads_enabled');
    const fileUploadsSettings = document.getElementById('file-upload-settings');
    if(fileUploadsCheck && fileUploadsSettings) {
        fileUploadsCheck.addEventListener('change', function() {
            fileUploadsSettings.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
