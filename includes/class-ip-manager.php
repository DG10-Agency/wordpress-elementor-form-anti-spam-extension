<?php
if (!defined('ABSPATH')) exit;

class DG10_IP_Manager {
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dg10_submissions';
        $this->create_table();
    }

    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            submission_time int(11) NOT NULL,
            form_name varchar(255) DEFAULT '',
            country_code varchar(2) DEFAULT '',
            country_name varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            KEY ip_time (ip_address, submission_time),
            KEY country (country_code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function clean_old_submissions() {
        global $wpdb;
        $one_hour_ago = time() - 3600;
        
        // Use prepared statement to prevent SQL injection
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE submission_time < %d",
            $one_hour_ago
        ));
        
        // Log errors if any
        if ($result === false && !empty($wpdb->last_error)) {
            error_log('DG10 IP Manager: Database error in clean_old_submissions: ' . $wpdb->last_error);
        }
        
        return $result;
    }

    public function is_submission_rate_exceeded($ip, $max_submissions) {
        global $wpdb;
        
        // Validate input parameters
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP) || $max_submissions <= 0) {
            return false;
        }
        
        // Sanitize IP address
        $ip = sanitize_text_field($ip);
        
        // Clean old submissions first
        $this->clean_old_submissions();
        
        // Count submissions in the last hour using prepared statement
        $one_hour_ago = time() - 3600;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE ip_address = %s AND submission_time > %d",
            $ip,
            $one_hour_ago
        ));
        
        // Handle database errors
        if ($count === null && !empty($wpdb->last_error)) {
            error_log('DG10 IP Manager: Database error in is_submission_rate_exceeded: ' . $wpdb->last_error);
            return false; // Fail open on database errors
        }
        
        $count = intval($count);
        
        // If under limit, log this submission
        if ($count < $max_submissions) {
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'ip_address' => $ip,
                    'submission_time' => time(),
                    'form_name' => 'rate_limit_check'
                ],
                ['%s', '%d', '%s']
            );
            
            // Log errors if any
            if ($result === false && !empty($wpdb->last_error)) {
                error_log('DG10 IP Manager: Database error inserting submission: ' . $wpdb->last_error);
            }
        }
        
        return $count >= $max_submissions;
    }

    public function get_client_ip() {
        $ip = '';
        
        // Check REMOTE_ADDR first (most reliable)
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        
        // Fallback to HTTP_CLIENT_IP if REMOTE_ADDR is not available or invalid
        if (!$ip && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        
        // Last fallback to HTTP_X_FORWARDED_FOR (can be spoofed)
        if (!$ip && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle comma-separated list of IPs
            $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($forwarded_ips as $forwarded_ip) {
                $forwarded_ip = trim($forwarded_ip);
                $ip = filter_var($forwarded_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($ip) {
                    break; // Use first valid IP
                }
            }
        }
        
        return $ip ?: '';
    }

    // Elementor passes ($record, $handler)
    public function log_submission($record, $handler) {
        global $wpdb;
        
        $ip = $this->get_client_ip();
        $form_name = is_object($record) && method_exists($record, 'get_form_settings')
            ? sanitize_text_field($record->get_form_settings('form_name'))
            : 'unknown';

        // Get country information
        $country_code = '';
        $country_name = '';
        if ($ip) {
            $geo_blocker = DG10_Geographic_Blocker::get_instance();
            $country_code = sanitize_text_field($geo_blocker->get_country_from_ip($ip));
            $country_name = sanitize_text_field($geo_blocker->get_country_name($country_code));
        }

        // Log time-based information
        if ($ip) {
            $time_rules = DG10_Time_Rules::get_instance();
            $time_rules->log_time_submission($ip, $form_name);
        }

        // Log to database
        if ($ip) {
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'ip_address' => $ip,
                    'submission_time' => time(),
                    'form_name' => $form_name,
                    'country_code' => $country_code,
                    'country_name' => $country_name
                ],
                ['%s', '%d', '%s', '%s', '%s']
            );
            
            // Log errors if any
            if ($result === false && !empty($wpdb->last_error)) {
                error_log('DG10 IP Manager: Database error in log_submission: ' . $wpdb->last_error);
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'DG10 submission | IP: %s | Form: %s | Country: %s | Time: %s',
                $ip ?: 'invalid',
                $form_name,
                $country_name ?: 'unknown',
                current_time('mysql')
            ));
        }
    }

    /**
     * Log country information for an IP
     */
    public function log_country_info($ip, $country_code) {
        global $wpdb;
        
        // Validate input parameters
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP) || !$country_code) {
            return;
        }

        // Sanitize inputs
        $ip = sanitize_text_field($ip);
        $country_code = sanitize_text_field($country_code);

        $geo_blocker = DG10_Geographic_Blocker::get_instance();
        $country_name = sanitize_text_field($geo_blocker->get_country_name($country_code));

        // Update existing records for this IP using prepared statement
        $result = $wpdb->update(
            $this->table_name,
            [
                'country_code' => $country_code,
                'country_name' => $country_name
            ],
            [
                'ip_address' => $ip,
                'country_code' => ''
            ],
            ['%s', '%s'],
            ['%s', '%s']
        );
        
        // Log errors if any
        if ($result === false && !empty($wpdb->last_error)) {
            error_log('DG10 IP Manager: Database error in log_country_info: ' . $wpdb->last_error);
        }
    }
}