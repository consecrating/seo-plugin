<?php
/**
 * Main plugin class - loads and coordinates all modules.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost
 */
final class SEO_Boost {

	/**
	 * Singleton instance.
	 *
	 * @var SEO_Boost|null
	 */
	private static $instance = null;

	/**
	 * Sitemap module.
	 *
	 * @var SEO_Boost_Sitemap
	 */
	public $sitemap;

	/**
	 * IndexNow module.
	 *
	 * @var SEO_Boost_IndexNow
	 */
	public $indexnow;

	/**
	 * Broken links module.
	 *
	 * @var SEO_Boost_Broken_Links
	 */
	public $broken_links;

	/**
	 * Admin module.
	 *
	 * @var SEO_Boost_Admin
	 */
	public $admin;

	/**
	 * REST API module.
	 *
	 * @var SEO_Boost_REST
	 */
	public $rest;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return SEO_Boost
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->includes();
			self::$instance->init_modules();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-activator.php';
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-settings.php';
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-sitemap.php';
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-indexnow.php';
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-broken-links.php';
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-rest.php';

		if ( is_admin() ) {
			require_once SEO_BOOST_PATH . 'includes/class-seo-boost-admin.php';
		}
	}

	/**
	 * Instantiate the modules.
	 */
	private function init_modules() {
		$this->sitemap      = new SEO_Boost_Sitemap();
		$this->indexnow     = new SEO_Boost_IndexNow();
		$this->broken_links = new SEO_Boost_Broken_Links();
		$this->rest         = new SEO_Boost_REST( $this );

		if ( is_admin() ) {
			$this->admin = new SEO_Boost_Admin( $this );
		}
	}

	/**
	 * Register global hooks.
	 */
	private function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'seo-boost', false, dirname( SEO_BOOST_BASENAME ) . '/languages' );
	}
}
