<?php
/**
 * Lumination AI Summarizer
 *
 * AI-powered content summarization for WordPress. Supports multiple input
 * modes (URL, text, PDF) and output formats (sectioned summary, raw
 * summary, mindmap). Configurable via shortcode presets for SEO pages.
 *
 * Requires Lumination Core (v1.0.0+) for API access and analytics.
 *
 * @package           LuminationSummarizer
 * @author            Lumination Team
 * @license           GPL-3.0-or-later
 * @link              https://lumination.ai
 * @copyright         2026 Lumination Team
 *
 * @wordpress-plugin
 * Plugin Name:       Lumination AI Summarizer
 * Description:       AI-powered content summarization with support for URLs, text, and PDFs. Generate structured summaries or visual mindmaps. Requires Lumination Core.
 * Version:           1.0.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Lumination Team
 * Author URI:        https://lumination.ai
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       lumination-summarizer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────

define( 'LUMINATION_SUMMARIZER_VERSION', '1.0.3' );
define( 'LUMINATION_SUMMARIZER_FILE',    __FILE__ );
define( 'LUMINATION_SUMMARIZER_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LUMINATION_SUMMARIZER_URL',     plugin_dir_url( __FILE__ ) );

// ── Auto-update via GitHub releases ──────────────────────────────────────────

require_once LUMINATION_SUMMARIZER_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/Borjablm/lumination-summarizer/',
	__FILE__,
	'lumination-summarizer'
);

// ── Dependency check + initialisation ────────────────────────────────────────

add_action(
	'plugins_loaded',
	function () {
		$core_ok = function_exists( 'lumination_core' )
				&& defined( 'LUMINATION_CORE_VERSION' )
				&& version_compare( LUMINATION_CORE_VERSION, '1.0.0', '>=' );

		if ( ! $core_ok ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					$msg = sprintf(
						wp_kses(
							/* translators: %s: URL to Plugins admin page */
							__( '<strong>Lumination AI Summarizer</strong> requires <strong>Lumination Core</strong> (v1.0.0+) to be installed and active. <a href="%s">Manage plugins &rarr;</a>', 'lumination-summarizer' ),
							array(
								'strong' => array(),
								'a'      => array( 'href' => array() ),
							)
						),
						esc_url( admin_url( 'plugins.php' ) )
					);
					echo '<div class="notice notice-error is-dismissible"><p>' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			);
			return;
		}

		// Core confirmed — load classes and register hooks.
		require_once LUMINATION_SUMMARIZER_DIR . 'includes/class-summarizer-settings.php';
		require_once LUMINATION_SUMMARIZER_DIR . 'includes/class-summarizer-ajax.php';
		require_once LUMINATION_SUMMARIZER_DIR . 'includes/class-summarizer.php';

		// Register settings on Core's hook.
		add_action( 'lumination_core_settings_init', array( 'Lumination_Summarizer_Settings', 'register_settings' ) );

		// Register admin tab in Core's panel.
		add_action(
			'lumination_core_admin_tabs_init',
			function () {
				Lumination_Core_Settings::register_tab(
					array(
						'id'       => 'summarizer',
						'label'    => __( 'Summarizer', 'lumination-summarizer' ),
						'callback' => array( 'Lumination_Summarizer_Settings', 'render_tab' ),
						'priority' => 30,
					)
				);
			}
		);

		// Initialise shortcode and AJAX.
		Lumination_Summarizer::init();
	},
	20 // Priority 20 — after Core (10).
);
