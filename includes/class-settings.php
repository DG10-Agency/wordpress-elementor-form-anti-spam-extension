<?php
if (!defined('ABSPATH')) exit;

class DG10_Settings {
    private static $instance = null;
    private $option_name = 'dg10_antispam_settings';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function get_option_name() {
        return $this->option_name;
    }

    public function get_option($key, $default = '') {
        $options = get_option($this->option_name, []);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function update_option($key, $value) {
        $options = get_option($this->option_name, []);
        $options[$key] = $value;
        return update_option($this->option_name, $options);
    }

    public function set_default_options() {
        if (!get_option($this->option_name)) {
            update_option($this->option_name, [
                'min_name_length' => 2,
                'max_submissions_per_hour' => 5,
                'enable_honeypot' => true,
                'enable_time_check' => true,
                'enable_spam_keywords' => true,
                'remove_data_on_uninstall' => false,
                'enable_deepseek' => false,
                'enable_gemini' => false,
                'deepseek_api_key' => '',
                'gemini_api_key' => '',
                'custom_error_message' => 'Invalid form submission detected.',
                // Lite Mode defaults
                'enable_lite_mode' => false,
                'lite_form_selector' => '',
                // Geographic Blocking defaults
                'enable_geographic_blocking' => false,
                'geographic_blocking_mode' => 'block',
                'blocked_countries' => [],
                'allowed_countries' => [],
                'geographic_whitelist_ips' => '',
                // Time-based rules defaults
                'enable_time_rules' => false,
                'timezone' => '',
                'enable_business_hours' => false,
                'weekday_start_time' => '09:00',
                'weekday_end_time' => '17:00',
                'enable_weekend_hours' => false,
                'weekend_start_time' => '10:00',
                'weekend_end_time' => '16:00',
                'weekend_days' => [6, 7],
                'enable_holiday_mode' => false,
                'enable_holiday_hours' => false,
                'holiday_start_time' => '10:00',
                'holiday_end_time' => '14:00',
                'holiday_dates' => [],
                'weekday_max_submissions' => 5,
                'weekend_max_submissions' => 3,
                'holiday_max_submissions' => 2,
                'weekday_enable_ai' => false,
                'weekend_enable_ai' => false,
                'holiday_enable_ai' => true,
                'weekday_enable_geo' => false,
                'weekend_enable_geo' => false,
                'holiday_enable_geo' => true,
                'weekday_min_name_length' => 2,
                'weekend_min_name_length' => 2,
                'holiday_min_name_length' => 3,
                'weekday_error_message' => 'Submissions are currently restricted due to business hours.',
                'weekend_error_message' => 'Submissions are currently restricted due to weekend hours.',
                'holiday_error_message' => 'Submissions are currently restricted due to holiday hours.'
            ]);
        }
    }

    public function register_settings() {
        register_setting(
            $this->option_name . '_group',
            $this->option_name,
            [$this, 'sanitize_settings']
        );

        // Basic Settings Section
        add_settings_section(
            'dg10_antispam_basic',
            __('Basic Validation Settings', 'dg10-antispam'),
            null,
            'dg10-antispam'
        );

        $this->add_basic_fields();

        // Lite Mode Section
        add_settings_section(
            'dg10_antispam_lite',
            __('Lite Mode (Elementor Free)', 'dg10-antispam'),
            [$this, 'render_lite_section_description'],
            'dg10-antispam'
        );

        add_settings_field(
            'enable_lite_mode',
            __('Enable Lite Mode for non-Elementor forms', 'dg10-antispam'),
            [$this, 'render_checkbox_field'],
            'dg10-antispam',
            'dg10_antispam_lite',
            ['field' => 'enable_lite_mode']
        );

        add_settings_field(
            'lite_form_selector',
            __('Lite Mode Form Selector (CSS)', 'dg10-antispam'),
            [$this, 'render_text_field'],
            'dg10-antispam',
            'dg10_antispam_lite',
            ['field' => 'lite_form_selector']
        );

        // AI Settings Section
        add_settings_section(
            'dg10_antispam_ai',
            __('AI-Powered Spam Detection', 'dg10-antispam'),
            [$this, 'render_ai_section_description'],
            'dg10-antispam'
        );

        $this->add_ai_fields();

        // Geographic Blocking Section
        add_settings_section(
            'dg10_antispam_geographic',
            __('Geographic Blocking', 'dg10-antispam'),
            [$this, 'render_geographic_section_description'],
            'dg10-antispam'
        );

        $this->add_geographic_fields();

        // Time-based Rules Section
        add_settings_section(
            'dg10_antispam_time_rules',
            __('Time-Based Rules', 'dg10-antispam'),
            [$this, 'render_time_rules_section_description'],
            'dg10-antispam'
        );

        $this->add_time_rules_fields();
    }

    private function add_basic_fields() {
        $basic_fields = [
            'min_name_length' => [
                'title' => __('Minimum Name Length', 'dg10-antispam'),
                'callback' => 'render_number_field',
                'args' => ['min' => 2, 'max' => 50]
            ],
            'max_submissions_per_hour' => [
                'title' => __('Max Submissions per Hour', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_number_field',
                'args' => ['min' => 1, 'max' => 100]
            ],
            'enable_honeypot' => [
                'title' => __('Enable Honeypot', 'dg10-antispam'),
                'callback' => 'render_checkbox_field'
            ],
            'enable_time_check' => [
                'title' => __('Enable Time-based Check', 'dg10-antispam'),
                'callback' => 'render_checkbox_field'
            ],
            'enable_spam_keywords' => [
                'title' => __('Enable Spam Keyword Filter', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'remove_data_on_uninstall' => [
                'title' => __('Remove data on uninstall', 'dg10-antispam'),
                'callback' => 'render_checkbox_field'
            ],
            'custom_error_message' => [
                'title' => __('Custom Error Message', 'dg10-antispam'),
                'callback' => 'render_text_field'
            ]
        ];

        foreach ($basic_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                [$this, $field['callback']],
                'dg10-antispam',
                'dg10_antispam_basic',
                array_merge(['field' => $id], isset($field['args']) ? $field['args'] : [])
            );
        }
    }

    private function add_ai_fields() {
        $ai_fields = [
            'enable_deepseek' => [
                'title' => __('Enable DeepSeek AI', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'deepseek_api_key' => [
                'title' => __('DeepSeek API Key', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_api_key_field'
            ],
            'enable_gemini' => [
                'title' => __('Enable Gemini AI', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'gemini_api_key' => [
                'title' => __('Gemini API Key', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_api_key_field'
            ]
        ];

        foreach ($ai_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                [$this, $field['callback']],
                'dg10-antispam',
                'dg10_antispam_ai',
                ['field' => $id]
            );
        }
    }

    private function add_geographic_fields() {
        $geographic_fields = [
            'enable_geographic_blocking' => [
                'title' => __('Enable Geographic Blocking', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'geographic_blocking_mode' => [
                'title' => __('Blocking Mode', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_select_field',
                'args' => [
                    'options' => [
                        'block' => __('Block specific countries', 'dg10-antispam'),
                        'allow' => __('Allow only specific countries', 'dg10-antispam')
                    ]
                ]
            ],
            'blocked_countries' => [
                'title' => __('Blocked Countries', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_country_multiselect_field'
            ],
            'allowed_countries' => [
                'title' => __('Allowed Countries', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_country_multiselect_field'
            ],
            'geographic_whitelist_ips' => [
                'title' => __('Whitelist IPs (Bypass Geographic Blocking)', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_textarea_field',
                'args' => ['rows' => 3, 'placeholder' => '192.168.1.1\n10.0.0.1\n203.0.113.1']
            ]
        ];

        foreach ($geographic_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                [$this, $field['callback']],
                'dg10-antispam',
                'dg10_antispam_geographic',
                array_merge(['field' => $id], isset($field['args']) ? $field['args'] : [])
            );
        }
    }

    public function render_geographic_section_description() {
        echo '<p>' . esc_html__('Block or allow form submissions based on the visitor\'s country. Uses free GeoIP detection without external API calls.', 'dg10-antispam') . '</p>';
    }

    private function add_time_rules_fields() {
        $time_rules_fields = [
            'enable_time_rules' => [
                'title' => __('Enable Time-Based Rules', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'timezone' => [
                'title' => __('Timezone', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_timezone_field'
            ],
            'enable_business_hours' => [
                'title' => __('Enable Business Hours', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'weekday_start_time' => [
                'title' => __('Weekday Start Time', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_time_field'
            ],
            'weekday_end_time' => [
                'title' => __('Weekday End Time', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_time_field'
            ],
            'enable_weekend_hours' => [
                'title' => __('Enable Weekend Hours', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'weekend_start_time' => [
                'title' => __('Weekend Start Time', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_time_field'
            ],
            'weekend_end_time' => [
                'title' => __('Weekend End Time', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_time_field'
            ],
            'weekend_days' => [
                'title' => __('Weekend Days', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_weekend_days_field'
            ],
            'enable_holiday_mode' => [
                'title' => __('Enable Holiday Mode', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_checkbox_field'
            ],
            'holiday_dates' => [
                'title' => __('Holiday Dates (YYYY-MM-DD)', 'dg10-antispam') . ' <span class="dg10-badge-pro">Pro</span>',
                'callback' => 'render_textarea_field',
                'args' => ['rows' => 3, 'placeholder' => '2024-12-25\n2024-01-01\n2024-07-04']
            ]
        ];

        foreach ($time_rules_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['title'],
                [$this, $field['callback']],
                'dg10-antispam',
                'dg10_antispam_time_rules',
                array_merge(['field' => $id], isset($field['args']) ? $field['args'] : [])
            );
        }
    }

    public function render_time_rules_section_description() {
        echo '<p>' . esc_html__('Configure time-based validation rules including business hours, weekend restrictions, and holiday modes with different security levels.', 'dg10-antispam') . '</p>';
    }

    public function render_ai_section_description() {
        echo '<p>' . esc_html__('Configure AI-powered spam detection using DeepSeek and Gemini. You need to provide API keys to enable these features.', 'dg10-antispam') . '</p>';
    }

    public function render_lite_section_description() {
        echo '<p>' . esc_html__('Lite Mode applies client-side validation (honeypot, time, basic field checks) to selected forms. For server-side enforcement, IP rate limiting, and AI, activate Elementor Pro.', 'dg10-antispam') . '</p>';
    }

    public function render_number_field($args) {
        $field = $args['field'];
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 999;
        $value = $this->get_option($field);

        // Convert array to string if needed (for fields that might be stored as arrays)
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        // Ensure value is a string
        $value = (string) $value;

        printf(
            '<input type="number" min="%d" max="%d" id="%s" name="%s[%s]" value="%s" class="regular-text">',
            esc_attr($min),
            esc_attr($max),
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value)
        );
    }

    public function render_checkbox_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);

        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" %s>',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            checked($value, true, false)
        );
    }

    public function render_text_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);

        // Convert array to string if needed (for fields that might be stored as arrays)
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        // Ensure value is a string
        $value = (string) $value;

        printf(
            '<input type="text" id="%s" name="%s[%s]" value="%s" class="regular-text">',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value)
        );
    }

    public function render_api_key_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);

        // Convert array to string if needed (for fields that might be stored as arrays)
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        // Ensure value is a string
        $value = (string) $value;

        printf(
            '<input type="password" id="%s" name="%s[%s]" value="%s" class="regular-text" autocomplete="off">',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value)
        );
    }

    public function render_select_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);
        $options = $args['options'];

        printf('<select id="%s" name="%s[%s]" class="regular-text">', 
            esc_attr($field), 
            esc_attr($this->option_name), 
            esc_attr($field)
        );

        foreach ($options as $option_value => $option_label) {
            printf('<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }

        echo '</select>';
    }

    public function render_country_multiselect_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field, []);
        $geo_blocker = DG10_Geographic_Blocker::get_instance();
        $countries = $geo_blocker->get_countries_list();

        printf('<select id="%s" name="%s[%s][]" multiple class="regular-text dg10-country-select" style="height: 120px;">', 
            esc_attr($field), 
            esc_attr($this->option_name), 
            esc_attr($field)
        );

        foreach ($countries as $code => $name) {
            $selected = in_array($code, (array)$value) ? 'selected' : '';
            printf('<option value="%s" %s>%s</option>',
                esc_attr($code),
                $selected,
                esc_html($name)
            );
        }

        echo '</select>';
        echo '<p class="description">' . esc_html__('Hold Ctrl/Cmd to select multiple countries.', 'dg10-antispam') . '</p>';
    }

    public function render_textarea_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);
        $rows = isset($args['rows']) ? $args['rows'] : 5;
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';

        // Convert array to string if needed (for fields that might be stored as arrays)
        if (is_array($value)) {
            $value = implode("\n", $value);
        }

        // Ensure value is a string
        $value = (string) $value;

        printf(
            '<textarea id="%s" name="%s[%s]" rows="%d" class="large-text" placeholder="%s">%s</textarea>',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($rows),
            esc_attr($placeholder),
            esc_textarea($value)
        );
    }

    public function render_timezone_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);
        $time_rules = DG10_Time_Rules::get_instance();
        $timezones = $time_rules->get_available_timezones();

        printf('<select id="%s" name="%s[%s]" class="regular-text">', 
            esc_attr($field), 
            esc_attr($this->option_name), 
            esc_attr($field)
        );

        printf('<option value="">%s</option>', esc_html__('Use WordPress default', 'dg10-antispam'));

        foreach ($timezones as $timezone => $label) {
            printf('<option value="%s" %s>%s</option>',
                esc_attr($timezone),
                selected($value, $timezone, false),
                esc_html($label)
            );
        }

        echo '</select>';
    }

    public function render_time_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field);

        printf(
            '<input type="time" id="%s" name="%s[%s]" value="%s" class="regular-text">',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value)
        );
    }

    public function render_weekend_days_field($args) {
        $field = $args['field'];
        $value = $this->get_option($field, []);
        $time_rules = DG10_Time_Rules::get_instance();
        $days = $time_rules->get_day_names();

        printf('<div class="dg10-weekend-days">');
        foreach ($days as $day_num => $day_name) {
            $checked = in_array($day_num, (array)$value) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="%s[%s][]" value="%d" %s> %s</label><br>',
                esc_attr($this->option_name),
                esc_attr($field),
                esc_attr($day_num),
                $checked,
                esc_html($day_name)
            );
        }
        printf('</div>');
    }

    public function sanitize_settings($input) {
        // Validate input is array
        if (!is_array($input)) {
            return $this->get_default_settings();
        }

        $sanitized = [];

        // Basic settings with validation
        $sanitized['min_name_length'] = $this->validate_positive_integer($input['min_name_length'] ?? 2, 1, 50);
        $sanitized['max_submissions_per_hour'] = $this->validate_positive_integer($input['max_submissions_per_hour'] ?? 5, 1, 100);
        $sanitized['enable_honeypot'] = isset($input['enable_honeypot']);
        $sanitized['enable_time_check'] = isset($input['enable_time_check']);
        $sanitized['enable_spam_keywords'] = isset($input['enable_spam_keywords']);
        $sanitized['remove_data_on_uninstall'] = isset($input['remove_data_on_uninstall']);
        $sanitized['custom_error_message'] = $this->sanitize_text_field($input['custom_error_message'] ?? '', 500);

        // Lite settings
        $sanitized['enable_lite_mode'] = isset($input['enable_lite_mode']);
        $sanitized['lite_form_selector'] = $this->sanitize_css_selector($input['lite_form_selector'] ?? '');

        // AI settings with API key validation
        $sanitized['enable_deepseek'] = isset($input['enable_deepseek']);
        $sanitized['enable_gemini'] = isset($input['enable_gemini']);
        $sanitized['deepseek_api_key'] = $this->sanitize_api_key($input['deepseek_api_key'] ?? '');
        $sanitized['gemini_api_key'] = $this->sanitize_api_key($input['gemini_api_key'] ?? '');

        // Geographic settings
        $sanitized['enable_geographic_blocking'] = isset($input['enable_geographic_blocking']);
        $sanitized['geographic_blocking_mode'] = $this->validate_geographic_mode($input['geographic_blocking_mode'] ?? 'block');
        $sanitized['blocked_countries'] = $this->sanitize_country_codes($input['blocked_countries'] ?? []);
        $sanitized['allowed_countries'] = $this->sanitize_country_codes($input['allowed_countries'] ?? []);
        
        // Sanitize whitelist IPs
        $sanitized['geographic_whitelist_ips'] = $this->sanitize_ip_list($input['geographic_whitelist_ips'] ?? '');

        // Time-based rules settings
        $sanitized['enable_time_rules'] = isset($input['enable_time_rules']);
        $sanitized['timezone'] = $this->validate_timezone($input['timezone'] ?? '');
        $sanitized['enable_business_hours'] = isset($input['enable_business_hours']);
        $sanitized['weekday_start_time'] = $this->validate_time_format($input['weekday_start_time'] ?? '09:00');
        $sanitized['weekday_end_time'] = $this->validate_time_format($input['weekday_end_time'] ?? '17:00');
        $sanitized['enable_weekend_hours'] = isset($input['enable_weekend_hours']);
        $sanitized['weekend_start_time'] = $this->validate_time_format($input['weekend_start_time'] ?? '10:00');
        $sanitized['weekend_end_time'] = $this->validate_time_format($input['weekend_end_time'] ?? '16:00');
        $sanitized['weekend_days'] = $this->sanitize_weekend_days($input['weekend_days'] ?? [6, 7]);
        $sanitized['enable_holiday_mode'] = isset($input['enable_holiday_mode']);
        $sanitized['enable_holiday_hours'] = isset($input['enable_holiday_hours']);
        $sanitized['holiday_start_time'] = $this->validate_time_format($input['holiday_start_time'] ?? '10:00');
        $sanitized['holiday_end_time'] = $this->validate_time_format($input['holiday_end_time'] ?? '14:00');
        
        // Sanitize holiday dates
        $sanitized['holiday_dates'] = $this->sanitize_holiday_dates($input['holiday_dates'] ?? '');

        // Time-based rule overrides
        $sanitized['weekday_max_submissions'] = $this->validate_positive_integer($input['weekday_max_submissions'] ?? 5, 1, 100);
        $sanitized['weekend_max_submissions'] = $this->validate_positive_integer($input['weekend_max_submissions'] ?? 3, 1, 100);
        $sanitized['holiday_max_submissions'] = $this->validate_positive_integer($input['holiday_max_submissions'] ?? 2, 1, 100);
        $sanitized['weekday_enable_ai'] = isset($input['weekday_enable_ai']);
        $sanitized['weekend_enable_ai'] = isset($input['weekend_enable_ai']);
        $sanitized['holiday_enable_ai'] = isset($input['holiday_enable_ai']);
        $sanitized['weekday_enable_geo'] = isset($input['weekday_enable_geo']);
        $sanitized['weekend_enable_geo'] = isset($input['weekend_enable_geo']);
        $sanitized['holiday_enable_geo'] = isset($input['holiday_enable_geo']);
        $sanitized['weekday_min_name_length'] = $this->validate_positive_integer($input['weekday_min_name_length'] ?? 2, 1, 50);
        $sanitized['weekend_min_name_length'] = $this->validate_positive_integer($input['weekend_min_name_length'] ?? 2, 1, 50);
        $sanitized['holiday_min_name_length'] = $this->validate_positive_integer($input['holiday_min_name_length'] ?? 3, 1, 50);
        $sanitized['weekday_error_message'] = $this->sanitize_text_field($input['weekday_error_message'] ?? '', 500);
        $sanitized['weekend_error_message'] = $this->sanitize_text_field($input['weekend_error_message'] ?? '', 500);
        $sanitized['holiday_error_message'] = $this->sanitize_text_field($input['holiday_error_message'] ?? '', 500);

        return $sanitized;
    }

    public function get_frontend_settings() {
        return [
            'min_name_length' => $this->get_option('min_name_length', 2),
            'enable_honeypot' => $this->get_option('enable_honeypot', true),
            'enable_time_check' => $this->get_option('enable_time_check', true),
            'min_submission_time' => 3000,
            'custom_error_message' => $this->get_option('custom_error_message', 'Invalid form submission detected.'),
            // Lite exposure
            'enable_lite_mode' => $this->get_option('enable_lite_mode', false),
            'lite_form_selector' => $this->get_option('lite_form_selector', '')
        ];
    }

    /**
     * Get default settings
     */
    private function get_default_settings() {
        return [
            'min_name_length' => 2,
            'max_submissions_per_hour' => 5,
            'enable_honeypot' => true,
            'enable_time_check' => true,
            'enable_spam_keywords' => true,
            'remove_data_on_uninstall' => false,
            'enable_deepseek' => false,
            'enable_gemini' => false,
            'deepseek_api_key' => '',
            'gemini_api_key' => '',
            'custom_error_message' => 'Invalid form submission detected.',
            'enable_lite_mode' => false,
            'lite_form_selector' => '',
            'enable_geographic_blocking' => false,
            'geographic_blocking_mode' => 'block',
            'blocked_countries' => [],
            'allowed_countries' => [],
            'geographic_whitelist_ips' => '',
            'enable_time_rules' => false,
            'timezone' => '',
            'enable_business_hours' => false,
            'weekday_start_time' => '09:00',
            'weekday_end_time' => '17:00',
            'enable_weekend_hours' => false,
            'weekend_start_time' => '10:00',
            'weekend_end_time' => '16:00',
            'weekend_days' => [6, 7],
            'enable_holiday_mode' => false,
            'enable_holiday_hours' => false,
            'holiday_start_time' => '10:00',
            'holiday_end_time' => '14:00',
            'holiday_dates' => [],
            'weekday_max_submissions' => 5,
            'weekend_max_submissions' => 3,
            'holiday_max_submissions' => 2,
            'weekday_enable_ai' => false,
            'weekend_enable_ai' => false,
            'holiday_enable_ai' => true,
            'weekday_enable_geo' => false,
            'weekend_enable_geo' => false,
            'holiday_enable_geo' => true,
            'weekday_min_name_length' => 2,
            'weekend_min_name_length' => 2,
            'holiday_min_name_length' => 3,
            'weekday_error_message' => 'Submissions are currently restricted due to business hours.',
            'weekend_error_message' => 'Submissions are currently restricted due to weekend hours.',
            'holiday_error_message' => 'Submissions are currently restricted due to holiday hours.'
        ];
    }

    /**
     * Validate positive integer with min/max bounds
     */
    private function validate_positive_integer($value, $min = 1, $max = 999) {
        $value = absint($value);
        return max($min, min($max, $value));
    }

    /**
     * Sanitize text field with length limit
     */
    private function sanitize_text_field($value, $max_length = 255) {
        $value = sanitize_text_field($value);
        return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
    }

    /**
     * Sanitize CSS selector
     */
    private function sanitize_css_selector($selector) {
        $selector = sanitize_text_field($selector);
        // Basic CSS selector validation - allow alphanumeric, spaces, dots, hashes, brackets, colons, dashes, underscores
        if (!preg_match('/^[a-zA-Z0-9\s\.#\[\]:\-_]*$/', $selector)) {
            return '';
        }
        return $selector;
    }

    /**
     * Sanitize API key
     */
    private function sanitize_api_key($key) {
        $key = sanitize_text_field($key);
        // Basic API key validation - alphanumeric and common special characters
        if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $key)) {
            return '';
        }
        return $key;
    }

    /**
     * Validate geographic blocking mode
     */
    private function validate_geographic_mode($mode) {
        $valid_modes = ['block', 'allow'];
        return in_array($mode, $valid_modes, true) ? $mode : 'block';
    }

    /**
     * Sanitize country codes
     */
    private function sanitize_country_codes($countries) {
        if (!is_array($countries)) {
            return [];
        }
        
        $valid_codes = [];
        foreach ($countries as $country) {
            $country = sanitize_text_field($country);
            if (preg_match('/^[A-Z]{2}$/', $country)) {
                $valid_codes[] = $country;
            }
        }
        return $valid_codes;
    }

    /**
     * Sanitize IP list
     */
    private function sanitize_ip_list($ip_list) {
        $ips = array_filter(array_map('trim', explode("\n", $ip_list)));
        $valid_ips = [];
        
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $valid_ips[] = sanitize_text_field($ip);
            }
        }
        
        return implode("\n", $valid_ips);
    }

    /**
     * Validate timezone
     */
    private function validate_timezone($timezone) {
        if (empty($timezone)) {
            return '';
        }
        
        $timezone = sanitize_text_field($timezone);
        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : '';
    }

    /**
     * Validate time format (HH:MM)
     */
    private function validate_time_format($time) {
        $time = sanitize_text_field($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
            return $time;
        }
        return '00:00';
    }

    /**
     * Sanitize weekend days
     */
    private function sanitize_weekend_days($days) {
        if (!is_array($days)) {
            return [6, 7];
        }
        
        $valid_days = [];
        foreach ($days as $day) {
            $day = absint($day);
            if ($day >= 1 && $day <= 7) {
                $valid_days[] = $day;
            }
        }
        
        return !empty($valid_days) ? $valid_days : [6, 7];
    }

    /**
     * Sanitize holiday dates
     */
    private function sanitize_holiday_dates($dates) {
        if (is_string($dates)) {
            $dates = array_filter(array_map('trim', explode("\n", $dates)));
        } elseif (!is_array($dates)) {
            return '';
        }
        
        $valid_dates = [];
        foreach ($dates as $date) {
            $date = sanitize_text_field($date);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $valid_dates[] = $date;
            }
        }
        
        return implode("\n", $valid_dates);
    }
}