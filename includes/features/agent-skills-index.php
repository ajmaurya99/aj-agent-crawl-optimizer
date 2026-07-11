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
		$md = build_skill_md( $name, $def );
		// `type` and `digest` per the Agent Skills Discovery RFC v0.2.0: the
		// index `type` is the artifact type (`skill-md` | `archive`), not the
		// skill's semantic category, and the hash field is named `digest` with
		// a `sha256:` prefix.
		$skills[] = array(
			'name'        => $name,
			'type'        => 'skill-md',
			'description' => $def['description'],
			'url'         => home_url( '/.well-known/agent-skills/' . $name . '/SKILL.md' ),
			'digest'      => 'sha256:' . hash( 'sha256', $md ),
		);
	}

	$skills_index = array(
		'$schema' => 'https://schemas.agentskills.io/discovery/0.2.0/schema.json',
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
	// Skill values come from a filter (`ajaco_skill_definitions`). This body
	// is served as text/markdown, so HTML entity escaping would corrupt it
	// (e.g. `Tom&#039;s`); instead sanitize for the markdown/YAML context by
	// stripping tags and newlines. esc_url_raw() keeps the URL un-entity-encoded.
	$name        = markdown_safe_text( $name );
	$type        = markdown_safe_text( $def['type'] );
	$description = markdown_safe_text( $def['description'] );
	$endpoint    = esc_url_raw( $def['endpoint'] );

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
		. 'The endpoint follows the WordPress REST API conventions. Fetch the full schema at `/openapi.json`.';
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
	// Plain-text Markdown served as text/markdown (never rendered as HTML).
	// Values are sanitized for the markdown context in build_skill_md().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo build_skill_md( $m[1], $skills[ $m[1] ] );
	exit;
}
