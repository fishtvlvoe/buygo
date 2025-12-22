<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;

class ReportController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/reports/(?P<type>[a-zA-Z0-9-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_report'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    /**
     * Get Report Data
     */
    public function get_report(WP_REST_Request $request) {
        $type = $request->get_param('type');
        $days = $request->get_param('days') ?: 365;
        
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        switch ($type) {
            case 'overview':
                return $this->get_overview_report($start_date, $end_date, $days);
            case 'sales':
            case 'orders':
            case 'income':
            case 'refunds':
            case 'subscriptions':
            case 'products':
            case 'customers':
            case 'sources':
                // Placeholder for other report types
                return new WP_REST_Response([
                    'success' => true,
                    'data' => []
                ], 200);
            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid report type'
                ], 400);
        }
    }

    /**
     * Get Overview Report
     */
    private function get_overview_report($start_date, $end_date, $days) {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_applications = $wpdb->prefix . 'buygo_seller_applications';
        $table_notifications = $wpdb->prefix . 'buygo_notification_logs';
        
        // Total Revenue (Summary)
        $total_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$table_orders}
            WHERE status IN ('completed', 'processing')
            AND created_at >= %s
            AND created_at <= %s
        ", $start_date, $end_date));
        
        // Previous period for comparison
        $prev_start = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($start_date)));
        $prev_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$table_orders}
            WHERE status IN ('completed', 'processing')
            AND created_at >= %s
            AND created_at < %s
        ", $prev_start, $start_date));
        
        $revenue_growth = $prev_revenue > 0 
            ? round((($total_revenue - $prev_revenue) / $prev_revenue) * 100, 1)
            : 0;
        
        // Quarterly Revenue (last 90 days)
        $quarter_start = date('Y-m-d H:i:s', strtotime('-90 days'));
        $quarter_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$table_orders}
            WHERE status IN ('completed', 'processing')
            AND created_at >= %s
            AND created_at <= %s
        ", $quarter_start, $end_date));
        
        $prev_quarter_start = date('Y-m-d H:i:s', strtotime('-180 days'));
        $prev_quarter_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$table_orders}
            WHERE status IN ('completed', 'processing')
            AND created_at >= %s
            AND created_at < %s
        ", $prev_quarter_start, $quarter_start));
        
        $quarter_growth = $prev_quarter_revenue > 0
            ? round((($quarter_revenue - $prev_quarter_revenue) / $prev_quarter_revenue) * 100, 1)
            : 0;
        
        // Additional Stats
        $stats = [];
        
        // Total Orders
        $total_orders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_orders}
            WHERE created_at >= %s
            AND created_at <= %s
        ", $start_date, $end_date));
        
        $prev_orders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_orders}
            WHERE created_at >= %s
            AND created_at < %s
        ", $prev_start, $start_date));
        
        $orders_change = $prev_orders > 0
            ? round((($total_orders - $prev_orders) / $prev_orders) * 100, 1)
            : 0;
        
        $stats[] = [
            'key' => 'orders',
            'label' => '總訂單數',
            'value' => number_format($total_orders),
            'change' => $orders_change
        ];
        
        // Total Customers
        $total_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT id)
            FROM {$table_customers}
            WHERE created_at >= %s
            AND created_at <= %s
        ", $start_date, $end_date));
        
        $prev_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT id)
            FROM {$table_customers}
            WHERE created_at >= %s
            AND created_at < %s
        ", $prev_start, $start_date));
        
        $customers_change = $prev_customers > 0
            ? round((($total_customers - $prev_customers) / $prev_customers) * 100, 1)
            : 0;
        
        $stats[] = [
            'key' => 'customers',
            'label' => '新增顧客',
            'value' => number_format($total_customers),
            'change' => $customers_change
        ];
        
        // Pending Applications
        $pending_applications = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$table_applications}
            WHERE status = 'pending'
        ");
        
        $stats[] = [
            'key' => 'applications',
            'label' => '待審核申請',
            'value' => number_format($pending_applications),
            'change' => null
        ];
        
        // Notification Count
        $notification_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_notifications}
            WHERE created_at >= %s
            AND created_at <= %s
        ", $start_date, $end_date));
        
        $stats[] = [
            'key' => 'notifications',
            'label' => '訊息發送量',
            'value' => number_format($notification_count / 1000, 1) . 'k',
            'change' => null
        ];
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'summary' => [
                    'total' => (int)$total_revenue,
                    'growth' => $revenue_growth
                ],
                'quarterly' => [
                    'total' => (int)$quarter_revenue,
                    'growth' => $quarter_growth
                ],
                'stats' => $stats
            ]
        ], 200);
    }
}
