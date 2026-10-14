<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Clickadu_Stats
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('clickadu_dash_options');
delete_option('clickadu_dash_redirect');

// Delete any transients matching clickadu_tot_*
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_clickadu_tot_%' OR option_name LIKE '_transient_timeout_clickadu_tot_%'");
