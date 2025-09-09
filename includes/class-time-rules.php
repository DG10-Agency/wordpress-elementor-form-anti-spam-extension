<?php
if (!defined('ABSPATH')) exit;

class DG10_Time_Rules {
    private static $instance = null;
    private $settings;
    private $timezone;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
        $this->init_timezone();
        add_action('wp_ajax_dg10_get_time_stats', [$this, 'ajax_get_time_stats']);
    }

    /**
     * Initialize timezone
     */
    private function init_timezone() {
        $timezone_string = $this->settings->get_option('timezone', '');
        
        if (!empty($timezone_string)) {
            // Validate timezone string
            $timezone_string = sanitize_text_field($timezone_string);
            
            try {
                // Validate timezone identifier
                if (in_array($timezone_string, DateTimeZone::listIdentifiers(), true)) {
                    $this->timezone = new DateTimeZone($timezone_string);
                } else {
                    throw new Exception('Invalid timezone identifier');
                }
            } catch (Exception $e) {
                // Log error and fallback to WordPress default
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('DG10 Time Rules: Invalid timezone "' . $timezone_string . '": ' . $e->getMessage());
                }
                $this->timezone = wp_timezone();
            }
        } else {
            $this->timezone = wp_timezone();
        }
    }

    /**
     * Get current time in configured timezone
     */
    public function get_current_time() {
        return new DateTime('now', $this->timezone);
    }

    /**
     * Check if current time is within business hours
     */
    public function is_business_hours() {
        if (!$this->settings->get_option('enable_business_hours', false)) {
            return true; // If business hours not enabled, always allow
        }

        $current_time = $this->get_current_time();
        $day_of_week = $current_time->format('N'); // 1 = Monday, 7 = Sunday
        $current_hour = (int) $current_time->format('H');
        $current_minute = (int) $current_time->format('i');
        $current_time_minutes = $current_hour * 60 + $current_minute;

        // Check if it's a weekend
        if ($this->is_weekend($day_of_week)) {
            return $this->is_weekend_business_hours($current_time_minutes);
        }

        // Check if it's a holiday
        if ($this->is_holiday($current_time)) {
            return $this->is_holiday_business_hours($current_time_minutes);
        }

        // Regular weekday business hours
        return $this->is_weekday_business_hours($current_time_minutes);
    }

    /**
     * Check if it's a weekend
     */
    private function is_weekend($day_of_week) {
        $weekend_days = $this->settings->get_option('weekend_days', [6, 7]); // Saturday, Sunday
        return in_array($day_of_week, $weekend_days);
    }

    /**
     * Check if it's a holiday
     */
    private function is_holiday($current_time) {
        if (!$this->settings->get_option('enable_holiday_mode', false)) {
            return false;
        }

        $holidays = $this->settings->get_option('holiday_dates', []);
        $current_date = $current_time->format('Y-m-d');
        
        // Validate holidays array
        if (!is_array($holidays)) {
            return false;
        }
        
        // Sanitize and validate holiday dates
        $valid_holidays = [];
        foreach ($holidays as $holiday) {
            $holiday = sanitize_text_field($holiday);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $holiday)) {
                $valid_holidays[] = $holiday;
            }
        }
        
        return in_array($current_date, $valid_holidays, true);
    }

    /**
     * Check weekday business hours
     */
    private function is_weekday_business_hours($current_time_minutes) {
        $start_time = $this->parse_time($this->settings->get_option('weekday_start_time', '09:00'));
        $end_time = $this->parse_time($this->settings->get_option('weekday_end_time', '17:00'));
        
        return $current_time_minutes >= $start_time && $current_time_minutes <= $end_time;
    }

    /**
     * Check weekend business hours
     */
    private function is_weekend_business_hours($current_time_minutes) {
        if (!$this->settings->get_option('enable_weekend_hours', false)) {
            return false; // No weekend hours by default
        }

        $start_time = $this->parse_time($this->settings->get_option('weekend_start_time', '10:00'));
        $end_time = $this->parse_time($this->settings->get_option('weekend_end_time', '16:00'));
        
        return $current_time_minutes >= $start_time && $current_time_minutes <= $end_time;
    }

    /**
     * Check holiday business hours
     */
    private function is_holiday_business_hours($current_time_minutes) {
        if (!$this->settings->get_option('enable_holiday_hours', false)) {
            return false; // No holiday hours by default
        }

        $start_time = $this->parse_time($this->settings->get_option('holiday_start_time', '10:00'));
        $end_time = $this->parse_time($this->settings->get_option('holiday_end_time', '14:00'));
        
        return $current_time_minutes >= $start_time && $current_time_minutes <= $end_time;
    }

    /**
     * Parse time string to minutes
     */
    private function parse_time($time_string) {
        // Sanitize and validate time string
        $time_string = sanitize_text_field($time_string);
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $time_string)) {
            return 0; // Invalid time format
        }
        
        $parts = explode(':', $time_string);
        $hours = absint($parts[0]);
        $minutes = isset($parts[1]) ? absint($parts[1]) : 0;
        
        // Validate hours and minutes
        if ($hours > 23 || $minutes > 59) {
            return 0;
        }
        
        return $hours * 60 + $minutes;
    }

    /**
     * Get time-based validation rules
     */
    public function get_time_based_rules() {
        $current_time = $this->get_current_time();
        $day_of_week = $current_time->format('N');
        $is_weekend = $this->is_weekend($day_of_week);
        $is_holiday = $this->is_holiday($current_time);
        $is_business_hours = $this->is_business_hours();

        $rules = [
            'is_business_hours' => $is_business_hours,
            'is_weekend' => $is_weekend,
            'is_holiday' => $is_holiday,
            'day_of_week' => $day_of_week,
            'current_time' => $current_time->format('H:i'),
            'timezone' => $this->timezone->getName()
        ];

        // Apply time-based rule modifications
        if ($is_holiday) {
            $rules = array_merge($rules, $this->get_holiday_rules());
        } elseif ($is_weekend) {
            $rules = array_merge($rules, $this->get_weekend_rules());
        } else {
            $rules = array_merge($rules, $this->get_weekday_rules());
        }

        return $rules;
    }

    /**
     * Get holiday-specific rules
     */
    private function get_holiday_rules() {
        return [
            'max_submissions_per_hour' => $this->settings->get_option('holiday_max_submissions', 2),
            'enable_ai_validation' => $this->settings->get_option('holiday_enable_ai', true),
            'enable_geographic_blocking' => $this->settings->get_option('holiday_enable_geo', true),
            'min_name_length' => $this->settings->get_option('holiday_min_name_length', 3),
            'custom_error_message' => $this->settings->get_option('holiday_error_message', 'Submissions are currently restricted due to holiday hours.')
        ];
    }

    /**
     * Get weekend-specific rules
     */
    private function get_weekend_rules() {
        return [
            'max_submissions_per_hour' => $this->settings->get_option('weekend_max_submissions', 3),
            'enable_ai_validation' => $this->settings->get_option('weekend_enable_ai', false),
            'enable_geographic_blocking' => $this->settings->get_option('weekend_enable_geo', false),
            'min_name_length' => $this->settings->get_option('weekend_min_name_length', 2),
            'custom_error_message' => $this->settings->get_option('weekend_error_message', 'Submissions are currently restricted due to weekend hours.')
        ];
    }

    /**
     * Get weekday-specific rules
     */
    private function get_weekday_rules() {
        return [
            'max_submissions_per_hour' => $this->settings->get_option('weekday_max_submissions', 5),
            'enable_ai_validation' => $this->settings->get_option('weekday_enable_ai', false),
            'enable_geographic_blocking' => $this->settings->get_option('weekday_enable_geo', false),
            'min_name_length' => $this->settings->get_option('weekday_min_name_length', 2),
            'custom_error_message' => $this->settings->get_option('weekday_error_message', 'Submissions are currently restricted due to business hours.')
        ];
    }

    /**
     * Check if submission should be blocked based on time rules
     */
    public function is_submission_blocked_by_time() {
        if (!$this->settings->get_option('enable_time_rules', false)) {
            return false;
        }

        $rules = $this->get_time_based_rules();
        
        // Block if outside business hours
        if (!$rules['is_business_hours']) {
            return true;
        }

        return false;
    }

    /**
     * Get available timezones
     */
    public function get_available_timezones() {
        $timezones = [];
        $timezone_identifiers = DateTimeZone::listIdentifiers();

        foreach ($timezone_identifiers as $timezone) {
            $dt = new DateTime('now', new DateTimeZone($timezone));
            $offset = $dt->format('P');
            $timezones[$timezone] = $timezone . ' (UTC' . $offset . ')';
        }

        return $timezones;
    }

    /**
     * Get day names
     */
    public function get_day_names() {
        return [
            1 => __('Monday', 'dg10-antispam'),
            2 => __('Tuesday', 'dg10-antispam'),
            3 => __('Wednesday', 'dg10-antispam'),
            4 => __('Thursday', 'dg10-antispam'),
            5 => __('Friday', 'dg10-antispam'),
            6 => __('Saturday', 'dg10-antispam'),
            7 => __('Sunday', 'dg10-antispam')
        ];
    }

    /**
     * Log time-based submission
     */
    public function log_time_submission($ip, $form_name = '') {
        $current_time = $this->get_current_time();
        $rules = $this->get_time_based_rules();
        
        $time_stats = get_option('dg10_time_stats', []);
        
        $time_key = $current_time->format('Y-m-d H:00'); // Hourly buckets
        if (!isset($time_stats[$time_key])) {
            $time_stats[$time_key] = [
                'submissions' => 0,
                'blocked' => 0,
                'is_weekend' => $rules['is_weekend'],
                'is_holiday' => $rules['is_holiday'],
                'is_business_hours' => $rules['is_business_hours']
            ];
        }
        
        $time_stats[$time_key]['submissions']++;
        
        // Clean old stats (keep last 30 days)
        $cutoff_date = $current_time->modify('-30 days')->format('Y-m-d');
        foreach ($time_stats as $key => $stats) {
            if ($key < $cutoff_date) {
                unset($time_stats[$key]);
            }
        }
        
        update_option('dg10_time_stats', $time_stats);
    }

    /**
     * Get time-based statistics
     */
    public function get_time_stats() {
        return get_option('dg10_time_stats', []);
    }

    /**
     * AJAX handler for getting time statistics
     */
    public function ajax_get_time_stats() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }
        
        try {
            $stats = $this->get_time_stats();
            $current_rules = $this->get_time_based_rules();
            
            // Sanitize stats data
            $sanitized_stats = [];
            if (is_array($stats)) {
                foreach ($stats as $key => $data) {
                    $sanitized_stats[sanitize_text_field($key)] = [
                        'submissions' => intval($data['submissions'] ?? 0),
                        'blocked' => intval($data['blocked'] ?? 0),
                        'is_weekend' => (bool) ($data['is_weekend'] ?? false),
                        'is_holiday' => (bool) ($data['is_holiday'] ?? false),
                        'is_business_hours' => (bool) ($data['is_business_hours'] ?? false)
                    ];
                }
            }
            
            // Sanitize current rules
            $sanitized_rules = [
                'is_business_hours' => (bool) ($current_rules['is_business_hours'] ?? false),
                'is_weekend' => (bool) ($current_rules['is_weekend'] ?? false),
                'is_holiday' => (bool) ($current_rules['is_holiday'] ?? false),
                'day_of_week' => intval($current_rules['day_of_week'] ?? 1),
                'current_time' => sanitize_text_field($current_rules['current_time'] ?? ''),
                'timezone' => sanitize_text_field($current_rules['timezone'] ?? '')
            ];
            
            wp_send_json_success([
                'stats' => $sanitized_stats,
                'current_rules' => $sanitized_rules
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to retrieve time statistics.', 'dg10-antispam')], 500);
        }
    }

    /**
     * Get next business hours opening time
     */
    public function get_next_business_hours() {
        $current_time = $this->get_current_time();
        $day_of_week = $current_time->format('N');
        
        // If it's weekend and weekend hours are disabled
        if ($this->is_weekend($day_of_week) && !$this->settings->get_option('enable_weekend_hours', false)) {
            // Find next weekday
            $next_weekday = clone $current_time;
            while ($this->is_weekend($next_weekday->format('N'))) {
                $next_weekday->modify('+1 day');
            }
            $next_weekday->setTime(
                $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) / 60,
                $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) % 60
            );
            return $next_weekday;
        }
        
        // If it's holiday and holiday hours are disabled
        if ($this->is_holiday($current_time) && !$this->settings->get_option('enable_holiday_hours', false)) {
            // Find next non-holiday day
            $next_day = clone $current_time;
            $next_day->modify('+1 day');
            while ($this->is_holiday($next_day)) {
                $next_day->modify('+1 day');
            }
            $next_day->setTime(
                $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) / 60,
                $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) % 60
            );
            return $next_day;
        }
        
        // Otherwise, find next business hours opening
        $next_opening = clone $current_time;
        $next_opening->modify('+1 day');
        $next_opening->setTime(
            $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) / 60,
            $this->parse_time($this->settings->get_option('weekday_start_time', '09:00')) % 60
        );
        
        return $next_opening;
    }
}
