<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_User;

class NotificationController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'page' => ['default' => 1],
                'per_page' => ['default' => 20],
                'search' => ['default' => ''],
                'status' => ['default' => ''],
                'channel' => ['default' => ''],
            ]
        ]);

        register_rest_route($this->namespace, '/notifications/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_summary'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/notifications/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_as_read'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route($this->namespace, '/notifications/mark-all-read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_all_as_read'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    public function get_items(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_notification_logs';
        
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $search = $request->get_param('search');
        
        // 支援複選：可能是字串或陣列
        $status_param = $request->get_param('status');
        $status = is_array($status_param) ? $status_param : (!empty($status_param) ? [$status_param] : []);
        
        $channel_param = $request->get_param('channel');
        $channel = is_array($channel_param) ? $channel_param : (!empty($channel_param) ? [$channel_param] : []);

        $offset = ($page - 1) * $per_page;
        $where = ["1=1"];
        $params = [];

        // 支援多個狀態篩選
        if (!empty($status) && is_array($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '%s'));
            $where[] = "status IN ($placeholders)";
            $params = array_merge($params, $status);
        }

        // 支援多個管道篩選
        if (!empty($channel) && is_array($channel)) {
            $placeholders = implode(',', array_fill(0, count($channel), '%s'));
            $where[] = "channel IN ($placeholders)";
            $params = array_merge($params, $channel);
        }

        if (!empty($search)) {
            $where[] = "(title LIKE %s OR message LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where);
        
        $total_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $items_query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY sent_at DESC LIMIT %d OFFSET %d";
        
        // 準備查詢參數
        $query_params = [];
        if (!empty($params)) {
            $query_params = $params;
        }
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        // 執行查詢
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, $params));
            $items = $wpdb->get_results($wpdb->prepare($items_query, $query_params));
        } else {
            $total_items = $wpdb->get_var($total_query);
            $items = $wpdb->get_results($wpdb->prepare($items_query, $per_page, $offset));
        }

        // Format Items
        $formatted_items = [];
        foreach ($items as $item) {
            $user = get_userdata($item->user_id);
            
            // 取得訂單資訊（如果有）
            $order_info = null;
            if ($item->order_id) {
                // 嘗試從 FluentCart 取得訂單資訊
                if (class_exists('\FluentCart\App\Models\Order')) {
                    try {
                        $order = \FluentCart\App\Models\Order::find($item->order_id);
                        if ($order) {
                            $order_info = [
                                'id' => $order->id,
                                'order_number' => $order->order_number ?? '',
                                'total' => $order->total_amount ?? 0,
                                'status' => $order->status ?? ''
                            ];
                        }
                    } catch (\Exception $e) {
                        // 如果無法取得訂單資訊，就只使用 order_id
                    }
                }
            }
            
            $formatted_items[] = [
                'id' => (int) $item->id,
                'sent_at' => $item->sent_at,
                'status' => $item->status, // sent, failed
                'channel' => $item->channel, // line, email
                'title' => $item->title,
                'message' => $item->message,
                'recipient' => $user ? [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => get_avatar_url($user->ID)
                ] : null,
                'order_id' => $item->order_id ? (int) $item->order_id : null,
                'order_info' => $order_info,
                'meta' => json_decode($item->meta, true)
            ];
        }

        return new WP_REST_Response([
            'items' => $formatted_items,
            'total' => (int) $total_items,
            'total_pages' => ceil($total_items / $per_page)
        ], 200);
    }

    /**
     * Get Notification Summary (for header dropdown)
     */
    public function get_summary() {
        global $wpdb;
        $user_id = get_current_user_id();
        $reads_table = $wpdb->prefix . 'buygo_notification_reads';
        
        $notifications = [];
        
        // 1. 最新訂單（最近 5 筆）
        $table_orders = $wpdb->prefix . 'fct_orders';
        $recent_orders = $wpdb->get_results("
            SELECT id, order_number, billing_first_name, billing_last_name, total_amount, status, created_at
            FROM {$table_orders}
            ORDER BY id DESC
            LIMIT 5
        ");
        
        foreach ($recent_orders as $order) {
            $notification_id = 'order_' . $order->id;
            $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            if (empty($customer_name)) {
                $customer_name = 'Guest';
            }
            
            // 檢查是否已讀
            $is_read = $wpdb->get_var($wpdb->prepare(
                "SELECT is_read FROM {$reads_table} WHERE user_id = %d AND notification_id = %s",
                $user_id,
                $notification_id
            ));
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'order',
                'title' => '新訂單',
                'message' => $customer_name . ' 建立了新訂單 #' . $order->id,
                'time' => $this->format_time($order->created_at),
                'timestamp' => strtotime($order->created_at),
                'link' => '/products-orders?tab=orders&order=' . $order->id,
                'icon' => 'order',
                'unread' => !$is_read
            ];
        }
        
        // 2. 產品上架（最近 5 筆）- 修正查詢使用正確的 post_type
        $table_posts = $wpdb->posts;
        $recent_products = $wpdb->get_results("
            SELECT ID, post_title, post_date, post_author
            FROM {$table_posts}
            WHERE post_type = 'fluent-products'
            AND post_status = 'publish'
            ORDER BY ID DESC
            LIMIT 5
        ");
        
        foreach ($recent_products as $product) {
            $author = get_userdata($product->post_author);
            $author_name = $author ? $author->display_name : '系統';
            $notification_id = 'product_' . $product->ID;
            
            // 檢查是否已讀
            $is_read = $wpdb->get_var($wpdb->prepare(
                "SELECT is_read FROM {$reads_table} WHERE user_id = %d AND notification_id = %s",
                $user_id,
                $notification_id
            ));
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'product',
                'title' => '產品上架',
                'message' => $author_name . ' 上架了「' . $product->post_title . '」',
                'time' => $this->format_time($product->post_date),
                'timestamp' => strtotime($product->post_date),
                'link' => '/products-orders?tab=products&product=' . $product->ID,
                'icon' => 'product',
                'unread' => !$is_read
            ];
        }
        
        // 3. 活動訊息（賣家申請、小幫手變更等）
        $table_applications = $wpdb->prefix . 'buygo_seller_applications';
        $recent_applications = $wpdb->get_results("
            SELECT id, user_id, status, created_at
            FROM {$table_applications}
            WHERE status = 'pending'
            ORDER BY id DESC
            LIMIT 3
        ");
        
        foreach ($recent_applications as $app) {
            $user = get_userdata($app->user_id);
            $user_name = $user ? $user->display_name : '未知使用者';
            $notification_id = 'application_' . $app->id;
            
            // 檢查是否已讀
            $is_read = $wpdb->get_var($wpdb->prepare(
                "SELECT is_read FROM {$reads_table} WHERE user_id = %d AND notification_id = %s",
                $user_id,
                $notification_id
            ));
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'activity',
                'title' => '新賣家申請',
                'message' => $user_name . ' 申請開通賣家權限',
                'time' => $this->format_time($app->created_at),
                'timestamp' => strtotime($app->created_at),
                'link' => '/members/applications',
                'icon' => 'activity',
                'unread' => !$is_read
            ];
        }
        
        // Sort by timestamp (newest first)
        usort($notifications, function($a, $b) {
            $timestamp_a = $a['timestamp'] ?? 0;
            $timestamp_b = $b['timestamp'] ?? 0;
            return $timestamp_b - $timestamp_a;
        });
        
        // Limit to 10 most recent
        $notifications = array_slice($notifications, 0, 10);
        
        $unread_count = count(array_filter($notifications, function($n) {
            return $n['unread'] === true;
        }));
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]
        ], 200);
    }

    /**
     * 標記單一通知為已讀
     */
    public function mark_as_read(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $notification_id = $request->get_param('notification_id');
        $notification_type = $request->get_param('notification_type') ?: 'order';
        
        if (empty($notification_id)) {
            return new WP_Error('missing_param', '缺少 notification_id 參數', ['status' => 400]);
        }
        
        $reads_table = $wpdb->prefix . 'buygo_notification_reads';
        
        // 檢查是否已存在記錄
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$reads_table} WHERE user_id = %d AND notification_id = %s",
            $user_id,
            $notification_id
        ));
        
        if ($existing) {
            // 更新為已讀
            $wpdb->update(
                $reads_table,
                [
                    'is_read' => 1,
                    'read_at' => current_time('mysql')
                ],
                [
                    'user_id' => $user_id,
                    'notification_id' => $notification_id
                ],
                ['%d', '%s'],
                ['%d', '%s']
            );
        } else {
            // 新增記錄
            $wpdb->insert(
                $reads_table,
                [
                    'user_id' => $user_id,
                    'notification_id' => $notification_id,
                    'notification_type' => $notification_type,
                    'is_read' => 1,
                    'read_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%d', '%s']
            );
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => '已標記為已讀'
        ], 200);
    }

    /**
     * 標記所有通知為已讀
     */
    public function mark_all_as_read(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $reads_table = $wpdb->prefix . 'buygo_notification_reads';
        
        // 取得所有未讀的通知 ID（從 summary 中）
        $summary = $this->get_summary();
        $summary_data = $summary->get_data();
        
        if (isset($summary_data['data']['notifications'])) {
            foreach ($summary_data['data']['notifications'] as $notification) {
                if ($notification['unread']) {
                    $notification_id = $notification['id'];
                    $notification_type = $notification['type'];
                    
                    // 檢查是否已存在記錄
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$reads_table} WHERE user_id = %d AND notification_id = %s",
                        $user_id,
                        $notification_id
                    ));
                    
                    if ($existing) {
                        // 更新為已讀
                        $wpdb->update(
                            $reads_table,
                            [
                                'is_read' => 1,
                                'read_at' => current_time('mysql')
                            ],
                            [
                                'user_id' => $user_id,
                                'notification_id' => $notification_id
                            ],
                            ['%d', '%s'],
                            ['%d', '%s']
                        );
                    } else {
                        // 新增記錄
                        $wpdb->insert(
                            $reads_table,
                            [
                                'user_id' => $user_id,
                                'notification_id' => $notification_id,
                                'notification_type' => $notification_type,
                                'is_read' => 1,
                                'read_at' => current_time('mysql')
                            ],
                            ['%d', '%s', '%s', '%d', '%s']
                        );
                    }
                }
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => '已標記所有通知為已讀'
        ], 200);
    }

    private function format_time($datetime) {
        if (empty($datetime)) {
            return '未知時間';
        }
        
        $timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return '剛剛';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' 分鐘前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' 小時前';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' 天前';
        } else {
            return date('Y-m-d', $timestamp);
        }
    }
}
