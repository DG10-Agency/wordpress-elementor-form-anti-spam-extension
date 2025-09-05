<?php
if (!defined('ABSPATH')) exit;

class DG10_Admin {
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_admin_menu() {
        add_options_page(
            __('DG10 Anti-Spam Settings', 'dg10-antispam'),
            __('DG10 Anti-Spam', 'dg10-antispam'),
            'manage_options',
            'dg10-antispam',
            [$this, 'render_settings_page']
        );
    }

    public function init_settings() {
        $this->settings->register_settings();
    }

    public function enqueue_assets($hook) {
        if ('settings_page_dg10-antispam' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'dg10-admin',
            DG10_PLUGIN_URL . 'assets/css/admin.css',
            [],
            DG10_VERSION
        );

        wp_enqueue_script(
            'dg10-admin',
            DG10_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            DG10_VERSION,
            true
        );

        wp_localize_script('dg10-admin', 'dg10AdminData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg10_admin')
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $blocked_attempts = $this->get_blocked_attempts();
        $protected_forms = $this->get_protected_forms();
        ?>
        <div class="wrap dg10-admin-container">
            <img class="dg10-logo" src="<?php echo esc_url(DG10_PLUGIN_URL . 'assets/images/logo.svg'); ?>" alt="<?php echo esc_attr__('DG10 Agency', 'dg10-antispam'); ?>">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="dg10-admin-content">
                <div class="dg10-admin-main">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields($this->settings->get_option_name() . '_group');
                        do_settings_sections('dg10-antispam');
                        submit_button(__('Save Settings', 'dg10-antispam'));
                        ?>
                    </form>
                </div>

                <div class="dg10-admin-sidebar">
                    <div class="dg10-box">
                        <h3><?php _e('Usage Statistics', 'dg10-antispam'); ?></h3>
                        <ul>
                            <li>
                                <strong><?php _e('Blocked Attempts:', 'dg10-antispam'); ?></strong>
                                <span id="dg10-blocked-attempts"><?php echo esc_html($blocked_attempts); ?></span>
                            </li>
                            <li>
                                <strong><?php _e('Protected Forms:', 'dg10-antispam'); ?></strong>
                                <span id="dg10-protected-forms"><?php echo esc_html(is_array($protected_forms) ? count($protected_forms) : intval($protected_forms)); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="dg10-box">
                        <h3><?php _e('Quick Tips', 'dg10-antispam'); ?></h3>
                        <ul>
                            <li><?php _e('Enable both DeepSeek and Gemini AI for maximum protection', 'dg10-antispam'); ?></li>
                            <li><?php _e('Keep your API keys secure and never share them', 'dg10-antispam'); ?></li>
                            <li><?php _e('Regularly check the blocked attempts statistics', 'dg10-antispam'); ?></li>
                            <li><?php _e('Test your forms after enabling new features', 'dg10-antispam'); ?></li>
                        </ul>
                    </div>

                    <div class="dg10-box">
                        <h3><?php _e('About', 'dg10-antispam'); ?></h3>
                        <p>
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: 1: DG10 Agency link */
                                    __('This plugin is developed by %1$s — please hire us for WordPress development.', 'dg10-antispam'),
                                    '<a href="https://www.dg10.agency" target="_blank" rel="noopener">DG10 Agency</a>'
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_blocked_attempts() {
        return intval(get_option('dg10_blocked_attempts', 0));
    }

    private function get_protected_forms() {
        return (array) get_option('dg10_protected_forms', []);
    }
}