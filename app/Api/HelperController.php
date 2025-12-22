<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use BuyGo\Core\Services\HelperManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class HelperController extends BaseController {

    private $manager;

    public function __construct() {
        $this->manager = App::instance()->make(HelperManager::class);
        if (!$this->manager) {
            $this->manager = new HelperManager();
        }
    }

    public function register_routes() {
        // 賣家管理小幫手 API
        register_rest_route($this->namespace, '/helpers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_seller_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_seller_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/helpers/(?P<id>\d+)', [
            [
                'methods' => 'PUT',  // Update permissions
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_seller_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_seller_permission'],
            ]
        ]);
    }

    public function check_seller_permission() {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return false;
        }
        
        return in_array('buygo_seller', (array) $user->roles) || 
               in_array('buygo_admin', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles) ||
               in_array('buygo_helper', (array) $user->roles);
    }

    public function get_items(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        
        // If Admin and requesting all
        if (current_user_can('manage_options') && $request->get_param('context') === 'admin') {
            global $wpdb;
            $table = $wpdb->prefix . 'buygo_helpers';
            $helpers = $wpdb->get_results("SELECT * FROM $table ORDER BY assigned_at DESC");
            
            // Format for UI
            $formatted = [];
            foreach ($helpers as $h) {
                $seller = get_userdata($h->seller_id);
                $helper = get_userdata($h->helper_id);
                $formatted[] = [
                    'id' => $h->id,
                    'seller' => $seller ? ['id' => $seller->ID, 'name' => $seller->display_name, 'email' => $seller->user_email] : null,
                    'helper' => $helper ? ['id' => $helper->ID, 'name' => $helper->display_name, 'email' => $helper->user_email] : null,
                    'permissions' => [
                        'view_orders' => (bool)$h->can_view_orders,
                        'update_orders' => (bool)$h->can_update_orders,
                        'manage_products' => (bool)$h->can_manage_products,
                        'reply_customers' => (bool)$h->can_reply_customers,
                    ],
                    'assigned_at' => $h->assigned_at
                ];
            }
            return new WP_REST_Response([
                'success' => true,
                'data' => $formatted
            ], 200);
        }

        $seller_id = $user_id;
        $helpers = $this->manager->get_seller_helpers($seller_id);
        
        // Format for UI (consistent with admin format)
        $formatted = [];
        $seller_user = get_userdata($seller_id);
        foreach ($helpers as $h) {
            $helper_user = get_userdata($h->helper_id);
            $formatted[] = [
                'id' => $h->id,
                'seller' => $seller_user ? [
                    'id' => $seller_user->ID, 
                    'name' => $seller_user->display_name, 
                    'email' => $seller_user->user_email
                ] : null,
                'helper' => $helper_user ? [
                    'id' => $helper_user->ID, 
                    'name' => $helper_user->display_name, 
                    'email' => $helper_user->user_email
                ] : null,
                'permissions' => [
                    'view_orders' => (bool)$h->can_view_orders,
                    'update_orders' => (bool)$h->can_update_orders,
                    'manage_products' => (bool)$h->can_manage_products,
                    'reply_customers' => (bool)$h->can_reply_customers,
                ],
                'assigned_at' => $h->assigned_at
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $formatted
        ], 200);
    }

    public function create_item(WP_REST_Request $request) {
        $seller_id = get_current_user_id();
        $user_id = $request->get_param('user_id');
        $email = $request->get_param('email');
        $permissions = $request->get_param('permissions') ?: [];

        $target_input = $user_id ? $user_id : $email;

        if (empty($target_input)) {
            return new WP_Error('missing_data', '請選擇使用者或輸入 Email');
        }

        $result = $this->manager->assign_helper($seller_id, $target_input, $permissions);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true, 'id' => $result], 201);
    }

    public function update_item(WP_REST_Request $request) {
        $seller_id = get_current_user_id();
        $id = $request->get_param('id');
        $permissions = $request->get_param('permissions');

        $result = $this->manager->update_permissions($id, $seller_id, $permissions);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function delete_item(WP_REST_Request $request) {
        $seller_id = get_current_user_id();
        $id = $request->get_param('id');

        $result = $this->manager->remove_helper($id, $seller_id);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}
