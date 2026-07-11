<?php
/**
 * Admin: render the Agent Ready settings page.
 *
 * Structure: readiness banner (real scan Level, not a toggle count) → feature
 * toggles (Discovery / Presentation / Declarations, with per-bot AI crawler
 * policy and Content-Signal preferences) → verification pointer → danger zone.
 *
 * Verification lives on the Dashboard — this page only configures what the
 * plugin publishes. One source of truth: the only score shown here is the
 * scan-verified Level.
 *
 * @package Ajaco
 */

namespace Ajaco;

use Ajaco\Scan\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the settings page.
 *
 * @return void
 */
function render_settings_page(): void {
	if ( ! current_user_can( required_capability() ) ) {
		return;
	}

	// First-activation Quick Setup wizard takes over the page once.
	if ( should_show_wizard() ) {
		render_wizard();
		return;
	}

	$markdown_enabled           = (bool) get_option( 'ajaco_markdown_enabled', false );
	$content_signals_enabled    = (bool) get_option( 'ajaco_content_signals_enabled', false );
	$api_catalog_enabled        = (bool) get_option( 'ajaco_api_catalog_enabled', false );
	$mcp_server_card_enabled    = (bool) get_option( 'ajaco_mcp_server_card_enabled', false );
	$agent_skills_index_enabled = (bool) get_option( 'ajaco_agent_skills_index_enabled', false );
	$webmcp_enabled             = (bool) get_option( 'ajaco_webmcp_enabled', false );
	$json_ld_enabled            = (bool) get_option( 'ajaco_json_ld_enabled', false );
	$openapi_enabled            = (bool) get_option( 'ajaco_openapi_enabled', false );
	$indexnow_enabled           = (bool) get_option( 'ajaco_indexnow_enabled', false );
	$indexnow_key               = (string) get_option( 'ajaco_indexnow_key', '' );
	$llms_txt_enabled           = (bool) get_option( 'ajaco_llms_txt_enabled', false );
	$ai_bot_rules_enabled       = (bool) get_option( 'ajaco_ai_bot_rules_enabled', false );
	$auth_md_enabled            = (bool) get_option( 'ajaco_auth_md_enabled', false );
	?>
	<div class="wrap">
		<h1 class="ajaco-screen-reader-text"><?php esc_html_e( 'Agent Ready Settings', 'aj-agent-crawl-optimizer' ); ?></h1>

		<?php render_settings_level_banner(); ?>

		<p>
			<?php esc_html_e( 'These toggles control what the plugin publishes for AI agents. Whether it actually works on your site is verified by the scanner on the Dashboard.', 'aj-agent-crawl-optimizer' ); ?>
			<?php esc_html_e( 'Detailed behavior notes for every feature are under the "Help" tab at the top right of this screen.', 'aj-agent-crawl-optimizer' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'ajaco_settings' ); ?>

			<h2 class="ajaco-section-heading"><?php esc_html_e( 'Discovery', 'aj-agent-crawl-optimizer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Help AI agents find your site and figure out what it offers — manifests, indexes, and push-based notifications.', 'aj-agent-crawl-optimizer' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Catalog', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_api_catalog_enabled">
							<input type="checkbox" id="ajaco_api_catalog_enabled" name="ajaco_api_catalog_enabled" value="1" <?php checked( $api_catalog_enabled, true ); ?> />
							<?php esc_html_e( 'Publish an API catalog at /.well-known/api-catalog for automated API discovery (RFC 9727). Also emits a Link header advertising the catalog on every response.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
						<?php if ( $api_catalog_enabled && ! $openapi_enabled ) : ?>
							<p class="description ajaco-hint">
								<?php esc_html_e( 'OpenAPI Spec is off, so the catalog is published without a service-desc link. Enable OpenAPI below for a complete catalog.', 'aj-agent-crawl-optimizer' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MCP Server Card', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_mcp_server_card_enabled">
							<input type="checkbox" id="ajaco_mcp_server_card_enabled" name="ajaco_mcp_server_card_enabled" value="1" <?php checked( $mcp_server_card_enabled, true ); ?> />
							<?php esc_html_e( 'Publish MCP Server Card at /.well-known/mcp/server-card.json for AI agent tool discovery.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Agent Skills Index', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_agent_skills_index_enabled">
							<input type="checkbox" id="ajaco_agent_skills_index_enabled" name="ajaco_agent_skills_index_enabled" value="1" <?php checked( $agent_skills_index_enabled, true ); ?> />
							<?php esc_html_e( 'Publish Agent Skills Index at /.well-known/agent-skills/index.json plus per-skill SKILL.md artifacts.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'auth.md', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_auth_md_enabled">
							<input type="checkbox" id="ajaco_auth_md_enabled" name="ajaco_auth_md_enabled" value="1" <?php checked( $auth_md_enabled, true ); ?> />
							<?php esc_html_e( 'Publish /auth.md documenting how agents authenticate to the REST API via Application Passwords.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'llms.txt', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_llms_txt_enabled">
							<input type="checkbox" id="ajaco_llms_txt_enabled" name="ajaco_llms_txt_enabled" value="1" <?php checked( $llms_txt_enabled, true ); ?> />
							<?php esc_html_e( 'Publish a curated, LLM-readable index at /llms.txt plus full recent content at /llms-full.txt (per llmstxt.org).', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'IndexNow', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_indexnow_enabled">
							<input type="checkbox" id="ajaco_indexnow_enabled" name="ajaco_indexnow_enabled" value="1" <?php checked( $indexnow_enabled, true ); ?> />
							<?php esc_html_e( 'Ping Bing and Yandex instantly when content is published or updated via IndexNow.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
						<?php if ( $indexnow_enabled && '' === trim( $indexnow_key ) ) : ?>
							<p class="description ajaco-warn">
								<?php esc_html_e( 'IndexNow is enabled but no API key is set below — no pings are being sent.', 'aj-agent-crawl-optimizer' ); ?>
							</p>
						<?php endif; ?>
						<p class="description ajaco-warn">
							<?php esc_html_e( 'Recommended for production only — do not enable on local or staging environments.', 'aj-agent-crawl-optimizer' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ajaco_indexnow_key"><?php esc_html_e( 'IndexNow API Key', 'aj-agent-crawl-optimizer' ); ?></label>
					</th>
					<td>
						<input type="text" id="ajaco_indexnow_key" name="ajaco_indexnow_key" value="<?php echo esc_attr( $indexnow_key ); ?>" class="regular-text code" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. 1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p', 'aj-agent-crawl-optimizer' ); ?>" />
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: %s: link to the Bing IndexNow portal. */
									__( 'Generate a key at %s and paste it here. Your site will host it at <code>/{key}.txt</code> for ownership verification.', 'aj-agent-crawl-optimizer' ),
									array(
										'code' => array(),
										'a'    => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								),
								'<a href="https://www.bing.com/webmasters/indexnow" target="_blank" rel="noopener">bing.com/webmasters/indexnow</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>

			<h2 class="ajaco-section-heading"><?php esc_html_e( 'Presentation', 'aj-agent-crawl-optimizer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Format the site’s content and APIs in shapes agents can consume — Markdown, structured data, machine-readable specs.', 'aj-agent-crawl-optimizer' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Markdown Negotiation', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_markdown_enabled">
							<input type="checkbox" id="ajaco_markdown_enabled" name="ajaco_markdown_enabled" value="1" <?php checked( $markdown_enabled, true ); ?> />
							<?php esc_html_e( 'Serve clean Markdown content when AI agents request it via Accept header.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'JSON-LD Schema', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_json_ld_enabled">
							<input type="checkbox" id="ajaco_json_ld_enabled" name="ajaco_json_ld_enabled" value="1" <?php checked( $json_ld_enabled, true ); ?> />
							<?php esc_html_e( 'Add Schema.org structured data (WebSite, Organization, Article, BreadcrumbList, FAQPage) for better content understanding by LLMs.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
						<?php
						$active_seo = active_seo_plugin();
						if ( null !== $active_seo ) :
							?>
							<p class="description ajaco-warn">
								<?php
								printf(
									/* translators: %s: SEO plugin display name. */
									esc_html__( '%s is active and emits its own JSON-LD. To prevent duplicate structured data, our output is automatically suppressed regardless of this toggle.', 'aj-agent-crawl-optimizer' ),
									'<strong>' . esc_html( $active_seo ) . '</strong>'
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'OpenAPI Spec', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_openapi_enabled">
							<input type="checkbox" id="ajaco_openapi_enabled" name="ajaco_openapi_enabled" value="1" <?php checked( $openapi_enabled, true ); ?> />
							<?php esc_html_e( 'Publish OpenAPI 3.0 specification at /openapi.json for API documentation.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WebMCP Tools', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_webmcp_enabled">
							<input type="checkbox" id="ajaco_webmcp_enabled" name="ajaco_webmcp_enabled" value="1" <?php checked( $webmcp_enabled, true ); ?> />
							<?php esc_html_e( 'Expose site tools to AI agents via WebMCP browser API (Chrome experimental).', 'aj-agent-crawl-optimizer' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2 class="ajaco-section-heading"><?php esc_html_e( 'Declarations', 'aj-agent-crawl-optimizer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Declare your preferences for how AI systems may use your content.', 'aj-agent-crawl-optimizer' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'AI Bot Rules', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_ai_bot_rules_enabled">
							<input type="checkbox" id="ajaco_ai_bot_rules_enabled" name="ajaco_ai_bot_rules_enabled" value="1" <?php checked( $ai_bot_rules_enabled, true ); ?> />
							<?php esc_html_e( 'Add explicit robots.txt User-agent groups for the 15 AI crawlers readiness scanners check for, using the per-bot policy below.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
						<?php render_ai_bot_policy_table(); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content-Signals', 'aj-agent-crawl-optimizer' ); ?></th>
					<td>
						<label for="ajaco_content_signals_enabled">
							<input type="checkbox" id="ajaco_content_signals_enabled" name="ajaco_content_signals_enabled" value="1" <?php checked( $content_signals_enabled, true ); ?> />
							<?php esc_html_e( 'Add a Content-Signal directive to robots.txt declaring AI usage preferences.', 'aj-agent-crawl-optimizer' ); ?>
						</label>
						<?php render_content_signal_fields(); ?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Verification', 'aj-agent-crawl-optimizer' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Don’t trust the toggles — verify. The Dashboard scans your live site over real HTTP (catching caches, CDNs, and server rules) and shows evidence for every check.', 'aj-agent-crawl-optimizer' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( dashboard_page_url() ); ?>"><?php esc_html_e( 'Run a scan on the Dashboard', 'aj-agent-crawl-optimizer' ); ?></a>
		</p>
		<p class="description">
			<?php esc_html_e( 'External validators:', 'aj-agent-crawl-optimizer' ); ?>
			<a href="<?php echo esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google Rich Results Test', 'aj-agent-crawl-optimizer' ); ?></a>
			·
			<a href="<?php echo esc_url( 'https://editor.swagger.io/?url=' . rawurlencode( home_url( '/openapi.json' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Swagger Editor (OpenAPI)', 'aj-agent-crawl-optimizer' ); ?></a>
			·
			<a href="https://isitagentready.com/" target="_blank" rel="noopener"><?php esc_html_e( 'isitagentready.com', 'aj-agent-crawl-optimizer' ); ?></a>
		</p>

		<hr />

		<h2><?php esc_html_e( 'Setup & reset', 'aj-agent-crawl-optimizer' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( add_query_arg( 'ajaco-wizard', '1', settings_page_url() ) ); ?>">
				<?php esc_html_e( 'Run the setup wizard again', 'aj-agent-crawl-optimizer' ); ?>
			</a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ajaco-reset-form" onsubmit="return confirm('<?php echo esc_js( __( 'Reset all Agent Ready settings to defaults? This turns every feature off, clears the IndexNow API key and bot policy, and deletes the stored scan.', 'aj-agent-crawl-optimizer' ) ); ?>');">
			<input type="hidden" name="action" value="ajaco_reset" />
			<?php wp_nonce_field( 'ajaco_reset' ); ?>
			<p class="description"><?php esc_html_e( 'Restores every toggle to off, clears the IndexNow API key and per-bot policy, deletes the stored scan result, and flushes cached endpoint outputs.', 'aj-agent-crawl-optimizer' ); ?></p>
			<?php submit_button( __( 'Reset to Defaults', 'aj-agent-crawl-optimizer' ), 'secondary delete', 'ajaco-reset-submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Readiness banner: the scan-verified Level (the only score this page shows),
 * or a run-your-first-scan prompt. One source of truth with the Dashboard.
 *
 * @return void
 */
function render_settings_level_banner(): void {
	$scan   = Scanner::get_last_scan();
	$colors = array( '#d63638', '#d97706', '#dba617', '#2271b1', '#00a32a', '#008a20' );
	?>
	<div class="ajaco-level-banner">
		<?php if ( is_array( $scan ) && isset( $scan['level'], $scan['levelName'] ) ) : ?>
			<?php $level = (int) $scan['level']; ?>
			<span class="ajaco-level-pill" style="background: <?php echo esc_attr( isset( $colors[ $level ] ) ? $colors[ $level ] : '#8c8f94' ); ?>;">
				<?php
				/* translators: %d: readiness level 0-5. */
				printf( esc_html__( 'Level %d', 'aj-agent-crawl-optimizer' ), (int) $level );
				?>
			</span>
			<span class="ajaco-level-name"><?php echo esc_html( $scan['levelName'] ); ?></span>
			<span class="ajaco-level-meta">
				<?php
				printf(
					/* translators: %s: date/time of the last scan. */
					esc_html__( 'Last verified by scan: %s', 'aj-agent-crawl-optimizer' ),
					esc_html( isset( $scan['scannedAt'] ) ? gmdate( 'M j, Y H:i', strtotime( (string) $scan['scannedAt'] ) ) . ' UTC' : '' )
				);
				?>
			</span>
			<a class="button button-small" href="<?php echo esc_url( dashboard_page_url() ); ?>"><?php esc_html_e( 'Open Dashboard', 'aj-agent-crawl-optimizer' ); ?></a>
		<?php else : ?>
			<span class="ajaco-level-name"><?php esc_html_e( 'No scan yet — your real agent-readiness Level is measured on the Dashboard, not by counting toggles.', 'aj-agent-crawl-optimizer' ); ?></span>
			<a class="button button-primary button-small" href="<?php echo esc_url( dashboard_page_url() ); ?>"><?php esc_html_e( 'Run your first scan', 'aj-agent-crawl-optimizer' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Per-bot AI crawler policy table with preset buttons. Fields are ALWAYS
 * rendered (whether or not the feature is enabled) so a settings save never
 * loses the stored policy — options.php updates every registered option in
 * the submitted group.
 *
 * @return void
 */
function render_ai_bot_policy_table(): void {
	$bots   = ai_bot_list();
	$policy = ai_bot_policy();

	$purpose_labels = array(
		'training'    => __( 'Training', 'aj-agent-crawl-optimizer' ),
		'search'      => __( 'AI search', 'aj-agent-crawl-optimizer' ),
		'user-action' => __( 'User requests', 'aj-agent-crawl-optimizer' ),
	);
	?>
	<div class="ajaco-bot-policy">
		<p class="description">
			<?php esc_html_e( 'Per-crawler policy (applies when the toggle above is on). Purpose: Training = model training crawls; AI search = answer-engine indexing; User requests = fetches made for a live user.', 'aj-agent-crawl-optimizer' ); ?>
		</p>
		<p class="ajaco-bot-presets">
			<button type="button" class="button button-small" data-ajaco-preset="allow-all"><?php esc_html_e( 'Allow all', 'aj-agent-crawl-optimizer' ); ?></button>
			<button type="button" class="button button-small" data-ajaco-preset="block-training"><?php esc_html_e( 'Allow search & user requests, block training', 'aj-agent-crawl-optimizer' ); ?></button>
			<button type="button" class="button button-small" data-ajaco-preset="block-all"><?php esc_html_e( 'Block all', 'aj-agent-crawl-optimizer' ); ?></button>
		</p>
		<table class="ajaco-bot-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Crawler', 'aj-agent-crawl-optimizer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Purpose', 'aj-agent-crawl-optimizer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Policy', 'aj-agent-crawl-optimizer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $bots as $token => $purpose ) : ?>
					<tr>
						<td><code><?php echo esc_html( $token ); ?></code></td>
						<td><?php echo esc_html( isset( $purpose_labels[ $purpose ] ) ? $purpose_labels[ $purpose ] : $purpose ); ?></td>
						<td>
							<select name="ajaco_ai_bot_policy[<?php echo esc_attr( $token ); ?>]" data-ajaco-purpose="<?php echo esc_attr( $purpose ); ?>" aria-label="<?php echo esc_attr( $token ); ?>">
								<option value="allow" <?php selected( isset( $policy[ $token ] ) ? $policy[ $token ] : 'allow', 'allow' ); ?>><?php esc_html_e( 'Allow', 'aj-agent-crawl-optimizer' ); ?></option>
								<option value="block" <?php selected( isset( $policy[ $token ] ) ? $policy[ $token ] : 'allow', 'block' ); ?>><?php esc_html_e( 'Block', 'aj-agent-crawl-optimizer' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Content-Signal preference selects (ai-train / search / ai-input). Always
 * rendered for the same save-safety reason as the bot policy table.
 *
 * @return void
 */
function render_content_signal_fields(): void {
	$prefs  = content_signal_prefs();
	$fields = array(
		'ai_train' => __( 'AI training (ai-train)', 'aj-agent-crawl-optimizer' ),
		'search'   => __( 'Search (search)', 'aj-agent-crawl-optimizer' ),
		'ai_input' => __( 'AI input / grounding (ai-input)', 'aj-agent-crawl-optimizer' ),
	);
	?>
	<div class="ajaco-signal-fields">
		<?php foreach ( $fields as $key => $label ) : ?>
			<label class="ajaco-signal-field">
				<?php echo esc_html( $label ); ?>
				<select name="ajaco_content_signal_prefs[<?php echo esc_attr( $key ); ?>]">
					<option value="yes" <?php selected( $prefs[ $key ], 'yes' ); ?>><?php esc_html_e( 'yes', 'aj-agent-crawl-optimizer' ); ?></option>
					<option value="no" <?php selected( $prefs[ $key ], 'no' ); ?>><?php esc_html_e( 'no', 'aj-agent-crawl-optimizer' ); ?></option>
				</select>
			</label>
		<?php endforeach; ?>
		<p class="description">
			<?php esc_html_e( 'Emitted as e.g. "Content-Signal: ai-train=no, search=yes, ai-input=no". A declaration of preference, not enforcement.', 'aj-agent-crawl-optimizer' ); ?>
		</p>
	</div>
	<?php
}
