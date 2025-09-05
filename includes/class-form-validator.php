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

        // IP-based rate limiting
        $ip_manager = DG10_IP_Manager::get_instance();
        $ip = $ip_manager->get_client_ip();
        $max_per_hour = absint($this->settings->get_option('max_submissions_per_hour', 5));
        if ($ip && $max_per_hour > 0) {
            $ip_manager->clean_old_submissions();
            if ($ip_manager->is_submission_rate_exceeded($ip, $max_per_hour)) {
                $this->add_error($ajax_handler);
                return;
            }
        }

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
                        if (is_string($field_value) && strlen(trim($field_value)) < $this->settings->get_option('min_name_length', 2)) {
                            $this->add_error($ajax_handler);
                            return;
                        }
                        break;
                }
            }
        }

        // AI Validation
        if (($this->settings->get_option('enable_deepseek', false) || 
             $this->settings->get_option('enable_gemini', false)) && 
            !$this->ai_validator->validate_with_ai($fields)) {
            $this->add_error($ajax_handler);
            return;
        }
    }

    private function validate_honeypot() {
        return !isset($_POST['dg10_hp_check']) || empty($_POST['dg10_hp_check']);
    }

    private function validate_submission_time() {
        if (!isset($_POST['dg10_submission_time'])) {
            return false;
        }

        $submission_time = absint($_POST['dg10_submission_time']);
        $current_time = time() * 1000; // Convert to milliseconds
        $min_time = 3000; // 3 seconds minimum

        return ($current_time - $submission_time) >= $min_time;
    }

    private function validate_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        return preg_match('/^[6-9]\d{9}$/', $phone) && !in_array($phone, $this->spam_phones, true);
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

    private function add_error($ajax_handler) {
        $message = $this->settings->get_option('custom_error_message', 'Invalid form submission detected.');
        $ajax_handler->add_error_message($message);

        // Update statistics
        update_option('dg10_blocked_attempts', intval(get_option('dg10_blocked_attempts', 0)) + 1);
    }
}