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

    public function set_default_options() {
        if (!get_option($this->option_name)) {
            update_option($this->option_name, [
                'min_name_length' => 2,
                'max_submissions_per_hour' => 5,
                'enable_honeypot' => true,
                'enable_time_check' => true,
                'enable_spam_keywords' => true,
                'enable_deepseek' => false,
                'enable_gemini' => false,
                'deepseek_api_key' => '',
                'gemini_api_key' => '',
                'custom_error_message' => 'Invalid form submission detected.',
                // Lite Mode defaults
                'enable_lite_mode' => false,
                'lite_form_selector' => ''
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

        printf(
            '<input type="password" id="%s" name="%s[%s]" value="%s" class="regular-text" autocomplete="off">',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value)
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];

        // Basic settings
        $sanitized['min_name_length'] = absint(isset($input['min_name_length']) ? $input['min_name_length'] : 2);
        $sanitized['max_submissions_per_hour'] = absint(isset($input['max_submissions_per_hour']) ? $input['max_submissions_per_hour'] : 5);
        $sanitized['enable_honeypot'] = isset($input['enable_honeypot']);
        $sanitized['enable_time_check'] = isset($input['enable_time_check']);
        $sanitized['enable_spam_keywords'] = isset($input['enable_spam_keywords']);
        $sanitized['custom_error_message'] = sanitize_text_field(isset($input['custom_error_message']) ? $input['custom_error_message'] : '');

        // Lite settings
        $sanitized['enable_lite_mode'] = isset($input['enable_lite_mode']);
        $sanitized['lite_form_selector'] = sanitize_text_field(isset($input['lite_form_selector']) ? $input['lite_form_selector'] : '');

        // AI settings
        $sanitized['enable_deepseek'] = isset($input['enable_deepseek']);
        $sanitized['enable_gemini'] = isset($input['enable_gemini']);
        $sanitized['deepseek_api_key'] = sanitize_text_field(isset($input['deepseek_api_key']) ? $input['deepseek_api_key'] : '');
        $sanitized['gemini_api_key'] = sanitize_text_field(isset($input['gemini_api_key']) ? $input['gemini_api_key'] : '');

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
}