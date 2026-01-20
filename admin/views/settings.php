<?php
/**
 * Settings View.
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$rate_limit = get_option( 'headless_forms_rate_limit', 5 );
$rate_window = get_option( 'headless_forms_rate_limit_window', 60 );
$honeypot_field = get_option( 'headless_forms_honeypot_field', '_honey' );
$cors_origins = get_option( 'headless_forms_cors_origins', '' );
$keep_data = get_option( 'headless_forms_keep_data_on_delete', false );
$retention_days = get_option( 'headless_forms_data_retention_days', 365 );
$cors_enforcement = get_option( 'headless_forms_cors_enforcement', false );
$provider_settings = get_option( 'headless_forms_provider_settings', array() );
?>

<!-- Page Header -->
<div class="hf-page-header">
    <h2 class="hf-page-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e( 'Settings', 'headless-forms' ); ?>
    </h2>
</div>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible" style="margin-left: 0;">
            <p><?php esc_html_e( 'Settings saved successfully.', 'headless-forms' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="hf-settings-form">
        <?php wp_nonce_field( 'headless_forms_save_settings', 'headless_forms_nonce' ); ?>

        <!-- API Key Section -->
        <div class="hf-card">
            <h2><?php esc_html_e( 'API Authentication', 'headless-forms' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'API Key', 'headless-forms' ); ?></label></th>
                    <td>
                        <div class="hf-api-key-wrap">
                            <input type="text" id="hf-api-key" value="<?php echo esc_attr( $api_key ); ?>" readonly class="regular-text code" style="background: #f1f5f9; border: 1px solid #cbd5e1;">
                            <button type="button" class="hf-button hf-button-secondary hf-copy-btn" data-copy="hf-api-key">
                                <span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Copy', 'headless-forms' ); ?>
                            </button>
                            <button type="button" class="hf-button hf-button-secondary" id="hf-regenerate-key">
                                <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate', 'headless-forms' ); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Use this key in the X-HF-API-Key header or as Bearer token.', 'headless-forms' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Provider Section -->
        <div class="hf-card">
            <h2><?php esc_html_e( 'Email Provider', 'headless-forms' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="email_provider"><?php esc_html_e( 'Select Provider', 'headless-forms' ); ?></label></th>
                    <td>
                        <select id="email_provider" name="email_provider" class="hf-provider-select">
                            <?php foreach ( $providers as $slug => $provider ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_provider, $slug ); ?>>
                                    <?php echo esc_html( $provider['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php foreach ( $providers as $slug => $provider ) : ?>
                            <?php if ( ! empty( $provider['help'] ) ) : ?>
                                <a href="<?php echo esc_url( $provider['help'] ); ?>" target="_blank" class="hf-help-link" data-provider="<?php echo esc_attr( $slug ); ?>" style="<?php echo $current_provider === $slug ? '' : 'display:none;'; ?>">
                                    <span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Documentation', 'headless-forms' ); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <!-- Provider Settings -->
            <?php foreach ( $providers as $slug => $provider ) : ?>
                <?php if ( ! empty( $provider['fields'] ) ) : ?>
                    <div class="hf-provider-settings" data-provider="<?php echo esc_attr( $slug ); ?>" style="<?php echo $current_provider === $slug ? '' : 'display:none;'; ?>">
                        <h3><?php echo esc_html( $provider['name'] ); ?> <?php esc_html_e( 'Settings', 'headless-forms' ); ?></h3>
                        <table class="form-table">
                            <?php foreach ( $provider['fields'] as $field ) : ?>
                                <?php
                                $saved_value = isset( $provider_settings[ $slug ][ $field['id'] ] ) ? $provider_settings[ $slug ][ $field['id'] ] : '';
                                
                                // Check if this is a sensitive field.
                                $field_id = $field['id'];
                                $is_sensitive = ( strpos( $field_id, 'password' ) !== false || strpos( $field_id, 'secret' ) !== false || strpos( $field_id, 'api_key' ) !== false || strpos( $field_id, 'token' ) !== false || strpos( $field_id, 'access_key' ) !== false );

                                // Mask sensitive values.
                                if ( $is_sensitive && ! empty( $saved_value ) ) {
                                    $saved_value = '••••••••••••';
                                }
                                ?>
                                <tr>
                                    <th>
                                        <label for="provider_<?php echo esc_attr( $slug . '_' . $field['id'] ); ?>">
                                            <?php echo esc_html( $field['label'] ); ?>
                                            <?php if ( ! empty( $field['required'] ) ) : ?>*<?php endif; ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php if ( $field['type'] === 'select' ) : ?>
                                            <select name="provider_settings[<?php echo esc_attr( $field['id'] ); ?>]" id="provider_<?php echo esc_attr( $slug . '_' . $field['id'] ); ?>">
                                                <?php foreach ( $field['options'] as $value => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $provider_settings[ $slug ][ $field['id'] ] ) ? $provider_settings[ $slug ][ $field['id'] ] : '', $value ); ?>>
                                                        <?php echo esc_html( $label ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else : ?>
                                            <input type="<?php echo esc_attr( $field['type'] ); ?>" 
                                                   name="provider_settings[<?php echo esc_attr( $field['id'] ); ?>]" 
                                                   id="provider_<?php echo esc_attr( $slug . '_' . $field['id'] ); ?>"
                                                   value="<?php echo esc_attr( $saved_value ); ?>"
                                                   class="regular-text"
                                                   placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                                                   <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $field['description'] ) ) : ?>
                                            <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Test Email -->
            <div class="hf-test-email">
                <h3><?php esc_html_e( 'Test Email', 'headless-forms' ); ?></h3>
                <div class="hf-test-email-wrap">
                    <input type="email" id="hf-test-email" placeholder="<?php esc_attr_e( 'Enter email address', 'headless-forms' ); ?>" class="regular-text">
                    <button type="button" id="hf-send-test" class="hf-button hf-button-secondary">
                        <span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Send Test', 'headless-forms' ); ?>
                    </button>
                    <span id="hf-test-result" style="display:none;"></span>
                </div>
            </div>
        </div>

        <!-- Security Section -->
        <div class="hf-card">
            <h2><?php esc_html_e( 'Security Settings', 'headless-forms' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="rate_limit"><?php esc_html_e( 'Rate Limit', 'headless-forms' ); ?></label></th>
                    <td>
                        <input type="number" id="rate_limit" name="rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" min="1" max="100" class="small-text">
                        <?php esc_html_e( 'requests per', 'headless-forms' ); ?>
                        <input type="number" id="rate_limit_window" name="rate_limit_window" value="<?php echo esc_attr( $rate_window ); ?>" min="10" max="3600" class="small-text">
                        <?php esc_html_e( 'seconds per IP', 'headless-forms' ); ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="honeypot_field"><?php esc_html_e( 'Honeypot Field Name', 'headless-forms' ); ?></label></th>
                    <td>
                        <input type="text" id="honeypot_field" name="honeypot_field" value="<?php echo esc_attr( $honeypot_field ); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Hidden field name for spam detection. Leave empty in submissions.', 'headless-forms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cors_enforcement"><?php esc_html_e( 'Strict CORS Enforcement', 'headless-forms' ); ?></label></th>
                    <td>
                        <input type="checkbox" id="cors_enforcement" name="cors_enforcement" value="1" <?php checked( $cors_enforcement ); ?>>
                        <p class="description"><?php esc_html_e( 'If enabled, ONLY the origins listed below will be allowed. If disabled, all origins are allowed.', 'headless-forms' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cors_origins"><?php esc_html_e( 'Allowed Origins', 'headless-forms' ); ?></label></th>
                    <td>
                        <textarea id="cors_origins" name="cors_origins" class="large-text" rows="3" placeholder="https://example.com"><?php echo esc_textarea( $cors_origins ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One origin per line. Only used if Strict CORS Enforcement is ON.', 'headless-forms' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Data Settings -->
        <div class="hf-card">
            <h2><?php esc_html_e( 'Data Settings', 'headless-forms' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Keep Data on Delete', 'headless-forms' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="keep_data_on_delete" value="1" <?php checked( $keep_data ); ?>>
                            <?php esc_html_e( 'Keep form data when plugin is deleted', 'headless-forms' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="data_retention_days"><?php esc_html_e( 'Data Retention', 'headless-forms' ); ?></label></th>
                    <td>
                        <input type="number" id="data_retention_days" name="data_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" min="0" class="small-text">
                        <?php esc_html_e( 'days (0 = keep forever)', 'headless-forms' ); ?>
                        <p class="description"><?php esc_html_e( 'Automatically delete submissions older than this.', 'headless-forms' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="headless_forms_save_settings" class="hf-button hf-button-primary">
                <?php esc_html_e( 'Save Settings', 'headless-forms' ); ?>
            </button>
        </p>
    </form>

