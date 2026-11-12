<?php
/**
 * Feature: Sitemap lastmod — freshness dates in the core sitemap.
 *
 * WordPress core sitemaps (/wp-sitemap.xml, since 5.5) list URLs without a
 * <lastmod> element, so crawlers and agents cannot tell what changed. This
 * adds one per post entry from the stored modification date on
 * core versions that lack it (newer core sets lastmod itself and is left
 * untouched). SEO plugins that replace the core sitemap already emit lastmod
 * and never pass through these filters.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_sitemaps_posts_entry', __NAMESPACE__ . '\\sitemap_post_lastmod', 10, 2 );
add_filter( 'wp_sitemaps_posts_show_on_front_entry', __NAMESPACE__ . '\\sitemap_front_lastmod' );

/**
 * Add lastmod to a post entry.
 *
 * @param array    $entry Sitemap entry (`loc`).
 * @param \WP_Post $post  Post.
 * @return array
 */
function sitemap_post_lastmod( array $entry, $post ): array {
	// Newer core versions set lastmod themselves — never override that value.
	if ( ! is_feature_enabled( 'sitemap_lastmod' ) || isset( $entry['lastmod'] ) || ! $post instanceof \WP_Post ) {
		return $entry;
	}
	if ( ! empty( $post->post_modified_gmt ) && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
		$entry['lastmod'] = gmdate( 'c', strtotime( $post->post_modified_gmt . ' UTC' ) );
	}
	return $entry;
}

/**
 * Add lastmod to the "posts page as front page" entry: the newest post's
 * modification date.
 *
 * @param array $entry Sitemap entry.
 * @return array
 */
function sitemap_front_lastmod( array $entry ): array {
	if ( ! is_feature_enabled( 'sitemap_lastmod' ) || isset( $entry['lastmod'] ) ) {
		return $entry;
	}
	$latest = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);
	if ( ! empty( $latest[0]->post_modified_gmt ) ) {
		$entry['lastmod'] = gmdate( 'c', strtotime( $latest[0]->post_modified_gmt . ' UTC' ) );
	}
	return $entry;
}
