<?php
if (!defined('ABSPATH')) exit;

class DG10_Preset_Manager {
    private static $instance = null;
    private $settings;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
        add_action('wp_ajax_dg10_apply_preset', [$this, 'ajax_apply_preset']);
        add_action('wp_ajax_dg10_get_current_preset', [$this, 'ajax_get_current_preset']);
    }

    /**
     * Get all available presets
     */
    public function get_presets() {
        return [
            'strict' => [
                'name' => __('Strict Mode', 'dg10-antispam'),
                'description' => __('Maximum protection with all features enabled and low thresholds', 'dg10-antispam'),
                'icon' => '🔒',
                'settings' => [
                    'min_name_length' => 3,
                    'max_submissions_per_hour' => 2,
                    'enable_honeypot' => true,
                    'enable_time_check' => true,
                    'enable_spam_keywords' => true,
                    'enable_deepseek' => true,
                    'enable_gemini' => true,
                    'custom_error_message' => __('Your submission has been blocked due to security policies.', 'dg10-antispam'),
                    'enable_lite_mode' => true,
                    'lite_form_selector' => 'form'
                ]
            ],
            'balanced' => [
                'name' => __('Balanced Mode', 'dg10-antispam'),
                'description' => __('Recommended settings providing good protection with minimal user friction', 'dg10-antispam'),
                'icon' => '⚖️',
                'settings' => [
                    'min_name_length' => 2,
                    'max_submissions_per_hour' => 5,
                    'enable_honeypot' => true,
                    'enable_time_check' => true,
                    'enable_spam_keywords' => true,
                    'enable_deepseek' => false,
                    'enable_gemini' => false,
                    'custom_error_message' => __('Invalid form submission detected.', 'dg10-antispam'),
                    'enable_lite_mode' => false,
                    'lite_form_selector' => ''
                ]
            ],
            'light' => [
                'name' => __('Light Mode', 'dg10-antispam'),
                'description' => __('Minimal interference with basic protection only', 'dg10-antispam'),
                'icon' => '🛡️',
                'settings' => [
                    'min_name_length' => 2,
                    'max_submissions_per_hour' => 10,
                    'enable_honeypot' => true,
                    'enable_time_check' => false,
                    'enable_spam_keywords' => false,
                    'enable_deepseek' => false,
                    'enable_gemini' => false,
                    'custom_error_message' => __('Please check your form submission.', 'dg10-antispam'),
                    'enable_lite_mode' => false,
                    'lite_form_selector' => ''
                ]
            ]
        ];
    }

    /**
     * Get current preset based on settings
     */
    public function get_current_preset() {
        $current_settings = $this->get_current_settings();
        $presets = $this->get_presets();
        
        foreach ($presets as $preset_id => $preset) {
            if ($this->settings_match($current_settings, $preset['settings'])) {
                return $preset_id;
            }
        }
        
        return 'custom';
    }

    /**
     * Apply a preset to settings
     */
    public function apply_preset($preset_id) {
        // Validate preset ID
        if (empty($preset_id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $preset_id)) {
            return false;
        }
        
        $presets = $this->get_presets();
        
        if (!isset($presets[$preset_id])) {
            return false;
        }
        
        $preset_settings = $presets[$preset_id]['settings'];
        $current_settings = $this->get_current_settings();
        
        // Validate preset settings before applying
        if (!$this->validate_preset_settings($preset_settings)) {
            return false;
        }
        
        // Merge preset settings with current settings (preserve API keys)
        $new_settings = array_merge($current_settings, $preset_settings);
        
        // Preserve API keys if they exist
        if (!empty($current_settings['deepseek_api_key'])) {
            $new_settings['deepseek_api_key'] = $current_settings['deepseek_api_key'];
        }
        if (!empty($current_settings['gemini_api_key'])) {
            $new_settings['gemini_api_key'] = $current_settings['gemini_api_key'];
        }
        
        // Sanitize all settings before saving
        $new_settings = $this->sanitize_settings($new_settings);
        
        $result = update_option($this->settings->get_option_name(), $new_settings);
        
        // Treat "no change" as success so UX isn't confusing when preset equals current
        if (!$result) {
            $after = get_option($this->settings->get_option_name(), []);
            if ($after === $new_settings) {
                $result = true;
            }
        }
        
        return $result;
    }

    /**
     * Get current settings array
     */
    private function get_current_settings() {
        return get_option($this->settings->get_option_name(), []);
    }

    /**
     * Check if settings match a preset
     */
    private function settings_match($current_settings, $preset_settings) {
        foreach ($preset_settings as $key => $value) {
            // Skip API keys in comparison
            if (in_array($key, ['deepseek_api_key', 'gemini_api_key'])) {
                continue;
            }
            
            $current_value = isset($current_settings[$key]) ? $current_settings[$key] : '';
            
            // Handle boolean values
            if (is_bool($value)) {
                $current_value = (bool) $current_value;
            }
            
            if ($current_value !== $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get preset information
     */
    public function get_preset_info($preset_id) {
        $presets = $this->get_presets();
        return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
    }

    /**
     * AJAX handler for applying presets
     */
    public function ajax_apply_preset() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }
        
        // Sanitize and validate input
        $preset_id = sanitize_text_field($_POST['preset_id'] ?? '');
        
        if (empty($preset_id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $preset_id)) {
            wp_send_json_error(['message' => __('Invalid preset ID.', 'dg10-antispam')], 400);
        }
        
        try {
            $result = $this->apply_preset($preset_id);
            
            if ($result) {
                $preset_info = $this->get_preset_info($preset_id);
                if ($preset_info) {
                    wp_send_json_success([
                        'message' => sprintf(__('Preset "%s" applied successfully!', 'dg10-antispam'), esc_html($preset_info['name'])),
                        'preset_id' => sanitize_text_field($preset_id),
                        'preset_name' => esc_html($preset_info['name'])
                    ]);
                } else {
                    wp_send_json_error(['message' => __('Preset information not found.', 'dg10-antispam')], 404);
                }
            } else {
                wp_send_json_error(['message' => __('Failed to apply preset.', 'dg10-antispam')], 500);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while applying the preset.', 'dg10-antispam')], 500);
        }
    }

    /**
     * AJAX handler for getting current preset
     */
    public function ajax_get_current_preset() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }
        
        try {
            $current_preset = $this->get_current_preset();
            $preset_info = $current_preset === 'custom' ? [
                'name' => __('Custom Mode', 'dg10-antispam'),
                'description' => __('Your current custom settings', 'dg10-antispam'),
                'icon' => '⚙️'
            ] : $this->get_preset_info($current_preset);
            
            // Sanitize output
            $sanitized_info = [
                'name' => esc_html($preset_info['name'] ?? ''),
                'description' => esc_html($preset_info['description'] ?? ''),
                'icon' => esc_html($preset_info['icon'] ?? '')
            ];
            
            wp_send_json_success([
                'preset_id' => sanitize_text_field($current_preset),
                'preset_info' => $sanitized_info
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to retrieve preset information.', 'dg10-antispam')], 500);
        }
    }

    /**
     * Get preset recommendations based on current setup
     */
    public function get_preset_recommendations() {
        $has_elementor_pro = class_exists('\\ElementorPro\\Plugin');
        $has_ai_keys = !empty($this->settings->get_option('deepseek_api_key')) || 
                      !empty($this->settings->get_option('gemini_api_key'));
        
        $recommendations = [];
        
        if ($has_elementor_pro && $has_ai_keys) {
            $recommendations[] = 'strict';
        } elseif ($has_elementor_pro) {
            $recommendations[] = 'balanced';
        } else {
            $recommendations[] = 'light';
        }
        
        return $recommendations;
    }

    /**
     * Validate preset settings before applying
     */
    private function validate_preset_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }
        
        // Define allowed setting keys
        $allowed_keys = [
            'min_name_length', 'max_submissions_per_hour', 'enable_honeypot',
            'enable_time_check', 'enable_spam_keywords', 'enable_deepseek',
            'enable_gemini', 'custom_error_message', 'enable_lite_mode',
            'lite_form_selector', 'enable_geographic_blocking', 'geographic_blocking_mode',
            'blocked_countries', 'allowed_countries', 'geographic_whitelist_ips',
            'enable_time_rules', 'timezone', 'enable_business_hours',
            'weekday_start_time', 'weekday_end_time', 'enable_weekend_hours',
            'weekend_start_time', 'weekend_end_time', 'weekend_days',
            'enable_holiday_mode', 'enable_holiday_hours', 'holiday_start_time',
            'holiday_end_time', 'holiday_dates', 'weekday_max_submissions',
            'weekend_max_submissions', 'holiday_max_submissions', 'weekday_enable_ai',
            'weekend_enable_ai', 'holiday_enable_ai', 'weekday_enable_geo',
            'weekend_enable_geo', 'holiday_enable_geo', 'weekday_min_name_length',
            'weekend_min_name_length', 'holiday_min_name_length', 'weekday_error_message',
            'weekend_error_message', 'holiday_error_message', 'remove_data_on_uninstall'
        ];
        
        // Check if all keys are allowed
        foreach (array_keys($settings) as $key) {
            if (!in_array($key, $allowed_keys, true)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Sanitize settings array
     */
    private function sanitize_settings($settings) {
        if (!is_array($settings)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'min_name_length':
                case 'max_submissions_per_hour':
                case 'weekday_max_submissions':
                case 'weekend_max_submissions':
                case 'holiday_max_submissions':
                case 'weekday_min_name_length':
                case 'weekend_min_name_length':
                case 'holiday_min_name_length':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'enable_honeypot':
                case 'enable_time_check':
                case 'enable_spam_keywords':
                case 'enable_deepseek':
                case 'enable_gemini':
                case 'enable_lite_mode':
                case 'enable_geographic_blocking':
                case 'enable_time_rules':
                case 'enable_business_hours':
                case 'enable_weekend_hours':
                case 'enable_holiday_mode':
                case 'enable_holiday_hours':
                case 'weekday_enable_ai':
                case 'weekend_enable_ai':
                case 'holiday_enable_ai':
                case 'weekday_enable_geo':
                case 'weekend_enable_geo':
                case 'holiday_enable_geo':
                case 'remove_data_on_uninstall':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                case 'blocked_countries':
                case 'allowed_countries':
                case 'weekend_days':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                    break;
                    
                case 'holiday_dates':
                    if (is_string($value)) {
                        $dates = array_filter(array_map('trim', explode("\n", $value)));
                        $sanitized[$key] = array_filter($dates, function($date) {
                            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
                        });
                    } else {
                        $sanitized[$key] = [];
                    }
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
}
