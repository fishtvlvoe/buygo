<?php
/**
 * 資料同步管理類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Sync_Manager {
    
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
     * 同步資料到 FluentCRM
     */
    public function sync_to_fluentcrm( $user_id, $data ) {
        // 檢查 FluentCRM 是否啟用
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return new WP_Error( 'fluentcrm_not_active', 'FluentCRM 未啟用' );
        }
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', '使用者不存在' );
        }
        
        try {
            $contact_api = FluentCrmApi( 'contacts' );
            
            // 建立或更新聯絡人
            $contact_data = array(
                'email' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            );
            
            $contact = $contact_api->createOrUpdate( $contact_data );
            
            // 更新自訂欄位
            if ( isset( $data['buygo_role'] ) ) {
                $contact->updateCustomField( 'buygo_role', $data['buygo_role'] );
            }
            
            if ( isset( $data['line_uid'] ) ) {
                $contact->updateCustomField( 'line_uid', $data['line_uid'] );
            }
            
            if ( isset( $data['seller_status'] ) ) {
                $contact->updateCustomField( 'seller_status', $data['seller_status'] );
            }
            
            // 更新標籤
            if ( isset( $data['tags_add'] ) && is_array( $data['tags_add'] ) ) {
                $contact->attachTags( $data['tags_add'] );
            }
            
            if ( isset( $data['tags_remove'] ) && is_array( $data['tags_remove'] ) ) {
                $contact->detachTags( $data['tags_remove'] );
            }
            
            // 更新列表
            if ( isset( $data['lists_add'] ) && is_array( $data['lists_add'] ) ) {
                $contact->attachLists( $data['lists_add'] );
            }
            
            return true;
            
        } catch ( Exception $e ) {
            return new WP_Error( 'sync_error', $e->getMessage() );
        }
    }
    
    /**
     * 同步到 WordPress 角色
     */
    public function sync_to_wordpress_role( $user_id, $buygo_role ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', '使用者不存在' );
        }
        
        // 角色對應
        $role_map = array(
            BUYGO_ROLE_ADMIN => 'administrator',
            BUYGO_ROLE_SELLER => 'buygo_seller',
            BUYGO_ROLE_HELPER => 'buygo_helper',
            BUYGO_ROLE_BUYER => 'subscriber',
        );
        
        $wp_role = $role_map[ $buygo_role ] ?? 'subscriber';
        
        // 設定 WordPress 角色
        $user->set_role( $wp_role );
        
        return true;
    }
    
    /**
     * 使用者註冊時的完整同步
     */
    public function sync_user_registration( $user_id ) {
        // 同步到 FluentCRM
        $this->sync_to_fluentcrm( $user_id, array(
            'buygo_role' => BUYGO_ROLE_BUYER,
            'tags_add' => array( 'BuyGo Buyer' ),
            'lists_add' => array( 'BuyGo 所有使用者' ),
        ) );
        
        // 同步到 WordPress 角色
        $this->sync_to_wordpress_role( $user_id, BUYGO_ROLE_BUYER );
    }
    
    /**
     * 角色變更時的同步
     */
    public function sync_role_change( $user_id, $old_role, $new_role ) {
        // 準備標籤變更
        $tags_remove = array();
        $tags_add = array();
        $lists_add = array();
        
        // 移除舊角色標籤
        switch ( $old_role ) {
            case BUYGO_ROLE_ADMIN:
                $tags_remove[] = 'BuyGo Admin';
                break;
            case BUYGO_ROLE_SELLER:
                $tags_remove[] = 'BuyGo Seller';
                break;
            case BUYGO_ROLE_HELPER:
                $tags_remove[] = 'BuyGo Helper';
                break;
            case BUYGO_ROLE_BUYER:
                $tags_remove[] = 'BuyGo Buyer';
                break;
        }
        
        // 新增新角色標籤
        switch ( $new_role ) {
            case BUYGO_ROLE_ADMIN:
                $tags_add[] = 'BuyGo Admin';
                break;
            case BUYGO_ROLE_SELLER:
                $tags_add[] = 'BuyGo Seller';
                $tags_add[] = 'Seller Approved';
                $lists_add[] = 'BuyGo 賣家';
                break;
            case BUYGO_ROLE_HELPER:
                $tags_add[] = 'BuyGo Helper';
                break;
            case BUYGO_ROLE_BUYER:
                $tags_add[] = 'BuyGo Buyer';
                break;
        }
        
        // 同步到 FluentCRM
        $this->sync_to_fluentcrm( $user_id, array(
            'buygo_role' => $new_role,
            'tags_remove' => $tags_remove,
            'tags_add' => $tags_add,
            'lists_add' => $lists_add,
        ) );
        
        // 同步到 WordPress 角色
        $this->sync_to_wordpress_role( $user_id, $new_role );
    }
    
    /**
     * LINE 綁定時的同步
     */
    public function sync_line_binding( $user_id, $line_uid ) {
        $this->sync_to_fluentcrm( $user_id, array(
            'line_uid' => $line_uid,
        ) );
    }
}
