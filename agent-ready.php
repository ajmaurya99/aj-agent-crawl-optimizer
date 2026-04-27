<?php
/**
 * Plugin Name:       Agent-Ready
 * Plugin URI:        https://github.com/ajmaurya99/agent-ready
 * Description:       Make your WordPress site discoverable to AI agents and crawlers — Markdown negotiation, JSON-LD, OpenAPI, MCP server card, agent-skills index, IndexNow, llms.txt, and Content-Signals — each as a separate toggle.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Author:            Ajay Maurya
 * Author URI:        https://x.com/aalootechie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agent-ready
 * Domain Path:       /languages
 *
 * @package AgentReady
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENT_READY_VERSION', '1.0.0' );
define( 'AGENT_READY_FILE', __FILE__ );
define( 'AGENT_READY_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENT_READY_URL', plugin_dir_url( __FILE__ ) );

// Shared helpers.
require_once AGENT_READY_DIR . 'includes/helpers.php';

// Feature handlers — each file registers its own hooks and gates on its toggle.
require_once AGENT_READY_DIR . 'includes/features/markdown-negotiation.php';
require_once AGENT_READY_DIR . 'includes/features/content-signals.php';
require_once AGENT_READY_DIR . 'includes/features/api-catalog.php';
require_once AGENT_READY_DIR . 'includes/features/mcp-server-card.php';
require_once AGENT_READY_DIR . 'includes/features/agent-skills-index.php';
require_once AGENT_READY_DIR . 'includes/features/webmcp-tools.php';
require_once AGENT_READY_DIR . 'includes/features/json-ld-schema.php';
require_once AGENT_READY_DIR . 'includes/features/openapi-spec.php';
require_once AGENT_READY_DIR . 'includes/features/indexnow.php';
require_once AGENT_READY_DIR . 'includes/features/llms-txt.php';

// Admin: menu, settings registration, settings page renderer, asset enqueue, help tabs, activation, reset handler, wizard.
require_once AGENT_READY_DIR . 'includes/admin/menu.php';
require_once AGENT_READY_DIR . 'includes/admin/register-settings.php';
require_once AGENT_READY_DIR . 'includes/admin/settings-page.php';
require_once AGENT_READY_DIR . 'includes/admin/enqueue.php';
require_once AGENT_READY_DIR . 'includes/admin/help-tabs.php';
require_once AGENT_READY_DIR . 'includes/admin/activation.php';
require_once AGENT_READY_DIR . 'includes/admin/reset.php';
require_once AGENT_READY_DIR . 'includes/admin/wizard.php';

/**
 * Load the plugin's translation files.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'agent-ready', false, dirname( plugin_basename( AGENT_READY_FILE ) ) . '/languages' );
	}
);
