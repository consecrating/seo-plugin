<?php
/**
 * Broken Link Checker.
 *
 * Extracts links from published content, checks their HTTP status on a
 * schedule (or on demand, in batches), and stores results in a custom table
 * so the dashboard can report and re-check them.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Broken_Links
 */
class SEO_Boost_Broken_Links {

	/**
	 * Status codes considered "broken".
	 *
	 * @var array
	 */
	private $broken_codes = array( 0, 404, 410, 500, 502, 503, 504 );

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_action( 'seo_boost_blc_scan', array( $this, 'run_scheduled_scan' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'seo_boost_broken_links';
	}

	/**
	 * Is the checker enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) SEO_Boost_Settings::get( 'blc_enabled', 1 );
	}

	/**
	 * Add a weekly schedule (WP ships hourly/twicedaily/daily).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'seo-boost' ),
			);
		}
		return $schedules;
	}

	/**
	 * Scheduled scan entry point: rebuild the link list, then check all.
	 */
	public function run_scheduled_scan() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$this->rebuild_link_index();
		$this->check_pending( 200 );
	}

	/**
	 * Walk published content and (re)populate the link table.
	 *
	 * Existing rows for a post are removed and replaced so stale links drop off.
	 *
	 * @return int Number of links indexed.
	 */
	public function rebuild_link_index() {
		global $wpdb;

		$post_types = (array) SEO_Boost_Settings::get( 'blc_post_types', array( 'post', 'page' ) );
		$table      = $this->table();
		$home_host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$indexed    = 0;

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		// Remember previously known URL/status so we can preserve results.
		foreach ( $query->posts as $post ) {
			$links = $this->extract_links( $post->post_content );

			// Clear existing rows for this post, then re-insert.
			$wpdb->delete( $table, array( 'post_id' => $post->ID ), array( '%d' ) ); // phpcs:ignore WordPress.DB

			foreach ( $links as $link ) {
				$url = $link['url'];

				// Skip anchors, mailto, tel, javascript, and same-host relative fragments.
				if ( ! $this->is_checkable_url( $url ) ) {
					continue;
				}

				$wpdb->insert( // phpcs:ignore WordPress.DB
					$table,
					array(
						'post_id'      => $post->ID,
						'url'          => $url,
						'anchor_text'  => $link['anchor'],
						'status_code'  => 0,
						'status'       => 'pending',
						'is_broken'    => 0,
						'last_checked' => null,
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
				);
				$indexed++;
			}
		}

		wp_reset_postdata();
		return $indexed;
	}

	/**
	 * Extract anchor href + text pairs from HTML.
	 *
	 * @param string $content HTML content.
	 * @return array List of arrays: array( 'url' => string, 'anchor' => string ).
	 */
	public function extract_links( $content ) {
		$links = array();

		if ( empty( $content ) ) {
			return $links;
		}

		// Resolve shortcodes so links inside them are captured.
		$content = do_shortcode( $content );

		if ( preg_match_all( '/<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$url    = trim( $m[2] );
				$anchor = trim( wp_strip_all_tags( $m[3] ) );
				if ( '' === $url ) {
					continue;
				}
				$links[] = array(
					'url'    => $this->normalize_url( $url ),
					'anchor' => mb_substr( $anchor, 0, 500 ),
				);
			}
		}

		return $links;
	}

	/**
	 * Normalise a URL (make protocol-relative and relative URLs absolute).
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function normalize_url( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			return set_url_scheme( 'http:' . $url );
		}
		// Relative path -> absolute.
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return home_url( $url );
		}
		return $url;
	}

	/**
	 * Should this URL be HTTP-checked?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_checkable_url( $url ) {
		if ( '' === $url ) {
			return false;
		}
		$lower = strtolower( $url );
		foreach ( array( '#', 'mailto:', 'tel:', 'javascript:', 'data:', 'sms:' ) as $prefix ) {
			if ( 0 === strpos( $lower, $prefix ) ) {
				return false;
			}
		}
		// Must look like http(s).
		return (bool) preg_match( '#^https?://#i', $url );
	}

	/**
	 * Check a batch of pending links.
	 *
	 * @param int $limit Maximum links to check this run.
	 * @return array Summary: array( 'checked' => int, 'broken' => int ).
	 */
	public function check_pending( $limit = 50 ) {
		global $wpdb;
		$table = $this->table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT id, url FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
				'pending',
				$limit
			)
		);

		$checked = 0;
		$broken  = 0;

		foreach ( $rows as $row ) {
			$result = $this->check_url( $row->url );
			$this->save_result( (int) $row->id, $result );
			$checked++;
			if ( $result['is_broken'] ) {
				$broken++;
			}
		}

		return array(
			'checked' => $checked,
			'broken'  => $broken,
		);
	}

	/**
	 * Perform an HTTP check on a single URL.
	 *
	 * @param string $url URL to check.
	 * @return array array( 'code' => int, 'is_broken' => bool, 'status' => string ).
	 */
	public function check_url( $url ) {
		$timeout = max( 3, (int) SEO_Boost_Settings::get( 'blc_timeout', 10 ) );

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'SEO-Boost-LinkChecker/1.0 (+' . home_url() . ')',
		);

		// Try a lightweight HEAD first.
		$response = wp_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Some servers reject HEAD (405) - retry with GET.
		if ( is_wp_error( $response ) || 405 === $code || 0 === $code ) {
			$response = wp_remote_get( $url, $args );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		$is_broken = in_array( $code, $this->broken_codes, true );

		if ( 0 === $code ) {
			$status = 'error';
		} elseif ( $is_broken ) {
			$status = 'broken';
		} elseif ( $code >= 300 && $code < 400 ) {
			$status = 'redirect';
		} else {
			$status = 'ok';
		}

		return array(
			'code'      => $code,
			'is_broken' => $is_broken,
			'status'    => $status,
		);
	}

	/**
	 * Persist a check result.
	 *
	 * @param int   $id     Row id.
	 * @param array $result Result from check_url().
	 */
	private function save_result( $id, array $result ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->table(),
			array(
				'status_code'  => $result['code'],
				'status'       => $result['status'],
				'is_broken'    => $result['is_broken'] ? 1 : 0,
				'last_checked' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Re-check a single stored link by id.
	 *
	 * @param int $id Row id.
	 * @return array|false Result or false if not found.
	 */
	public function recheck( $id ) {
		global $wpdb;
		$table = $this->table();
		$url   = $wpdb->get_var( $wpdb->prepare( "SELECT url FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB

		if ( ! $url ) {
			return false;
		}

		$result = $this->check_url( $url );
		$this->save_result( (int) $id, $result );
		return $result;
	}

	/**
	 * Mark all links pending so the next check re-tests them.
	 */
	public function reset_all_pending() {
		global $wpdb;
		$table = $this->table();
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s", 'pending' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Number of links still pending a check.
	 *
	 * @return int
	 */
	public function count_pending() {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Aggregate statistics for the dashboard.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;
		$table = $this->table();

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		$broken  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE is_broken = %d", 1 ) ); // phpcs:ignore WordPress.DB
		$ok      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'ok' ) ); // phpcs:ignore WordPress.DB
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending' ) ); // phpcs:ignore WordPress.DB
		$redir   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'redirect' ) ); // phpcs:ignore WordPress.DB

		return array(
			'total'    => $total,
			'broken'   => $broken,
			'ok'       => $ok,
			'pending'  => $pending,
			'redirect' => $redir,
		);
	}

	/**
	 * Fetch links for the table view.
	 *
	 * @param array $args filter/paging args.
	 * @return array array( 'items' => array, 'total' => int ).
	 */
	public function get_links( $args = array() ) {
		global $wpdb;
		$table = $this->table();

		$defaults = array(
			'filter'   => 'broken', // broken|all|ok|redirect|pending.
			'per_page' => 20,
			'page'     => 1,
			'search'   => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$params = array();

		switch ( $args['filter'] ) {
			case 'broken':
				$where   .= ' AND is_broken = %d';
				$params[] = 1;
				break;
			case 'ok':
			case 'redirect':
			case 'pending':
				$where   .= ' AND status = %s';
				$params[] = $args['filter'];
				break;
			case 'all':
			default:
				break;
		}

		if ( '' !== $args['search'] ) {
			$where   .= ' AND (url LIKE %s OR anchor_text LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		// Total.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$total     = (int) $wpdb->get_var( empty( $params ) ? $count_sql : $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB

		// Page.
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$list_sql       = "SELECT * FROM {$table} WHERE {$where} ORDER BY is_broken DESC, last_checked DESC LIMIT %d OFFSET %d";
		$list_params    = $params;
		$list_params[]  = $per_page;
		$list_params[]  = $offset;
		$items          = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) ); // phpcs:ignore WordPress.DB

		// Attach edit links / titles.
		foreach ( $items as $item ) {
			$item->post_title = $item->post_id ? get_the_title( $item->post_id ) : '';
			$item->edit_link  = $item->post_id ? get_edit_post_link( $item->post_id, 'raw' ) : '';
			$item->view_link  = $item->post_id ? get_permalink( $item->post_id ) : '';
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
