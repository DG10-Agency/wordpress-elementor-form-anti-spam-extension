<?php
if (!defined('ABSPATH')) exit;

class DG10_Geographic_Blocker {
    private static $instance = null;
    private $settings;
    private $ip_manager;
    private $geoip_data = null;
    private $countries_list = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = DG10_Settings::get_instance();
        $this->ip_manager = DG10_IP_Manager::get_instance();
        $this->load_countries_list();
        add_action('wp_ajax_dg10_block_country', [$this, 'ajax_block_country']);
        add_action('wp_ajax_dg10_get_country_stats', [$this, 'ajax_get_country_stats']);
    }

    /**
     * Load countries list for dropdown
     */
    private function load_countries_list() {
        $this->countries_list = [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
            'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
            'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
            'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
            'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
            'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
            'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
            'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands', 'CO' => 'Colombia',
            'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic', 'CK' => 'Cook Islands',
            'CR' => 'Costa Rica', 'CI' => 'Cote D\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba',
            'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
            'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt',
            'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia',
            'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands',
            'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana',
            'PF' => 'French Polynesia', 'TF' => 'French Southern Territories', 'GA' => 'Gabon',
            'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
            'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
            'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey',
            'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
            'HM' => 'Heard Island and Mcdonald Islands', 'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
            'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic', 'IQ' => 'Iraq',
            'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy',
            'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
            'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic',
            'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
            'MO' => 'Macao', 'MK' => 'Macedonia, Former Yugoslav Republic', 'MG' => 'Madagascar',
            'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali',
            'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania',
            'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States',
            'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
            'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar',
            'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
            'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
            'PW' => 'Palau', 'PS' => 'Palestinian Territory, Occupied', 'PA' => 'Panama', 'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn',
            'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar',
            'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia',
            'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
            'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
            'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Swaziland',
            'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan',
            'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic', 'TH' => 'Thailand', 'TL' => 'Timor-Leste',
            'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom', 'US' => 'United States', 'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela',
            'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.s.',
            'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        ];
    }

    /**
     * Get country code from IP address using free GeoIP
     */
    public function get_country_from_ip($ip) {
        // Validate and sanitize IP address
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'UNKNOWN';
        }

        $ip = sanitize_text_field($ip);

        // Try to get country from WordPress built-in function first (if available)
        if (function_exists('geoip_country_code_by_name')) {
            try {
                $country = call_user_func('geoip_country_code_by_name', $ip);
                if ($country && is_string($country)) {
                    return strtoupper(sanitize_text_field($country));
                }
            } catch (Exception $e) {
                // Log error and continue to fallback
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('DG10 Geographic Blocker: GeoIP function error: ' . $e->getMessage());
                }
            }
        }

        // Fallback to simple IP range checking for common countries
        return $this->get_country_from_ip_ranges($ip);
    }

    /**
     * Simple IP range checking for major countries (fallback method)
     */
    private function get_country_from_ip_ranges($ip) {
        $ip_long = ip2long($ip);
        
        if ($ip_long === false) {
            return 'UNKNOWN';
        }

        // Major IP ranges for common countries (simplified)
        $ranges = [
            'US' => [
                ['3.0.0.0', '3.255.255.255'],
                ['8.0.0.0', '8.255.255.255'],
                ['12.0.0.0', '12.255.255.255'],
                ['24.0.0.0', '24.255.255.255'],
                ['32.0.0.0', '32.255.255.255'],
                ['40.0.0.0', '40.255.255.255'],
                ['50.0.0.0', '50.255.255.255'],
                ['64.0.0.0', '64.255.255.255'],
                ['66.0.0.0', '66.255.255.255'],
                ['68.0.0.0', '68.255.255.255'],
                ['70.0.0.0', '70.255.255.255'],
                ['72.0.0.0', '72.255.255.255'],
                ['74.0.0.0', '74.255.255.255'],
                ['76.0.0.0', '76.255.255.255'],
                ['96.0.0.0', '96.255.255.255'],
                ['97.0.0.0', '97.255.255.255'],
                ['98.0.0.0', '98.255.255.255'],
                ['99.0.0.0', '99.255.255.255'],
                ['104.0.0.0', '104.255.255.255'],
                ['107.0.0.0', '107.255.255.255'],
                ['108.0.0.0', '108.255.255.255'],
                ['152.0.0.0', '152.255.255.255'],
                ['162.0.0.0', '162.255.255.255'],
                ['166.0.0.0', '166.255.255.255'],
                ['168.0.0.0', '168.255.255.255'],
                ['170.0.0.0', '170.255.255.255'],
                ['172.0.0.0', '172.255.255.255'],
                ['173.0.0.0', '173.255.255.255'],
                ['174.0.0.0', '174.255.255.255'],
                ['184.0.0.0', '184.255.255.255'],
                ['192.0.0.0', '192.255.255.255'],
                ['198.0.0.0', '198.255.255.255'],
                ['199.0.0.0', '199.255.255.255'],
                ['204.0.0.0', '204.255.255.255'],
                ['205.0.0.0', '205.255.255.255'],
                ['206.0.0.0', '206.255.255.255'],
                ['207.0.0.0', '207.255.255.255'],
                ['208.0.0.0', '208.255.255.255'],
                ['209.0.0.0', '209.255.255.255'],
                ['216.0.0.0', '216.255.255.255']
            ],
            'GB' => [
                ['2.16.0.0', '2.19.255.255'],
                ['5.0.0.0', '5.255.255.255'],
                ['25.0.0.0', '25.255.255.255'],
                ['31.0.0.0', '31.255.255.255'],
                ['46.0.0.0', '46.255.255.255'],
                ['51.0.0.0', '51.255.255.255'],
                ['77.0.0.0', '77.255.255.255'],
                ['78.0.0.0', '78.255.255.255'],
                ['79.0.0.0', '79.255.255.255'],
                ['80.0.0.0', '80.255.255.255'],
                ['81.0.0.0', '81.255.255.255'],
                ['82.0.0.0', '82.255.255.255'],
                ['83.0.0.0', '83.255.255.255'],
                ['84.0.0.0', '84.255.255.255'],
                ['85.0.0.0', '85.255.255.255'],
                ['86.0.0.0', '86.255.255.255'],
                ['87.0.0.0', '87.255.255.255'],
                ['88.0.0.0', '88.255.255.255'],
                ['89.0.0.0', '89.255.255.255'],
                ['90.0.0.0', '90.255.255.255'],
                ['91.0.0.0', '91.255.255.255'],
                ['92.0.0.0', '92.255.255.255'],
                ['93.0.0.0', '93.255.255.255'],
                ['94.0.0.0', '94.255.255.255'],
                ['95.0.0.0', '95.255.255.255']
            ],
            'DE' => [
                ['5.0.0.0', '5.255.255.255'],
                ['31.0.0.0', '31.255.255.255'],
                ['37.0.0.0', '37.255.255.255'],
                ['46.0.0.0', '46.255.255.255'],
                ['62.0.0.0', '62.255.255.255'],
                ['77.0.0.0', '77.255.255.255'],
                ['78.0.0.0', '78.255.255.255'],
                ['79.0.0.0', '79.255.255.255'],
                ['80.0.0.0', '80.255.255.255'],
                ['81.0.0.0', '81.255.255.255'],
                ['82.0.0.0', '82.255.255.255'],
                ['83.0.0.0', '83.255.255.255'],
                ['84.0.0.0', '84.255.255.255'],
                ['85.0.0.0', '85.255.255.255'],
                ['86.0.0.0', '86.255.255.255'],
                ['87.0.0.0', '87.255.255.255'],
                ['88.0.0.0', '88.255.255.255'],
                ['89.0.0.0', '89.255.255.255'],
                ['90.0.0.0', '90.255.255.255'],
                ['91.0.0.0', '91.255.255.255'],
                ['92.0.0.0', '92.255.255.255'],
                ['93.0.0.0', '93.255.255.255'],
                ['94.0.0.0', '94.255.255.255'],
                ['95.0.0.0', '95.255.255.255']
            ],
            'CN' => [
                ['1.0.0.0', '1.255.255.255'],
                ['14.0.0.0', '14.255.255.255'],
                ['27.0.0.0', '27.255.255.255'],
                ['36.0.0.0', '36.255.255.255'],
                ['39.0.0.0', '39.255.255.255'],
                ['42.0.0.0', '42.255.255.255'],
                ['49.0.0.0', '49.255.255.255'],
                ['58.0.0.0', '58.255.255.255'],
                ['59.0.0.0', '59.255.255.255'],
                ['60.0.0.0', '60.255.255.255'],
                ['61.0.0.0', '61.255.255.255'],
                ['101.0.0.0', '101.255.255.255'],
                ['103.0.0.0', '103.255.255.255'],
                ['106.0.0.0', '106.255.255.255'],
                ['110.0.0.0', '110.255.255.255'],
                ['111.0.0.0', '111.255.255.255'],
                ['112.0.0.0', '112.255.255.255'],
                ['113.0.0.0', '113.255.255.255'],
                ['114.0.0.0', '114.255.255.255'],
                ['115.0.0.0', '115.255.255.255'],
                ['116.0.0.0', '116.255.255.255'],
                ['117.0.0.0', '117.255.255.255'],
                ['118.0.0.0', '118.255.255.255'],
                ['119.0.0.0', '119.255.255.255'],
                ['120.0.0.0', '120.255.255.255'],
                ['121.0.0.0', '121.255.255.255'],
                ['122.0.0.0', '122.255.255.255'],
                ['123.0.0.0', '123.255.255.255'],
                ['124.0.0.0', '124.255.255.255'],
                ['125.0.0.0', '125.255.255.255'],
                ['126.0.0.0', '126.255.255.255'],
                ['171.0.0.0', '171.255.255.255'],
                ['175.0.0.0', '175.255.255.255'],
                ['180.0.0.0', '180.255.255.255'],
                ['182.0.0.0', '182.255.255.255'],
                ['183.0.0.0', '183.255.255.255'],
                ['202.0.0.0', '202.255.255.255'],
                ['203.0.0.0', '203.255.255.255'],
                ['210.0.0.0', '210.255.255.255'],
                ['211.0.0.0', '211.255.255.255'],
                ['218.0.0.0', '218.255.255.255'],
                ['219.0.0.0', '219.255.255.255'],
                ['220.0.0.0', '220.255.255.255'],
                ['221.0.0.0', '221.255.255.255'],
                ['222.0.0.0', '222.255.255.255']
            ]
        ];

        foreach ($ranges as $country => $country_ranges) {
            foreach ($country_ranges as $range) {
                $start = ip2long($range[0]);
                $end = ip2long($range[1]);
                if ($ip_long >= $start && $ip_long <= $end) {
                    return $country;
                }
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Check if IP should be blocked based on geographic rules
     */
    public function is_ip_blocked_by_geography($ip) {
        if (!$this->settings->get_option('enable_geographic_blocking', false)) {
            return false;
        }

        // Validate IP address
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Check if IP is whitelisted
        if ($this->is_ip_whitelisted($ip)) {
            return false;
        }

        $country = $this->get_country_from_ip($ip);
        $blocked_countries = $this->settings->get_option('blocked_countries', []);
        $allowed_countries = $this->settings->get_option('allowed_countries', []);
        $blocking_mode = $this->settings->get_option('geographic_blocking_mode', 'block');

        // Validate country arrays
        if (!is_array($blocked_countries)) {
            $blocked_countries = [];
        }
        if (!is_array($allowed_countries)) {
            $allowed_countries = [];
        }

        if ($blocking_mode === 'allow') {
            // Allow only specific countries
            return !in_array($country, $allowed_countries, true);
        } else {
            // Block specific countries
            return in_array($country, $blocked_countries, true);
        }
    }

    /**
     * Check if IP is whitelisted
     */
    private function is_ip_whitelisted($ip) {
        $whitelisted_ips_string = $this->settings->get_option('geographic_whitelist_ips', '');
        
        // Convert string to array if needed
        if (is_string($whitelisted_ips_string)) {
            $whitelisted_ips = array_filter(array_map('trim', explode("\n", $whitelisted_ips_string)));
        } else {
            $whitelisted_ips = (array) $whitelisted_ips_string;
        }
        
        // Validate each IP in the whitelist
        $valid_ips = [];
        foreach ($whitelisted_ips as $whitelisted_ip) {
            if (filter_var($whitelisted_ip, FILTER_VALIDATE_IP)) {
                $valid_ips[] = sanitize_text_field($whitelisted_ip);
            }
        }
        
        return in_array($ip, $valid_ips, true);
    }

    /**
     * Get countries list for dropdown
     */
    public function get_countries_list() {
        return $this->countries_list;
    }

    /**
     * Get country name by code
     */
    public function get_country_name($country_code) {
        return isset($this->countries_list[$country_code]) ? $this->countries_list[$country_code] : $country_code;
    }

    /**
     * Log country information for IP
     */
    public function log_country_info($ip, $country = null) {
        if (!$country) {
            $country = $this->get_country_from_ip($ip);
        }

        // Store country info in IP manager
        if (method_exists($this->ip_manager, 'log_country_info')) {
            $this->ip_manager->log_country_info($ip, $country);
        }

        // Update country statistics
        $this->update_country_stats($country);
    }

    /**
     * Update country statistics
     */
    private function update_country_stats($country) {
        $stats = get_option('dg10_country_stats', []);
        
        if (!isset($stats[$country])) {
            $stats[$country] = [
                'name' => $this->get_country_name($country),
                'submissions' => 0,
                'blocked' => 0,
                'last_seen' => current_time('mysql')
            ];
        }
        
        $stats[$country]['submissions']++;
        $stats[$country]['last_seen'] = current_time('mysql');
        
        update_option('dg10_country_stats', $stats);
    }

    /**
     * Get country statistics
     */
    public function get_country_stats() {
        return get_option('dg10_country_stats', []);
    }

    /**
     * AJAX handler for blocking a country
     */
    public function ajax_block_country() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dg10_admin')) {
            wp_send_json_error(['message' => __('Security check failed.', 'dg10-antispam')], 403);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dg10-antispam')], 403);
        }
        
        // Sanitize and validate input
        $country_code = sanitize_text_field($_POST['country_code'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? 'block');
        
        // Validate country code format
        if (empty($country_code) || !preg_match('/^[A-Z]{2}$/', $country_code)) {
            wp_send_json_error(['message' => __('Invalid country code format.', 'dg10-antispam')], 400);
        }
        
        // Validate action
        if (!in_array($action, ['block', 'unblock'], true)) {
            wp_send_json_error(['message' => __('Invalid action type.', 'dg10-antispam')], 400);
        }
        
        try {
            $blocked_countries = $this->settings->get_option('blocked_countries', []);
            $allowed_countries = $this->settings->get_option('allowed_countries', []);
            
            // Ensure arrays are valid
            if (!is_array($blocked_countries)) {
                $blocked_countries = [];
            }
            if (!is_array($allowed_countries)) {
                $allowed_countries = [];
            }
            
            if ($action === 'block') {
                if (!in_array($country_code, $blocked_countries, true)) {
                    $blocked_countries[] = $country_code;
                    $this->settings->update_option('blocked_countries', $blocked_countries);
                }
            } elseif ($action === 'unblock') {
                $blocked_countries = array_diff($blocked_countries, [$country_code]);
                $this->settings->update_option('blocked_countries', $blocked_countries);
            }
            
            $country_name = $this->get_country_name($country_code);
            wp_send_json_success([
                'message' => sprintf(__('Country "%s" %s successfully', 'dg10-antispam'), esc_html($country_name), $action === 'block' ? 'blocked' : 'unblocked'),
                'country_code' => sanitize_text_field($country_code),
                'country_name' => esc_html($country_name)
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to update country settings.', 'dg10-antispam')], 500);
        }
    }

    /**
     * AJAX handler for getting country statistics
     */
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
            $stats = $this->get_country_stats();
            
            // Sanitize stats data
            $sanitized_stats = [];
            if (is_array($stats)) {
                foreach ($stats as $code => $data) {
                    $sanitized_stats[sanitize_text_field($code)] = [
                        'name' => sanitize_text_field($data['name'] ?? ''),
                        'submissions' => intval($data['submissions'] ?? 0),
                        'blocked' => intval($data['blocked'] ?? 0),
                        'last_seen' => sanitize_text_field($data['last_seen'] ?? '')
                    ];
                }
            }
            
            wp_send_json_success(['stats' => $sanitized_stats]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Failed to retrieve country statistics.', 'dg10-antispam')], 500);
        }
    }
}
