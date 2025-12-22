<?php
/**
 * 外掛啟用處理類別
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Activator
 */
class BuyGo_Plus_One_Activator {

	/**
	 * 外掛啟用時執行
	 */
	public static function activate() {
		// 檢查 PHP 版本
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( BUYGO_PLUS_ONE_BASENAME );
			wp_die(
				esc_html__( 'BuyGo 喊單外掛需要 PHP 7.4 或更高版本。', 'buygo-plus-one' ),
				esc_html__( '外掛啟用失敗', 'buygo-plus-one' ),
				array( 'back_link' => true )
			);
		}

		// 檢查 WordPress 版本
		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			deactivate_plugins( BUYGO_PLUS_ONE_BASENAME );
			wp_die(
				esc_html__( 'BuyGo 喊單外掛需要 WordPress 5.8 或更高版本。', 'buygo-plus-one' ),
				esc_html__( '外掛啟用失敗', 'buygo-plus-one' ),
				array( 'back_link' => true )
			);
		}

		// 建立資料庫表格
		self::create_tables();

		// 建立自訂角色
		self::create_roles();

		// 設定預設選項
		self::set_default_options();

		// 註冊定時任務
		self::schedule_cron_jobs();

		// 儲存外掛版本
		update_option( 'buygo_plus_one_version', BUYGO_PLUS_ONE_VERSION );

		// 設定啟用標記（用於顯示歡迎訊息）
		set_transient( 'buygo_plus_one_activated', true, 60 );
	}

	/**
	 * 建立資料庫表格
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// LINE 使用者對應表
		$table_line_users = $wpdb->prefix . 'buygo_line_users';
		$sql_line_users   = "CREATE TABLE IF NOT EXISTS $table_line_users (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			line_uid varchar(100) NOT NULL,
			line_name varchar(255) DEFAULT NULL,
			line_email varchar(255) DEFAULT NULL,
			line_picture_url text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY line_uid (line_uid),
			KEY user_id (user_id)
		) $charset_collate;";

		// 賣家申請表
		$table_seller_applications = $wpdb->prefix . 'buygo_seller_applications';
		$sql_seller_applications    = "CREATE TABLE IF NOT EXISTS $table_seller_applications (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			shop_name varchar(255) NOT NULL,
			phone varchar(50) NOT NULL,
			product_types text NOT NULL,
			reason text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_note text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_line_users );
		dbDelta( $sql_seller_applications );
	}

	/**
	 * 建立自訂角色
	 */
	private static function create_roles() {
		// Buyer 角色（只能購買）
		add_role(
			'buygo_buyer',
			__( 'BuyGo 買家', 'buygo-plus-one' ),
			array(
				'read'         => true,
				'edit_posts'   => false,
				'delete_posts' => false,
			)
		);

		// Seller 角色（可以上傳商品和購買）
		add_role(
			'buygo_seller',
			__( 'BuyGo 賣家', 'buygo-plus-one' ),
			array(
				'read'                   => true,
				'edit_posts'             => false,
				'delete_posts'           => false,
				'buygo_upload_products'  => true,
			)
		);
	}

	/**
	 * 設定預設選項
	 */
	private static function set_default_options() {
		$defaults = array(
			'buygo_plus_one_payment_deadline'      => 3,
			'buygo_plus_one_auto_create_category'  => 'yes',
			'buygo_plus_one_default_category'      => 'LINE 商品',
			'buygo_plus_one_enable_tax'            => 'no',
			'buygo_plus_one_default_tax_rate'      => 0,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * 註冊定時任務
	 */
	private static function schedule_cron_jobs() {
		// 檢查逾期訂單（每小時執行一次）
		if ( ! wp_next_scheduled( 'buygo_plus_one_check_expired_orders' ) ) {
			wp_schedule_event( time(), 'hourly', 'buygo_plus_one_check_expired_orders' );
		}
	}
}
