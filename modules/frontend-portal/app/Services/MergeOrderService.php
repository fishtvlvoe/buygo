<?php
/**
 * MergeOrderService
 *
 * [AI Context]
 * - Handles order merging functionality
 * - Creates merged orders from multiple FluentCart orders
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 *
 * [Constraints]
 * - Must use MergedOrder Model for database operations
 * - Must validate orders belong to the same customer
 * - Must convert prices from cents to yuan
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Modules\FrontendPortal\App\Models\MergedOrder;
use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MergeOrderService {

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
	 * Merge multiple orders
	 *
	 * @param int $user_id User ID (seller/admin)
	 * @param array $order_ids Array of order IDs to merge
	 * @param string $shipping_method Shipping method
	 * @return array|false Merged order data or false on failure
	 */
	public function mergeOrders( $user_id, $order_ids, $shipping_method = 'standard' ) {
		global $wpdb;

		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			return false;
		}

		$order_ids = array_map( 'absint', $order_ids );
		$order_ids = array_unique( $order_ids );

		if ( count( $order_ids ) < 2 ) {
			return false; // Need at least 2 orders to merge
		}

		$table_orders = $wpdb->prefix . 'fct_orders';
		$table_customers = $wpdb->prefix . 'fct_customers';

		// Get orders
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$orders = $wpdb->get_results( $wpdb->prepare(
			"SELECT o.*, c.first_name, c.last_name, c.email, c.contact_id as user_id
			FROM {$table_orders} o
			LEFT JOIN {$table_customers} c ON o.customer_id = c.id
			WHERE o.id IN ($placeholders)",
			...$order_ids
		) );

		if ( empty( $orders ) || count( $orders ) !== count( $order_ids ) ) {
			return false; // Some orders not found
		}

		// Validate all orders belong to the same customer
		$customer_ids = array_unique( array_filter( array_column( $orders, 'customer_id' ) ) );
		if ( count( $customer_ids ) !== 1 ) {
			return false; // Orders must belong to the same customer
		}

		$customer_id = $customer_ids[0];
		$first_order = $orders[0];

		// Calculate total amount (convert from cents to yuan)
		$total_amount_in_cents = 0;
		$currency = 'TWD';
		foreach ( $orders as $order ) {
			$total_amount_in_cents += (float) $order->total_amount;
			if ( ! empty( $order->currency ) ) {
				$currency = $order->currency;
			}
		}

		$total_amount = $this->convert_price( $total_amount_in_cents, $currency );

		// Determine seller ID (from first order's products)
		$table_items = $wpdb->prefix . 'fct_order_items';
		$table_posts = $wpdb->posts;

		$seller_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.post_author
			FROM {$table_items} oi
			INNER JOIN {$table_posts} p ON oi.post_id = p.ID
			WHERE oi.order_id = %d
			AND p.post_type = 'fluent-products'
			LIMIT 1",
			$order_ids[0]
		) );

		if ( ! $seller_id ) {
			$seller_id = $user_id; // Fallback to current user
		}

		// Create merged order
		$merged_order_data = [
			'merged_order_id' => null, // Will be set later if needed
			'original_order_ids' => $order_ids,
			'customer_id' => $customer_id,
			'seller_id' => $seller_id,
			'shipping_method' => sanitize_text_field( $shipping_method ),
			'shipping_status' => 'unshipped',
			'total_amount' => $total_amount,
			'currency' => $currency,
		];

		$merged_order_id = MergedOrder::create( $merged_order_data );

		if ( ! $merged_order_id ) {
			return false;
		}

		// Get customer info
		$customer_info = $this->getCustomerInfo( $customer_id );

		return [
			'merged_order_id' => $merged_order_id,
			'customer_info' => $customer_info,
			'shipping_method' => $shipping_method,
			'merged_orders' => $order_ids,
			'total_amount' => $total_amount,
			'currency' => $currency,
		];
	}

	/**
	 * Get merged order
	 *
	 * @param int $merged_order_id Merged order ID
	 * @return object|null Merged order object or null if not found
	 */
	public function getMergedOrder( $merged_order_id ) {
		return MergedOrder::find( $merged_order_id );
	}

	/**
	 * Get customer info
	 *
	 * @param int $customer_id Customer ID
	 * @return array Customer info
	 */
	public function getCustomerInfo( $customer_id ) {
		global $wpdb;

		$table_customers = $wpdb->prefix . 'fct_customers';

		$customer = $wpdb->get_row( $wpdb->prepare(
			"SELECT first_name, last_name, email, phone, address_1, address_2, city, state, postcode, country
			FROM {$table_customers}
			WHERE id = %d",
			$customer_id
		) );

		if ( ! $customer ) {
			return [
				'name' => '',
				'phone' => '',
				'email' => '',
				'address' => '',
			];
		}

		return [
			'name' => trim( ( $customer->first_name ?? '' ) . ' ' . ( $customer->last_name ?? '' ) ),
			'phone' => $customer->phone ?? '',
			'email' => $customer->email ?? '',
			'address' => trim( ( $customer->address_1 ?? '' ) . ' ' . ( $customer->address_2 ?? '' ) ),
			'city' => $customer->city ?? '',
			'state' => $customer->state ?? '',
			'postcode' => $customer->postcode ?? '',
			'country' => $customer->country ?? '',
		];
	}

	/**
	 * Unmerge a merged order (split it back into individual orders)
	 *
	 * @param int $user_id User ID
	 * @param int $merged_order_id Merged order ID
	 * @return array|false Original order IDs or false on failure
	 */
	public function unmergeOrder( $user_id, $merged_order_id ) {
		global $wpdb;

		// Get merged order
		$merged_order = MergedOrder::find( $merged_order_id );
		if ( ! $merged_order ) {
			return false;
		}

		// Check permission (admin or seller who owns the merged order)
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = $role_manager->user_has_role( $user_id, 'buygo_admin' ) || current_user_can( 'manage_options' );
		$is_seller = $role_manager->user_has_role( $user_id, 'buygo_seller' );

		if ( ! $is_admin && ( ! $is_seller || (int) $merged_order->seller_id !== (int) $user_id ) ) {
			return false; // No permission
		}

		// Get original order IDs (stored as JSON)
		$original_order_ids = json_decode( $merged_order->original_order_ids ?? '[]', true );
		if ( empty( $original_order_ids ) || ! is_array( $original_order_ids ) ) {
			return false;
		}

		// Delete merged order record
		$table_merged = $wpdb->prefix . 'buygo_merged_orders';
		$result = $wpdb->delete(
			$table_merged,
			[ 'id' => $merged_order_id ],
			[ '%d' ]
		);

		if ( $result === false ) {
			return false;
		}

		return [
			'original_order_ids' => $original_order_ids,
			'message' => 'Merged order unmerged successfully',
		];
	}
}
