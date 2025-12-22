<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;
use BuyGo\Core\Services\WorkflowLogger;

class WorkflowController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/workflows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_workflows'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/workflows/(?P<id>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_workflow_detail'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/workflows/(?P<id>[a-zA-Z0-9_-]+)/export', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_workflow_log'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    public function check_permission() {
        return current_user_can('manage_options');
    }

    /**
     * 取得流程列表
     */
    public function get_workflows(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';
        
        $type = $request->get_param('type');
        $status = $request->get_param('status');
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, min(100, intval($request->get_param('per_page') ?: 10)));
        $offset = ($page - 1) * $per_page;

        // 建立 WHERE 條件
        $where_conditions = [];
        $params = [];

        if ($type) {
            $where_conditions[] = 'workflow_type = %s';
            $params[] = $type;
        }

        $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // 先計算總數
        $count_query = "
            SELECT COUNT(DISTINCT workflow_id) as total
            FROM {$table_name}
            {$where_sql}
        ";
        
        if (!empty($params)) {
            $total = $wpdb->get_var($wpdb->prepare($count_query, $params));
        } else {
            $total = $wpdb->get_var($count_query);
        }

        // 取得流程列表
        $query = "
            SELECT 
                workflow_id,
                workflow_type,
                MAX(step_order) as max_step_order,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_steps,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_steps,
                MIN(started_at) as started_at,
                MAX(completed_at) as finished_at,
                MAX(CASE WHEN status = 'failed' THEN error_message END) as last_error,
                MAX(line_user_id) as line_user_id
            FROM {$table_name}
            {$where_sql}
            GROUP BY workflow_id, workflow_type
            ORDER BY started_at DESC
            LIMIT %d OFFSET %d
        ";

        $query_params = array_merge($params, [$per_page, $offset]);
        
        if (!empty($params)) {
            $workflows = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        } else {
            $workflows = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
        }

        // 計算成功率和狀態，並加入用戶資訊
        foreach ($workflows as &$workflow) {
            // 根據 workflow_type 決定總步驟數
            $expected_total_steps = $this->get_expected_total_steps($workflow['workflow_type']);
            
            // 使用預期的總步驟數，而不是 MAX(step_order)
            $workflow['total_steps'] = $expected_total_steps;
            
            $workflow['success_rate'] = $expected_total_steps > 0 
                ? round(($workflow['completed_steps'] / $expected_total_steps) * 100, 1)
                : 0;
            $workflow['status'] = $workflow['failed_steps'] > 0 ? 'failed' : ($workflow['completed_steps'] == $expected_total_steps ? 'completed' : 'processing');
            
            // 加入用戶資訊
            if (!empty($workflow['line_user_id'])) {
                $user_info = $this->get_user_info_by_line_uid($workflow['line_user_id']);
                $workflow['user_info'] = $user_info;
            } else {
                $workflow['user_info'] = null;
            }
        }

        // 如果指定了狀態篩選，在這裡過濾
        if ($status) {
            $workflows = array_filter($workflows, function($w) use ($status) {
                return $w['status'] === $status;
            });
            // 重新計算總數（基於篩選後的結果）
            $total = count($workflows);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => array_values($workflows),
            'pagination' => [
                'total' => (int) $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => ceil($total / $per_page)
            ]
        ], 200);
    }

    /**
     * 取得流程詳情
     */
    public function get_workflow_detail(WP_REST_Request $request) {
        global $wpdb;
        $workflow_id = $request->get_param('id');
        
        $logger = new WorkflowLogger($workflow_id);
        $steps = $logger->get_workflow_steps($workflow_id);

        // 取得商品資訊（從第一個有 product_id 的步驟）
        $product_id = null;
        $product_info = null;
        foreach ($steps as $step) {
            if (!empty($step['product_id'])) {
                $product_id = $step['product_id'];
                break;
            }
        }

        if ($product_id) {
            $product = get_post($product_id);
            if ($product) {
                $product_info = [
                    'id' => $product_id,
                    'name' => $product->post_title,
                    'permalink' => get_permalink($product_id)
                ];
            }
        }

        // 解析 metadata 並去重複步驟（只對相同的 step_name 和 step_order 去重，保留最新的記錄）
        $seen_steps = [];
        foreach ($steps as $step) {
            // 使用 step_name 和 step_order 作為唯一 key（因為同一個步驟可能被記錄多次）
            $step_key = $step['step_name'] . '_' . $step['step_order'];
            
            // 如果已經看過這個步驟，比較時間戳，保留最新的
            if (isset($seen_steps[$step_key])) {
                $existing_time = strtotime($seen_steps[$step_key]['started_at'] ?? '1970-01-01');
                $current_time = strtotime($step['started_at'] ?? '1970-01-01');
                if ($current_time > $existing_time) {
                    // 替換為更新的記錄
                    $seen_steps[$step_key] = $step;
                }
            } else {
                $seen_steps[$step_key] = $step;
            }
        }
        
        // 將去重後的步驟轉回陣列並解析 metadata
        $steps = array_values($seen_steps);
        
        foreach ($steps as &$step) {
            if ($step['metadata']) {
                $step['metadata'] = json_decode($step['metadata'], true);
            }
        }

        // 取得用戶資訊（從第一個有 line_user_id 的步驟）
        $line_user_id = null;
        foreach ($steps as $step) {
            if (!empty($step['line_user_id'])) {
                $line_user_id = $step['line_user_id'];
                break;
            }
        }
        
        $user_info = null;
        if ($line_user_id) {
            $user_info = $this->get_user_info_by_line_uid($line_user_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow_id,
                'steps' => $steps,
                'product_info' => $product_info,
                'user_info' => $user_info
            ]
        ], 200);
    }

    /**
     * 匯出流程 debug log
     */
    public function export_workflow_log(WP_REST_Request $request) {
        global $wpdb;
        $workflow_id = $request->get_param('id');
        
        if (empty($workflow_id)) {
            return new WP_Error('missing_workflow_id', '流程 ID 不能為空', ['status' => 400]);
        }
        
        $logger = new WorkflowLogger($workflow_id);
        $steps = $logger->get_workflow_steps($workflow_id);
        
        // 取得用戶資訊
        $line_user_id = null;
        foreach ($steps as $step) {
            if (!empty($step['line_user_id'])) {
                $line_user_id = $step['line_user_id'];
                break;
            }
        }
        
        $user_info = null;
        if ($line_user_id) {
            $user_info = $this->get_user_info_by_line_uid($line_user_id);
        }
        
        // 取得商品資訊
        $product_id = null;
        $product_info = null;
        foreach ($steps as $step) {
            if (!empty($step['product_id'])) {
                $product_id = $step['product_id'];
                break;
            }
        }
        
        if ($product_id) {
            $product = get_post($product_id);
            if ($product) {
                $product_info = [
                    'id' => $product_id,
                    'name' => $product->post_title,
                    'permalink' => get_permalink($product_id)
                ];
            }
        }
        
        // 建立 debug log 內容
        $log_data = [
            'workflow_id' => $workflow_id,
            'exported_at' => current_time('mysql'),
            'user_info' => $user_info,
            'product_info' => $product_info,
            'steps' => $steps,
            'summary' => [
                'total_steps' => count($steps),
                'completed_steps' => count(array_filter($steps, function($s) { return $s['status'] === 'completed'; })),
                'failed_steps' => count(array_filter($steps, function($s) { return $s['status'] === 'failed'; })),
                'processing_steps' => count(array_filter($steps, function($s) { return $s['status'] === 'processing'; }))
            ]
        ];
        
        // 回傳 JSON 格式的 log
        $response = new WP_REST_Response($log_data, 200);
        $response->header('Content-Type', 'application/json');
        $response->header('Content-Disposition', 'attachment; filename="workflow_' . $workflow_id . '_' . date('Y-m-d_His') . '.json"');
        
        return $response;
    }

    /**
     * 根據 workflow_type 取得預期的總步驟數
     */
    private function get_expected_total_steps($workflow_type) {
        $expected_steps = [
            'product_upload' => 5, // 1.接收LINE訊息, 2.解析圖片與文字, 3.建立產品, 4.社群貼文發布, 5.LINE成功訊息回傳
            'order_notification' => 2, // 可根據實際需求調整
        ];
        
        return $expected_steps[$workflow_type] ?? 0;
    }

    /**
     * 根據 LINE UID 取得用戶資訊
     */
    private function get_user_info_by_line_uid($line_user_id) {
        if (empty($line_user_id)) {
            return null;
        }

        // 嘗試使用 LineService 取得用戶資訊
        if (class_exists('\BuyGo\Core\Services\LineService')) {
            try {
                $line_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\LineService::class);
                $user = $line_service->get_user_by_line_uid($line_user_id);
                
                if ($user) {
                    return [
                        'user_id' => $user->ID,
                        'display_name' => $user->display_name,
                        'user_email' => $user->user_email,
                        'line_uid' => $line_user_id,
                        'line_name' => $this->get_line_name($line_user_id)
                    ];
                }
            } catch (\Exception $e) {
                // LineService 可能不存在或出錯，繼續使用其他方法
            }
        }

        // 如果找不到 WordPress 用戶，至少回傳 LINE UID 和名稱
        $line_name = $this->get_line_name($line_user_id);
        return [
            'user_id' => null,
            'display_name' => $line_name,
            'user_email' => null,
            'line_uid' => $line_user_id,
            'line_name' => $line_name
        ];
    }

    /**
     * 取得 LINE 名稱（從 Nextend Social Login 或其他來源）
     */
    private function get_line_name($line_user_id) {
        global $wpdb;
        
        // 嘗試從 Nextend Social Login 取得
        $nsl_table = $wpdb->prefix . 'social_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '$nsl_table'") === $nsl_table) {
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$nsl_table} WHERE identifier = %s AND type = 'line' LIMIT 1",
                $line_user_id
            ));
            
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    return $user->display_name;
                }
            }
        }
        
        return null;
    }
}
