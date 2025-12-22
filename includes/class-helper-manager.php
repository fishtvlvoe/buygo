<?php
/**
 * 小幫手管理類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Helper_Manager {
    
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
        // 註冊 hooks
    }
    
    /**
     * 指派小幫手
     */
    public function assign_helper( $seller_id, $helper_id, $permissions, $assigned_by ) {
        global $wpdb;
        
        // 驗證賣家和小幫手
        $seller = get_userdata( $seller_id );
        $helper = get_userdata( $helper_id );
        
        if ( ! $seller || ! $helper ) {
            return new WP_Error( 'invalid_user', '使用者不存在' );
        }
        
        // 檢查是否已經指派
        if ( $this->is_helper_assigned( $seller_id, $helper_id ) ) {
            return new WP_Error( 'already_assigned', '此小幫手已被指派' );
        }
        
        // 插入資料
        $table = $wpdb->prefix . 'buygo_helpers';
        $result = $wpdb->insert(
            $table,
            array(
                'seller_id' => $seller_id,
                'helper_id' => $helper_id,
                'can_view_orders' => ! empty( $permissions[ BUYGO_PERM_VIEW_ORDERS ] ) ? 1 : 0,
                'can_update_orders' => ! empty( $permissions[ BUYGO_PERM_UPDATE_ORDERS ] ) ? 1 : 0,
                'can_manage_products' => ! empty( $permissions[ BUYGO_PERM_MANAGE_PRODUCTS ] ) ? 1 : 0,
                'can_reply_customers' => ! empty( $permissions[ BUYGO_PERM_REPLY_CUSTOMERS ] ) ? 1 : 0,
                'assigned_at' => current_time( 'mysql' ),
                'assigned_by' => $assigned_by,
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d' )
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', '資料庫錯誤' );
        }
        
        // 升級小幫手角色（如果還不是）
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        $current_role = $role_manager->get_user_role( $helper_id );
        if ( $current_role === BUYGO_ROLE_BUYER ) {
            $role_manager->set_user_role( $helper_id, BUYGO_ROLE_HELPER );
        }
        
        // 清除快取
        $this->clear_helper_cache( $seller_id, $helper_id );
        
        // 發送通知
        $notification = BuyGo_RP_Notification::get_instance();
        $notification->notify_helper_assigned( $seller_id, $helper_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_helper_assigned', $seller_id, $helper_id, $permissions );
        
        return $wpdb->insert_id;
    }
    
    /**
     * 移除小幫手
     */
    public function remove_helper( $seller_id, $helper_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_helpers';
        $result = $wpdb->delete(
            $table,
            array(
                'seller_id' => $seller_id,
                'helper_id' => $helper_id,
            ),
            array( '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', '資料庫錯誤' );
        }
        
        // 清除快取
        $this->clear_helper_cache( $seller_id, $helper_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_helper_removed', $seller_id, $helper_id );
        
        return true;
    }
    
    /**
     * 取得賣家的所有小幫手
     */
    public function get_seller_helpers( $seller_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_helpers';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE seller_id = %d ORDER BY assigned_at DESC",
            $seller_id
        ) );
    }
    
    /**
     * 取得小幫手協助的所有賣家
     */
    public function get_helper_sellers( $helper_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_helpers';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE helper_id = %d ORDER BY assigned_at DESC",
            $helper_id
        ) );
    }
    
    /**
     * 更新小幫手權限
     */
    public function update_helper_permissions( $seller_id, $helper_id, $permissions ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_helpers';
        $result = $wpdb->update(
            $table,
            array(
                'can_view_orders' => ! empty( $permissions[ BUYGO_PERM_VIEW_ORDERS ] ) ? 1 : 0,
                'can_update_orders' => ! empty( $permissions[ BUYGO_PERM_UPDATE_ORDERS ] ) ? 1 : 0,
                'can_manage_products' => ! empty( $permissions[ BUYGO_PERM_MANAGE_PRODUCTS ] ) ? 1 : 0,
                'can_reply_customers' => ! empty( $permissions[ BUYGO_PERM_REPLY_CUSTOMERS ] ) ? 1 : 0,
            ),
            array(
                'seller_id' => $seller_id,
                'helper_id' => $helper_id,
            ),
            array( '%d', '%d', '%d', '%d' ),
            array( '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', '資料庫錯誤' );
        }
        
        // 清除快取
        $this->clear_helper_cache( $seller_id, $helper_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_helper_permissions_updated', $seller_id, $helper_id, $permissions );
        
        return true;
    }
    
    /**
     * 檢查小幫手權限
     */
    public function check_helper_permission( $helper_id, $seller_id, $permission ) {
        global $wpdb;
        
        // 檢查快取
        $cache_key = "buygo_helper_perm_{$helper_id}_{$seller_id}_{$permission}";
        $has_permission = get_transient( $cache_key );
        
        if ( false === $has_permission ) {
            $table = $wpdb->prefix . 'buygo_helpers';
            $result = $wpdb->get_var( $wpdb->prepare(
                "SELECT {$permission} FROM {$table} WHERE seller_id = %d AND helper_id = %d",
                $seller_id,
                $helper_id
            ) );
            
            $has_permission = ( 1 === (int) $result ) ? 'yes' : 'no';
            
            // 快取 5 分鐘
            set_transient( $cache_key, $has_permission, 5 * MINUTE_IN_SECONDS );
        }
        
        return 'yes' === $has_permission;
    }
    
    /**
     * 檢查是否已指派
     */
    private function is_helper_assigned( $seller_id, $helper_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_helpers';
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE seller_id = %d AND helper_id = %d",
            $seller_id,
            $helper_id
        ) );
        
        return $count > 0;
    }
    
    /**
     * 清除快取
     */
    private function clear_helper_cache( $seller_id, $helper_id ) {
        $permissions = array(
            BUYGO_PERM_VIEW_ORDERS,
            BUYGO_PERM_UPDATE_ORDERS,
            BUYGO_PERM_MANAGE_PRODUCTS,
            BUYGO_PERM_REPLY_CUSTOMERS,
        );
        
        foreach ( $permissions as $permission ) {
            delete_transient( "buygo_helper_perm_{$helper_id}_{$seller_id}_{$permission}" );
        }
    }
}
