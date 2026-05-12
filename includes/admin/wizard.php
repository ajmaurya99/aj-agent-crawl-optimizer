<?php
/**
 * Admin: first-activation Quick Setup wizard.
 *
 * Rendered on the settings page in place of the normal UI when a one-shot
 * transient is set. The user applies recommended defaults (environment-aware:
 * skips JSON-LD if an SEO plugin is detected) or skips to manual configuration.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WIZARD_TRANSIENT    = 'ajaco_show_wizard';
const WIZARD_APPLIED_FLAG = 'ajaco_wizard_applied';

add_action( 'admin_post_ajaco_apply_wizard', __NAMESPACE__ . '\\handle_wizard_submit' );
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
		'ajaco_api_catalog_enabled'        => true,
		'ajaco_mcp_server_card_enabled'    => true,
		'ajaco_agent_skills_index_enabled' => true,
		'ajaco_llms_txt_enabled'           => true,
		// IndexNow off — requires API key + production environment.
		'ajaco_indexnow_enabled'           => false,
		// Presentation — on by default; JSON-LD off when an SEO plugin is active
		// (auto-suppression would no-op anyway, but turning it off makes the UI honest).
		'ajaco_markdown_enabled'           => true,
		'ajaco_json_ld_enabled'            => ! $seo_active,
		'ajaco_openapi_enabled'            => true,
		'ajaco_webmcp_enabled'             => true,
		// Declarations — on.
		'ajaco_content_signals_enabled'    => true,
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
			esc_html__( 'You do not have permission to run the AJ Agent Crawl Optimizer setup wizard.', 'aj-agent-crawl-optimizer' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'ajaco_apply_wizard' );

	$action = isset( $_POST['ajaco_wizard_action'] )
		? sanitize_text_field( wp_unslash( $_POST['ajaco_wizard_action'] ) )
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

	wp_safe_redirect( admin_url( 'options-general.php?page=aj-agent-crawl-optimizer' ) );
	exit;
}

/**
 * Show a flash notice after the wizard's settings are applied.
 *
 * @return void
 */
function maybe_show_wizard_applied_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->id !== 'settings_page_aj-agent-crawl-optimizer' ) {
		return;
	}

	if ( ! get_transient( WIZARD_APPLIED_FLAG ) ) {
		return;
	}

	delete_transient( WIZARD_APPLIED_FLAG );
	?>
	<div class="notice notice-success settings-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Recommended AJ Agent Crawl Optimizer settings applied.', 'aj-agent-crawl-optimizer' ); ?></strong>
			<?php esc_html_e( 'Tweak any toggle below if you want to deviate from the defaults.', 'aj-agent-crawl-optimizer' ); ?>
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
				'action'                    => 'ajaco_apply_wizard',
				'ajaco_wizard_action' => 'skip',
			),
			admin_url( 'admin-post.php' )
		),
		'ajaco_apply_wizard'
	);

	$rows = array(
		// label, option name, recommended-by-default, description.
		array( 'discovery', __( 'API Catalog', 'aj-agent-crawl-optimizer' ), 'ajaco_api_catalog_enabled', __( '/.well-known/api-catalog manifest plus a Link header on every response (RFC 9727).', 'aj-agent-crawl-optimizer' ) ),
		array( 'discovery', __( 'MCP Server Card', 'aj-agent-crawl-optimizer' ), 'ajaco_mcp_server_card_enabled', __( '/.well-known/mcp/server-card.json descriptor for MCP-aware agents.', 'aj-agent-crawl-optimizer' ) ),
		array( 'discovery', __( 'Agent Skills Index', 'aj-agent-crawl-optimizer' ), 'ajaco_agent_skills_index_enabled', __( '/.well-known/agent-skills/index.json plus per-skill SKILL.md artifacts.', 'aj-agent-crawl-optimizer' ) ),
		array( 'discovery', __( 'llms.txt', 'aj-agent-crawl-optimizer' ), 'ajaco_llms_txt_enabled', __( 'A curated, LLM-readable index at /llms.txt (per llmstxt.org).', 'aj-agent-crawl-optimizer' ) ),
		array( 'discovery', __( 'IndexNow', 'aj-agent-crawl-optimizer' ), 'ajaco_indexnow_enabled', __( 'Pings Bing/Yandex on publish. Requires a key from bing.com/webmasters/indexnow — leave off for now and configure later.', 'aj-agent-crawl-optimizer' ) ),
		array( 'presentation', __( 'Markdown Negotiation', 'aj-agent-crawl-optimizer' ), 'ajaco_markdown_enabled', __( 'Returns clean Markdown when an agent sends Accept: text/markdown. Browsers are unaffected.', 'aj-agent-crawl-optimizer' ) ),
		array( 'presentation', __( 'JSON-LD Schema', 'aj-agent-crawl-optimizer' ), 'ajaco_json_ld_enabled', __( 'WebSite, Organization, Article, BreadcrumbList, FAQPage structured data.', 'aj-agent-crawl-optimizer' ) ),
		array( 'presentation', __( 'OpenAPI Spec', 'aj-agent-crawl-optimizer' ), 'ajaco_openapi_enabled', __( 'OpenAPI 3.0.3 document at /?format=openapi, generated from REST routes.', 'aj-agent-crawl-optimizer' ) ),
		array( 'presentation', __( 'WebMCP Tools', 'aj-agent-crawl-optimizer' ), 'ajaco_webmcp_enabled', __( 'Frontend script registering tools via navigator.modelContext (Chrome experimental).', 'aj-agent-crawl-optimizer' ) ),
		array( 'declarations', __( 'Content-Signals', 'aj-agent-crawl-optimizer' ), 'ajaco_content_signals_enabled', __( 'Adds a Content-Signal directive to robots.txt declaring AI-usage preferences.', 'aj-agent-crawl-optimizer' ) ),
	);

	$section_titles = array(
		'discovery'    => __( 'Discovery', 'aj-agent-crawl-optimizer' ),
		'presentation' => __( 'Presentation', 'aj-agent-crawl-optimizer' ),
		'declarations' => __( 'Declarations', 'aj-agent-crawl-optimizer' ),
	);

	$current_section = '';
	?>
	<div class="wrap ajaco-wizard-wrap">
		<h1><?php esc_html_e( 'Welcome to AJ Agent Crawl Optimizer', 'aj-agent-crawl-optimizer' ); ?></h1>

		<div class="ajaco-wizard">
			<p class="ajaco-wizard-intro">
				<?php esc_html_e( 'One-time setup. The toggles below are pre-selected based on your environment — review, adjust, then apply. You can change anything later from the regular settings page.', 'aj-agent-crawl-optimizer' ); ?>
			</p>

			<?php if ( $seo_plugin !== null ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						printf(
							/* translators: %s: detected SEO plugin name. */
							esc_html__( 'Detected %s — JSON-LD Schema is unchecked below to avoid duplicate structured data. Our schema auto-suppresses anyway when an SEO plugin is active.', 'aj-agent-crawl-optimizer' ),
							'<strong>' . esc_html( $seo_plugin ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ajaco_apply_wizard" />
				<?php wp_nonce_field( 'ajaco_apply_wizard' ); ?>

				<?php foreach ( $rows as $row ) : ?>
					<?php
					list( $section, $label, $option, $description ) = $row;
					if ( $section !== $current_section ) {
						$current_section = $section;
						echo '<h2 class="ajaco-wizard-section">' . esc_html( $section_titles[ $section ] ) . '</h2>';
					}
					$checked = ! empty( $rec[ $option ] );
					?>
					<label class="ajaco-wizard-row">
						<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $checked ); ?> />
						<span class="ajaco-wizard-label">
							<strong><?php echo esc_html( $label ); ?></strong>
							<span class="ajaco-wizard-description"><?php echo esc_html( $description ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>

				<p class="ajaco-wizard-actions">
					<button type="submit" name="ajaco_wizard_action" value="apply" class="button button-primary button-large">
						<?php esc_html_e( 'Apply Recommended Settings', 'aj-agent-crawl-optimizer' ); ?>
					</button>
					<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-large">
						<?php esc_html_e( 'Skip — Configure Manually', 'aj-agent-crawl-optimizer' ); ?>
					</a>
				</p>
			</form>
		</div>
	</div>
	<?php
}
