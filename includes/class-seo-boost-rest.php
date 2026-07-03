<?php
/**
 * REST API endpoints that power the dashboard.
 *
 * All routes live under /wp-json/seo-boost/v1/ and require the
 * `manage_options` capability plus a valid nonce (handled by WP for
 * logged-in cookie auth via the X-WP-Nonce header).
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_REST
 */
class SEO_Boost_REST {

	const NAMESPACE = 'seo-boost/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Capability + nonce check for every route.
	 *
	 * @return bool
	 */
	public function permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes() {
		$args_get  = array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => array( $this, 'permissions' ),
		);
		$args_post = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => array( $this, 'permissions' ),
		);

		register_rest_route( self::NAMESPACE, '/stats', array_merge( $args_get, array( 'callback' => array( $this, 'get_stats' ) ) ) );

		register_rest_route( self::NAMESPACE, '/settings', array_merge( $args_get, array( 'callback' => array( $this, 'get_settings' ) ) ) );
		register_rest_route( self::NAMESPACE, '/settings', array_merge( $args_post, array( 'callback' => array( $this, 'save_settings' ) ) ) );

		// IndexNow.
		register_rest_route( self::NAMESPACE, '/indexnow/submit', array_merge( $args_post, array( 'callback' => array( $this, 'indexnow_submit' ) ) ) );
		register_rest_route( self::NAMESPACE, '/indexnow/submit-all', array_merge( $args_post, array( 'callback' => array( $this, 'indexnow_submit_all' ) ) ) );
		register_rest_route( self::NAMESPACE, '/indexnow/regenerate-key', array_merge( $args_post, array( 'callback' => array( $this, 'indexnow_regenerate_key' ) ) ) );
		register_rest_route( self::NAMESPACE, '/indexnow/clear-log', array_merge( $args_post, array( 'callback' => array( $this, 'indexnow_clear_log' ) ) ) );

		// Broken links.
		register_rest_route(
			self::NAMESPACE,
			'/links',
			array_merge(
				$args_get,
				array(
					'callback' => array( $this, 'get_links' ),
					'args'     => array(
						'filter'   => array( 'default' => 'broken' ),
						'page'     => array( 'default' => 1 ),
						'per_page' => array( 'default' => 20 ),
						'search'   => array( 'default' => '' ),
					),
				)
			)
		);
		register_rest_route( self::NAMESPACE, '/links/scan', array_merge( $args_post, array( 'callback' => array( $this, 'links_scan' ) ) ) );
		register_rest_route( self::NAMESPACE, '/links/check-batch', array_merge( $args_post, array( 'callback' => array( $this, 'links_check_batch' ) ) ) );
		register_rest_route(
			self::NAMESPACE,
			'/links/(?P<id>\d+)/recheck',
			array_merge( $args_post, array( 'callback' => array( $this, 'links_recheck' ) ) )
		);
	}

	/**
	 * GET /stats - overview data for the dashboard.
	 *
	 * @return WP_REST_Response
	 */
	public function get_stats() {
		$blc_stats = $this->plugin->broken_links->get_stats();
		$entries   = $this->plugin->sitemap->get_sitemap_entries();
		$log       = $this->plugin->indexnow->get_log();

		$last_submission = ! empty( $log ) ? $log[0] : null;

		return rest_ensure_response(
			array(
				'sitemap'  => array(
					'enabled'    => (bool) SEO_Boost_Settings::get( 'sitemap_enabled', 1 ),
					'url'        => $this->plugin->sitemap->get_index_url(),
					'subsitemaps' => count( $entries ),
				),
				'indexnow' => array(
					'enabled'         => $this->plugin->indexnow->is_enabled(),
					'key'             => $this->plugin->indexnow->get_key(),
					'submissions'     => count( $log ),
					'last_submission' => $last_submission,
				),
				'links'    => $blc_stats,
			)
		);
	}

	/**
	 * GET /settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( SEO_Boost_Settings::all() );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ) {
		$in    = $request->get_json_params();
		$in    = is_array( $in ) ? $in : array();
		$clean = array();

		// Booleans.
		foreach ( array( 'sitemap_enabled', 'sitemap_include_images', 'indexnow_enabled', 'indexnow_auto_submit', 'blc_enabled' ) as $key ) {
			if ( array_key_exists( $key, $in ) ) {
				$clean[ $key ] = ! empty( $in[ $key ] ) ? 1 : 0;
			}
		}

		// Integers.
		if ( isset( $in['sitemap_per_page'] ) ) {
			$clean['sitemap_per_page'] = max( 1, min( 50000, (int) $in['sitemap_per_page'] ) );
		}
		if ( isset( $in['blc_timeout'] ) ) {
			$clean['blc_timeout'] = max( 3, min( 60, (int) $in['blc_timeout'] ) );
		}

		// Arrays of slugs.
		foreach ( array( 'sitemap_post_types', 'sitemap_taxonomies', 'blc_post_types' ) as $key ) {
			if ( isset( $in[ $key ] ) && is_array( $in[ $key ] ) ) {
				$clean[ $key ] = array_values( array_map( 'sanitize_key', $in[ $key ] ) );
			}
		}

		// Frequency (validate against known schedules).
		if ( isset( $in['blc_frequency'] ) ) {
			$allowed                = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
			$freq                   = in_array( $in['blc_frequency'], $allowed, true ) ? $in['blc_frequency'] : 'daily';
			$clean['blc_frequency'] = $freq;
		}

		// Endpoint URL.
		if ( isset( $in['indexnow_endpoint'] ) ) {
			$clean['indexnow_endpoint'] = esc_url_raw( $in['indexnow_endpoint'] );
		}

		$saved = SEO_Boost_Settings::update( $clean );

		// Reschedule cron if the frequency changed.
		if ( isset( $clean['blc_frequency'] ) ) {
			wp_clear_scheduled_hook( 'seo_boost_blc_scan' );
			wp_schedule_event( time() + HOUR_IN_SECONDS, $clean['blc_frequency'], 'seo_boost_blc_scan' );
		}

		// Flush rewrite rules in case sitemap toggled.
		flush_rewrite_rules();

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $saved,
				'message'  => __( 'Settings saved.', 'seo-boost' ),
			)
		);
	}

	/**
	 * POST /indexnow/submit - submit arbitrary URLs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function indexnow_submit( WP_REST_Request $request ) {
		$urls = $request->get_param( 'urls' );
		if ( is_string( $urls ) ) {
			$urls = preg_split( '/\r\n|\r|\n/', $urls );
		}
		$urls   = is_array( $urls ) ? $urls : array();
		$result = $this->plugin->indexnow->submit_urls( $urls );
		return rest_ensure_response( $result );
	}

	/**
	 * POST /indexnow/submit-all.
	 *
	 * @return WP_REST_Response
	 */
	public function indexnow_submit_all() {
		return rest_ensure_response( $this->plugin->indexnow->submit_all() );
	}

	/**
	 * POST /indexnow/regenerate-key.
	 *
	 * @return WP_REST_Response
	 */
	public function indexnow_regenerate_key() {
		require_once SEO_BOOST_PATH . 'includes/class-seo-boost-activator.php';
		$key = SEO_Boost_Activator::generate_indexnow_key();
		SEO_Boost_Settings::update( array( 'indexnow_key' => $key ) );
		flush_rewrite_rules();
		return rest_ensure_response(
			array(
				'success' => true,
				'key'     => $key,
				'message' => __( 'A new IndexNow key has been generated.', 'seo-boost' ),
			)
		);
	}

	/**
	 * POST /indexnow/clear-log.
	 *
	 * @return WP_REST_Response
	 */
	public function indexnow_clear_log() {
		$this->plugin->indexnow->clear_log();
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * GET /links.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_links( WP_REST_Request $request ) {
		$data = $this->plugin->broken_links->get_links(
			array(
				'filter'   => sanitize_key( $request->get_param( 'filter' ) ),
				'page'     => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page' => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			)
		);
		$data['log'] = $this->plugin->indexnow->get_log();
		return rest_ensure_response( $data );
	}

	/**
	 * POST /links/scan - rebuild the link index and reset to pending.
	 *
	 * @return WP_REST_Response
	 */
	public function links_scan() {
		$indexed = $this->plugin->broken_links->rebuild_link_index();
		$pending = $this->plugin->broken_links->count_pending();
		return rest_ensure_response(
			array(
				'success' => true,
				'indexed' => $indexed,
				'pending' => $pending,
				'message' => sprintf( /* translators: %d: link count */ __( 'Indexed %d links. Checking now...', 'seo-boost' ), $indexed ),
			)
		);
	}

	/**
	 * POST /links/check-batch - check a batch of pending links.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_check_batch( WP_REST_Request $request ) {
		$limit   = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );
		$summary = $this->plugin->broken_links->check_pending( $limit ? $limit : 20 );
		$pending = $this->plugin->broken_links->count_pending();
		return rest_ensure_response(
			array(
				'success' => true,
				'checked' => $summary['checked'],
				'broken'  => $summary['broken'],
				'pending' => $pending,
				'done'    => ( 0 === $pending ),
			)
		);
	}

	/**
	 * POST /links/{id}/recheck.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_recheck( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->plugin->broken_links->recheck( $id );
		if ( false === $result ) {
			return new WP_Error( 'not_found', __( 'Link not found.', 'seo-boost' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'result'  => $result,
			)
		);
	}
}
