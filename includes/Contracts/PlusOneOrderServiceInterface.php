<?php

namespace BuyGo\Core\Contracts;

defined('ABSPATH') or die;

/**
 * Plus One Order Service Interface
 * 
 * 監聽 FluentCommunity 留言事件，偵測 +1 關鍵字並建立訂單
 */
interface PlusOneOrderServiceInterface
{
    /**
     * 處理留言事件
     *
     * @param object $comment 留言物件
     * @param object $feed 貼文物件
     * @return void
     */
    public function handleComment(object $comment, object $feed): void;

    /**
     * 解析下單指令
     *
     * @param string $message 留言內容
     * @return array|null 解析結果 ['quantity' => int, 'variant' => string|null] 或 null
     */
    public function parseOrderCommand(string $message): ?array;

    /**
     * 建立訂單
     *
     * @param int $userId 使用者 ID
     * @param int $productId 商品 ID
     * @param int $quantity 數量
     * @param string|null $variant 規格
     * @return array 訂單結果
     */
    public function createOrder(int $userId, int $productId, int $quantity, ?string $variant): array;

    /**
     * 回覆留言
     *
     * @param int $feedId 貼文 ID
     * @param int $parentCommentId 父留言 ID
     * @param string $message 回覆訊息
     * @return void
     */
    public function replyToComment(int $feedId, int $parentCommentId, string $message): void;

    /**
     * 檢查使用者資料完整性
     *
     * @param int $userId 使用者 ID
     * @return array ['valid' => bool, 'missing' => array]
     */
    public function checkUserProfile(int $userId): array;
}
