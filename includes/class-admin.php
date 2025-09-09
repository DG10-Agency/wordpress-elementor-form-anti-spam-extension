<?php
if (!defined('ABSPATH')) exit;

class DG10_Admin {
    private static $instance = null;
    private $settings;
    private $preset_manager;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
        $this->preset_manager = DG10_Preset_Manager::get_instance();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_dg10_get_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_dg10_get_country_stats', [$this, 'ajax_get_country_stats']);
        add_action('wp_ajax_dg10_get_time_stats', [$this, 'ajax_get_time_stats']);
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

        // Enqueue WordPress common.js which handles dismissible notices
        wp_enqueue_script('common');

        wp_enqueue_script(
            'dg10-admin',
            DG10_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'common'],
            DG10_VERSION,
            true
        );

        wp_localize_script('dg10-admin', 'dg10AdminData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dg10_admin'),
            'presets' => $this->preset_manager->get_presets(),
            'currentPreset' => $this->preset_manager->get_current_preset(),
            'recommendations' => $this->preset_manager->get_preset_recommendations(),
            'blockedCountries' => $this->settings->get_option('blocked_countries', [])
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $blocked_attempts = $this->get_blocked_attempts();
        $protected_forms = $this->get_protected_forms();
        $has_pro = class_exists('\\ElementorPro\\Plugin');
        $lite_enabled = (bool) $this->settings->get_option('enable_lite_mode', false);
        ?>
        <div class="wrap dg10-admin-container">
            <div class="dg10-header">
                <img class="dg10-logo" src="<?php echo esc_url(DG10_PLUGIN_URL . 'assets/images/logo.svg'); ?>" alt="<?php echo esc_attr__('DG10', 'dg10-antispam'); ?>">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>

            <div class="dg10-mode-notice <?php echo $has_pro ? 'is-pro' : 'is-lite'; ?>">
                <?php if ($has_pro): ?>
                    <p>
                        <strong><?php esc_html_e('Pro mode active', 'dg10-antispam'); ?>.</strong>
                        <?php esc_html_e('Server-side validation, IP throttling, and AI checks are enabled for Elementor Pro forms.', 'dg10-antispam'); ?>
                        <?php esc_html_e('Lite Mode options apply to non-Elementor forms on the site.', 'dg10-antispam'); ?>
                        <span class="dg10-badge-pro"><?php esc_html_e('Pro', 'dg10-antispam'); ?></span>
                    </p>
                <?php else: ?>
                    <p>
                        <strong><?php esc_html_e('Lite mode active', 'dg10-antispam'); ?>.</strong>
                        <?php esc_html_e('Using client-side checks (honeypot, time, basic validation). Features marked require Elementor Pro hooks.', 'dg10-antispam'); ?>
                        <a href="<?php echo esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=elementor-pro')); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('Get Elementor Pro', 'dg10-antispam'); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if (!$has_pro && !$lite_enabled): ?>
                    <p class="description"><?php esc_html_e('Tip: Enable Lite Mode below and set a CSS selector to protect your non-Elementor forms.', 'dg10-antispam'); ?></p>
                <?php endif; ?>
            </div>

            <?php $this->render_preset_interface(); ?>
            
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

                    <?php if ($this->settings->get_option('enable_geographic_blocking', false)): ?>
                    <div class="dg10-box">
                        <h3><?php _e('Geographic Statistics', 'dg10-antispam'); ?></h3>
                        <div id="dg10-country-stats">
                            <p class="description"><?php _e('Loading country statistics...', 'dg10-antispam'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($this->settings->get_option('enable_time_rules', false)): ?>
                    <div class="dg10-box">
                        <h3><?php _e('Time-Based Statistics', 'dg10-antispam'); ?></h3>
                        <div id="dg10-time-stats">
                            <p class="description"><?php _e('Loading time statistics...', 'dg10-antispam'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="dg10-box">
                        <h3><?php _e('Quick Tips', 'dg10-antispam'); ?></h3>
                        <ul>
                            <li><?php _e('Enable both DeepSeek and Gemini AI for maximum protection', 'dg10-antispam'); ?></li>
                            <li><?php _e('Keep your API keys secure and never share them', 'dg10-antispam'); ?></li>
                            <li><?php _e('Regularly check the blocked attempts statistics', 'dg10-antispam'); ?></li>
                            <li><?php _e('Test your forms after enabling new features', 'dg10-antispam'); ?></li>
                        </ul>
                    </div>

                    <div class="dg10-box dg10-about">
                        <div class="dg10-about-header">
                            <img class="dg10-logo" src="<?php echo esc_url(DG10_PLUGIN_URL . 'assets/images/logo.svg'); ?>" alt="<?php echo esc_attr__('DG10', 'dg10-antispam'); ?>">
                            <h3><?php _e('About us', 'dg10-antispam'); ?></h3>
                        </div>
                        <p>
                            <?php echo esc_html__(
                                'We craft high‑performance WordPress, Elementor and other digital solutions. Please visit website to check our services Need help with custom development, optimization, or complex integrations?',
                                'dg10-antispam'
                            ); ?>
                        </p>
                        <div class="dg10-actions">
                            <a class="dg10-cta-button is-primary" href="https://www.dg10.agency" target="_blank" rel="noopener">
                                <?php esc_html_e('Visit Website', 'dg10-antispam'); ?>
                                <svg class="icon icon-right" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M13 5l7 7-7 7M5 12h14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                            <a class="dg10-cta-button is-outline" href="https://calendly.com/dg10-agency/30min" target="_blank" rel="noopener">
                                <svg class="icon icon-left" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                                    <path d="M7 2v3M17 2v3M3 9h18M5 6h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                                    <rect x="7" y="13" width="3" height="3" rx="0.5" />
                                    <rect x="12" y="13" width="3" height="3" rx="0.5" />
                                    <rect x="17" y="13" width="3" height="3" rx="0.5" />
                                </svg>
                                <?php esc_html_e('Book a Free Consultation', 'dg10-antispam'); ?>
                            </a>
                        </div>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: 1: open anchor, 2: close anchor */
                                esc_html__('This is an open‑source project — please %1$sstar the repo on GitHub%2$s.', 'dg10-antispam'),
                                '<a href="https://github.com/DG10-Agency/wordpress-elementor-form-anti-spam-extension" target="_blank" rel="noopener">',
                                '</a>'
                            );
                            ?>
                        </p>

                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_stats() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }

        try {
            $blocked = $this->get_blocked_attempts();
            $forms = $this->get_protected_forms();
            
            wp_send_json_success([
                'blocked' => intval($blocked),
                'forms' => is_array($forms) ? count($forms) : 0
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to retrieve statistics.', 'dg10-antispam')], 500);
        }
    }

    private function get_blocked_attempts() {
        return intval(get_option('dg10_blocked_attempts', 0));
    }

    private function get_protected_forms() {
        return (array) get_option('dg10_protected_forms', []);
    }

    public function ajax_get_country_stats() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }
        
        try {
            $geo_blocker = DG10_Geographic_Blocker::get_instance();
            $stats = $geo_blocker->get_country_stats();
            
            // Validate and sanitize stats data
            if (!is_array($stats)) {
                $stats = [];
            }
            
            // Sort by submission count
            uasort($stats, function($a, $b) {
                $a_submissions = isset($a['submissions']) ? intval($a['submissions']) : 0;
                $b_submissions = isset($b['submissions']) ? intval($b['submissions']) : 0;
                return $b_submissions - $a_submissions;
            });
            
            // Get top 10 countries and sanitize output
            $top_countries = array_slice($stats, 0, 10, true);
            $sanitized_stats = [];
            
            foreach ($top_countries as $code => $data) {
                $sanitized_stats[sanitize_text_field($code)] = [
                    'name' => sanitize_text_field($data['name'] ?? ''),
                    'submissions' => intval($data['submissions'] ?? 0),
                    'blocked' => intval($data['blocked'] ?? 0),
                    'last_seen' => sanitize_text_field($data['last_seen'] ?? '')
                ];
            }
            
            wp_send_json_success(['stats' => $sanitized_stats]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to retrieve country statistics.', 'dg10-antispam')], 500);
        }
    }

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
            $time_rules = DG10_Time_Rules::get_instance();
            $stats = $time_rules->get_time_stats();
            $current_rules = $time_rules->get_time_based_rules();
            
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

    private function render_preset_interface() {
        $current_preset = $this->preset_manager->get_current_preset();
        $presets = $this->preset_manager->get_presets();
        $recommendations = $this->preset_manager->get_preset_recommendations();
        ?>
        <div class="dg10-preset-interface">
            <div class="dg10-preset-header">
                <h2><?php esc_html_e('One-Click Presets', 'dg10-antispam'); ?></h2>
                <p class="description"><?php esc_html_e('Quickly apply predefined configurations optimized for different use cases.', 'dg10-antispam'); ?></p>
            </div>

            <div class="dg10-preset-grid">
                <?php foreach ($presets as $preset_id => $preset): ?>
                    <div class="dg10-preset-card <?php echo $current_preset === $preset_id ? 'is-active' : ''; ?> <?php echo in_array($preset_id, $recommendations) ? 'is-recommended' : ''; ?>" data-preset-id="<?php echo esc_attr($preset_id); ?>">
                        <div class="dg10-preset-icon"><?php echo esc_html($preset['icon']); ?></div>
                        <div class="dg10-preset-content">
                            <h3><?php echo esc_html($preset['name']); ?></h3>
                            <p><?php echo esc_html($preset['description']); ?></p>
                            <?php if (in_array($preset_id, $recommendations)): ?>
                                <span class="dg10-preset-badge"><?php esc_html_e('Recommended', 'dg10-antispam'); ?></span>
                            <?php endif; ?>
                            <?php if ($current_preset === $preset_id): ?>
                                <span class="dg10-preset-badge is-active"><?php esc_html_e('Active', 'dg10-antispam'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dg10-preset-actions">
                            <button type="button" class="button button-primary dg10-apply-preset" data-preset-id="<?php echo esc_attr($preset_id); ?>">
                                <?php echo $current_preset === $preset_id ? esc_html__('Current', 'dg10-antispam') : esc_html__('Apply', 'dg10-antispam'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="dg10-preset-card <?php echo $current_preset === 'custom' ? 'is-active' : ''; ?>" data-preset-id="custom">
                    <div class="dg10-preset-icon">⚙️</div>
                    <div class="dg10-preset-content">
                        <h3><?php esc_html_e('Custom Mode', 'dg10-antispam'); ?></h3>
                        <p><?php esc_html_e('Your current custom settings', 'dg10-antispam'); ?></p>
                        <?php if ($current_preset === 'custom'): ?>
                            <span class="dg10-preset-badge is-active"><?php esc_html_e('Active', 'dg10-antispam'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dg10-preset-actions">
                        <button type="button" class="button" disabled>
                            <?php esc_html_e('Current', 'dg10-antispam'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="dg10-preset-info">
                <p class="description">
                    <strong><?php esc_html_e('Note:', 'dg10-antispam'); ?></strong>
                    <?php esc_html_e('Applying a preset will update your settings. API keys will be preserved if they are already configured.', 'dg10-antispam'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}