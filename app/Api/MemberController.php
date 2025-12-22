<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;

class MemberController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/members', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'], // Allow searching for autocomplete
                'args' => [
                    'context' => [
                        'required' => false,
                        'description' => 'Context of the request (e.g. "view", "edit", "search")',
                        'type' => 'string'
                    ]
                ],
                'permission_callback' => function () {
                    return is_user_logged_in(); // Basic check, detailed check in callback logic or specific permission method
                }
            ]
        ]);

        register_rest_route($this->namespace, '/members/(?P<id>\d+)/review', [
            'methods' => 'PUT',
            'callback' => [$this, 'review_application'],
            'permission_callback' => [$this, 'check_write_permission'],
        ]);

        register_rest_route($this->namespace, '/members/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_write_permission'],
        ]);
    }

    public function check_permission() {
        $user = wp_get_current_user();
        
        // Allow Admins and Shop Managers - check roles instead of capabilities
        return in_array('administrator', (array)$user->roles) || 
               in_array('buygo_admin', (array)$user->roles) ||
               in_array('buygo_seller', (array)$user->roles) || 
               in_array('buygo_helper', (array)$user->roles);
    }

    public function check_write_permission() {
        return current_user_can('manage_options');
    }

    /**
     * 審核申請
     */
    public function review_application(WP_REST_Request $request) {
        $user_id = $request->get_param('id');
        $action = $request->get_param('action'); // 'approve' or 'reject'

        if (!in_array($action, ['approve', 'reject'])) {
            return new \WP_Error('invalid_action', '無效的動作', ['status' => 400]);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_seller_applications';
        
        // 1. Update Application Status
        $wpdb->update(
            $table_name,
            ['status' => $action === 'approve' ? 'approved' : 'rejected'],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        );

        // 2. Update User Role
        $user = get_user_by('id', $user_id);
        if ($user) {
            if ($action === 'approve') {
                // Remove customer roles first
                $user->remove_role('subscriber');
                $user->remove_role('customer');
                $user->remove_role('buygo_buyer');
                
                // Set seller role (this will replace all roles)
                $user->set_role('buygo_seller');

                // Trigger Notification
                if (class_exists('\\BuyGo\\Core\\Services\\LineService')) {
                    $line_service = new \BuyGo\Core\Services\LineService();
                    $line_uid = $line_service->get_line_uid($user_id);
                    
                    if ($line_uid) {
                        $message = "恭喜 {$user->display_name}！\n您的賣家申請已通過審核。\n您現在可以開始使用賣家功能了。";
                        $line_service->send_push_message($line_uid, $message);
                    }
                }
            } else {
                // Remove seller role and restore customer role
                $user->remove_role('buygo_seller');
                if (empty($user->roles)) {
                    $user->set_role('subscriber'); // Default to subscriber if no roles
                }
                
                // Optional: Send rejection message
                /*
                if (class_exists('\\BuyGo\\Core\\Services\\LineService')) {
                    $line_service = new \BuyGo\Core\Services\LineService();
                    $line_uid = $line_service->get_line_uid($user_id);
                    if ($line_uid) {
                        $line_service->send_push_message($line_uid, "很抱歉，您的賣家申請未通過審核。");
                    }
                }
                */
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $action === 'approve' ? '已核准申請' : '已拒絕申請',
            'status' => $action === 'approve' ? 'approved' : 'rejected'
        ], 200);
    }

    /**
     * 更新會員資料 (角色)
     */
    public function update_item(WP_REST_Request $request) {
        $user_id = $request->get_param('id');
        $roles = $request->get_param('roles'); // Expecting array ['role_slug']
        $post_channel_id = $request->get_param('post_channel_id'); // Space ID for roles with channel posting

        if (empty($roles) || !is_array($roles)) {
            return new \WP_Error('invalid_param', '缺少角色參數', ['status' => 400]);
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('not_found', '找不到使用者', ['status' => 404]);
        }

        // Get old roles for comparison
        $old_roles = $user->roles;

        // Standard WP way to set roles (replaces existing)
        $primary_role = $roles[0];
        $user->set_role($primary_role); 
        
        // If supporting multiple roles:
        for ($i = 1; $i < count($roles); $i++) {
            $user->add_role($roles[$i]);
        }

        // 處理角色的發文頻道設定（支援：buygo_seller, buygo_admin, administrator, buygo_helper）
        $roles_with_channel = ['buygo_seller', 'buygo_admin', 'administrator', 'buygo_helper'];
        if (in_array($primary_role, $roles_with_channel) && isset($post_channel_id)) {
            if ($post_channel_id) {
                update_user_meta($user_id, 'buygo_post_channel_id', (int)$post_channel_id);
            } else {
                delete_user_meta($user_id, 'buygo_post_channel_id');
            }
        } elseif (!in_array($primary_role, $roles_with_channel)) {
            // 如果角色不支援頻道設定，清除頻道設定
            delete_user_meta($user_id, 'buygo_post_channel_id');
        }

        // Sync to FluentCart and FluentCommunity if it's a BuyGo role
        $buygo_roles = ['buygo_admin', 'buygo_seller', 'buygo_helper'];
        if (in_array($primary_role, $buygo_roles)) {
            $role_sync = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\RoleSyncService::class);
            if ($role_sync) {
                $role_sync->sync_role_to_integrations($user_id, $primary_role);
            }
        }

        // Trigger hook
        do_action('buygo_role_changed', $user_id, $old_roles, $roles);

        return new WP_REST_Response([
            'success' => true,
            'message' => '會員資料已更新',
            'user' => $this->format_user_with_status($user)
        ], 200);
    }

    /**
     * 取得會員列表
     */
    public function get_items(WP_REST_Request $request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $search = $request->get_param('search');
        $role = $request->get_param('role');

        $args = [
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        // [Contextual Filtering]
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $is_admin = in_array('administrator', (array)$current_user->roles) || 
                    in_array('buygo_admin', (array)$current_user->roles);
        $is_seller = in_array('buygo_seller', (array)$current_user->roles);
        
        // If not Admin, restrict view
        if (!$is_admin) {
            // Seller can only see connected users (Helpers or Customers)
            // Implementation detail: For now, if we don't have a "Seller-Customer" relation table,
            // we might restrict them to only see 'subscriber' role or similar.
            // TODO: Refine this logic when 'Seller-Customer' relationship is fully defined.
            
            // For MVP: Sellers can search for customers to add to orders/messages.
            // But they shouldn't see other Sellers or Admins.
            $args['role__not_in'] = ['administrator', 'buygo_admin', 'buygo_seller'];
            // However, they might need to see THEIR helpers.
            // This is complex. Let's start with basic Admin protection.
            
            // If user is searching specifically for helpers
            if ($role === 'buygo_helper') {
                 // TODO: Filter only my helpers
            }
        }

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($role)) {
            // Unify Customer Roles: subscriber, customer, buygo_buyer
            if ($role === 'subscriber') {
                $args['role__in'] = ['subscriber', 'customer', 'buygo_buyer'];
            } else {
                $args['role'] = $role;
            }
        }

        $user_query = new WP_User_Query($args);
        $total_users = $user_query->get_total();
        $users = $user_query->get_results();

        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = $this->format_user_with_status($user);
        }

        return new WP_REST_Response([
            'success' => true,
            'items' => $formatted_users,
            'total' => $total_users,
            'total_pages' => ceil($total_users / $per_page)
        ], 200);
    }

    /**
     * 格式化使用者資料
     */
    private function format_user($user) {
        // 嘗試取得 LINE UID
        $line_uid = $this->get_line_uid($user->ID);

        // 取得角色顯示名稱 (中文)
        // Priority: administrator > buygo_admin > buygo_seller > buygo_helper > customer
        global $wp_roles;
        $role_key = null;
        
        if (in_array('administrator', $user->roles)) {
            $role_key = 'administrator';
        } elseif (in_array('buygo_admin', $user->roles)) {
            $role_key = 'buygo_admin';
        } elseif (in_array('buygo_seller', $user->roles)) {
            $role_key = 'buygo_seller';
        } elseif (in_array('buygo_helper', $user->roles)) {
            $role_key = 'buygo_helper';
        } else {
            $role_key = reset($user->roles) ?: 'subscriber';
        }
        
        // Custom role display names
        $role_name_map = [
            'administrator' => 'WP 管理員',
            'buygo_admin' => 'BuyGo 管理員',
            'buygo_seller' => '賣家',
            'buygo_helper' => '小幫手',
        ];
        
        if (isset($role_name_map[$role_key])) {
            $role_name = $role_name_map[$role_key];
        } elseif (in_array($role_key, ['subscriber', 'customer', 'buygo_buyer'])) {
            $role_name = '顧客';
        } else {
            $role_name = isset($wp_roles->roles[$role_key]) ? $wp_roles->roles[$role_key]['name'] : $role_key;
        }

        // 取得角色的發文頻道 (Space ID) - 支援：buygo_seller, buygo_admin, administrator, buygo_helper
        $post_channel_id = null;
        $roles_with_channel = ['buygo_seller', 'buygo_admin', 'administrator', 'buygo_helper'];
        $user_has_channel_role = false;
        foreach ($roles_with_channel as $role) {
            if (in_array($role, $user->roles)) {
                $user_has_channel_role = true;
                break;
            }
        }
        if ($user_has_channel_role) {
            $post_channel_id = get_user_meta($user->ID, 'buygo_post_channel_id', true);
            if ($post_channel_id) {
                $post_channel_id = (int)$post_channel_id;
            }
        }

        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => get_avatar_url($user->ID),
            'roles' => $user->roles,
            'role_display' => $role_name,
            'registered_date' => date('Y-m-d', strtotime($user->user_registered)),
            'line_uid' => $line_uid,
            'post_channel_id' => $post_channel_id,
            'line_status' => !empty($line_uid) ? 'bound' : 'unbound',
            'edit_link' => get_edit_user_link($user->ID), // 加入編輯連結
        ];
    }

    /**
     * 取得 LINE UID 多重嘗試
     */
    private function get_line_uid($user_id) {
        $possible_keys = [
            'line_account_id',
            'social_id_line',
            'nsl_line_id', 
            '_buygo_line_uid'
        ];

        foreach ($possible_keys as $key) {
            $uid = get_user_meta($user_id, $key, true);
            if (!empty($uid)) {
                return $uid;
            }
        }
        
        // 嘗試從 social_users 表找 (Nextend Social Login)
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_users';
        // 檢查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
             $uid = $wpdb->get_var($wpdb->prepare(
                "SELECT identifier FROM {$table_name} WHERE ID = %d AND type = 'line'",
                $user_id
            ));
            if ($uid) return $uid;
        }

        return null;
    }

    /**
     * 取得賣家申請狀態
     */
    private function get_seller_application_status($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_seller_applications';
        
        // Check if table exists first to avoid errors
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 'none';
        }

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        return $status ?: 'none';
    }

    /**
     * Override format_user to include seller status
     */
    private function format_user_with_status($user) {
        $data = $this->format_user($user); // Call original
        $data['seller_status'] = $this->get_seller_application_status($user->ID);
        return $data;
    }
}
