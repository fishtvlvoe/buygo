<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

use BuyGo\Core\Contracts\LineWebhookHandlerInterface;

/**
 * LINE Webhook Handler
 * 
 * 接收 LINE Messaging API Webhook，處理商品上傳訊息
 */
class LineWebhookHandler implements LineWebhookHandlerInterface
{
    private ProductDataParser $productParser;
    private ImageProcessor $imageProcessor;
    private $workflowLogger;

    public function __construct()
    {
        $this->productParser = new ProductDataParser();
        $this->imageProcessor = new ImageProcessor();
    }

    /**
     * 處理 Webhook 請求
     */
    public function handleWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        // 寫入檔案 log（不依賴 WordPress debug.log）
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "\n=== {$timestamp} ===\n", FILE_APPEND);
        file_put_contents($logFile, "Received request\n", FILE_APPEND);
        file_put_contents($logFile, "Body: " . $request->get_body() . "\n", FILE_APPEND);
        
        // 驗證簽章
        if (!$this->verifySignature($request)) {
            file_put_contents($logFile, "Invalid signature\n", FILE_APPEND);
            return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
        }

        $body = $request->get_json_params();
        $events = $body['events'] ?? [];
        
        file_put_contents($logFile, "Events count: " . count($events) . "\n", FILE_APPEND);

        foreach ($events as $event) {
            $eventType = $event['type'] ?? 'unknown';
            file_put_contents($logFile, "Processing event type: {$eventType}\n", FILE_APPEND);
            $this->handleEvent($event);
        }

        return new \WP_REST_Response(['success' => true, 'processed' => count($events)], 200);
    }

    /**
     * 驗證 LINE 簽章
     */
    private function verifySignature(\WP_REST_Request $request): bool
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        $channelSecret = get_option('mygo_line_channel_secret', '');
        file_put_contents($logFile, "verifySignature - channelSecret length: " . strlen($channelSecret) . "\n", FILE_APPEND);
        
        if (empty($channelSecret)) {
            file_put_contents($logFile, "verifySignature - No channel secret, SKIPPING verification\n", FILE_APPEND);
            return true; // 開發模式，未設定 secret 時跳過驗證
        }

        $signature = $request->get_header('X-Line-Signature');
        file_put_contents($logFile, "verifySignature - X-Line-Signature: " . ($signature ?: 'empty') . "\n", FILE_APPEND);
        
        if (empty($signature)) {
            file_put_contents($logFile, "verifySignature - No signature header, FAILED\n", FILE_APPEND);
            return false;
        }

        $body = $request->get_body();
        $hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
        
        file_put_contents($logFile, "verifySignature - Expected hash: {$hash}\n", FILE_APPEND);
        file_put_contents($logFile, "verifySignature - Received signature: {$signature}\n", FILE_APPEND);

        $isValid = hash_equals($hash, $signature);
        file_put_contents($logFile, "verifySignature - Result: " . ($isValid ? 'PASS' : 'FAIL') . "\n", FILE_APPEND);
        
        return $isValid;
    }

    /**
     * 處理單一事件
     */
    private function handleEvent(array $event): void
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        $type = $event['type'] ?? '';
        
        file_put_contents($logFile, "handleEvent - type: {$type}\n", FILE_APPEND);

        switch ($type) {
            case 'message':
                file_put_contents($logFile, "handleEvent - calling handleMessage\n", FILE_APPEND);
                try {
                    $this->handleMessage($event);
                    file_put_contents($logFile, "handleEvent - handleMessage completed\n", FILE_APPEND);
                } catch (\Exception $e) {
                    file_put_contents($logFile, "handleEvent - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
                }
                break;
            case 'follow':
                $this->handleFollow($event);
                break;
            case 'unfollow':
                $this->handleUnfollow($event);
                break;
        }
    }

    /**
     * 處理訊息事件
     */
    public function handleMessage(array $event): void
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        $messageType = $event['message']['type'] ?? '';
        $replyToken = $event['replyToken'] ?? '';
        $userId = $event['source']['userId'] ?? '';

        file_put_contents($logFile, "handleMessage - userId: {$userId}\n", FILE_APPEND);
        file_put_contents($logFile, "handleMessage - messageType: {$messageType}\n", FILE_APPEND);

        // 檢查是否為賣家
        $isSeller = $this->isSellerUser($userId);
        file_put_contents($logFile, "handleMessage - isSeller: " . ($isSeller ? 'true' : 'false') . "\n", FILE_APPEND);
        
        if (!$isSeller) {
            file_put_contents($logFile, "User is not seller, sending permission denied\n", FILE_APPEND);
            $this->sendReply($replyToken, '您沒有上傳商品的權限。請先在網站完成帳號綁定，並確認您的角色為「賣家」。');
            return;
        }

        switch ($messageType) {
            case 'text':
                file_put_contents($logFile, "Calling handleTextMessage\n", FILE_APPEND);
                $this->handleTextMessage($event, $replyToken, $userId);
                break;
            case 'image':
                file_put_contents($logFile, "Calling handleImageMessage\n", FILE_APPEND);
                $this->handleImageMessage($event, $replyToken, $userId);
                break;
        }
    }

    /**
     * 處理文字訊息
     */
    private function handleTextMessage(array $event, string $replyToken, string $userId): void
    {
        $text = trim($event['message']['text'] ?? '');
        error_log('MYGO Webhook: handleTextMessage - text = ' . $text);
        
        // 恢復 WorkflowLogger（如果有暫存）
        $savedWorkflowId = get_transient('mygo_workflow_id_' . $userId);
        if ($savedWorkflowId) {
            $this->workflowLogger = new \BuyGo\Core\Services\WorkflowLogger($savedWorkflowId);
        }
        
        // 檢查是否為指令
        $isCommand = $this->handleCommand($text, $replyToken, $userId);
        error_log('MYGO Webhook: handleTextMessage - isCommand = ' . ($isCommand ? 'true' : 'false'));
        
        if ($isCommand) {
            return;
        }
        
        // 取得暫存的圖片 attachment_id
        $pendingImageAttachmentId = get_transient('mygo_pending_image_' . $userId);
        error_log('MYGO Webhook: handleTextMessage - pendingImageAttachmentId = ' . ($pendingImageAttachmentId ?: 'null'));

        // 步驟 4: 接收商品資料
        if ($this->workflowLogger) {
            $this->workflowLogger->log_step('接收商品資料', 4, [
                'status' => 'completed',
                'line_user_id' => $userId,
                'message' => '用戶回傳商品資訊文字',
                'workflow_type' => 'product_upload'
            ]);
        }
        
        // 解析商品資料
        $productData = $this->parseProductData($text, null);
        
        // 如果有暫存的圖片 attachment_id，加入商品資料
        if (!empty($pendingImageAttachmentId)) {
            $productData['image_attachment_id'] = $pendingImageAttachmentId;
            error_log('MYGO Webhook: handleTextMessage - added image_attachment_id to productData');
        }
        
        // 驗證資料
        if (!$this->validateProductData($productData)) {
            $validation = $this->productParser->validate($productData);
            $missingNames = $this->productParser->getMissingFieldNames($validation['missing']);
            
            if ($this->workflowLogger) {
                $this->workflowLogger->log_step('資料驗證失敗', 5, [
                    'status' => 'failed',
                    'line_user_id' => $userId,
                    'error' => '商品資料不完整：' . implode('、', $missingNames),
                    'workflow_type' => 'product_upload'
                ]);
            }
            
            $this->sendReply($replyToken, '商品資料不完整，缺少：' . implode('、', $missingNames));
            return;
        }

        // 建立商品
        $result = $this->createProduct($productData, $userId);

        if ($result['success']) {
            delete_transient('mygo_pending_image_' . $userId);
            delete_transient('mygo_workflow_id_' . $userId);
            
            $feedUrl = $result['feed_url'] ?? '';
            
            // 準備模板變數
            $template_args = [
                'product_name' => $productData['name'] ?? '',
                'price' => number_format($productData['price'] ?? 0),
                'quantity' => $productData['quantity'] ?? 0,
                'product_url' => '',
                'community_url' => !empty($feedUrl) ? "\n\n📱 社群貼文連結：\n{$feedUrl}\n\n商品卡片已發送，可以轉發給朋友！" : '',
                'category_section' => '',
                'arrival_date_section' => '',
                'preorder_date_section' => '',
            ];
            
            // 使用 NotificationTemplates 系統讀取自訂模板
            $template = \BuyGo\Core\Services\NotificationTemplates::get('system_product_published', $template_args);
            $message = $template && isset($template['line']['text']) 
                ? $template['line']['text'] 
                : "✅ 商品「{$productData['name']}」已成功上架！\n\n💰 價格：NT$ " . number_format($productData['price']) . "\n📦 數量：{$productData['quantity']} 個" . (!empty($feedUrl) ? "\n\n📱 社群貼文連結：\n{$feedUrl}\n\n商品卡片已發送，可以轉發給朋友！" : '');
            
            $this->sendReply($replyToken, $message);
        } else {
            // 使用 NotificationTemplates 系統讀取自訂模板
            $template_args = [
                'error_message' => $result['error'] ?? '未知錯誤',
            ];
            $template = \BuyGo\Core\Services\NotificationTemplates::get('system_product_publish_failed', $template_args);
            $message = $template && isset($template['line']['text']) 
                ? $template['line']['text'] 
                : '❌ 商品上架失敗：' . ($result['error'] ?? '未知錯誤');
            
            $this->sendReply($replyToken, $message);
        }
    }

    /**
     * 處理圖片訊息
     */
    private function handleImageMessage(array $event, string $replyToken, string $userId): void
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        // 啟動 WorkflowLogger
        $this->workflowLogger = new \BuyGo\Core\Services\WorkflowLogger();
        $workflowId = $this->workflowLogger->get_workflow_id();
        
        // 步驟 1: 接收圖片訊息
        $this->workflowLogger->log_step('接收圖片', 1, [
            'status' => 'completed',
            'line_user_id' => $userId,
            'message' => '用戶透過 LINE 傳送商品圖片',
            'workflow_type' => 'product_upload'
        ]);
        
        try {
            $messageId = $event['message']['id'] ?? '';
            file_put_contents($logFile, "handleImageMessage - START\n", FILE_APPEND);
            file_put_contents($logFile, "handleImageMessage - messageId: {$messageId}\n", FILE_APPEND);
            file_put_contents($logFile, "handleImageMessage - WorkflowID: {$workflowId}\n", FILE_APPEND);
            
            // 下載圖片
            file_put_contents($logFile, "handleImageMessage - calling getImageContent\n", FILE_APPEND);
            $tempFile = $this->getImageContent($messageId);
            file_put_contents($logFile, "handleImageMessage - tempFile: " . ($tempFile ?: 'null') . "\n", FILE_APPEND);
            
            if (!$tempFile) {
                file_put_contents($logFile, "handleImageMessage - ERROR: no tempFile\n", FILE_APPEND);
                $this->sendReply($replyToken, '無法取得圖片，請重新上傳');
                return;
            }

            // 步驟 2: 處理圖片（壓縮、上傳）
            $this->workflowLogger->log_step('處理圖片', 2, [
                'status' => 'pending',
                'line_user_id' => $userId,
                'message' => '開始處理圖片（壓縮、上傳至媒體庫）',
                'workflow_type' => 'product_upload'
            ]);
            
            file_put_contents($logFile, "handleImageMessage - calling processImage\n", FILE_APPEND);
            $result = $this->imageProcessor->processImage($tempFile);
            file_put_contents($logFile, "handleImageMessage - processImage result: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            if (!$result['success']) {
                file_put_contents($logFile, "handleImageMessage - ERROR: processImage failed\n", FILE_APPEND);
                
                $this->workflowLogger->update_step('處理圖片', 'failed', [
                    'error' => $result['error'] ?? '圖片處理失敗',
                    'line_user_id' => $userId
                ]);
                
                $this->sendReply($replyToken, '圖片處理失敗：' . ($result['error'] ?? '未知錯誤'));
                return;
            }
            
            $this->workflowLogger->update_step('處理圖片', 'completed', [
                'message' => '圖片處理完成，attachment_id: ' . ($result['attachment_id'] ?? 'N/A'),
                'line_user_id' => $userId,
                'metadata' => ['attachment_id' => $result['attachment_id'] ?? null]
            ]);

            // 暫存圖片 attachment_id，等待商品資訊
            $attachmentId = $result['attachment_id'];
            set_transient('mygo_pending_image_' . $userId, $attachmentId, 3600);
            set_transient('mygo_workflow_id_' . $userId, $workflowId, 3600); // 保存 workflow_id
            file_put_contents($logFile, "handleImageMessage - saved attachment_id: {$attachmentId}\n", FILE_APPEND);

            // 步驟 3: 發送引導訊息
            $this->workflowLogger->log_step('發送引導訊息', 3, [
                'status' => 'completed',
                'line_user_id' => $userId,
                'message' => '引導用戶輸入商品資料',
                'workflow_type' => 'product_upload'
            ]);

            $this->sendReply($replyToken, "圖片已上傳成功！\n請接著輸入商品資訊：\n\n商品名稱\n價格:XXX\n數量:XXX\n到貨:YYYY-MM-DD");
            file_put_contents($logFile, "handleImageMessage - DONE\n", FILE_APPEND);
            
        } catch (\Exception $e) {
            file_put_contents($logFile, "handleImageMessage - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "handleImageMessage - TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            $this->sendReply($replyToken, '圖片處理時發生錯誤，請稍後再試');
        }
    }

    /**
     * 從 LINE 取得圖片內容
     */
    private function getImageContent(string $messageId): ?string
    {
        $accessToken = get_option('mygo_line_channel_access_token', '');
        if (empty($accessToken)) {
            error_log('MYGO Webhook: getImageContent - no access token');
            return null;
        }

        $url = "https://api-data.line.me/v2/bot/message/{$messageId}/content";
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('MYGO Webhook: getImageContent - request error: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('MYGO Webhook: getImageContent - empty response body');
            return null;
        }

        // 建立暫存檔
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . '/mygo-temp';
        
        // 確保暫存目錄存在
        if (!file_exists($tempDir)) {
            wp_mkdir_p($tempDir);
        }
        
        $tempFile = $tempDir . '/line_img_' . uniqid() . '.jpg';
        
        $result = file_put_contents($tempFile, $body);
        
        if ($result === false) {
            error_log('MYGO Webhook: getImageContent - failed to save temp file');
            return null;
        }
        
        error_log('MYGO Webhook: getImageContent - saved to temp file: ' . $tempFile . ' (' . $result . ' bytes)');

        return $tempFile;
    }

    /**
     * 解析商品資料
     */
    public function parseProductData(string $message, ?string $imageUrl): array
    {
        return $this->productParser->parse($message, $imageUrl);
    }

    /**
     * 驗證商品資料
     */
    public function validateProductData(array $data): bool
    {
        $result = $this->productParser->validate($data);
        return $result['valid'];
    }

    /**
     * 建立商品並發布到社群
     */
    private function createProduct(array $data, string $lineUserId): array
    {
        do_action('mygo/product/creating', $data, $lineUserId);

        // 步驟 5: 建立 FluentCart 產品
        if ($this->workflowLogger) {
            $this->workflowLogger->log_step('建立商品', 5, [
                'status' => 'pending',
                'line_user_id' => $lineUserId,
                'message' => '開始在 FluentCart 建立商品',
                'workflow_type' => 'product_upload'
            ]);
        }

        // 1. 在 FluentCart 建立商品
        $cartService = new FluentCartService();
        $productResult = $cartService->createProduct($data, $lineUserId);

        if (!$productResult['success']) {
            if ($this->workflowLogger) {
                $this->workflowLogger->update_step('建立商品', 'failed', [
                    'error' => $productResult['error'] ?? '商品建立失敗',
                    'line_user_id' => $lineUserId
                ]);
            }
            return $productResult;
        }
        
        if ($this->workflowLogger) {
            $this->workflowLogger->update_step('建立商品', 'completed', [
                'product_id' => $productResult['product_id'],
                'message' => '商品建立成功: ' . ($data['name'] ?? 'N/A'),
                'line_user_id' => $lineUserId
            ]);
        }

        $productId = $productResult['product_id'];
        $data['id'] = $productId;

        // 步驟 6: 發布到社群
        if ($this->workflowLogger) {
            $this->workflowLogger->log_step('發布社群貼文', 6, [
                'status' => 'pending',
                'product_id' => $productId,
                'line_user_id' => $lineUserId,
                'message' => '開始發布到 FluentCommunity',
                'workflow_type' => 'product_upload'
            ]);
        }

        // 2. 在 FluentCommunity 發布商品貼文
        $communityService = new FluentCommunityService();
        $feedResult = $communityService->publishProductPost($data);

        if (!$feedResult['success']) {
            // 商品已建立但貼文失敗，記錄錯誤但不影響流程
            error_log('MYGO: Failed to publish feed for product ' . $productId . ': ' . $feedResult['error']);
            
            if ($this->workflowLogger) {
                $this->workflowLogger->update_step('發布社群貼文', 'failed', [
                    'error' => $feedResult['error'] ?? '社群貼文發布失敗',
                    'product_id' => $productId,
                    'line_user_id' => $lineUserId
                ]);
            }
        } else {
            if ($this->workflowLogger) {
                $this->workflowLogger->update_step('發布社群貼文', 'completed', [
                    'feed_id' => $feedResult['feed_id'] ?? null,
                    'product_id' => $productId,
                    'message' => '社群貼文發布成功',
                    'line_user_id' => $lineUserId
                ]);
            }
        }

        $feedId = $feedResult['feed_id'] ?? 0;
        $feedUrl = $this->getFeedUrl($feedId);
        $data['url'] = $feedUrl;
        $data['community_url'] = $feedUrl; // 社群貼文連結
        
        // 確保有圖片 URL（從 attachment_id 取得）
        if (!empty($data['image_attachment_id']) && empty($data['image_url'])) {
            $data['image_url'] = wp_get_attachment_url($data['image_attachment_id']);
        }

        // 步驟 7: 發送成功通知
        if ($this->workflowLogger) {
            $this->workflowLogger->log_step('發送通知', 7, [
                'status' => 'completed',
                'product_id' => $productId,
                'feed_id' => $feedId,
                'line_user_id' => $lineUserId,
                'message' => '發送商品卡片與成功訊息給用戶',
                'workflow_type' => 'product_upload'
            ]);
        }

        // 3. 發送 LINE Flex Message 卡片
        $lineService = new LineMessageService();
        $lineService->sendProductCard($lineUserId, $data);
        
        // 4. 發送純文字訊息（可複製到 LINE 社群）
        $textMessage = $this->buildProductTextMessage($data);
        $lineService->sendTextMessage($lineUserId, $textMessage);

        // 5. 廣播給所有追蹤者（可選）
        $this->broadcastProductCard($data);

        return [
            'success' => true,
            'product_id' => $productId,
            'feed_id' => $feedId,
            'feed_url' => $feedUrl,
        ];
    }

    /**
     * 取得貼文 URL
     */
    private function getFeedUrl(int $feedId): string
    {
        if (!$feedId) {
            return home_url();
        }
        
        // 嘗試從 FluentCommunity 取得正確的 URL
        if (defined('FLUENT_COMMUNITY_PLUGIN_VERSION') && class_exists('\FluentCommunity\App\Models\Feed')) {
            $feed = \FluentCommunity\App\Models\Feed::find($feedId);
            if ($feed) {
                return $feed->getPermalink();
            }
        }
        
        // 備用：使用 Helper::baseUrl
        if (class_exists('\FluentCommunity\App\Services\Helper')) {
            return \FluentCommunity\App\Services\Helper::baseUrl('post/' . $feedId);
        }
        
        return home_url('/portal/post/' . $feedId);
    }

    /**
     * 廣播商品卡片給所有追蹤者
     */
    private function broadcastProductCard(array $product): void
    {
        // 取得所有已綁定 LINE 的使用者
        global $wpdb;
        $lineUsers = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_mygo_line_uid' AND meta_value != ''"
        );

        if (empty($lineUsers)) {
            return;
        }

        $lineService = new LineMessageService();
        
        foreach ($lineUsers as $lineUid) {
            $lineService->sendProductCard($lineUid, $product);
        }
    }

    /**
     * 發送回覆訊息
     */
    public function sendReply(string $replyToken, string $message): void
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        // 檢查 LINE 訊息通知是否啟用
        if (class_exists('\BuyGo\Core\Services\SettingsService')) {
            $settings_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\SettingsService::class);
            $line_message_enabled = $settings_service->get('line_message_enabled', true);
            
            if (!$line_message_enabled) {
                file_put_contents($logFile, "sendReply - SKIPPED: LINE message notification is disabled\n", FILE_APPEND);
                error_log('LineWebhookHandler: LINE message notification is disabled, skipping reply message');
                return;
            }
        } else {
            // 回退到舊系統檢查
            $settings = get_option('buygo_core_settings', []);
            $line_message_enabled = isset($settings['line_message_enabled']) ? $settings['line_message_enabled'] : true;
            
            if (!$line_message_enabled) {
                file_put_contents($logFile, "sendReply - SKIPPED: LINE message notification is disabled\n", FILE_APPEND);
                error_log('LineWebhookHandler: LINE message notification is disabled, skipping reply message');
                return;
            }
        }
        
        $accessToken = get_option('mygo_line_channel_access_token', '');
        file_put_contents($logFile, "sendReply - accessToken length: " . strlen($accessToken) . "\n", FILE_APPEND);
        file_put_contents($logFile, "sendReply - replyToken: {$replyToken}\n", FILE_APPEND);
        file_put_contents($logFile, "sendReply - message: {$message}\n", FILE_APPEND);
        
        if (empty($accessToken) || empty($replyToken)) {
            file_put_contents($logFile, "sendReply - SKIPPED: empty accessToken or replyToken\n", FILE_APPEND);
            return;
        }

        $url = 'https://api.line.me/v2/bot/message/reply';
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => json_encode([
                'replyToken' => $replyToken,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ],
            ]),
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            file_put_contents($logFile, "sendReply - ERROR: " . $response->get_error_message() . "\n", FILE_APPEND);
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            file_put_contents($logFile, "sendReply - Response code: {$code}\n", FILE_APPEND);
            file_put_contents($logFile, "sendReply - Response body: {$body}\n", FILE_APPEND);
        }
    }

    /**
     * 檢查是否為賣家
     */
    private function isSellerUser(string $lineUserId): bool
    {
        $validator = new UserProfileValidator();
        $wpUserId = $validator->findUserByLineUid($lineUserId);

        error_log('MYGO Webhook: isSellerUser - lineUserId = ' . $lineUserId);
        error_log('MYGO Webhook: isSellerUser - wpUserId = ' . ($wpUserId ?: 'not found'));

        if (!$wpUserId) {
            error_log('MYGO Webhook: isSellerUser - No WP user found for this LINE UID');
            return false;
        }

        $role = get_user_meta($wpUserId, '_mygo_role', true);
        error_log('MYGO Webhook: isSellerUser - role = ' . ($role ?: 'empty'));
        
        return in_array($role, ['seller', 'admin'], true);
    }

    /**
     * 處理指令
     * 
     * @return bool 是否已處理指令
     */
    private function handleCommand(string $text, string $replyToken, string $userId): bool
    {
        $helpCommands = ['上架', '新增商品', '幫助', '說明', 'help', '?', '？'];
        $statusCommands = ['我的商品', '商品列表', '查詢'];
        
        $lowerText = mb_strtolower($text);
        error_log('MYGO Webhook: handleCommand - text = ' . $text . ', lowerText = ' . $lowerText);
        
        // 幫助指令
        if (in_array($lowerText, $helpCommands, true)) {
            error_log('MYGO Webhook: handleCommand - matched help command');
            $this->sendProductFormatHelp($replyToken);
            return true;
        }
        
        // 查詢商品指令
        if (in_array($lowerText, $statusCommands, true)) {
            error_log('MYGO Webhook: handleCommand - matched status command');
            $this->sendProductList($replyToken, $userId);
            return true;
        }
        
        error_log('MYGO Webhook: handleCommand - no command matched');
        return false;
    }
    
    /**
     * 發送商品格式說明
     */
    private function sendProductFormatHelp(string $replyToken): void
    {
        $message = "📦 商品上架說明\n";
        $message .= "━━━━━━━━━━━━━━\n\n";
        $message .= "【步驟 1】先傳送商品圖片\n\n";
        $message .= "【步驟 2】再傳送商品資訊\n";
        $message .= "格式如下：\n\n";
        $message .= "商品名稱\n";
        $message .= "價格：299\n";
        $message .= "數量：10\n";
        $message .= "到貨：01/25\n";
        $message .= "（其他說明文字）\n\n";
        $message .= "━━━━━━━━━━━━━━\n";
        $message .= "✅ 必填：名稱、價格、數量\n";
        $message .= "📝 選填：到貨日期、預購截止、類型\n\n";
        $message .= "💡 範例：\n";
        $message .= "日本薯條三兄弟\n";
        $message .= "價格：350\n";
        $message .= "數量：20\n";
        $message .= "到貨：01/25\n";
        $message .= "超好吃限量供應！";
        
        $this->sendReply($replyToken, $message);
    }
    
    /**
     * 發送商品列表
     */
    private function sendProductList(string $replyToken, string $userId): void
    {
        $validator = new UserProfileValidator();
        $wpUserId = $validator->findUserByLineUid($userId);
        
        if (!$wpUserId) {
            $this->sendReply($replyToken, '請先完成帳號綁定');
            return;
        }
        
        // 取得賣家的商品（這裡需要根據實際的 FluentCart API 調整）
        $cartService = new FluentCartService();
        $products = $cartService->getSellerProducts($wpUserId, 5);
        
        if (empty($products)) {
            $this->sendReply($replyToken, "您目前沒有上架的商品\n\n輸入「上架」查看上架說明");
            return;
        }
        
        $message = "📦 您的商品列表\n";
        $message .= "━━━━━━━━━━━━━━\n\n";
        
        foreach ($products as $index => $product) {
            $message .= ($index + 1) . ". {$product['title']}\n";
            $message .= "   💰 NT$ " . number_format($product['price']) . "\n";
            $message .= "   📦 庫存：{$product['stock_quantity']}\n\n";
        }
        
        $this->sendReply($replyToken, $message);
    }

    /**
     * 處理追蹤事件
     */
    private function handleFollow(array $event): void
    {
        $userId = $event['source']['userId'] ?? '';
        $replyToken = $event['replyToken'] ?? '';

        $message = "🎉 歡迎使用 BuyGo！\n\n";
        $message .= "📱 買家功能：\n";
        $message .= "• 在社群貼文下留言 +1 即可下單\n\n";
        $message .= "🏪 賣家功能：\n";
        $message .= "• 輸入「上架」查看商品上架說明\n";
        $message .= "• 輸入「我的商品」查看已上架商品\n\n";
        $message .= "如果您是賣家，請先在網站完成帳號綁定。";
        
        $this->sendReply($replyToken, $message);
    }

    /**
     * 處理取消追蹤事件
     */
    private function handleUnfollow(array $event): void
    {
        // 記錄取消追蹤
        $userId = $event['source']['userId'] ?? '';
        do_action('mygo/line/unfollow', $userId);
    }
    
    /**
     * 建立商品純文字訊息（可複製到 LINE 社群）
     */
    private function buildProductTextMessage(array $product): string
    {
        $productCode = $product['code'] ?? '';
        $price = intval($product['price'] ?? 0);
        $quantity = intval($product['quantity'] ?? 0);
        $feedUrl = $product['community_url'] ?? $product['url'] ?? '';
        
        $message = "✅ 商品「{$productCode}」已成功上架！\n\n";
        $message .= "💰 價格：NT$ " . number_format($price) . "\n";
        $message .= "📦 數量：{$quantity} 個\n";
        
        if (!empty($feedUrl)) {
            $message .= "\n📱 社群貼文連結：\n{$feedUrl}";
        }
        
        $message .= "\n\n👉 點擊留言 +1 立刻下單";
        
        return $message;
    }
}
