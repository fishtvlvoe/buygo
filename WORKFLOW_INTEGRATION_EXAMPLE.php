<?php
/**
 * 流程日誌整合範例
 * 
 * 這個文件展示如何在 LINE 上架流程中整合流程日誌
 * 請將這些代碼整合到您的實際流程處理代碼中
 */

// 範例：在 LineWebhookHandler::createProduct 方法中

use BuyGo\Core\Services\WorkflowLoggerHelper;

private function createProduct(array $data, string $lineUserId): array
{
    // 開始流程日誌
    $workflow_id = WorkflowLoggerHelper::start_workflow('product_upload', $lineUserId);
    
    // 步驟 1：接收 LINE 訊息（在 handleWebhook 中已經記錄）
    // WorkflowLoggerHelper::log_step($workflow_id, 'line_message_received', 1, 'completed', [
    //     'line_user_id' => $lineUserId,
    //     'message' => '已接收 LINE 訊息'
    // ]);
    
    // 步驟 2：解析圖片與文字（在 handleWebhook 中已經記錄）
    // WorkflowLoggerHelper::log_step($workflow_id, 'parse_image_text', 2, 'completed', [
    //     'line_user_id' => $lineUserId,
    //     'message' => '成功解析商品資料'
    // ]);
    
    do_action('mygo/product/creating', $data, $lineUserId);

    // 步驟 3：在 FluentCart 建立商品
    WorkflowLoggerHelper::log_step($workflow_id, 'fluentcart_create', 3, 'processing', [
        'line_user_id' => $lineUserId,
        'workflow_type' => 'product_upload'
    ]);
    
    $cartService = new FluentCartService();
    $productResult = $cartService->createProduct($data, $lineUserId);

    if (!$productResult['success']) {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcart_create', 'failed', [
            'error' => $productResult['error'] ?? 'FluentCart 商品建立失敗'
        ]);
        return $productResult;
    }

    $productId = $productResult['product_id'];
    WorkflowLoggerHelper::update_step($workflow_id, 'fluentcart_create', 'completed', [
        'product_id' => $productId,
        'message' => 'FluentCart 商品建立成功'
    ]);
    
    $data['id'] = $productId;

    // 步驟 4：在 FluentCommunity 發布商品貼文
    WorkflowLoggerHelper::log_step($workflow_id, 'fluentcommunity_post', 4, 'processing', [
        'product_id' => $productId,
        'line_user_id' => $lineUserId,
        'workflow_type' => 'product_upload'
    ]);
    
    $communityService = new FluentCommunityService();
    $feedResult = $communityService->publishProductPost($data);

    if (!$feedResult['success']) {
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcommunity_post', 'failed', [
            'error' => $feedResult['error'] ?? 'FluentCommunity 貼文發布失敗'
        ]);
        error_log('MYGO: Failed to publish feed for product ' . $productId . ': ' . $feedResult['error']);
    } else {
        $feedId = $feedResult['feed_id'] ?? 0;
        WorkflowLoggerHelper::update_step($workflow_id, 'fluentcommunity_post', 'completed', [
            'feed_id' => $feedId,
            'message' => 'FluentCommunity 貼文發布成功'
        ]);
    }

    $feedId = $feedResult['feed_id'] ?? 0;
    $feedUrl = $this->getFeedUrl($feedId);
    $data['url'] = $feedUrl;
    $data['community_url'] = $feedUrl;
    
    if (!empty($data['image_attachment_id']) && empty($data['image_url'])) {
        $data['image_url'] = wp_get_attachment_url($data['image_attachment_id']);
    }

    // 步驟 5：發送 LINE Flex Message 卡片
    WorkflowLoggerHelper::log_step($workflow_id, 'line_success_notify', 5, 'processing', [
        'line_user_id' => $lineUserId,
        'product_id' => $productId,
        'workflow_type' => 'product_upload'
    ]);
    
    $lineService = new LineMessageService();
    
    try {
        $lineService->sendProductCard($lineUserId, $data);
        $lineService->sendTextMessage($lineUserId, $this->buildProductTextMessage($data));
        
        WorkflowLoggerHelper::update_step($workflow_id, 'line_success_notify', 'completed', [
            'message' => 'LINE 成功訊息已發送'
        ]);
    } catch (Exception $e) {
        WorkflowLoggerHelper::update_step($workflow_id, 'line_success_notify', 'failed', [
            'error' => $e->getMessage()
        ]);
    }

    $this->broadcastProductCard($data);

    return [
        'success' => true,
        'product_id' => $productId,
        'feed_id' => $feedId,
        'feed_url' => $feedUrl,
        'workflow_id' => $workflow_id, // 返回 workflow_id 供後續使用
    ];
}

// 範例：訂單通知流程
function handle_order_created($order_id, $order_data) {
    $workflow_id = WorkflowLoggerHelper::start_workflow('order_notification');
    
    // 步驟 1：訂單建立
    WorkflowLoggerHelper::log_step($workflow_id, 'order_created', 1, 'completed', [
        'order_id' => $order_id,
        'workflow_type' => 'order_notification'
    ]);
    
    // 步驟 2：通知賣家
    $seller_id = $order_data['seller_id'] ?? null;
    if ($seller_id) {
        WorkflowLoggerHelper::log_step($workflow_id, 'order_notify_seller', 2, 'processing', [
            'order_id' => $order_id,
            'user_id' => $seller_id,
            'workflow_type' => 'order_notification'
        ]);
        
        try {
            // 發送通知邏輯...
            send_order_notification_to_seller($seller_id, $order_id);
            
            WorkflowLoggerHelper::update_step($workflow_id, 'order_notify_seller', 'completed', [
                'message' => '賣家通知已發送'
            ]);
        } catch (Exception $e) {
            WorkflowLoggerHelper::update_step($workflow_id, 'order_notify_seller', 'failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
