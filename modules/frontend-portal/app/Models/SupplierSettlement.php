<?php
/**
 * SupplierSettlement Model
 *
 * [AI Context]
 * - Represents a settlement record between a seller and a supplier
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

class SupplierSettlement {

	/**
	 * Table name
	 */
	protected static $table_name = 'buygo_supplier_settlements';

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
		'supplier_id',
		'seller_id',
		'period_start',
		'period_end',
		'total_payable',
		'currency',
		'status',
		'settled_at',
		'notes',
	];

	/**
	 * Create a new settlement record
	 *
	 * @param array $data Settlement data
	 * @return int|false Insert ID or false on failure
	 */
	public static function create( $data ) {
		global $wpdb;

		$table = self::get_table_name();

		// Sanitize and prepare data
		$insert_data = [];
		foreach ( static::$fillable as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $field === 'notes' ) {
					$insert_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} elseif ( in_array( $field, [ 'period_start', 'period_end', 'settled_at' ] ) ) {
					$insert_data[ $field ] = sanitize_text_field( $data[ $field ] );
				} else {
					$insert_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		// Ensure seller_id is set
		if ( ! isset( $insert_data['seller_id'] ) ) {
			$insert_data['seller_id'] = get_current_user_id();
		}

		// Ensure currency is set (default to TWD)
		if ( ! isset( $insert_data['currency'] ) ) {
			$insert_data['currency'] = 'TWD';
		}

		// Ensure status is set
		if ( ! isset( $insert_data['status'] ) ) {
			$insert_data['status'] = 'pending';
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
	 * Get settlement by ID
	 *
	 * @param int $id Settlement ID
	 * @return object|null Settlement object or null if not found
	 */
	public static function find( $id ) {
		global $wpdb;

		$table = self::get_table_name();
		$id = absint( $id );

		$settlement = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		) );

		return $settlement ?: null;
	}

	/**
	 * Get settlements by supplier ID
	 *
	 * @param int $supplier_id Supplier ID
	 * @param array $args Query arguments
	 * @return array Array of settlement objects
	 */
	public static function getBySupplierId( $supplier_id, $args = [] ) {
		global $wpdb;

		$table = self::get_table_name();
		$supplier_id = absint( $supplier_id );

		$defaults = [
			'per_page' => 20,
			'page' => 1,
			'orderby' => 'created_at',
			'order' => 'DESC',
			'status' => 'all', // 'all', 'pending', 'settled'
		];

		$args = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$where = [ $wpdb->prepare( 'supplier_id = %d', $supplier_id ) ];

		if ( $args['status'] !== 'all' ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'created_at DESC';
		}

		$settlements = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
			$args['per_page'],
			$offset
		) );

		return $settlements;
	}

	/**
	 * Update settlement
	 *
	 * @param int $id Settlement ID
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
				if ( $field === 'notes' ) {
					$update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} elseif ( in_array( $field, [ 'period_start', 'period_end', 'settled_at' ] ) ) {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				} else {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// If status is being set to 'settled', set settled_at timestamp
		if ( isset( $update_data['status'] ) && $update_data['status'] === 'settled' && ! isset( $update_data['settled_at'] ) ) {
			$update_data['settled_at'] = current_time( 'mysql' );
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
	 * Delete settlement
	 *
	 * @param int $id Settlement ID
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
