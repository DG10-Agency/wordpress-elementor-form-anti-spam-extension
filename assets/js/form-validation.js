(function($) {
    'use strict';

    class DG10FormValidator {
        constructor() {
            this.settings = (window.dg10Data && window.dg10Data.settings) ? window.dg10Data.settings : {};
            this.enableAjaxValidation = !!(window.dg10Data && window.dg10Data.enableAjaxValidation);
            this.init();
        }

        init() {
            this.setupFormValidation();
            this.setupAjaxValidation();
        }

        setupFormValidation() {
            // Collect targets: Elementor forms and optional Lite selector
            const selectors = ['.elementor-form'];
            if (this.settings && this.settings.enable_lite_mode && typeof this.settings.lite_form_selector === 'string' && this.settings.lite_form_selector.trim().length > 0) {
                selectors.push(this.settings.lite_form_selector.trim());
            }

            // Query and deduplicate forms
            const forms = Array.from(new Set(
                selectors.flatMap(sel => Array.from(document.querySelectorAll(sel)))
            ));

            if (!forms.length) return;

            forms.forEach(form => {
                this.addHoneypotField(form);
                this.addTimeField(form);
                this.removeNativeBrowserValidation(form);
                this.addSubmitHandler(form);
            });
        }

        addHoneypotField(form) {
            if (!this.settings.enable_honeypot) return;

            const honeypot = document.createElement('div');
            honeypot.style.cssText = 'position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important;';
            honeypot.innerHTML = '<input type="text" name="dg10_hp_check" value="" tabindex="-1" autocomplete="off">';
            form.appendChild(honeypot);
        }

        addTimeField(form) {
            if (!this.settings.enable_time_check) return;

            const timeField = document.createElement('input');
            timeField.type = 'hidden';
            timeField.name = 'dg10_submission_time';
            timeField.value = Date.now();
            form.appendChild(timeField);
        }

        removeNativeBrowserValidation(form) {
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => {
                input.removeAttribute('pattern');
                input.removeAttribute('title');
            });
        }

        addSubmitHandler(form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e, form), true);
        }

        async handleSubmit(e, form) {
            if (!await this.validateForm(form)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                this.showError(form, this.settings.custom_error_message || 'Invalid form submission detected.');
            }
        }

        async validateForm(form) {
            const validations = [
                this.validateHoneypot(form),
                this.validateTimeCheck(form),
                this.validateFields(form)
            ];

            if (this.enableAjaxValidation) {
                validations.push(this.validateWithServer(form));
            }

            const results = await Promise.all(validations);
            return results.every(result => result === true);
        }

        validateHoneypot(form) {
            if (!this.settings.enable_honeypot) return true;

            const honeypotInput = form.querySelector('input[name="dg10_hp_check"]');
            return !honeypotInput || honeypotInput.value === '';
        }

        validateTimeCheck(form) {
            if (!this.settings.enable_time_check) return true;

            const timeField = form.querySelector('input[name="dg10_submission_time"]');
            if (!timeField) return true;

            const submissionTime = parseInt(timeField.value, 10);
            const currentTime = Date.now();
            const minTime = this.settings.min_submission_time || 3000; // 3 seconds minimum

            return (currentTime - submissionTime) >= minTime;
        }

        validateFields(form) {
            const validations = [
                this.validatePhoneFields(form),
                this.validateNameFields(form),
                this.validateEmailFields(form)
            ];

            return validations.every(validation => validation === true);
        }

        validatePhoneFields(form) {
            const phoneInputs = form.querySelectorAll('input[type="tel"]');
            return Array.from(phoneInputs).every(input => {
                // Sanitize phone input
                const phone = this.sanitizePhoneNumber(input.value);
                const spamPhones = [
                    '1234567890', '0000000000', '1111111111', '2222222222',
                    '3333333333', '4444444444', '5555555555', '6666666666',
                    '7777777777', '8888888888', '9999999999'
                ];
                // More flexible phone validation - accepts 7-15 digits
                return /^[0-9]{7,15}$/.test(phone) && !spamPhones.includes(phone);
            });
        }

        /**
         * Sanitize phone number input
         */
        sanitizePhoneNumber(phone) {
            if (typeof phone !== 'string') {
                return '';
            }
            
            // Remove all non-numeric characters except +
            return phone.trim().replace(/[^0-9+]/g, '').replace(/^\+/, '');
        }

        validateNameFields(form) {
            const nameInputs = form.querySelectorAll('input[name*="name"][type="text"]');
            const minLength = Math.max(1, Math.min(50, parseInt(this.settings.min_name_length) || 2));

            return Array.from(nameInputs).every(input => {
                const name = this.sanitizeName(input.value);
                return name.length >= minLength && /^[A-Za-z\s\-'\.]+$/.test(name);
            });
        }

        /**
         * Sanitize name input
         */
        sanitizeName(name) {
            if (typeof name !== 'string') {
                return '';
            }
            
            // Remove potentially dangerous characters and limit length
            return name.trim()
                .replace(/[<>]/g, '') // Remove < and >
                .replace(/javascript:/gi, '') // Remove javascript: protocol
                .substring(0, 100); // Limit length
        }

        validateEmailFields(form) {
            const emailInputs = form.querySelectorAll('input[type="email"]');
            return Array.from(emailInputs).every(input => {
                const email = this.sanitizeEmail(input.value);
                if (!/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/.test(email)) return false;

                const domain = email.split('@')[1];
                const spamTlds = ['xyz', 'top', 'work', 'date', 'racing', 'win', 'loan'];
                return !spamTlds.some(tld => domain.endsWith('.' + tld));
            });
        }

        /**
         * Sanitize email input
         */
        sanitizeEmail(email) {
            if (typeof email !== 'string') {
                return '';
            }
            
            // Remove potentially dangerous characters and limit length
            return email.trim()
                .replace(/[<>]/g, '') // Remove < and >
                .replace(/javascript:/gi, '') // Remove javascript: protocol
                .substring(0, 254); // RFC 5321 limit
        }

        async validateWithServer(form) {
            try {
                // Validate required data exists
                if (!window.dg10Data || !window.dg10Data.ajaxurl || !window.dg10Data.nonce) {
                    console.error('DG10: Missing required validation data');
                    return false;
                }

                const formData = new FormData(form);
                formData.append('action', 'dg10_validate_form');
                formData.append('nonce', window.dg10Data.nonce);

                // Add timeout to prevent hanging requests
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

                const response = await fetch(window.dg10Data.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                // Validate response structure
                if (typeof result !== 'object' || result === null) {
                    throw new Error('Invalid response format');
                }

                return result.success === true;
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.error('DG10: Form validation request timed out');
                } else {
                    console.error('DG10 Form validation error:', error.message || error);
                }
                return false;
            }
        }

        showError(form, message) {
            // Sanitize error message to prevent XSS
            const sanitizedMessage = this.sanitizeMessage(message);
            
            // Prefer Elementor error container if present, else fall back to a generic message box
            let errorDiv = form.querySelector('.elementor-message-danger');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'elementor-message elementor-message-danger';
                errorDiv.setAttribute('role', 'alert');
                errorDiv.textContent = sanitizedMessage;
                form.prepend(errorDiv);
            } else {
                errorDiv.textContent = sanitizedMessage;
            }

            setTimeout(() => {
                if (errorDiv && errorDiv.parentNode === form) {
                    errorDiv.remove();
                }
            }, 5000);
        }

        /**
         * Sanitize message to prevent XSS
         */
        sanitizeMessage(message) {
            if (typeof message !== 'string') {
                return 'Invalid form submission detected.';
            }
            
            // Remove potentially dangerous characters
            return message
                .replace(/[<>]/g, '') // Remove < and >
                .replace(/javascript:/gi, '') // Remove javascript: protocol
                .replace(/on\w+=/gi, '') // Remove event handlers
                .substring(0, 500); // Limit length
        }

        setupAjaxValidation() {
            $(document).on('elementor/ajax/register_actions', function(e, actions) {
                actions.dg10_validate_form = function(settings) {
                    return {
                        url: window.dg10Data.ajaxurl,
                        type: 'POST',
                        data: settings.data
                    };
                };
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new DG10FormValidator();
    });

})(jQuery);