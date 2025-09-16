<?php
/**
 * Plugin Test File for DG10 Elementor Form Anti-Spam
 * 
 * This file can be used to test the plugin functionality
 * Remove this file before submitting to WordPress.org
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DG10_Plugin_Test {
    
    public static function run_tests() {
        $tests = [
            'test_plugin_activation' => 'Test plugin activation',
            'test_database_creation' => 'Test database table creation',
            'test_settings_initialization' => 'Test settings initialization',
            'test_security_functions' => 'Test security functions',
            'test_internationalization' => 'Test internationalization',
            'test_error_handling' => 'Test error handling',
        ];
        
        $results = [];
        
        foreach ($tests as $test_method => $test_name) {
            try {
                $result = self::$test_method();
                $results[$test_name] = $result ? 'PASS' : 'FAIL';
            } catch (Exception $e) {
                $results[$test_name] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    private static function test_plugin_activation() {
        // Test if plugin constants are defined
        return defined('DG10_PLUGIN_PATH') && 
               defined('DG10_PLUGIN_URL') && 
               defined('DG10_VERSION');
    }
    
    private static function test_database_creation() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dg10_submissions';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            // Check table structure
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            $required_columns = ['id', 'ip_address', 'submission_time', 'form_id', 'validation_result'];
            
            foreach ($required_columns as $column) {
                $found = false;
                foreach ($columns as $col) {
                    if ($col->Field === $column) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }
        }
        
        return $table_exists;
    }
    
    private static function test_settings_initialization() {
        $settings = get_option('dg10_antispam_settings');
        return is_array($settings) && !empty($settings);
    }
    
    private static function test_security_functions() {
        // Test nonce creation
        $nonce = wp_create_nonce('dg10_validation');
        if (empty($nonce)) {
            return false;
        }
        
        // Test nonce verification
        if (!wp_verify_nonce($nonce, 'dg10_validation')) {
            return false;
        }
        
        // Test sanitization functions
        $test_input = '<script>alert("test")</script>';
        $sanitized = sanitize_text_field($test_input);
        if ($sanitized !== 'alert("test")') {
            return false;
        }
        
        return true;
    }
    
    private static function test_internationalization() {
        // Test if text domain is loaded
        $test_string = __('DG10 Anti-Spam Settings', 'dg10-antispam');
        return !empty($test_string);
    }
    
    private static function test_error_handling() {
        // Test logger class exists
        if (!class_exists('DG10_Logger')) {
            return false;
        }
        
        $logger = DG10_Logger::get_instance();
        if (!$logger) {
            return false;
        }
        
        // Test logging methods exist
        return method_exists($logger, 'error') && 
               method_exists($logger, 'warning') && 
               method_exists($logger, 'info') && 
               method_exists($logger, 'debug');
    }
}

// Run tests if accessed directly
if (isset($_GET['run_dg10_tests']) && current_user_can('manage_options')) {
    $results = DG10_Plugin_Test::run_tests();
    
    echo '<h2>DG10 Plugin Test Results</h2>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Test</th><th>Result</th></tr>';
    
    foreach ($results as $test_name => $result) {
        $color = strpos($result, 'PASS') !== false ? 'green' : 'red';
        echo "<tr><td>$test_name</td><td style='color: $color'>$result</td></tr>";
    }
    
    echo '</table>';
}
