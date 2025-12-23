<?php

namespace BuyGo\Core\Services;

class NotificationTemplates {

    /**
     * 快取所有自訂模板（單次請求內）
     */
    private static $cached_custom_templates = null;

    /**
     * 快取 key
     */
    private static $cache_key = 'buygo_notification_templates_cache';

    /**
     * 快取群組
     */
    private static $cache_group = 'buygo_notification_templates';

    public static function get($key, $args = []) {
        $templates = self::definitions();
        
        if (!isset($templates[$key])) {
            return null;
        }

        // 先從資料庫讀取自訂模板，如果沒有則使用預設值
        $custom_template = self::get_custom_template($key);
        
        // 如果自訂模板存在，使用自訂模板（即使 line.message 為空，也使用自訂模板）
        // 這樣可以讓用戶清空模板內容，而不會被預設模板覆蓋
        if ($custom_template !== null) {
            $template = $custom_template;
        } else {
            $template = $templates[$key];
        }

        // Process replacements
        $subject = self::replace_placeholders($template['email']['subject'] ?? '', $args);
        $email_body = self::replace_placeholders($template['email']['message'] ?? '', $args);
        $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);

        return [
            'email' => [
                'subject' => $subject,
                'message' => $email_body
            ],
            'line' => [
                'type' => 'text',
                'text' => $line_message
            ]
        ];
    }

    /**
     * 根據 trigger_condition 取得模板（優先使用自訂模板）
     * 如果有多個自訂模板，返回所有匹配的模板（按 message_order 排序）
     * 
     * @param string $trigger_condition 觸發條件（例如 'system_image_uploaded'）
     * @param array $args 模板變數參數
     * @return array|array[] 單個模板或模板陣列（格式與 get() 相同），每個模板包含額外的 'message_order' 和 'send_interval' 欄位
     */
    public static function get_by_trigger_condition($trigger_condition, $args = []) {
        // 查找所有匹配的自訂模板（根據 trigger_condition）
        $custom_templates = self::get_all_custom_templates_by_trigger($trigger_condition);
        
        $result = [];
        
        // 檢查是否有自訂模板設定了 message_order = 1
        $has_custom_order_1 = false;
        foreach ($custom_templates as $template_data) {
            $message_order = intval($template_data['message_order'] ?? 1);
            if ($message_order === 1) {
                $has_custom_order_1 = true;
                break;
            }
        }
        
        // 預設模板邏輯：
        // 1. 如果沒有自訂模板，使用預設模板
        // 2. 如果有自訂模板但沒有 message_order = 1 的，預設模板作為第 1 則
        // 3. 如果有自訂模板且 message_order = 1，預設模板不會出現（被自訂模板替換）
        $default_templates = self::definitions();
        if (!$has_custom_order_1 && isset($default_templates[$trigger_condition])) {
            $template = $default_templates[$trigger_condition];
            
            // Process replacements
            $subject = self::replace_placeholders($template['email']['subject'] ?? '', $args);
            $email_body = self::replace_placeholders($template['email']['message'] ?? '', $args);
            $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);
            
            $result[] = [
                'email' => [
                    'subject' => $subject,
                    'message' => $email_body
                ],
                'line' => [
                    'type' => 'text',
                    'text' => $line_message
                ],
                'message_order' => 1, // 預設模板為第一則
                'send_interval' => 0 // 預設模板沒有間隔
            ];
        }
        
        // 然後加入所有自訂模板（按 message_order 排序）
        if (!empty($custom_templates)) {
            foreach ($custom_templates as $template_data) {
                $template = $template_data['template'];
                $message_order = $template_data['message_order'] ?? 1;
                $send_interval = $template_data['send_interval'] ?? 0.5;
                
                // Process replacements
                $subject = self::replace_placeholders($template['email']['subject'] ?? '', $args);
                $email_body = self::replace_placeholders($template['email']['message'] ?? '', $args);
                $line_message = self::replace_placeholders($template['line']['message'] ?? '', $args);
                
                $result[] = [
                    'email' => [
                        'subject' => $subject,
                        'message' => $email_body
                    ],
                    'line' => [
                        'type' => 'text',
                        'text' => $line_message
                    ],
                    'message_order' => $message_order,
                    'send_interval' => $send_interval
                ];
            }
        }
        
        // 如果沒有任何模板，返回空陣列
        if (empty($result)) {
            return [];
        }
        
        // 按照 message_order 排序（確保順序正確）
        usort($result, function($a, $b) {
            return ($a['message_order'] ?? 1) - ($b['message_order'] ?? 1);
        });
        
        // 返回陣列（即使只有一個模板，也返回陣列以便統一處理）
        return $result;
    }

    /**
     * 根據 trigger_condition 取得所有自訂模板（按 message_order 排序）
     * 
     * @param string $trigger_condition 觸發條件
     * @return array 自訂模板陣列，每個元素包含 'template', 'message_order', 'send_interval'
     */
    private static function get_all_custom_templates_by_trigger($trigger_condition) {
        $custom_templates = self::get_all_custom_templates();
        $custom_metadata = get_option('buygo_notification_templates_metadata', []);
        
        // 查找所有匹配的自訂模板
        $matched_templates = [];
        foreach ($custom_templates as $key => $template) {
            $metadata = $custom_metadata[$key] ?? [];
            $metadata_trigger = $metadata['trigger_condition'] ?? '';
            
            // 匹配條件：
            // 1. metadata 中有 trigger_condition 且匹配
            // 2. 或者模板的 key 等於 trigger_condition（系統預設模板的自定義版本）
            $matches = false;
            if (isset($metadata['trigger_condition']) && $metadata['trigger_condition'] === $trigger_condition) {
                $matches = true;
            } elseif ($key === $trigger_condition) {
                // 系統預設模板的自定義版本（key 就是 trigger_condition）
                $matches = true;
            }
            
            if ($matches) {
                $matched_templates[] = [
                    'key' => $key,
                    'template' => $template,
                    'message_order' => intval($metadata['message_order'] ?? 1),
                    'send_interval' => floatval($metadata['send_interval'] ?? 0.5)
                ];
            }
        }
        
        // 按照 message_order 排序
        if (!empty($matched_templates)) {
            usort($matched_templates, function($a, $b) {
                return ($a['message_order'] ?? 1) - ($b['message_order'] ?? 1);
            });
        }
        
        return $matched_templates;
    }

    /**
     * 從資料庫讀取自訂模板（帶快取）
     */
    private static function get_custom_template($key) {
        $custom_templates = self::get_all_custom_templates();
        return isset($custom_templates[$key]) ? $custom_templates[$key] : null;
    }

    /**
     * 取得所有自訂模板（帶快取）
     */
    private static function get_all_custom_templates() {
        // 先檢查 static 變數快取（單次請求內最快）
        if (self::$cached_custom_templates !== null) {
            return self::$cached_custom_templates;
        }

        // 再檢查 WordPress object cache（跨請求快取）
        $cached = wp_cache_get(self::$cache_key, self::$cache_group);
        
        if ($cached !== false) {
            self::$cached_custom_templates = $cached;
            return $cached;
        }

        // 最後才從資料庫讀取
        $templates = get_option('buygo_notification_templates', []);
        
        // 儲存到快取
        self::$cached_custom_templates = $templates;
        wp_cache_set(self::$cache_key, $templates, self::$cache_group, 3600); // 快取 1 小時

        return $templates;
    }

    /**
     * 取得所有模板（包含自訂和預設）
     */
    public static function get_all_templates() {
        $default_templates = self::definitions();
        $custom_templates = self::get_all_custom_templates();
        
        $result = [];
        // 先加入所有預設模板（如果有自訂版本則使用自訂版本）
        foreach ($default_templates as $key => $default) {
            $result[$key] = isset($custom_templates[$key]) ? $custom_templates[$key] : $default;
        }
        
        // 再加入所有完全自訂的模板（key 以 custom_ 開頭）
        foreach ($custom_templates as $key => $custom) {
            if (strpos($key, 'custom_') === 0 && !isset($default_templates[$key])) {
                $result[$key] = $custom;
            }
        }
        
        return $result;
    }

    /**
     * 儲存自訂模板
     */
    public static function save_custom_templates($templates) {
        update_option('buygo_notification_templates', $templates);
        
        // 清除快取
        self::clear_cache();
    }

    /**
     * 清除快取
     */
    public static function clear_cache() {
        // 清除 static 變數快取
        self::$cached_custom_templates = null;
        
        // 清除 WordPress object cache
        wp_cache_delete(self::$cache_key, self::$cache_group);
    }

    private static function replace_placeholders($text, $args) {
        // 先替換所有變數
        foreach ($args as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        // 清理多餘的空行：
        // 1. 移除連續的多個空行（3個或以上），只保留一個空行
        // 2. 移除只包含標籤但沒有值的行（例如「原價：」後面沒有值）
        // 3. 清理開頭和結尾的空行
        
        // 先將連續的多個空行（3個或以上）合併為兩個換行符
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // 然後逐行處理，移除只包含標籤但沒有值的行
        $lines = explode("\n", $text);
        $cleaned_lines = [];
        $prev_empty = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            $is_empty = $trimmed === '';
            
            // 檢查是否是只包含標籤但沒有值的行（例如「原價：」）
            // 這種行通常以「：」結尾，且後面沒有內容
            $is_label_only = false;
            if (!$is_empty && preg_match('/^[^：:]+[：:]\s*$/', $trimmed)) {
                $is_label_only = true;
            }
            
            // 如果是只包含標籤的行，跳過（不加入結果）
            if ($is_label_only) {
                $prev_empty = true; // 標記為空，以便後續處理
                continue;
            }
            
            // 如果是空行，且前一行也是空行，跳過（避免連續空行）
            if ($is_empty && $prev_empty) {
                continue;
            }
            
            $cleaned_lines[] = $line;
            $prev_empty = $is_empty;
        }
        
        $text = implode("\n", $cleaned_lines);
        
        // 清理開頭和結尾的空行
        $text = trim($text);
        
        return $text;
    }

    private static function definitions() {
        return [
            'admin_new_seller_application' => [
                'email' => [
                    'subject' => '【BuyGo】新的賣家申請待審核',
                    'message' => "您好，\n\n有新的賣家申請需要審核：\n\n申請人：{display_name} ({user_email})\n真實姓名：{real_name}\n聯絡電話：{phone}\nLINE ID：{line_id}\n申請時間：{submitted_at}\n\n請登入後台審核：{admin_url}\n\n謝謝！"
                ],
                'line' => [
                    'message' => "🔔 新的賣家申請\n\n申請人：{display_name}\n時間：{submitted_at}\n\n請至後台審核。"
                ]
            ],
            'seller_application_approved' => [
                'email' => [
                    'subject' => '【BuyGo】您的賣家申請已核准',
                    'message' => "恭喜 {display_name}，\n\n您的賣家申請已通過審核！\n\n{review_note_section}\n\n您現在可以開始上架商品了。\n\n請透過以下指令開始使用：\n• 上架商品：直接傳送商品照片\n• 查看訂單：輸入「我的訂單」\n• 管理商品：輸入「我的商品」\n\n祝您銷售順利！"
                ],
                'line' => [
                    'message' => "🎉 恭喜！您的賣家申請已通過審核\n\n您現在可以開始上架商品了！\n\n請透過以下指令開始使用：\n• 上架商品：直接傳送商品照片\n• 查看訂單：輸入「我的訂單」\n• 管理商品：輸入「我的商品」\n\n祝您銷售順利！"
                ]
            ],
            'seller_application_rejected' => [
                'email' => [
                    'subject' => '【BuyGo】您的賣家申請未通過審核',
                    'message' => "{display_name} 您好，\n\n很抱歉，您的賣家申請未通過審核。\n\n拒絕原因：{review_note}\n\n如有疑問，請聯絡客服。"
                ],
                'line' => [
                    'message' => "很抱歉，您的賣家申請未通過審核\n\n拒絕原因：{review_note}\n\n如有疑問，請聯絡客服。"
                ]
            ],
            'helper_assigned' => [
                'email' => [
                    'subject' => '【BuyGo】您已被邀請成為小幫手',
                    'message' => "{helper_name} 您好，\n\n您已被 {seller_name} 邀請成為小幫手。\n\n請登入後台查看詳情：{link}\n\n謝謝！"
                ],
                'line' => [
                    'message' => "📢 您已被邀請成為小幫手\n\n賣家：{seller_name}\n\n請登入查看詳情。"
                ]
            ],
            'line_binding_success' => [
                'email' => [
                    'subject' => '', 
                    'message' => ''
                ],
                'line' => [
                    'message' => "✅ LINE 帳號綁定成功\n\n您的 LINE 帳號已成功綁定到 BuyGo 系統。\n\n現在您可以：\n• 接收訂單通知\n• 透過 LINE 上架商品（賣家）\n• 查詢訂單狀態\n\n感謝您的使用！"
                ]
            ],
            // Order Notifications
            'order_arrived' => [
                'email' => [
                    'subject' => '【BuyGo】您的訂單商品已到貨！ (單號 #{order_id})',
                    'message' => "您好，\n\n賣家通知：您訂購的商品已到貨！\n\n訂單編號：{order_id}\n賣家備註：{note}\n\n請留意賣家後續通知或依約定方式取貨。\n\n謝謝您的購買！"
                ],
                'line' => [
                    'message' => "✨ 您訂購的商品已到貨！\n\n訂單編號：{order_id}\n賣家備註：{note}\n\n請留意賣家後續通知或前往取貨。"
                ]
            ],
            'order_paid' => [
                'email' => [
                    'subject' => '【BuyGo】賣家已確認您的付款 (單號 #{order_id})',
                    'message' => "您好，\n\n賣家通知：已收到您的款項。\n\n訂單編號：{order_id}\n賣家備註：{note}\n\n賣家將會盡快為您安排出貨或代購。\n\n謝謝您的購買！"
                ],
                'line' => [
                    'message' => "💰 賣家已確認收到您的款項。\n\n訂單編號：{order_id}\n賣家備註：{note}"
                ]
            ],
            'order_shipped' => [
                'email' => [
                    'subject' => '【BuyGo】您的訂單已寄出！ (單號 #{order_id})',
                    'message' => "您好，\n\n賣家通知：您的商品已經寄出！\n\n訂單編號：{order_id}\n賣家備註：{note}\n\n請留意物流簡訊或通知，謝謝您的耐心等待。\n\n謝謝您的購買！"
                ],
                'line' => [
                    'message' => "🚚 您的訂單已寄出！\n\n訂單編號：{order_id}\n賣家備註：{note}\n\n請留意物流簡訊或通知。"
                ]
            ],
            'order_cancelled' => [
                'email' => [
                    'subject' => '【BuyGo】您的訂單狀態更新：已取消/缺貨 (單號 #{order_id})',
                    'message' => "您好，\n\n賣家通知：您的訂單有異動或已取消。\n\n訂單編號：{order_id}\n說明：{note}\n\n如有疑問，請直接聯絡賣家詢問。\n\n謝謝！"
                ],
                'line' => [
                    'message' => "❌ 您的訂單有異動/取消。\n\n訂單編號：{order_id}\n說明：{note}"
                ]
            ],
            'order_processing' => [
                'email' => [
                    'subject' => '【BuyGo】您的訂單處理中 (單號 #{order_id})',
                    'message' => "您好，\n\n賣家已開始處理您的訂單。\n\n訂單編號：{order_id}\n備註：{note}\n\n謝謝您的購買！"
                ],
                'line' => [
                    'message' => "🔄 您的訂單處理中。\n\n訂單編號：{order_id}\n備註：{note}"
                ]
            ],
            // Seller Order Notifications
            'seller_order_created' => [
                'email' => [
                    'subject' => '【BuyGo】您有新的訂單！ (單號 #{order_id})',
                    'message' => "您好，\n\n您有新的訂單需要處理：\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：NT$ {order_total}\n\n請盡快處理訂單。\n\n查看訂單：{order_url}"
                ],
                'line' => [
                    'message' => "🛒 您有新的訂單！\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：NT$ {order_total}\n\n請盡快處理訂單。"
                ]
            ],
            'seller_order_paid' => [
                'email' => [
                    'subject' => '【BuyGo】訂單已付款 (單號 #{order_id})',
                    'message' => "您好，\n\n訂單已收到付款：\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：NT$ {order_total}\n\n請盡快安排出貨。\n\n查看訂單：{order_url}"
                ],
                'line' => [
                    'message' => "💰 訂單已收到付款\n\n訂單編號：{order_id}\n買家：{buyer_name}\n金額：NT$ {order_total}\n\n請盡快安排出貨。"
                ]
            ],
            'seller_order_cancelled' => [
                'email' => [
                    'subject' => '【BuyGo】訂單已取消 (單號 #{order_id})',
                    'message' => "您好，\n\n訂單已被取消：\n\n訂單編號：{order_id}\n買家：{buyer_name}\n取消原因：{note}\n\n查看訂單：{order_url}"
                ],
                'line' => [
                    'message' => "❌ 訂單已取消\n\n訂單編號：{order_id}\n買家：{buyer_name}\n取消原因：{note}"
                ]
            ],
            // System Messages
            'system_line_follow' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => "歡迎使用 BuyGo 商品上架 🎉\n\n【快速開始】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【格式範例】\n商品名稱\n價格：350\n數量：20\n\n💡 輸入 /help 查看完整說明"
                ]
            ],
            'system_image_uploaded' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => "✅ 圖片已收到！\n\n請發送商品資訊：\n商品名稱、價格、數量\n\n💡 輸入 /help 查看格式說明"
                ]
            ],
            'system_copy_template' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => "📋 複製以下格式發送商品資訊：\n\n商品名稱\n價格：XXX\n數量：XXX\n到貨：YYYY/MM/DD（選填）\n預購：YYYY/MM/DD（選填）"
                ]
            ],
            'system_account_not_bound' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => '請先使用 LINE Login 綁定您的帳號。'
                ]
            ],
            'system_no_permission' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => '您沒有上傳商品的權限。請先申請成為賣家。'
                ]
            ],
            'system_image_upload_failed' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => '圖片上傳失敗，請稍後再試。'
                ]
            ],
            'system_user_error' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => '系統錯誤：無法識別使用者。請重新綁定 LINE 帳號。'
                ]
            ],
            // Product Upload Messages
            'system_product_published' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => "商品名稱：{product_name}\n價格：{currency_symbol} {price}{original_price_section}\n數量：{quantity} 個{category_section}{arrival_date_section}{preorder_date_section}\n\n直接下單連結：\n{product_url}{community_url_section}"
                ]
            ],
            'system_product_publish_failed' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => '❌ 商品上架失敗：{error_message}'
                ]
            ],
            'system_product_data_incomplete' => [
                'email' => [
                    'subject' => '',
                    'message' => ''
                ],
                'line' => [
                    'message' => "商品資料不完整，缺少：{missing_fields}\n\n請使用以下格式：\n商品名稱\n價格：350\n數量：20"
                ]
            ]
        ];
    }
}
