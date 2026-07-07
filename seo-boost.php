<?php
/**
 * Plugin Name:       SEO Boost - Sitemap, IndexNow & Broken Links
 * Plugin URI:        https://example.com/seo-boost
 * Description:        All-in-one SEO toolkit: XML sitemaps, IndexNow, broken link checker, Google Search Console, Local SEO / Schema, content freshness audit, and an AI Content Kit that exports briefs for Claude/ChatGPT - in a modern dashboard.
 * Version:           1.3.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Your Agency
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-boost
 * Domain Path:       /languages
 *
 * @package SEO_Boost
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin constants.
 */
define( 'SEO_BOOST_VERSION', '1.3.0' );
define( 'SEO_BOOST_FILE', __FILE__ );
define( 'SEO_BOOST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEO_BOOST_URL', plugin_dir_url( __FILE__ ) );
define( 'SEO_BOOST_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the main plugin class.
 */
require_once SEO_BOOST_PATH . 'includes/class-seo-boost.php';

/**
 * Activation hook - set defaults, create tables, flush rewrite rules.
 */
function seo_boost_activate() {
	require_once SEO_BOOST_PATH . 'includes/class-seo-boost-activator.php';
	SEO_Boost_Activator::activate();
}
register_activation_hook( __FILE__, 'seo_boost_activate' );

/**
 * Deactivation hook - clear scheduled events and flush rewrite rules.
 */
function seo_boost_deactivate() {
	require_once SEO_BOOST_PATH . 'includes/class-seo-boost-activator.php';
	SEO_Boost_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'seo_boost_deactivate' );

/**
 * Boot the plugin.
 *
 * @return SEO_Boost
 */
function seo_boost() {
	return SEO_Boost::instance();
}
seo_boost();
