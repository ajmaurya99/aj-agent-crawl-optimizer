<?php
/**
 * Admin: plugin lifecycle hooks — activation notice + plugins-row Settings link.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( AGENT_READY_FILE, __NAMESPACE__ . '\\on_activation' );
add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_show_activation_notice' );
add_filter( 'plugin_action_links_' . plugin_basename( AGENT_READY_FILE ), __NAMESPACE__ . '\\plugin_action_links' );

/**
 * Set a short-lived flag on activation so the next admin pageview shows the
 * "configure your settings" notice. Uses a 60-second transient — long enough
 * to survive the activation redirect, short enough that it never lingers.
 *
 * @return void
 */
function on_activation(): void {
	set_transient( 'agent_ready_activation_notice', 1, 60 );

	// Trigger the one-time Quick Setup wizard on the next visit to the
	// settings page. 5-minute window is enough for the user to navigate over;
	// after that the wizard quietly drops away and the normal page renders.
	set_transient( WIZARD_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

	// Flush cached endpoint outputs so the first request after (re)activation
	// always reflects the current plugin code, not a stale pre-update body.
	delete_transient( 'agent_ready_openapi_cache' );
	delete_transient( 'agent_ready_llms_txt_cache' );
}

/**
 * Render the one-shot activation notice if the flag is set.
 *
 * @return void
 */
function maybe_show_activation_notice(): void {
	if ( ! get_transient( 'agent_ready_activation_notice' ) ) {
		return;
	}

	// Only show it to users who can actually configure the plugin.
	if ( ! current_user_can( required_capability() ) ) {
		return;
	}

	// Don't double-show if the user is already on the settings page.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->id === 'settings_page_agent-ready' ) {
		delete_transient( 'agent_ready_activation_notice' );
		return;
	}

	// Consume the flag so the notice doesn't repeat.
	delete_transient( 'agent_ready_activation_notice' );

	$settings_url = admin_url( 'options-general.php?page=agent-ready' );
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Agent-Ready is active.', 'agent-ready' ); ?></strong>
			<?php esc_html_e( 'All features start disabled — pick which capabilities to expose to AI agents.', 'agent-ready' ); ?>
			<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary" style="margin-left: 8px;">
				<?php esc_html_e( 'Configure Agent-Ready', 'agent-ready' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Add a Settings link to the plugin's row on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=agent-ready' ) ),
		esc_html__( 'Settings', 'agent-ready' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
