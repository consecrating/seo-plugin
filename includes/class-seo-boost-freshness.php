<?php
/**
 * Content Freshness audit.
 *
 * Google rewards content that stays current ("freshness" / QDF). This module
 * analyses when each piece of content was last modified and surfaces the pages
 * that are getting stale, so an agency knows exactly what to refresh to keep
 * rankings from decaying. It is read-only (computed from post_modified), so
 * there is nothing to store or schedule.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Freshness
 */
class SEO_Boost_Freshness {

	/**
	 * Post types to audit.
	 *
	 * @return array
	 */
	private function post_types() {
		$types = (array) SEO_Boost_Settings::get( 'freshness_post_types', array( 'post' ) );
		$types = array_values( array_filter( $types, 'post_type_exists' ) );
		return empty( $types ) ? array( 'post' ) : $types;
	}

	/**
	 * Aging threshold in days.
	 *
	 * @return int
	 */
	public function aging_days() {
		return max( 1, (int) SEO_Boost_Settings::get( 'freshness_aging_months', 3 ) ) * 30;
	}

	/**
	 * Stale threshold in days.
	 *
	 * @return int
	 */
	public function stale_days() {
		return max( 2, (int) SEO_Boost_Settings::get( 'freshness_stale_months', 6 ) ) * 30;
	}

	/**
	 * Days since a post was last modified.
	 *
	 * @param WP_Post|int $post Post.
	 * @return int
	 */
	public function days_since_modified( $post ) {
		$modified = (int) get_post_modified_time( 'U', true, $post );
		if ( $modified <= 0 ) {
			return 0;
		}
		$diff = time() - $modified;
		return (int) floor( $diff / DAY_IN_SECONDS );
	}

	/**
	 * Compute a 0-100 freshness score from the age in days.
	 *
	 * 100 = updated today; drops to 0 as it approaches (and passes) the stale
	 * threshold. Simple, explainable, and good enough to prioritise work.
	 *
	 * @param int $days Days since modified.
	 * @return int
	 */
	public function score_from_days( $days ) {
		$stale = $this->stale_days();
		if ( $days <= 0 ) {
			return 100;
		}
		if ( $days >= $stale ) {
			return 0;
		}
		return (int) round( 100 * ( 1 - ( $days / $stale ) ) );
	}

	/**
	 * Classify a post as fresh / aging / stale.
	 *
	 * @param int $days Days since modified.
	 * @return string
	 */
	public function classify( $days ) {
		if ( $days >= $this->stale_days() ) {
			return 'stale';
		}
		if ( $days >= $this->aging_days() ) {
			return 'aging';
		}
		return 'fresh';
	}

	/**
	 * Site-wide freshness summary.
	 *
	 * @return array
	 */
	public function get_summary() {
		$query = new WP_Query(
			array(
				'post_type'              => $this->post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);

		$fresh      = 0;
		$aging      = 0;
		$stale      = 0;
		$total      = 0;
		$score_sum  = 0;
		$oldest_days = 0;

		foreach ( $query->posts as $post_id ) {
			$days  = $this->days_since_modified( $post_id );
			$class = $this->classify( $days );
			$score_sum += $this->score_from_days( $days );
			$oldest_days = max( $oldest_days, $days );
			$total++;

			if ( 'stale' === $class ) {
				$stale++;
			} elseif ( 'aging' === $class ) {
				$aging++;
			} else {
				$fresh++;
			}
		}

		return array(
			'total'          => $total,
			'fresh'          => $fresh,
			'aging'          => $aging,
			'stale'          => $stale,
			'avg_score'      => $total > 0 ? (int) round( $score_sum / $total ) : 100,
			'oldest_days'    => $oldest_days,
			'aging_months'   => (int) SEO_Boost_Settings::get( 'freshness_aging_months', 3 ),
			'stale_months'   => (int) SEO_Boost_Settings::get( 'freshness_stale_months', 6 ),
		);
	}

	/**
	 * Get a paged list of content, oldest first, with freshness data.
	 *
	 * @param array $args filter/paging.
	 * @return array array( 'items' => array, 'total' => int ).
	 */
	public function get_content( $args = array() ) {
		$defaults = array(
			'filter'   => 'stale', // stale|aging|fresh|all.
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'              => $this->post_types(),
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'modified',
			'order'                  => 'ASC', // Oldest (most stale) first.
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			's'                      => $args['search'],
		);

		$query = new WP_Query( $query_args );
		$all   = array();

		foreach ( $query->posts as $post ) {
			$days  = $this->days_since_modified( $post );
			$class = $this->classify( $days );

			if ( 'all' !== $args['filter'] && $class !== $args['filter'] ) {
				continue;
			}

			$all[] = array(
				'id'            => $post->ID,
				'title'         => wp_strip_all_tags( get_the_title( $post ) ),
				'url'           => get_permalink( $post ),
				'edit_link'     => get_edit_post_link( $post->ID, 'raw' ),
				'post_type'     => $post->post_type,
				'modified'      => get_post_modified_time( 'Y-m-d', true, $post ),
				'days'          => $days,
				'score'         => $this->score_from_days( $days ),
				'status'        => $class,
			);
		}

		wp_reset_postdata();

		$total    = count( $all );
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$items    = array_slice( $all, $offset, $per_page );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
