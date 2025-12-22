<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

use BuyGo\Core\Contracts\LineAuthHandlerInterface;

/**
 * LINE Auth Handler
 * 
 * 處理 LINE Login OAuth 流程與使用者資料同步
 */
class LineAuthHandler implements LineAuthHandlerInterface
{
    private const LINE_AUTH_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    private const LINE_TOKEN_URL = 'https://api.line.me/oauth2/v2.1/token';
    private const LINE_PROFILE_URL = 'https://api.line.me/v2/profile';

    /**
     * 取得 LINE Login 授權 URL
     */
    public function getAuthUrl(string $redirectUri): string
    {
        $clientId = get_option('mygo_line_login_channel_id', '');
        
        // 除錯訊息
        error_log('MYGO LineAuth: getAuthUrl - clientId = ' . $clientId);
        error_log('MYGO LineAuth: getAuthUrl - redirectUri = ' . $redirectUri);
        
        if (empty($clientId)) {
            error_log('MYGO LineAuth: getAuthUrl - ERROR: clientId is empty!');
        }
        
        $state = wp_create_nonce('mygo_line_login');

        // 儲存 state 供驗證
        set_transient('mygo_line_state_' . $state, true, 600);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'profile openid',  // 只申請 profile 權限，email 由前端表單收集
        ];

        // 使用 http_build_query 但要修正 scope 的編碼（LINE 要求用 %20 而非 +）
        $authUrl = self::LINE_AUTH_URL . '?' . str_replace('+', '%20', http_build_query($params));
        error_log('MYGO LineAuth: Final Auth URL = ' . $authUrl);
        error_log('MYGO LineAuth: Scope = profile openid');
        
        return $authUrl;
    }

    /**
     * 處理 OAuth Callback
     */
    public function handleCallback(string $code, string $redirectUri = ''): array
    {
        // 取得 Access Token
        $tokenData = $this->getAccessToken($code, $redirectUri);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return [
                'success' => false,
                'error' => '無法取得 Access Token',
            ];
        }

        // 解析 ID Token（openid scope 就能取得基本資料）
        $idTokenData = null;
        if (isset($tokenData['id_token'])) {
            $idTokenData = $this->decodeIdToken($tokenData['id_token']);
            error_log('MYGO LineCallback: ID Token decoded - sub = ' . ($idTokenData['sub'] ?? 'none'));
            error_log('MYGO LineCallback: ID Token email = ' . ($idTokenData['email'] ?? 'none'));
        }
        
        if (!$idTokenData || !isset($idTokenData['sub'])) {
            error_log('MYGO LineCallback: ID Token missing or invalid');
            return [
                'success' => false,
                'error' => '無法取得使用者資料',
            ];
        }

        // 嘗試取得完整 profile（如果有 profile scope）
        $profile = $this->getProfile($tokenData['access_token']);
        
        // 如果 ID Token 沒有 email，嘗試透過 verify API 取得
        $email = $idTokenData['email'] ?? null;
        if (empty($email) && isset($tokenData['id_token'])) {
            $verifiedData = $this->verifyIdToken($tokenData['id_token']);
            $email = $verifiedData['email'] ?? null;
            error_log('MYGO LineCallback: Verified email = ' . ($email ?? 'none'));
        }
        
        $displayName = $profile['displayName'] ?? $idTokenData['name'] ?? 'LINE User';
        error_log('MYGO LineCallback: Display name = ' . $displayName);
        error_log('MYGO LineCallback: Final email = ' . ($email ?? 'none'));
        
        return [
            'success' => true,
            'profile' => [
                'userId' => $idTokenData['sub'], // sub 就是 LINE user ID
                'displayName' => $displayName,
                'pictureUrl' => $profile['pictureUrl'] ?? $idTokenData['picture'] ?? '',
                'email' => $email,
            ],
        ];
    }

    /**
     * 取得 Access Token
     */
    private function getAccessToken(string $code, string $redirectUri = ''): ?array
    {
        $clientId = get_option('mygo_line_login_channel_id', '');
        $clientSecret = get_option('mygo_line_login_channel_secret', '');
        
        if (empty($redirectUri)) {
            $redirectUri = rest_url('mygo/v1/line-callback');
        }

        $response = wp_remote_post(self::LINE_TOKEN_URL, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * 取得使用者 Profile
     */
    private function getProfile(string $accessToken): ?array
    {
        $response = wp_remote_get(self::LINE_PROFILE_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * 解碼 ID Token
     */
    private function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        return json_decode($payload, true) ?: [];
    }

    /**
     * 驗證 ID Token 並取得 email
     * 參考 nextend-social-login-pro 的實作
     */
    private function verifyIdToken(string $idToken): array
    {
        $clientId = get_option('mygo_line_login_channel_id', '');
        
        $response = wp_remote_post('https://api.line.me/oauth2/v2.1/verify', [
            'timeout' => 15,
            'user-agent' => 'WordPress',
            'body' => [
                'id_token' => $idToken,
                'client_id' => $clientId,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('MYGO LineCallback: Verify ID Token failed - ' . $response->get_error_message());
            return [];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            error_log('MYGO LineCallback: Verify ID Token status = ' . $statusCode);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data ?: [];
    }

    /**
     * 同步使用者資料至 WordPress
     */
    public function syncUserData(array $lineProfile, int $wpUserId): void
    {
        update_user_meta($wpUserId, '_mygo_line_uid', $lineProfile['userId']);
        update_user_meta($wpUserId, '_mygo_line_name', $lineProfile['displayName']);
        
        if (!empty($lineProfile['email'])) {
            update_user_meta($wpUserId, '_mygo_line_email', $lineProfile['email']);
        }

        if (!empty($lineProfile['pictureUrl'])) {
            update_user_meta($wpUserId, '_mygo_line_picture', $lineProfile['pictureUrl']);
        }

        // 設定預設角色
        $existingRole = get_user_meta($wpUserId, '_mygo_role', true);
        if (empty($existingRole)) {
            update_user_meta($wpUserId, '_mygo_role', 'buyer');
        }

        // 同步至 FluentCRM（如果已啟用）
        $this->syncToFluentCRM($lineProfile, $wpUserId);

        do_action('mygo/user/synced', $wpUserId, $lineProfile);
    }

    /**
     * 同步至 FluentCRM
     */
    private function syncToFluentCRM(array $lineProfile, int $wpUserId): void
    {
        if (!function_exists('FluentCrmApi')) {
            return;
        }

        $user = get_userdata($wpUserId);
        if (!$user) {
            return;
        }

        $email = $lineProfile['email'] ?: $user->user_email;
        if (empty($email)) {
            return;
        }

        $contactApi = FluentCrmApi('contacts');
        
        $contactData = [
            'email' => $email,
            'first_name' => $lineProfile['displayName'],
            'custom_values' => [
                'line_uid' => $lineProfile['userId'],
            ],
            'tags' => ['line-user'],
        ];

        // 檢查是否已存在
        $existingContact = $contactApi->getContact($email);
        
        if ($existingContact) {
            $contactApi->updateContact($existingContact->id, $contactData);
        } else {
            $contactApi->createOrUpdate($contactData);
        }
    }

    /**
     * 偵測 LINE 內部瀏覽器
     */
    public function detectLineInAppBrowser(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/Line/i', $userAgent);
    }

    /**
     * 取得外部瀏覽器 URL
     */
    public function getExternalBrowserUrl(string $targetUrl): string
    {
        // 使用 LINE 的 openExternalBrowser 參數
        $separator = strpos($targetUrl, '?') !== false ? '&' : '?';
        return $targetUrl . $separator . 'openExternalBrowser=1';
    }

    /**
     * 處理登入或註冊
     */
    public function loginOrRegister(array $lineProfile): array
    {
        $validator = new UserProfileValidator();
        
        // 檢查是否已有綁定的使用者
        $existingUserId = $validator->findUserByLineUid($lineProfile['userId']);
        
        if ($existingUserId) {
            // 更新資料並登入
            $this->syncUserData($lineProfile, $existingUserId);
            wp_set_current_user($existingUserId);
            wp_set_auth_cookie($existingUserId);
            
            // 檢查資料是否完整
            $profileValidator = new UserProfileValidator();
            $profileCheck = $profileValidator->validateForOrder($existingUserId);
            $needsProfile = !$profileCheck['valid'];
            
            return [
                'success' => true,
                'user_id' => $existingUserId,
                'is_new' => false,
                'needs_profile' => $needsProfile,
            ];
        }

        // 建立新使用者
        $displayName = $lineProfile['displayName'];
        
        // 優先使用 email 前綴作為使用者名稱
        if (!empty($lineProfile['email'])) {
            // 從 email 取得前綴（@ 之前的部分）
            $emailPrefix = explode('@', $lineProfile['email'])[0];
            $username = sanitize_user($emailPrefix, true);
        } else {
            // 如果沒有 email，使用 LINE UID 的後 8 碼
            $username = 'line_' . substr($lineProfile['userId'], -8);
        }
        
        // 確保使用者名稱唯一
        $originalUsername = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $originalUsername . $counter;
            $counter++;
        }
        
        // Email 處理：
        // 1. 優先使用 LINE 提供的真實 email
        // 2. 如果沒有，檢查是否有預先填寫的 email（從 sessionStorage）
        // 3. 最後才使用臨時 email
        $hasRealEmail = !empty($lineProfile['email']);
        $email = $lineProfile['email'];
        
        if (!$hasRealEmail) {
            // 嘗試從 user meta 取得預先填寫的 email（由前端儲存）
            // 注意：這裡無法直接存取 sessionStorage，需要前端在登入成功後透過 AJAX 傳送
            $email = $username . '@temp.line.mygo.local';
        }
        
        $userId = wp_create_user($username, wp_generate_password(20, true, true), $email);
        
        if (is_wp_error($userId)) {
            return [
                'success' => false,
                'error' => $userId->get_error_message(),
            ];
        }

        // 更新顯示名稱（使用原始的 LINE 名稱）
        wp_update_user([
            'ID' => $userId,
            'display_name' => $displayName,
            'first_name' => $displayName,
        ]);
        
        // 如果沒有真實 email，標記需要補充
        if (!$hasRealEmail) {
            update_user_meta($userId, '_mygo_needs_email', true);
        }

        // 同步資料
        $this->syncUserData($lineProfile, $userId);

        // 登入
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId);

        // 新用戶一定需要完善資料
        return [
            'success' => true,
            'user_id' => $userId,
            'is_new' => true,
            'needs_profile' => true,
            'needs_email' => !$hasRealEmail,  // 標記是否需要補充 email
        ];
    }
}
