<?php
/**
 * Migration: Create Suppliers Table
 *
 * [AI Context]
 * - This table stores supplier basic information
 * - Used for tracking which products belong to which supplier
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
 * Create suppliers table
 */
function buygo_frontend_portal_create_suppliers_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_suppliers';
	$charset_collate = $wpdb->get_charset_collate();

	// Check if table already exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		seller_id bigint(20) UNSIGNED NOT NULL COMMENT '賣家 ID（這個供應商屬於哪個賣家）',
		name varchar(255) NOT NULL COMMENT '供應商名稱',
		contact_name varchar(255) NOT NULL DEFAULT '' COMMENT '聯絡人姓名',
		phone varchar(50) NOT NULL DEFAULT '' COMMENT '聯絡電話',
		email varchar(255) NOT NULL DEFAULT '' COMMENT '電子郵件',
		tax_id varchar(50) NOT NULL DEFAULT '' COMMENT '統一編號',
		address text NULL COMMENT '地址',
		bank_account varchar(255) NOT NULL DEFAULT '' COMMENT '銀行帳號（用於匯款）',
		notes text NULL COMMENT '備註',
		status varchar(20) NOT NULL DEFAULT 'active' COMMENT '狀態（active=啟用, inactive=停用）',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '建立時間',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新時間',
		PRIMARY KEY (id),
		KEY seller_id (seller_id),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// Run migration on activation (will be called from bootstrap)
// buygo_frontend_portal_create_suppliers_table();
