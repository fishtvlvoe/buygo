<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\App;

class SellerApplicationService {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'buygo_seller_applications';
    }

    /**
     * 提交申請
     */
    public function submit($user_id, $data) {
        global $wpdb;

        // 檢查是否已有待審核申請
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));

        if ($existing) {
            return new \WP_Error('duplicate_application', '您已有待審核的申請。');
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'status' => 'pending',
                'real_name' => sanitize_text_field($data['real_name']),
                'phone' => sanitize_text_field($data['phone']),
                'line_id' => sanitize_text_field($data['line_id']),
                'reason' => sanitize_textarea_field($data['reason'] ?? ''),
                'product_types' => sanitize_textarea_field($data['product_types'] ?? ''),
                'submitted_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', '資料庫寫入失敗。');
        }

        $insert_id = $wpdb->insert_id;
        
        // Trigger Notification
        do_action('buygo_seller_application_submitted', $insert_id);

        return $insert_id;
    }

    /**
     * 取得申請列表
     */
    public function get_applications($args = []) {
        global $wpdb;

        $defaults = [
            'status' => '',
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'submitted_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $where = "1=1";
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            $args['per_page'],
            $offset
        ));

        // 附加使用者資料
        foreach ($items as $item) {
            $user = get_userdata($item->user_id);
            $item->user_login = $user ? $user->user_login : 'Unknown';
            $item->user_email = $user ? $user->user_email : 'Unknown';
            $item->avatar_url = get_avatar_url($item->user_id);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}");

        return [
            'items' => $items,
            'total' => (int)$total_items,
            'total_pages' => ceil($total_items / $args['per_page'])
        ];
    }

    /**
     * 取得單一使用者的申請狀態
     */
    public function get_user_application($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY submitted_at DESC LIMIT 1",
            $user_id
        ));
    }

    /**
     * 核准申請
     */
    public function approve($id, $admin_id, $note = '') {
        global $wpdb;

        $application = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
        if (!$application) {
            return new \WP_Error('not_found', '找不到申請資料。');
        }

        if ($application->status !== 'pending') {
            return new \WP_Error('invalid_status', '此申請已處理過。');
        }

        // 更新狀態
        $wpdb->update(
            $this->table_name,
            [
                'status' => 'approved',
                'reviewed_by' => $admin_id,
                'reviewed_at' => current_time('mysql'),
                'review_note' => $note
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        // 變更使用者角色
        $role_manager = new RoleManager();
        $role_manager->set_user_role($application->user_id, 'buygo_seller');

        // 觸發 Hook
        do_action('buygo_seller_approved', $application->user_id, $application);

        return true;
    }

    /**
     * 拒絕申請
     */
    public function reject($id, $admin_id, $note = '') {
        global $wpdb;

        $application = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
        if (!$application) {
            return new \WP_Error('not_found', '找不到申請資料。');
        }

        if ($application->status !== 'pending') {
            return new \WP_Error('invalid_status', '此申請已處理過。');
        }

        // 更新狀態
        $wpdb->update(
            $this->table_name,
            [
                'status' => 'rejected',
                'reviewed_by' => $admin_id,
                'reviewed_at' => current_time('mysql'),
                'review_note' => $note
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        // 觸發 Hook
        do_action('buygo_seller_rejected', $application->user_id, $application);

        return true;
    }
}
