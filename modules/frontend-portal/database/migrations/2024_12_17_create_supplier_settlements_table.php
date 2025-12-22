<?php
/**
 * Migration: Create Supplier Settlements Table
 *
 * [AI Context]
 * - This table stores settlement records (結算紀錄)
 * - Records when a seller settles accounts with a supplier for a specific time period
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
 * Create supplier settlements table
 */
function buygo_frontend_portal_create_supplier_settlements_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_supplier_settlements';
	$charset_collate = $wpdb->get_charset_collate();

	// Check if table already exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		supplier_id bigint(20) UNSIGNED NOT NULL COMMENT '供應商 ID',
		seller_id bigint(20) UNSIGNED NOT NULL COMMENT '賣家 ID',
		period_start date NOT NULL COMMENT '結算期間開始日期',
		period_end date NOT NULL COMMENT '結算期間結束日期',
		total_payable decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '總應付金額（以元為單位）',
		currency varchar(10) NOT NULL DEFAULT 'TWD' COMMENT '幣別',
		status varchar(20) NOT NULL DEFAULT 'pending' COMMENT '狀態（pending=待結算, settled=已結算）',
		settled_at datetime NULL COMMENT '結算時間',
		notes text NULL COMMENT '備註',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '建立時間',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新時間',
		PRIMARY KEY (id),
		KEY supplier_id (supplier_id),
		KEY seller_id (seller_id),
		KEY status (status),
		KEY period_start (period_start),
		KEY period_end (period_end)
	) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// Run migration on activation (will be called from bootstrap)
// buygo_frontend_portal_create_supplier_settlements_table();
