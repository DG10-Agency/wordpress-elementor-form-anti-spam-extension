(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize tooltips (guard if jQuery UI not present)
        if ($.fn.tooltip) {
            $('.dg10-tooltip').tooltip();
        }

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
            const message = sanitizeText($(this).val()) || 'Invalid form submission detected.';
            
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
            
            // Validate required data before making request
            if (!window.dg10AdminData || !window.dg10AdminData.nonce) {
                console.error('DG10: Missing required admin data for stats update');
                return;
            }
            
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    action: 'dg10_get_stats',
                    nonce: window.dg10AdminData.nonce
                },
                timeout: 10000, // 10 second timeout
                success: function(response) {
                    if (response && response.success && response.data) {
                        // Sanitize data before displaying
                        const blocked = sanitizeNumber(response.data.blocked);
                        const forms = sanitizeNumber(response.data.forms);
                        $('#dg10-blocked-attempts').text(blocked);
                        $('#dg10-protected-forms').text(forms);
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'timeout') {
                        console.error('DG10: Stats update request timed out');
                    } else {
                        console.error('DG10: Stats update error:', error);
                    }
                }
            });
        }

        // Initial call and update stats every 5 minutes
        updateStats();
        setInterval(updateStats, 300000);

        // Preset functionality
        initPresetInterface();

        // Geographic blocking functionality
        initGeographicBlocking();

        // Conditional show/hide for geographic country lists
        toggleGeoCountryLists();
        $('#geographic_blocking_mode').on('change', toggleGeoCountryLists);

        // Time-based rules functionality
        initTimeBasedRules();

        // Unsaved changes detection + sticky save bar
        initUnsavedChangesSaveBar();
        injectSaveBehaviorTip();

        // Persist dismissal of DG10 notices (works alongside WP core behavior)
        persistDismissibleNotices();
    });

    function initPresetInterface() {
        // Validate required data
        if (!window.dg10AdminData || !window.dg10AdminData.nonce) {
            console.error('DG10: Missing required admin data for preset interface');
            return;
        }
        
        // Handle preset application
        $(document).on('click', '.dg10-apply-preset', function(e) {
            e.preventDefault();
            
            const presetId = sanitizeText($(this).data('preset-id'));
            const presetName = sanitizeText($(this).closest('.dg10-preset-card').find('h3').text());
            
            if (!presetId || presetId === 'custom') {
                return;
            }
            
            // Show confirmation dialog
            if (!confirm(`Are you sure you want to apply the "${presetName}" preset?\n\nThis will update your current settings. API keys will be preserved if already configured.`)) {
                return;
            }
            
            applyPreset(presetId);
        });

        // Handle preset card hover effects
        $('.dg10-preset-card').hover(
            function() {
                $(this).addClass('is-hovered');
            },
            function() {
                $(this).removeClass('is-hovered');
            }
        );
    }

    function applyPreset(presetId) {
        // Validate input
        if (!presetId || typeof presetId !== 'string') {
            showPresetMessage('error', 'Invalid preset ID');
            return;
        }
        
        const button = $(`.dg10-apply-preset[data-preset-id="${presetId}"]`);
        const originalText = button.text();
        
        // Show loading state
        button.prop('disabled', true).text('Applying...');
        
        const ajaxData = {
            action: 'dg10_apply_preset',
            preset_id: presetId,
            nonce: window.dg10AdminData.nonce
        };
        
        $.ajax({
            url: window.dg10AdminData.ajaxurl,
            type: 'POST',
            data: ajaxData,
            timeout: 15000, // 15 second timeout
            success: function(response) {
                if (response && response.success) {
                    // Sanitize response data
                    const message = sanitizeText(response.data.message);
                    const presetName = sanitizeText(response.data.preset_name);
                    
                    // Show success message
                    showPresetMessage('success', message);
                    
                    // Update UI to reflect new preset
                    updatePresetUI(presetId, presetName);
                    
                    // Reload page after a short delay to show updated settings
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    const errorMessage = sanitizeText(response.data ? response.data.message : 'Failed to apply preset');
                    showPresetMessage('error', errorMessage);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showPresetMessage('error', 'Request timed out. Please try again.');
                } else {
                    showPresetMessage('error', 'An error occurred while applying the preset');
                }
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    function persistDismissibleNotices() {
        $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
            const $notice = $(this).closest('.notice');
            const noticeId = sanitizeText($notice.data('dg10-notice-id'));
            
            if (!noticeId || !window.dg10AdminData || !window.dg10AdminData.nonce) {
                return;
            }
            
            $.post(window.dg10AdminData.ajaxurl, {
                action: 'dg10_dismiss_notice',
                nonce: window.dg10AdminData.nonce,
                notice_id: noticeId
            }).fail(function() {
                console.error('DG10: Failed to dismiss notice');
            });
        });
    }

    function updatePresetUI(presetId, presetName) {
        // Remove active state from all cards
        $('.dg10-preset-card').removeClass('is-active');
        
        // Add active state to current preset
        $(`.dg10-preset-card[data-preset-id="${presetId}"]`).addClass('is-active');
        
        // Update button texts
        $('.dg10-apply-preset').each(function() {
            const cardPresetId = $(this).data('preset-id');
            if (cardPresetId === presetId) {
                $(this).text('Current');
            } else {
                $(this).text('Apply');
            }
        });
        
        // Update active badges
        $('.dg10-preset-badge.is-active').remove();
        $(`.dg10-preset-card[data-preset-id="${presetId}"] .dg10-preset-content`).append(
            `<span class="dg10-preset-badge is-active">Active</span>`
        );
    }

    function showPresetMessage(type, message) {
        // Remove existing messages
        $('.dg10-preset-message').remove();
        
        // Sanitize message
        const sanitizedMessage = sanitizeText(message);
        
        // Create new message
        const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        const messageHtml = `
            <div class="notice ${messageClass} dg10-preset-message" style="margin: 15px 0;">
                <p>${sanitizedMessage}</p>
            </div>
        `;
        
        // Insert message after preset interface
        $('.dg10-preset-interface').after(messageHtml);
        
        // Auto-remove success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.dg10-preset-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

    function initGeographicBlocking() {
        // Load country statistics
        loadCountryStats();

        // Handle country blocking quick actions
        $(document).on('click', '.dg10-block-country', function(e) {
            e.preventDefault();
            
            const countryCode = sanitizeText($(this).data('country-code'));
            const countryName = sanitizeText($(this).data('country-name'));
            
            if (!countryCode || !countryName) {
                return;
            }
            
            if (!confirm(`Are you sure you want to block submissions from ${countryName}?`)) {
                return;
            }
            
            blockCountry(countryCode, countryName);
        });

        // Handle country unblocking
        $(document).on('click', '.dg10-unblock-country', function(e) {
            e.preventDefault();
            
            const countryCode = sanitizeText($(this).data('country-code'));
            const countryName = sanitizeText($(this).data('country-name'));
            
            if (!countryCode || !countryName) {
                return;
            }
            
            if (!confirm(`Are you sure you want to unblock submissions from ${countryName}?`)) {
                return;
            }
            
            unblockCountry(countryCode, countryName);
        });
    }

    function toggleGeoCountryLists() {
        var mode = $('#geographic_blocking_mode').val();
        var blockedRow = $('#blocked_countries').closest('tr');
        var allowedRow = $('#allowed_countries').closest('tr');
        if (mode === 'allow') {
            blockedRow.hide();
            allowedRow.show();
        } else {
            blockedRow.show();
            allowedRow.hide();
        }
    }

    function initUnsavedChangesSaveBar() {
        const $form = $('.dg10-admin-main form[action="options.php"]');
        if (!$form.length) return;

        // Create sticky bar
        const $bar = $('<div class="dg10-sticky-savebar" style="display:none;" aria-live="polite" role="status">\
            <span class="dg10-savebar-text">Changes not saved</span>\
            <div class="dg10-savebar-actions">\
                <button type="button" class="button button-secondary dg10-discard">Discard</button>\
                <button type="button" class="button button-primary dg10-save">Save settings</button>\
            </div>\
        </div>');
        $('body').append($bar);

        let dirty = false;
        const showBar = () => { if (!dirty) { dirty = true; $bar.fadeIn(120); } };
        const hideBar = () => { dirty = false; $bar.fadeOut(120); };

        // Mark dirty on input changes
        $form.on('change input', 'input, select, textarea', function() {
            // Ignore clicks on the submit button itself
            if ($(this).is(':submit')) return;
            showBar();
        });

        // Save via bar
        $bar.on('click', '.dg10-save', function() {
            hideBar();
            $form.trigger('submit');
        });

        // Discard via page reload
        $bar.on('click', '.dg10-discard', function() {
            window.location.reload();
        });

        // Hide bar when form submits successfully
        $form.on('submit', function() { hideBar(); });
    }

    function injectSaveBehaviorTip() {
        const $form = $('.dg10-admin-main form[action="options.php"]');
        if (!$form.length) return;
        const tip = $('<p class="description dg10-save-tip">Most settings require saving. Presets and sidebar quick actions apply instantly.</p>');
        // Insert tip just above the form fields
        const $firstSection = $('.dg10-admin-main').find('h2').first();
        if ($firstSection.length) {
            $firstSection.after(tip);
        } else {
            $form.prepend(tip);
        }
    }

    function loadCountryStats() {
        // Validate required data
        if (!window.dg10AdminData || !window.dg10AdminData.nonce) {
            $('#dg10-country-stats').html('<p class="description">Error: Missing required data.</p>');
            return;
        }
        
        $.ajax({
            url: window.dg10AdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dg10_get_country_stats',
                nonce: window.dg10AdminData.nonce
            },
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response && response.success && response.data && response.data.stats) {
                    displayCountryStats(response.data.stats);
                } else {
                    $('#dg10-country-stats').html('<p class="description">No country data available yet.</p>');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    $('#dg10-country-stats').html('<p class="description">Request timed out. Please try again.</p>');
                } else {
                    $('#dg10-country-stats').html('<p class="description">Error loading country statistics.</p>');
                }
            }
        });
    }

    function displayCountryStats(stats) {
        if (!stats || Object.keys(stats).length === 0) {
            $('#dg10-country-stats').html('<p class="description">No country data available yet.</p>');
            return;
        }

        let html = '<ul class="dg10-country-stats-list">';
        
        Object.entries(stats).forEach(([code, data]) => {
            const isBlocked = window.dg10AdminData.blockedCountries && 
                             window.dg10AdminData.blockedCountries.includes(code);
            
            // Sanitize data before displaying
            const sanitizedCode = sanitizeText(code);
            const sanitizedName = sanitizeText(data.name);
            const sanitizedSubmissions = sanitizeNumber(data.submissions);
            
            html += `
                <li class="dg10-country-stat-item">
                    <div class="dg10-country-info">
                        <strong>${sanitizedName}</strong>
                        <span class="dg10-country-code">${sanitizedCode}</span>
                        <span class="dg10-country-count">${sanitizedSubmissions} submissions</span>
                    </div>
                    <div class="dg10-country-actions">
                        ${isBlocked ? 
                            `<button class="button button-small dg10-unblock-country" data-country-code="${sanitizedCode}" data-country-name="${sanitizedName}">Unblock</button>` :
                            `<button class="button button-small dg10-block-country" data-country-code="${sanitizedCode}" data-country-name="${sanitizedName}">Block</button>`
                        }
                    </div>
                </li>
            `;
        });
        
        html += '</ul>';
        $('#dg10-country-stats').html(html);
    }

    function blockCountry(countryCode, countryName) {
        // Validate input
        if (!countryCode || !countryName) {
            showGeographicMessage('error', 'Invalid country data');
            return;
        }
        
        $.ajax({
            url: window.dg10AdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dg10_block_country',
                country_code: countryCode,
                action_type: 'block',
                nonce: window.dg10AdminData.nonce
            },
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response && response.success) {
                    const message = sanitizeText(response.data.message);
                    showGeographicMessage('success', message);
                    loadCountryStats(); // Refresh the display
                } else {
                    const errorMessage = sanitizeText(response.data ? response.data.message : 'Failed to block country');
                    showGeographicMessage('error', errorMessage);
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showGeographicMessage('error', 'Request timed out. Please try again.');
                } else {
                    showGeographicMessage('error', 'An error occurred while blocking the country');
                }
            }
        });
    }

    function unblockCountry(countryCode, countryName) {
        // Validate input
        if (!countryCode || !countryName) {
            showGeographicMessage('error', 'Invalid country data');
            return;
        }
        
        $.ajax({
            url: window.dg10AdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dg10_block_country',
                country_code: countryCode,
                action_type: 'unblock',
                nonce: window.dg10AdminData.nonce
            },
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response && response.success) {
                    const message = sanitizeText(response.data.message);
                    showGeographicMessage('success', message);
                    loadCountryStats(); // Refresh the display
                } else {
                    const errorMessage = sanitizeText(response.data ? response.data.message : 'Failed to unblock country');
                    showGeographicMessage('error', errorMessage);
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showGeographicMessage('error', 'Request timed out. Please try again.');
                } else {
                    showGeographicMessage('error', 'An error occurred while unblocking the country');
                }
            }
        });
    }

    function showGeographicMessage(type, message) {
        // Remove existing messages
        $('.dg10-geographic-message').remove();
        
        // Sanitize message
        const sanitizedMessage = sanitizeText(message);
        
        // Create new message
        const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        const messageHtml = `
            <div class="notice ${messageClass} dg10-geographic-message" style="margin: 15px 0;">
                <p>${sanitizedMessage}</p>
            </div>
        `;
        
        // Insert message after geographic statistics
        $('.dg10-box:has(#dg10-country-stats)').after(messageHtml);
        
        // Auto-remove success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.dg10-geographic-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

    function initTimeBasedRules() {
        // Load time statistics
        loadTimeStats();

        // Handle time field dependencies
        $('#enable_business_hours').on('change', function() {
            const weekdayFields = $('#weekday_start_time, #weekday_end_time').closest('tr');
            if ($(this).is(':checked')) {
                weekdayFields.show();
            } else {
                weekdayFields.hide();
            }
        }).trigger('change');

        $('#enable_weekend_hours').on('change', function() {
            const weekendFields = $('#weekend_start_time, #weekend_end_time, #weekend_days').closest('tr');
            if ($(this).is(':checked')) {
                weekendFields.show();
            } else {
                weekendFields.hide();
            }
        }).trigger('change');

        $('#enable_holiday_mode').on('change', function() {
            const holidayFields = $('#holiday_dates, #enable_holiday_hours').closest('tr');
            if ($(this).is(':checked')) {
                holidayFields.show();
            } else {
                holidayFields.hide();
            }
        }).trigger('change');

        $('#enable_holiday_hours').on('change', function() {
            const holidayTimeFields = $('#holiday_start_time, #holiday_end_time').closest('tr');
            if ($(this).is(':checked')) {
                holidayTimeFields.show();
            } else {
                holidayTimeFields.hide();
            }
        }).trigger('change');
    }

    function loadTimeStats() {
        // Validate required data
        if (!window.dg10AdminData || !window.dg10AdminData.nonce) {
            $('#dg10-time-stats').html('<p class="description">Error: Missing required data.</p>');
            return;
        }
        
        $.ajax({
            url: window.dg10AdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dg10_get_time_stats',
                nonce: window.dg10AdminData.nonce
            },
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response && response.success && response.data) {
                    displayTimeStats(response.data.stats, response.data.current_rules);
                } else {
                    $('#dg10-time-stats').html('<p class="description">No time data available yet.</p>');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    $('#dg10-time-stats').html('<p class="description">Request timed out. Please try again.</p>');
                } else {
                    $('#dg10-time-stats').html('<p class="description">Error loading time statistics.</p>');
                }
            }
        });
    }

    function displayTimeStats(stats, currentRules) {
        if (!stats || Object.keys(stats).length === 0) {
            $('#dg10-time-stats').html('<p class="description">No time data available yet.</p>');
            return;
        }

        let html = '<div class="dg10-time-stats-container">';
        
        // Current status
        html += '<div class="dg10-current-status">';
        html += '<h4>Current Status</h4>';
        html += `<p><strong>Time:</strong> ${sanitizeText(currentRules.current_time)} (${sanitizeText(currentRules.timezone)})</p>`;
        html += `<p><strong>Business Hours:</strong> ${currentRules.is_business_hours ? 'Open' : 'Closed'}</p>`;
        html += `<p><strong>Day Type:</strong> ${currentRules.is_holiday ? 'Holiday' : (currentRules.is_weekend ? 'Weekend' : 'Weekday')}</p>`;
        html += '</div>';

        // Recent activity
        html += '<div class="dg10-recent-activity">';
        html += '<h4>Recent Activity (Last 24 Hours)</h4>';
        
        const recentStats = Object.entries(stats)
            .filter(([key]) => {
                const statDate = new Date(key);
                const now = new Date();
                const diffHours = (now - statDate) / (1000 * 60 * 60);
                return diffHours <= 24;
            })
            .sort(([a], [b]) => new Date(b) - new Date(a))
            .slice(0, 6);

        if (recentStats.length > 0) {
            html += '<ul class="dg10-time-stats-list">';
            recentStats.forEach(([timeKey, data]) => {
                const time = new Date(timeKey).toLocaleString();
                const type = data.is_holiday ? 'Holiday' : (data.is_weekend ? 'Weekend' : 'Weekday');
                const status = data.is_business_hours ? 'Open' : 'Closed';
                
                // Sanitize data before displaying
                const sanitizedTime = sanitizeText(time);
                const sanitizedType = sanitizeText(type);
                const sanitizedStatus = sanitizeText(status);
                const sanitizedSubmissions = sanitizeNumber(data.submissions);
                
                html += `
                    <li class="dg10-time-stat-item">
                        <div class="dg10-time-info">
                            <strong>${sanitizedTime}</strong>
                            <span class="dg10-time-type">${sanitizedType}</span>
                            <span class="dg10-time-status ${data.is_business_hours ? 'is-open' : 'is-closed'}">${sanitizedStatus}</span>
                        </div>
                        <div class="dg10-time-count">
                            ${sanitizedSubmissions} submissions
                        </div>
                    </li>
                `;
            });
            html += '</ul>';
        } else {
            html += '<p class="description">No recent activity.</p>';
        }
        
        html += '</div>';
        html += '</div>';
        
        $('#dg10-time-stats').html(html);
    }

    // WordPress handles dismissible notices automatically - no custom handler needed

    // Security helper functions
    function sanitizeText(text) {
        if (typeof text !== 'string') {
            return '';
        }
        
        // Remove potentially dangerous characters and limit length
        return text.trim()
            .replace(/[<>]/g, '') // Remove < and >
            .replace(/javascript:/gi, '') // Remove javascript: protocol
            .replace(/on\w+=/gi, '') // Remove event handlers
            .substring(0, 1000); // Limit length
    }

    function sanitizeNumber(num) {
        if (typeof num === 'number') {
            return Math.max(0, Math.floor(num));
        }
        
        const parsed = parseInt(num, 10);
        return isNaN(parsed) ? 0 : Math.max(0, parsed);
    }
})(jQuery);