<?php
/**
 * Migration: Fix Merged Orders Table Timestamps
 *
 * [AI Context]
 * - Fixes created_at and updated_at columns to be NOT NULL
 * - Updates existing records with current timestamp if they are NULL
 * - This is safer than dropping and recreating the table
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check if table exists before altering
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fix merged orders table timestamps
 */
function buygo_frontend_portal_fix_merged_orders_timestamps() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_merged_orders';

	// Check if table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
		return;
	}

	// Update existing NULL timestamps to current time
	$current_time = current_time( 'mysql' );
	
	// Update records where created_at is NULL
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$table_name} SET created_at = %s WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'",
		$current_time
	) );
	
	// Update records where updated_at is NULL
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$table_name} SET updated_at = %s WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'",
		$current_time
	) );

	// Alter table to make columns NOT NULL with default
	// Note: MySQL doesn't support DEFAULT CURRENT_TIMESTAMP for DATETIME in all versions
	// So we'll make them NOT NULL and let the application handle the timestamps
	$wpdb->query( "ALTER TABLE {$table_name} 
		MODIFY COLUMN created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '建立時間',
		MODIFY COLUMN updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新時間'
	" );
}
