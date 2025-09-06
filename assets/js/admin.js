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

        // Form submits via WordPress Settings API (options.php); no AJAX interception needed.

        // Add live statistics update
        function updateStats() {
            var url = (window.dg10AdminData && window.dg10AdminData.ajaxurl) ? window.dg10AdminData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            if (!url) { return; }
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    action: 'dg10_get_stats',
                    nonce: (window.dg10AdminData && window.dg10AdminData.nonce) ? window.dg10AdminData.nonce : ''
                },
                success: function(response) {
                    if (response && response.success && response.data) {
                        $('#dg10-blocked-attempts').text(response.data.blocked);
                        $('#dg10-protected-forms').text(response.data.forms);
                    }
                }
            });
        }

        // Initial call and update stats every 5 minutes
        updateStats();
        setInterval(updateStats, 300000);
    });
})(jQuery);