<?php
/**
 * 通知系統類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Notification {
    
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
     * 通知管理員有新的賣家申請
     */
    public function notify_admin_new_application( $application_id ) {
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        $application = $application_manager->get_application( $application_id );
        
        if ( ! $application ) {
            return false;
        }
        
        $user = get_userdata( $application->user_id );
        
        // 取得所有管理員
        $admins = get_users( array( 'role' => 'administrator' ) );
        
        foreach ( $admins as $admin ) {
            $to = $admin->user_email;
            $subject = '【BuyGo】新的賣家申請待審核';
            $message = sprintf(
                "您好，\n\n有新的賣家申請需要審核：\n\n申請人：%s (%s)\n真實姓名：%s\n聯絡電話：%s\nLINE ID：%s\n申請時間：%s\n\n請登入後台審核：%s\n\n謝謝！",
                $user->display_name,
                $user->user_email,
                $application->real_name,
                $application->phone,
                $application->line_id,
                $application->submitted_at,
                admin_url( 'admin.php?page=buygo-seller-applications' )
            );
            
            wp_mail( $to, $subject, $message );
        }
        
        return true;
    }
    
    /**
     * 通知申請人申請已核准
     */
    public function notify_application_approved( $application_id ) {
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        $application = $application_manager->get_application( $application_id );
        
        if ( ! $application ) {
            return false;
        }
        
        $user = get_userdata( $application->user_id );
        
        // Email 通知
        $to = $user->user_email;
        $subject = '【BuyGo】您的賣家申請已核准';
        $message = sprintf(
            "恭喜 %s，\n\n您的賣家申請已通過審核！\n\n%s\n\n您現在可以開始上架商品了。\n\n請透過以下指令開始使用：\n• 上架商品：直接傳送商品照片\n• 查看訂單：輸入「我的訂單」\n• 管理商品：輸入「我的商品」\n\n祝您銷售順利！",
            $user->display_name,
            ! empty( $application->review_note ) ? "審核備註：{$application->review_note}\n" : ''
        );
        
        wp_mail( $to, $subject, $message );
        
        // LINE 通知
        $this->send_line_notification( $application->user_id, sprintf(
            "🎉 恭喜！您的賣家申請已通過審核\n\n您現在可以開始上架商品了！\n\n請透過以下指令開始使用：\n• 上架商品：直接傳送商品照片\n• 查看訂單：輸入「我的訂單」\n• 管理商品：輸入「我的商品」\n\n祝您銷售順利！"
        ) );
        
        return true;
    }
    
    /**
     * 通知申請人申請已拒絕
     */
    public function notify_application_rejected( $application_id ) {
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        $application = $application_manager->get_application( $application_id );
        
        if ( ! $application ) {
            return false;
        }
        
        $user = get_userdata( $application->user_id );
        
        // Email 通知
        $to = $user->user_email;
        $subject = '【BuyGo】您的賣家申請未通過審核';
        $message = sprintf(
            "%s 您好，\n\n很抱歉，您的賣家申請未通過審核。\n\n拒絕原因：%s\n\n如有疑問，請聯絡客服。",
            $user->display_name,
            $application->review_note ?: '未提供'
        );
        
        wp_mail( $to, $subject, $message );
        
        // LINE 通知
        $this->send_line_notification( $application->user_id, sprintf(
            "很抱歉，您的賣家申請未通過審核\n\n拒絕原因：%s\n\n如有疑問，請聯絡客服。",
            $application->review_note ?: '未提供'
        ) );
        
        return true;
    }
    
    /**
     * 通知小幫手已被指派
     */
    public function notify_helper_assigned( $seller_id, $helper_id ) {
        $seller = get_userdata( $seller_id );
        $helper = get_userdata( $helper_id );
        
        if ( ! $seller || ! $helper ) {
            return false;
        }
        
        // Email 通知
        $to = $helper->user_email;
        $subject = '【BuyGo】您已被邀請成為小幫手';
        $message = sprintf(
            "%s 您好，\n\n您已被 %s 邀請成為小幫手。\n\n請登入後台查看詳情：%s\n\n謝謝！",
            $helper->display_name,
            $seller->display_name,
            admin_url( 'admin.php?page=buygo-helpers' )
        );
        
        wp_mail( $to, $subject, $message );
        
        // LINE 通知
        $this->send_line_notification( $helper_id, sprintf(
            "📢 您已被邀請成為小幫手\n\n賣家：%s\n\n請登入後台查看詳情。",
            $seller->display_name
        ) );
        
        return true;
    }
    
    /**
     * 通知 LINE 綁定成功
     */
    public function notify_line_binding_success( $user_id, $line_uid ) {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        // LINE 通知
        $this->send_line_notification( $user_id, 
            "✅ LINE 帳號綁定成功\n\n您的 LINE 帳號已成功綁定到 BuyGo 系統。\n\n現在您可以：\n• 接收訂單通知\n• 透過 LINE 上架商品（賣家）\n• 查詢訂單狀態\n\n感謝您的使用！"
        );
        
        return true;
    }
    
    /**
     * 發送 LINE 通知
     */
    private function send_line_notification( $user_id, $message ) {
        // 取得使用者的 LINE UID
        $line_binding = BuyGo_RP_Line_Binding::get_instance();
        $line_uid = $line_binding->get_user_line_uid( $user_id );
        
        if ( empty( $line_uid ) ) {
            return false;
        }
        
        // 這裡需要整合 LINE Messaging API
        // 暫時先記錄到日誌
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf(
                '[BuyGo RP] LINE Notification to %s: %s',
                $line_uid,
                $message
            ) );
        }
        
        // 觸發 action hook，讓其他外掛可以處理 LINE 通知
        do_action( 'buygo_rp_send_line_notification', $line_uid, $message );
        
        return true;
    }
}
