<?php
/**
 * IndexNow integration.
 *
 * Instantly notifies participating search engines (Bing, Yandex, Seznam,
 * Naver, and via the shared protocol, others) whenever content is published,
 * updated, or trashed. Also serves the key verification file.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_IndexNow
 */
class SEO_Boost_IndexNow {

	const LOG_OPTION = 'seo_boost_indexnow_log';
	const LOG_LIMIT  = 50;

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		// Serve the key verification file: /{key}.txt.
		add_action( 'init', array( $this, 'add_key_file_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_key_file' ) );

		// Auto-submit on content changes.
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
	}

	/**
	 * Is IndexNow enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) SEO_Boost_Settings::get( 'indexnow_enabled', 1 );
	}

	/**
	 * Return the configured key (generating one if empty).
	 *
	 * @return string
	 */
	public function get_key() {
		$key = SEO_Boost_Settings::get( 'indexnow_key', '' );
		if ( empty( $key ) ) {
			require_once SEO_BOOST_PATH . 'includes/class-seo-boost-activator.php';
			$key = SEO_Boost_Activator::generate_indexnow_key();
			SEO_Boost_Settings::update( array( 'indexnow_key' => $key ) );
		}
		return $key;
	}

	/**
	 * Public URL where the key verification file is served.
	 *
	 * @return string
	 */
	public function get_key_file_url() {
		return home_url( '/' . $this->get_key() . '.txt' );
	}

	/**
	 * Confirm the key file is publicly reachable and returns the exact key.
	 *
	 * A failing check is the usual reason submissions stay stuck on "key
	 * validation pending" (HTTP 202) or get rejected (HTTP 403), so this gives
	 * the user a one-click way to diagnose it.
	 *
	 * @return array {
	 *     @type bool   $reachable Whether the file responded with HTTP 200.
	 *     @type bool   $matches   Whether the file content matches the key.
	 *     @type int    $code      HTTP status code of the request.
	 *     @type string $url       The key file URL that was checked.
	 *     @type string $message   Human-readable summary.
	 * }
	 */
	public function verify_key() {
		$url      = $this->get_key_file_url();
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'reachable' => false,
				'matches'   => false,
				'code'      => 0,
				'url'       => $url,
				'message'   => sprintf(
					/* translators: %s: error message */
					__( 'Could not reach the key file: %s', 'seo-boost' ),
					$response->get_error_message()
				),
			);
		}

		$code      = (int) wp_remote_retrieve_response_code( $response );
		$body      = trim( (string) wp_remote_retrieve_body( $response ) );
		$reachable = ( 200 === $code );
		$matches   = $reachable && hash_equals( $this->get_key(), $body );

		if ( $matches ) {
			$message = __( 'Verified! Your key file is publicly accessible and search engines can validate it.', 'seo-boost' );
		} elseif ( $reachable ) {
			$message = __( 'The key file is reachable but its contents do not match the current key. Try re-saving or regenerating the key.', 'seo-boost' );
		} else {
			$message = sprintf(
				/* translators: %d: HTTP code */
				__( 'The key file is not accessible (HTTP %d). Go to Settings > Permalinks and click Save once to flush rewrite rules, then try again.', 'seo-boost' ),
				$code
			);
		}

		return array(
			'reachable' => $reachable,
			'matches'   => $matches,
			'code'      => $code,
			'url'       => $url,
			'message'   => $message,
		);
	}

	/**
	 * Register the {key}.txt rewrite rule.
	 */
	public function add_key_file_rewrite() {
		add_rewrite_rule( '^([a-zA-Z0-9-]{8,128})\.txt$', 'index.php?seo_boost_indexnow_key=$matches[1]', 'top' );
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'seo_boost_indexnow_key';
		return $vars;
	}

	/**
	 * Serve the key file when the request matches our key.
	 */
	public function maybe_serve_key_file() {
		$requested = get_query_var( 'seo_boost_indexnow_key' );
		if ( empty( $requested ) ) {
			return;
		}

		if ( hash_equals( $this->get_key(), (string) $requested ) ) {
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo esc_html( $this->get_key() );
			exit;
		}
	}

	/**
	 * React to post status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $this->is_enabled() || ! (bool) SEO_Boost_Settings::get( 'indexnow_auto_submit', 1 ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		// Only public, viewable post types.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
			return;
		}

		// Became public, or a public post was updated / unpublished.
		$was_public = ( 'publish' === $old_status );
		$is_public  = ( 'publish' === $new_status );

		if ( $is_public || $was_public ) {
			$url = get_permalink( $post );
			if ( $url ) {
				$this->submit_urls( array( $url ) );
			}
		}
	}

	/**
	 * Submit one or more URLs to the IndexNow endpoint.
	 *
	 * @param array $urls List of absolute URLs.
	 * @return array Result: array( 'success' => bool, 'code' => int, 'message' => string, 'count' => int ).
	 */
	public function submit_urls( array $urls ) {
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );

		if ( empty( $urls ) ) {
			return array(
				'success' => false,
				'code'    => 0,
				'message' => __( 'No valid URLs to submit.', 'seo-boost' ),
				'count'   => 0,
			);
		}

		$key      = $this->get_key();
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$endpoint = SEO_Boost_Settings::get( 'indexnow_endpoint', 'https://api.indexnow.org/indexnow' );

		$body = array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => home_url( '/' . $key . '.txt' ),
			'urlList'     => $urls,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 15,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			$result = array(
				'success' => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
				'count'   => count( $urls ),
			);
		} else {
			$code    = (int) wp_remote_retrieve_response_code( $response );
			$success = in_array( $code, array( 200, 202 ), true );
			$result  = array(
				'success' => $success,
				'code'    => $code,
				'message' => $this->describe_code( $code ),
				'count'   => count( $urls ),
			);
		}

		$this->log_submission( $urls, $result );
		return $result;
	}

	/**
	 * Human-readable description for common IndexNow status codes.
	 *
	 * @param int $code HTTP status code.
	 * @return string
	 */
	private function describe_code( $code ) {
		$map = array(
			200 => __( 'OK - URLs received and key verified.', 'seo-boost' ),
			202 => __( 'Accepted - URLs received. The engine will verify your key file shortly (this is normal, especially on the first submission).', 'seo-boost' ),
			400 => __( 'Bad request - invalid format.', 'seo-boost' ),
			403 => __( 'Forbidden - key not valid or not found.', 'seo-boost' ),
			422 => __( 'Unprocessable - URLs do not belong to the host, or the key mismatched.', 'seo-boost' ),
			429 => __( 'Too many requests - you are being rate limited.', 'seo-boost' ),
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : sprintf( /* translators: %d: HTTP code */ __( 'Received HTTP %d.', 'seo-boost' ), $code );
	}

	/**
	 * Append a submission to the rolling log.
	 *
	 * @param array $urls   Submitted URLs.
	 * @param array $result Result array.
	 */
	private function log_submission( array $urls, array $result ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'urls'    => $urls,
				'count'   => $result['count'],
				'code'    => $result['code'],
				'success' => $result['success'],
				'message' => $result['message'],
			)
		);

		$log = array_slice( $log, 0, self::LOG_LIMIT );
		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Return the submission log.
	 *
	 * @return array
	 */
	public function get_log() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the submission log.
	 */
	public function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Submit the whole sitemap (all indexable URLs). Useful for a manual
	 * "resubmit everything" action - chunked to respect API limits.
	 *
	 * @param int $max Maximum URLs to gather.
	 * @return array Aggregated result.
	 */
	public function submit_all( $max = 10000 ) {
		$urls       = array();
		$post_types = (array) SEO_Boost_Settings::get( 'sitemap_post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			$query = new WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 2000,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $query->posts as $post_id ) {
				$urls[] = get_permalink( $post_id );
				if ( count( $urls ) >= $max ) {
					break 2;
				}
			}
		}

		$urls    = array_values( array_unique( array_filter( $urls ) ) );
		$chunks  = array_chunk( $urls, 100 );
		$sent    = 0;
		$success = true;
		$last    = array();

		foreach ( $chunks as $chunk ) {
			$res  = $this->submit_urls( $chunk );
			$last = $res;
			if ( $res['success'] ) {
				$sent += $res['count'];
			} else {
				$success = false;
			}
		}

		return array(
			'success' => $success,
			'count'   => $sent,
			'total'   => count( $urls ),
			'message' => empty( $last ) ? __( 'Nothing to submit.', 'seo-boost' ) : $last['message'],
		);
	}
}
