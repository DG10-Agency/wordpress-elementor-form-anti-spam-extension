<?php
/**
 * Logger class for DG10 Elementor Form Anti-Spam
 *
 * @package DG10_Elementor_Form_Anti_Spam
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG10_Logger {
    private static $instance = null;
    private $log_level;
    private $log_file;
    
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_DEBUG = 'debug';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->log_level = defined('WP_DEBUG') && WP_DEBUG ? self::LOG_LEVEL_DEBUG : self::LOG_LEVEL_ERROR;
        $this->log_file = WP_CONTENT_DIR . '/dg10-antispam.log';
    }
    
    /**
     * Log an error message
     */
    public function error($message, $context = []) {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public function warning($message, $context = []) {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public function info($message, $context = []) {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public function debug($message, $context = []) {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Main logging method
     */
    private function log($level, $message, $context = []) {
        // Check if we should log this level
        if (!$this->should_log($level)) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf(
            '[%s] %s: %s %s',
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        // Log to WordPress error log
        error_log($log_entry);
        
        // Log to custom file if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && $this->log_level === self::LOG_LEVEL_DEBUG) {
            $this->write_to_file($log_entry);
        }
    }
    
    /**
     * Check if we should log this level
     */
    private function should_log($level) {
        $levels = [
            self::LOG_LEVEL_ERROR => 1,
            self::LOG_LEVEL_WARNING => 2,
            self::LOG_LEVEL_INFO => 3,
            self::LOG_LEVEL_DEBUG => 4
        ];
        
        $current_level = $levels[$this->log_level] ?? 1;
        $message_level = $levels[$level] ?? 1;
        
        return $message_level <= $current_level;
    }
    
    /**
     * Write log entry to file
     */
    private function write_to_file($log_entry) {
        if (!is_writable(WP_CONTENT_DIR)) {
            return;
        }
        
        $log_entry .= PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Rotate log file if it gets too large (5MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 5 * 1024 * 1024) {
            $this->rotate_log_file();
        }
    }
    
    /**
     * Rotate log file
     */
    private function rotate_log_file() {
        $backup_file = $this->log_file . '.1';
        
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        if (file_exists($this->log_file)) {
            rename($this->log_file, $backup_file);
        }
    }
    
    /**
     * Clear log file
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
        
        $backup_file = $this->log_file . '.1';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
    }
    
    /**
     * Get log file contents
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file_lines = file($this->log_file, FILE_IGNORE_NEW_LINES);
        return array_slice($file_lines, -$lines);
    }
    
    /**
     * Log form validation attempt
     */
    public function log_validation_attempt($ip, $form_name, $result, $error_message = '') {
        $context = [
            'ip' => $ip,
            'form_name' => $form_name,
            'result' => $result,
            'error_message' => $error_message,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];
        
        if ($result === 'blocked') {
            $this->warning('Form submission blocked', $context);
        } else {
            $this->info('Form submission allowed', $context);
        }
    }
    
    /**
     * Log AI validation attempt
     */
    public function log_ai_validation($provider, $result, $confidence = null, $error = '') {
        $context = [
            'provider' => $provider,
            'result' => $result,
            'confidence' => $confidence,
            'error' => $error
        ];
        
        if ($result === 'error') {
            $this->error('AI validation failed', $context);
        } else {
            $this->info('AI validation completed', $context);
        }
    }
    
    /**
     * Log database operation
     */
    public function log_database_operation($operation, $table, $result, $error = '') {
        $context = [
            'operation' => $operation,
            'table' => $table,
            'result' => $result,
            'error' => $error
        ];
        
        if ($result === 'error') {
            $this->error('Database operation failed', $context);
        } else {
            $this->debug('Database operation completed', $context);
        }
    }
}
