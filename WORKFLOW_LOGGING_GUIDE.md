# 流程日誌使用指南

## 概述

流程監控系統可以追蹤 LINE 上架流程的每個步驟，幫助您快速定位問題。

## 在代碼中使用

### 範例：商品上架流程

```php
use BuyGo\Core\Services\WorkflowLoggerHelper;

// 1. 開始流程
$workflow_id = WorkflowLoggerHelper::start_workflow('product_upload', $line_user_id);

// 2. 記錄步驟 1：接收 LINE 訊息
WorkflowLoggerHelper::log_step($workflow_id, 'line_message_received', 1, 'completed', [
    'line_user_id' => $line_user_id,
    'message' => '已接收 LINE 訊息'
]);

// 3. 記錄步驟 2：解析圖片與文字
WorkflowLoggerHelper::log_step($workflow_id, 'parse_image_text', 2, 'processing', [
    'line_user_id' => $line_user_id
]);

try {
    // 解析邏輯...
    $product_data = parse_image_and_text($image, $text);
    
    // 更新步驟為成功
    WorkflowLoggerHelper::update_step($workflow_id, 'parse_image_text', 'completed', [
        'message' => '成功解析商品資料',
        'metadata' => ['product_name' => $product_data['name']]
    ]);
} catch (Exception $e) {
    // 更新步驟為失敗
    WorkflowLoggerHelper::update_step($workflow_id, 'parse_image_text', 'failed', [
        'error' => $e->getMessage()
    ]);
    return;
}

// 4. 記錄步驟 3：FluentCart 商品建立
WorkflowLoggerHelper::log_step($workflow_id, 'fluentcart_create', 3, 'processing', [
    'line_user_id' => $line_user_id
]);

try {
    $product_result = $cartService->createProduct($product_data, $line_user_id);
    
    if ($product_result['success']) {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcart_create', 'completed', [
            'product_id' => $product_result['product_id'],
            'message' => 'FluentCart 商品建立成功'
        ]);
    } else {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcart_create', 'failed', [
            'error' => $product_result['error'] ?? '未知錯誤'
        ]);
    }
} catch (Exception $e) {
    WorkflowLoggerHelper::update_step($workflow_id, 'fluentcart_create', 'failed', [
        'error' => $e->getMessage()
    ]);
}

// 5. 記錄步驟 4：FluentCommunity 貼文發布
WorkflowLoggerHelper::log_step($workflow_id, 'fluentcommunity_post', 4, 'processing', [
    'product_id' => $product_result['product_id'] ?? null,
    'line_user_id' => $line_user_id
]);

try {
    $feed_result = $communityService->publishProductPost($product_data);
    
    if ($feed_result['success']) {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcommunity_post', 'completed', [
            'feed_id' => $feed_result['feed_id'] ?? null,
            'message' => 'FluentCommunity 貼文發布成功'
        ]);
    } else {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcommunity_post', 'failed', [
            'error' => $feed_result['error'] ?? '未知錯誤'
        ]);
    }
} catch (Exception $e) {
    WorkflowLoggerHelper::update_step($workflow_id, 'fluentcommunity_post', 'failed', [
        'error' => $e->getMessage()
    ]);
}

// 6. 記錄步驟 5：LINE 成功訊息回傳
WorkflowLoggerHelper::log_step($workflow_id, 'line_success_notify', 5, 'processing', [
    'line_user_id' => $line_user_id
]);

try {
    $lineService->sendProductCard($line_user_id, $product_data);
    WorkflowLoggerHelper::update_step($workflow_id, 'line_success_notify', 'completed', [
        'message' => 'LINE 成功訊息已發送'
    ]);
} catch (Exception $e) {
    WorkflowLoggerHelper::update_step($workflow_id, 'line_success_notify', 'failed', [
        'error' => $e->getMessage()
    ]);
}
```

## 標準步驟名稱

建議使用以下標準步驟名稱：

- `line_message_received` - 接收 LINE 訊息
- `parse_image_text` - 解析圖片與文字
- `fluentcart_create` - FluentCart 商品建立
- `fluentcommunity_post` - FluentCommunity 貼文發布
- `line_success_notify` - LINE 成功訊息回傳
- `order_created` - 訂單建立
- `order_notify_seller` - 通知賣家訂單

## 狀態值

- `pending` - 等待中
- `processing` - 處理中
- `completed` - 已完成
- `failed` - 失敗

## 在後台查看

1. 前往「訊息」→「流程監控」
2. 查看所有流程記錄
3. 點擊流程查看詳細步驟
4. 查看錯誤訊息和詳細資料
