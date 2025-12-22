<?php
/**
 * BuyGo Plus One Module Bootstrap
 *
 * [AI Context]
 * - This file allows the legacy "buygo-plus-one" codebase to run as a module inside the single "buygo" plugin.
 * - We intentionally DO NOT keep the original plugin header file to avoid WordPress showing a second plugin entry.
 *
 * [Constraints]
 * - Must not call register_activation_hook / register_deactivation_hook (only one plugin exists now).
 * - Must load after BuyGo Core (BuyGo\Core\App) so BuyGo_Core facade/services are available.
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 
// Constants used by the legacy Plus One codebase.
if ( ! defined( 'BUYGO_PLUS_ONE_VERSION' ) ) {
	define( 'BUYGO_PLUS_ONE_VERSION', '0.0.2 beta' );
}
 
if ( ! defined( 'BUYGO_PLUS_ONE_PATH' ) ) {
	define( 'BUYGO_PLUS_ONE_PATH', plugin_dir_path( __FILE__ ) );
}
 
if ( ! defined( 'BUYGO_PLUS_ONE_URL' ) ) {
	define( 'BUYGO_PLUS_ONE_URL', plugin_dir_url( __FILE__ ) );
}
 
// The legacy loader expects this constant to exist in a few places (mostly for plugin list hooks).
// As a module, it should never be used to deactivate/activate anything, but keeping it avoids notices.
if ( ! defined( 'BUYGO_PLUS_ONE_BASENAME' ) ) {
	define( 'BUYGO_PLUS_ONE_BASENAME', plugin_basename( __FILE__ ) );
}
 
/**
 * Boot the Plus One module.
 *
 * [AI Context]
 * - We boot on plugins_loaded AFTER core (core uses priority 0), so BuyGo_Core::settings() etc exist.
 * - If critical dependencies are missing, we show an admin notice and skip boot to avoid fatals.
 */
function buygo_plus_one_module_boot() {
	// #region agent log
	$log_data = [
		'sessionId' => 'debug-session',
		'runId' => 'run1',
		'hypothesisId' => 'A',
		'location' => 'bootstrap.php:44',
		'message' => 'Module boot started',
		'data' => [
			'FLUENT_COMMUNITY_VERSION' => defined('FLUENT_COMMUNITY_VERSION') ? constant('FLUENT_COMMUNITY_VERSION') : 'NOT_DEFINED',
			'FLUENT_COMMUNITY_PLUGIN_VERSION' => defined('FLUENT_COMMUNITY_PLUGIN_VERSION') ? constant('FLUENT_COMMUNITY_PLUGIN_VERSION') : 'NOT_DEFINED',
			'FLUENT_COMMUNITY_PLUGIN_URL' => defined('FLUENT_COMMUNITY_PLUGIN_URL') ? constant('FLUENT_COMMUNITY_PLUGIN_URL') : 'NOT_DEFINED',
			'FluentCommunity_App_exists' => class_exists('FluentCommunity\App') ? 'YES' : 'NO',
			'FluentCommunity_Helper_exists' => class_exists('FluentCommunity\App\Services\Helper') ? 'YES' : 'NO',
			'FluentCommunity_PortalHandler_exists' => class_exists('FluentCommunity\App\Hooks\Handlers\PortalHandler') ? 'YES' : 'NO',
		],
		'timestamp' => time() * 1000
	];
	file_put_contents('/Users/fishtv/Local Sites/buygo/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
	// #endregion

	// BuyGo Core must be available (Composer autoload + core App).
	if ( ! class_exists( '\BuyGo\Core\App' ) || ! class_exists( 'BuyGo_Core' ) ) {
		// #region agent log
		$log_data = [
			'sessionId' => 'debug-session',
			'runId' => 'run1',
			'hypothesisId' => 'B',
			'location' => 'bootstrap.php:47',
			'message' => 'BuyGo Core missing, exiting',
			'data' => [
				'BuyGo_Core_exists' => class_exists('BuyGo_Core') ? 'YES' : 'NO',
				'BuyGo_Core_App_exists' => class_exists('\BuyGo\Core\App') ? 'YES' : 'NO',
			],
			'timestamp' => time() * 1000
		];
		file_put_contents('/Users/fishtv/Local Sites/buygo/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
		// #endregion
		return;
	}

	// Soft dependency checks (avoid breaking wp-admin if missing).
	$missing = [];

	if ( ! class_exists( 'FluentCart\App\App' ) ) {
		$missing[] = 'FluentCart';
	}

	// FluentCommunity is optional in some flows, but Plus One uses it for community posting.
	// Check using the same method as other FluentCommunity addons (fca-content-manager, fca-events, etc.)
	$fluent_community_active = (
		defined( 'FLUENT_COMMUNITY_PLUGIN_VERSION' ) ||
		defined( 'FLUENT_COMMUNITY_PLUGIN_URL' ) ||
		class_exists( 'FluentCommunity\App\Services\Helper' ) ||
		class_exists( 'FluentCommunity\App\Hooks\Handlers\PortalHandler' )
	);

	// #region agent log
	$log_data = [
		'sessionId' => 'debug-session',
		'runId' => 'post-fix',
		'hypothesisId' => 'C',
		'location' => 'bootstrap.php:58',
		'message' => 'FluentCommunity check result',
		'data' => [
			'FLUENT_COMMUNITY_PLUGIN_VERSION' => defined('FLUENT_COMMUNITY_PLUGIN_VERSION'),
			'FLUENT_COMMUNITY_PLUGIN_URL' => defined('FLUENT_COMMUNITY_PLUGIN_URL'),
			'FluentCommunity_Helper' => class_exists('FluentCommunity\App\Services\Helper'),
			'FluentCommunity_PortalHandler' => class_exists('FluentCommunity\App\Hooks\Handlers\PortalHandler'),
			'check_result' => $fluent_community_active ? 'PASSED' : 'FAILED',
		],
		'timestamp' => time() * 1000
	];
	file_put_contents('/Users/fishtv/Local Sites/buygo/.cursor/debug.log', json_encode($log_data) . "\n", FILE_APPEND);
	// #endregion

	if ( ! $fluent_community_active ) {
		$missing[] = 'FluentCommunity';
	}
 
	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function() use ( $missing ) {
			$list = esc_html( implode( ', ', $missing ) );
			echo '<div class="notice notice-warning"><p>';
			echo 'BuyGo：Plus One 模組需要啟用 ' . $list . ' 才能完整運作。';
			echo '</p></div>';
		} );
 
		// Still allow the core plugin to work; skip Plus One.
		return;
	}
 
	$loader_file = BUYGO_PLUS_ONE_PATH . 'includes/class-buygo-plus-one-loader.php';
	if ( ! file_exists( $loader_file ) ) {
		return;
	}
 
	require_once $loader_file;
 
	$loader = new \BuyGo_Plus_One_Loader();
	$loader->run();
 
	// Fix: legacy cron hook mismatch (schedule the hook that Order_Manager actually listens to).
	if ( ! wp_next_scheduled( 'buygo_line_fc_check_expired_orders' ) ) {
		wp_schedule_event( time(), 'hourly', 'buygo_line_fc_check_expired_orders' );
	}
}
add_action( 'plugins_loaded', 'buygo_plus_one_module_boot', 1 );
 
