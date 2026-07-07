<?php
/**
 * AI Content Kit.
 *
 * Turns any page or post into a ready-to-paste brief for an AI assistant
 * (Claude, ChatGPT, etc.) so it can write fresh content or rewrite an old page
 * to rank higher. Each brief bundles:
 *
 *   - Your business / local context (pulled from the Schema settings) so the AI
 *     knows it is writing for, e.g., a digital marketing agency in Goa.
 *   - The page's current content, headings outline, word count and freshness.
 *   - Suggested focus keywords derived from the title, categories and tags.
 *   - A task prompt tuned for the chosen goal (rewrite / fresh / expand / meta).
 *
 * Output is available as paste-ready Markdown or structured JSON, for a single
 * page or a bulk "content pack" of many pages.
 *
 * @package SEO_Boost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Boost_AI_Export
 */
class SEO_Boost_AI_Export {

	/**
	 * Plugin container.
	 *
	 * @var SEO_Boost
	 */
	private $plugin;

	/**
	 * Max posts allowed in a single bulk export.
	 */
	const BULK_LIMIT = 30;

	/**
	 * Constructor.
	 *
	 * @param SEO_Boost $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Available prompt templates (id => label).
	 *
	 * @return array
	 */
	public function templates() {
		return array(
			'rewrite' => __( 'Rewrite & optimise existing content', 'seo-boost' ),
			'fresh'   => __( 'Write brand-new content on this topic', 'seo-boost' ),
			'expand'  => __( 'Expand thin content into an in-depth guide', 'seo-boost' ),
			'meta'    => __( 'Generate SEO title, meta description & FAQs', 'seo-boost' ),
		);
	}

	/**
	 * Post types that can be exported.
	 *
	 * @return array
	 */
	private function exportable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/* ---------------------------------------------------------------------
	 * Listing
	 * ------------------------------------------------------------------- */

	/**
	 * List posts available for export, with freshness data attached.
	 *
	 * @param array $args filter/paging.
	 * @return array array( 'items' => array, 'total' => int ).
	 */
	public function get_posts( $args = array() ) {
		$defaults = array(
			'filter'   => 'all', // all|stale|aging|fresh.
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
		);
		$args      = wp_parse_args( $args, $defaults );
		$freshness = $this->plugin->freshness;

		$query = new WP_Query(
			array(
				'post_type'              => $this->exportable_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'modified',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				's'                      => $args['search'],
			)
		);

		$all = array();
		foreach ( $query->posts as $post ) {
			$days   = $freshness->days_since_modified( $post );
			$status = $freshness->classify( $days );

			if ( 'all' !== $args['filter'] && $status !== $args['filter'] ) {
				continue;
			}

			$all[] = array(
				'id'        => $post->ID,
				'title'     => wp_strip_all_tags( get_the_title( $post ) ),
				'url'       => get_permalink( $post ),
				'post_type' => $post->post_type,
				'modified'  => get_post_modified_time( 'Y-m-d', true, $post ),
				'days'      => $days,
				'status'    => $status,
				'score'     => $freshness->score_from_days( $days ),
				'words'     => $this->word_count( $post->post_content ),
			);
		}
		wp_reset_postdata();

		$total    = count( $all );
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		return array(
			'items' => array_slice( $all, $offset, $per_page ),
			'total' => $total,
		);
	}

	/* ---------------------------------------------------------------------
	 * Brief building
	 * ------------------------------------------------------------------- */

	/**
	 * Build the structured brief for a single post.
	 *
	 * @param int  $post_id     Post ID.
	 * @param bool $include_body Whether to include the full body text.
	 * @return array|null
	 */
	public function build_brief( $post_id, $include_body = true ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$freshness = $this->plugin->freshness;
		$days      = $freshness->days_since_modified( $post );
		$body      = $this->html_to_text( $post->post_content );
		$words     = str_word_count( $body );

		return array(
			'id'            => $post->ID,
			'title'         => wp_strip_all_tags( get_the_title( $post ) ),
			'url'           => get_permalink( $post ),
			'post_type'     => $post->post_type,
			'published'     => get_post_time( 'Y-m-d', false, $post ),
			'modified'      => get_post_modified_time( 'Y-m-d', false, $post ),
			'days_old'      => $days,
			'freshness'     => $freshness->classify( $days ),
			'score'         => $freshness->score_from_days( $days ),
			'word_count'    => $words,
			'reading_time'  => max( 1, (int) ceil( $words / 200 ) ),
			'excerpt'       => $this->excerpt( $post ),
			'categories'    => $this->term_names( $post, 'category' ),
			'tags'          => $this->term_names( $post, 'post_tag' ),
			'keywords'      => $this->suggest_keywords( $post ),
			'headings'      => $this->extract_headings( $post->post_content ),
			'body'          => $include_body ? $body : '',
		);
	}

	/**
	 * Business/local context block (from Schema settings) shared by all prompts.
	 *
	 * @return array
	 */
	public function business_context() {
		$name = SEO_Boost_Settings::get( 'org_name', '' );
		$name = '' !== trim( (string) $name ) ? $name : get_bloginfo( 'name' );

		$locality = SEO_Boost_Settings::get( 'org_locality', '' );
		$region   = SEO_Boost_Settings::get( 'org_region', '' );
		$location = trim( $locality . ( $locality && $region ? ', ' : '' ) . $region );

		$type_map = array(
			'Organization'        => __( 'business', 'seo-boost' ),
			'LocalBusiness'       => __( 'local business', 'seo-boost' ),
			'ProfessionalService' => __( 'professional service', 'seo-boost' ),
		);
		$type = SEO_Boost_Settings::get( 'schema_type', 'Organization' );

		return array(
			'name'        => $name,
			'type'        => isset( $type_map[ $type ] ) ? $type_map[ $type ] : 'business',
			'location'    => $location,
			'area_served' => SEO_Boost_Settings::get( 'org_area_served', '' ),
			'site'        => get_bloginfo( 'name' ),
			'language'    => get_bloginfo( 'language' ),
			'notes'       => trim( (string) SEO_Boost_Settings::get( 'ai_business_context', '' ) ),
		);
	}

	/**
	 * Build the AI task prompt for a brief + template.
	 *
	 * @param array  $brief    Brief data.
	 * @param string $template Template id.
	 * @return string
	 */
	public function build_prompt( $brief, $template ) {
		$ctx  = $this->business_context();
		$year = gmdate( 'Y' );

		$who = sprintf(
			/* translators: 1: business name, 2: business type, 3: location */
			__( 'You are an expert SEO copywriter and content strategist writing for %1$s, a %2$s%3$s.', 'seo-boost' ),
			$ctx['name'],
			$ctx['type'],
			$ctx['location'] ? ' ' . sprintf( __( 'based in %s', 'seo-boost' ), $ctx['location'] ) : ''
		);

		if ( $ctx['area_served'] ) {
			$who .= ' ' . sprintf( __( 'It serves customers across %s.', 'seo-boost' ), $ctx['area_served'] );
		}
		if ( $ctx['notes'] ) {
			$who .= ' ' . $ctx['notes'];
		}

		$kw = ! empty( $brief['keywords'] ) ? implode( ', ', $brief['keywords'] ) : $brief['title'];

		switch ( $template ) {
			case 'fresh':
				$task = sprintf(
					__( 'Write a brand-new, original, high-quality article on the topic of "%1$s". Target these keywords naturally: %2$s. Make it more comprehensive and helpful than the current top-ranking pages, and current for %3$s.', 'seo-boost' ),
					$brief['title'],
					$kw,
					$year
				);
				break;

			case 'expand':
				$task = sprintf(
					__( 'The page below is thin (%1$d words). Expand it into an in-depth, authoritative guide of 1,200-1,800 words. Keep everything accurate, add practical detail, examples and sections that answer real user questions. Target keywords: %2$s.', 'seo-boost' ),
					(int) $brief['word_count'],
					$kw
				);
				break;

			case 'meta':
				$task = sprintf(
					__( 'Based on the page below, produce: (1) an SEO title under 60 characters, (2) a compelling meta description under 155 characters, (3) 5 FAQ questions with concise answers, and (4) 3 improved H2 heading ideas. Target keywords: %s.', 'seo-boost' ),
					$kw
				);
				break;

			case 'rewrite':
			default:
				$task = sprintf(
					__( 'Rewrite and modernise the page below so it ranks higher on Google. Keep all factual claims and the core message, but improve structure with clear H2/H3 headings, tighten the writing, improve readability and E-E-A-T, add a short FAQ, and make it current for %1$s. Target these keywords naturally without stuffing: %2$s.', 'seo-boost' ),
					$year,
					$kw
				);
				break;
		}

		$rules = array(
			__( 'Match a professional, trustworthy brand voice.', 'seo-boost' ),
			$ctx['location'] ? sprintf( __( 'Where relevant, reflect local intent for %s.', 'seo-boost' ), $ctx['location'] ) : '',
			__( 'Use natural language; do not keyword-stuff.', 'seo-boost' ),
			__( 'Output in clean Markdown. Start with a suggested SEO title (<=60 chars) and meta description (<=155 chars), then the content.', 'seo-boost' ),
			sprintf( __( 'Write in %s.', 'seo-boost' ), $ctx['language'] ),
		);
		$rules = array_values( array_filter( $rules ) );

		$out  = $who . "\n\n";
		$out .= '## Task' . "\n" . $task . "\n\n";
		$out .= '## Requirements' . "\n";
		foreach ( $rules as $r ) {
			$out .= '- ' . $r . "\n";
		}
		return trim( $out );
	}

	/* ---------------------------------------------------------------------
	 * Output formats
	 * ------------------------------------------------------------------- */

	/**
	 * Render a single brief as paste-ready Markdown (context + data + prompt).
	 *
	 * @param array  $brief    Brief.
	 * @param string $template Template id.
	 * @return string
	 */
	public function to_markdown( $brief, $template ) {
		$ctx = $this->business_context();
		$md  = array();

		$md[] = '# SEO Content Brief: ' . $brief['title'];
		$md[] = '';
		$md[] = '## Business context';
		$md[] = '- Business: ' . $ctx['name'] . ' (' . $ctx['type'] . ')';
		if ( $ctx['location'] ) {
			$md[] = '- Location: ' . $ctx['location'];
		}
		if ( $ctx['area_served'] ) {
			$md[] = '- Area served: ' . $ctx['area_served'];
		}
		if ( $ctx['notes'] ) {
			$md[] = '- Notes: ' . $ctx['notes'];
		}
		$md[] = '';
		$md[] = '## Page details';
		$md[] = '- URL: ' . $brief['url'];
		$md[] = '- Type: ' . $brief['post_type'];
		$md[] = '- Published: ' . $brief['published'];
		$md[] = '- Last updated: ' . $brief['modified'] . ' (' . $brief['freshness'] . ', ' . $brief['days_old'] . ' days ago, freshness ' . $brief['score'] . '%)';
		$md[] = '- Word count: ' . $brief['word_count'] . ' (~' . $brief['reading_time'] . ' min read)';
		if ( $brief['categories'] ) {
			$md[] = '- Categories: ' . implode( ', ', $brief['categories'] );
		}
		if ( $brief['tags'] ) {
			$md[] = '- Tags: ' . implode( ', ', $brief['tags'] );
		}
		if ( $brief['keywords'] ) {
			$md[] = '- Suggested focus keywords: ' . implode( ', ', $brief['keywords'] );
		}

		if ( ! empty( $brief['headings'] ) ) {
			$md[] = '';
			$md[] = '## Current heading outline';
			foreach ( $brief['headings'] as $hd ) {
				$md[] = str_repeat( '  ', max( 0, (int) $hd['level'] - 1 ) ) . '- H' . $hd['level'] . ': ' . $hd['text'];
			}
		}

		if ( 'fresh' !== $template && '' !== trim( (string) $brief['body'] ) ) {
			$md[] = '';
			$md[] = '## Current content';
			$md[] = $brief['body'];
		}

		$md[] = '';
		$md[] = '---';
		$md[] = '';
		$md[] = '## Instructions for the AI';
		$md[] = $this->build_prompt( $brief, $template );

		return implode( "\n", $md );
	}

	/**
	 * Build a single-post export in the requested format.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $template Template id.
	 * @param string $format   markdown|json.
	 * @return array|null array( 'filename' => string, 'mime' => string, 'content' => string, 'brief' => array ).
	 */
	public function export_single( $post_id, $template, $format = 'markdown' ) {
		$brief = $this->build_brief( $post_id, ( 'fresh' !== $template ) );
		if ( ! $brief ) {
			return null;
		}
		$template = array_key_exists( $template, $this->templates() ) ? $template : 'rewrite';
		$slug     = sanitize_title( $brief['title'] );
		$slug     = $slug ? $slug : 'content';

		if ( 'json' === $format ) {
			$payload = array(
				'business_context' => $this->business_context(),
				'template'         => $template,
				'prompt'           => $this->build_prompt( $brief, $template ),
				'page'             => $brief,
			);
			return array(
				'filename' => 'seo-brief-' . $slug . '.json',
				'mime'     => 'application/json',
				'content'  => wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'brief'    => $brief,
			);
		}

		return array(
			'filename' => 'seo-brief-' . $slug . '.md',
			'mime'     => 'text/markdown',
			'content'  => $this->to_markdown( $brief, $template ),
			'brief'    => $brief,
		);
	}

	/**
	 * Build a bulk "content pack" export from many posts.
	 *
	 * @param array  $post_ids Post IDs.
	 * @param string $template Template id.
	 * @param string $format   markdown|json.
	 * @return array array( 'filename' => string, 'mime' => string, 'content' => string, 'count' => int ).
	 */
	public function export_bulk( $post_ids, $template, $format = 'markdown' ) {
		$post_ids = array_slice( array_values( array_unique( array_map( 'intval', (array) $post_ids ) ) ), 0, self::BULK_LIMIT );
		$template = array_key_exists( $template, $this->templates() ) ? $template : 'rewrite';
		$date     = gmdate( 'Y-m-d' );

		if ( 'json' === $format ) {
			$pages = array();
			foreach ( $post_ids as $id ) {
				$brief = $this->build_brief( $id, ( 'fresh' !== $template ) );
				if ( $brief ) {
					$brief['prompt'] = $this->build_prompt( $brief, $template );
					$pages[]         = $brief;
				}
			}
			$payload = array(
				'business_context' => $this->business_context(),
				'template'         => $template,
				'generated'        => $date,
				'pages'            => $pages,
			);
			return array(
				'filename' => 'seo-content-pack-' . $date . '.json',
				'mime'     => 'application/json',
				'content'  => wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'count'    => count( $pages ),
			);
		}

		$parts = array();
		$count = 0;
		foreach ( $post_ids as $id ) {
			$brief = $this->build_brief( $id, ( 'fresh' !== $template ) );
			if ( ! $brief ) {
				continue;
			}
			$count++;
			$parts[] = $this->to_markdown( $brief, $template );
			$parts[] = "\n\n" . str_repeat( '=', 60 ) . "\n\n";
		}

		$header = "# SEO Content Pack\n\nGenerated: {$date}\nPages: {$count}\n\nEach section below is a self-contained brief. Paste one section at a time into your AI assistant.\n\n" . str_repeat( '=', 60 ) . "\n\n";

		return array(
			'filename' => 'seo-content-pack-' . $date . '.md',
			'mime'     => 'text/markdown',
			'content'  => $header . implode( '', $parts ),
			'count'    => $count,
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Convert post HTML into readable plain text preserving line breaks.
	 *
	 * @param string $html Content HTML.
	 * @return string
	 */
	private function html_to_text( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return '';
		}
		$html = strip_shortcodes( $html );
		$html = preg_replace( '#<(br|/p|/div|/h[1-6]|/li|/tr)[^>]*>#i', "\n", $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Word count of raw content.
	 *
	 * @param string $html Content HTML.
	 * @return int
	 */
	private function word_count( $html ) {
		return str_word_count( $this->html_to_text( $html ) );
	}

	/**
	 * Extract a description-style excerpt.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function excerpt( $post ) {
		if ( has_excerpt( $post ) ) {
			return wp_strip_all_tags( get_the_excerpt( $post ) );
		}
		return wp_trim_words( $this->html_to_text( $post->post_content ), 40, '…' );
	}

	/**
	 * Extract H1-H6 headings as an outline.
	 *
	 * @param string $html Content HTML.
	 * @return array List of array( 'level' => int, 'text' => string ).
	 */
	private function extract_headings( $html ) {
		$out = array();
		if ( preg_match_all( '#<h([1-6])[^>]*>(.*?)</h\1>#is', (string) $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$text = trim( wp_strip_all_tags( $match[2] ) );
				if ( '' !== $text ) {
					$out[] = array(
						'level' => (int) $match[1],
						'text'  => $text,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Term names for a taxonomy.
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy.
	 * @return array
	 */
	private function term_names( $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return array_values( wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Suggest focus keywords from the title, categories and tags.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	private function suggest_keywords( $post ) {
		$stop = array(
			'the', 'and', 'for', 'you', 'your', 'with', 'that', 'this', 'from', 'how', 'what', 'why',
			'are', 'was', 'were', 'has', 'have', 'had', 'not', 'but', 'our', 'their', 'they', 'them',
			'a', 'an', 'of', 'to', 'in', 'on', 'is', 'it', 'as', 'at', 'or', 'by', 'be', 'we', 'best', 'top', 'guide',
		);

		$title = strtolower( wp_strip_all_tags( get_the_title( $post ) ) );
		$title = preg_replace( '/[^a-z0-9\s]/', ' ', $title );
		$words = array_values( array_filter( preg_split( '/\s+/', $title ), function ( $w ) use ( $stop ) {
			return strlen( $w ) > 2 && ! in_array( $w, $stop, true );
		} ) );

		$keywords = array();
		// The full title (minus stop words) makes a good primary phrase.
		if ( ! empty( $words ) ) {
			$keywords[] = implode( ' ', array_slice( $words, 0, 5 ) );
		}
		// Categories & tags are strong topical signals.
		foreach ( array_merge( $this->term_names( $post, 'category' ), $this->term_names( $post, 'post_tag' ) ) as $term ) {
			$term = strtolower( trim( $term ) );
			if ( $term && 'uncategorized' !== $term && ! in_array( $term, $keywords, true ) ) {
				$keywords[] = $term;
			}
		}

		return array_slice( array_values( array_unique( $keywords ) ), 0, 6 );
	}
}
