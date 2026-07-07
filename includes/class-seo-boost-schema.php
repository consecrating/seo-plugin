<?php
/**
 * Structured Data (Schema.org JSON-LD) + Local SEO.
 *
 * Outputs a single connected @graph in the site <head> so search engines can
 * build rich results. For a local agency this is where the biggest ranking &
 * click-through wins come from:
 *
 *   - Organization / LocalBusiness / ProfessionalService (NAP, geo, hours,
 *     areaServed, social profiles) -> Knowledge Panel & local pack signals.
 *   - WebSite + SearchAction -> sitelinks search box.
 *   - Article / WebPage with datePublished + dateModified -> freshness signal.
 *   - BreadcrumbList -> breadcrumb rich result.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_Schema
 */
class SEO_Boost_Schema {

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_schema' ), 20 );
	}

	/**
	 * Whether schema output is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) SEO_Boost_Settings::get( 'schema_enabled', 1 );
	}

	/**
	 * Print the JSON-LD graph.
	 */
	public function print_schema() {
		if ( ! $this->is_enabled() || is_admin() ) {
			return;
		}

		$graph = $this->build_graph();
		if ( empty( $graph ) ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n<!-- SEO Boost structured data -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Assemble the full node graph for the current request.
	 *
	 * @return array
	 */
	public function build_graph() {
		$graph = array();

		$org     = $this->build_organization();
		$website = $this->build_website( $org );

		if ( $org ) {
			$graph[] = $org;
		}
		if ( $website ) {
			$graph[] = $website;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$content_node = $this->build_content_node( $post, $org, $website );
				if ( $content_node ) {
					$graph[] = $content_node;
				}

				if ( (bool) SEO_Boost_Settings::get( 'schema_breadcrumbs', 1 ) ) {
					$crumbs = $this->build_breadcrumbs( $post );
					if ( $crumbs ) {
						$graph[] = $crumbs;
					}
				}
			}
		}

		return $graph;
	}

	/**
	 * Build the Organization / LocalBusiness node.
	 *
	 * @return array|null
	 */
	private function build_organization() {
		$type = SEO_Boost_Settings::get( 'schema_type', 'Organization' );
		$type = in_array( $type, array( 'Organization', 'LocalBusiness', 'ProfessionalService' ), true ) ? $type : 'Organization';

		$name = SEO_Boost_Settings::get( 'org_name', '' );
		$name = '' !== trim( (string) $name ) ? $name : get_bloginfo( 'name' );

		$node = array(
			'@type' => $type,
			'@id'   => home_url( '/#organization' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);

		$logo = SEO_Boost_Settings::get( 'org_logo', '' );
		if ( $logo ) {
			$node['logo']  = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
			$node['image'] = $logo;
		}

		$phone = SEO_Boost_Settings::get( 'org_phone', '' );
		if ( $phone ) {
			$node['telephone'] = $phone;
		}

		$email = SEO_Boost_Settings::get( 'org_email', '' );
		if ( $email ) {
			$node['email'] = $email;
		}

		// Postal address (Local SEO core).
		$address = array_filter(
			array(
				'streetAddress'   => SEO_Boost_Settings::get( 'org_street', '' ),
				'addressLocality' => SEO_Boost_Settings::get( 'org_locality', '' ),
				'addressRegion'   => SEO_Boost_Settings::get( 'org_region', '' ),
				'postalCode'      => SEO_Boost_Settings::get( 'org_postal', '' ),
				'addressCountry'  => SEO_Boost_Settings::get( 'org_country', 'IN' ),
			),
			static function ( $v ) {
				return '' !== trim( (string) $v );
			}
		);
		if ( ! empty( $address ) ) {
			$address['@type']  = 'PostalAddress';
			$node['address']   = $address;
		}

		// Geo coordinates.
		$lat = SEO_Boost_Settings::get( 'org_lat', '' );
		$lng = SEO_Boost_Settings::get( 'org_lng', '' );
		if ( '' !== trim( (string) $lat ) && '' !== trim( (string) $lng ) ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}

		// Local-business-only enrichments.
		if ( 'Organization' !== $type ) {
			$area = SEO_Boost_Settings::get( 'org_area_served', '' );
			if ( $area ) {
				$node['areaServed'] = $area;
			}
			$price = SEO_Boost_Settings::get( 'org_price_range', '' );
			if ( $price ) {
				$node['priceRange'] = $price;
			}
			$hours = SEO_Boost_Settings::get( 'org_hours', '' );
			if ( $hours ) {
				$node['openingHours'] = $hours;
			}
		}

		// Social profiles -> sameAs.
		$social = (array) SEO_Boost_Settings::get( 'social_profiles', array() );
		$social = array_values( array_filter( array_map( 'trim', $social ) ) );
		if ( ! empty( $social ) ) {
			$node['sameAs'] = $social;
		}

		return $node;
	}

	/**
	 * Build the WebSite node (with optional SearchAction).
	 *
	 * @param array|null $org Organization node for publisher linkage.
	 * @return array
	 */
	private function build_website( $org ) {
		$node = array(
			'@type' => 'WebSite',
			'@id'   => home_url( '/#website' ),
			'url'   => home_url( '/' ),
			'name'  => get_bloginfo( 'name' ),
		);

		if ( $org ) {
			$node['publisher'] = array( '@id' => $org['@id'] );
		}

		if ( (bool) SEO_Boost_Settings::get( 'schema_searchbox', 1 ) ) {
			$node['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			);
		}

		return $node;
	}

	/**
	 * Build the Article (posts) or WebPage node for a singular view.
	 *
	 * @param WP_Post    $post    Post object.
	 * @param array|null $org     Organization node.
	 * @param array      $website WebSite node.
	 * @return array|null
	 */
	private function build_content_node( $post, $org, $website ) {
		$is_article = ( 'post' === $post->post_type ) && (bool) SEO_Boost_Settings::get( 'schema_article', 1 );
		$permalink  = get_permalink( $post );

		$node = array(
			'@type'         => $is_article ? 'Article' : 'WebPage',
			'@id'           => $permalink . '#' . ( $is_article ? 'article' : 'webpage' ),
			'url'           => $permalink,
			'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
			'name'          => wp_strip_all_tags( get_the_title( $post ) ),
			// Freshness signals: these two dates tell Google how current the content is.
			'datePublished' => get_post_time( 'c', true, $post ),
			'dateModified'  => get_post_modified_time( 'c', true, $post ),
			'inLanguage'    => get_bloginfo( 'language' ),
		);

		if ( ! empty( $website['@id'] ) ) {
			$node['isPartOf'] = array( '@id' => $website['@id'] );
		}

		// Description from excerpt.
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40, '' );
		if ( $excerpt ) {
			$node['description'] = $excerpt;
		}

		// Featured image.
		$thumb = get_the_post_thumbnail_url( $post, 'full' );
		if ( $thumb ) {
			$node['image'] = $thumb;
		}

		if ( $is_article ) {
			$author_id   = (int) $post->post_author;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			if ( $author_name ) {
				$node['author'] = array(
					'@type' => 'Person',
					'name'  => $author_name,
					'url'   => get_author_posts_url( $author_id ),
				);
			}
			if ( $org ) {
				$node['publisher'] = array( '@id' => $org['@id'] );
			}
			$node['mainEntityOfPage'] = array(
				'@type' => 'WebPage',
				'@id'   => $permalink,
			);
		}

		return $node;
	}

	/**
	 * Build a BreadcrumbList node for the current singular post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array|null
	 */
	private function build_breadcrumbs( $post ) {
		$items    = array();
		$position = 1;

		// Home.
		$items[] = $this->crumb( $position++, __( 'Home', 'seo-boost' ), home_url( '/' ) );

		// Primary category (for posts).
		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			if ( ! empty( $cats ) ) {
				$cat  = $cats[0];
				$link = get_category_link( $cat->term_id );
				if ( ! is_wp_error( $link ) ) {
					$items[] = $this->crumb( $position++, $cat->name, $link );
				}
			}
		} elseif ( $post->post_parent ) {
			// Page ancestors.
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = $this->crumb( $position++, get_the_title( $ancestor_id ), get_permalink( $ancestor_id ) );
			}
		}

		// Current.
		$items[] = $this->crumb( $position++, wp_strip_all_tags( get_the_title( $post ) ), get_permalink( $post ) );

		if ( count( $items ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => get_permalink( $post ) . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Build a single breadcrumb list item.
	 *
	 * @param int    $position Position (1-based).
	 * @param string $name     Item name.
	 * @param string $url      Item URL.
	 * @return array
	 */
	private function crumb( $position, $name, $url ) {
		return array(
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => $name,
			'item'     => $url,
		);
	}

	/**
	 * Whether the essential Local SEO fields are filled in.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$name    = trim( (string) SEO_Boost_Settings::get( 'org_name', '' ) );
		$locality = trim( (string) SEO_Boost_Settings::get( 'org_locality', '' ) );
		return ( '' !== $name || '' !== $locality );
	}
}
