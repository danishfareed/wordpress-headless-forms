/**
 * Headless Forms Admin JavaScript
 *
 * @package HeadlessForms
 * @since   1.0.0
 */

(function($) {
    'use strict';

    // Initialize on DOM ready.
    $(document).ready(function() {
        initCopyButtons();
        initProviderSwitcher();
        initTestEmail();
        initRegenerateKey();
        initDeleteConfirm();
        initEndpointCopy();
        initRegenerateKey();
        initDeleteConfirm();
        initEndpointCopy();
        initTabs();
        initIntegrations();
    });

    /**
     * Integrations / Webhooks management.
     */
    function initIntegrations() {
        var $modal = $('#hf-integration-modal');
        var $list = $('#hf-integrations-list');
        
        // Open Modal
        $('#hf-add-integration-btn').on('click', function() {
            resetModal();
            $modal.fadeIn(200);
        });
        
        // Close Modal
        $('.hf-close-modal').on('click', function() {
            $modal.fadeOut(200);
        });
        
        // Preset Selection
        $('input[name="integration_preset"]').on('change', function() {
            var preset = $(this).val();
            var $payloadGroup = $('#int-payload-group');
            var $name = $('#int_name');
            var $payload = $('#int_payload');
            
            $('.hf-grid-option').removeClass('selected');
            $(this).parent().addClass('selected');
            
            // Defaults
            if (preset === 'slack') {
                $name.val('Slack Notification');
                $payloadGroup.show();
                $payload.val('{\n  "text": "New submission from *{{form_name}}*:\\n{{all_fields}}"\n}');
            } else if (preset === 'sheets') {
                $name.val('Google Sheets');
                $payloadGroup.hide();
                $payload.val('');
            } else if (preset === 'zapier') {
                $name.val('Zapier Webhook');
                $payloadGroup.hide();
                $payload.val('');
            } else {
                $name.val('Custom Webhook');
                $payloadGroup.show();
                $payload.val('');
            }
        });
        
        // Save Integration
        $('#hf-save-integration').on('click', function() {
            var $btn = $(this);
            var formId = $('#int_form_id').val();
            var name = $('#int_name').val();
            var url = $('#int_url').val();
            var preset = $('input[name="integration_preset"]:checked').val();
            var payload = $('#int_payload').val();
            
            if (!name || !url) {
                alert('Please fill in all required fields.');
                return;
            }
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: headlessFormsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'headless_forms_save_webhook',
                    nonce: headlessFormsAdmin.nonce,
                    form_id: formId,
                    webhook_name: name,
                    webhook_url: url,
                    trigger_event: 'submission.created',
                    payload_template: payload,
                    preset: preset
                },
                success: function(response) {
                    if (response.success) {
                        $('#hf-no-integrations').hide();
                        
                        // Append to list (Simplistic append, normally we'd refetch or use template)
                        var newItem = `
                            <div class="hf-integration-item" data-id="${response.data.id}" style="border:1px solid #e2e8f0; padding:16px; border-radius:6px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong style="display:block; font-size:14px;">${name}</strong>
                                    <small style="color:#64748b;">${url}</small>
                                    <div style="font-size:11px; margin-top:4px;">
                                        <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px;">submission.created</span>
                                        <span style="margin-left:8px; color:#64748b;">Pending</span>
                                    </div>
                                </div>
                                <button type="button" class="hf-button hf-button-danger hf-delete-webhook" data-id="${response.data.id}" style="padding:4px 8px; font-size:12px;">Delete</button>
                            </div>
                        `;
                        $list.append(newItem);
                        $modal.fadeOut(200);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('Error saving integration.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Integration');
                }
            });
        });
        
        // Delete Integration
        $(document).on('click', '.hf-delete-webhook', function() {
            if(!confirm('Delete this integration?')) return;
            
            var $item = $(this).closest('.hf-integration-item');
            var id = $(this).data('id');
            
            $.ajax({
                url: headlessFormsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'headless_forms_delete_webhook',
                    nonce: headlessFormsAdmin.nonce,
                    webhook_id: id
                },
                success: function(response) {
                    if(response.success) {
                        $item.fadeOut(200, function() { $(this).remove(); });
                    }
                }
            });
        });
        
        function resetModal() {
            $('#int_name').val('');
            $('#int_url').val('');
            $('#int_payload').val('');
            $('input[name="integration_preset"][value="custom"]').prop('checked', true).trigger('change');
            $('#hf-save-integration').prop('disabled', false).text('Save Integration');
        }
    }
    function initTabs() {
        $('.hf-tab-link').on('click', function() {
            var $tab = $(this);
            var targetId = $tab.data('tab');
            
            // Toggle active class on tabs.
            $('.hf-tab-link').removeClass('active');
            $tab.addClass('active');
            
            // Toggle active class on content.
            $('.hf-tab-content').removeClass('active');
            $('#tab-' + targetId).addClass('active');
        });
    }

    /**
     * Copy to clipboard functionality.
     */
    function initCopyButtons() {
        $('.hf-copy-btn').on('click', function() {
            var $btn = $(this);
            var targetId = $btn.data('copy');
            var $target = $('#' + targetId);
            
            if ($target.length) {
                var text = $target.is('input, textarea') ? $target.val() : $target.text();
                
                navigator.clipboard.writeText(text).then(function() {
                    var originalText = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> ' + headlessFormsAdmin.strings.copied);
                    
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                });
            }
        });
    }

    /**
     * Email provider switcher.
     */
    function initProviderSwitcher() {
        $('#email_provider').on('change', function() {
            var provider = $(this).val();
            
            // Hide all provider settings.
            $('.hf-provider-settings').hide();
            $('.hf-help-link').hide();
            
            // Show selected provider settings.
            $('.hf-provider-settings[data-provider="' + provider + '"]').show();
            $('.hf-help-link[data-provider="' + provider + '"]').show();
        });
    }

    /**
     * Test email functionality.
     */
    function initTestEmail() {
        $('#hf-send-test').on('click', function() {
            var $btn = $(this);
            var $input = $('#hf-test-email');
            var $result = $('#hf-test-result');
            var email = $input.val();
            
            if (!email || !isValidEmail(email)) {
                $result.removeClass('success').addClass('error')
                    .text('Please enter a valid email address.').show();
                return;
            }
            
            $btn.prop('disabled', true).text(headlessFormsAdmin.strings.testEmailSending);
            $result.hide();
            
            $.ajax({
                url: headlessFormsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'headless_forms_test_email',
                    nonce: headlessFormsAdmin.nonce,
                    email: email,
                    provider: $('#email_provider').val()
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .text(response.data.message).show();
                    } else {
                        $result.removeClass('success').addClass('error')
                            .text(response.data.message).show();
                    }
                },
                error: function() {
                    $result.removeClass('success').addClass('error')
                        .text('An error occurred. Please try again.').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Send Test');
                }
            });
        });
    }

    /**
     * Regenerate API key.
     */
    function initRegenerateKey() {
        $('#hf-regenerate-key').on('click', function() {
            if (!confirm('Are you sure you want to regenerate the API key? This will invalidate the current key.')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.ajax({
                url: headlessFormsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'headless_forms_regenerate_key',
                    nonce: headlessFormsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#hf-api-key').val(response.data.api_key);
                        alert('API key regenerated successfully!');
                    } else {
                        alert('Failed to regenerate API key.');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Delete confirmation.
     */
    function initDeleteConfirm() {
        $(document).on('click', '.hf-delete-link', function(e) {
            if (!confirm(headlessFormsAdmin.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Endpoint copy on click.
     */
    function initEndpointCopy() {
        $(document).on('click', '.hf-endpoint', function() {
            var endpoint = $(this).attr('title');
            
            navigator.clipboard.writeText(endpoint).then(function() {
                // Show feedback.
                var $el = $(this);
                var originalText = $el.text();
                $el.text('Copied!');
                
                setTimeout(function() {
                    $el.text(originalText);
                }, 1500);
            }.bind(this));
        });
    }

    /**
     * Validate email address.
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * Star toggle.
     */
    $(document).on('click', '.hf-star', function() {
        var $star = $(this);
        var submissionId = $star.data('id');
        var isStarred = $star.hasClass('starred');
        
        // Toggle UI immediately.
        $star.toggleClass('starred').text(isStarred ? '☆' : '★');
        
        // Send AJAX request.
        $.ajax({
            url: headlessFormsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'headless_forms_toggle_star',
                nonce: headlessFormsAdmin.nonce,
                submission_id: submissionId,
                starred: !isStarred
            }
        });
    });

})(jQuery);
