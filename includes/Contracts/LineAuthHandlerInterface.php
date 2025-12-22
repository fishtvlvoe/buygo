<?php

namespace BuyGo\Core\Contracts;

defined('ABSPATH') or die;

/**
 * LINE Auth Handler Interface
 * 
 * 處理 LINE Login OAuth 流程與使用者資料同步
 */
interface LineAuthHandlerInterface
{
    /**
     * 取得 LINE Login 授權 URL
     *
     * @param string $redirectUri 回調 URL
     * @return string 授權 URL
     */
    public function getAuthUrl(string $redirectUri): string;

    /**
     * 處理 OAuth Callback
     *
     * @param string $code 授權碼
     * @return array LINE 使用者資料
     */
    public function handleCallback(string $code): array;

    /**
     * 同步使用者資料至 WordPress
     *
     * @param array $lineProfile LINE 使用者資料
     * @param int $wpUserId WordPress 使用者 ID
     * @return void
     */
    public function syncUserData(array $lineProfile, int $wpUserId): void;

    /**
     * 偵測 LINE 內部瀏覽器
     *
     * @return bool 是否為 LINE 內部瀏覽器
     */
    public function detectLineInAppBrowser(): bool;

    /**
     * 取得外部瀏覽器 URL
     *
     * @param string $targetUrl 目標 URL
     * @return string 外部瀏覽器 URL
     */
    public function getExternalBrowserUrl(string $targetUrl): string;
}
