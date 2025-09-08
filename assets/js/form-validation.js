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
                const phone = input.value.trim().replace(/[^0-9+]/g, '').replace(/^\+/, '');
                const spamPhones = [
                    '1234567890', '0000000000', '1111111111', '2222222222',
                    '3333333333', '4444444444', '5555555555', '6666666666',
                    '7777777777', '8888888888', '9999999999'
                ];
                // More flexible phone validation - accepts 7-15 digits
                return /^[0-9]{7,15}$/.test(phone) && !spamPhones.includes(phone);
            });
        }

        validateNameFields(form) {
            const nameInputs = form.querySelectorAll('input[name*="name"][type="text"]');
            const minLength = this.settings.min_name_length || 2;

            return Array.from(nameInputs).every(input => {
                const name = input.value.trim();
                return name.length >= minLength && /^[A-Za-z\s]+$/.test(name);
            });
        }

        validateEmailFields(form) {
            const emailInputs = form.querySelectorAll('input[type="email"]');
            return Array.from(emailInputs).every(input => {
                const email = input.value.trim();
                if (!/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/.test(email)) return false;

                const domain = email.split('@')[1];
                const spamTlds = ['xyz', 'top', 'work', 'date', 'racing', 'win', 'loan'];
                return !spamTlds.some(tld => domain.endsWith('.' + tld));
            });
        }

        async validateWithServer(form) {
            try {
                const formData = new FormData(form);
                formData.append('action', 'dg10_validate_form');
                formData.append('nonce', window.dg10Data.nonce);

                const response = await fetch(window.dg10Data.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();
                return result.success === true;
            } catch (error) {
                console.error('DG10 Form validation error:', error);
                return false;
            }
        }

        showError(form, message) {
            // Prefer Elementor error container if present, else fall back to a generic message box
            let errorDiv = form.querySelector('.elementor-message-danger');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'elementor-message elementor-message-danger';
                errorDiv.setAttribute('role', 'alert');
                errorDiv.textContent = message;
                form.prepend(errorDiv);
            } else {
                errorDiv.textContent = message;
            }

            setTimeout(() => {
                if (errorDiv && errorDiv.parentNode === form) {
                    errorDiv.remove();
                }
            }, 5000);
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