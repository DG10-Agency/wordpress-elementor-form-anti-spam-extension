<?php
if (!defined('ABSPATH')) exit;

class DG10_AI_Validator {
    private static $instance = null;
    private $settings;
    private $deepseek_endpoint = 'https://api.deepseek.com/v1/chat/completions';
    private $gemini_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
    }

    public function validate_with_ai($form_data) {
        $deepseek_enabled = $this->settings->get_option('enable_deepseek', false);
        $gemini_enabled = $this->settings->get_option('enable_gemini', false);
        $results = [];

        if ($deepseek_enabled) {
            $results[] = $this->check_with_deepseek($form_data);
        }

        if ($gemini_enabled) {
            $results[] = $this->check_with_gemini($form_data);
        }

        // If any AI model detects spam, return false and update stats
        $is_spam = in_array(false, $results, true);
        $this->update_ai_stats($is_spam);
        return !$is_spam;
    }

    private function check_with_deepseek($form_data) {
        $api_key = $this->settings->get_option('deepseek_api_key', '');
        if (empty($api_key)) {
            return true; // Skip validation if no API key
        }

        $prompt = $this->prepare_ai_prompt($form_data);
        $response = wp_remote_post($this->deepseek_endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a spam detection AI. Analyze the form submission and respond with only "SPAM" or "NOT_SPAM". Consider patterns, language, and context.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1
            ])
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DeepSeek API Error: ' . $response->get_error_message());
            }
            return true; // Allow submission on API error
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DeepSeek API HTTP Error: ' . $response_code);
            }
            return true; // Allow submission on API error
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DeepSeek API JSON Error: ' . json_last_error_msg());
            }
            return true; // Allow submission on JSON error
        }
        
        $result = isset($body['choices'][0]['message']['content']) ? $body['choices'][0]['message']['content'] : '';
        
        return trim($result) !== 'SPAM';
    }

    private function check_with_gemini($form_data) {
        $api_key = $this->settings->get_option('gemini_api_key', '');
        if (empty($api_key)) {
            return true; // Skip validation if no API key
        }

        $prompt = $this->prepare_ai_prompt($form_data);
        $response = wp_remote_post($this->gemini_endpoint . '?key=' . $api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "You are a spam detection AI. Analyze this form submission and respond with only 'SPAM' or 'NOT_SPAM'. Form data: " . $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'topK' => 1,
                    'topP' => 0.1
                ]
            ])
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API Error: ' . $response->get_error_message());
            }
            return true; // Allow submission on API error
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API HTTP Error: ' . $response_code);
            }
            return true; // Allow submission on API error
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gemini API JSON Error: ' . json_last_error_msg());
            }
            return true; // Allow submission on JSON error
        }
        
        $result = isset($body['candidates'][0]['content']['parts'][0]['text']) ? $body['candidates'][0]['content']['parts'][0]['text'] : '';
        
        return trim($result) !== 'SPAM';
    }

    private function prepare_ai_prompt($form_data) {
        $prompt = "Form submission analysis request:\n";
        
        foreach ($form_data as $field) {
            $field_type = isset($field['type']) ? $field['type'] : 'text';
            $field_value = isset($field['value']) ? $field['value'] : '';
            $prompt .= "Field type: {$field_type}\nContent: {$field_value}\n";
        }

        // Add submission context (sanitized)
        $prompt .= "\nSubmission context:\n";
        $prompt .= "IP: " . $this->get_safe_ip() . "\n";
        $prompt .= "User Agent: " . $this->get_safe_user_agent() . "\n";
        $prompt .= "Timestamp: " . current_time('mysql') . "\n";

        return $prompt;
    }

    public function get_ai_stats() {
        return [
            'total_checks' => intval(get_option('dg10_ai_total_checks', 0)),
            'spam_detected' => intval(get_option('dg10_ai_spam_detected', 0))
        ];
    }

    public function update_ai_stats($is_spam) {
        $total_checks = intval(get_option('dg10_ai_total_checks', 0)) + 1;
        $spam_detected = intval(get_option('dg10_ai_spam_detected', 0)) + ($is_spam ? 1 : 0);

        update_option('dg10_ai_total_checks', $total_checks);
        update_option('dg10_ai_spam_detected', $spam_detected);
    }

    private function get_safe_ip() {
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
        return $ip ?: 'unknown';
    }

    private function get_safe_user_agent() {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            return sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 200));
        }
        return 'unknown';
    }
}