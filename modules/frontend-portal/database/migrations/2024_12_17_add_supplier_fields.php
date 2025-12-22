<?php
/**
 * Migration: Add additional fields to Suppliers Table
 *
 * [AI Context]
 * - Adds Line ID, bank name, and bank branch fields
 * - These fields are required for supplier management
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check if columns exist before adding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add additional fields to suppliers table
 */
function buygo_frontend_portal_add_supplier_fields() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_suppliers';

	// Check if table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
		return;
	}

	// Add Line ID field
	$line_id_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'line_id'" );
	if ( empty( $line_id_exists ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN line_id varchar(255) NOT NULL DEFAULT '' COMMENT 'Line ID' AFTER email" );
	}

	// Add bank name field
	$bank_name_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'bank_name'" );
	if ( empty( $bank_name_exists ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN bank_name varchar(255) NOT NULL DEFAULT '' COMMENT '銀行名稱' AFTER bank_account" );
	}

	// Add bank branch field
	$bank_branch_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'bank_branch'" );
	if ( empty( $bank_branch_exists ) ) {
		$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN bank_branch varchar(255) NOT NULL DEFAULT '' COMMENT '分行' AFTER bank_name" );
	}
}
