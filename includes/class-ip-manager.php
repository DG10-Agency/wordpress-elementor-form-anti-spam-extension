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
            PRIMARY KEY (id),
            KEY ip_time (ip_address, submission_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function clean_old_submissions() {
        global $wpdb;
        $one_hour_ago = time() - 3600;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE submission_time < %d",
            $one_hour_ago
        ));
    }

    public function is_submission_rate_exceeded($ip, $max_submissions) {
        global $wpdb;
        
        if (!$ip || $max_submissions <= 0) {
            return false;
        }
        
        // Clean old submissions first
        $this->clean_old_submissions();
        
        // Count submissions in the last hour
        $one_hour_ago = time() - 3600;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE ip_address = %s AND submission_time > %d",
            $ip,
            $one_hour_ago
        ));
        
        // If under limit, log this submission
        if ($count < $max_submissions) {
            $wpdb->insert(
                $this->table_name,
                [
                    'ip_address' => $ip,
                    'submission_time' => time(),
                    'form_name' => 'rate_limit_check'
                ],
                ['%s', '%d', '%s']
            );
        }
        
        return $count >= $max_submissions;
    }

    public function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        }
        if (!$ip && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
        }
        if (!$ip && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        }
        return $ip;
    }

    // Elementor passes ($record, $handler)
    public function log_submission($record, $handler) {
        global $wpdb;
        
        $ip = $this->get_client_ip();
        $form_name = is_object($record) && method_exists($record, 'get_form_settings')
            ? $record->get_form_settings('form_name')
            : 'unknown';

        // Log to database
        if ($ip) {
            $wpdb->insert(
                $this->table_name,
                [
                    'ip_address' => $ip,
                    'submission_time' => time(),
                    'form_name' => $form_name
                ],
                ['%s', '%d', '%s']
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'DG10 submission | IP: %s | Form: %s | Time: %s',
                $ip ?: 'invalid',
                $form_name,
                current_time('mysql')
            ));
        }
    }
}