<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;

class SearchController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/search', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search_all'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    /**
     * Global Search - Search across all data types
     */
    public function search_all(WP_REST_Request $request) {
        $query = $request->get_param('q');
        
        if (empty($query) || strlen($query) < 2) {
            return new WP_REST_Response([
                'success' => true,
                'data' => []
            ], 200);
        }

        $results = [];

        // 1. Search Users
        $users = $this->search_users($query);
        $results = array_merge($results, $users);

        // 2. Search Orders
        $orders = $this->search_orders($query);
        $results = array_merge($results, $orders);

        // 3. Search Products
        $products = $this->search_products($query);
        $results = array_merge($results, $products);

        // 4. Search Settings/Pages
        $pages = $this->search_pages($query);
        $results = array_merge($results, $pages);

        return new WP_REST_Response([
            'success' => true,
            'data' => $results
        ], 200);
    }

    /**
     * Search Users
     */
    private function search_users($query) {
        // Use WP_User_Query for better compatibility and search capabilities
        $args = [
            'number' => 5,
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name', 'user_nicename'],
            'orderby' => 'registered',
            'order' => 'DESC'
        ];
        
        $user_query = new \WP_User_Query($args);
        $users = $user_query->get_results();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => 'user_' . $user->ID,
                'type' => 'user',
                'type_label' => '會員',
                'title' => $user->display_name ?: $user->user_login,
                'subtitle' => $user->user_email,
                'link' => '/members/list?user=' . $user->ID
            ];
        }

        return $results;
    }

    /**
     * Search Orders
     */
    private function search_orders($query) {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_orders}'") !== $table_orders) {
            return [];
        }

        $is_numeric = is_numeric($query);
        
        $where = [];
        $params = [];

        if ($is_numeric) {
            $where[] = "id = %d";
            $params[] = intval($query);
        } else {
            $where[] = "(billing_first_name LIKE %s OR billing_last_name LIKE %s OR billing_email LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($query) . '%';
            $params[] = '%' . $wpdb->esc_like($query) . '%';
            $params[] = '%' . $wpdb->esc_like($query) . '%';
        }

        $where_sql = implode(' OR ', $where);
        $sql = "SELECT id, billing_first_name, billing_last_name, billing_email, total_amount, status, created_at
                FROM {$table_orders}
                WHERE {$where_sql}
                ORDER BY id DESC
                LIMIT 5";

        if (!empty($params)) {
            $orders = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $orders = $wpdb->get_results($sql);
        }

        $results = [];
        foreach ($orders as $order) {
            $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            if (empty($customer_name)) {
                $customer_name = 'Guest';
            }

            $results[] = [
                'id' => 'order_' . $order->id,
                'type' => 'order',
                'type_label' => '訂單',
                'title' => '訂單 #' . $order->id,
                'subtitle' => $customer_name . ' • NT$ ' . number_format(($order->total_amount ?? 0) / 100, 0),
                'link' => '/products-orders?tab=orders&order=' . $order->id
            ];
        }

        return $results;
    }

    /**
     * Search Products
     */
    private function search_products($query) {
        global $wpdb;
        
        $table_posts = $wpdb->posts;
        
        $products = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_status
            FROM {$table_posts}
            WHERE post_type = 'product'
            AND (post_title LIKE %s OR ID = %d)
            AND post_status IN ('publish', 'draft', 'pending')
            ORDER BY ID DESC
            LIMIT 5
        ", '%' . $wpdb->esc_like($query) . '%', is_numeric($query) ? intval($query) : 0));

        $results = [];
        foreach ($products as $product) {
            $price = get_post_meta($product->ID, '_price', true) ?: 0;
            $formatted_price = 'NT$ ' . number_format((float)$price / 100, 0);

            $results[] = [
                'id' => 'product_' . $product->ID,
                'type' => 'product',
                'type_label' => '產品',
                'title' => $product->post_title,
                'subtitle' => $formatted_price . ' • ' . ($product->post_status === 'publish' ? '已發布' : '草稿'),
                'link' => '/products-orders?tab=products&product=' . $product->ID
            ];
        }

        return $results;
    }

    /**
     * Search Pages/Settings
     */
    private function search_pages($query) {
        $pages = [
            ['title' => '儀表板', 'link' => '/', 'type' => 'setting'],
            ['title' => '會員管理', 'link' => '/members', 'type' => 'setting'],
            ['title' => '產品訂單', 'link' => '/products-orders', 'type' => 'setting'],
            ['title' => '報告', 'link' => '/reports', 'type' => 'setting'],
            ['title' => '訊息中心', 'link' => '/messages', 'type' => 'setting'],
            ['title' => '全域設定', 'link' => '/settings', 'type' => 'setting'],
            ['title' => 'LINE Messaging API', 'link' => '/settings/line', 'type' => 'setting'],
            ['title' => 'Fluent 整合', 'link' => '/settings/fluent', 'type' => 'setting'],
        ];

        $results = [];
        $query_lower = strtolower($query);
        
        foreach ($pages as $page) {
            if (stripos($page['title'], $query) !== false) {
                $results[] = [
                    'id' => 'page_' . md5($page['link']),
                    'type' => 'setting',
                    'type_label' => '頁面',
                    'title' => $page['title'],
                    'subtitle' => '導航至 ' . $page['title'],
                    'link' => $page['link']
                ];
            }
        }

        return $results;
    }
}
