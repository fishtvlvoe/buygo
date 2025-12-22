<?php
/**
 * Migration: Create Report Cache Table
 *
 * [AI Context]
 * - This table caches report data to avoid frequent calculations
 * - Cache is refreshed 5-6 times per day
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check if table exists before creating
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create report cache table
 */
function buygo_frontend_portal_create_report_cache_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_report_cache';
	$charset_collate = $wpdb->get_charset_collate();

	// Check if table already exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		cache_key varchar(255) NOT NULL COMMENT '快取鍵值',
		cache_data longtext NOT NULL COMMENT '快取數據（JSON 格式）',
		expires_at datetime NOT NULL COMMENT '過期時間',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY cache_key (cache_key),
		KEY expires_at (expires_at)
	) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// Run migration on activation (will be called from bootstrap or activator)
// buygo_frontend_portal_create_report_cache_table();
