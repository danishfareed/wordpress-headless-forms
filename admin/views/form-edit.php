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
?>
<div class="wrap hf-wrap">
    <h1><?php echo $is_new ? esc_html__( 'Add New Form', 'headless-forms' ) : esc_html__( 'Edit Form', 'headless-forms' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Form saved successfully.', 'headless-forms' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="hf-form-edit">
        <?php wp_nonce_field( 'headless_forms_save_form', 'headless_forms_nonce' ); ?>
        <input type="hidden" name="form_id" value="<?php echo esc_attr( $form ? $form->id : '' ); ?>">

        <div class="hf-form-grid">
            <!-- Main Settings -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Basic Settings', 'headless-forms' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="form_name"><?php esc_html_e( 'Form Name', 'headless-forms' ); ?> *</label></th>
                        <td>
                            <input type="text" id="form_name" name="form_name" class="regular-text" 
                                   value="<?php echo esc_attr( $form ? $form->form_name : '' ); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="form_slug"><?php esc_html_e( 'Form Slug', 'headless-forms' ); ?></label></th>
                        <td>
                            <input type="text" id="form_slug" name="form_slug" class="regular-text" 
                                   value="<?php echo esc_attr( $form ? $form->form_slug : '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Auto-generated from name', 'headless-forms' ); ?>">
                            <?php if ( $form ) : ?>
                                <p class="description">
                                    <?php esc_html_e( 'Endpoint:', 'headless-forms' ); ?>
                                    <code><?php echo esc_html( rest_url( 'headless-forms/v1/submit/' . $form->form_slug ) ); ?></code>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="form_description"><?php esc_html_e( 'Description', 'headless-forms' ); ?></label></th>
                        <td>
                            <textarea id="form_description" name="form_description" class="large-text" rows="3"><?php echo esc_textarea( $form ? $form->form_description : '' ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php esc_html_e( 'Status', 'headless-forms' ); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="active" <?php selected( $form ? $form->status : 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'headless-forms' ); ?></option>
                                <option value="inactive" <?php selected( $form ? $form->status : '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'headless-forms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Submission Settings -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Submission Settings', 'headless-forms' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><label for="success_message"><?php esc_html_e( 'Success Message', 'headless-forms' ); ?></label></th>
                        <td>
                            <textarea id="success_message" name="success_message" class="large-text" rows="2"><?php echo esc_textarea( $form ? $form->success_message : '' ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Message returned in API response on successful submission.', 'headless-forms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="redirect_url"><?php esc_html_e( 'Redirect URL', 'headless-forms' ); ?></label></th>
                        <td>
                            <input type="url" id="redirect_url" name="redirect_url" class="regular-text" 
                                   value="<?php echo esc_url( $form ? $form->redirect_url : '' ); ?>">
                            <p class="description"><?php esc_html_e( 'Optional. URL returned in response for frontend redirect.', 'headless-forms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Email Notifications -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Email Notifications', 'headless-forms' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Notifications', 'headless-forms' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="notification_enabled" value="1" 
                                       <?php checked( $form ? $form->notification_enabled : 1 ); ?>>
                                <?php esc_html_e( 'Send email notifications on new submissions', 'headless-forms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_recipients"><?php esc_html_e( 'Recipients', 'headless-forms' ); ?></label></th>
                        <td>
                            <textarea id="email_recipients" name="email_recipients" class="large-text" rows="2"><?php echo esc_textarea( isset( $email_settings['recipients'] ) ? $email_settings['recipients'] : get_option( 'admin_email' ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Comma-separated email addresses.', 'headless-forms' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_subject"><?php esc_html_e( 'Email Subject', 'headless-forms' ); ?></label></th>
                        <td>
                            <input type="text" id="email_subject" name="email_subject" class="regular-text" 
                                   value="<?php echo esc_attr( isset( $email_settings['subject'] ) ? $email_settings['subject'] : '' ); ?>"
                                   placeholder="New submission from {{form_name}}">
                            <p class="description"><?php esc_html_e( 'Use {{form_name}}, {{field_name}} placeholders.', 'headless-forms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Auto-Responder -->
            <div class="hf-card">
                <h2><?php esc_html_e( 'Auto-Responder', 'headless-forms' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Enable Auto-Responder', 'headless-forms' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_responder_enabled" value="1" 
                                       <?php checked( $form ? $form->auto_responder_enabled : 0 ); ?>>
                                <?php esc_html_e( 'Send confirmation email to submitter', 'headless-forms' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="auto_responder_subject"><?php esc_html_e( 'Subject', 'headless-forms' ); ?></label></th>
                        <td>
                            <input type="text" id="auto_responder_subject" name="auto_responder_subject" class="regular-text" 
                                   value="<?php echo esc_attr( isset( $auto_settings['subject'] ) ? $auto_settings['subject'] : '' ); ?>"
                                   placeholder="Thank you for your submission">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="auto_responder_message"><?php esc_html_e( 'Message', 'headless-forms' ); ?></label></th>
                        <td>
                            <?php
                            wp_editor(
                                isset( $auto_settings['message'] ) ? $auto_settings['message'] : '',
                                'auto_responder_message',
                                array(
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                )
                            );
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="headless_forms_save_form" class="button button-primary button-large">
                <?php echo $is_new ? esc_html__( 'Create Form', 'headless-forms' ) : esc_html__( 'Save Changes', 'headless-forms' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=headless-forms-forms' ) ); ?>" class="button button-secondary">
                <?php esc_html_e( 'Cancel', 'headless-forms' ); ?>
            </a>
        </p>
    </form>
</div>
