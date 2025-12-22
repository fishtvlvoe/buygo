<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

use BuyGo\Core\Core\Database;

/**
 * FluentCart Service
 * 
 * 整合 FluentCart 的商品與訂單操作
 */
class FluentCartService
{
    /**
     * 建立商品
     *
     * @param array $data 商品資料
     * @param string $lineUserId 上傳者 LINE UID
     * @return array ['success' => bool, 'product_id' => int, 'error' => string]
     */
    public function createProduct(array $data, string $lineUserId): array
    {
        error_log('MYGO FluentCartService: createProduct called');
        error_log('MYGO FluentCartService: data = ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        if (!class_exists('FluentCart\App\App')) {
            error_log('MYGO FluentCartService: FluentCart not installed');
            return [
                'success' => false,
                'error' => 'FluentCart 未安裝',
            ];
        }

        try {
            // 使用 WordPress 原生函數建立商品
            $postData = [
                'post_title' => sanitize_text_field($data['name']),
                'post_content' => sanitize_textarea_field($data['description'] ?? ''),
                'post_excerpt' => '',
                'post_status' => 'publish',
                'post_type' => 'fluent-products',
                'comment_status' => 'open',
                'ping_status' => 'closed',
            ];

            error_log('MYGO FluentCartService: Creating post with data = ' . json_encode($postData, JSON_UNESCAPED_UNICODE));

            $productId = wp_insert_post($postData, true);
            
            if (is_wp_error($productId)) {
                error_log('MYGO FluentCartService: wp_insert_post error = ' . $productId->get_error_message());
                return [
                    'success' => false,
                    'error' => $productId->get_error_message(),
                ];
            }

            error_log('MYGO FluentCartService: Product created with ID = ' . $productId);

            // 建立 FluentCart 商品詳情
            $this->createProductDetails($productId, $data);

            // 設定圖片
            if (!empty($data['image_attachment_id'])) {
                // 如果有 attachment_id，直接設定為 post thumbnail
                set_post_thumbnail($productId, intval($data['image_attachment_id']));
                error_log('MYGO FluentCartService: Product image set from attachment_id = ' . $data['image_attachment_id']);
            } elseif (!empty($data['image_url'])) {
                // 如果是檔案路徑，上傳到媒體庫
                $this->setProductImage($productId, $data['image_url']);
            }

            // 儲存 MYGO meta
            $this->saveProductMeta($productId, [
                '_mygo_line_user_id' => $lineUserId,
                '_mygo_arrival_date' => $data['arrival_date'] ?? '',
                '_mygo_preorder_date' => $data['preorder_date'] ?? '',
            ]);

            do_action('mygo/product/created', $productId, $data, $lineUserId);

            return [
                'success' => true,
                'product_id' => $productId,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 建立訂單
     *
     * @param int $userId WordPress 使用者 ID
     * @param int $productId 商品 ID
     * @param int $quantity 數量
     * @param string|null $variant 規格
     * @param array $orderMeta 額外的訂單 meta
     * @return array ['success' => bool, 'order_id' => int, 'error' => string]
     */
    public function createOrder(int $userId, int $productId, int $quantity, ?string $variant = null, array $orderMeta = []): array
    {
        if (!class_exists('FluentCart\App\App')) {
            return [
                'success' => false,
                'error' => 'FluentCart 未安裝',
            ];
        }

        // 檢查庫存
        $stockCheck = $this->checkStock($productId, $quantity);
        if (!$stockCheck['valid']) {
            return [
                'success' => false,
                'error' => $stockCheck['message'],
                'available' => $stockCheck['available'],
            ];
        }

        // 確保 FluentCart 客戶存在
        $customerId = $this->ensureCustomerExists($userId);
        if (!$customerId) {
            return [
                'success' => false,
                'error' => '無法建立客戶資料',
            ];
        }

        // 取得使用者資料
        $userProfile = $this->getUserOrderInfo($userId);

        try {
            // 取得商品資料
            $product = $this->getProduct($productId);
            
            if (!$product) {
                return [
                    'success' => false,
                    'error' => '商品不存在',
                ];
            }
            
            // FluentCart 回傳的是 Model 物件，需要轉成陣列
            if (is_object($product)) {
                $product = json_decode(json_encode($product), true);
            }
            
            // FluentCart API 回傳的資料結構是 { "product": { "detail": { ... } } }
            $productData = $product['product'] ?? $product;
            
            // 價格在 detail 裡面
            $productDetail = $productData['detail'] ?? [];

            // FluentCart 價格以「分」為單位，需要除以 100
            $priceInCents = intval($productDetail['min_price'] ?? 0);
            $unitPrice = $priceInCents / 100;
            
            // 確保價格正確
            if ($unitPrice <= 0) {
                error_log('MYGO FluentCartService: Invalid price from API, priceInCents = ' . $priceInCents);
            }
            
            $itemTotal = $unitPrice * $quantity;
            $productTitle = $productData['post_title'] ?? $productData['title'] ?? '商品';
            
            error_log('MYGO FluentCartService: Price calculation - priceInCents=' . $priceInCents . ', unitPrice=' . $unitPrice . ', quantity=' . $quantity . ', itemTotal=' . $itemTotal);
            
            if ($unitPrice <= 0) {
                return [
                    'success' => false,
                    'error' => '商品價格無效',
                ];
            }

            // 取得商品的預設變體 ID（FluentCart 需要用變體 ID 建立訂單）
            $variationId = $this->getDefaultVariationId($productId);
            if (!$variationId) {
                return [
                    'success' => false,
                    'error' => '找不到商品變體',
                ];
            }

            // 準備訂單資料（根據 FluentCart OrderRequest 驗證規則）
            $orderData = [
                'customer_id' => $customerId,
                'order_items' => [
                    [
                        'post_id' => $productId,
                        'variation_id' => $variationId,
                        'object_id' => $variationId,
                        'quantity' => $quantity,
                        'title' => $productTitle,
                        'price' => $unitPrice,
                        'unit_price' => $unitPrice,
                        'item_cost' => $unitPrice,
                        'item_total' => $itemTotal,
                        'total' => $itemTotal,
                        'line_total' => $itemTotal,
                        'tax_amount' => 0,
                        'discount_total' => 0,
                    ],
                ],
                'subtotal' => $itemTotal,
                'total_amount' => $itemTotal,
                'tax_total' => 0,
                'discount_tax' => 0,
                'manual_discount_total' => 0,
                'coupon_discount_total' => 0,
                'shipping_tax' => 0,
                'shipping_total' => 0,
            ];

            // 使用 FluentCart API 建立訂單
            error_log('MYGO FluentCartService: createOrder - calling FluentCart API with data = ' . json_encode($orderData, JSON_UNESCAPED_UNICODE));
            $response = $this->callFluentCartApi('orders', 'POST', $orderData);
            error_log('MYGO FluentCartService: createOrder - API response = ' . json_encode($response, JSON_UNESCAPED_UNICODE));

            if (!$response || !isset($response['order_id'])) {
                error_log('MYGO FluentCartService: createOrder - order creation failed, response = ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                return [
                    'success' => false,
                    'error' => $response['message'] ?? '建立訂單失敗',
                ];
            }

            $orderId = $response['order_id'];

            // 儲存 MYGO meta
            $mygoMeta = array_merge([
                '_mygo_source' => 'plus_one',
                '_mygo_line_uid' => get_user_meta($userId, '_mygo_line_uid', true),
                '_mygo_line_name' => get_user_meta($userId, '_mygo_line_name', true),
                '_mygo_shipping_method' => $userProfile['shipping_method'],
                '_mygo_status_arrived' => false,
                '_mygo_status_closed' => false,
                '_mygo_status_paid' => false,
                '_mygo_status_shipped' => false,
            ], $orderMeta);

            $this->saveOrderMeta($orderId, $mygoMeta);

            // 扣除庫存
            $this->decreaseStock($productId, $quantity);

            do_action('mygo/order/created', $orderId, $userId, $productId, $quantity);

            return [
                'success' => true,
                'order_id' => $orderId,
                'order' => $response,
                'total' => $itemTotal,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 檢查庫存
     *
     * @param int $productId 商品 ID
     * @param int $requestedQuantity 請求數量
     * @return array ['valid' => bool, 'message' => string, 'available' => int]
     */
    public function checkStock(int $productId, int $requestedQuantity): array
    {
        $product = $this->getProduct($productId);
        
        if (!$product) {
            return [
                'valid' => false,
                'message' => '商品不存在',
                'available' => 0,
            ];
        }

        $stockQuantity = intval($product['stock_quantity'] ?? 0);
        $manageStock = $product['manage_stock'] ?? false;

        // 如果不管理庫存，視為無限
        if (!$manageStock) {
            return [
                'valid' => true,
                'message' => null,
                'available' => PHP_INT_MAX,
            ];
        }

        if ($stockQuantity <= 0) {
            return [
                'valid' => false,
                'message' => '商品已售完',
                'available' => 0,
            ];
        }

        if ($requestedQuantity > $stockQuantity) {
            return [
                'valid' => false,
                'message' => "庫存不足，目前可購買數量為 {$stockQuantity} 個",
                'available' => $stockQuantity,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'available' => $stockQuantity,
        ];
    }

    /**
     * 取得商品
     */
    public function getProduct(int $productId): ?array
    {
        global $wpdb;
        
        // 直接從資料庫取得商品資料，避免 API 回傳結構不一致的問題
        $post = get_post($productId);
        if (!$post || $post->post_type !== 'fluent-products') {
            return null;
        }
        
        // 從 FluentCart 表取得價格和庫存
        $variation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_product_variations WHERE post_id = %d LIMIT 1",
            $productId
        ), ARRAY_A);
        
        $details = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fct_product_details WHERE post_id = %d LIMIT 1",
            $productId
        ), ARRAY_A);
        
        // 取得價格（優先從 variation，否則從 details）
        $priceCents = 0;
        if (!empty($variation['item_price'])) {
            $priceCents = floatval($variation['item_price']);
        } elseif (!empty($details['min_price'])) {
            $priceCents = floatval($details['min_price']);
        }
        
        // 取得庫存
        $stockQuantity = intval($variation['available'] ?? $variation['total_stock'] ?? 0);
        $stockStatus = $variation['stock_status'] ?? $details['stock_availability'] ?? 'out-of-stock';
        $manageStock = !empty($variation['manage_stock']) || !empty($details['manage_stock']);
        
        return [
            'id' => $productId,
            'title' => $post->post_title,
            'post_title' => $post->post_title,
            'description' => $post->post_content,
            'price' => $priceCents / 100, // 轉換為元
            'price_cents' => $priceCents, // 保留分的值
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'manage_stock' => $manageStock,
            'product' => [
                'post_title' => $post->post_title,
                'title' => $post->post_title,
                'detail' => [
                    'min_price' => $priceCents,
                ],
            ],
        ];
    }

    /**
     * 取得商品的預設變體 ID
     */
    private function getDefaultVariationId(int $productId): ?int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'fct_product_variations';
        
        $variationId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tableName} WHERE post_id = %d AND item_status = 'active' ORDER BY id ASC LIMIT 1",
            $productId
        ));
        
        return $variationId ? (int) $variationId : null;
    }

    /**
     * 確保 FluentCart 客戶存在
     */
    private function ensureCustomerExists(int $userId): ?int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'fct_customers';
        
        // 先查詢是否已存在
        $customerId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tableName} WHERE user_id = %d LIMIT 1",
            $userId
        ));
        
        if ($customerId) {
            return (int) $customerId;
        }
        
        // 不存在則建立新客戶
        $user = get_userdata($userId);
        if (!$user) {
            return null;
        }
        
        $customerData = [
            'user_id' => $userId,
            'email' => $user->user_email,
            'first_name' => get_user_meta($userId, '_mygo_line_name', true) ?: $user->display_name,
            'last_name' => '',
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($tableName, $customerData);
        
        if ($result === false) {
            return null;
        }
        
        return (int) $wpdb->insert_id;
    }

    /**
     * 取得訂單
     */
    public function getOrder(int $orderId): ?array
    {
        return $this->callFluentCartApi("orders/{$orderId}", 'GET');
    }

    /**
     * 扣除庫存
     */
    private function decreaseStock(int $productId, int $quantity): bool
    {
        $product = $this->getProduct($productId);
        if (!$product || !($product['manage_stock'] ?? false)) {
            return true;
        }

        $newStock = max(0, intval($product['stock_quantity']) - $quantity);
        
        return (bool) $this->callFluentCartApi("products/{$productId}", 'PUT', [
            'stock_quantity' => $newStock,
            'stock_status' => $newStock > 0 ? 'in_stock' : 'out_of_stock',
        ]);
    }

    /**
     * 建立 FluentCart 商品詳情
     */
    private function createProductDetails(int $productId, array $data): void
    {
        global $wpdb;
        
        // FluentCart 使用「分」為單位儲存價格，所以 350 元要存成 35000
        $price = intval($data['price'] ?? 0) * 100;
        $quantity = intval($data['quantity'] ?? 0);
        
        $detailData = [
            'post_id' => $productId,
            'fulfillment_type' => 'physical',
            'min_price' => $price,
            'max_price' => $price,
            'default_variation_id' => null,
            'default_media' => null,
            'manage_stock' => 1,
            'stock_availability' => $quantity > 0 ? 'in-stock' : 'out-of-stock',
            'variation_type' => 'simple',
            'manage_downloadable' => 0,
            'other_info' => json_encode([
                'stock_quantity' => $quantity,
            ]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $tableName = $wpdb->prefix . 'fct_product_details';
        
        // 檢查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") !== $tableName) {
            error_log('MYGO FluentCartService: fct_product_details table not found');
            return;
        }

        $wpdb->insert($tableName, $detailData);
        
        error_log('MYGO FluentCartService: Product details created, insert_id = ' . $wpdb->insert_id);
        
        // 建立預設變體（FluentCart 需要）
        $this->createDefaultVariation($productId, $data);
    }

    /**
     * 建立預設商品變體
     */
    private function createDefaultVariation(int $productId, array $data): void
    {
        global $wpdb;
        
        // FluentCart 使用「分」為單位儲存價格，所以 350 元要存成 35000
        $price = intval($data['price'] ?? 0) * 100;
        $quantity = intval($data['quantity'] ?? 0);
        
        $tableName = $wpdb->prefix . 'fct_product_variations';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") !== $tableName) {
            error_log('MYGO FluentCartService: fct_product_variations table not found');
            return;
        }
        
        // 先查詢表結構，確認欄位名稱
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$tableName}");
        error_log('MYGO FluentCartService: fct_product_variations columns = ' . json_encode($columns));
        
        // 根據實際欄位建立資料
        $variationData = [
            'post_id' => $productId,
            'variation_title' => sanitize_text_field($data['name'] ?? ''),
            'variation_identifier' => 'MYGO-' . $productId,
            'manage_stock' => 1,
            'stock_status' => $quantity > 0 ? 'in-stock' : 'out-of-stock',
            'total_stock' => $quantity,
            'available' => $quantity,
            'item_status' => 'active',
            'item_price' => $price,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($tableName, $variationData);
        
        if ($result === false) {
            error_log('MYGO FluentCartService: Product variation insert failed - ' . $wpdb->last_error);
        } else {
            error_log('MYGO FluentCartService: Product variation created, insert_id = ' . $wpdb->insert_id);
        }
    }

    /**
     * 設定商品圖片
     */
    private function setProductImage(int $productId, string $imagePath): void
    {
        // 如果是暫存檔案路徑，上傳到媒體庫
        if (file_exists($imagePath)) {
            $uploadDir = wp_upload_dir();
            $filename = 'mygo-product-' . $productId . '-' . time() . '.jpg';
            $newPath = $uploadDir['path'] . '/' . $filename;
            
            if (copy($imagePath, $newPath)) {
                $attachment = [
                    'post_mime_type' => 'image/jpeg',
                    'post_title' => sanitize_file_name($filename),
                    'post_content' => '',
                    'post_status' => 'inherit',
                ];
                
                $attachmentId = wp_insert_attachment($attachment, $newPath, $productId);
                
                if (!is_wp_error($attachmentId)) {
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attachmentData = wp_generate_attachment_metadata($attachmentId, $newPath);
                    wp_update_attachment_metadata($attachmentId, $attachmentData);
                    set_post_thumbnail($productId, $attachmentId);
                    
                    error_log('MYGO FluentCartService: Product image set, attachment_id = ' . $attachmentId);
                }
            }
            
            // 刪除暫存檔
            @unlink($imagePath);
        }
    }

    /**
     * 儲存商品 meta
     */
    private function saveProductMeta(int $productId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            update_post_meta($productId, $key, $value);
        }
    }

    /**
     * 儲存訂單 meta
     */
    private function saveOrderMeta(int $orderId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            update_post_meta($orderId, $key, $value);
        }
    }

    /**
     * 取得使用者訂單資訊
     */
    private function getUserOrderInfo(int $userId): array
    {
        $user = get_userdata($userId);
        
        return [
            'name' => get_user_meta($userId, '_mygo_line_name', true) ?: $user->display_name,
            'email' => get_user_meta($userId, '_mygo_line_email', true) ?: $user->user_email,
            'phone' => get_user_meta($userId, '_mygo_phone', true),
            'address' => get_user_meta($userId, '_mygo_address', true),
            'shipping_method' => get_user_meta($userId, '_mygo_shipping_preference', true),
        ];
    }

    /**
     * 呼叫 FluentCart API
     */
    private function callFluentCartApi(string $endpoint, string $method, array $data = []): ?array
    {
        error_log('MYGO FluentCartService: callFluentCartApi - endpoint = ' . $endpoint . ', method = ' . $method);
        
        // 使用內部請求
        $request = new \WP_REST_Request($method, "/fluent-cart/v2/{$endpoint}");
        
        if (!empty($data)) {
            $request->set_body(json_encode($data));
            $request->set_header('Content-Type', 'application/json');
            // 也設定 JSON params
            $request->set_body_params($data);
        }

        // 設定當前使用者（需要有權限）
        $request->set_param('_wpnonce', wp_create_nonce('wp_rest'));

        $response = rest_do_request($request);
        
        error_log('MYGO FluentCartService: Response status = ' . $response->get_status());
        
        if ($response->is_error()) {
            $error = $response->as_error();
            error_log('MYGO FluentCartService: API Error = ' . $error->get_error_message());
            error_log('MYGO FluentCartService: Error data = ' . json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
            return null;
        }

        return $response->get_data();
    }

    /**
     * 更新訂單狀態
     */
    public function updateOrderStatus(int $orderId, string $statusType, bool $value, int $changedBy): bool
    {
        $validTypes = ['arrived', 'closed', 'paid', 'shipped'];
        if (!in_array($statusType, $validTypes, true)) {
            return false;
        }

        $metaKey = '_mygo_status_' . $statusType;
        $oldValue = (bool) get_post_meta($orderId, $metaKey, true);

        if ($oldValue === $value) {
            return true; // 沒有變更
        }

        update_post_meta($orderId, $metaKey, $value);

        // 記錄狀態變更
        Database::logOrderStatusChange($orderId, $statusType, $oldValue, $value, $changedBy);

        do_action('mygo/order/status_changed', $orderId, $statusType, $oldValue, $value);

        return true;
    }

    /**
     * 取得訂單狀態
     */
    public function getOrderStatuses(int $orderId): array
    {
        return [
            'arrived' => (bool) get_post_meta($orderId, '_mygo_status_arrived', true),
            'closed' => (bool) get_post_meta($orderId, '_mygo_status_closed', true),
            'paid' => (bool) get_post_meta($orderId, '_mygo_status_paid', true),
            'shipped' => (bool) get_post_meta($orderId, '_mygo_status_shipped', true),
        ];
    }

    /**
     * 取得賣家的商品列表
     *
     * @param int $wpUserId WordPress 使用者 ID
     * @param int $limit 數量限制
     * @return array 商品列表
     */
    public function getSellerProducts(int $wpUserId, int $limit = 10): array
    {
        $lineUid = get_user_meta($wpUserId, '_mygo_line_uid', true);
        
        if (empty($lineUid)) {
            return [];
        }

        global $wpdb;
        
        // 查詢該賣家上傳的商品
        $productIds = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_mygo_line_user_id' AND meta_value = %s
             LIMIT %d",
            $lineUid,
            $limit
        ));

        if (empty($productIds)) {
            return [];
        }

        $products = [];
        foreach ($productIds as $productId) {
            $product = $this->getProduct((int) $productId);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }
    
    /**
     * 刪除商品
     * 
     * @param int $productId FluentCart 商品 ID
     * @return bool 是否成功
     */
    public function deleteProduct(int $productId): bool
    {
        if (!class_exists('\FluentCart\App\Models\Product')) {
            return false;
        }

        try {
            $product = \FluentCart\App\Models\Product::find($productId);
            
            if (!$product) {
                return false;
            }

            // FluentCart 的 delete() 方法會自動處理相關資料
            $product->delete();
            
            return true;
            
        } catch (\Exception $e) {
            error_log('MYGO: Failed to delete FluentCart product ' . $productId . ': ' . $e->getMessage());
            return false;
        }
    }
}
