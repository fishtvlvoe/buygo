<?php
/**
 * ReportCacheService
 *
 * [AI Context]
 * - Handles report data caching
 * - Cache is refreshed 5-6 times per day
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 *
 * [Constraints]
 * - Must use ReportCache Model for database operations
 * - Must handle cache expiration
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Modules\FrontendPortal\App\Models\ReportCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportCacheService {

	/**
	 * Cache expiration time (4 hours = 14400 seconds)
	 */
	const CACHE_EXPIRATION = 14400;

	/**
	 * Get cache data
	 *
	 * @param string $cache_key Cache key
	 * @return array|null Cached data or null if not found/expired
	 */
	public function get( $cache_key ) {
		return ReportCache::get( $cache_key );
	}

	/**
	 * Set cache data
	 *
	 * @param string $cache_key Cache key
	 * @param array $data Data to cache
	 * @param int $expires_in Expiration time in seconds
	 * @return bool True on success, false on failure
	 */
	public function set( $cache_key, $data, $expires_in = null ) {
		if ( $expires_in === null ) {
			$expires_in = self::CACHE_EXPIRATION;
		}
		return ReportCache::set( $cache_key, $data, $expires_in );
	}

	/**
	 * Delete cache
	 *
	 * @param string $cache_key Cache key
	 * @return bool True on success, false on failure
	 */
	public function delete( $cache_key ) {
		return ReportCache::delete( $cache_key );
	}

	/**
	 * Clear expired cache
	 *
	 * @return int Number of deleted rows
	 */
	public function clearExpired() {
		return ReportCache::clearExpired();
	}

	/**
	 * Clear all cache
	 *
	 * @return bool True on success, false on failure
	 */
	public function clearAll() {
		return ReportCache::clearAll();
	}

	/**
	 * Refresh cache (delete and recalculate)
	 *
	 * @param string $cache_key Cache key
	 * @param callable $callback Callback function to recalculate data
	 * @return array|null Refreshed data or null on failure
	 */
	public function refresh( $cache_key, $callback ) {
		// Delete existing cache
		$this->delete( $cache_key );

		// Recalculate data
		if ( is_callable( $callback ) ) {
			$data = call_user_func( $callback );
			if ( $data !== null ) {
				$this->set( $cache_key, $data );
				return $data;
			}
		}

		return null;
	}
}
