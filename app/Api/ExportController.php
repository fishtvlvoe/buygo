<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;

class ExportController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/export/(?P<type>[a-zA-Z]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_data'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    /**
     * Export Data
     */
    public function export_data(WP_REST_Request $request) {
        $type = $request->get_param('type');
        
        switch ($type) {
            case 'members':
                return $this->export_members();
            case 'products':
                return $this->export_products();
            case 'orders':
                return $this->export_orders();
            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid export type'
                ], 400);
        }
    }

    /**
     * Export Members
     */
    private function export_members() {
        global $wpdb;
        
        $users = get_users(['number' => -1]);
        
        $filename = 'buygo_members_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['ID', '姓名', 'Email', '帳號', '角色', '註冊日期']);
        
        // Data
        foreach ($users as $user) {
            $role = get_user_meta($user->ID, '_mygo_role', true) ?: 'buyer';
            $roles_map = [
                'buyer' => '買家',
                'seller' => '賣家',
                'helper' => '小幫手',
                'admin' => '管理員'
            ];
            
            fputcsv($output, [
                $user->ID,
                $user->display_name,
                $user->user_email,
                $user->user_login,
                $roles_map[$role] ?? $role,
                $user->user_registered
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export Products
     */
    private function export_products() {
        global $wpdb;
        
        $table_posts = $wpdb->posts;
        
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_status, p.post_date
            FROM {$table_posts} p
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'pending')
            ORDER BY p.ID DESC
        ");
        
        $filename = 'buygo_products_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['ID', '產品名稱', '價格', '庫存', '狀態', '建立日期']);
        
        // Data
        foreach ($products as $product) {
            $price = get_post_meta($product->ID, '_price', true) ?: 0;
            $stock = get_post_meta($product->ID, '_stock', true) ?: 0;
            $status_map = [
                'publish' => '已發布',
                'draft' => '草稿',
                'pending' => '審核中'
            ];
            
            fputcsv($output, [
                $product->ID,
                $product->post_title,
                number_format((float)$price / 100, 2),
                $stock,
                $status_map[$product->post_status] ?? $product->post_status,
                $product->post_date
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export Orders
     */
    private function export_orders() {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_orders}'") !== $table_orders) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Orders table not found'
            ], 404);
        }
        
        $orders = $wpdb->get_results("
            SELECT id, billing_first_name, billing_last_name, billing_email, 
                   total_amount, status, currency, created_at
            FROM {$table_orders}
            ORDER BY id DESC
            LIMIT 1000
        ");
        
        $filename = 'buygo_orders_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['訂單ID', '顧客姓名', 'Email', '總金額', '狀態', '建立日期']);
        
        // Data
        foreach ($orders as $order) {
            $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            if (empty($customer_name)) {
                $customer_name = 'Guest';
            }
            
            $status_map = [
                'completed' => '已完成',
                'processing' => '處理中',
                'pending' => '等待',
                'cancelled' => '已取消'
            ];
            
            fputcsv($output, [
                $order->id,
                $customer_name,
                $order->billing_email ?? '',
                number_format(($order->total_amount ?? 0) / 100, 2),
                $status_map[$order->status] ?? $order->status,
                $order->created_at
            ]);
        }
        
        fclose($output);
        exit;
    }
}
