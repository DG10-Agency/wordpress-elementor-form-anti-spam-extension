<?php
/**
 * Database Migration Class
 * 
 * Handles database schema updates and migrations for the DG10 Elementor Form Anti-Spam plugin.
 * 
 * @package DG10_Elementor_Form_Anti_Spam
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DG10_Database_Migration {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Database version
     */
    private $db_version = '1.0.0';
    
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
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'check_database_version'));
        // Note: Activation/deactivation hooks should be registered in the main plugin file
        // register_activation_hook(DG10_PLUGIN_FILE, array($this, 'create_tables'));
        // register_deactivation_hook(DG10_PLUGIN_FILE, array($this, 'cleanup_on_deactivation'));
    }
    
    /**
     * Check database version and run migrations if needed
     */
    public function check_database_version() {
        $current_version = get_option('dg10_db_version', '0.0.0');
        
        if (version_compare($current_version, $this->db_version, '<')) {
            $this->run_migrations($current_version);
            update_option('dg10_db_version', $this->db_version);
        }
    }
    
    /**
     * Run database migrations
     */
    private function run_migrations($from_version) {
        global $wpdb;
        
        // Log migration start
        error_log("DG10: Starting database migration from version {$from_version} to {$this->db_version}");
        
        try {
            // Migration from 0.0.0 to 1.0.0 (initial setup)
            if (version_compare($from_version, '1.0.0', '<')) {
                $this->migrate_to_1_0_0();
            }
            
            // Future migrations can be added here
            // if (version_compare($from_version, '1.1.0', '<')) {
            //     $this->migrate_to_1_1_0();
            // }
            
            error_log("DG10: Database migration completed successfully");
            
        } catch (Exception $e) {
            error_log("DG10: Database migration failed: " . $e->getMessage());
            
            // Send admin notice about migration failure
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>DG10 Elementor Form Anti-Spam:</strong> Database migration failed. ';
                echo 'Please contact support. Error: ' . esc_html($e->getMessage());
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Create initial database tables
     */
    public function create_tables() {
        $this->migrate_to_1_0_0();
        update_option('dg10_db_version', $this->db_version);
    }
    
    /**
     * Migration to version 1.0.0
     */
    private function migrate_to_1_0_0() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create submissions table
        $table_name = $wpdb->prefix . 'dg10_form_submissions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_name varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            country_code varchar(2) DEFAULT NULL,
            country_name varchar(100) DEFAULT NULL,
            submission_time int(11) NOT NULL,
            is_blocked tinyint(1) NOT NULL DEFAULT 0,
            block_reason varchar(255) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            form_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY submission_time (submission_time),
            KEY country_code (country_code),
            KEY is_blocked (is_blocked),
            KEY form_name (form_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create security logs table
        $security_table = $wpdb->prefix . 'dg10_security_logs';
        $security_sql = "CREATE TABLE $security_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'medium',
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            event_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY severity (severity),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($security_sql);
        
        // Create rate limiting table
        $rate_limit_table = $wpdb->prefix . 'dg10_rate_limits';
        $rate_limit_sql = "CREATE TABLE $rate_limit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            action_type varchar(100) NOT NULL,
            request_count int(11) NOT NULL DEFAULT 1,
            window_start int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_action (identifier, action_type),
            KEY window_start (window_start),
            KEY action_type (action_type)
        ) $charset_collate;";
        
        dbDelta($rate_limit_sql);
        
        // Create blocked IPs table
        $blocked_ips_table = $wpdb->prefix . 'dg10_blocked_ips';
        $blocked_ips_sql = "CREATE TABLE $blocked_ips_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reason varchar(255) NOT NULL,
            blocked_until datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY blocked_until (blocked_until),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        dbDelta($blocked_ips_sql);
        
        // Create whitelisted IPs table
        $whitelist_table = $wpdb->prefix . 'dg10_whitelisted_ips';
        $whitelist_sql = "CREATE TABLE $whitelist_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        dbDelta($whitelist_sql);
        
        // Create country statistics table
        $country_stats_table = $wpdb->prefix . 'dg10_country_stats';
        $country_stats_sql = "CREATE TABLE $country_stats_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            country_code varchar(2) NOT NULL,
            country_name varchar(100) NOT NULL,
            submission_count int(11) NOT NULL DEFAULT 0,
            blocked_count int(11) NOT NULL DEFAULT 0,
            last_seen datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY country_code (country_code),
            KEY submission_count (submission_count),
            KEY blocked_count (blocked_count),
            KEY last_seen (last_seen)
        ) $charset_collate;";
        
        dbDelta($country_stats_sql);
        
        // Create time-based statistics table
        $time_stats_table = $wpdb->prefix . 'dg10_time_stats';
        $time_stats_sql = "CREATE TABLE $time_stats_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time_key varchar(50) NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            is_weekend tinyint(1) NOT NULL DEFAULT 0,
            is_holiday tinyint(1) NOT NULL DEFAULT 0,
            is_business_hours tinyint(1) NOT NULL DEFAULT 0,
            submission_count int(11) NOT NULL DEFAULT 0,
            blocked_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY time_key (time_key),
            KEY day_of_week (day_of_week),
            KEY is_weekend (is_weekend),
            KEY is_holiday (is_holiday),
            KEY is_business_hours (is_business_hours)
        ) $charset_collate;";
        
        dbDelta($time_stats_sql);
        
        // Verify tables were created successfully
        $this->verify_table_creation();
    }
    
    /**
     * Verify that all tables were created successfully
     */
    private function verify_table_creation() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'dg10_form_submissions',
            $wpdb->prefix . 'dg10_security_logs',
            $wpdb->prefix . 'dg10_rate_limits',
            $wpdb->prefix . 'dg10_blocked_ips',
            $wpdb->prefix . 'dg10_whitelisted_ips',
            $wpdb->prefix . 'dg10_country_stats',
            $wpdb->prefix . 'dg10_time_stats'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            if (!$table_exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            throw new Exception('Failed to create required database tables: ' . implode(', ', $missing_tables));
        }
        
        // Log successful table creation
        error_log('DG10: All database tables created successfully');
    }
    
    /**
     * Clean up on plugin deactivation
     */
    public function cleanup_on_deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook('dg10_cleanup_security_logs');
        
        // Optionally clean up old data (uncomment if needed)
        // $this->cleanup_old_data();
    }
    
    /**
     * Clean up old data (optional)
     */
    private function cleanup_old_data() {
        global $wpdb;
        
        // Clean up submissions older than 1 year
        $submissions_table = $wpdb->prefix . 'dg10_form_submissions';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $submissions_table WHERE submission_time < %d",
            strtotime('-1 year')
        ));
        
        // Clean up security logs older than 6 months
        $security_table = $wpdb->prefix . 'dg10_security_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $security_table WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-6 months'))
        ));
        
        // Clean up expired rate limits
        $rate_limit_table = $wpdb->prefix . 'dg10_rate_limits';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $rate_limit_table WHERE window_start < %d",
            time() - 3600 // 1 hour ago
        ));
        
        // Clean up expired blocked IPs
        $blocked_ips_table = $wpdb->prefix . 'dg10_blocked_ips';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $blocked_ips_table WHERE blocked_until IS NOT NULL AND blocked_until < %s",
            current_time('mysql')
        ));
    }
    
    /**
     * Get database statistics
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Count submissions
        $submissions_table = $wpdb->prefix . 'dg10_form_submissions';
        $stats['total_submissions'] = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table");
        $stats['blocked_submissions'] = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table WHERE is_blocked = 1");
        
        // Count security events
        $security_table = $wpdb->prefix . 'dg10_security_logs';
        $stats['security_events'] = $wpdb->get_var("SELECT COUNT(*) FROM $security_table");
        
        // Count blocked IPs
        $blocked_ips_table = $wpdb->prefix . 'dg10_blocked_ips';
        $stats['blocked_ips'] = $wpdb->get_var("SELECT COUNT(*) FROM $blocked_ips_table");
        
        // Count whitelisted IPs
        $whitelist_table = $wpdb->prefix . 'dg10_whitelisted_ips';
        $stats['whitelisted_ips'] = $wpdb->get_var("SELECT COUNT(*) FROM $whitelist_table");
        
        // Get table sizes
        $stats['table_sizes'] = array();
        $tables = array(
            'dg10_form_submissions' => 'Form Submissions',
            'dg10_security_logs' => 'Security Logs',
            'dg10_rate_limits' => 'Rate Limits',
            'dg10_blocked_ips' => 'Blocked IPs',
            'dg10_whitelisted_ips' => 'Whitelisted IPs',
            'dg10_country_stats' => 'Country Statistics',
            'dg10_time_stats' => 'Time Statistics'
        );
        
        foreach ($tables as $table => $name) {
            $full_table_name = $wpdb->prefix . $table;
            $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size' FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '$full_table_name'");
            $stats['table_sizes'][$name] = $size ? $size . ' MB' : '0 MB';
        }
        
        return $stats;
    }
    
    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'dg10_form_submissions',
            $wpdb->prefix . 'dg10_security_logs',
            $wpdb->prefix . 'dg10_rate_limits',
            $wpdb->prefix . 'dg10_blocked_ips',
            $wpdb->prefix . 'dg10_whitelisted_ips',
            $wpdb->prefix . 'dg10_country_stats',
            $wpdb->prefix . 'dg10_time_stats'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
        
        return true;
    }
    
    /**
     * Reset database (for development/testing)
     */
    public function reset_database() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }
        
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'dg10_form_submissions',
            $wpdb->prefix . 'dg10_security_logs',
            $wpdb->prefix . 'dg10_rate_limits',
            $wpdb->prefix . 'dg10_blocked_ips',
            $wpdb->prefix . 'dg10_whitelisted_ips',
            $wpdb->prefix . 'dg10_country_stats',
            $wpdb->prefix . 'dg10_time_stats'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('dg10_db_version');
        $this->create_tables();
        
        return true;
    }
}
