<?php
/**
 * Uninstall file for DG10 Elementor Form Anti-Spam
 *
 * This file is executed when the plugin is uninstalled (deleted) via the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package DG10_Elementor_Form_Anti_Spam
 * @version 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall plugins
if (!current_user_can('delete_plugins')) {
    exit;
}

// Get plugin settings to check if data should be removed
$settings = get_option('dg10_antispam_settings', []);
$remove_data = isset($settings['remove_data_on_uninstall']) ? (bool) $settings['remove_data_on_uninstall'] : false;

// Only remove data if user opted in
if (!$remove_data) {
    return;
}

// Remove plugin options
delete_option('dg10_antispam_settings');
delete_option('dg10_blocked_attempts');
delete_option('dg10_protected_forms');
delete_option('dg10_ai_total_checks');
delete_option('dg10_ai_spam_detected');
delete_option('dg10_country_stats');
delete_option('dg10_time_stats');
delete_option('dg10_geographic_stats');
delete_option('dg10_preset_settings');

// Remove user meta for dismissed notices
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        'dg10_dismissed_notice_%'
    )
);

// Drop custom database table
$table_name = $wpdb->prefix . 'dg10_submissions';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any cached data
wp_cache_flush();

// Log uninstall event for debugging (optional)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('DG10 Elementor Form Anti-Spam: Plugin uninstalled and data removed.');
}

