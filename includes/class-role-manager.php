<?php
/**
 * 角色管理核心類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Role_Manager {
    
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
        // 移除自動設定角色的 hook，避免無限迴圈
    }
    
    /**
     * 取得使用者的 BuyGo 角色
     */
    public function get_user_role( $user_id ) {
        // 先檢查快取
        $cache_key = 'buygo_role_' . $user_id;
        $role = get_transient( $cache_key );
        
        if ( false === $role ) {
            // 優先從 user meta 讀取（新系統）
            $role = get_user_meta( $user_id, 'buygo_role', true );
            
            // 如果沒有，從 WordPress 角色讀取（舊系統）
            if ( empty( $role ) ) {
                $user = get_userdata( $user_id );
                if ( $user && ! empty( $user->roles ) ) {
                    // 檢查 WordPress 角色並對應到 BuyGo 角色
                    if ( in_array( 'administrator', $user->roles ) ) {
                        $role = BUYGO_ROLE_ADMIN;
                    } elseif ( in_array( 'buygo_seller', $user->roles ) ) {
                        $role = BUYGO_ROLE_SELLER;
                    } elseif ( in_array( 'buygo_helper', $user->roles ) ) {
                        $role = BUYGO_ROLE_HELPER;
                    } elseif ( in_array( 'buygo_buyer', $user->roles ) || in_array( 'subscriber', $user->roles ) ) {
                        $role = BUYGO_ROLE_BUYER;
                    } else {
                        $role = BUYGO_ROLE_BUYER;
                    }
                } else {
                    $role = BUYGO_ROLE_BUYER;
                }
            }
            
            // 快取 5 分鐘
            set_transient( $cache_key, $role, 5 * MINUTE_IN_SECONDS );
        }
        
        return $role;
    }
    
    /**
     * 設定使用者角色
     */
    public function set_user_role( $user_id, $role ) {
        // 驗證角色
        $valid_roles = array(
            BUYGO_ROLE_ADMIN,
            BUYGO_ROLE_SELLER,
            BUYGO_ROLE_HELPER,
            BUYGO_ROLE_BUYER,
        );
        
        if ( ! in_array( $role, $valid_roles ) ) {
            return new WP_Error( 'invalid_role', '無效的角色' );
        }
        
        // 直接從資料庫取得舊角色，避免呼叫 get_user_role() 造成無限迴圈
        $old_role = get_user_meta( $user_id, 'buygo_role', true );
        if ( empty( $old_role ) ) {
            $old_role = BUYGO_ROLE_BUYER;
        }
        
        // 如果角色沒有變更，直接返回
        if ( $old_role === $role ) {
            return true;
        }
        
        // 更新 user meta
        update_user_meta( $user_id, 'buygo_role', $role );
        
        // 清除快取
        $this->clear_user_role_cache( $user_id );
        
        // 同步到其他系統
        if ( class_exists( 'BuyGo_RP_Sync_Manager' ) ) {
            $sync_manager = BuyGo_RP_Sync_Manager::get_instance();
            $sync_manager->sync_role_change( $user_id, $old_role, $role );
        }
        
        // 觸發 action hook
        do_action( 'buygo_rp_role_changed', $user_id, $old_role, $role );
        
        return true;
    }
    
    /**
     * 取得指定角色的所有使用者
     */
    public function get_users_by_role( $role, $args = array() ) {
        $defaults = array(
            'meta_key' => 'buygo_role',
            'meta_value' => $role,
            'number' => -1,
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        return get_users( $args );
    }
    
    /**
     * 驗證使用者權限
     */
    public function validate_role_permission( $user_id, $permission ) {
        $role = $this->get_user_role( $user_id );
        
        // 定義權限對應
        $permissions = array(
            'manage_roles' => array( BUYGO_ROLE_ADMIN ),
            'approve_sellers' => array( BUYGO_ROLE_ADMIN ),
            'manage_helpers' => array( BUYGO_ROLE_ADMIN, BUYGO_ROLE_SELLER ),
            'view_all_orders' => array( BUYGO_ROLE_ADMIN ),
            'view_own_orders' => array( BUYGO_ROLE_ADMIN, BUYGO_ROLE_SELLER, BUYGO_ROLE_HELPER, BUYGO_ROLE_BUYER ),
            'manage_products' => array( BUYGO_ROLE_ADMIN, BUYGO_ROLE_SELLER ),
        );
        
        // 套用過濾器，允許其他外掛修改權限
        $permissions = apply_filters( 'buygo_rp_role_permissions', $permissions );
        
        if ( ! isset( $permissions[ $permission ] ) ) {
            return false;
        }
        
        return in_array( $role, $permissions[ $permission ] );
    }
    
    /**
     * 清除使用者角色快取
     */
    public function clear_user_role_cache( $user_id ) {
        delete_transient( 'buygo_role_' . $user_id );
    }
    
    /**
     * 批次取得使用者角色
     */
    public function get_users_roles( $user_ids ) {
        global $wpdb;
        
        if ( empty( $user_ids ) ) {
            return array();
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, meta_value as role
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'buygo_role'
            AND user_id IN ($placeholders)",
            $user_ids
        ) );
        
        $roles = array();
        foreach ( $results as $row ) {
            $roles[ $row->user_id ] = $row->role;
        }
        
        return $roles;
    }
}
