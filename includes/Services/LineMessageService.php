<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * LINE Message Service
 * 
 * 發送 LINE 訊息，包含 Flex Message 卡片
 */
class LineMessageService
{
    private const PUSH_URL = 'https://api.line.me/v2/bot/message/push';
    private const REPLY_URL = 'https://api.line.me/v2/bot/message/reply';

    /**
     * 發送商品卡片
     */
    public function sendProductCard(string $userId, array $product): bool
    {
        $card = $this->buildProductCard($product);
        return $this->pushMessage($userId, [$card]);
    }

    /**
     * 建立商品卡片 Flex Message
     */
    public function buildProductCard(array $product): array
    {
        $productUrl = $product['url'] ?? home_url('/product/' . ($product['id'] ?? ''));
        $communityUrl = $product['community_url'] ?? '';
        
        // 準備商品資訊
        $productCode = $product['code'] ?? '';
        $price = intval($product['price'] ?? 0);
        $quantity = intval($product['quantity'] ?? 0);
        $arrivalDate = $product['arrival_date'] ?? '';
        $preorderDate = $product['preorder_date'] ?? '';
        
        // 狀態標籤
        $statusText = $quantity > 0 ? '預購中' : '已售完';
        $statusColor = $quantity > 0 ? '#34C759' : '#FF3B30';
        
        // 建立商品資訊內容
        $bodyContents = [
            [
                'type' => 'text',
                'text' => '🎉 新商品上架',
                'size' => 'sm',
                'color' => '#8E8E93',
                'margin' => 'none',
            ],
        ];
        
        // 商品代碼
        if ($productCode) {
            $bodyContents[] = [
                'type' => 'text',
                'text' => '代碼：' . $productCode,
                'size' => 'xs',
                'color' => '#8E8E93',
                'margin' => 'xs',
            ];
        }
        
        // 商品名稱
        $bodyContents[] = [
            'type' => 'text',
            'text' => $product['name'] ?? '商品名稱',
            'weight' => 'bold',
            'size' => 'xl',
            'wrap' => true,
            'margin' => 'md',
        ];
        
        // 價格區塊
        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'baseline',
            'margin' => 'md',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => 'NT$ ' . number_format($price),
                    'size' => 'xxl',
                    'color' => '#FF3B30',
                    'weight' => 'bold',
                    'flex' => 0,
                ],
            ],
        ];
        
        // 商品詳細資訊
        $infoContents = [];
        
        // 狀態
        $infoContents[] = [
            'type' => 'box',
            'layout' => 'baseline',
            'spacing' => 'sm',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => '狀態',
                    'color' => '#8E8E93',
                    'size' => 'sm',
                    'flex' => 2,
                ],
                [
                    'type' => 'text',
                    'text' => $statusText,
                    'wrap' => true,
                    'color' => $statusColor,
                    'size' => 'sm',
                    'flex' => 3,
                    'weight' => 'bold',
                ],
            ],
        ];
        
        // 庫存
        $infoContents[] = [
            'type' => 'box',
            'layout' => 'baseline',
            'spacing' => 'sm',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => '庫存',
                    'color' => '#8E8E93',
                    'size' => 'sm',
                    'flex' => 2,
                ],
                [
                    'type' => 'text',
                    'text' => '剩 ' . $quantity . ' 件',
                    'wrap' => true,
                    'color' => '#1C1C1E',
                    'size' => 'sm',
                    'flex' => 3,
                ],
            ],
        ];
        
        // 到貨日期
        if ($arrivalDate) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'baseline',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '到貨日',
                        'color' => '#8E8E93',
                        'size' => 'sm',
                        'flex' => 2,
                    ],
                    [
                        'type' => 'text',
                        'text' => date('Y/m/d', strtotime($arrivalDate)),
                        'wrap' => true,
                        'color' => '#1C1C1E',
                        'size' => 'sm',
                        'flex' => 3,
                    ],
                ],
            ];
        }
        
        // 預購截止
        if ($preorderDate) {
            $infoContents[] = [
                'type' => 'box',
                'layout' => 'baseline',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '預購截止',
                        'color' => '#8E8E93',
                        'size' => 'sm',
                        'flex' => 2,
                    ],
                    [
                        'type' => 'text',
                        'text' => date('Y/m/d', strtotime($preorderDate)),
                        'wrap' => true,
                        'color' => '#FF9500',
                        'size' => 'sm',
                        'flex' => 3,
                        'weight' => 'bold',
                    ],
                ],
            ];
        }
        
        $bodyContents[] = [
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'spacing' => 'sm',
            'contents' => $infoContents,
        ];
        
        // 建立按鈕
        $footerButtons = [];
        
        // 使用社群貼文連結，如果沒有則使用商品連結
        $orderUrl = !empty($communityUrl) ? $communityUrl : $productUrl;
        
        // 1. 點擊下單按鈕
        $footerButtons[] = [
            'type' => 'button',
            'style' => 'primary',
            'height' => 'sm',
            'action' => [
                'type' => 'uri',
                'label' => '點擊下單',
                'uri' => $orderUrl,
            ],
            'color' => '#007AFF',
        ];
        
        // 2. 分享給朋友按鈕（使用 LIFF）
        $liffId = get_option('mygo_liff_id', '');
        
        if (!empty($liffId) && !empty($product['id'])) {
            // 使用 LIFF Share Target Picker
            $liffUrl = 'https://liff.line.me/' . $liffId . '?productId=' . $product['id'];
            
            $footerButtons[] = [
                'type' => 'button',
                'style' => 'link',
                'height' => 'sm',
                'action' => [
                    'type' => 'uri',
                    'label' => '分享給朋友',
                    'uri' => $liffUrl,
                ],
            ];
        } else {
            // 備用：使用純文字分享
            $shareText = "🎉 新商品上架！\n\n" 
                . ($product['name'] ?? '商品') . "\n"
                . "💰 NT$ " . number_format($price) . "\n"
                . "📦 剩 " . $quantity . " 件\n\n"
                . "👉 點擊下單：" . $orderUrl;
            
            $footerButtons[] = [
                'type' => 'button',
                'style' => 'link',
                'height' => 'sm',
                'action' => [
                    'type' => 'uri',
                    'label' => '分享給朋友',
                    'uri' => 'https://line.me/R/share?text=' . urlencode($shareText),
                ],
            ];
        }
        
        return [
            'type' => 'flex',
            'altText' => '🎉 新商品：' . ($product['name'] ?? '商品') . ' NT$ ' . number_format($price),
            'contents' => [
                'type' => 'bubble',
                'hero' => [
                    'type' => 'image',
                    'url' => $product['image_url'] ?? 'https://via.placeholder.com/800x800?text=No+Image',
                    'size' => 'full',
                    'aspectRatio' => '1:1',
                    'aspectMode' => 'cover',
                    'action' => [
                        'type' => 'uri',
                        'uri' => $productUrl,
                    ],
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => $bodyContents,
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => $footerButtons,
                    'flex' => 0,
                ],
            ],
        ];
    }

    /**
     * 發送訂單確認卡片（給買家）
     */
    public function sendOrderConfirmCard(string $userId, array $order): bool
    {
        $card = $this->buildOrderConfirmCard($order);
        return $this->pushMessage($userId, [$card]);
    }
    
    /**
     * 發送訂單通知卡片（給賣家）
     */
    public function sendSellerOrderNotification(string $sellerLineUid, array $order): bool
    {
        $card = $this->buildSellerOrderCard($order);
        return $this->pushMessage($sellerLineUid, [$card]);
    }

    /**
     * 建立訂單確認卡片
     */
    public function buildOrderConfirmCard(array $order): array
    {
        return [
            'type' => 'flex',
            'altText' => '訂單確認 #' . ($order['order_number'] ?? ''),
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '訂單確認',
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#007AFF',
                        ],
                        [
                            'type' => 'text',
                            'text' => '#' . ($order['order_number'] ?? ''),
                            'size' => 'sm',
                            'color' => '#8E8E93',
                            'margin' => 'md',
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'lg',
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'lg',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '商品',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 1,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $order['product_name'] ?? '',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                            'wrap' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '數量',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 1,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => ($order['quantity'] ?? 1) . ' 個',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '金額',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 1,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => 'NT$ ' . number_format($order['total'] ?? 0),
                                            'size' => 'sm',
                                            'color' => '#007AFF',
                                            'weight' => 'bold',
                                            'flex' => 3,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 建立賣家訂單通知卡片
     */
    public function buildSellerOrderCard(array $order): array
    {
        return [
            'type' => 'flex',
            'altText' => '用戶下單 #' . ($order['order_number'] ?? ''),
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '用戶下單',
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#007AFF',
                        ],
                        [
                            'type' => 'separator',
                            'margin' => 'lg',
                        ],
                        [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'lg',
                            'spacing' => 'sm',
                            'contents' => [
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '訂單編號',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => '#' . ($order['order_number'] ?? ''),
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                            'wrap' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '買家姓名',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $order['buyer_name'] ?? '',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                            'wrap' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '商品名稱',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $order['product_name'] ?? '',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                            'wrap' => true,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '商品數量',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => ($order['quantity'] ?? 1) . ' 個',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '商品類別',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $order['category'] ?? '一般商品',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '總計金額',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => 'NT$ ' . number_format($order['total'] ?? 0),
                                            'size' => 'sm',
                                            'color' => '#007AFF',
                                            'weight' => 'bold',
                                            'flex' => 3,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'box',
                                    'layout' => 'baseline',
                                    'contents' => [
                                        [
                                            'type' => 'text',
                                            'text' => '送貨/收貨方式',
                                            'size' => 'sm',
                                            'color' => '#8E8E93',
                                            'flex' => 2,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $order['shipping_method'] ?? '未設定',
                                            'size' => 'sm',
                                            'color' => '#1C1C1E',
                                            'flex' => 3,
                                            'wrap' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * 發送文字訊息
     */
    public function sendTextMessage(string $userId, string $text): bool
    {
        return $this->pushMessage($userId, [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ]);
    }

    /**
     * Push 訊息
     */
    public function pushMessage(string $userId, array $messages): bool
    {
        // 檢查 LINE 訊息通知是否啟用
        if (class_exists('\BuyGo\Core\Services\SettingsService')) {
            $settings_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\SettingsService::class);
            $line_message_enabled = $settings_service->get('line_message_enabled', true);
            
            if (!$line_message_enabled) {
                error_log('LineMessageService: LINE message notification is disabled, skipping push message');
                return false;
            }
        } else {
            // 回退到舊系統檢查
            $settings = get_option('buygo_core_settings', []);
            $line_message_enabled = isset($settings['line_message_enabled']) ? $settings['line_message_enabled'] : true;
            
            if (!$line_message_enabled) {
                error_log('LineMessageService: LINE message notification is disabled, skipping push message');
                return false;
            }
        }
        
        $accessToken = get_option('mygo_line_channel_access_token', '');
        if (empty($accessToken)) {
            return false;
        }

        $response = wp_remote_post(self::PUSH_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => json_encode([
                'to' => $userId,
                'messages' => $messages,
            ]),
            'timeout' => 10,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Reply 訊息
     */
    public function replyMessage(string $replyToken, array $messages): bool
    {
        // 檢查 LINE 訊息通知是否啟用
        if (class_exists('\BuyGo\Core\Services\SettingsService')) {
            $settings_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\SettingsService::class);
            $line_message_enabled = $settings_service->get('line_message_enabled', true);
            
            if (!$line_message_enabled) {
                error_log('LineMessageService: LINE message notification is disabled, skipping reply message');
                return false;
            }
        } else {
            // 回退到舊系統檢查
            $settings = get_option('buygo_core_settings', []);
            $line_message_enabled = isset($settings['line_message_enabled']) ? $settings['line_message_enabled'] : true;
            
            if (!$line_message_enabled) {
                error_log('LineMessageService: LINE message notification is disabled, skipping reply message');
                return false;
            }
        }
        
        $accessToken = get_option('mygo_line_channel_access_token', '');
        if (empty($accessToken)) {
            return false;
        }

        $response = wp_remote_post(self::REPLY_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => json_encode([
                'replyToken' => $replyToken,
                'messages' => $messages,
            ]),
            'timeout' => 10,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}
