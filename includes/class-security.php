<?php
/**
 * Security Enhancements Class
 * 
 * Provides additional security features and utilities for the DG10 Elementor Form Anti-Spam plugin.
 * 
 * @package DG10_Elementor_Form_Anti_Spam
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DG10_Security {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize security hooks
     */
    private function init_hooks() {
        // Add security headers
        add_action('init', array($this, 'add_security_headers'));
        
        // Rate limiting for admin actions
        add_action('wp_ajax_dg10_apply_preset', array($this, 'check_admin_rate_limit'), 1);
        add_action('wp_ajax_dg10_block_country', array($this, 'check_admin_rate_limit'), 1);
        add_action('wp_ajax_dg10_get_stats', array($this, 'check_admin_rate_limit'), 1);
        
        // Log security events
        add_action('dg10_security_event', array($this, 'log_security_event'), 10, 3);
        
        // Clean up old security logs
        add_action('dg10_cleanup_security_logs', array($this, 'cleanup_old_security_logs'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('dg10_cleanup_security_logs')) {
            wp_schedule_event(time(), 'daily', 'dg10_cleanup_security_logs');
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers on frontend
        if (is_admin()) {
            return;
        }
        
        // Content Security Policy for forms
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'elementor') !== false) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }
    
    /**
     * Check admin rate limiting
     */
    public function check_admin_rate_limit() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $action = $_POST['action'] ?? '';
        $rate_limit_key = 'dg10_admin_rate_limit_' . $user_id . '_' . $action;
        $rate_limit_data = get_transient($rate_limit_key);
        
        if ($rate_limit_data === false) {
            // First request, set rate limit
            set_transient($rate_limit_key, array('count' => 1, 'reset_time' => time() + 300), 300);
            return;
        }
        
        // Check if within rate limit (max 10 requests per 5 minutes per action)
        if ($rate_limit_data['count'] >= 10) {
            $this->log_security_event('rate_limit_exceeded', array(
                'user_id' => $user_id,
                'action' => $action,
                'ip' => $this->get_client_ip()
            ));
            
            wp_send_json_error(array(
                'message' => 'Rate limit exceeded. Please wait before trying again.'
            ));
        }
        
        // Increment counter
        $rate_limit_data['count']++;
        set_transient($rate_limit_key, $rate_limit_data, 300);
    }
    
    /**
     * Log security events
     */
    public function log_security_event($event_type, $data = array(), $severity = 'medium') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'severity' => sanitize_text_field($severity),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'data' => $this->sanitize_log_data($data)
        );
        
        // Store in options (in production, consider using a dedicated logging system)
        $security_logs = get_option('dg10_security_logs', array());
        $security_logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($security_logs) > 1000) {
            $security_logs = array_slice($security_logs, -1000);
        }
        
        update_option('dg10_security_logs', $security_logs);
        
        // Also log to error log for immediate visibility
        error_log(sprintf(
            'DG10 Security Event: %s - %s - IP: %s - Data: %s',
            $event_type,
            $severity,
            $this->get_client_ip(),
            wp_json_encode($data)
        ));
    }
    
    /**
     * Sanitize log data
     */
    private function sanitize_log_data($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);
            if (is_array($value)) {
                $sanitized[$sanitized_key] = $this->sanitize_log_data($value);
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return sanitize_text_field($ip);
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Clean up old security logs
     */
    public function cleanup_old_security_logs() {
        $security_logs = get_option('dg10_security_logs', array());
        $cutoff_time = strtotime('-30 days');
        
        $filtered_logs = array_filter($security_logs, function($log) use ($cutoff_time) {
            return strtotime($log['timestamp']) > $cutoff_time;
        });
        
        update_option('dg10_security_logs', array_values($filtered_logs));
    }
    
    /**
     * Validate nonce for AJAX requests
     */
    public static function verify_ajax_nonce($nonce, $action) {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.'
            ));
        }
        return true;
    }
    
    /**
     * Check user capabilities
     */
    public static function check_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => 'Insufficient permissions.'
            ));
        }
        return true;
    }
    
    /**
     * Sanitize and validate input data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return absint($data);
            case 'float':
                return floatval($data);
            case 'bool':
                return (bool) $data;
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'key':
                return sanitize_key($data);
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Escape output data
     */
    public static function escape_output($data, $type = 'html') {
        switch ($type) {
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            case 'js':
                return esc_js($data);
            case 'textarea':
                return esc_textarea($data);
            default:
                return esc_html($data);
        }
    }
    
    /**
     * Validate file upload
     */
    public static function validate_file_upload($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        // Check file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return false;
        }
        
        // Check file type
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        return in_array($file_extension, $allowed_types, true);
    }
    
    /**
     * Generate secure random string
     */
    public static function generate_random_string($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            // Fallback to wp_generate_password
            return wp_generate_password($length, false);
        }
    }
    
    /**
     * Check if request is from a bot
     */
    public static function is_bot_request() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bot_patterns = array(
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'php', 'ruby', 'perl', 'go-http'
        );
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get security statistics
     */
    public function get_security_stats() {
        $security_logs = get_option('dg10_security_logs', array());
        $stats = array(
            'total_events' => count($security_logs),
            'events_by_type' => array(),
            'events_by_severity' => array(),
            'recent_events' => array()
        );
        
        // Count events by type and severity
        foreach ($security_logs as $log) {
            $type = $log['event_type'];
            $severity = $log['severity'];
            
            $stats['events_by_type'][$type] = ($stats['events_by_type'][$type] ?? 0) + 1;
            $stats['events_by_severity'][$severity] = ($stats['events_by_severity'][$severity] ?? 0) + 1;
        }
        
        // Get recent events (last 24 hours)
        $cutoff_time = strtotime('-24 hours');
        $stats['recent_events'] = array_filter($security_logs, function($log) use ($cutoff_time) {
            return strtotime($log['timestamp']) > $cutoff_time;
        });
        
        return $stats;
    }
    
    /**
     * Clear security logs
     */
    public function clear_security_logs() {
        delete_option('dg10_security_logs');
    }
}
