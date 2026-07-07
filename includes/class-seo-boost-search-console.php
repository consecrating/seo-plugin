<?php
/**
 * Google Search Console integration.
 *
 * Provides the "HTML tag" site-verification method (the simplest way to
 * connect a site to Search Console without an OAuth app) by printing the
 * google-site-verification meta tag in the site <head>. Also exposes helpers
 * used by the dashboard to link straight to the correct Search Console screens
 * for adding the sitemap.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Search_Console
 */
class SEO_Boost_Search_Console {

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_verification_meta' ), 1 );
	}

	/**
	 * Stored verification token.
	 *
	 * @return string
	 */
	public function get_code() {
		return (string) SEO_Boost_Settings::get( 'gsc_verification', '' );
	}

	/**
	 * Is a verification token present?
	 *
	 * @return bool
	 */
	public function is_verified() {
		return '' !== trim( $this->get_code() );
	}

	/**
	 * Print the verification meta tag on the front end.
	 */
	public function print_verification_meta() {
		$code = trim( $this->get_code() );
		if ( '' === $code ) {
			return;
		}
		echo '<meta name="google-site-verification" content="' . esc_attr( $code ) . '" />' . "\n";
	}

	/**
	 * Accept either a raw token or a full meta tag pasted from Search Console
	 * and return just the token.
	 *
	 * @param string $value User-supplied value.
	 * @return string
	 */
	public static function extract_code( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		// If the user pasted the whole <meta ... content="TOKEN" ...> tag,
		// pull the content attribute out of it.
		if ( preg_match( '/content=("|\')([^"\']+)\1/i', $value, $m ) ) {
			$value = $m[2];
		}

		// Tokens are limited to a safe character set.
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $value );
	}

	/**
	 * Search Console URL to add / view sitemaps for this property.
	 *
	 * Uses the URL-prefix property format (the most common) and pre-fills the
	 * site so the user lands on the right screen.
	 *
	 * @return string
	 */
	public function sitemaps_dashboard_url() {
		$site = rawurlencode( home_url( '/' ) );
		return 'https://search.google.com/search-console/sitemaps?resource_id=' . $site;
	}

	/**
	 * Search Console "add property" URL.
	 *
	 * @return string
	 */
	public function add_property_url() {
		return 'https://search.google.com/search-console/welcome?resource_id=' . rawurlencode( home_url( '/' ) );
	}
}
