<?php
/**
 * Plugin Name: DG10 Elementor Form Anti-Spam
 * Plugin URI: https://www.dg10.agency
 * Description: Advanced form validation and spam protection for Elementor Pro forms with AI-powered spam detection. Developed by DG10 Agency — hire us for WordPress development at www.dg10.agency.
 * Version: 1.0.0
 * Author: DG10 Agency
 * Author URI: https://www.dg10.agency
 * Text Domain: dg10-antispam
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

define('DG10_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DG10_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DG10_VERSION', '1.0.0');

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
        if (!$this->check_elementor_pro()) {
            add_action('admin_notices', [$this, 'elementor_pro_missing_notice']);
            return;
        }

        $this->init_components();
        $this->init_hooks();
    }

    private function load_textdomain() {
        load_plugin_textdomain('dg10-antispam', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    private function init_components() {
        $this->settings = DG10_Settings::get_instance();
        $this->admin = DG10_Admin::get_instance();
        $this->form_validator = DG10_Form_Validator::get_instance();
        $this->ip_manager = DG10_IP_Manager::get_instance();
        $this->ai_validator = DG10_AI_Validator::get_instance();
    }

    private function init_hooks() {
        add_action('elementor_pro/forms/validation', [$this->form_validator, 'validate_form'], 10, 2);
        add_action('elementor_pro/forms/process', [$this->ip_manager, 'log_submission'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        if (!class_exists('\\ElementorPro\\Plugin')) {
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
            'settings' => $this->settings->get_frontend_settings(),
        ]);
    }

    private function check_elementor_pro() {
        return class_exists('\\ElementorPro\\Plugin');
    }

    public function elementor_pro_missing_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $message = sprintf(
            esc_html__('DG10 Elementor Form Anti-Spam requires Elementor Pro to be installed and activated. Please %s to continue.', 'dg10-antispam'),
            '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=elementor-pro')) . '">install Elementor Pro</a>'
        );

        printf('<div class="notice notice-warning"><p>%s</p></div>', $message);
    }

    public function activate() {
        if (!get_option('dg10_blocked_attempts')) {
            add_option('dg10_blocked_attempts', 0);
        }

        if (!get_option('dg10_protected_forms')) {
            add_option('dg10_protected_forms', []);
        }

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

DG10_Elementor_Silent_Validation::get_instance();
