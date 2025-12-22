<?php
/**
 * API 提供者類別
 * 提供給其他外掛或系統呼叫的介面
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Api_Provider {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 取得單例實例
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函數
     */
    private function __construct() {
        // 註冊 REST API
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_rest_routes() {
        register_rest_route( 'buygo/v1', '/check-permission', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_check_permission' ),
            'permission_callback' => '__return_true', // 需在 callback 中驗證 API Key
        ) );
        
        register_rest_route( 'buygo/v1', '/line-bind', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_line_bind' ),
            'permission_callback' => '__return_true', // 公開 endpoint，內部驗證綁定碼
        ) );
    }
    
    /**
     * REST API: 檢查權限
     */
    public function rest_check_permission( $request ) {
        // 這裡應該驗證 API Key，暫時略過
        
        $user_id = $request->get_param( 'user_id' );
        $capability = $request->get_param( 'capability' );
        
        if ( ! $user_id || ! $capability ) {
            return new WP_Error( 'missing_params', '參數不足', array( 'status' => 400 ) );
        }
        
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        // 這裡需要實作具體的權限檢查邏輯，或是直接用 user_can
        $has_cap = user_can( $user_id, $capability );
        
        return array(
            'user_id' => $user_id,
            'capability' => $capability,
            'has_capability' => $has_cap,
        );
    }
    
    /**
     * REST API: LINE 綁定驗證
     * 接收 LINE Webhook 或其他來源的綁定請求
     */
    public function rest_line_bind( $request ) {
        $code = $request->get_param( 'code' );
        $line_uid = $request->get_param( 'line_uid' );
        
        if ( ! $code || ! $line_uid ) {
            return new WP_Error( 'missing_params', '參數不足', array( 'status' => 400 ) );
        }
        
        $binding_manager = BuyGo_RP_Line_Binding::get_instance();
        $result = $binding_manager->verify_binding_code( $code, $line_uid );
        
        if ( is_wp_error( $result ) ) {
            return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
        }
        
        return array(
            'success' => true,
            'message' => '綁定成功',
            'data' => $result,
        );
    }
    
    /**
     * 公開方法：檢查使用者是否為賣家
     */
    public static function is_seller( $user_id ) {
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        return $role_manager->get_user_role( $user_id ) === BUYGO_ROLE_SELLER;
    }
    
    /**
     * 公開方法：檢查使用者是否為小幫手
     */
    public static function is_helper( $user_id ) {
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        return $role_manager->get_user_role( $user_id ) === BUYGO_ROLE_HELPER;
    }
    
    /**
     * 公開方法：取得小幫手的權限
     */
    public static function get_helper_permissions( $seller_id, $helper_id ) {
        $helper_manager = BuyGo_RP_Helper_Manager::get_instance();
        
        $permissions = array(
            'view_orders' => $helper_manager->check_helper_permission( $helper_id, $seller_id, BUYGO_PERM_VIEW_ORDERS ),
            'update_orders' => $helper_manager->check_helper_permission( $helper_id, $seller_id, BUYGO_PERM_UPDATE_ORDERS ),
            'manage_products' => $helper_manager->check_helper_permission( $helper_id, $seller_id, BUYGO_PERM_MANAGE_PRODUCTS ),
            'reply_customers' => $helper_manager->check_helper_permission( $helper_id, $seller_id, BUYGO_PERM_REPLY_CUSTOMERS ),
        );
        
        return $permissions;
    }
}
