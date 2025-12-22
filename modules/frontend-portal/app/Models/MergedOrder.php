<?php
/**
 * MergedOrder Model
 *
 * [AI Context]
 * - Represents a merged order that combines multiple FluentCart orders
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must sanitize all input data
 */

namespace BuyGo\Modules\FrontendPortal\App\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MergedOrder {

	/**
	 * Table name
	 */
	protected static $table_name = 'buygo_merged_orders';

	/**
	 * Get table name with prefix
	 */
	protected static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . static::$table_name;
	}

	/**
	 * Fillable fields
	 */
	protected static $fillable = [
		'merged_order_id',
		'original_order_ids',
		'customer_id',
		'seller_id',
		'shipping_method',
		'shipping_status',
		'total_amount',
		'currency',
	];

	/**
	 * Create a new merged order
	 *
	 * @param array $data Order data
	 * @return int|false Insert ID or false on failure
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = self::get_table_name();

		// Sanitize and prepare data
		$insert_data = [];
		foreach ( static::$fillable as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $field === 'original_order_ids' ) {
					// Convert array to JSON
					$insert_data[ $field ] = json_encode( $data[ $field ] );
				} else {
					$insert_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		// Ensure currency is set (default to TWD)
		if ( ! isset( $insert_data['currency'] ) ) {
			$insert_data['currency'] = 'TWD';
		}

		// Set timestamps
		$current_time = current_time( 'mysql' );
		$insert_data['created_at'] = $current_time;
		$insert_data['updated_at'] = $current_time;

		$result = $wpdb->insert( $table, $insert_data );

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get merged order by ID
	 *
	 * @param int $id Merged order ID
	 * @return object|null Order object or null if not found
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = self::get_table_name();
		$id = absint( $id );

		$order = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );

		if ( ! $order ) {
			return null;
		}

		// Decode JSON fields
		$order->original_order_ids = json_decode( $order->original_order_ids, true );

		return $order;
	}

	/**
	 * Get merged order by merged_order_id
	 *
	 * @param int $merged_order_id FluentCart order ID
	 * @return object|null Order object or null if not found
	 */
	public static function findByMergedOrderId( $merged_order_id ) {
		global $wpdb;

		$table = self::get_table_name();
		$merged_order_id = absint( $merged_order_id );

		$order = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE merged_order_id = %d",
			$merged_order_id
		) );

		if ( ! $order ) {
			return null;
		}

		// Decode JSON fields
		$order->original_order_ids = json_decode( $order->original_order_ids, true );

		return $order;
	}

	/**
	 * Get merged orders by seller ID
	 *
	 * @param int $seller_id Seller ID
	 * @param array $args Query arguments
	 * @return array Array of order objects
	 */
	public static function getBySellerId( $seller_id, $args = [] ) {
		global $wpdb;

		$table = self::get_table_name();
		$seller_id = absint( $seller_id );

		$defaults = [
			'per_page' => 20,
			'page' => 1,
			'orderby' => 'created_at',
			'order' => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'created_at DESC';
		}

		$orders = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE seller_id = %d ORDER BY {$orderby} LIMIT %d OFFSET %d",
			$seller_id,
			$args['per_page'],
			$offset
		) );

		// Decode JSON fields
		foreach ( $orders as $order ) {
			$order->original_order_ids = json_decode( $order->original_order_ids, true );
		}

		return $orders;
	}

	/**
	 * Update merged order
	 *
	 * @param int $id Merged order ID
	 * @param array $data Update data
	 * @return bool True on success, false on failure
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$table = self::get_table_name();
		$id = absint( $id );

		// Sanitize and prepare data
		$update_data = [];
		foreach ( static::$fillable as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $field === 'original_order_ids' ) {
					// Convert array to JSON
					$update_data[ $field ] = json_encode( $data[ $field ] );
				} else {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// Update timestamp
		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			$update_data,
			[ 'id' => $id ],
			null,
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete merged order
	 *
	 * @param int $id Merged order ID
	 * @return bool True on success, false on failure
	 */
	public static function delete( $id ) {
		global $wpdb;

		$table = self::get_table_name();
		$id = absint( $id );

		$result = $wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return $result !== false;
	}
}
