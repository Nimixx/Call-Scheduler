<?php
/**
 * Plugin uninstall handler
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 * Removes all plugin data from the database.
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop plugin tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cs_bookings");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cs_availability");

// Delete plugin options
delete_option('cs_db_version');

// Delete all user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'cs_is_team_member'");

// Clear all transients (rate limits, etc.)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cs_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cs_%'");

// Clear any cached data
wp_cache_flush();
