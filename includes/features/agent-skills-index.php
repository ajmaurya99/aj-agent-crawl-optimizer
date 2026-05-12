<?php
/**
 * Feature: Agent Skills Index (RFC v0.2.0).
 *
 * Serves /.well-known/agent-skills/index.json listing capabilities the site
 * exposes, plus deterministic SKILL.md artifacts at
 * /.well-known/agent-skills/{name}/SKILL.md whose sha256 digests the index
 * advertises (so agents can fetch and verify).
 *
 * @see https://github.com/cloudflare/agent-skills-discovery-rfc
 * @see https://agentskills.io/
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_agent_skills_index_request' );
add_action( 'init', __NAMESPACE__ . '\\handle_agent_skill_md_request' );

/**
 * Serve /.well-known/agent-skills/index.json.
 *
 * @return void
 */
function handle_agent_skills_index_request(): void {
	if ( ! request_path_is( '/.well-known/agent-skills/index.json' ) ) {
		return;
	}

	if ( ! is_feature_enabled( 'agent_skills_index' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );

	$skills = array();
	foreach ( get_skill_definitions() as $name => $def ) {
		$md       = build_skill_md( $name, $def );
		$skills[] = array(
			'name'        => $name,
			'type'        => $def['type'],
			'description' => $def['description'],
			'url'         => home_url( '/.well-known/agent-skills/' . $name . '/SKILL.md' ),
			'sha256'      => hash( 'sha256', $md ),
		);
	}

	$skills_index = array(
		'$schema' => 'https://agentskills.io/schema/v0.2.0',
		'skills'  => $skills,
	);

	echo wp_json_encode( $skills_index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}

/**
 * Registry of skills exposed via the agent-skills discovery index.
 *
 * Single source of truth shared by the index handler and the SKILL.md handler
 * so the index's sha256 digest always matches the artifact bytes served at `url`.
 *
 * @return array<string, array{type: string, description: string, endpoint: string}>
 */
function get_skill_definitions(): array {
	$api_url = rest_url( '/' );
	$skills  = array(
		'content-query' => array(
			'type'        => 'information-retrieval',
			'description' => 'Query and search site content including posts, pages, and media',
			'endpoint'    => $api_url . 'wp/v2/search',
		),
		'posts-read'    => array(
			'type'        => 'information-retrieval',
			'description' => 'Read blog posts and their metadata',
			'endpoint'    => $api_url . 'wp/v2/posts',
		),
		'pages-read'    => array(
			'type'        => 'information-retrieval',
			'description' => 'Read site pages and their metadata',
			'endpoint'    => $api_url . 'wp/v2/pages',
		),
		'media-library' => array(
			'type'        => 'information-retrieval',
			'description' => 'Access site media library and attachments',
			'endpoint'    => $api_url . 'wp/v2/media',
		),
		'categories'    => array(
			'type'        => 'information-retrieval',
			'description' => 'Browse post categories and taxonomy',
			'endpoint'    => $api_url . 'wp/v2/categories',
		),
		'tags'          => array(
			'type'        => 'information-retrieval',
			'description' => 'Browse post tags and taxonomy',
			'endpoint'    => $api_url . 'wp/v2/tags',
		),
	);

	/**
	 * Filter the registered agent skills.
	 *
	 * Each entry is keyed by the skill slug (used in the SKILL.md URL) and
	 * must contain `type`, `description`, and `endpoint` keys. WooCommerce,
	 * Easy Digital Downloads, etc. can register their own skills here.
	 *
	 * @param array<string, array{type: string, description: string, endpoint: string}> $skills
	 */
	return apply_filters( 'ajaco_skill_definitions', $skills );
}

/**
 * Render the deterministic SKILL.md body for a given skill definition.
 *
 * @param string $name Skill slug.
 * @param array  $def  Skill definition from get_skill_definitions().
 * @return string
 */
function build_skill_md( string $name, array $def ): string {
	// Skill values come from a filter (`ajaco_skill_definitions`), so
	// escape them when interpolating into the markdown template.
	$name        = esc_html( $name );
	$type        = esc_html( $def['type'] );
	$description = esc_html( $def['description'] );
	$endpoint    = esc_url( $def['endpoint'] );

	return "---\n"
		. "name: {$name}\n"
		. "type: {$type}\n"
		. "description: {$description}\n"
		. "---\n"
		. "\n"
		. "# {$name}\n"
		. "\n"
		. "{$description}\n"
		. "\n"
		. "## How to use\n"
		. "\n"
		. "Issue a GET request to:\n"
		. "\n"
		. "```\n"
		. "{$endpoint}\n"
		. "```\n"
		. "\n"
		. 'The endpoint follows the WordPress REST API conventions. Fetch the full schema at `/?format=openapi`.';
}

/**
 * Serve the SKILL.md artifact for a registered skill at
 * /.well-known/agent-skills/{name}/SKILL.md (root or any multisite subsite path).
 *
 * @return void
 */
function handle_agent_skill_md_request(): void {
	if ( ! is_feature_enabled( 'agent_skills_index' ) ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return;
	}

	if ( ! preg_match( '#(?:^|/)\.well-known/agent-skills/([a-z0-9_-]+)/SKILL\.md$#', $path, $m ) ) {
		return;
	}

	$skills = get_skill_definitions();
	if ( ! isset( $skills[ $m[1] ] ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );
	// Plain-text Markdown served as text/markdown. Body is built from a
	// hardcoded template plus values pre-escaped via esc_html/esc_url in
	// build_skill_md().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo build_skill_md( $m[1], $skills[ $m[1] ] );
	exit;
}
