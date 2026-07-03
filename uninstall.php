<?php
/**
 * Fired when the plugin is deleted from the Plugins screen.
 *
 * Removes plugin options, the custom table, and scheduled events.
 *
 * @package SEO_Boost
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove options.
delete_option( 'seo_boost_settings' );
delete_option( 'seo_boost_indexnow_log' );

// Drop the custom table.
$table = $wpdb->prefix . 'seo_boost_broken_links';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

// Clear any scheduled events.
wp_clear_scheduled_hook( 'seo_boost_blc_scan' );
