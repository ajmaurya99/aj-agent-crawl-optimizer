<?php
/**
 * Admin: render the Agent-Ready settings page.
 *
 * Score card → form → Testing → Details.
 *
 * @package AgentReady
 */

namespace AgentReady;

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

	$markdown_enabled           = (bool) get_option( 'agent_ready_markdown_enabled', false );
	$content_signals_enabled    = (bool) get_option( 'agent_ready_content_signals_enabled', false );
	$api_catalog_enabled        = (bool) get_option( 'agent_ready_api_catalog_enabled', false );
	$mcp_server_card_enabled    = (bool) get_option( 'agent_ready_mcp_server_card_enabled', false );
	$agent_skills_index_enabled = (bool) get_option( 'agent_ready_agent_skills_index_enabled', false );
	$webmcp_enabled             = (bool) get_option( 'agent_ready_webmcp_enabled', false );
	$json_ld_enabled            = (bool) get_option( 'agent_ready_json_ld_enabled', false );
	$openapi_enabled            = (bool) get_option( 'agent_ready_openapi_enabled', false );
	$indexnow_enabled           = (bool) get_option( 'agent_ready_indexnow_enabled', false );
	$indexnow_key               = (string) get_option( 'agent_ready_indexnow_key', '' );
	$llms_txt_enabled           = (bool) get_option( 'agent_ready_llms_txt_enabled', false );

	// Calculate Agent-Ready score.
	$features       = array(
		'markdown'        => $markdown_enabled,
		'content_signals' => $content_signals_enabled,
		'api_catalog'     => $api_catalog_enabled,
		'mcp_card'        => $mcp_server_card_enabled,
		'skills_index'    => $agent_skills_index_enabled,
		'webmcp'          => $webmcp_enabled,
		'json_ld'         => $json_ld_enabled,
		'openapi'         => $openapi_enabled,
		'llms_txt'        => $llms_txt_enabled,
		'indexnow'        => $indexnow_enabled,
	);
	$enabled_count  = count( array_filter( $features ) );
	$total_features = count( $features );
	$score          = (int) round( ( $enabled_count / $total_features ) * 100 );

	if ( $score >= 80 ) {
		$score_label = __( 'Excellent', 'agent-ready' );
		$score_color = '#00a32a';
	} elseif ( $score >= 60 ) {
		$score_label = __( 'Good', 'agent-ready' );
		$score_color = '#2271b1';
	} elseif ( $score >= 40 ) {
		$score_label = __( 'Needs Work', 'agent-ready' );
		$score_color = '#dba617';
	} else {
		$score_label = __( 'Poor', 'agent-ready' );
		$score_color = '#d63638';
	}
	$score_aria_label = sprintf(
		/* translators: 1: numeric score 0-100. 2: qualitative label e.g. "Excellent". */
		__( 'Agent-Ready score: %1$d out of 100, %2$s.', 'agent-ready' ),
		$score,
		$score_label
	);
	?>
	<div class="wrap">
		<h1 class="agent-ready-screen-reader-text"><?php esc_html_e( 'Agent-Ready Settings', 'agent-ready' ); ?></h1>

		<div class="agent-ready-score-card">
			<div class="agent-ready-score-circle">
				<svg width="100" height="100" viewBox="0 0 100 100" role="img" aria-label="<?php echo esc_attr( $score_aria_label ); ?>">
					<circle class="score-bg" cx="50" cy="50" r="40" />
					<circle
						class="score-progress"
						cx="50"
						cy="50"
						r="40"
						style="stroke: <?php echo esc_attr( $score_color ); ?>; stroke-dashoffset: <?php echo (int) ( 251.2 - ( 251.2 * $score / 100 ) ); ?>;"
					/>
				</svg>
				<div class="agent-ready-score-value">
					<span class="number"><?php echo esc_html( $score ); ?></span>
					<span class="label"><?php esc_html_e( 'Score', 'agent-ready' ); ?></span>
				</div>
			</div>
			<div class="agent-ready-score-info">
				<h2><?php echo esc_html( $score_label ); ?></h2>
				<p>
				<?php
				echo esc_html(
					sprintf(
					/* translators: 1: enabled count. 2: total features. */
						__( '%1$d of %2$d Agent-Ready features enabled', 'agent-ready' ),
						$enabled_count,
						$total_features
					)
				);
				?>
				</p>
				<div class="agent-ready-features-summary">
					<?php
					foreach ( $features as $key => $enabled ) :
						$labels = array(
							'markdown'        => __( 'Markdown', 'agent-ready' ),
							'content_signals' => __( 'Content-Signals', 'agent-ready' ),
							'api_catalog'     => __( 'API Catalog', 'agent-ready' ),
							'mcp_card'        => __( 'MCP Card', 'agent-ready' ),
							'skills_index'    => __( 'Skills Index', 'agent-ready' ),
							'webmcp'          => __( 'WebMCP', 'agent-ready' ),
							'json_ld'         => __( 'JSON-LD', 'agent-ready' ),
							'openapi'         => __( 'OpenAPI', 'agent-ready' ),
							'llms_txt'        => __( 'llms.txt', 'agent-ready' ),
							'indexnow'        => __( 'IndexNow', 'agent-ready' ),
						);
						?>
						<span class="agent-ready-feature-badge">
							<span class="dot <?php echo $enabled ? 'enabled' : 'disabled'; ?>"></span>
							<?php echo esc_html( $labels[ $key ] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<p>
			<?php esc_html_e( 'Agent-Ready improves your site\'s compatibility with AI agents and crawlers.', 'agent-ready' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'agent_ready_settings' ); ?>

			<h2 class="agent-ready-section-heading"><?php esc_html_e( 'Discovery', 'agent-ready' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Help AI agents find your site and figure out what it offers — manifests, indexes, and push-based notifications.', 'agent-ready' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Catalog', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_api_catalog_enabled">
							<input type="checkbox" id="agent_ready_api_catalog_enabled" name="agent_ready_api_catalog_enabled" value="1" <?php checked( $api_catalog_enabled, true ); ?> />
							<?php esc_html_e( 'Publish an API catalog at /.well-known/api-catalog for automated API discovery (RFC 9727). Also emits a Link header advertising the catalog on every response.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-api-catalog"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'MCP Server Card', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_mcp_server_card_enabled">
							<input type="checkbox" id="agent_ready_mcp_server_card_enabled" name="agent_ready_mcp_server_card_enabled" value="1" <?php checked( $mcp_server_card_enabled, true ); ?> />
							<?php esc_html_e( 'Publish MCP Server Card at /.well-known/mcp/server-card.json for AI agent tool discovery.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-mcp-server-card"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Agent Skills Index', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_agent_skills_index_enabled">
							<input type="checkbox" id="agent_ready_agent_skills_index_enabled" name="agent_ready_agent_skills_index_enabled" value="1" <?php checked( $agent_skills_index_enabled, true ); ?> />
							<?php esc_html_e( 'Publish Agent Skills Index at /.well-known/agent-skills/index.json plus per-skill SKILL.md artifacts.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-agent-skills-index"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'llms.txt', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_llms_txt_enabled">
							<input type="checkbox" id="agent_ready_llms_txt_enabled" name="agent_ready_llms_txt_enabled" value="1" <?php checked( $llms_txt_enabled, true ); ?> />
							<?php esc_html_e( 'Publish a curated, LLM-readable index of the site at /llms.txt (per llmstxt.org).', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-llms-txt"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'IndexNow', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_indexnow_enabled">
							<input type="checkbox" id="agent_ready_indexnow_enabled" name="agent_ready_indexnow_enabled" value="1" <?php checked( $indexnow_enabled, true ); ?> />
							<?php esc_html_e( 'Ping Bing and Yandex instantly when content is published or updated via IndexNow.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-indexnow"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
						<p class="description" style="color: #d63638; margin-top: 5px;">
							<?php esc_html_e( 'Recommended for production only — do not enable on local or staging environments.', 'agent-ready' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="agent_ready_indexnow_key"><?php esc_html_e( 'IndexNow API Key', 'agent-ready' ); ?></label>
					</th>
					<td>
						<input type="text" id="agent_ready_indexnow_key" name="agent_ready_indexnow_key" value="<?php echo esc_attr( $indexnow_key ); ?>" class="regular-text code" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. 1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p', 'agent-ready' ); ?>" />
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: %s: link to the Bing IndexNow portal. */
									__( 'Generate a key at %s and paste it here. Your site will host it at <code>/{key}.txt</code> for ownership verification.', 'agent-ready' ),
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

			<h2 class="agent-ready-section-heading"><?php esc_html_e( 'Presentation', 'agent-ready' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Format the site’s content and APIs in shapes agents can consume — Markdown, structured data, machine-readable specs.', 'agent-ready' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Markdown Negotiation', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_markdown_enabled">
							<input type="checkbox" id="agent_ready_markdown_enabled" name="agent_ready_markdown_enabled" value="1" <?php checked( $markdown_enabled, true ); ?> />
							<?php esc_html_e( 'Serve clean Markdown content when AI agents request it via Accept header.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-markdown"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'JSON-LD Schema', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_json_ld_enabled">
							<input type="checkbox" id="agent_ready_json_ld_enabled" name="agent_ready_json_ld_enabled" value="1" <?php checked( $json_ld_enabled, true ); ?> />
							<?php esc_html_e( 'Add Schema.org structured data (WebSite, Organization, Article, BreadcrumbList, FAQPage) for better content understanding by LLMs.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-json-ld"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
						<?php
						$active_seo = active_seo_plugin();
						if ( $active_seo !== null ) :
							?>
							<p class="description" style="color: #d63638; margin-top: 5px;">
								<?php
								printf(
									/* translators: %s: SEO plugin display name. */
									esc_html__( '%s is active and emits its own JSON-LD. To prevent duplicate structured data, our output is automatically suppressed regardless of this toggle.', 'agent-ready' ),
									'<strong>' . esc_html( $active_seo ) . '</strong>'
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'OpenAPI Spec', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_openapi_enabled">
							<input type="checkbox" id="agent_ready_openapi_enabled" name="agent_ready_openapi_enabled" value="1" <?php checked( $openapi_enabled, true ); ?> />
							<?php esc_html_e( 'Publish OpenAPI 3.0 specification at /?format=openapi for API documentation.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-openapi"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WebMCP Tools', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_webmcp_enabled">
							<input type="checkbox" id="agent_ready_webmcp_enabled" name="agent_ready_webmcp_enabled" value="1" <?php checked( $webmcp_enabled, true ); ?> />
							<?php esc_html_e( 'Expose site tools to AI agents via WebMCP browser API (Chrome experimental).', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-webmcp"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
			</table>

			<h2 class="agent-ready-section-heading"><?php esc_html_e( 'Declarations', 'agent-ready' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Declare your preferences for how AI systems may use your content.', 'agent-ready' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Content-Signals', 'agent-ready' ); ?></th>
					<td>
						<label for="agent_ready_content_signals_enabled">
							<input type="checkbox" id="agent_ready_content_signals_enabled" name="agent_ready_content_signals_enabled" value="1" <?php checked( $content_signals_enabled, true ); ?> />
							<?php esc_html_e( 'Add Content-Signals directives to robots.txt declaring AI usage preferences.', 'agent-ready' ); ?>
						</label>
						<a class="agent-ready-read-more" href="#detail-content-signals"><?php esc_html_e( 'Read more', 'agent-ready' ); ?></a>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agent-ready-reset-form" onsubmit="return confirm('<?php echo esc_js( __( 'Reset all Agent-Ready settings to defaults? This will turn every feature off and clear the IndexNow API key.', 'agent-ready' ) ); ?>');">
			<input type="hidden" name="action" value="agent_ready_reset" />
			<?php wp_nonce_field( 'agent_ready_reset' ); ?>
			<p class="description"><?php esc_html_e( 'Restores every toggle to off and clears the IndexNow API key. Cached endpoint outputs are also cleared.', 'agent-ready' ); ?></p>
			<?php submit_button( __( 'Reset to Defaults', 'agent-ready' ), 'secondary delete', 'agent-ready-reset-submit', false ); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Testing', 'agent-ready' ); ?></h2>
		<p class="description" style="margin-bottom: 15px;">
			<?php esc_html_e( 'One-click curl commands grouped by section.', 'agent-ready' ); ?>
		</p>

		<h3 class="agent-ready-test-section"><?php esc_html_e( 'Discovery', 'agent-ready' ); ?></h3>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'API Catalog', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check API catalog for automated discovery:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-api-catalog">curl <?php echo esc_url( home_url( '/.well-known/api-catalog' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-api-catalog"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/.well-known/api-catalog' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'API Catalog Link Header', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Confirm every page advertises the catalog via a Link header (RFC 9727 §3):', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-link-header">curl -sI <?php echo esc_url( home_url( '/' ) ); ?> | grep -i '^link:'</code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-link-header"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-note"><?php esc_html_e( 'Requires the API Catalog toggle above to be enabled.', 'agent-ready' ); ?></p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'MCP Server Card', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check MCP Server Card for AI agent tool discovery:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-mcp">curl <?php echo esc_url( home_url( '/.well-known/mcp/server-card.json' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-mcp"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/.well-known/mcp/server-card.json' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'Agent Skills Index', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check Agent Skills Index for skill discovery:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-skills">curl <?php echo esc_url( home_url( '/.well-known/agent-skills/index.json' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-skills"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/.well-known/agent-skills/index.json' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'llms.txt', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Fetch the LLM-readable site index:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-llms-txt">curl <?php echo esc_url( home_url( '/llms.txt' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-llms-txt"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'IndexNow', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'IndexNow pings Bing and Yandex when content changes. Test by publishing or updating a post — check your server logs for requests to api.indexnow.org.', 'agent-ready' ); ?></p>
			<p class="agent-ready-validate">
				<a href="https://www.bing.com/webmasters/" target="_blank" rel="noopener"><?php esc_html_e( 'Open Bing Webmaster Tools', 'agent-ready' ); ?></a>
			</p>
		</div>

		<h3 class="agent-ready-test-section"><?php esc_html_e( 'Presentation', 'agent-ready' ); ?></h3>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'Markdown Negotiation', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Test the Markdown negotiation feature:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-markdown">curl -H "Accept: text/markdown" <?php echo esc_url( home_url( '/' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-markdown"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'JSON-LD Schema', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check for JSON-LD structured data in page source:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-jsonld">curl <?php echo esc_url( home_url( '/' ) ); ?> | grep "application/ld+json"</code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-jsonld"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="https://search.google.com/test/rich-results?url=<?php echo rawurlencode( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in Google Rich Results Test', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'OpenAPI Spec', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check OpenAPI specification:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-openapi">curl "<?php echo esc_url( home_url( '/?format=openapi' ) ); ?>" | head -30</code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-openapi"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/?format=openapi' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
				<span class="agent-ready-validate-sep">·</span>
				<a href="https://editor.swagger.io/?url=<?php echo rawurlencode( home_url( '/?format=openapi' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in Swagger Editor', 'agent-ready' ); ?></a>
			</p>
		</div>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'WebMCP Tools', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'WebMCP tools are loaded via JavaScript on the frontend. Check the page source to verify the script is enqueued:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-webmcp">curl -s <?php echo esc_url( home_url( '/' ) ); ?> | grep webmcp</code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-webmcp"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-note"><?php esc_html_e( 'Note: WebMCP requires Chrome with the experimental AI features enabled.', 'agent-ready' ); ?></p>
		</div>

		<h3 class="agent-ready-test-section"><?php esc_html_e( 'Declarations', 'agent-ready' ); ?></h3>

		<div class="agent-ready-test-block">
			<h4><?php esc_html_e( 'Content-Signals', 'agent-ready' ); ?></h4>
			<p><?php esc_html_e( 'Check robots.txt for Content-Signals:', 'agent-ready' ); ?></p>
			<div class="agent-ready-code-wrapper">
				<code id="test-robots">curl <?php echo esc_url( home_url( '/robots.txt' ) ); ?></code>
				<button type="button" class="agent-ready-copy-btn" data-target="test-robots"><?php esc_html_e( 'Copy', 'agent-ready' ); ?></button>
			</div>
			<p class="agent-ready-validate">
				<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View output', 'agent-ready' ); ?></a>
			</p>
		</div>

		<hr />

		<h2><?php esc_html_e( 'Details', 'agent-ready' ); ?></h2>
		<p class="description" style="margin-bottom: 15px;">
			<?php esc_html_e( 'What each feature actually does behind the scenes when its checkbox is enabled.', 'agent-ready' ); ?>
		</p>

		<div class="agent-ready-details">
			<h3 class="agent-ready-details-section"><?php esc_html_e( 'Discovery', 'agent-ready' ); ?></h3>

			<h4 id="detail-api-catalog"><?php esc_html_e( 'API Catalog', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: 1: well-known URL path. 2: MIME type. */
						esc_html__( 'Serves %1$s as %2$s per RFC 9727.', 'agent-ready' ),
						'<code>/.well-known/api-catalog</code>',
						'<code>application/linkset+json</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Each linkset entry advertises service-desc (OpenAPI spec), service-doc (WordPress REST API handbook), and status (REST root).', 'agent-ready' ); ?></li>
				<li>
				<?php
					printf(
						/* translators: %s: HTTP Link header example, code-formatted. */
						esc_html__( 'Also emits %s on every frontend response so agents discover the catalog without having to know about /.well-known/.', 'agent-ready' ),
						'<code>Link: &lt;url&gt;; rel="api-catalog"</code>'
					);
				?>
				</li>
			</ul>

			<h4 id="detail-mcp-server-card"><?php esc_html_e( 'MCP Server Card', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: well-known URL path, code-formatted. */
						esc_html__( 'Serves %s per the SEP-1649 draft so MCP-aware agents can discover the site.', 'agent-ready' ),
						'<code>/.well-known/mcp/server-card.json</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'serverInfo (name, version, description, websiteUrl) is generated dynamically from get_bloginfo() so it stays accurate per subsite.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Declares transport, MCP capability flag objects, protocolVersion, and instructions pointing agents at the API catalog and OpenAPI spec.', 'agent-ready' ); ?></li>
			</ul>

			<h4 id="detail-agent-skills-index"><?php esc_html_e( 'Agent Skills Index', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: well-known URL path, code-formatted. */
						esc_html__( 'Serves %s listing 6 skills (content-query, posts-read, pages-read, media-library, categories, tags) per Agent Skills Discovery RFC v0.2.0.', 'agent-ready' ),
						'<code>/.well-known/agent-skills/index.json</code>'
					);
				?>
				</li>
				<li>
				<?php
					printf(
						/* translators: %s: URL pattern for SKILL.md artifacts, code-formatted. */
						esc_html__( 'For each skill, also serves a deterministic SKILL.md artifact at %s with a how-to-use guide.', 'agent-ready' ),
						'<code>/.well-known/agent-skills/{name}/SKILL.md</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Each index entry includes a real sha256 digest of the served SKILL.md bytes so agents can verify the artifact has not been tampered with.', 'agent-ready' ); ?></li>
			</ul>

			<h4 id="detail-llms-txt"><?php esc_html_e( 'llms.txt', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: URL path of /llms.txt, code-formatted. */
						esc_html__( 'Serves %s — a curated, Markdown index of the site that LLMs read to find your most important pages, per llmstxt.org.', 'agent-ready' ),
						'<code>/llms.txt</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Generated dynamically per site/subsite from get_bloginfo() plus your top-level published pages and recent posts (excerpts cleaned, read-more markers stripped).', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'A Discovery section auto-links every other agent-ready endpoint that is currently enabled (API Catalog, OpenAPI, Skills Index, MCP Card) so an LLM can crawl from llms.txt to everything else.', 'agent-ready' ); ?></li>
			</ul>

			<h4 id="detail-indexnow"><?php esc_html_e( 'IndexNow', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: IndexNow API endpoint URL, code-formatted. */
						esc_html__( 'Hooks transition_post_status; when a post moves to publish, fires a non-blocking POST to %s.', 'agent-ready' ),
						'<code>https://api.indexnow.org/indexnow</code>'
					);
				?>
				</li>
				<li>
				<?php
					printf(
						/* translators: %s: JSON payload shape, code-formatted. */
						esc_html__( 'Payload is exactly %s — Bing and Yandex begin re-crawling the URL within minutes.', 'agent-ready' ),
						'<code>' . esc_html( '{ host, key, urlList }' ) . '</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Skips revisions, autosaves, and post types that are not public. The ping is non-blocking so page saves stay fast.', 'agent-ready' ); ?></li>
				<li>
				<?php
					printf(
						/* translators: %s: URL pattern for the IndexNow key file, code-formatted. */
						esc_html__( 'Reads the key from the IndexNow API Key field above; also serves the key file at %s so search engines can verify ownership.', 'agent-ready' ),
						'<code>/{key}.txt</code>'
					);
				?>
				</li>
			</ul>

			<h3 class="agent-ready-details-section"><?php esc_html_e( 'Presentation', 'agent-ready' ); ?></h3>

			<h4 id="detail-markdown"><?php esc_html_e( 'Markdown Negotiation', 'agent-ready' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Watches every page request for the Accept: text/markdown header; browsers asking for HTML get the normal response.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Buffers the rendered HTML, extracts the main content region, and converts it to clean Markdown.', 'agent-ready' ); ?></li>
				<li>
				<?php
					printf(
						/* translators: 1: response Content-Type header. 2: X-Markdown-Tokens header name. */
						esc_html__( 'Returns %1$s and emits %2$s (≈ chars/4) so AI agents can budget context size.', 'agent-ready' ),
						'<code>' . esc_html( 'Content-Type: text/markdown; charset=UTF-8' ) . '</code>',
						'<code>X-Markdown-Tokens</code>'
					);
				?>
				</li>
			</ul>

			<h4 id="detail-json-ld"><?php esc_html_e( 'JSON-LD Schema', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: HTML script tag opening, code-formatted. */
						esc_html__( 'Outputs %s in the head on every page so search engines and LLMs understand the content.', 'agent-ready' ),
						'<code>&lt;script type="application/ld+json"&gt;</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Always includes WebSite (with SearchAction) and Organization (logo resolved from theme custom logo or site icon).', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'On singular posts/pages of public REST-enabled post types, adds Article (cleaned excerpt, author, datePublished/dateModified, image, mainEntityOfPage).', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'On non-front singular pages, adds BreadcrumbList (Home → Category → Post).', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Auto-detects FAQ content (definition-list pairs or question-shaped headings) and emits FAQPage with Question/Answer pairs.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Auto-suppresses when an SEO plugin (Yoast, Rank Math, AIOSEO, etc.) is active to prevent duplicate structured data.', 'agent-ready' ); ?></li>
			</ul>

			<h4 id="detail-openapi"><?php esc_html_e( 'OpenAPI Spec', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: query string parameter, code-formatted. */
						esc_html__( 'Intercepts %s and returns a complete OpenAPI 3.0.3 document as application/json.', 'agent-ready' ),
						'<code>?format=openapi</code>'
					);
				?>
				</li>
				<li>
				<?php
					printf(
						/* translators: %s: WordPress function name, code-formatted. */
						esc_html__( 'Iterates every route from %s (filtered through rest_endpoints to honor show_in_index), so plugin-registered routes appear automatically.', 'agent-ready' ),
						'<code>rest_get_server()</code>'
					);
				?>
				</li>
				<li>
				<?php
					printf(
						/* translators: 1: regex named-capture pattern. 2: OpenAPI templated-path pattern. */
						esc_html__( 'Converts WordPress regex placeholders such as %1$s into OpenAPI templated paths like %2$s and splits args into path parameters, query parameters, and requestBody for write methods.', 'agent-ready' ),
						'<code>(?P&lt;id&gt;[\\d]+)</code>',
						'<code>{id}</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Includes Post, Page, Media, Term, and User component schemas, with info pulled from site name, description, and WordPress version.', 'agent-ready' ); ?></li>
			</ul>

			<h4 id="detail-webmcp"><?php esc_html_e( 'WebMCP Tools', 'agent-ready' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Enqueues a frontend script that registers tools via navigator.modelContext.provideContext() — the W3C WebMCP draft API.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Exposes 4 tools to AI agents: search_content, get_posts, get_pages, get_site_info — each with name, description, JSON-Schema inputSchema, and an execute callback that wraps the WP REST API.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Subsite-aware via wp_localize_script — the script reads the right REST URL for whichever site it is running on.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'The tools only register in browsers with the experimental WebMCP feature enabled (currently Chrome behind a flag). Other browsers no-op silently.', 'agent-ready' ); ?></li>
			</ul>

			<h3 class="agent-ready-details-section"><?php esc_html_e( 'Declarations', 'agent-ready' ); ?></h3>

			<h4 id="detail-content-signals"><?php esc_html_e( 'Content-Signals', 'agent-ready' ); ?></h4>
			<ul>
				<li>
				<?php
					printf(
						/* translators: %s: /robots.txt path, code-formatted. */
						esc_html__( 'Intercepts %s (and any subsite path ending in it) and serves WordPress-default robots rules plus a Content-Signal directive.', 'agent-ready' ),
						'<code>/robots.txt</code>'
					);
				?>
				</li>
				<li><?php esc_html_e( 'Composes with SEO plugins: Yoast/Rank Math/AIOSEO additions are preserved, our Content-Signal is appended last.', 'agent-ready' ); ?></li>
				<li><?php esc_html_e( 'Honors the "Discourage search engines" reading setting (switches to Disallow: / when that is on).', 'agent-ready' ); ?></li>
			</ul>
		</div>

	</div>
	<?php
}
