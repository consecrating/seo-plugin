<?php
/**
 * Centralised settings helper.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Settings
 *
 * Thin wrapper around a single options array so every module reads/writes
 * settings the same way.
 */
class SEO_Boost_Settings {

	const OPTION_KEY = 'seo_boost_settings';

	/**
	 * Return default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Sitemap.
			'sitemap_enabled'        => 1,
			'sitemap_post_types'     => array( 'post', 'page' ),
			'sitemap_taxonomies'     => array( 'category', 'post_tag' ),
			'sitemap_per_page'       => 1000,
			'sitemap_include_images' => 1,

			// IndexNow.
			'indexnow_enabled'       => 1,
			'indexnow_key'           => '',
			'indexnow_endpoint'      => 'https://api.indexnow.org/indexnow',
			'indexnow_auto_submit'   => 1,

			// Broken links.
			'blc_enabled'            => 1,
			'blc_frequency'          => 'daily',
			'blc_post_types'         => array( 'post', 'page' ),
			'blc_timeout'            => 10,

			// Google Search Console.
			'gsc_verification'       => '',

			// Structured data / Local SEO (Schema.org).
			'schema_enabled'         => 1,
			'schema_type'            => 'Organization', // Organization | LocalBusiness | ProfessionalService.
			'org_name'               => '',
			'org_logo'               => '',
			'org_phone'              => '',
			'org_email'              => '',
			'org_street'             => '',
			'org_locality'           => '', // City, e.g. Panaji.
			'org_region'             => '', // State, e.g. Goa.
			'org_postal'             => '',
			'org_country'            => 'IN',
			'org_lat'                => '',
			'org_lng'                => '',
			'org_area_served'        => '', // e.g. Goa.
			'org_price_range'        => '',
			'org_hours'              => '', // e.g. Mo-Sa 09:00-18:00.
			'social_profiles'        => array(),
			'schema_article'         => 1,
			'schema_breadcrumbs'     => 1,
			'schema_searchbox'       => 1,

			// Content freshness.
			'freshness_post_types'   => array( 'post' ),
			'freshness_aging_months' => 3,
			'freshness_stale_months' => 6,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Persist the full settings array (values are merged over existing).
	 *
	 * @param array $values Values to save.
	 * @return array The stored settings.
	 */
	public static function update( array $values ) {
		$current = self::all();
		$merged  = array_merge( $current, $values );
		update_option( self::OPTION_KEY, $merged );
		return $merged;
	}
}
