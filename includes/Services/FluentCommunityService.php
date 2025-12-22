<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * FluentCommunity Service
 * 
 * 整合 FluentCommunity 的貼文與留言操作
 */
class FluentCommunityService
{
    /**
     * 發布商品貼文
     *
     * @param array $product 商品資料
     * @param int|null $spaceId 頻道 ID
     * @return array ['success' => bool, 'feed_id' => int, 'error' => string]
     */
    public function publishProductPost(array $product, ?int $spaceId = null): array
    {
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            return [
                'success' => false,
                'error' => 'FluentCommunity 未安裝',
            ];
        }

        try {
            // 取得 space slug
            $spaceSlug = $this->getDefaultSpaceSlug();
            error_log('MYGO FluentCommunityService: publishProductPost - spaceSlug = ' . $spaceSlug);
            
            // 確保系統使用者加入 space 並有權限
            $this->ensureSystemUserInSpace($spaceSlug);
            
            $message = $this->formatProductMessage($product);
            
            $postData = [
                'message' => $message,
                'space' => $spaceSlug,  // FluentCommunity API 使用 space slug
            ];

            // 準備圖片 URL
            $imageUrl = null;
            $imageWidth = 0;
            $imageHeight = 0;
            
            if (!empty($product['image_attachment_id'])) {
                $attachmentId = $product['image_attachment_id'];
                $imageUrl = wp_get_attachment_url($attachmentId);
                
                // 取得圖片尺寸
                $metadata = wp_get_attachment_metadata($attachmentId);
                if ($metadata) {
                    $imageWidth = $metadata['width'] ?? 0;
                    $imageHeight = $metadata['height'] ?? 0;
                }
                
                // 確保 product 陣列有 image_url（用於 formatProductMessage）
                $product['image_url'] = $imageUrl;
                
                error_log('MYGO FluentCommunityService: publishProductPost - image from attachment_id = ' . $attachmentId . ', url = ' . $imageUrl);
            } elseif (!empty($product['image_url'])) {
                $imageUrl = $product['image_url'];
                error_log('MYGO FluentCommunityService: publishProductPost - image from url = ' . $imageUrl);
            }

            // 重新格式化訊息（包含圖片）
            $message = $this->formatProductMessage($product);
            $postData['message'] = $message;

            error_log('MYGO FluentCommunityService: publishProductPost - postData = ' . json_encode($postData, JSON_UNESCAPED_UNICODE));

            // 使用 FluentCommunity API 發布貼文
            $response = $this->callFluentCommunityApi('feeds', 'POST', $postData);

            if ($response === null) {
                error_log('MYGO FluentCommunityService: publishProductPost - API returned null, check logs above for details');
                return [
                    'success' => false,
                    'error' => '發布貼文失敗：API 回傳錯誤，請檢查日誌',
                ];
            }

            // FluentCommunity API 回傳格式是 {"feed": {...}, "message": "..."}
            $feed = $response['feed'] ?? $response;
            
            if (!$feed || !isset($feed['id'])) {
                error_log('MYGO FluentCommunityService: publishProductPost - feed creation failed, response = ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                return [
                    'success' => false,
                    'error' => '發布貼文失敗：回傳資料格式錯誤',
                ];
            }

            error_log('MYGO FluentCommunityService: publishProductPost - feed created, id = ' . $feed['id']);

            // 儲存關聯
            if (!empty($product['id'])) {
                update_post_meta($product['id'], '_mygo_feed_id', $feed['id']);
            }

            do_action('mygo/feed/published', $feed['id'], $product);

            return [
                'success' => true,
                'feed_id' => $feed['id'],
                'feed' => $feed,
            ];

        } catch (\Exception $e) {
            error_log('MYGO FluentCommunityService: publishProductPost - exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 格式化商品貼文內容
     * 支援單一商品與多樣式商品
     * 使用後台設定的模版進行變數替換
     */
    public function formatProductMessage(array $product): string
    {
        $lines = [];
        
        // === 圖片 ===
        if (!empty($product['image_url'])) {
            $imageUrl = esc_url($product['image_url']);
            $lines[] = '<img src="' . $imageUrl . '" alt="' . esc_attr($product['name'] ?? '商品圖片') . '" style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 16px;">';
            $lines[] = '';
        }
        
        // === 判斷單一或多樣式 ===
        $hasVariations = isset($product['variations']) && is_array($product['variations']);
        
        if ($hasVariations) {
            // === 驗證：陣列是否為空 ===
            if (empty($product['variations'])) {
                throw new \Exception('發文失敗：多樣式商品沒有樣式資料');
            }

            // === 驗證：每個樣式是否都有價格 ===
            foreach ($product['variations'] as $index => $variation) {
                if (!isset($variation['price']) || $variation['price'] === null || $variation['price'] === '') {
                    $title = $variation['variation_title'] ?? "樣式 #{$index}";
                    throw new \Exception("發文失敗：樣式「{$title}」未設定價格");
                }
            }
            
            // === 生成樣式清單 ===
            $variationLines = [];
            foreach ($product['variations'] as $variation) {
                $varLine = '';
                
                // 代碼
                $code = $variation['code'] ?? '';
                if (!empty($code)) {
                    $varLine .= '▫️ ' . strtoupper($code) . ' - ';
                } else {
                    $varLine .= '▫️ ';
                }
                
                // 樣式名稱
                if (!empty($variation['variation_title'])) {
                    $varLine .= $variation['variation_title'];
                }
                
                // 價格
                if (!empty($variation['price'])) {
                    $varLine .= ' - NT$ ' . number_format($variation['price']);
                }
                
                // 庫存狀態
                $stock = $variation['stock'] ?? $variation['quantity'] ?? 0;
                if ($stock > 0) {
                    $varLine .= ' (庫存：' . $stock . ')';
                } else {
                    $varLine .= ' ❌ 已售完';
                }
                
                $variationLines[] = $varLine;
            }
            
            // 使用多樣式模版
            $template = $this->getCommunityTemplate('multi');
            $content = $this->replaceTemplateVariables($template, [
                '商品名稱' => $product['name'] ?? '新商品',
                '樣式清單' => implode("\n", $variationLines),
                '到貨日' => $product['arrival_date'] ?? '',
                '描述' => $product['description'] ?? ''
            ]);
            
        } else {
            // 使用單一商品模版
            $template = $this->getCommunityTemplate('single');
            $content = $this->replaceTemplateVariables($template, [
                '商品名稱' => $product['name'] ?? '新商品',
                '價格' => !empty($product['price']) ? 'NT$ ' . number_format($product['price']) : '',
                '庫存' => $product['quantity'] ?? '',
                '到貨日' => $product['arrival_date'] ?? '',
                '描述' => $product['description'] ?? ''
            ]);
        }
        
        // 清理多餘的空行（當變數為空時可能產生）
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // 合併圖片和內容
        $lines[] = trim($content);
        
        return implode("\n", $lines);
    }

    /**
     * 取得社群上架模版
     * 
     * @param string $type 'single' 或 'multi'
     * @return string 模版內容
     */
    private function getCommunityTemplate(string $type): string
    {
        $defaults = [
            'single' => "🛒 {商品名稱}\n\n💰 價格：{價格}\n📦 數量：{庫存} 個\n\n👉 留言 +1 即可下單！\n👉 +數量 可購買多個（如 +2）\n📅 到貨：{到貨日}\n\n{描述}",
            'multi' => "🛒 {商品名稱}\n\n📦 商品樣式：\n\n{樣式清單}\n\n👉 留言格式：[代碼]+[數量]\n   例如：A+1 或 B+2\n📅 到貨：{到貨日}\n\n{描述}"
        ];
        
        $templates = get_option('buygo_community_templates', $defaults);
        
        return $templates[$type] ?? $defaults[$type];
    }

    /**
     * 替換模版中的變數
     * 
     * @param string $template 模版內容
     * @param array $variables 變數陣列 ['變數名' => '值']
     * @return string 替換後的內容
     */
    private function replaceTemplateVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }



    /**
     * 回覆留言
     *
     * @param int $feedId 貼文 ID
     * @param int $parentCommentId 父留言 ID
     * @param string $message 回覆訊息
     * @return array ['success' => bool, 'comment_id' => int, 'error' => string]
     */
    public function replyToComment(int $feedId, int $parentCommentId, string $message): array
    {
        try {
            $commentData = [
                'comment' => $message,  // FluentCommunity 使用 'comment' 而不是 'message'
                'parent_id' => $parentCommentId,
            ];

            $comment = $this->callFluentCommunityApi("feeds/{$feedId}/comments", 'POST', $commentData);

            if (!$comment || !isset($comment['id'])) {
                return [
                    'success' => false,
                    'error' => '回覆留言失敗',
                ];
            }

            return [
                'success' => true,
                'comment_id' => $comment['id'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 取得貼文關聯的商品 ID
     */
    public function getProductIdByFeed(int $feedId): ?int
    {
        global $wpdb;

        $productId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mygo_feed_id' AND meta_value = %d LIMIT 1",
            $feedId
        ));

        return $productId ? (int) $productId : null;
    }

    /**
     * 取得預設頻道 ID
     */
    private function getDefaultSpaceId(): int
    {
        return (int) get_option('mygo_default_space_id', 1);
    }

    /**
     * 取得預設頻道 Slug
     */
    private function getDefaultSpaceSlug(): string
    {
        $spaceSlug = get_option('mygo_default_space_slug', '');
        
        // 如果沒有設定 slug，嘗試從 space_id 取得
        if (empty($spaceSlug)) {
            $spaceId = $this->getDefaultSpaceId();
            if ($spaceId && class_exists('\FluentCommunity\App\Models\Space')) {
                $space = \FluentCommunity\App\Models\Space::find($spaceId);
                if ($space) {
                    $spaceSlug = $space->slug;
                }
            }
        }
        
        // 如果還是沒有，嘗試取得第一個可用的 space
        if (empty($spaceSlug)) {
            $spaceSlug = $this->getFirstAvailableSpaceSlug();
        }
        
        return $spaceSlug ?: 'general';
    }
    
    /**
     * 確保系統使用者加入 space 並有權限
     */
    private function ensureSystemUserInSpace(string $spaceSlug): void
    {
        if (!class_exists('\FluentCommunity\App\Models\Space') || !class_exists('\FluentCommunity\App\Services\Helper')) {
            return;
        }
        
        try {
            $space = \FluentCommunity\App\Models\Space::where('slug', $spaceSlug)->first();
            if (!$space) {
                error_log('MYGO FluentCommunityService: ensureSystemUserInSpace - space not found: ' . $spaceSlug);
                return;
            }
            
            $systemUserId = $this->getSystemUserId();
            
            // 檢查使用者是否已在 space 中
            if (!\FluentCommunity\App\Services\Helper::isUserInSpace($systemUserId, $space->id)) {
                // 將使用者加入 space（作為管理員）
                \FluentCommunity\App\Services\Helper::addToSpace($space->id, $systemUserId, 'admin', 'by_admin');
                error_log('MYGO FluentCommunityService: ensureSystemUserInSpace - added user ' . $systemUserId . ' to space ' . $space->id);
            }
        } catch (\Exception $e) {
            error_log('MYGO FluentCommunityService: ensureSystemUserInSpace - exception: ' . $e->getMessage());
        }
    }
    
    /**
     * 取得系統使用者 ID（用於發布貼文）
     */
    private function getSystemUserId(): int
    {
        $userId = get_option('mygo_system_user_id', 0);
        
        // 檢查使用者是否存在
        if ($userId) {
            $user = get_user_by('id', $userId);
            if ($user) {
                return $userId;
            }
        }
        
        // 如果沒有設定或使用者不存在，自動取得第一個管理員
        $admins = get_users([
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        if (!empty($admins)) {
            $adminId = $admins[0]->ID;
            // 自動儲存為預設值
            update_option('mygo_system_user_id', $adminId);
            error_log('MYGO FluentCommunityService: Auto-detected system user_id = ' . $adminId);
            return $adminId;
        }
        
        // 如果沒有管理員，回傳當前使用者或 1（作為最後手段）
        $currentUserId = get_current_user_id();
        return $currentUserId ?: 1;
    }
    
    /**
     * 取得第一個可用的 Space Slug
     */
    private function getFirstAvailableSpaceSlug(): string
    {
        if (!class_exists('\FluentCommunity\App\Models\Space')) {
            return '';
        }
        
        try {
            // 取得第一個已發布的 community space
            $space = \FluentCommunity\App\Models\Space::where('type', 'community')
                ->where('status', 'published')
                ->orderBy('serial', 'ASC')
                ->orderBy('id', 'ASC')
                ->first();
            
            if ($space && !empty($space->slug)) {
                // 自動儲存為預設值，方便下次使用
                update_option('mygo_default_space_slug', $space->slug);
                update_option('mygo_default_space_id', $space->id);
                error_log('MYGO FluentCommunityService: Auto-detected space slug = ' . $space->slug . ', id = ' . $space->id);
                return $space->slug;
            }
        } catch (\Exception $e) {
            error_log('MYGO FluentCommunityService: getFirstAvailableSpaceSlug - exception: ' . $e->getMessage());
        }
        
        return '';
    }

    /**
     * 呼叫 FluentCommunity API
     */
    private function callFluentCommunityApi(string $endpoint, string $method, array $data = []): ?array
    {
        error_log('MYGO FluentCommunityService: callFluentCommunityApi - endpoint = ' . $endpoint . ', method = ' . $method);
        error_log('MYGO FluentCommunityService: callFluentCommunityApi - data = ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        // 設定當前使用者（使用系統帳號發布）
        $adminId = $this->getSystemUserId();
        $previousUserId = get_current_user_id();
        wp_set_current_user($adminId);
        error_log('MYGO FluentCommunityService: callFluentCommunityApi - using user_id = ' . $adminId);

        $request = new \WP_REST_Request($method, "/fluent-community/v2/{$endpoint}");
        
        if (!empty($data)) {
            // 對於 POST/PUT/PATCH 請求，使用 body params
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request->set_body_params($data);
            } else {
                // GET/DELETE 使用 query params
                foreach ($data as $key => $value) {
                    $request->set_param($key, $value);
                }
            }
        }

        $response = rest_do_request($request);
        
        error_log('MYGO FluentCommunityService: callFluentCommunityApi - response status = ' . $response->get_status());
        
        // 還原使用者
        if ($previousUserId) {
            wp_set_current_user($previousUserId);
        }
        
        if ($response->is_error()) {
            $error = $response->as_error();
            $errorMessage = $error->get_error_message();
            $errorData = $error->get_error_data();
            
            error_log('MYGO FluentCommunityService: callFluentCommunityApi - error = ' . $errorMessage);
            error_log('MYGO FluentCommunityService: callFluentCommunityApi - error code = ' . $error->get_error_code());
            error_log('MYGO FluentCommunityService: callFluentCommunityApi - response data = ' . json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
            
            // 如果是權限錯誤，嘗試確保使用者加入 space
            if (strpos($errorMessage, 'permission') !== false || strpos($errorMessage, 'not allowed') !== false) {
                if (isset($data['space'])) {
                    $this->ensureSystemUserInSpace($data['space']);
                }
            }
            
            return null;
        }

        $responseData = $response->get_data();
        error_log('MYGO FluentCommunityService: callFluentCommunityApi - response data = ' . json_encode($responseData, JSON_UNESCAPED_UNICODE));

        // FluentCommunity API 回傳格式: {"comment": {...}, "message": "..."}
        // comment 可能是物件或陣列，統一轉換成陣列
        if (isset($responseData['comment'])) {
            $comment = $responseData['comment'];
            // 如果是物件，轉換成陣列
            if (is_object($comment)) {
                return json_decode(json_encode($comment), true);
            }
            return $comment;
        }

        return $responseData;
    }

    /**
     * 更新貼文 media
     * 
     * @param int $feedId 貼文 ID
     * @param array $mediaData 媒體資料陣列
     */
    private function updateFeedMedia(int $feedId, array $mediaData): bool
    {
        if (!class_exists('\FluentCommunity\App\Models\Feed')) {
            return false;
        }

        try {
            $feed = \FluentCommunity\App\Models\Feed::find($feedId);
            if (!$feed) {
                return false;
            }

            // 直接設定 media 欄位
            $feed->media = $mediaData;
            $feed->save();
            
            error_log('MYGO FluentCommunityService: updateFeedMedia - updated feed ' . $feedId . ' with media = ' . json_encode($mediaData, JSON_UNESCAPED_UNICODE));
            
            return true;
        } catch (\Exception $e) {
            error_log('MYGO FluentCommunityService: updateFeedMedia - error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新貼文 meta
     * 
     * @param int $feedId 貼文 ID
     * @param array $metaData 要更新的 meta 資料
     */
    private function updateFeedMeta(int $feedId, array $metaData): bool
    {
        if (!class_exists('\FluentCommunity\App\Models\Feed')) {
            return false;
        }

        try {
            $feed = \FluentCommunity\App\Models\Feed::find($feedId);
            if (!$feed) {
                return false;
            }

            // 合併現有的 meta 和新的 meta
            $existingMeta = $feed->meta ?: [];
            $newMeta = array_merge($existingMeta, $metaData);
            
            $feed->meta = $newMeta;
            $feed->save();
            
            error_log('MYGO FluentCommunityService: updateFeedMeta - updated feed ' . $feedId . ' with meta = ' . json_encode($newMeta, JSON_UNESCAPED_UNICODE));
            
            return true;
        } catch (\Exception $e) {
            error_log('MYGO FluentCommunityService: updateFeedMeta - error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 取得貼文資料
     */
    public function getFeed(int $feedId): ?array
    {
        return $this->callFluentCommunityApi("feeds/{$feedId}", 'GET');
    }

    /**
     * 取得留言資料
     */
    public function getComment(int $feedId, int $commentId): ?array
    {
        $comments = $this->callFluentCommunityApi("feeds/{$feedId}/comments", 'GET');
        
        if (!$comments) {
            return null;
        }

        foreach ($comments as $comment) {
            if (($comment['id'] ?? 0) === $commentId) {
                return $comment;
            }
        }

        return null;
    }
}
