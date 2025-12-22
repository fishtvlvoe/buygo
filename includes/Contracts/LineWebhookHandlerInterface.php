<?php

namespace BuyGo\Core\Contracts;

defined('ABSPATH') or die;

/**
 * LINE Webhook Handler Interface
 * 
 * 負責接收 LINE 官方帳號的訊息，解析商品資訊並建立商品
 */
interface LineWebhookHandlerInterface
{
    /**
     * 處理 LINE 訊息事件
     *
     * @param array $event LINE Webhook 事件資料
     * @return void
     */
    public function handleMessage(array $event): void;

    /**
     * 解析商品資料
     *
     * @param string $message 訊息內容
     * @param string|null $imageUrl 圖片 URL
     * @return array 解析後的商品資料
     */
    public function parseProductData(string $message, ?string $imageUrl): array;

    /**
     * 驗證商品資料完整性
     *
     * @param array $data 商品資料
     * @return bool 是否有效
     */
    public function validateProductData(array $data): bool;

    /**
     * 發送回覆訊息
     *
     * @param string $replyToken LINE Reply Token
     * @param string $message 回覆訊息
     * @return void
     */
    public function sendReply(string $replyToken, string $message): void;
}
