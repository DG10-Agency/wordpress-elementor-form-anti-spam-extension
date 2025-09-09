<?php
if (!defined('ABSPATH')) exit;

class DG10_Form_Validator {
    private static $instance = null;
    private $settings;
    private $ai_validator;
    private $spam_phones = [
        '1234567890', '0000000000', '1111111111', '2222222222',
        '3333333333', '4444444444', '5555555555', '6666666666',
        '7777777777', '8888888888', '9999999999'
    ];
    private $spam_tlds = ['xyz', 'top', 'work', 'date', 'racing', 'win', 'loan'];
    private $spam_keywords = ['viagra', 'casino', 'loan', 'crypto', 'bitcoin', 'forex', 'investment'];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
        $this->ai_validator = DG10_AI_Validator::get_instance();
    }

    public function validate_form($record, $ajax_handler) {
        // Correct Elementor record API
        $fields = $record->get('fields');
        $form_name = $record->get_form_settings('form_name');
        // Track protected forms (unique by form_name)
        if (!empty($form_name)) {
            $forms = (array) get_option('dg10_protected_forms', []);
            if (!in_array($form_name, $forms, true)) {
                $forms[] = $form_name;
                update_option('dg10_protected_forms', $forms);
            }
        }

        // IP-based rate limiting
        $ip_manager = DG10_IP_Manager::get_instance();
        $ip = $ip_manager->get_client_ip();
        $max_per_hour = isset($_POST['_dg10_runtime_max_per_hour'])
            ? absint(sanitize_text_field($_POST['_dg10_runtime_max_per_hour']))
            : absint($this->settings->get_option('max_submissions_per_hour', 5));
        if ($ip && $max_per_hour > 0) {
            $ip_manager->clean_old_submissions();
            if ($ip_manager->is_submission_rate_exceeded($ip, $max_per_hour)) {
                $this->add_error($ajax_handler);
                return;
            }
        }

        // Geographic blocking
        if ($ip) {
            $geo_blocker = DG10_Geographic_Blocker::get_instance();
            if ($geo_blocker->is_ip_blocked_by_geography($ip)) {
                $this->add_error($ajax_handler);
                return;
            }
        }

        // Time-based rules
        $time_rules = DG10_Time_Rules::get_instance();
        if ($time_rules->is_submission_blocked_by_time()) {
            $time_based_rules = $time_rules->get_time_based_rules();
            $error_message = $time_based_rules['custom_error_message'] ?? 'Submissions are currently restricted.';
            $this->add_error($ajax_handler, $error_message);
            return;
        }

        // Apply time-based rule overrides to current runtime only
        $time_based_rules = $time_rules->get_time_based_rules();
        $this->apply_time_based_rules($time_based_rules);

        // Check honeypot
        if ($this->settings->get_option('enable_honeypot', true) && !$this->validate_honeypot()) {
            $this->add_error($ajax_handler);
            return;
        }

        // Check submission time
        if ($this->settings->get_option('enable_time_check', true) && !$this->validate_submission_time()) {
            $this->add_error($ajax_handler);
            return;
        }

        // Validate fields
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $field_type = isset($field['type']) ? $field['type'] : '';
                $field_value = isset($field['value']) ? $field['value'] : '';

                // Check for spam keywords
                if ($this->settings->get_option('enable_spam_keywords', true) && $this->contains_spam_keywords($field_value)) {
                    $this->add_error($ajax_handler);
                    return;
                }

                switch ($field_type) {
                    case 'tel':
                        if (!$this->validate_phone($field_value)) {
                            $this->add_error($ajax_handler);
                            return;
                        }
                        break;

                    case 'email':
                        if (!$this->validate_email($field_value)) {
                            $this->add_error($ajax_handler);
                            return;
                        }
                        break;

                    case 'text':
                    case 'name':
                        $runtimeMinLen = isset($_POST['_dg10_runtime_min_name_length']) ? absint(sanitize_text_field($_POST['_dg10_runtime_min_name_length'])) : null;
                        $minLen = $runtimeMinLen !== null ? $runtimeMinLen : $this->settings->get_option('min_name_length', 2);
                        if (is_string($field_value) && strlen(trim($field_value)) < $minLen) {
                            $this->add_error($ajax_handler);
                            return;
                        }
                        break;
                }
            }
        }

        // AI Validation
        $runtimeEnableAI = isset($_POST['_dg10_runtime_enable_ai']) ? (bool) sanitize_text_field($_POST['_dg10_runtime_enable_ai']) : null;
        $enableAI = is_bool($runtimeEnableAI) ? $runtimeEnableAI : (
            $this->settings->get_option('enable_deepseek', false) || $this->settings->get_option('enable_gemini', false)
        );

        if ($enableAI && 
            !$this->ai_validator->validate_with_ai($fields)) {
            $this->add_error($ajax_handler);
            return;
        }
    }

    private function validate_honeypot() {
        $honeypot_value = sanitize_text_field($_POST['dg10_hp_check'] ?? '');
        return empty($honeypot_value);
    }

    private function validate_submission_time() {
        $submission_time_raw = $_POST['dg10_submission_time'] ?? '';
        if (empty($submission_time_raw)) {
            return false;
        }

        $submission_time = absint(sanitize_text_field($submission_time_raw));
        $current_time = time() * 1000; // Convert to milliseconds
        $min_time = 3000; // 3 seconds minimum

        return ($current_time - $submission_time) >= $min_time;
    }

    private function validate_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);
        
        // Remove leading + if present
        $phone = ltrim($phone, '+');
        
        // Check for spam numbers
        if (in_array($phone, $this->spam_phones, true)) {
            return false;
        }
        
        // More flexible phone validation - accepts 7-15 digits
        // This covers most international formats
        return preg_match('/^[0-9]{7,15}$/', $phone);
    }

    private function validate_email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, "@"), 1);
        foreach ($this->spam_tlds as $tld) {
            if (preg_match('/\.' . preg_quote($tld, '/') . '$/i', $domain)) {
                return false;
            }
        }

        return true;
    }

    private function contains_spam_keywords($text) {
        $text = strtolower((string) $text);
        foreach ($this->spam_keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function add_error($ajax_handler, $custom_message = null) {
        $message = $custom_message ?: $this->settings->get_option('custom_error_message', 'Invalid form submission detected.');
        $ajax_handler->add_error_message($message);

        // Update statistics
        update_option('dg10_blocked_attempts', intval(get_option('dg10_blocked_attempts', 0)) + 1);
    }

    /**
     * Apply time-based rule overrides
     */
    private function apply_time_based_rules($time_based_rules) {
        // Apply overrides to in-memory properties used during this validation only
        if (isset($time_based_rules['max_submissions_per_hour'])) {
            $_POST['_dg10_runtime_max_per_hour'] = absint(sanitize_text_field($time_based_rules['max_submissions_per_hour']));
        }
        if (isset($time_based_rules['enable_ai_validation'])) {
            $_POST['_dg10_runtime_enable_ai'] = (bool) sanitize_text_field($time_based_rules['enable_ai_validation']);
        }
        if (isset($time_based_rules['enable_geographic_blocking'])) {
            $_POST['_dg10_runtime_enable_geo'] = (bool) sanitize_text_field($time_based_rules['enable_geographic_blocking']);
        }
        if (isset($time_based_rules['min_name_length'])) {
            $_POST['_dg10_runtime_min_name_length'] = absint(sanitize_text_field($time_based_rules['min_name_length']));
        }
    }
}