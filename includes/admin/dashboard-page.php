<?php
/**
 * Admin: Agent Ready dashboard shell.
 *
 * Renders the mount point for the scanner app (assets/js/dashboard.js). All
 * data arrives via wp_localize_script in enqueue.php; all interaction goes
 * through the ajaco/v1 REST API.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the dashboard page.
 *
 * @return void
 */
function render_dashboard_page(): void {
	if ( ! current_user_can( required_capability() ) ) {
		return;
	}
	?>
	<div class="wrap ajaco-dash-wrap">
		<h1 class="screen-reader-text"><?php esc_html_e( 'Agent Ready Dashboard', 'aj-agent-crawl-optimizer' ); ?></h1>
		<div id="ajaco-dashboard" class="ajaco-dash">
			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'The Agent Ready dashboard requires JavaScript. You can still manage individual features under Agent Ready → Settings, or run scans with: wp agent-ready scan', 'aj-agent-crawl-optimizer' ); ?></p>
				</div>
			</noscript>
		</div>
	</div>
	<?php
}
