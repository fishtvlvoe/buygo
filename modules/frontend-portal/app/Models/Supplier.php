<?php
/**
 * Supplier Model
 *
 * [AI Context]
 * - Represents a supplier that provides products to sellers
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

class Supplier {

	/**
	 * Table name
	 */
	protected static $table_name = 'buygo_suppliers';

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
		'seller_id',
		'name',
		'contact_name',
		'phone',
		'email',
		'line_id',
		'tax_id',
		'address',
		'bank_account',
		'bank_name',
		'bank_branch',
		'notes',
		'status',
	];

	/**
	 * Create a new supplier
	 *
	 * @param array $data Supplier data
	 * @return int|false Insert ID or false on failure
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = self::get_table_name();

		// Sanitize and prepare data
		$insert_data = [];
		foreach ( static::$fillable as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( in_array( $field, [ 'address', 'notes' ] ) ) {
					$insert_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} elseif ( $field === 'email' ) {
					$insert_data[ $field ] = sanitize_email( $data[ $field ] );
				} else {
					$insert_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		// Ensure seller_id is set (use current user if not provided)
		if ( ! isset( $insert_data['seller_id'] ) ) {
			$insert_data['seller_id'] = get_current_user_id();
		}

		// Ensure status is set
		if ( ! isset( $insert_data['status'] ) ) {
			$insert_data['status'] = 'active';
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
	 * Get supplier by ID
	 *
	 * @param int $id Supplier ID
	 * @return object|null Supplier object or null if not found
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = self::get_table_name();
		$id = absint( $id );

		$supplier = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );

		return $supplier ?: null;
	}

	/**
	 * Get suppliers by seller ID
	 *
	 * @param int $seller_id Seller ID
	 * @param array $args Query arguments
	 * @return array Array of supplier objects
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
			'status' => 'all', // 'all', 'active', 'inactive'
		];

		$args = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$where = [ $wpdb->prepare( 'seller_id = %d', $seller_id ) ];

		if ( $args['status'] !== 'all' ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'created_at DESC';
		}

		$suppliers = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
			$args['per_page'],
			$offset
		) );

		return $suppliers;
	}

	/**
	 * Update supplier
	 *
	 * @param int $id Supplier ID
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
				if ( in_array( $field, [ 'address', 'notes' ] ) ) {
					$update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} elseif ( $field === 'email' ) {
					$update_data[ $field ] = sanitize_email( $data[ $field ] );
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
	 * Delete supplier
	 *
	 * @param int $id Supplier ID
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
