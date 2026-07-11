<?php
/**
 * Plugin Name:       AJ Agent Crawl Optimizer
 * Plugin URI:        https://github.com/ajmaurya99/aj-agent-crawl-optimizer
 * Description:       The agent-readiness scanner and fixer for WordPress — run the 21-check readiness scan (Level 0-5) with evidence, fix failures in one click, and publish Markdown negotiation, llms.txt, MCP server card, agent skills, AI bot rules, and more.
 * Version:           2.0.0-alpha
 * Requires at least: 5.5
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            Ajay Maurya
 * Author URI:        https://x.com/aalootechie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aj-agent-crawl-optimizer
 * Domain Path:       /languages
 *
 * @package Ajaco
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AJACO_VERSION', '2.0.0-alpha' );
define( 'AJACO_FILE', __FILE__ );
define( 'AJACO_DIR', plugin_dir_path( __FILE__ ) );
define( 'AJACO_URL', plugin_dir_url( __FILE__ ) );

// Shared helpers.
require_once AJACO_DIR . 'includes/helpers.php';

// Feature handlers — each file registers its own hooks and gates on its toggle.
require_once AJACO_DIR . 'includes/features/markdown-negotiation.php';
require_once AJACO_DIR . 'includes/features/content-signals.php';
require_once AJACO_DIR . 'includes/features/api-catalog.php';
require_once AJACO_DIR . 'includes/features/mcp-server-card.php';
require_once AJACO_DIR . 'includes/features/agent-skills-index.php';
require_once AJACO_DIR . 'includes/features/webmcp-tools.php';
require_once AJACO_DIR . 'includes/features/json-ld-schema.php';
require_once AJACO_DIR . 'includes/features/openapi-spec.php';
require_once AJACO_DIR . 'includes/features/indexnow.php';
require_once AJACO_DIR . 'includes/features/llms-txt.php';
require_once AJACO_DIR . 'includes/features/ai-bot-rules.php';
require_once AJACO_DIR . 'includes/features/auth-md.php';

// Scan engine — self-scan with evidence, Level 0-5 ladder, one-click fixes.
require_once AJACO_DIR . 'includes/scan/class-check-result.php';
require_once AJACO_DIR . 'includes/scan/class-evidence.php';
require_once AJACO_DIR . 'includes/scan/class-check.php';
require_once AJACO_DIR . 'includes/scan/class-check-info.php';
require_once AJACO_DIR . 'includes/scan/class-level.php';
require_once AJACO_DIR . 'includes/scan/class-scanner.php';
require_once AJACO_DIR . 'includes/scan/class-fix-registry.php';
require_once AJACO_DIR . 'includes/scan/class-hosting-diagnosis.php';
$ajaco_check_files = glob( AJACO_DIR . 'includes/scan/checks/class-check-*.php' );
if ( is_array( $ajaco_check_files ) ) {
	foreach ( $ajaco_check_files as $ajaco_check_file ) {
		require_once $ajaco_check_file;
	}
}
unset( $ajaco_check_files, $ajaco_check_file );

// REST API (ajaco/v1): scan, fix, health.
require_once AJACO_DIR . 'includes/rest/class-scan-controller.php';

// WP-CLI: wp agent-ready scan|status|fix (no-ops outside WP-CLI).
require_once AJACO_DIR . 'includes/cli.php';

// Admin: menu, settings registration, settings + dashboard renderers, asset enqueue, help tabs, activation, reset handler, wizard.
require_once AJACO_DIR . 'includes/admin/menu.php';
require_once AJACO_DIR . 'includes/admin/register-settings.php';
require_once AJACO_DIR . 'includes/admin/settings-page.php';
require_once AJACO_DIR . 'includes/admin/dashboard-page.php';
require_once AJACO_DIR . 'includes/admin/enqueue.php';
require_once AJACO_DIR . 'includes/admin/help-tabs.php';
require_once AJACO_DIR . 'includes/admin/activation.php';
require_once AJACO_DIR . 'includes/admin/reset.php';
require_once AJACO_DIR . 'includes/admin/wizard.php';
