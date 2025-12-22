<?php

namespace BuyGo\Core\Services;

class HelperManager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'buygo_helpers';
    }

    /**
     * 取得賣家的小幫手列表
     */
    public function get_seller_helpers($seller_id) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h.*, u.user_login, u.user_email, u.display_name 
             FROM {$this->table_name} h
             LEFT JOIN {$wpdb->users} u ON h.helper_id = u.ID
             WHERE h.seller_id = %d",
            $seller_id
        ));

        // Format data: ensure permissions are booleans
        foreach ($results as $helper) {
            $helper->can_view_orders = (bool) $helper->can_view_orders;
            $helper->can_update_orders = (bool) $helper->can_update_orders;
            $helper->can_manage_products = (bool) $helper->can_manage_products;
            $helper->can_reply_customers = (bool) $helper->can_reply_customers;
            $helper->avatar_url = get_avatar_url($helper->helper_id);
        }

        return $results;
    }

    /**
     * 指派小幫手
     */
    public function assign_helper($seller_id, $user_input, $permissions = []) {
        global $wpdb;

        // 1. Find user (by ID or Email)
        $user = false;
        if (is_numeric($user_input)) {
            $user = get_user_by('id', intval($user_input));
        } else {
            $user = get_user_by('email', $user_input);
        }

        if (!$user) {
            return new \WP_Error('user_not_found', '找不到此 Email 的使用者，請確認對方已註冊。');
        }

        $helper_id = $user->ID;

        if ($helper_id == $seller_id) {
            return new \WP_Error('invalid_operation', '不能將自己設為小幫手。');
        }

        // 2. Check if already assigned
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE seller_id = %d AND helper_id = %d",
            $seller_id, $helper_id
        ));

        if ($exists) {
            return new \WP_Error('already_assigned', '該使用者已經是您的小幫手了。');
        }

        // 3. Insert
        $data = [
            'seller_id' => $seller_id,
            'helper_id' => $helper_id,
            'assigned_at' => current_time('mysql'),
            'assigned_by' => $seller_id,
            'can_view_orders' => !empty($permissions['can_view_orders']) ? 1 : 0,
            'can_update_orders' => !empty($permissions['can_update_orders']) ? 1 : 0,
            'can_manage_products' => !empty($permissions['can_manage_products']) ? 1 : 0,
            'can_reply_customers' => !empty($permissions['can_reply_customers']) ? 1 : 0,
        ];

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            return new \WP_Error('db_error', '新增失敗');
        }

        // 4. Update User Role (Add 'buygo_helper' if not present)
        if (!in_array('buygo_helper', (array)$user->roles) && !in_array('buygo_admin', (array)$user->roles) && !in_array('administrator', (array)$user->roles)) {
            $user->add_role('buygo_helper');
        }

        // Trigger Notification
        do_action('buygo_helper_assigned', $seller_id, $helper_id);

        return $wpdb->insert_id;
    }

    /**
     * 更新權限
     */
    public function update_permissions($id, $seller_id, $permissions) {
        global $wpdb;

        // Verify ownership
        $check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE id = %d AND seller_id = %d",
            $id, $seller_id
        ));

        if (!$check) {
            return new \WP_Error('not_found', '權限不足或找不到該小幫手。');
        }

        $data = [];
        if (isset($permissions['can_view_orders'])) $data['can_view_orders'] = $permissions['can_view_orders'] ? 1 : 0;
        if (isset($permissions['can_update_orders'])) $data['can_update_orders'] = $permissions['can_update_orders'] ? 1 : 0;
        if (isset($permissions['can_manage_products'])) $data['can_manage_products'] = $permissions['can_manage_products'] ? 1 : 0;
        if (isset($permissions['can_reply_customers'])) $data['can_reply_customers'] = $permissions['can_reply_customers'] ? 1 : 0;

        $wpdb->update($this->table_name, $data, ['id' => $id]);
        
        return true;
    }

    /**
     * 移除小幫手
     */
    public function remove_helper($id, $seller_id) {
        global $wpdb;

        // Verify ownership and get helper_id
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT helper_id FROM {$this->table_name} WHERE id = %d AND seller_id = %d",
            $id, $seller_id
        ));

        if (!$row) {
            return new \WP_Error('not_found', '權限不足或找不到該小幫手。');
        }

        // Remove
        $wpdb->delete($this->table_name, ['id' => $id]);

        // Note: We generally don't remove the role immediately because they might be helper for another seller.
        // Ideally we check if they are helper for ANY seller.
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE helper_id = %d", $row->helper_id));
        if ($count == 0) {
            $user = get_userdata($row->helper_id);
            if ($user) $user->remove_role('buygo_helper');
        }

        return true;
    }
}
