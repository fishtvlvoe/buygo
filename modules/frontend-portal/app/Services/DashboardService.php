<?php
/**
 * DashboardService
 *
 * [AI Context]
 * - Handles dashboard data retrieval
 * - Uses ReportCacheService for caching
 * - Converts prices from "cents" (分) to "yuan" (元) for TWD
 *
 * [Constraints]
 * - Must use ReportCacheService for caching
 * - Cache is refreshed 5-6 times per day
 * - Must check user permissions using BuyGo RoleManager
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardService {

	/**
	 * ReportCacheService instance
	 */
	protected $cache_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cache_service = new ReportCacheService();
	}

	/**
	 * Convert price from cents to yuan for TWD
	 *
	 * @param float $price_in_cents Price in cents
	 * @param string $currency Currency code (default: TWD)
	 * @return float Price in yuan
	 */
	protected function convert_price( $price_in_cents, $currency = 'TWD' ) {
		if ( $currency === 'TWD' ) {
			return (float) $price_in_cents / 100;
		}
		return (float) $price_in_cents;
	}

	/**
	 * Get dashboard data
	 *
	 * @param int $user_id User ID
	 * @return array Dashboard data
	 */
	public function getDashboardData( $user_id ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'today_orders' => 0,
				'today_revenue' => 0,
				'pending_orders' => 0,
				'listed_products' => 0,
			];
		}

		// Check cache first
		$cache_key = 'dashboard_data_' . $user_id;
		$cached_data = $this->cache_service->get( $cache_key );

		if ( $cached_data !== null ) {
			return $cached_data;
		}

		// Calculate data
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );

		$table_orders = $wpdb->prefix . 'fct_orders';
		$table_items = $wpdb->prefix . 'fct_order_items';
		$table_posts = $wpdb->posts;

		// Today's date range
		$today_start = date( 'Y-m-d 00:00:00' );
		$today_end = date( 'Y-m-d 23:59:59' );

		// Today orders
		$today_orders = 0;
		if ( $is_admin ) {
			$today_orders = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT o.id)
				FROM {$table_orders} o
				WHERE o.created_at >= %s AND o.created_at <= %s",
				$today_start,
				$today_end
			) );
		} elseif ( $is_seller ) {
			$today_orders = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT o.id)
				FROM {$table_orders} o
				INNER JOIN {$table_items} oi ON o.id = oi.order_id
				INNER JOIN {$table_posts} p ON oi.post_id = p.ID
				WHERE o.created_at >= %s AND o.created_at <= %s
				AND p.post_type = 'fluent-products'
				AND p.post_author = %d",
				$today_start,
				$today_end,
				$user_id
			) );
		}

		// Today revenue
		$today_revenue_in_cents = 0;
		if ( $is_admin ) {
			$today_revenue_in_cents = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(o.total_amount), 0)
				FROM {$table_orders} o
				WHERE o.created_at >= %s AND o.created_at <= %s
				AND o.payment_status = 'paid'",
				$today_start,
				$today_end
			) );
		} elseif ( $is_seller ) {
			$today_revenue_in_cents = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(o.total_amount), 0)
				FROM {$table_orders} o
				INNER JOIN {$table_items} oi ON o.id = oi.order_id
				INNER JOIN {$table_posts} p ON oi.post_id = p.ID
				WHERE o.created_at >= %s AND o.created_at <= %s
				AND o.payment_status = 'paid'
				AND p.post_type = 'fluent-products'
				AND p.post_author = %d",
				$today_start,
				$today_end,
				$user_id
			) );
		}

		// Convert revenue from cents to yuan
		$today_revenue = $this->convert_price( $today_revenue_in_cents, 'TWD' );

		// Pending orders
		$pending_orders = 0;
		if ( $is_admin ) {
			$pending_orders = $wpdb->get_var(
				"SELECT COUNT(DISTINCT o.id)
				FROM {$table_orders} o
				WHERE o.shipping_status = 'unshipped'"
			);
		} elseif ( $is_seller ) {
			$pending_orders = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT o.id)
				FROM {$table_orders} o
				INNER JOIN {$table_items} oi ON o.id = oi.order_id
				INNER JOIN {$table_posts} p ON oi.post_id = p.ID
				WHERE o.shipping_status = 'unshipped'
				AND p.post_type = 'fluent-products'
				AND p.post_author = %d",
				$user_id
			) );
		}

		// Listed products
		$listed_products = 0;
		if ( $is_admin ) {
			$listed_products = $wpdb->get_var(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'fluent-products'
				AND p.post_status = 'publish'"
			);
		} elseif ( $is_seller ) {
			$listed_products = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'fluent-products'
				AND p.post_status = 'publish'
				AND p.post_author = %d",
				$user_id
			) );
		}

		$data = [
			'today_orders' => (int) $today_orders,
			'today_revenue' => $today_revenue,
			'pending_orders' => (int) $pending_orders,
			'listed_products' => (int) $listed_products,
		];

		// Cache the data (4 hours)
		$this->cache_service->set( $cache_key, $data );

		return $data;
	}
}
