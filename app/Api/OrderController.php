<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;
use BuyGo\Core\Services\OrderService; // Future Service
use BuyGo\Core\App;

class OrderController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => '__return_true',
            ]
        ]);
        
        // Get single order details
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    public function check_permission() {
        return current_user_can('manage_options') || current_user_can('buygo_admin');
    }
    
    public function check_read_permission($request) {
        $user = wp_get_current_user();
        
        // Allow admin, buygo_admin, buygo_seller, and buygo_helper
        return in_array('administrator', (array)$user->roles) || 
               in_array('buygo_admin', (array)$user->roles) ||
               in_array('buygo_seller', (array)$user->roles) || 
               in_array('buygo_helper', (array)$user->roles);
    }

    /**
     * Get Orders List (Scoped to Seller)
     */
    public function get_items(WP_REST_Request $request) {
        global $wpdb;
        
        $user = wp_get_current_user();
        $user_id = $user->ID;

        // Verify tables exist first (Sanity Check)
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        $table_customers = $wpdb->prefix . 'fct_customers';

        // SQL: Get ALL Orders (for admin) or filtered by seller (for non-admin)
        $is_admin = in_array('administrator', (array)$user->roles) || in_array('buygo_admin', (array)$user->roles);
        
        $where_conditions = ["1=1"];
        
        // Add Status Filter
        $status = $request->get_param('status');
        if ($status && $status !== 'all') {
            $where_conditions[] = $wpdb->prepare("o.status = %s", $status);
        }

        // Add User Search (Order No)
        $search = $request->get_param('search');
        if ($search && is_numeric($search)) {
            $where_conditions[] = $wpdb->prepare("o.id = %d", intval($search));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Check if tables exist
        $customers_table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table_customers}'") === $table_customers);
        
        // Build query - for admin, get all orders; for non-admin, filter by seller products
        if ($is_admin) {
            // Admin: Get all orders without seller filter
            if ($customers_table_exists) {
                $sql = "
                    SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status, 
                           o.currency, o.created_at,
                           c.first_name, c.last_name, c.email, c.contact_id as user_id
                    FROM {$table_orders} o
                    LEFT JOIN {$table_customers} c ON o.customer_id = c.id
                    WHERE {$where_clause}
                ";
                
                // Add search filter for customer name/email if provided
                if ($search && !is_numeric($search)) {
                    $sql .= $wpdb->prepare(
                        " AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
                        '%' . $wpdb->esc_like($search) . '%',
                        '%' . $wpdb->esc_like($search) . '%',
                        '%' . $wpdb->esc_like($search) . '%'
                    );
                }
                
                $sql .= " ORDER BY o.id DESC LIMIT 100";
            } else {
                $sql = "
                    SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status, 
                           o.currency, o.created_at,
                           NULL as first_name, NULL as last_name, NULL as email, NULL as user_id
                    FROM {$table_orders} o
                    WHERE {$where_clause}
                    ORDER BY o.id DESC
                    LIMIT 100
                ";
            }
        } else {
            // Non-admin: Only get orders with products from this seller
            if ($customers_table_exists) {
                $sql = "
                    SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status, 
                           o.currency, o.created_at,
                           c.first_name, c.last_name, c.email, c.contact_id as user_id
                    FROM {$table_orders} o
                    LEFT JOIN {$table_customers} c ON o.customer_id = c.id
                    INNER JOIN {$table_items} oi ON o.id = oi.order_id
                    INNER JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE {$where_clause}
                    AND p.post_type = 'fluent-products'
                    AND p.post_author = %d
                ";
                
                // Add search filter for customer name/email if provided
                if ($search && !is_numeric($search)) {
                    $sql .= $wpdb->prepare(
                        " AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
                        '%' . $wpdb->esc_like($search) . '%',
                        '%' . $wpdb->esc_like($search) . '%',
                        '%' . $wpdb->esc_like($search) . '%'
                    );
                }
                
                $sql = $wpdb->prepare($sql . " ORDER BY o.id DESC LIMIT 100", $user->ID);
            } else {
                $sql = $wpdb->prepare("
                    SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status, 
                           o.currency, o.created_at,
                           NULL as first_name, NULL as last_name, NULL as email, NULL as user_id
                    FROM {$table_orders} o
                    INNER JOIN {$table_items} oi ON o.id = oi.order_id
                    INNER JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE {$where_clause}
                    AND p.post_type = 'fluent-products'
                    AND p.post_author = %d
                    ORDER BY o.id DESC
                    LIMIT 100
                ", $user->ID);
            }
        }
        
        $results = $wpdb->get_results($sql);
        
        // Format Data for Frontend
        $data = [];
        
        if ($results) {
            foreach ($results as $row) {
                // Determine Customer Name (from fct_customers or fallback to user)
                $customer_name = 'Guest';
                $customer_email = '';
                
                if (!empty($row->first_name) || !empty($row->last_name)) {
                    $customer_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                    $customer_email = $row->email ?? '';
                } elseif (!empty($row->customer_id) && $customers_table_exists) {
                    // Try to get customer from fct_customers table
                    $customer = $wpdb->get_row($wpdb->prepare(
                        "SELECT first_name, last_name, email FROM {$table_customers} WHERE id = %d",
                        $row->customer_id
                    ));
                    if ($customer) {
                        $customer_name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                        $customer_email = $customer->email ?? '';
                    }
                }
                
                // Fallback to WordPress user
                if (empty($customer_name) || $customer_name === 'Guest') {
                    if (!empty($row->user_id)) {
                        $u = get_userdata($row->user_id);
                        if ($u) {
                            $customer_name = $u->display_name;
                            $customer_email = $u->user_email;
                        }
                    }
                }

                // Get item count for this order
                $item_count_sql = $wpdb->prepare(
                    "SELECT COUNT(*) as count FROM {$table_items} WHERE order_id = %d",
                    $row->id
                );
                $item_count_result = $wpdb->get_var($item_count_sql);
                $item_count = (int)($item_count_result ?? 0);

                // Get sellers for this order (from order items -> products -> authors)
                // FluentCart products use 'fluent-products' post_type
                $sellers_sql = $wpdb->prepare("
                    SELECT DISTINCT p.post_author
                    FROM {$table_items} oi
                    LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE oi.order_id = %d
                    AND p.post_type = 'fluent-products'
                    AND p.post_author IS NOT NULL
                ", $row->id);
                
                $seller_ids = $wpdb->get_col($sellers_sql);
                $sellers = [];
                foreach ($seller_ids as $seller_id) {
                    if ($seller_id) {
                        $seller_user = get_userdata($seller_id);
                        if ($seller_user) {
                            $sellers[] = [
                                'id' => $seller_user->ID,
                                'name' => $seller_user->display_name ?: $seller_user->user_login
                            ];
                        }
                    }
                }

                // Determine payment status
                $payment_status = 'pending';
                if (!empty($row->payment_status)) {
                    $payment_status = $row->payment_status;
                } elseif (!empty($row->status) && $row->status === 'completed') {
                    $payment_status = 'paid';
                }

                // Format total (FluentCart stores in cents)
                $total = $row->total_amount ?? 0;
                if (is_numeric($total) && $total > 10000) {
                    // Likely in cents, convert
                    $total = $total / 100;
                }

                $data[] = [
                    'id' => (int)$row->id,
                    'order_number' => '#' . $row->id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email, 
                    'status' => $row->status ?? 'pending',
                    'payment_status' => $payment_status,
                    'total' => $total,
                    'item_count' => $item_count,
                    'sellers' => $sellers,
                    'currency' => $row->currency ?? 'TWD',
                    'created_at' => $row->created_at ?? ''
                ];
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => count($data)
            ]
        ], 200);
    }
    
    public function get_item($request) {
        $order_id = $request->get_param('id');
        
        global $wpdb;
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        $table_customers = $wpdb->prefix . 'fct_customers';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, c.first_name, c.last_name, c.email 
             FROM {$table_orders} o
             LEFT JOIN {$table_customers} c ON o.customer_id = c.id
             WHERE o.id = %d",
            $order_id
        ));
        
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '訂單不存在'
            ], 404);
        }
        
        // 取得訂單項目
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.post_title as product_name
             FROM {$table_items} oi
             LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
             WHERE oi.order_id = %d",
            $order_id
        ), ARRAY_A);
        
        // 取得賣家資訊
        $sellers_sql = $wpdb->prepare("
            SELECT DISTINCT p.post_author
            FROM {$table_items} oi
            LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
            WHERE oi.order_id = %d
            AND p.post_type = 'fluent-products'
            AND p.post_author IS NOT NULL
        ", $order_id);
        
        $seller_ids = $wpdb->get_col($sellers_sql);
        $sellers = [];
        foreach ($seller_ids as $seller_id) {
            $seller_user = get_userdata($seller_id);
            if ($seller_user) {
                $sellers[] = [
                    'id' => $seller_user->ID,
                    'name' => $seller_user->display_name ?: $seller_user->user_login
                ];
            }
        }
        
        // 格式化總額
        $total = $order->total_amount ?? 0;
        if (is_numeric($total) && $total > 10000) {
            $total = $total / 100;
        }
        
        $customer_name = 'Guest';
        if (!empty($order->first_name) || !empty($order->last_name)) {
            $customer_name = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'id' => (int)$order->id,
                'order_number' => '#' . $order->id,
                'customer_name' => $customer_name,
                'customer_email' => $order->email ?? '',
                'status' => $order->status ?? 'pending',
                'payment_status' => $order->payment_status ?? 'pending',
                'total' => $total,
                'currency' => $order->currency ?? 'TWD',
                'created_at' => $order->created_at ?? '',
                'item_count' => count($items),
                'items' => $items,
                'sellers' => $sellers
            ]
        ], 200);
    }
    
    /**
     * 更新訂單
     */
    public function update_item(WP_REST_Request $request) {
        $order_id = $request->get_param('id');
        $params = $request->get_json_params();
        
        global $wpdb;
        $table_orders = $wpdb->prefix . 'fct_orders';
        
        // 檢查訂單是否存在
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_orders} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '訂單不存在'
            ], 404);
        }
        
        $update_data = [];
        $update_format = [];
        
        // 更新訂單狀態
        if (isset($params['status'])) {
            $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
            if (in_array($params['status'], $valid_statuses)) {
                $update_data['status'] = sanitize_text_field($params['status']);
                $update_format[] = '%s';
                
                // 如果狀態改為 completed，設定 completed_at
                if ($params['status'] === 'completed' && empty($order->completed_at)) {
                    $update_data['completed_at'] = current_time('mysql');
                    $update_format[] = '%s';
                }
            }
        }
        
        // 更新付款狀態
        if (isset($params['payment_status'])) {
            $valid_payment_statuses = ['pending', 'paid', 'refunded', 'failed', 'partially_paid', 'partially_refunded'];
            if (in_array($params['payment_status'], $valid_payment_statuses)) {
                $update_data['payment_status'] = sanitize_text_field($params['payment_status']);
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '沒有需要更新的資料'
            ], 400);
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $wpdb->update(
            $table_orders,
            $update_data,
            ['id' => $order_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '更新失敗'
            ], 500);
        }
        
        // 觸發 WordPress action
        do_action('buygo_order_updated', $order_id, $update_data, $order);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => '訂單已更新'
        ], 200);
    }
}
