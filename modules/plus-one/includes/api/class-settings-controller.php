<?php
/**
 * 設定 API 控制器
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Settings_Controller
 */
class BuyGo_Plus_One_Settings_Controller extends WP_REST_Controller {

	/**
	 * 建構函數
	 */
	public function __construct() {
		$this->namespace = 'buygo-plus-one/v1';
		$this->rest_base = 'settings';
	}

	/**
	 * 註冊路由
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);
	}

	/**
	 * 檢查權限
	 *
	 * @param WP_REST_Request $request 請求物件.
	 * @return bool|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( '您沒有權限執行此操作。', 'buygo-plus-one' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * 取得設定
	 *
	 * @param WP_REST_Request $request 請求物件.
	 * @return WP_REST_Response
	 */
	public function get_settings( $request ) {
		$settings = array(
			'default_space_id' => (int) get_option( 'buygo_plus_one_default_space_id', 7 ),
			'webhook_url'      => rest_url( 'buygo-plus-one/v1/webhook' ),
			'seller_mappings'  => $this->get_enriched_mappings(),
			'available_users'  => $this->get_available_users(),  // 新增：使用者下拉選單資料
			'available_spaces' => $this->get_available_spaces(), // 新增：Space 下拉選單資料
		);

		return rest_ensure_response( $settings );
	}

	/**
	 * 取得可用使用者列表（用於下拉選單）
	 *
	 * @return array
	 */
	private function get_available_users() {
		// 抓取管理員、賣家、買家等角色
		$users = get_users( array(
			'role__in' => array( 
				'administrator', 
				'editor', 
				'author', 
				'shop_manager',
				'buygo_seller', // BuyGo 賣家
				'buygo_buyer',  // BuyGo 買家
				'subscriber',   // 訂閱者
				'customer'      // 顧客
			),
			'number'   => 100, // 限制數量避免過多
			'fields'   => array( 'ID', 'display_name', 'user_login' ),
		) );

		$options = array();
		foreach ( $users as $user ) {
			$options[] = array(
				'id'    => $user->ID,
				'label' => $user->display_name . ' (' . $user->user_login . ')',
			);
		}
		return $options;
	}

	/**
	 * 取得可用 Space 列表（用於下拉選單）
	 *
	 * @return array
	 */
	private function get_available_spaces() {
		$options = array();
		
		// 嘗試從 FluentCommunity Model 取得
		if ( class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			try {
				$spaces = \FluentCommunity\App\Models\Space::select(['id', 'title'])->get();
				foreach ( $spaces as $space ) {
					$options[] = array(
						'id'    => $space->id,
						'label' => $space->title . ' (ID: ' . $space->id . ')',
					);
				}
			} catch ( \Exception $e ) {
				// 忽略錯誤
			}
		}

		// 如果抓不到，至少保留預設的 (Fallback)
		if ( empty( $options ) ) {
			$options[] = array( 'id' => 7, 'label' => '好物推薦區 (Default)' );
		}

		return $options;
	}

	/**
	 * 更新設定
	 *
	 * @param WP_REST_Request $request 請求物件.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params();

		if ( isset( $params['default_space_id'] ) ) {
			update_option( 'buygo_plus_one_default_space_id', absint( $params['default_space_id'] ) );
		}

		if ( isset( $params['seller_mappings'] ) && is_array( $params['seller_mappings'] ) ) {
			$mappings = array();
			foreach ( $params['seller_mappings'] as $mapping ) {
				if ( isset( $mapping['user_id'] ) && isset( $mapping['space_id'] ) ) {
					$mappings[] = array(
						'user_id'   => absint( $mapping['user_id'] ),
						'space_id'  => absint( $mapping['space_id'] ),
						'is_active' => isset( $mapping['is_active'] ) ? (bool) $mapping['is_active'] : true,
					);
				}
			}
			update_option( 'buygo_plus_one_seller_mappings', $mappings );
		}

		return $this->get_settings( $request );
	}

	/**
	 * 取得豐富的映射資料（包含使用者名稱和 LINE UID）
	 *
	 * @return array
	 */
	private function get_enriched_mappings() {
		$mappings = get_option( 'buygo_plus_one_seller_mappings', array() );
		if ( ! is_array( $mappings ) ) {
			$mappings = array();
		}

		$enriched = array();
		foreach ( $mappings as $mapping ) {
			$user_id = $mapping['user_id'];
			$user = get_userdata( $user_id );
			
			// 如果使用者已被刪除，則跳過或標記
			if ( ! $user ) {
				continue;
			}

			// 取得 LINE UID (需要 Social Users 表或 User Meta)
			// 這裡簡單使用 helper 或直接查詢
			// 為了方便，我們假設 Admin 類別有 helper，或者我們自己寫一個簡單的 query
			$line_uid = $this->get_user_line_uid( $user_id );

			$enriched[] = array_merge( $mapping, array(
				'display_name' => $user->display_name,
				'user_login'   => $user->user_login,
				'line_uid'     => $line_uid,
				'avatar_url'   => get_avatar_url( $user_id ),
			) );
		}

		return $enriched;
	}

	/**
	 * 取得使用者的 LINE UID
	 *
	 * @param int $user_id WordPress 使用者 ID.
	 * @return string|null
	 */
	private function get_user_line_uid( $user_id ) {
		global $wpdb;
		$social_table = $wpdb->prefix . 'social_users';
		
		// 檢查資料表是否存在
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$social_table'" ) !== $social_table ) {
			return null;
		}

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT identifier FROM {$social_table} WHERE type = 'line' AND ID = %d LIMIT 1",
			$user_id
		) );
	}

	/**
	 * 獲取參數 schema
	 * 
	 * @param string $method HTTP 方法.
	 * @return array
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = array();

		if ( WP_REST_Server::EDITABLE === $method ) {
			$args['default_space_id'] = array(
				'description'       => __( '預設發布 Space ID', 'buygo-plus-one' ),
				'type'              => 'integer',
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				},
				'sanitize_callback' => 'absint',
			);
			
			$args['seller_mappings'] = array(
				'description' => __( '賣家映射列表', 'buygo-plus-one' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'user_id'   => array( 'type' => 'integer' ),
						'space_id'  => array( 'type' => 'integer' ),
						'is_active' => array( 'type' => 'boolean' ),
					),
				),
			);
		}

		return $args;
	}
}
