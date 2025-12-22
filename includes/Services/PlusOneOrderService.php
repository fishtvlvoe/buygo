<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

use BuyGo\Core\Contracts\PlusOneOrderServiceInterface;
use BuyGo\Core\Core\Database;

/**
 * Plus One Order Service
 * 
 * 處理 +1 留言下單的完整流程
 */
class PlusOneOrderService implements PlusOneOrderServiceInterface
{
    private OrderCommandParser $commandParser;
    private UserProfileValidator $profileValidator;
    private FluentCartService $cartService;
    private FluentCommunityService $communityService;
    private LineMessageService $lineService;

    public function __construct()
    {
        $this->commandParser = new OrderCommandParser();
        $this->profileValidator = new UserProfileValidator();
        $this->cartService = new FluentCartService();
        $this->communityService = new FluentCommunityService();
        $this->lineService = new LineMessageService();
    }

    /**
     * 註冊 Hook
     */
    public static function register(): void
    {
        // 只在 FluentCommunity 存在時註冊
        if (!class_exists('FluentCommunity\App\App')) {
            return;
        }
        
        add_action('fluent_community/comment_added', function ($comment, $feed, $mentions = []) {
            $service = new self();
            $service->handleComment($comment, $feed);
        }, 10, 3);
    }

    /**
     * 處理留言事件
     */
    public function handleComment(object $comment, object $feed): void
    {
        error_log('MYGO PlusOneOrderService: handleComment - comment_id = ' . ($comment->id ?? 'null') . ', parent_id = ' . ($comment->parent_id ?? 'null'));
        
        // 只處理頂層留言，跳過所有回覆（避免無限迴圈）
        $parentId = $comment->parent_id ?? null;
        if ($parentId) {
            error_log('MYGO PlusOneOrderService: handleComment - skipping reply comment');
            return; // 這是回覆留言，不處理
        }
        
        $message = $comment->message ?? '';
        error_log('MYGO PlusOneOrderService: handleComment - message = ' . $message);
        
        // 解析下單指令
        $orderCommand = $this->parseOrderCommand($message);
        
        if (!$orderCommand) {
            error_log('MYGO PlusOneOrderService: handleComment - not an order command');
            return; // 不是下單指令
        }

        error_log('MYGO PlusOneOrderService: handleComment - order command detected: ' . json_encode($orderCommand));
        
        $userId = $comment->user_id ?? 0;
        $feedId = $feed->id ?? 0;

        // 檢查使用者是否登入
        if (!$userId) {
            $this->replyToComment($feedId, $comment->id, '請先登入後再下單');
            return;
        }

        // 取得商品 ID
        $productId = $this->communityService->getProductIdByFeed($feedId);
        
        if (!$productId) {
            $this->replyToComment($feedId, $comment->id, '此貼文沒有關聯的商品');
            return;
        }

        // 處理下單
        $this->processOrder($comment, $feed, $orderCommand, $productId);
    }

    /**
     * 解析下單指令
     */
    public function parseOrderCommand(string $message): ?array
    {
        return $this->commandParser->parse($message);
    }

    /**
     * 處理下單流程
     */
    private function processOrder(object $comment, object $feed, array $orderCommand, int $productId): void
    {
        $userId = $comment->user_id;
        $feedId = $feed->id;
        $quantity = $orderCommand['quantity'];
        $variant = $orderCommand['variant'];

        // 檢查使用者資料
        $profileCheck = $this->checkUserProfile($userId);
        if (!$profileCheck['valid']) {
            $missingNames = $this->profileValidator->getMissingFieldNames($profileCheck['missing']);
            $this->replyToComment($feedId, $comment->id, '請先完善個人資料（' . implode('、', $missingNames) . '）');
            
            // 觸發顯示資料補充表單
            do_action('mygo/order/needs_profile', $userId, $feedId, $comment->id);
            return;
        }

        // 檢查庫存
        $stockCheck = $this->cartService->checkStock($productId, $quantity);
        if (!$stockCheck['valid']) {
            $this->replyToComment($feedId, $comment->id, $stockCheck['message']);
            return;
        }

        // 檢查商品規格
        $product = $this->cartService->getProduct($productId);
        $availableVariants = $this->getProductVariants($product);
        
        if (!empty($availableVariants)) {
            $variantCheck = $this->commandParser->validateVariant($variant, $availableVariants);
            if (!$variantCheck['valid']) {
                if ($variantCheck['needs_selection'] ?? false) {
                    // 觸發顯示規格選擇介面
                    do_action('mygo/order/needs_variant', $userId, $feedId, $comment->id, $availableVariants);
                }
                $this->replyToComment($feedId, $comment->id, $variantCheck['message']);
                return;
            }
        }

        // 檢查是否需要累加訂單
        $existingOrder = Database::getUserFeedOrder($userId, $feedId);
        
        if ($existingOrder && $existingOrder->fluentcart_order_id) {
            // 累加訂單
            $this->accumulateOrder($existingOrder, $quantity, $variant, $feedId, $comment->id);
        } else {
            // 建立新訂單
            $this->createNewOrder($userId, $productId, $feedId, $quantity, $variant, $comment->id);
        }
    }

    /**
     * 建立新訂單
     */
    private function createNewOrder(int $userId, int $productId, int $feedId, int $quantity, ?string $variant, int $commentId): void
    {
        $result = $this->createOrder($userId, $productId, $quantity, $variant);

        if (!$result['success']) {
            $this->replyToComment($feedId, $commentId, '訂單建立失敗：' . $result['error']);
            return;
        }

        // 記錄 +1 訂單
        Database::incrementPlusOneQuantity($userId, $productId, $feedId, $quantity, $variant);
        
        $plusOneOrder = Database::getUserFeedOrder($userId, $feedId);
        if ($plusOneOrder) {
            Database::updateFluentCartOrderId($plusOneOrder->id, $result['order_id']);
        }

        // 回覆確認
        $product = $this->cartService->getProduct($productId);
        $productName = $product['title'] ?? '商品';
        $total = $result['total'] ?? 0;
        
        $replyMessage = "已收到您的訂單！\n商品：{$productName}\n數量：{$quantity}\n金額：NT$ " . number_format($total);
        
        if ($variant) {
            $replyMessage = "已收到您的訂單！\n商品：{$productName}（{$variant}）\n數量：{$quantity}\n金額：NT$ " . number_format($total);
        }
        
        $this->replyToComment($feedId, $commentId, $replyMessage);

        // 發送 LINE 通知給買家
        $lineUid = get_user_meta($userId, '_mygo_line_uid', true);
        if ($lineUid) {
            $this->lineService->sendOrderConfirmCard($lineUid, [
                'order_number' => $result['order_id'],
                'product_name' => $productName,
                'quantity' => $quantity,
                'total' => $total,
            ]);
        }
        
        // 發送 LINE 通知給賣家
        $sellerLineUid = get_post_meta($productId, '_mygo_line_user_id', true);
        if ($sellerLineUid) {
            // 取得買家姓名
            $user = get_userdata($userId);
            $buyerName = get_user_meta($userId, '_mygo_buyer_name', true);
            if (empty($buyerName)) {
                $buyerName = $user ? $user->display_name : get_user_meta($userId, '_mygo_line_name', true);
            }
            
            // 取得寄送方式
            $shippingMethod = get_user_meta($userId, '_mygo_shipping_preference', true) ?: get_user_meta($userId, '_mygo_shipping_method', true);
            $shippingMethodLabel = $this->translateShippingMethod($shippingMethod);
            
            $this->lineService->sendSellerOrderNotification($sellerLineUid, [
                'order_number' => $result['order_id'],
                'buyer_name' => $buyerName ?: 'LINE User',
                'product_name' => $productName,
                'quantity' => $quantity,
                'category' => '一般商品',
                'total' => $total,
                'shipping_method' => $shippingMethodLabel,
            ]);
        }
    }
    
    /**
     * 翻譯寄送方式
     */
    private function translateShippingMethod(string $method): string
    {
        $translations = [
            'self_pickup' => '自取',
            'home_delivery' => '宅配',
            'convenience_store' => '超商取貨',
        ];
        
        return $translations[$method] ?? '未設定';
    }

    /**
     * 累加訂單
     */
    private function accumulateOrder(object $existingOrder, int $additionalQuantity, ?string $variant, int $feedId, int $commentId): void
    {
        $productId = $existingOrder->product_id;
        $userId = $existingOrder->user_id;
        
        // 檢查新增數量的庫存
        $stockCheck = $this->cartService->checkStock($productId, $additionalQuantity);
        if (!$stockCheck['valid']) {
            $this->replyToComment($feedId, $commentId, $stockCheck['message']);
            return;
        }

        // 累加數量
        Database::incrementPlusOneQuantity($userId, $productId, $feedId, $additionalQuantity, $variant);

        // 取得更新後的總數量
        $updatedOrder = Database::getUserFeedOrder($userId, $feedId);
        $totalQuantity = $updatedOrder->quantity ?? $additionalQuantity;

        // 取得商品資料
        $product = $this->cartService->getProduct($productId);
        $productName = $product['title'] ?? '商品';
        $unitPrice = floatval($product['price'] ?? 0);
        $total = $unitPrice * $totalQuantity;

        $replyMessage = "訂單已更新！\n商品：{$productName}\n累計數量：{$totalQuantity}\n累計金額：NT$ " . number_format($total);
        
        $this->replyToComment($feedId, $commentId, $replyMessage);
    }

    /**
     * 建立訂單
     */
    public function createOrder(int $userId, int $productId, int $quantity, ?string $variant): array
    {
        return $this->cartService->createOrder($userId, $productId, $quantity, $variant);
    }

    /**
     * 回覆留言
     */
    public function replyToComment(int $feedId, int $parentCommentId, string $message): void
    {
        $this->communityService->replyToComment($feedId, $parentCommentId, $message);
    }

    /**
     * 檢查使用者資料
     */
    public function checkUserProfile(int $userId): array
    {
        return $this->profileValidator->validateForOrder($userId);
    }

    /**
     * 取得商品規格
     */
    private function getProductVariants(?array $product): array
    {
        if (!$product) {
            return [];
        }

        // 從商品資料中取得規格選項
        $variants = $product['variants'] ?? [];
        
        if (empty($variants)) {
            return [];
        }

        return array_map(fn($v) => $v['name'] ?? $v, $variants);
    }
}
