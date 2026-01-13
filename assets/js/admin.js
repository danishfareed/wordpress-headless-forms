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
    });

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
