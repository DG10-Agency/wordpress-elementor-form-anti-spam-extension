(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize tooltips
        $('.dg10-tooltip').tooltip();

        // Handle real-time validation of number inputs
        $('input[type="number"]').on('input', function() {
            const min = parseInt($(this).attr('min'));
            const max = parseInt($(this).attr('max'));
            let value = parseInt($(this).val());

            if (value < min) {
                $(this).val(min);
            } else if (value > max) {
                $(this).val(max);
            }
        });

        // Handle custom error message preview
        $('#custom_error_message').on('input', function() {
            const preview = $('#error-message-preview');
            const message = $(this).val() || 'Invalid form submission detected.';
            
            if (!preview.length) {
                $(this).after('<div id="error-message-preview" class="error-message"></div>');
            }
            $('#error-message-preview').text(message);
        });

        // Handle dependency between options
        $('#enable_time_check').on('change', function() {
            const maxSubmissionsField = $('#max_submissions_per_hour').closest('tr');
            if ($(this).is(':checked')) {
                maxSubmissionsField.show();
            } else {
                maxSubmissionsField.hide();
            }
        }).trigger('change');

        // Add confirmation for resetting statistics
        $('.dg10-reset-stats').on('click', function(e) {
            if (!confirm('Are you sure you want to reset all statistics? This cannot be undone.')) {
                e.preventDefault();
            }
        });

        // Add AJAX form submission
        $('form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitButton = form.find('input[type="submit"]');
            const originalText = submitButton.val();

            submitButton.val('Saving...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: form.serialize() + '&action=dg10_save_settings',
                success: function(response) {
                    if (response.success) {
                        form.before('<div class="updated"><p>Settings saved successfully!</p></div>');
                        setTimeout(function() {
                            $('.updated').fadeOut();
                        }, 3000);
                    } else {
                        form.before('<div class="error"><p>Error saving settings. Please try again.</p></div>');
                    }
                },
                error: function() {
                    form.before('<div class="error"><p>Error saving settings. Please try again.</p></div>');
                },
                complete: function() {
                    submitButton.val(originalText).prop('disabled', false);
                }
            });
        });

        // Add live statistics update
        function updateStats() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dg10_get_stats'
                },
                success: function(response) {
                    if (response.success) {
                        $('#blocked-attempts').text(response.data.blocked);
                        $('#protected-forms').text(response.data.forms);
                    }
                }
            });
        }

        // Update stats every 5 minutes
        setInterval(updateStats, 300000);
    });
})(jQuery);