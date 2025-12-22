<?php
/**
 * 角色管理器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Role_Manager
 */
class BuyGo_Plus_One_Role_Manager {

	/**
	 * Logger
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * 建構函數
	 */
	public function __construct() {
		// 安全地初始化 Logger
		if ( class_exists( 'BuyGo_Plus_One_Logger' ) ) {
			$this->logger = BuyGo_Plus_One_Logger::get_instance();
		}
	}

	/**
	 * 初始化
	 */
	public function init() {
		// 監聽 Nextend Social Login Hooks.
		add_action( 'nsl_line_register_new_user', array( $this, 'on_line_register' ), 10, 3 );
		add_action( 'nsl_line_login', array( $this, 'on_line_login' ), 10, 3 );
	}

	/**
	 * 處理新使用者註冊
	 *
	 * @param int   $user_id WordPress 使用者 ID.
	 * @param mixed $provider_or_data Nextend provider 物件或資料.
	 * @param mixed $data_or_provider 資料或 provider 物件.
	 */
	public function on_line_register( $user_id, $provider_or_data = null, $data_or_provider = null ) {
		if ( $this->logger ) {
			$this->logger->info(
				'New LINE user registered',
				array(
					'user_id' => $user_id,
				)
			);
		}

		// 設定為 Buyer 角色.
		$user = new WP_User( $user_id );
		$user->set_role( 'buygo_buyer' );

		if ( $this->logger ) {
			$this->logger->info(
				'User role set to buygo_buyer',
				array(
					'user_id' => $user_id,
				)
			);
		}
	}

	/**
	 * 處理使用者登入
	 *
	 * @param int   $user_id WordPress 使用者 ID.
	 * @param mixed $provider_or_data Nextend provider 物件或資料.
	 * @param mixed $data_or_provider 資料或 provider 物件.
	 */
	public function on_line_login( $user_id, $provider_or_data = null, $data_or_provider = null ) {
		if ( $this->logger ) {
			$this->logger->debug(
				'LINE user logged in',
				array(
					'user_id' => $user_id,
				)
			);
		}
	}

	/**
	 * 檢查使用者是否可以上傳商品
	 *
	 * @param int $user_id 使用者 ID
	 * @return bool
	 */
	public function can_upload_product( $user_id ) {
		$user = new WP_User( $user_id );
		
		// Administrator、BuyGo Admin 和 Seller 可以上傳商品
		if ( $user->has_cap( 'administrator' ) || in_array( 'buygo_admin', (array)$user->roles, true ) || in_array( 'buygo_seller', (array)$user->roles, true ) ) {
			return true;
		}

		// 檢查小幫手權限：需要同時滿足以下條件
		// 1. 擁有 buygo_helper 角色
		// 2. 在 buygo_helpers 資料表中被正式指派（至少被一個賣家指派）
		// 3. can_manage_products 權限為 true
		if ( in_array( 'buygo_helper', (array)$user->roles, true ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'buygo_helpers';
			
			// 檢查是否有任何賣家指派此小幫手，且 can_manage_products 為 true
			$has_permission = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE helper_id = %d AND can_manage_products = 1",
				$user_id
			) );
			
			if ( $has_permission > 0 ) {
				return true;
			}
		}

		return false;
	}


}
