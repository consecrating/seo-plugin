<?php
/**
 * Handles activation / deactivation tasks.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Activator
 */
class SEO_Boost_Activator {

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-settings.php';

		// Seed default settings and generate an IndexNow key on first install.
		$settings = SEO_Boost_Settings::all();
		if ( empty( $settings['indexnow_key'] ) ) {
			$settings['indexnow_key'] = self::generate_indexnow_key();
		}
		update_option( SEO_Boost_Settings::OPTION_KEY, $settings );

		// Create the broken-links results table.
		self::create_tables();

		// Register rewrite rules for the sitemap, then flush.
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-sitemap.php';
		$sitemap = new SEO_Boost_Sitemap();
		$sitemap->add_rewrite_rules();
		flush_rewrite_rules();

		// Schedule the recurring broken-link scan.
		if ( ! wp_next_scheduled( 'seo_boost_blc_scan' ) ) {
			$frequency = $settings['blc_frequency'] ? $settings['blc_frequency'] : 'daily';
			wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, 'seo_boost_blc_scan' );
		}
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'seo_boost_blc_scan' );
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'seo_boost_broken_links';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			url VARCHAR(2083) NOT NULL,
			anchor_text VARCHAR(512) NOT NULL DEFAULT '',
			status_code SMALLINT(6) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'unknown',
			is_broken TINYINT(1) NOT NULL DEFAULT 0,
			last_checked DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY is_broken (is_broken),
			KEY url (url(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Generate a valid IndexNow key (32-64 hex chars).
	 *
	 * @return string
	 */
	public static function generate_indexnow_key() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return str_replace( '-', '', wp_generate_uuid4() ) . str_replace( '-', '', wp_generate_uuid4() );
		}
		return md5( uniqid( (string) wp_rand(), true ) ) . md5( uniqid( (string) wp_rand(), true ) );
	}
}
