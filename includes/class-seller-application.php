<?php
/**
 * 賣家申請系統類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Seller_Application {
    
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
     * 提交賣家申請
     */
    public function submit_application( $user_id, $data ) {
        global $wpdb;
        
        // 驗證資料
        $validated = $this->validate_application_data( $data );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }
        
        // 檢查是否已有待審核的申請
        $existing = $this->get_user_application( $user_id, BUYGO_APP_STATUS_PENDING );
        if ( $existing ) {
            return new WP_Error( 'duplicate_application', '您已經提交過申請，請勿重複提交' );
        }
        
        // 插入資料
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'status' => BUYGO_APP_STATUS_PENDING,
                'real_name' => sanitize_text_field( $data['real_name'] ),
                'phone' => sanitize_text_field( $data['phone'] ),
                'line_id' => sanitize_text_field( $data['line_id'] ),
                'reason' => sanitize_textarea_field( $data['reason'] ?? '' ),
                'product_types' => sanitize_textarea_field( $data['product_types'] ?? '' ),
                'submitted_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        if ( false === $result ) {
            return new WP_Error( 'db_error', '資料庫錯誤' );
        }
        
        $application_id = $wpdb->insert_id;
        
        // 發送通知給管理員
        $notification = BuyGo_RP_Notification::get_instance();
        $notification->notify_admin_new_application( $application_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_application_submitted', $application_id, $user_id );
        
        return $application_id;
    }
    
    /**
     * 取得申請詳情
     */
    public function get_application( $application_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_seller_applications';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $application_id
        ) );
    }
    
    /**
     * 取得使用者的申請記錄
     */
    public function get_user_application( $user_id, $status = null ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_seller_applications';
        
        if ( $status ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
                $user_id,
                $status
            ) );
        } else {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ) );
        }
    }
    
    /**
     * 核准申請
     */
    public function approve_application( $application_id, $admin_id, $note = '' ) {
        global $wpdb;
        
        $application = $this->get_application( $application_id );
        if ( ! $application ) {
            return new WP_Error( 'not_found', '找不到申請記錄' );
        }
        
        if ( $application->status !== BUYGO_APP_STATUS_PENDING ) {
            return new WP_Error( 'invalid_status', '只能審核待審核的申請' );
        }
        
        // 更新申請狀態
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $wpdb->update(
            $table,
            array(
                'status' => BUYGO_APP_STATUS_APPROVED,
                'reviewed_at' => current_time( 'mysql' ),
                'reviewed_by' => $admin_id,
                'review_note' => sanitize_textarea_field( $note ),
            ),
            array( 'id' => $application_id ),
            array( '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );
        
        // 升級使用者角色為賣家
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        $role_manager->set_user_role( $application->user_id, BUYGO_ROLE_SELLER );
        
        // 發送通知
        $notification = BuyGo_RP_Notification::get_instance();
        $notification->notify_application_approved( $application_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_application_approved', $application_id, $application->user_id );
        
        return true;
    }
    
    /**
     * 拒絕申請
     */
    public function reject_application( $application_id, $admin_id, $note = '' ) {
        global $wpdb;
        
        $application = $this->get_application( $application_id );
        if ( ! $application ) {
            return new WP_Error( 'not_found', '找不到申請記錄' );
        }
        
        if ( $application->status !== BUYGO_APP_STATUS_PENDING ) {
            return new WP_Error( 'invalid_status', '只能審核待審核的申請' );
        }
        
        // 更新申請狀態
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $wpdb->update(
            $table,
            array(
                'status' => BUYGO_APP_STATUS_REJECTED,
                'reviewed_at' => current_time( 'mysql' ),
                'reviewed_by' => $admin_id,
                'review_note' => sanitize_textarea_field( $note ),
            ),
            array( 'id' => $application_id ),
            array( '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );
        
        // 發送通知
        $notification = BuyGo_RP_Notification::get_instance();
        $notification->notify_application_rejected( $application_id );
        
        // 觸發 action hook
        do_action( 'buygo_rp_application_rejected', $application_id, $application->user_id );
        
        return true;
    }
    
    /**
     * 取得待審核申請列表
     */
    public function get_pending_applications() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'buygo_seller_applications';
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE status = '" . BUYGO_APP_STATUS_PENDING . "' ORDER BY submitted_at DESC"
        );
    }
    
    /**
     * 驗證申請資料
     */
    private function validate_application_data( $data ) {
        // 檢查必填欄位
        if ( empty( $data['real_name'] ) ) {
            return new WP_Error( 'missing_field', '請填寫真實姓名' );
        }
        
        if ( empty( $data['phone'] ) ) {
            return new WP_Error( 'missing_field', '請填寫聯絡電話' );
        }
        
        if ( empty( $data['line_id'] ) ) {
            return new WP_Error( 'missing_field', '請填寫 LINE ID' );
        }
        
        // 驗證電話格式
        if ( ! $this->validate_phone( $data['phone'] ) ) {
            return new WP_Error( 'invalid_phone', '電話號碼格式不正確' );
        }
        
        return true;
    }
    
    /**
     * 驗證電話號碼格式
     */
    private function validate_phone( $phone ) {
        // 台灣手機：09 開頭，10 碼
        // 台灣市話：區碼 + 號碼
        $pattern = '/^(09\d{8}|0\d{1,2}-?\d{7,8})$/';
        return preg_match( $pattern, $phone );
    }
}
