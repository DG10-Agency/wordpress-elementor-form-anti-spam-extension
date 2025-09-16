<?php
/**
 * Plugin Name: DG10 Elementor Form Anti-Spam
 * Plugin URI: https://wordpress.org/plugins/dg10-elementor-form-anti-spam/
 * Description: Advanced form validation and spam protection for Elementor Pro forms with AI-powered spam detection, honeypot fields, and geographic blocking.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: DG10 Agency
 * Author URI: https://www.dg10.agency
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dg10-antispam
 * Domain Path: /languages
 * Network: false
 * Update URI: https://wordpress.org/plugins/dg10-elementor-form-anti-spam/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DG10_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DG10_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DG10_VERSION', '1.0.0');
define('DG10_PLUGIN_FILE', __FILE__);
define('DG10_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'DG10_';
    $base_dir = DG10_PLUGIN_PATH . 'includes/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

class DG10_Elementor_Silent_Validation {
    private static $instance = null;
    private $settings;
    private $admin;
    private $form_validator;
    private $ip_manager;
    private $ai_validator;
    private $logger;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init_plugin']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init_plugin() {
        $this->load_textdomain();

        // Initialize components for both Pro and Lite modes
        $this->init_components();

        // Show admin notice if Elementor Pro is missing (Lite mode)
        if (!$this->check_elementor_pro()) {
            add_action('admin_notices', [$this, 'elementor_pro_missing_notice']);
        }

        // Register hooks (conditionally for Pro where needed)
        $this->init_hooks();
    }

    private function load_textdomain() {
        load_plugin_textdomain('dg10-antispam', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    private function init_components() {
        $this->logger = DG10_Logger::get_instance();
        $this->settings = DG10_Settings::get_instance();
        $this->admin = DG10_Admin::get_instance();
        $this->form_validator = DG10_Form_Validator::get_instance();
        $this->ip_manager = DG10_IP_Manager::get_instance();
        $this->ai_validator = DG10_AI_Validator::get_instance();
        
        // Initialize security class
        DG10_Security::get_instance();
        
        // Preset manager, geographic blocker, and time rules are initialized through the admin class
    }

    private function init_hooks() {
        // Register Elementor Pro-only hooks if Pro is active
        if ($this->check_elementor_pro()) {
            add_action('elementor_pro/forms/validation', [$this, 'delegate_validate_form'], 10, 2);
            add_action('elementor_pro/forms/process', [$this->ip_manager, 'log_submission'], 10, 2);
        }

        // Always enqueue frontend scripts (Lite mode works client-side only)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add AJAX handler for frontend validation
        add_action('wp_ajax_dg10_validate_form', [$this, 'ajax_validate_form']);
        add_action('wp_ajax_nopriv_dg10_validate_form', [$this, 'ajax_validate_form']);

        // Admin: handle dismissing notices persistently
        add_action('wp_ajax_dg10_dismiss_notice', [$this, 'ajax_dismiss_notice']);
        
        // WordPress dismissible notices are handled automatically by WordPress core
        // No custom AJAX handler needed - WordPress handles the dismissal
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    // Small delegator to keep reference consistent if Pro is present
    public function delegate_validate_form($record, $ajax_handler) {
        if ($this->form_validator) {
            $this->form_validator->validate_form($record, $ajax_handler);
        }
    }

    public function ajax_validate_form() {
        try {
            // Check if request is valid
            if (!wp_doing_ajax()) {
                wp_die(__('Invalid request.', 'dg10-antispam'));
            }

            // Verify nonce for security
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_validation')) {
                $this->logger->warning('AJAX validation failed: Invalid nonce', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
            }

            // Check user capabilities for additional security
            if (!current_user_can('read')) {
                $this->logger->warning('AJAX validation failed: Insufficient permissions', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_id' => get_current_user_id()
                ]);
                wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
            }

            // Sanitize all POST data before processing
            $sanitized_data = [
                'dg10_hp_check' => sanitize_text_field($_POST['dg10_hp_check'] ?? ''),
                'dg10_submission_time' => absint($_POST['dg10_submission_time'] ?? 0),
            ];

            // Basic validation for Lite mode
            $is_valid = true;
            $errors = [];

            // Check honeypot
            if ($this->settings->get_option('enable_honeypot', true) && !empty($sanitized_data['dg10_hp_check'])) {
                $is_valid = false;
                $errors[] = __('Honeypot field detected.', 'dg10-antispam');
            }

            // Check submission time
            if ($this->settings->get_option('enable_time_check', true)) {
                $submission_time = $sanitized_data['dg10_submission_time'];
                $current_time = time() * 1000;
                $min_time = 3000; // 3 seconds minimum
                
                if (($current_time - $submission_time) < $min_time) {
                    $is_valid = false;
                    $errors[] = __('Submission too fast.', 'dg10-antispam');
                }
            }

            // Update statistics if invalid
            if (!$is_valid) {
                update_option('dg10_blocked_attempts', intval(get_option('dg10_blocked_attempts', 0)) + 1);
                $this->logger->log_validation_attempt(
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'ajax_validation',
                    'blocked',
                    implode(' ', $errors)
                );
            }

            if ($is_valid) {
                $this->logger->log_validation_attempt(
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'ajax_validation',
                    'allowed'
                );
                wp_send_json_success(['message' => __('Form validation passed.', 'dg10-antispam')]);
            } else {
                wp_send_json_error(['message' => implode(' ', $errors)], 400);
            }
        } catch (Exception $e) {
            $this->logger->error('AJAX validation exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            wp_send_json_error(['message' => __('An error occurred during validation.', 'dg10-antispam')], 500);
        }
    }

    public function enqueue_scripts() {
        // Only enqueue on frontend
        if (is_admin()) {
            return;
        }

        // Check if we should load scripts
        if (!$this->should_load_scripts()) {
            return;
        }

        wp_enqueue_script(
            'dg10-form-validation',
            DG10_PLUGIN_URL . 'assets/js/form-validation.js',
            ['jquery'],
            DG10_VERSION,
            true
        );

        wp_localize_script('dg10-form-validation', 'dg10Data', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg10_validation'),
            'settings' => $this->settings ? $this->settings->get_frontend_settings() : [],
            // Gate server-side validation features to Elementor Pro only
            'enableAjaxValidation' => $this->check_elementor_pro(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
        ]);
    }

    /**
     * Check if scripts should be loaded
     */
    private function should_load_scripts() {
        // Load if Elementor Pro is active
        if ($this->check_elementor_pro()) {
            return true;
        }

        // Load if Lite mode is enabled
        if ($this->settings && $this->settings->get_option('enable_lite_mode', false)) {
            return true;
        }

        return false;
    }

    public function enqueue_admin_scripts($hook) {
        // Only enqueue on admin pages
        if (!is_admin()) {
            return;
        }

        // Only enqueue on our plugin pages
        if (strpos($hook, 'dg10') === false) {
            return;
        }

        wp_enqueue_script(
            'dg10-admin',
            DG10_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            DG10_VERSION,
            true
        );

        wp_enqueue_style(
            'dg10-admin',
            DG10_PLUGIN_URL . 'assets/css/admin.css',
            [],
            DG10_VERSION
        );

        wp_localize_script('dg10-admin', 'dg10AdminData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg10_admin'),
            'strings' => [
                'confirm_reset' => __('Are you sure you want to reset all settings? This action cannot be undone.', 'dg10-antispam'),
                'confirm_delete' => __('Are you sure you want to delete this item? This action cannot be undone.', 'dg10-antispam'),
            ]
        ]);
    }

    // WordPress handles dismissible notices automatically - no custom AJAX handler needed

    /**
     * Check if a specific notice has been dismissed by the current user
     */
    public function is_notice_dismissed($notice_id) {
        $user_id = get_current_user_id();
        $dismissed_key = 'dg10_dismissed_notice_' . $notice_id;
        return (bool) get_user_meta($user_id, $dismissed_key, true);
    }

    /**
     * Reset dismissed notices for current user (useful for testing)
     * 
     * To test the dismissible notice functionality:
     * 1. Call this method: DG10_Elementor_Silent_Validation::get_instance()->reset_dismissed_notices();
     * 2. Refresh the admin dashboard to see the notice again
     * 3. Click the X button to dismiss it - it should disappear immediately
     * 4. Refresh again - the notice should not appear
     * 
     * Note: This now uses WordPress's built-in dismissible notice system for immediate dismissal
     */
    public function reset_dismissed_notices() {
        $user_id = get_current_user_id();
        $dismissed_key = 'dismissed_wp_help_point_elementor_pro_missing';
        delete_user_meta($user_id, $dismissed_key);
    }

    private function check_elementor_pro() {
        return class_exists('\\ElementorPro\\Plugin');
    }

    public function elementor_pro_missing_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Check if user has dismissed this notice
        if ($this->is_notice_dismissed('elementor_pro_missing')) {
            return;
        }

        $message = sprintf(
            esc_html__('DG10 Elementor Form Anti-Spam is running in Lite mode. To access server-side validation and advanced features (uses Elementor Pro hooks), please %s.', 'dg10-antispam'),
            '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=elementor-pro')) . '">install/activate Elementor Pro</a>'
        );

        printf('<div class="notice notice-info is-dismissible" data-dismissible="elementor_pro_missing" data-dg10-notice-id="elementor_pro_missing"><p>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">%s</span></button></div>', 
            $message, 
            esc_html__('Dismiss this notice.', 'dg10-antispam')
        );
    }

    /**
     * Persist dismissal of admin notices per user
     */
    public function ajax_dismiss_notice() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }

        // Sanitize input data
        $notice_id = sanitize_text_field($_POST['notice_id'] ?? '');
        
        // Validate notice ID
        if (empty($notice_id) || !preg_match('/^[a-zA-Z0-9_-]+$/', $notice_id)) {
            wp_send_json_error(['message' => __('Invalid notice ID.', 'dg10-antispam')], 400);
        }

        // Update user meta
        $user_id = get_current_user_id();
        $dismissed_key = 'dg10_dismissed_notice_' . $notice_id;
        
        if (update_user_meta($user_id, $dismissed_key, 1)) {
            wp_send_json_success(['message' => __('Notice dismissed successfully.', 'dg10-antispam')]);
        } else {
            wp_send_json_error(['message' => __('Failed to dismiss notice.', 'dg10-antispam')], 500);
        }
    }

    public function activate() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.6', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WordPress 5.6 or higher.', 'dg10-antispam'));
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires PHP 7.4 or higher.', 'dg10-antispam'));
        }

        // Initialize default settings
        $this->settings = DG10_Settings::get_instance();
        $this->settings->set_default_options();

        // Create database tables
        $this->create_database_tables();

        // Initialize statistics options
        $this->initialize_default_options();

        // Set activation flag
        update_option('dg10_plugin_activated', time());

        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'dg10_submissions';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            country_code varchar(2),
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            form_id varchar(100),
            validation_result varchar(20),
            error_message text,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY submission_time (submission_time),
            KEY country_code (country_code)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Initialize default options
     */
    private function initialize_default_options() {
        $default_options = [
            'dg10_blocked_attempts' => 0,
            'dg10_protected_forms' => [],
            'dg10_ai_total_checks' => 0,
            'dg10_ai_spam_detected' => 0,
            'dg10_country_stats' => [],
            'dg10_time_stats' => [],
            'dg10_geographic_stats' => [],
        ];

        foreach ($default_options as $option => $default_value) {
            if (!get_option($option)) {
                add_option($option, $default_value);
            }
        }
    }

    public function deactivate() {
        // Clean up old submissions on deactivation
        if ($this->ip_manager) {
            $this->ip_manager->clean_old_submissions();
        }
        
        flush_rewrite_rules();
    }

    public static function uninstall() {
        // Remove data only if user opted in
        $settings = get_option('dg10_antispam_settings', []);
        $remove = isset($settings['remove_data_on_uninstall']) ? (bool) $settings['remove_data_on_uninstall'] : false;
        if (!$remove) {
            return;
        }

        // Delete plugin options
        delete_option('dg10_antispam_settings');
        delete_option('dg10_blocked_attempts');
        delete_option('dg10_protected_forms');
        delete_option('dg10_ai_total_checks');
        delete_option('dg10_ai_spam_detected');
        delete_option('dg10_country_stats');
        delete_option('dg10_time_stats');

        // Drop submissions table
        global $wpdb;
        $table = $wpdb->prefix . 'dg10_submissions';
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

DG10_Elementor_Silent_Validation::get_instance();
register_uninstall_hook(__FILE__, ['DG10_Elementor_Silent_Validation', 'uninstall']);
