<?php
/**
 * Admin dashboard: menus, asset loading, and the app shell.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Admin
 */
class SEO_Boost_Admin {

	const MENU_SLUG = 'seo-boost';

	/**
	 * Plugin container.
	 *
	 * @var SEO_Boost
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param SEO_Boost $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . SEO_BOOST_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Register the top-level menu + submenus.
	 */
	public function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'SEO Boost', 'seo-boost' ),
			__( 'SEO Boost', 'seo-boost' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_app' ),
			'dashicons-chart-area',
			58
		);

		// Rename the auto-generated first submenu entry to "Dashboard".
		add_submenu_page( self::MENU_SLUG, __( 'Dashboard', 'seo-boost' ), __( 'Dashboard', 'seo-boost' ), $cap, self::MENU_SLUG, array( $this, 'render_app' ) );
	}

	/**
	 * Enqueue CSS/JS only on our page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'seo-boost-admin',
			SEO_BOOST_URL . 'admin/css/admin.css',
			array(),
			SEO_BOOST_VERSION
		);

		wp_enqueue_script(
			'seo-boost-admin',
			SEO_BOOST_URL . 'admin/js/admin.js',
			array( 'wp-api-fetch' ),
			SEO_BOOST_VERSION,
			true
		);

		wp_localize_script(
			'seo-boost-admin',
			'SEOBoost',
			array(
				'root'       => esc_url_raw( rest_url( SEO_Boost_REST::NAMESPACE ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'version'    => SEO_BOOST_VERSION,
				'homeUrl'    => home_url( '/' ),
				'adminUrl'   => admin_url(),
				'postTypes'  => $this->get_public_post_types(),
				'taxonomies' => $this->get_public_taxonomies(),
				'i18n'       => array(
					'saved'      => __( 'Saved!', 'seo-boost' ),
					'error'      => __( 'Something went wrong.', 'seo-boost' ),
					'confirmKey' => __( 'Regenerate the IndexNow key? Search engines will need to re-verify the new key file.', 'seo-boost' ),
				),
			)
		);
	}

	/**
	 * Public post types (slug => label).
	 *
	 * @return array
	 */
	private function get_public_post_types() {
		$out   = array();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $types as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$out[ $type->name ] = $type->labels->name;
		}
		return $out;
	}

	/**
	 * Public taxonomies (slug => label).
	 *
	 * @return array
	 */
	private function get_public_taxonomies() {
		$out  = array();
		$taxs = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxs as $tax ) {
			$out[ $tax->name ] = $tax->labels->name;
		}
		return $out;
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Dashboard', 'seo-boost' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Render the app shell. The SPA-style UI is driven by admin.js.
	 */
	public function render_app() {
		require SEO_BOOST_PATH . 'admin/views/app.php';
	}
}
