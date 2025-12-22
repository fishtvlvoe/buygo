<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

class WorkflowLogger {

    private $table_name;
    private $workflow_id;

    public function __construct($workflow_id = null) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'buygo_workflow_logs';
        $this->workflow_id = $workflow_id ?: $this->generate_workflow_id();
    }

    /**
     * 生成唯一的流程 ID
     */
    private function generate_workflow_id() {
        return 'wf_' . time() . '_' . wp_generate_password(8, false);
    }

    /**
     * 記錄流程步驟
     */
    public function log_step($step_name, $step_order, $data = []) {
        global $wpdb;

        $status = $data['status'] ?? 'pending';
        $message = $data['message'] ?? null;
        $error_message = $data['error'] ?? null;
        $metadata = isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null;

        $wpdb->insert(
            $this->table_name,
            [
                'workflow_id' => $this->workflow_id,
                'workflow_type' => $data['workflow_type'] ?? 'product_upload',
                'step_name' => $step_name,
                'step_order' => $step_order,
                'status' => $status,
                'product_id' => $data['product_id'] ?? null,
                'feed_id' => $data['feed_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'line_user_id' => $data['line_user_id'] ?? null,
                'message' => $message,
                'error_message' => $error_message,
                'metadata' => $metadata,
                'started_at' => current_time('mysql'),
                'completed_at' => $status === 'completed' || $status === 'failed' ? current_time('mysql') : null,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * 更新步驟狀態
     */
    public function update_step($step_name, $status, $data = []) {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'completed_at' => current_time('mysql'),
        ];

        if (isset($data['error'])) {
            $update_data['error_message'] = $data['error'];
        }

        if (isset($data['message'])) {
            $update_data['message'] = $data['message'];
        }

        if (isset($data['product_id'])) {
            $update_data['product_id'] = $data['product_id'];
        }

        if (isset($data['feed_id'])) {
            $update_data['feed_id'] = $data['feed_id'];
        }

        if (isset($data['metadata'])) {
            $update_data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        // 先檢查記錄是否存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE workflow_id = %s AND step_name = %s LIMIT 1",
            $this->workflow_id,
            $step_name
        ));

        if ($existing) {
            // 記錄存在，執行更新
            $result = $wpdb->update(
            $this->table_name,
            $update_data,
            [
                'workflow_id' => $this->workflow_id,
                'step_name' => $step_name,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s'],
            ['%s', '%s']
        );
        } else {
            // 記錄不存在，需要先找到 step_order（從其他步驟推斷或使用預設值）
            $step_order = $data['step_order'] ?? 4; // 預設為 4（FluentCommunity 貼文發布）
            
            // 如果沒有提供 step_order，嘗試從其他步驟推斷
            if (!isset($data['step_order'])) {
                $max_order = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(step_order) FROM {$this->table_name} WHERE workflow_id = %s",
                    $this->workflow_id
                ));
                $step_order = $max_order ? ($max_order + 1) : 4;
            }
            
            // 創建新記錄
            $log_data_for_insert = array_merge($data, [
                'status' => $status,
                'workflow_type' => $data['workflow_type'] ?? 'product_upload'
            ]);
            $result = $this->log_step($step_name, $step_order, $log_data_for_insert);
        }

        return $result;
    }

    /**
     * 取得流程的所有步驟
     */
    public function get_workflow_steps($workflow_id = null) {
        global $wpdb;

        $workflow_id = $workflow_id ?: $this->workflow_id;

        $steps = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE workflow_id = %s ORDER BY step_order ASC, started_at ASC",
                $workflow_id
            ),
            ARRAY_A
        );
        
        return $steps;
    }

    /**
     * 取得最近的流程列表
     */
    public static function get_recent_workflows($limit = 50, $workflow_type = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_workflow_logs';

        $where = '';
        $params = [];

        if ($workflow_type) {
            $where = 'WHERE workflow_type = %s';
            $params[] = $workflow_type;
        }

        $query = "
            SELECT 
                workflow_id,
                workflow_type,
                MAX(step_order) as total_steps,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_steps,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_steps,
                MIN(started_at) as started_at,
                MAX(completed_at) as finished_at,
                MAX(CASE WHEN status = 'failed' THEN error_message END) as last_error
            FROM {$table_name}
            {$where}
            GROUP BY workflow_id, workflow_type
            ORDER BY started_at DESC
            LIMIT %d
        ";

        if ($workflow_type) {
            $params[] = $limit;
            return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        } else {
            return $wpdb->get_results($wpdb->prepare($query, [$limit]), ARRAY_A);
        }
    }

    /**
     * 取得流程 ID
     */
    public function get_workflow_id() {
        return $this->workflow_id;
    }
}

// 註冊 WordPress hooks 讓其他插件可以自動記錄流程
add_action('buygo_workflow_start', function($workflow_type, $line_user_id = null) {
    if (class_exists('\BuyGo\Core\Services\WorkflowLoggerHelper')) {
        $workflow_id = \BuyGo\Core\Services\WorkflowLoggerHelper::start_workflow($workflow_type, $line_user_id);
        do_action('buygo_workflow_id_generated', $workflow_id, $workflow_type);
        return $workflow_id;
    }
}, 10, 2);

add_action('buygo_workflow_log_step', function($workflow_id, $step_name, $step_order, $status = 'pending', $data = []) {
    if (class_exists('\BuyGo\Core\Services\WorkflowLoggerHelper')) {
        \BuyGo\Core\Services\WorkflowLoggerHelper::log_step($workflow_id, $step_name, $step_order, $status, $data);
    }
}, 10, 5);

add_action('buygo_workflow_update_step', function($workflow_id, $step_name, $status, $data = []) {
    if (class_exists('\BuyGo\Core\Services\WorkflowLoggerHelper')) {
        \BuyGo\Core\Services\WorkflowLoggerHelper::update_step($workflow_id, $step_name, $status, $data);
    }
}, 10, 4);
