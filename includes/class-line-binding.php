<?php
/**
 * LINE 綁定功能類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Line_Binding {
    
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
        // 定期清理過期的綁定碼
        add_action( 'buygo_rp_cleanup_expired_bindings', array( $this, 'cleanup_expired_bindings' ) );
        
        if ( ! wp_next_scheduled( 'buygo_rp_cleanup_expired_bindings' ) ) {
            wp_schedule_event( time(), 'hourly', 'buygo_rp_cleanup_expired_bindings' );
        }

        // 整合 BuyGo LINE FluentCart Webhook
        add_filter( 'buygo_line_fc_pre_handle_text_message', array( $this, 'handle_webhook_text' ), 10, 5 );
    }

    /**
     * 處理來自 BuyGo LINE FluentCart 的 Webhook 文字訊息
     * 
     * @param bool   $handled     是否已處理
     * @param string $text        訊息內容
     * @param string $line_uid    LINE User ID
     * @param string $reply_token Reply Token
     * @param object $handler     Webhook Handler 實例 (用來發送回覆)
     * @return bool
     */
    public function handle_webhook_text( $handled, $text, $line_uid, $reply_token, $handler ) {
        if ( $handled ) {
            return true;
        }

        // 檢查是否為 6 位數綁定碼
        if ( ! preg_match( '/^\d{6}$/', trim( $text ) ) ) {
            return false;
        }

        // 嘗試驗證綁定碼
        $result = $this->verify_binding_code( trim( $text ), $line_uid );

        if ( is_wp_error( $result ) ) {
            // 驗證失敗，發送錯誤訊息
            if ( method_exists( $handler, 'send_reply' ) ) {
                $handler->send_reply( $reply_token, '❌ 綁定失敗：' . $result->get_error_message() );
            }
        } else {
            // 綁定成功
            if ( method_exists( $handler, 'send_reply' ) ) {
                $user = get_userdata( $result['user_id'] );
                $name = $user ? $user->display_name : '使用者';
                
                $message  = "✅ 綁定成功！\n\n";
                $message .= "嗨，{$name}！\n";
                $message .= "您的 LINE 帳號已成功連結到 BuyGo。\n";
                $message .= "現在您可以開始使用小幫手功能或接收通知了。";
                
                $handler->send_reply( $reply_token, $message );
            }
        }

        // 標記為已處理，阻止後續流程（如建立商品）
        return true;
    }
    
    /**
     * 產生綁定碼
     */
    public function generate_binding_code( $user_id ) {
        global $wpdb;
        
        // 檢查使用者是否存在
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', '使用者不存在' );
        }
        
        // 產生 6 位數綁定碼
        $code = $this->generate_unique_code();
        
        // 有效期 10 分鐘
        $expires_at = date( 'Y-m-d H:i:s', strtotime( '+10 minutes' ) );
        
        // 插入資料
        $table = $wpdb->prefix . 'buygo_line_bindings';
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'binding_code' => $code,
                'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
                'expires_at' => $expires_at,
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', '資料庫錯誤' );
        }
        
        // 觸發 action hook
        do_action( 'buygo_rp_binding_code_generated', $user_id, $code );
        
        return $code;
    }
    
    /**
     * 驗證綁定碼並完成綁定
     */
    public function verify_binding_code( $code, $line_uid ) {
        global $wpdb;
        
        $binding = $this->get_binding_by_code( $code );
        
        if ( ! $binding ) {
            return new WP_Error( 'invalid_code', '綁定碼不存在' );
        }
        
        // 檢查狀態
        if ( $binding->status !== 'pending' ) {
            return new WP_Error( 'invalid_status', '綁定碼已使用或已過期' );
        }
        
        // 檢查是否過期
        if ( strtotime( $binding->expires_at ) < time() ) {
            // 更新狀態為過期
            $table = $wpdb->prefix . 'buygo_line_bindings';
            $wpdb->update(
                $table,
                array( 'status' => 'expired' ),
                array( 'id' => $binding->id ),
                array( '%s' ),
                array( '%d' )
            );
            
            return new WP_Error( 'expired_code', '綁定碼已過期' );
        }
        
        // 更新綁定記錄
        $table = $wpdb->prefix . 'buygo_line_bindings';
        $wpdb->update(
            $table,
            array(
                'line_uid' => $line_uid,
                'status' => 'completed',
                'completed_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $binding->id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
        
        // 同步到 FluentCRM
        $sync_manager = BuyGo_RP_Sync_Manager::get_instance();
        $sync_manager->sync_line_binding( $binding->user_id, $line_uid );
        
        // 發送綁定成功通知
        $notification = BuyGo_RP_Notification::get_instance();
        $notification->notify_line_binding_success( $binding->user_id, $line_uid );
        
        // 觸發 action hook
        do_action( 'buygo_rp_line_binding_completed', $binding->user_id, $line_uid );
        
        return array(
            'user_id' => $binding->user_id,
            'line_uid' => $line_uid,
        );
    }
    
    /**
     * 根據綁定碼取得綁定資訊
     */
    public function get_binding_by_code( $code ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_line_bindings';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE binding_code = %s ORDER BY id DESC LIMIT 1",
            $code
        ) );
    }
    
    /**
     * 取得使用者的 LINE UID
     */
    public function get_user_line_uid( $user_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_line_bindings';
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT line_uid FROM {$table} WHERE user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
            $user_id
        ) );
    }
    
    /**
     * 檢查使用者是否已綁定 LINE
     */
    public function is_line_bound( $user_id ) {
        $line_uid = $this->get_user_line_uid( $user_id );
        return ! empty( $line_uid );
    }
    
    /**
     * 解除 LINE 綁定
     */
    public function unbind_line( $user_id ) {
        // 這裡不刪除記錄，只是標記為解除綁定
        // 實際上可以考慮在 FluentCRM 中清除 LINE UID
        
        $sync_manager = BuyGo_RP_Sync_Manager::get_instance();
        $sync_manager->sync_line_binding( $user_id, '' );
        
        // 觸發 action hook
        do_action( 'buygo_rp_line_binding_removed', $user_id );
        
        return true;
    }
    
    /**
     * 產生唯一的綁定碼
     */
    private function generate_unique_code() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_line_bindings';
        
        do {
            // 產生 6 位數字
            $code = str_pad( mt_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
            
            // 檢查是否已存在
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE binding_code = %s AND status = 'pending'",
                $code
            ) );
        } while ( $exists > 0 );
        
        return $code;
    }
    
    /**
     * 清理過期的綁定碼
     */
    public function cleanup_expired_bindings() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_line_bindings';
        $wpdb->query(
            "UPDATE {$table} SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()"
        );
    }
}
