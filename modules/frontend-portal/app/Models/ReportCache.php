<?php
/**
 * ReportCache Model
 *
 * [AI Context]
 * - Caches report data to avoid frequent calculations
 * - Cache is refreshed 5-6 times per day
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must sanitize all input data
 * - Must handle cache expiration
 */

namespace BuyGo\Modules\FrontendPortal\App\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportCache {

	/**
	 * Table name
	 */
	protected static $table_name = 'buygo_report_cache';

	/**
	 * Get table name with prefix
	 */
	protected static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . static::$table_name;
	}

	/**
	 * Get cache data
	 *
	 * @param string $cache_key Cache key
	 * @return array|null Cached data or null if not found/expired
	 */
	public static function get( $cache_key ) {
		global $wpdb;

		$table = self::get_table_name();
		$cache_key = sanitize_text_field( $cache_key );

		$cache = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE cache_key = %s AND expires_at > NOW()",
			$cache_key
		) );

		if ( ! $cache ) {
			return null;
		}

		// Decode JSON data
		$data = json_decode( $cache->cache_data, true );

		return $data;
	}

	/**
	 * Set cache data
	 *
	 * @param string $cache_key Cache key
	 * @param array $data Data to cache
	 * @param int $expires_in Expiration time in seconds (default: 4 hours)
	 * @return bool True on success, false on failure
	 */
	public static function set( $cache_key, $data, $expires_in = 14400 ) {
		global $wpdb;

		$table = self::get_table_name();
		$cache_key = sanitize_text_field( $cache_key );

		// Calculate expiration time
		$expires_at = date( 'Y-m-d H:i:s', time() + $expires_in );

		// Encode data to JSON
		$cache_data = json_encode( $data );

		// Check if cache exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE cache_key = %s",
			$cache_key
		) );

		if ( $existing ) {
			// Update existing cache
			$result = $wpdb->update(
				$table,
				[
					'cache_data' => $cache_data,
					'expires_at' => $expires_at,
				],
				[ 'cache_key' => $cache_key ],
				[ '%s', '%s' ],
				[ '%s' ]
			);
		} else {
			// Insert new cache
			$result = $wpdb->insert(
				$table,
				[
					'cache_key' => $cache_key,
					'cache_data' => $cache_data,
					'expires_at' => $expires_at,
				],
				[ '%s', '%s', '%s' ]
			);
		}

		return $result !== false;
	}

	/**
	 * Delete cache
	 *
	 * @param string $cache_key Cache key
	 * @return bool True on success, false on failure
	 */
	public static function delete( $cache_key ) {
		global $wpdb;

		$table = self::get_table_name();
		$cache_key = sanitize_text_field( $cache_key );

		$result = $wpdb->delete(
			$table,
			[ 'cache_key' => $cache_key ],
			[ '%s' ]
		);

		return $result !== false;
	}

	/**
	 * Clear expired cache
	 *
	 * @return int Number of deleted rows
	 */
	public static function clearExpired() {
		global $wpdb;

		$table = self::get_table_name();

		$deleted = $wpdb->query(
			"DELETE FROM {$table} WHERE expires_at <= NOW()"
		);

		return $deleted;
	}

	/**
	 * Clear all cache
	 *
	 * @return bool True on success, false on failure
	 */
	public static function clearAll() {
		global $wpdb;

		$table = self::get_table_name();

		$result = $wpdb->query( "TRUNCATE TABLE {$table}" );

		return $result !== false;
	}
}
