<?php
if (!defined('ABSPATH')) exit;

class DG10_IP_Manager {
    private static $instance = null;
    private $submission_times = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function clean_old_submissions() {
        $current_time = time();
        foreach ($this->submission_times as $ip => $times) {
            $this->submission_times[$ip] = array_filter($times, function($time) use ($current_time) {
                return ($current_time - $time) < 3600;
            });
            if (empty($this->submission_times[$ip])) {
                unset($this->submission_times[$ip]);
            }
        }
    }

    public function is_submission_rate_exceeded($ip, $max_submissions) {
        if (!isset($this->submission_times[$ip])) {
            $this->submission_times[$ip] = [];
        }
        $this->submission_times[$ip][] = time();
        return count($this->submission_times[$ip]) > $max_submissions;
    }

    public function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP);
    }

    // Elementor passes ($record, $handler)
    public function log_submission($record, $handler) {
        $ip = $this->get_client_ip();
        $form_name = is_object($record) && method_exists($record, 'get_form_settings')
            ? $record->get_form_settings('form_name')
            : 'unknown';

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