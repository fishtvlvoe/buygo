<?php
/**
 * 外掛停用處理類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Deactivator
 */
class BuyGo_Plus_One_Deactivator {

	/**
	 * 外掛停用時執行
	 */
	public static function deactivate() {
		// 移除定時任務
		self::clear_cron_jobs();

		// 移除自訂角色
		self::remove_roles();

		// 清除暫存資料
		self::clear_transients();
	}

	/**
	 * 清除定時任務
	 */
	private static function clear_cron_jobs() {
		$timestamp = wp_next_scheduled( 'buygo_line_fc_check_expired_orders' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'buygo_line_fc_check_expired_orders' );
		}
	}

	/**
	 * 移除自訂角色
	 */
	private static function remove_roles() {
		remove_role( 'buygo_buyer' );
		remove_role( 'buygo_seller' );
	}

	/**
	 * 清除暫存資料
	 */
	private static function clear_transients() {
		delete_transient( 'buygo_line_fc_activated' );
	}
}
