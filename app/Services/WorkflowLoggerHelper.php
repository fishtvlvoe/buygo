<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * WorkflowLoggerHelper
 * 
 * 提供簡單的 helper 函數，讓其他插件可以輕鬆記錄流程步驟
 */
class WorkflowLoggerHelper {

    /**
     * 開始一個新的流程
     */
    public static function start_workflow($workflow_type = 'product_upload', $line_user_id = null) {
        $logger = new WorkflowLogger();
        return $logger->get_workflow_id();
    }

    /**
     * 記錄流程步驟（簡化版本）
     */
    public static function log_step($workflow_id, $step_name, $step_order, $status = 'pending', $data = []) {
        $logger = new WorkflowLogger($workflow_id);
        
        $log_data = array_merge($data, [
            'status' => $status,
            'workflow_type' => $data['workflow_type'] ?? 'product_upload'
        ]);
        
        return $logger->log_step($step_name, $step_order, $log_data);
    }

    /**
     * 更新步驟狀態
     */
    public static function update_step($workflow_id, $step_name, $status, $data = []) {
        $logger = new WorkflowLogger($workflow_id);
        return $logger->update_step($step_name, $status, $data);
    }
}
