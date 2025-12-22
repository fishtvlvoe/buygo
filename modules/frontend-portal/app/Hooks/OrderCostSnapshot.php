<?php
/**
 * OrderCostSnapshot Hook Handler
 *
 * [AI Context]
 * - Captures product cost and supplier_id when an order is created
 * - Stores cost snapshot in order item meta to prevent historical data changes
 * - This ensures that even if product cost changes later, old orders remain accurate
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must sanitize all input data
 */

namespace BuyGo\Modules\FrontendPortal\App\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderCostSnapshot {

	/**
	 * Register hooks
	 */
	public static function register() {
		// Hook into FluentCart OrderCreated event
		// The event passes an array with 'order' key
		add_action( 'fluent_cart/order_created', [ __CLASS__, 'capture_order_costs_from_event' ], 10, 1 );
		
		// Fallback hooks
		add_action( 'fc_order_created', [ __CLASS__, 'capture_order_costs' ], 10, 1 );
		
		// Fallback: Hook into WordPress post creation if order is created as post
		add_action( 'save_post', [ __CLASS__, 'maybe_capture_order_costs' ], 10, 2 );
	}

	/**
	 * Capture order costs from FluentCart OrderCreated event
	 *
	 * @param \FluentCart\App\Events\Order\OrderCreated|array|object $event Event object or data
	 */
	public static function capture_order_costs_from_event( $event ) {
		// Handle FluentCart OrderCreated event object
		if ( is_object( $event ) && isset( $event->order ) ) {
			$order = $event->order;
			if ( is_object( $order ) && isset( $order->id ) ) {
				self::capture_order_costs( $order->id );
				return;
			}
		}
		
		// Handle array structure
		if ( is_array( $event ) && isset( $event['order'] ) ) {
			$order = $event['order'];
			if ( is_object( $order ) && isset( $order->id ) ) {
				self::capture_order_costs( $order->id );
				return;
			}
		}
		
		// Fallback to direct order ID
		self::capture_order_costs( $event );
	}

	/**
	 * Capture order costs when order is created
	 *
	 * @param int|object $order_id Order ID or Order object
	 */
	public static function capture_order_costs( $order_id ) {
		global $wpdb;

		// Get order ID if object is passed
		if ( is_object( $order_id ) ) {
			$order_id = isset( $order_id->id ) ? $order_id->id : ( isset( $order_id->ID ) ? $order_id->ID : 0 );
		}

		if ( ! $order_id ) {
			return;
		}

		// Get order items from FluentCart
		$order_items_table = $wpdb->prefix . 'fc_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'fc_order_itemmeta';

		$order_items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$order_items_table} WHERE order_id = %d",
			$order_id
		) );

		if ( empty( $order_items ) ) {
			return;
		}

		// Process each order item
		foreach ( $order_items as $item ) {
			$product_id = $item->product_id;
			$variation_id = $item->variation_id ?: 0;

			// Get supplier_id from product meta
			$supplier_id = get_post_meta( $product_id, '_buygo_supplier_id', true );
			if ( ! $supplier_id ) {
				continue; // Skip if no supplier assigned
			}

			// Get cost price from product meta
			$cost_price = get_post_meta( $product_id, '_buygo_cost_price', true );
			if ( ! $cost_price ) {
				// Try to get from FluentCart product meta if exists
				$cost_price = get_post_meta( $product_id, '_cost', true );
			}

			if ( ! $cost_price ) {
				continue; // Skip if no cost price
			}

			// Check if meta already exists to avoid duplicates
			$existing_supplier = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_id FROM {$order_itemmeta_table} WHERE order_item_id = %d AND meta_key = '_buygo_supplier_id'",
				$item->id
			) );

			if ( ! $existing_supplier ) {
				// Store supplier_id in order item meta
				$wpdb->insert(
					$order_itemmeta_table,
					[
						'order_item_id' => $item->id,
						'meta_key' => '_buygo_supplier_id',
						'meta_value' => sanitize_text_field( $supplier_id ),
					],
					[ '%d', '%s', '%s' ]
				);
			} else {
				// Update existing meta
				$wpdb->update(
					$order_itemmeta_table,
					[ 'meta_value' => sanitize_text_field( $supplier_id ) ],
					[ 'order_item_id' => $item->id, 'meta_key' => '_buygo_supplier_id' ],
					[ '%s' ],
					[ '%d', '%s' ]
				);
			}

			// Check if cost snapshot already exists
			$existing_cost = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_id FROM {$order_itemmeta_table} WHERE order_item_id = %d AND meta_key = '_buygo_cost_snapshot'",
				$item->id
			) );

			if ( ! $existing_cost ) {
				// Store cost snapshot in order item meta
				$wpdb->insert(
					$order_itemmeta_table,
					[
						'order_item_id' => $item->id,
						'meta_key' => '_buygo_cost_snapshot',
						'meta_value' => sanitize_text_field( $cost_price ),
					],
					[ '%d', '%s', '%s' ]
				);
			} else {
				// Update existing cost snapshot
				$wpdb->update(
					$order_itemmeta_table,
					[ 'meta_value' => sanitize_text_field( $cost_price ) ],
					[ 'order_item_id' => $item->id, 'meta_key' => '_buygo_cost_snapshot' ],
					[ '%s' ],
					[ '%d', '%s' ]
				);
			}
		}
	}

	/**
	 * Maybe capture order costs (fallback for save_post hook)
	 *
	 * @param int $post_id Post ID
	 * @param object $post Post object
	 */
	public static function maybe_capture_order_costs( $post_id, $post ) {
		// Only process if this is a FluentCart order
		if ( $post->post_type !== 'fc_order' ) {
			return;
		}

		// Only process on creation (not update)
		if ( get_post_meta( $post_id, '_buygo_costs_captured', true ) ) {
			return;
		}

		self::capture_order_costs( $post_id );

		// Mark as captured
		update_post_meta( $post_id, '_buygo_costs_captured', true );
	}
}
