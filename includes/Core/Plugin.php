<?php

namespace BuyGo\Core\Core;

defined('ABSPATH') or die;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        $this->loadTextDomain();
        $this->registerHooks();
        $this->registerRestRoutes();
        $this->registerServices();
    }

    private function registerServices(): void
    {
        // 註冊 +1 留言監聯
        \Mygo\Services\PlusOneOrderService::register();
        
        // 註冊 FluentCommunity 登入頁面客製化
        \Mygo\Services\FluentCommunityAuthCustomizer::register();
        
        // 註冊個人資料編輯頁面
        \Mygo\ProfileEditPage::register();
        
        // 註冊用戶 Meta 欄位
        \Mygo\Services\UserMetaFields::register();
    }

    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'mygo-plus-one',
            false,
            dirname(MYGO_PLUGIN_BASENAME) . '/language'
        );
    }

    private function registerHooks(): void
    {
        // 後台選單
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        
        // 處理匯出（在 admin_init 早期執行）
        add_action('admin_init', [$this, 'handleExportOrders'], 1);
        
        // AJAX 處理
        add_action('wp_ajax_mygo_update_profile', [$this, 'handleProfileUpdate']);
        add_action('wp_ajax_mygo_get_profile', [$this, 'handleGetProfile']);
        add_action('wp_ajax_mygo_bulk_delete_products', [$this, 'handleBulkDeleteProducts']);
        add_action('wp_ajax_mygo_update_order_status', [$this, 'handleUpdateOrderStatus']);
        add_action('wp_ajax_mygo_save_order_notes', [$this, 'handleSaveOrderNotes']);
        add_action('wp_ajax_mygo_update_buyer_info', [$this, 'handleUpdateBuyerInfo']);
        add_action('wp_ajax_mygo_delete_order', [$this, 'handleDeleteOrder']);
        add_action('wp_ajax_mygo_update_order_info', [$this, 'handleUpdateOrderInfo']);
        add_action('wp_ajax_mygo_bulk_delete_orders', [$this, 'handleBulkDeleteOrders']);
        
        // 註冊設定
        add_action('admin_init', [\Mygo\AdminController::class, 'registerSettings']);
        
        // 載入後台樣式
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // 載入前台樣式
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        
        // 監聽商品建立完成事件，自動建立 FluentCommunity 貼文
        add_action('mygo/product/created', [$this, 'handleProductCreated'], 10, 3);
    }
    
    /**
     * 處理訂單匯出
     */
    public function handleExportOrders(): void
    {
        // 檢查是否是匯出請求
        if (!isset($_GET['page']) || $_GET['page'] !== 'mygo-orders') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'export') {
            return;
        }
        
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die('您沒有權限執行此操作');
        }
        
        $controller = new \Mygo\AdminController();
        $controller->exportOrdersPublic();
    }

    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function () {
            // LINE Webhook
            register_rest_route('mygo/v1', '/line-webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'handleLineWebhook'],
                'permission_callback' => '__return_true',
            ]);

            // LINE Login Callback
            register_rest_route('mygo/v1', '/line-callback', [
                'methods' => 'GET',
                'callback' => [$this, 'handleLineCallback'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('BuyGo', 'mygo-plus-one'),
            __('BuyGo', 'mygo-plus-one'),
            'manage_options',
            'mygo-plus-one',
            [$this, 'renderAdminPage'],
            'dashicons-cart',
            30
        );

        add_submenu_page(
            'mygo-plus-one',
            __('商品管理', 'mygo-plus-one'),
            __('商品管理', 'mygo-plus-one'),
            'manage_options',
            'mygo-products',
            [$this, 'renderProductsPage']
        );

        add_submenu_page(
            'mygo-plus-one',
            __('訂單管理', 'mygo-plus-one'),
            __('訂單管理', 'mygo-plus-one'),
            'manage_options',
            'mygo-orders',
            [$this, 'renderOrdersPage']
        );

        add_submenu_page(
            'mygo-plus-one',
            __('使用者管理', 'mygo-plus-one'),
            __('使用者管理', 'mygo-plus-one'),
            'manage_options',
            'mygo-users',
            [$this, 'renderUsersPage']
        );

        add_submenu_page(
            'mygo-plus-one',
            __('設定', 'mygo-plus-one'),
            __('設定', 'mygo-plus-one'),
            'manage_options',
            'mygo-settings',
            [$this, 'renderSettingsPage']
        );

        add_submenu_page(
            'mygo-plus-one',
            __('Debug Log', 'mygo-plus-one'),
            __('Debug Log', 'mygo-plus-one'),
            'manage_options',
            'mygo-debug-log',
            [$this, 'renderDebugLogPage']
        );
    }

    public function renderUsersPage(): void
    {
        $controller = new \Mygo\AdminController();
        $controller->renderUsers();
    }

    public function renderDebugLogPage(): void
    {
        include MYGO_PLUGIN_DIR . 'admin/views/debug-log.php';
    }

    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'mygo') === false) {
            return;
        }

        wp_enqueue_style(
            'mygo-admin',
            MYGO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MYGO_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'mygo-admin',
            MYGO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MYGO_PLUGIN_VERSION,
            true
        );

        wp_localize_script('mygo-admin', 'mygoAdmin', [
            'nonce' => wp_create_nonce('mygo_admin'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function enqueuePublicAssets(): void
    {
        wp_enqueue_style(
            'mygo-public',
            MYGO_PLUGIN_URL . 'assets/css/public.css',
            [],
            MYGO_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'mygo-public',
            MYGO_PLUGIN_URL . 'assets/js/public.js',
            ['jquery'],
            MYGO_PLUGIN_VERSION,
            true
        );
    }

    public function renderAdminPage(): void
    {
        $controller = new \Mygo\AdminController();
        $controller->renderDashboard();
    }

    public function renderProductsPage(): void
    {
        $controller = new \Mygo\AdminController();
        $controller->renderProducts();
    }

    public function renderOrdersPage(): void
    {
        $controller = new \Mygo\AdminController();
        $controller->renderOrders();
    }

    public function renderSettingsPage(): void
    {
        $controller = new \Mygo\AdminController();
        $controller->renderSettings();
    }

    public function handleLineWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $handler = new \Mygo\Services\LineWebhookHandler();
        return $handler->handleWebhook($request);
    }

    public function handleLineCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $handler = new \Mygo\Services\LineAuthHandler();
        
        $code = $request->get_param('code');
        $state = $request->get_param('state');

        // 驗證 state
        if (!get_transient('mygo_line_state_' . $state)) {
            return new \WP_REST_Response(['error' => 'Invalid state'], 400);
        }
        delete_transient('mygo_line_state_' . $state);

        // 處理 OAuth
        $result = $handler->handleCallback($code);

        if (!$result['success']) {
            return new \WP_REST_Response(['error' => $result['error']], 400);
        }

        // 登入或註冊
        $loginResult = $handler->loginOrRegister($result['profile']);

        if (!$loginResult['success']) {
            return new \WP_REST_Response(['error' => $loginResult['error']], 400);
        }

        // 重導向到首頁或指定頁面
        $redirectUrl = get_option('mygo_login_redirect_url', home_url());
        
        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * 處理個人資料更新 AJAX 請求
     */
    public function handleProfileUpdate(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_profile_edit', 'nonce', false)) {
            wp_send_json_error(['message' => '安全驗證失敗']);
        }

        // 檢查登入狀態
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => '請先登入']);
        }

        $userId = get_current_user_id();
        $validator = new \Mygo\Services\UserProfileValidator();

        $data = [
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_text_field($_POST['address'] ?? ''),
            'shipping_method' => sanitize_text_field($_POST['shipping_method'] ?? ''),
        ];

        $validation = $validator->validateAndSanitize($data);

        if (!$validation['valid']) {
            wp_send_json_error([
                'message' => '資料驗證失敗',
                'errors' => $validation['errors'],
            ]);
        }

        $validator->updateUserProfile($userId, $validation['sanitized']);

        wp_send_json_success([
            'message' => '個人資料已更新',
        ]);
    }
    
    /**
     * 取得個人資料 AJAX 請求
     */
    public function handleGetProfile(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_profile_edit', 'nonce', false)) {
            wp_send_json_error(['message' => '安全驗證失敗']);
        }

        // 檢查登入狀態
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => '請先登入']);
        }

        $userId = get_current_user_id();
        $validator = new \Mygo\Services\UserProfileValidator();
        $profile = $validator->getUserProfile($userId);

        wp_send_json_success($profile);
    }
    
    /**
     * 批次刪除商品 AJAX 請求
     */
    public function handleBulkDeleteProducts(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_bulk_delete', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $productIds = $_POST['product_ids'] ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            wp_send_json_error('請選擇要刪除的商品');
        }

        $cartService = new \Mygo\Services\FluentCartService();
        $deletedCount = 0;
        $errors = [];

        foreach ($productIds as $productId) {
            $productId = intval($productId);
            
            try {
                // 1. 取得商品資訊
                $feedId = get_post_meta($productId, '_mygo_feed_id', true);
                $imageId = get_post_meta($productId, '_mygo_image_id', true);
                
                // 2. 刪除 FluentCart 商品（會自動刪除相關的變體和詳細資料）
                $cartDeleted = $cartService->deleteProduct($productId);
                
                // 3. 刪除 FluentCommunity 貼文
                if ($feedId && class_exists('\FluentCommunity\App\Models\Feed')) {
                    try {
                        $feed = \FluentCommunity\App\Models\Feed::find($feedId);
                        if ($feed) {
                            $feed->delete();
                        }
                    } catch (\Exception $e) {
                        error_log('MYGO: Failed to delete feed ' . $feedId . ': ' . $e->getMessage());
                    }
                }
                
                // 4. 刪除商品圖片（從 Media Library）
                if ($imageId) {
                    wp_delete_attachment($imageId, true);
                }
                
                // 5. 刪除所有 post meta
                global $wpdb;
                $wpdb->delete($wpdb->postmeta, ['post_id' => $productId], ['%d']);
                
                if ($cartDeleted) {
                    $deletedCount++;
                } else {
                    $errors[] = "商品 ID {$productId}: FluentCart 刪除失敗";
                }
                
            } catch (\Exception $e) {
                $errors[] = "商品 ID {$productId}: " . $e->getMessage();
            }
        }

        if ($deletedCount > 0) {
            wp_send_json_success([
                'deleted' => $deletedCount,
                'errors' => $errors,
            ]);
        } else {
            wp_send_json_error('刪除失敗：' . implode(', ', $errors));
        }
    }

    /**
     * 更新訂單狀態 AJAX 請求
     */
    public function handleUpdateOrderStatus(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        $statusType = sanitize_text_field($_POST['status_type'] ?? '');
        $value = filter_var($_POST['value'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$orderId || !$statusType) {
            wp_send_json_error('參數錯誤');
        }

        $cartService = new \Mygo\Services\FluentCartService();
        $result = $cartService->updateOrderStatus($orderId, $statusType, $value, get_current_user_id());

        if ($result) {
            wp_send_json_success(['message' => '狀態已更新']);
        } else {
            wp_send_json_error('更新失敗');
        }
    }

    /**
     * 儲存訂單備註 AJAX 請求
     */
    public function handleSaveOrderNotes(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (!$orderId) {
            wp_send_json_error('參數錯誤');
        }

        update_post_meta($orderId, '_mygo_notes', $notes);
        wp_send_json_success([
            'message' => '備註已儲存',
            'redirect' => admin_url('admin.php?page=mygo-orders')
        ]);
    }
    
    /**
     * 更新買家資訊 AJAX 請求
     */
    public function handleUpdateBuyerInfo(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $userId = intval($_POST['user_id'] ?? 0);
        $buyerName = sanitize_text_field($_POST['buyer_name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $shippingMethod = sanitize_text_field($_POST['shipping_method'] ?? '');

        if (!$userId) {
            wp_send_json_error('參數錯誤');
        }

        update_user_meta($userId, '_mygo_buyer_name', $buyerName);
        update_user_meta($userId, '_mygo_phone', $phone);
        update_user_meta($userId, '_mygo_address', $address);
        update_user_meta($userId, '_mygo_shipping_preference', $shippingMethod);

        wp_send_json_success(['message' => '買家資訊已更新']);
    }
    
    /**
     * 刪除訂單 AJAX 請求
     */
    public function handleDeleteOrder(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $orderId = intval($_POST['order_id'] ?? 0);

        if (!$orderId) {
            wp_send_json_error('參數錯誤');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mygo_plus_one_orders';
        
        // 刪除訂單
        $result = $wpdb->delete($table, ['id' => $orderId], ['%d']);

        if ($result) {
            wp_send_json_success([
                'message' => '訂單已刪除',
                'redirect' => admin_url('admin.php?page=mygo-orders')
            ]);
        } else {
            wp_send_json_error('刪除失敗');
        }
    }
    
    /**
     * 更新訂單資訊 AJAX 請求
     */
    public function handleUpdateOrderInfo(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);

        if (!$orderId || !$productId) {
            wp_send_json_error('參數錯誤');
        }

        global $wpdb;
        
        // 更新 mygo_plus_one_orders 表
        $table = $wpdb->prefix . 'mygo_plus_one_orders';
        $wpdb->update(
            $table,
            ['quantity' => $quantity],
            ['id' => $orderId],
            ['%d'],
            ['%d']
        );
        
        // 更新 FluentCart 商品價格（轉換為分）
        $priceCents = intval($unitPrice * 100);
        $wpdb->update(
            $wpdb->prefix . 'fct_product_variations',
            ['item_price' => $priceCents],
            ['post_id' => $productId],
            ['%d'],
            ['%d']
        );
        
        $wpdb->update(
            $wpdb->prefix . 'fct_product_details',
            [
                'min_price' => $priceCents,
                'max_price' => $priceCents,
            ],
            ['post_id' => $productId],
            ['%d', '%d'],
            ['%d']
        );

        wp_send_json_success([
            'message' => '訂單資訊已更新',
            'total' => $unitPrice * $quantity
        ]);
    }
    
    /**
     * 批次刪除訂單 AJAX 請求
     */
    public function handleBulkDeleteOrders(): void
    {
        // 驗證 nonce
        if (!check_ajax_referer('mygo_admin', 'nonce', false)) {
            wp_send_json_error('安全驗證失敗');
        }

        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('您沒有權限執行此操作');
        }

        $orderIds = $_POST['order_ids'] ?? [];
        
        if (empty($orderIds) || !is_array($orderIds)) {
            wp_send_json_error('請選擇要刪除的訂單');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mygo_plus_one_orders';
        $deletedCount = 0;

        foreach ($orderIds as $orderId) {
            $orderId = intval($orderId);
            $result = $wpdb->delete($table, ['id' => $orderId], ['%d']);
            if ($result) {
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            wp_send_json_success([
                'deleted' => $deletedCount,
                'message' => "已刪除 {$deletedCount} 筆訂單"
            ]);
        } else {
            wp_send_json_error('刪除失敗');
        }
    }
    
    /**
     * 處理商品建立完成事件
     * 自動在 FluentCommunity 建立貼文
     *
     * @param int $productId 商品 ID
     * @param array $data 商品資料
     * @param string $lineUserId LINE 使用者 ID
     */
    public function handleProductCreated(int $productId, array $data, string $lineUserId): void
    {
        error_log('MYGO Plugin: handleProductCreated - productId = ' . $productId);
        
        // 檢查是否已經建立過貼文（避免重複建立）
        $existingFeedId = get_post_meta($productId, '_mygo_feed_id', true);
        if ($existingFeedId) {
            error_log('MYGO Plugin: handleProductCreated - feed already exists, feed_id = ' . $existingFeedId);
            return;
        }
        
        // 檢查 FluentCommunity 是否可用
        if (!class_exists('\FluentCommunity\App\App')) {
            error_log('MYGO Plugin: handleProductCreated - FluentCommunity not installed');
            return;
        }
        
        try {
            // 取得商品完整資料
            $product = $this->prepareProductDataForFeed($productId, $data);
            
            if (!$product) {
                error_log('MYGO Plugin: handleProductCreated - failed to prepare product data');
                return;
            }
            
            // 建立 FluentCommunity 貼文
            $communityService = new \Mygo\Services\FluentCommunityService();
            $feedResult = $communityService->publishProductPost($product);
            
            if (!$feedResult['success']) {
                error_log('MYGO Plugin: handleProductCreated - failed to publish feed: ' . $feedResult['error']);
                return;
            }
            
            $feedId = $feedResult['feed_id'] ?? 0;
            $feed = $feedResult['feed'] ?? null;
            
            // 儲存貼文 ID 到商品 Meta
            update_post_meta($productId, '_mygo_feed_id', $feedId);
            
            // 取得貼文連結
            $feedUrl = '';
            if ($feed && isset($feed['id'])) {
                $feedUrl = $this->getFeedUrl($feed['id']);
                update_post_meta($productId, '_mygo_feed_url', $feedUrl);
            }
            
            error_log('MYGO Plugin: handleProductCreated - feed created successfully, feed_id = ' . $feedId . ', url = ' . $feedUrl);
            
            // 觸發貼文發布完成事件
            do_action('mygo/feed/auto_published', $feedId, $productId, $feedUrl);
            
        } catch (\Exception $e) {
            error_log('MYGO Plugin: handleProductCreated - exception: ' . $e->getMessage());
        }
    }
    
    /**
     * 準備商品資料供 FluentCommunity 使用
     *
     * @param int $productId 商品 ID
     * @param array $originalData 原始商品資料
     * @return array|null 準備好的商品資料
     */
    private function prepareProductDataForFeed(int $productId, array $originalData): ?array
    {
        // 從商品取得完整資訊
        $product = get_post($productId);
        if (!$product || $product->post_type !== 'fluent-products') {
            return null;
        }
        
        // 取得商品價格（從 FluentCart 資料表）
        $price = $this->getProductPrice($productId);
        
        // 取得商品庫存
        $stock = $this->getProductStock($productId);
        
        // 取得商品圖片
        $imageAttachmentId = get_post_thumbnail_id($productId);
        $imageUrl = $imageAttachmentId ? wp_get_attachment_url($imageAttachmentId) : '';
        
        // 組合商品資料
        $productData = [
            'id' => $productId,
            'name' => $product->post_title,
            'description' => $product->post_content,
            'price' => $price,
            'quantity' => $stock,
            'image_attachment_id' => $imageAttachmentId,
            'image_url' => $imageUrl,
            'arrival_date' => get_post_meta($productId, '_mygo_arrival_date', true),
            'preorder_date' => get_post_meta($productId, '_mygo_preorder_date', true),
        ];
        
        // 合併原始資料（優先使用原始資料）
        return array_merge($productData, $originalData);
    }
    
    /**
     * 取得商品價格（從 FluentCart 資料表）
     *
     * @param int $productId 商品 ID
     * @return int 價格（元）
     */
    private function getProductPrice(int $productId): int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'fct_product_details';
        $price = $wpdb->get_var($wpdb->prepare(
            "SELECT min_price FROM {$tableName} WHERE post_id = %d LIMIT 1",
            $productId
        ));
        
        // FluentCart 使用「分」為單位，轉換為「元」
        return $price ? (int)($price / 100) : 0;
    }
    
    /**
     * 取得商品庫存
     *
     * @param int $productId 商品 ID
     * @return int 庫存數量
     */
    private function getProductStock(int $productId): int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'fct_product_variations';
        $stock = $wpdb->get_var($wpdb->prepare(
            "SELECT available FROM {$tableName} WHERE post_id = %d LIMIT 1",
            $productId
        ));
        
        return $stock ? (int)$stock : 0;
    }
    
    /**
     * 取得貼文 URL
     *
     * @param int $feedId 貼文 ID
     * @return string 貼文 URL
     */
    private function getFeedUrl(int $feedId): string
    {
        if (!$feedId) {
            return home_url();
        }
        
        if (class_exists('\FluentCommunity\App\Models\Feed')) {
            try {
                $feed = \FluentCommunity\App\Models\Feed::find($feedId);
                if ($feed) {
                    return $feed->getPermalink();
                }
            } catch (\Exception $e) {
                error_log('MYGO Plugin: getFeedUrl - exception: ' . $e->getMessage());
            }
        }
        
        // 如果無法取得，使用預設格式
        return home_url('/community/feed/' . $feedId);
    }
}
