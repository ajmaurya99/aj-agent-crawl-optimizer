<?php
/**
 * Feature: llms.txt curation config + per-post controls.
 *
 * The llms.txt generator is curated, not random: this file owns the config
 * schema (intro text, per-post-type sections, a custom markdown block) and
 * the two post-meta fields that let an author steer an individual entry
 * (exclude it, or override its one-line summary for LLMs).
 *
 * Defaults reproduce the pre-2.0 output exactly, so upgrading changes nothing
 * until the owner edits something.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LLMS_CONFIG_OPTION = 'ajaco_llms_config';
const LLMS_META_EXCLUDE  = '_ajaco_llms_exclude';
const LLMS_META_SUMMARY  = '_ajaco_llms_summary';

add_action( 'init', __NAMESPACE__ . '\\register_llms_post_meta' );

// Editing an entry's inclusion or summary changes the file — bust the cache.
add_action( 'updated_post_meta', __NAMESPACE__ . '\\maybe_flush_llms_on_meta_change', 10, 3 );
add_action( 'added_post_meta', __NAMESPACE__ . '\\maybe_flush_llms_on_meta_change', 10, 3 );
add_action( 'deleted_post_meta', __NAMESPACE__ . '\\maybe_flush_llms_on_meta_change', 10, 3 );

/**
 * Post types an owner can curate into llms.txt: public, with a UI, excluding
 * attachments (media items are not useful reading for an agent).
 *
 * @return array<string, string> post type slug => display label.
 */
function llms_curatable_post_types(): array {
	$types = array();

	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
		if ( 'attachment' === $type->name ) {
			continue;
		}
		if ( empty( $type->show_ui ) && ! in_array( $type->name, array( 'post', 'page' ), true ) ) {
			continue;
		}
		$types[ $type->name ] = $type->labels->name;
	}

	/**
	 * Filter the post types offered on the llms.txt curation screen.
	 *
	 * @param array<string, string> $types Post type slug => label.
	 */
	return (array) apply_filters( 'ajaco_llms_post_types', $types );
}

/**
 * Default config — reproduces the historical output: top-level pages by menu
 * order, then the 10 most recent posts with dates.
 *
 * @return array
 */
function llms_default_config(): array {
	return array(
		'intro'           => '',
		'custom_md'       => '',
		'custom_position' => 'bottom',
		'sections'        => array(
			'page' => array(
				'enabled'     => true,
				'heading'     => __( 'Pages', 'aj-agent-crawl-optimizer' ),
				'count'       => 20,
				'order'       => 'menu',
				'top_level'   => true,
				'show_date'   => false,
			),
			'post' => array(
				'enabled'     => true,
				'heading'     => __( 'Recent Posts', 'aj-agent-crawl-optimizer' ),
				'count'       => 10,
				'order'       => 'recent',
				'top_level'   => false,
				'show_date'   => true,
			),
		),
	);
}

/**
 * The effective config: stored option merged over defaults, fully normalized.
 *
 * @return array
 */
function llms_config(): array {
	$stored = get_option( LLMS_CONFIG_OPTION, array() );
	return sanitize_llms_config( is_array( $stored ) ? $stored : array() );
}

/**
 * Normalize/sanitize a config array (also the settings sanitize_callback).
 *
 * Unknown post types are dropped; counts are clamped; text is kept as plain
 * markdown-safe text (this ends up in a text/markdown body, so it must NOT be
 * HTML-entity escaped).
 *
 * @param mixed $value Raw config.
 * @return array
 */
function sanitize_llms_config( $value ): array {
	$defaults = llms_default_config();
	if ( ! is_array( $value ) ) {
		return $defaults;
	}

	$clean = array();

	$clean['intro']     = isset( $value['intro'] ) ? sanitize_llms_text( (string) $value['intro'], 2000 ) : $defaults['intro'];
	$clean['custom_md'] = isset( $value['custom_md'] ) ? sanitize_llms_text( (string) $value['custom_md'], 20000, true ) : $defaults['custom_md'];

	$position                 = isset( $value['custom_position'] ) ? (string) $value['custom_position'] : 'bottom';
	$clean['custom_position'] = ( 'top' === $position ) ? 'top' : 'bottom';

	$known             = llms_curatable_post_types();
	$clean['sections'] = array();

	$sections = isset( $value['sections'] ) && is_array( $value['sections'] ) ? $value['sections'] : array();
	foreach ( $sections as $type => $section ) {
		if ( ! isset( $known[ $type ] ) || ! is_array( $section ) ) {
			continue;
		}

		$section_defaults = isset( $defaults['sections'][ $type ] )
			? $defaults['sections'][ $type ]
			: array(
				'enabled'   => false,
				'heading'   => $known[ $type ],
				'count'     => 10,
				'order'     => 'recent',
				'top_level' => false,
				'show_date' => false,
			);

		$order = isset( $section['order'] ) ? (string) $section['order'] : $section_defaults['order'];
		if ( ! in_array( $order, array( 'recent', 'menu', 'title' ), true ) ) {
			$order = 'recent';
		}

		$heading = isset( $section['heading'] ) ? sanitize_llms_text( (string) $section['heading'], 120 ) : $section_defaults['heading'];
		if ( '' === $heading ) {
			$heading = $known[ $type ];
		}

		$count = isset( $section['count'] ) ? (int) $section['count'] : (int) $section_defaults['count'];
		$count = max( 1, min( 200, $count ) );

		$clean['sections'][ $type ] = array(
			'enabled'   => ! empty( $section['enabled'] ),
			'heading'   => $heading,
			'count'     => $count,
			'order'     => $order,
			'top_level' => ! empty( $section['top_level'] ),
			'show_date' => ! empty( $section['show_date'] ),
		);
	}

	// A config with no sections at all is almost certainly a mistake (or a
	// half-built POST) — fall back to the defaults rather than serving a
	// contentless file.
	if ( empty( $clean['sections'] ) ) {
		$clean['sections'] = $defaults['sections'];
	}

	return $clean;
}

/**
 * Sanitize owner-authored text bound for a text/markdown body.
 *
 * Strips tags and control characters but does NOT HTML-escape (agents would
 * receive `&#039;`). Single-line fields also collapse newlines.
 *
 * @param string $text       Raw text.
 * @param int    $max_length Hard cap.
 * @param bool   $multiline  Keep newlines (markdown blocks) or collapse them.
 * @return string
 */
function sanitize_llms_text( string $text, int $max_length, bool $multiline = false ): string {
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	if ( $multiline ) {
		// Normalize newlines, drop other control characters.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[^\P{C}\n]+/u', '', $text );
	} else {
		$text = preg_replace( '/\s+/u', ' ', $text );
	}

	$text = trim( (string) $text );

	if ( mb_strlen( $text ) > $max_length ) {
		$text = mb_substr( $text, 0, $max_length );
	}

	return $text;
}

/**
 * Register the per-post curation meta (REST-exposed so the block editor
 * sidebar can read/write it).
 *
 * @return void
 */
function register_llms_post_meta(): void {
	foreach ( array_keys( llms_curatable_post_types() ) as $post_type ) {
		register_post_meta(
			$post_type,
			LLMS_META_EXCLUDE,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		register_post_meta(
			$post_type,
			LLMS_META_SUMMARY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize_llms_summary_meta',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}

/**
 * Sanitize the per-post LLM summary override.
 *
 * @param mixed $value Raw meta value.
 * @return string
 */
function sanitize_llms_summary_meta( $value ): string {
	return sanitize_llms_text( (string) $value, 300 );
}

/**
 * Flush the llms.txt caches when a curation meta field changes.
 *
 * @param int    $meta_id  Meta row id (unused).
 * @param int    $post_id  Post id (unused).
 * @param string $meta_key Meta key.
 * @return void
 */
function maybe_flush_llms_on_meta_change( $meta_id, $post_id, $meta_key ): void {
	unset( $meta_id, $post_id );
	if ( LLMS_META_EXCLUDE === $meta_key || LLMS_META_SUMMARY === $meta_key ) {
		flush_llms_txt_cache();
	}
}

/**
 * Whether a post is excluded from the agent indexes.
 *
 * @param \WP_Post $post Post object.
 * @return bool
 */
function is_llms_excluded( \WP_Post $post ): bool {
	$excluded = (bool) get_post_meta( $post->ID, LLMS_META_EXCLUDE, true );

	/**
	 * Filter whether a post is excluded from llms.txt / llms-full.txt.
	 *
	 * @param bool     $excluded Current exclusion state.
	 * @param \WP_Post $post     The post.
	 */
	return (bool) apply_filters( 'ajaco_llms_exclude_post', $excluded, $post );
}

/**
 * The one-line summary an agent sees for a post: the author's override when
 * set, otherwise the cleaned excerpt.
 *
 * @param \WP_Post $post Post object.
 * @return string
 */
function llms_post_summary( \WP_Post $post ): string {
	$override = (string) get_post_meta( $post->ID, LLMS_META_SUMMARY, true );
	if ( '' !== trim( $override ) ) {
		return sanitize_llms_text( $override, 300 );
	}

	$excerpt = markdown_safe_text( get_the_excerpt( $post ) );
	$excerpt = preg_replace( '/\s*\[(\.\.\.|…)\]\s*$/u', '', (string) $excerpt );

	return trim( (string) $excerpt );
}

/**
 * Query the posts for one configured section, honoring per-post exclusions.
 *
 * @param string $post_type Post type slug.
 * @param array  $section   Normalized section config.
 * @return \WP_Post[]
 */
function llms_section_posts( string $post_type, array $section ): array {
	$args = array(
		'post_type'        => $post_type,
		'post_status'      => 'publish',
		// Over-fetch a little so exclusions don't shrink the section below the
		// requested count.
		'numberposts'      => min( 300, $section['count'] * 2 ),
		'suppress_filters' => false,
		'has_password'     => false,
		'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => LLMS_META_EXCLUDE,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => LLMS_META_EXCLUDE,
				'value'   => '1',
				'compare' => '!=',
			),
		),
	);

	if ( ! empty( $section['top_level'] ) && is_post_type_hierarchical( $post_type ) ) {
		$args['post_parent'] = 0;
	}

	if ( 'menu' === $section['order'] ) {
		$args['orderby'] = 'menu_order title';
		$args['order']   = 'ASC';
	} elseif ( 'title' === $section['order'] ) {
		$args['orderby'] = 'title';
		$args['order']   = 'ASC';
	} else {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}

	$posts = get_posts( $args );

	// The meta_query covers stored exclusions; the filter hook can still veto.
	$posts = array_values(
		array_filter(
			$posts,
			function ( $post ) {
				return ! is_llms_excluded( $post ) && ! post_password_required( $post );
			}
		)
	);

	return array_slice( $posts, 0, (int) $section['count'] );
}
