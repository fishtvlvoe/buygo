<?php
/**
 * CacheScheduler
 *
 * [AI Context]
 * - Handles scheduled cache refresh for dashboard data
 * - Cache is refreshed 5-6 times per day (every 4 hours)
 * - Uses WordPress cron system
 *
 * [Constraints]
 * - Must use WordPress wp_schedule_event
 * - Must handle cache refresh for all users with dashboard access
 */

namespace BuyGo\Modules\FrontendPortal\App\Hooks;

use BuyGo\Modules\FrontendPortal\App\Services\DashboardService;
use BuyGo\Modules\FrontendPortal\App\Services\ReportCacheService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CacheScheduler {

	/**
	 * Register cron schedules and hooks
	 */
	public static function register() {
		// Add custom cron schedule (every 4 hours)
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedule' ] );

		// Register cron hook
		$hook_name = 'buygo_frontend_portal_refresh_cache';
		if ( ! wp_next_scheduled( $hook_name ) ) {
			wp_schedule_event( time(), 'buygo_four_hours', $hook_name );
		}

		// Add action handler
		add_action( $hook_name, [ __CLASS__, 'refresh_all_cache' ] );
	}

	/**
	 * Add custom cron schedule (every 4 hours)
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public static function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['buygo_four_hours'] ) ) {
			$schedules['buygo_four_hours'] = [
				'interval' => 14400, // 4 hours in seconds
				'display' => __( 'Every 4 Hours (BuyGo Frontend Portal)', 'buygo' ),
			];
		}
		return $schedules;
	}

	/**
	 * Refresh cache for all users
	 */
	public static function refresh_all_cache() {
		global $wpdb;

		// Get all users with dashboard access (sellers, admins, helpers)
		$users = $wpdb->get_col(
			"SELECT DISTINCT u.ID
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = '{$wpdb->prefix}capabilities'
			WHERE (
				um1.meta_value LIKE '%buygo_seller%' OR
				um1.meta_value LIKE '%buygo_admin%' OR
				um1.meta_value LIKE '%buygo_helper%' OR
				um1.meta_value LIKE '%administrator%'
			)"
		);

		if ( empty( $users ) ) {
			return;
		}

		$dashboard_service = new DashboardService();
		$cache_service = new ReportCacheService();

		// Refresh cache for each user
		foreach ( $users as $user_id ) {
			$cache_key = 'dashboard_data_' . $user_id;

			// Delete existing cache
			$cache_service->delete( $cache_key );

			// Recalculate and cache
			try {
				$data = $dashboard_service->getDashboardData( $user_id );
				$cache_service->set( $cache_key, $data );
			} catch ( \Exception $e ) {
				// Log error but continue with other users
				error_log( 'BuyGo Frontend Portal: Failed to refresh cache for user ' . $user_id . ': ' . $e->getMessage() );
			}
		}

		// Clear expired cache
		$cache_service->clearExpired();
	}

	/**
	 * Unregister cron schedules and hooks (for deactivation)
	 */
	public static function unregister() {
		$hook_name = 'buygo_frontend_portal_refresh_cache';
		$timestamp = wp_next_scheduled( $hook_name );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook_name );
		}
	}
}
