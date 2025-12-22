<?php
/**
 * Migration: Create Merged Orders Table
 *
 * [AI Context]
 * - This table stores merged order information
 * - Prices are stored in "yuan" (元) for TWD, not in "cents" (分)
 * - This avoids the need to divide by 100 when displaying
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check if table exists before creating
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create merged orders table
 */
function buygo_frontend_portal_create_merged_orders_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'buygo_merged_orders';
	$charset_collate = $wpdb->get_charset_collate();

	// Check if table already exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) {
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		merged_order_id bigint(20) UNSIGNED NULL COMMENT '合併後的訂單 ID（對應 FluentCart order ID）',
		original_order_ids longtext NOT NULL COMMENT '原始訂單 ID 列表（JSON 格式）',
		customer_id bigint(20) UNSIGNED NOT NULL COMMENT '買家 ID',
		seller_id bigint(20) UNSIGNED NOT NULL COMMENT '賣家 ID',
		shipping_method varchar(100) NOT NULL DEFAULT '' COMMENT '運送方式',
		shipping_status varchar(20) NOT NULL DEFAULT 'unshipped' COMMENT '運送狀態',
		total_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '總金額（以元為單位，TWD 直接存元）',
		currency varchar(10) NOT NULL DEFAULT 'TWD' COMMENT '幣別',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '建立時間',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新時間',
		PRIMARY KEY (id),
		KEY merged_order_id (merged_order_id),
		KEY customer_id (customer_id),
		KEY seller_id (seller_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

// Run migration on activation (will be called from bootstrap or activator)
// buygo_frontend_portal_create_merged_orders_table();
