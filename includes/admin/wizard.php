<?php
/**
 * Admin: first-activation Quick Setup wizard.
 *
 * Rendered on the settings page in place of the normal UI when a one-shot
 * transient is set. The user applies recommended defaults (environment-aware:
 * skips JSON-LD if an SEO plugin is detected) or skips to manual configuration.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WIZARD_TRANSIENT    = 'agent_ready_show_wizard';
const WIZARD_APPLIED_FLAG = 'agent_ready_wizard_applied';

add_action( 'admin_post_agent_ready_apply_wizard', __NAMESPACE__ . '\\handle_wizard_submit' );
add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_show_wizard_applied_notice' );

/**
 * Whether the first-activation wizard should be displayed.
 *
 * @return bool
 */
function should_show_wizard(): bool {
	return (bool) get_transient( WIZARD_TRANSIENT );
}

/**
 * Recommended default state for each toggle, environment-aware.
 *
 * @return array<string, bool>
 */
function recommended_settings(): array {
	$seo_active = active_seo_plugin() !== null;

	return array(
		// Discovery — all on, low cost, no conflicts.
		'agent_ready_api_catalog_enabled'        => true,
		'agent_ready_mcp_server_card_enabled'    => true,
		'agent_ready_agent_skills_index_enabled' => true,
		'agent_ready_llms_txt_enabled'           => true,
		// IndexNow off — requires API key + production environment.
		'agent_ready_indexnow_enabled'           => false,
		// Presentation — on by default; JSON-LD off when an SEO plugin is active
		// (auto-suppression would no-op anyway, but turning it off makes the UI honest).
		'agent_ready_markdown_enabled'           => true,
		'agent_ready_json_ld_enabled'            => ! $seo_active,
		'agent_ready_openapi_enabled'            => true,
		'agent_ready_webmcp_enabled'             => true,
		// Declarations — on.
		'agent_ready_content_signals_enabled'    => true,
	);
}

/**
 * Handle the wizard form submission.
 *
 * @return void
 */
function handle_wizard_submit(): void {
	if ( ! current_user_can( required_capability() ) ) {
		wp_die(
			esc_html__( 'You do not have permission to run the Agent-Ready setup wizard.', 'agent-ready' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'agent_ready_apply_wizard' );

	$action = isset( $_POST['agent_ready_wizard_action'] )
		? sanitize_text_field( wp_unslash( $_POST['agent_ready_wizard_action'] ) )
		: '';

	if ( $action === 'apply' ) {
		// Save whatever the user actually checked in the form. Each option
		// either appears in $_POST as '1' (checked) or is absent (unchecked).
		foreach ( array_keys( recommended_settings() ) as $option ) {
			$checked = isset( $_POST[ $option ] ) && $_POST[ $option ] === '1';
			update_option( $option, $checked );
		}
		set_transient( WIZARD_APPLIED_FLAG, 1, 60 );
	}

	// Apply or skip — either way, dismiss the wizard.
	delete_transient( WIZARD_TRANSIENT );

	wp_safe_redirect( admin_url( 'options-general.php?page=agent-ready' ) );
	exit;
}

/**
 * Show a flash notice after the wizard's settings are applied.
 *
 * @return void
 */
function maybe_show_wizard_applied_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->id !== 'settings_page_agent-ready' ) {
		return;
	}

	if ( ! get_transient( WIZARD_APPLIED_FLAG ) ) {
		return;
	}

	delete_transient( WIZARD_APPLIED_FLAG );
	?>
	<div class="notice notice-success settings-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Recommended Agent-Ready settings applied.', 'agent-ready' ); ?></strong>
			<?php esc_html_e( 'Tweak any toggle below if you want to deviate from the defaults.', 'agent-ready' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Render the wizard UI in place of the normal settings page.
 *
 * @return void
 */
function render_wizard(): void {
	$rec        = recommended_settings();
	$seo_plugin = active_seo_plugin();
	$skip_url   = wp_nonce_url(
		add_query_arg(
			array(
				'action'                    => 'agent_ready_apply_wizard',
				'agent_ready_wizard_action' => 'skip',
			),
			admin_url( 'admin-post.php' )
		),
		'agent_ready_apply_wizard'
	);

	$rows = array(
		// label, option name, recommended-by-default, description.
		array( 'discovery', __( 'API Catalog', 'agent-ready' ), 'agent_ready_api_catalog_enabled', __( '/.well-known/api-catalog manifest plus a Link header on every response (RFC 9727).', 'agent-ready' ) ),
		array( 'discovery', __( 'MCP Server Card', 'agent-ready' ), 'agent_ready_mcp_server_card_enabled', __( '/.well-known/mcp/server-card.json descriptor for MCP-aware agents.', 'agent-ready' ) ),
		array( 'discovery', __( 'Agent Skills Index', 'agent-ready' ), 'agent_ready_agent_skills_index_enabled', __( '/.well-known/agent-skills/index.json plus per-skill SKILL.md artifacts.', 'agent-ready' ) ),
		array( 'discovery', __( 'llms.txt', 'agent-ready' ), 'agent_ready_llms_txt_enabled', __( 'A curated, LLM-readable index at /llms.txt (per llmstxt.org).', 'agent-ready' ) ),
		array( 'discovery', __( 'IndexNow', 'agent-ready' ), 'agent_ready_indexnow_enabled', __( 'Pings Bing/Yandex on publish. Requires a key from bing.com/webmasters/indexnow — leave off for now and configure later.', 'agent-ready' ) ),
		array( 'presentation', __( 'Markdown Negotiation', 'agent-ready' ), 'agent_ready_markdown_enabled', __( 'Returns clean Markdown when an agent sends Accept: text/markdown. Browsers are unaffected.', 'agent-ready' ) ),
		array( 'presentation', __( 'JSON-LD Schema', 'agent-ready' ), 'agent_ready_json_ld_enabled', __( 'WebSite, Organization, Article, BreadcrumbList, FAQPage structured data.', 'agent-ready' ) ),
		array( 'presentation', __( 'OpenAPI Spec', 'agent-ready' ), 'agent_ready_openapi_enabled', __( 'OpenAPI 3.0.3 document at /?format=openapi, generated from REST routes.', 'agent-ready' ) ),
		array( 'presentation', __( 'WebMCP Tools', 'agent-ready' ), 'agent_ready_webmcp_enabled', __( 'Frontend script registering tools via navigator.modelContext (Chrome experimental).', 'agent-ready' ) ),
		array( 'declarations', __( 'Content-Signals', 'agent-ready' ), 'agent_ready_content_signals_enabled', __( 'Adds a Content-Signal directive to robots.txt declaring AI-usage preferences.', 'agent-ready' ) ),
	);

	$section_titles = array(
		'discovery'    => __( 'Discovery', 'agent-ready' ),
		'presentation' => __( 'Presentation', 'agent-ready' ),
		'declarations' => __( 'Declarations', 'agent-ready' ),
	);

	$current_section = '';
	?>
	<div class="wrap agent-ready-wizard-wrap">
		<h1><?php esc_html_e( 'Welcome to Agent-Ready', 'agent-ready' ); ?></h1>

		<div class="agent-ready-wizard">
			<p class="agent-ready-wizard-intro">
				<?php esc_html_e( 'One-time setup. The toggles below are pre-selected based on your environment — review, adjust, then apply. You can change anything later from the regular settings page.', 'agent-ready' ); ?>
			</p>

			<?php if ( $seo_plugin !== null ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						printf(
							/* translators: %s: detected SEO plugin name. */
							esc_html__( 'Detected %s — JSON-LD Schema is unchecked below to avoid duplicate structured data. Our schema auto-suppresses anyway when an SEO plugin is active.', 'agent-ready' ),
							'<strong>' . esc_html( $seo_plugin ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="agent_ready_apply_wizard" />
				<?php wp_nonce_field( 'agent_ready_apply_wizard' ); ?>

				<?php foreach ( $rows as $row ) : ?>
					<?php
					list( $section, $label, $option, $description ) = $row;
					if ( $section !== $current_section ) {
						$current_section = $section;
						echo '<h2 class="agent-ready-wizard-section">' . esc_html( $section_titles[ $section ] ) . '</h2>';
					}
					$checked = ! empty( $rec[ $option ] );
					?>
					<label class="agent-ready-wizard-row">
						<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $checked ); ?> />
						<span class="agent-ready-wizard-label">
							<strong><?php echo esc_html( $label ); ?></strong>
							<span class="agent-ready-wizard-description"><?php echo esc_html( $description ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>

				<p class="agent-ready-wizard-actions">
					<button type="submit" name="agent_ready_wizard_action" value="apply" class="button button-primary button-large">
						<?php esc_html_e( 'Apply Recommended Settings', 'agent-ready' ); ?>
					</button>
					<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-large">
						<?php esc_html_e( 'Skip — Configure Manually', 'agent-ready' ); ?>
					</a>
				</p>
			</form>
		</div>
	</div>
	<?php
}
