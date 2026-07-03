<?php
/**
 * XML Sitemap generator.
 *
 * Serves a sitemap index plus per-type sub-sitemaps at pretty URLs:
 *   /sitemap.xml                 -> index
 *   /sitemap-post-1.xml          -> posts, page 1
 *   /sitemap-page-1.xml          -> pages, page 1
 *   /sitemap-tax-category-1.xml  -> category terms, page 1
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Sitemap
 */
class SEO_Boost_Sitemap {

	/**
	 * Query var used to route sitemap requests.
	 *
	 * @var string
	 */
	private $query_var = 'seo_boost_sitemap';

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render' ) );

		// Disable the core WP sitemap so the two don't collide.
		add_filter( 'wp_sitemaps_enabled', array( $this, 'maybe_disable_core_sitemap' ) );

		// Advertise the sitemap in robots.txt.
		add_filter( 'robots_txt', array( $this, 'add_to_robots' ), 10, 1 );
	}

	/**
	 * Whether the sitemap feature is on.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		return (bool) SEO_Boost_Settings::get( 'sitemap_enabled', 1 );
	}

	/**
	 * Register the rewrite rules.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . $this->query_var . '=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$', 'index.php?' . $this->query_var . '=$matches[1]&seo_boost_page=$matches[2]', 'top' );
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = $this->query_var;
		$vars[] = 'seo_boost_page';
		return $vars;
	}

	/**
	 * Turn off the core sitemap when ours is active.
	 *
	 * @param bool $enabled Current state.
	 * @return bool
	 */
	public function maybe_disable_core_sitemap( $enabled ) {
		return $this->is_enabled() ? false : $enabled;
	}

	/**
	 * Add the sitemap reference to robots.txt.
	 *
	 * @param string $output Existing robots.txt output.
	 * @return string
	 */
	public function add_to_robots( $output ) {
		if ( $this->is_enabled() ) {
			$output .= "\nSitemap: " . esc_url( home_url( '/sitemap.xml' ) ) . "\n";
		}
		return $output;
	}

	/**
	 * Route and render the requested sitemap.
	 */
	public function render() {
		$type = get_query_var( $this->query_var );
		if ( empty( $type ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			status_header( 404 );
			exit;
		}

		$page = absint( get_query_var( 'seo_boost_page' ) );
		$page = $page > 0 ? $page : 1;

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );

		if ( 'index' === $type ) {
			echo $this->render_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo $this->render_sub_sitemap( $type, $page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		exit;
	}

	/**
	 * Build the list of sub-sitemaps for the index.
	 *
	 * @return array List of arrays with 'loc' and 'lastmod'.
	 */
	public function get_sitemap_entries() {
		$entries    = array();
		$per_page   = max( 1, (int) SEO_Boost_Settings::get( 'sitemap_per_page', 1000 ) );
		$post_types = (array) SEO_Boost_Settings::get( 'sitemap_post_types', array( 'post', 'page' ) );
		$taxonomies = (array) SEO_Boost_Settings::get( 'sitemap_taxonomies', array( 'category', 'post_tag' ) );

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$counts = wp_count_posts( $post_type );
			$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;
			if ( $total < 1 ) {
				continue;
			}
			$pages = (int) ceil( $total / $per_page );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$entries[] = array(
					'loc'     => home_url( "/sitemap-{$post_type}-{$i}.xml" ),
					'lastmod' => $this->get_post_type_lastmod( $post_type ),
				);
			}
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$total = (int) wp_count_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			if ( $total < 1 ) {
				continue;
			}
			$pages = (int) ceil( $total / $per_page );
			for ( $i = 1; $i <= $pages; $i++ ) {
				$entries[] = array(
					'loc'     => home_url( "/sitemap-tax-{$taxonomy}-{$i}.xml" ),
					'lastmod' => gmdate( 'c' ),
				);
			}
		}

		return $entries;
	}

	/**
	 * Render the sitemap index XML.
	 *
	 * @return string
	 */
	private function render_index() {
		$entries = $this->get_sitemap_entries();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $entries as $entry ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";
			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= "\t</sitemap>\n";
		}
		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Render a single sub-sitemap.
	 *
	 * @param string $type Either a post type, or "tax-{taxonomy}".
	 * @param int    $page Page number.
	 * @return string
	 */
	private function render_sub_sitemap( $type, $page ) {
		$per_page       = max( 1, (int) SEO_Boost_Settings::get( 'sitemap_per_page', 1000 ) );
		$include_images = (bool) SEO_Boost_Settings::get( 'sitemap_include_images', 1 );

		$urls = array();

		if ( 0 === strpos( $type, 'tax-' ) ) {
			$taxonomy = substr( $type, 4 );
			$urls     = $this->get_term_urls( $taxonomy, $page, $per_page );
		} else {
			$urls = $this->get_post_urls( $type, $page, $per_page, $include_images );
		}

		$ns  = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		$ns .= $include_images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset ' . $ns . '>' . "\n";

		foreach ( $urls as $url ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( $url['loc'] ) . "</loc>\n";
			if ( ! empty( $url['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . esc_html( $url['lastmod'] ) . "</lastmod>\n";
			}
			if ( ! empty( $url['changefreq'] ) ) {
				$xml .= "\t\t<changefreq>" . esc_html( $url['changefreq'] ) . "</changefreq>\n";
			}
			if ( isset( $url['priority'] ) ) {
				$xml .= "\t\t<priority>" . esc_html( number_format( (float) $url['priority'], 1 ) ) . "</priority>\n";
			}
			if ( ! empty( $url['images'] ) ) {
				foreach ( $url['images'] as $image ) {
					$xml .= "\t\t<image:image>\n";
					$xml .= "\t\t\t<image:loc>" . esc_url( $image ) . "</image:loc>\n";
					$xml .= "\t\t</image:image>\n";
				}
			}
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Fetch post URLs for a given post type and page.
	 *
	 * @param string $post_type      Post type.
	 * @param int    $page           Page number.
	 * @param int    $per_page       Items per page.
	 * @param bool   $include_images Whether to attach images.
	 * @return array
	 */
	private function get_post_urls( $post_type, $page, $per_page, $include_images ) {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		$urls = array();
		foreach ( $query->posts as $post ) {
			$entry = array(
				'loc'        => get_permalink( $post ),
				'lastmod'    => get_post_modified_time( 'c', true, $post ),
				'changefreq' => 'weekly',
				'priority'   => ( 'page' === $post_type ) ? 0.6 : 0.8,
			);

			if ( $include_images ) {
				$entry['images'] = $this->get_post_images( $post );
			}

			$urls[] = $entry;
		}

		wp_reset_postdata();
		return $urls;
	}

	/**
	 * Collect image URLs (featured + embedded) for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function get_post_images( $post ) {
		$images = array();

		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_url( $thumb_id, 'full' );
			if ( $src ) {
				$images[] = $src;
			}
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $post->post_content, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$images[] = $src;
			}
		}

		return array_values( array_unique( $images ) );
	}

	/**
	 * Fetch term URLs for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $page     Page number.
	 * @param int    $per_page Items per page.
	 * @return array
	 */
	private function get_term_urls( $taxonomy, $page, $per_page ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => $per_page,
				'offset'     => ( $page - 1 ) * $per_page,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$urls = array();
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$urls[] = array(
				'loc'        => $link,
				'changefreq' => 'weekly',
				'priority'   => 0.5,
			);
		}

		return $urls;
	}

	/**
	 * Get the most-recent modified date for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string ISO-8601 date.
	 */
	private function get_post_type_lastmod( $post_type ) {
		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $query->posts ) ) {
			return get_post_modified_time( 'c', true, $query->posts[0] );
		}

		return gmdate( 'c' );
	}

	/**
	 * Public helper: the sitemap index URL.
	 *
	 * @return string
	 */
	public function get_index_url() {
		return home_url( '/sitemap.xml' );
	}
}
