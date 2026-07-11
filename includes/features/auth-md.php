<?php
/**
 * Feature: auth.md — agent authentication documentation.
 *
 * Serves /auth.md as text/markdown documenting how agents authenticate to
 * this site's WP REST API via Application Passwords (WorkOS Auth.md
 * convention: an H1 containing "auth.md" plus human-readable auth docs).
 * Documents only what WordPress actually provides — no fabricated OAuth
 * endpoints or registration URLs.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_auth_md_request' );

/**
 * Serve /auth.md at the root or any multisite subsite path.
 *
 * @return void
 */
function handle_auth_md_request(): void {
	if ( ! request_path_is( '/auth.md' ) ) {
		return;
	}

	if ( ! is_feature_enabled( 'auth_md' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );

	// Plain-text Markdown served as text/markdown (never rendered as HTML).
	// Values are sanitized for the markdown context in build_auth_md().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo build_auth_md();
	exit;
}

/**
 * Build the auth.md body.
 *
 * @return string
 */
function build_auth_md(): string {
	// text/markdown body — markdown_safe_text/esc_url_raw (not esc_html/esc_url)
	// so agents receive real characters, not HTML entities.
	$host        = markdown_safe_text( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$name        = markdown_safe_text( get_bloginfo( 'name' ) );
	$rest_url    = esc_url_raw( rest_url() );
	$profile_url = esc_url_raw( admin_url( 'profile.php' ) . '#application-passwords-section' );

	$out  = "# {$host} auth.md\n\n";
	$out .= "How automated agents authenticate to the {$name} WordPress REST API.\n\n";

	$out .= "## Authentication method: Application Passwords\n\n";
	$out .= 'This site uses WordPress Application Passwords (built into WordPress 5.6+). '
		. 'An Application Password is a credential tied to a single WordPress user account, '
		. "intended for API clients — it cannot be used to log in to wp-admin interactively.\n\n";

	// Honesty guard, inverted-safe: when the availability function is MISSING
	// (WP < 5.6, where Application Passwords don't exist at all) the caveat
	// must appear, not disappear.
	if ( ! function_exists( 'wp_is_application_passwords_available' ) || ! wp_is_application_passwords_available() ) {
		$out .= 'Note: Application Passwords are currently not available on this site '
			. '(they require WordPress 5.6+ and HTTPS, or an explicit opt-in). '
			. "Contact the site owner before attempting authenticated requests.\n\n";
	}

	$out .= "## Getting credentials\n\n";
	$out .= 'There is no self-service or automated registration endpoint. A human account owner '
		. "must create an Application Password for the agent:\n\n";
	$out .= "1. Log in to WordPress as the user account the agent should act as.\n";
	$out .= "2. Open the profile screen: {$profile_url}\n";
	$out .= "3. Under \"Application Passwords\", enter a name identifying the agent and click \"Add New Application Password\".\n";
	$out .= "4. Copy the generated password immediately — it is shown only once.\n\n";

	$out .= "## Usage\n\n";
	$out .= "Send the WordPress username and the Application Password using HTTP Basic authentication:\n\n";
	$out .= "```\ncurl -u 'user:app-password' {$rest_url}\n```\n\n";

	$out .= "## Scope\n\n";
	$out .= 'An Application Password inherits the role and capabilities of the user account it belongs to '
		. '— there are no per-token scopes. For least privilege, create a dedicated user with only the '
		. "capabilities the agent needs.\n\n";

	$out .= "## Revocation\n\n";
	$out .= 'The account owner can revoke an Application Password at any time by deleting it from the same '
		. "Application Passwords section of the profile screen ({$profile_url}). Revocation takes effect immediately.\n\n";

	$out .= "REST API root: {$rest_url}\n";

	/**
	 * Filter the final auth.md body.
	 *
	 * Sites with additional authentication methods (e.g. an OAuth plugin) can
	 * append their own sections. Return value is served verbatim with
	 * `Content-Type: text/markdown`.
	 *
	 * @param string $out The Markdown body about to be served.
	 */
	return (string) apply_filters( 'ajaco_auth_md_content', $out );
}
