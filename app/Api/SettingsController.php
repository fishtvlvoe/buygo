<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use BuyGo\Core\Services\SettingsService;
use BuyGo\Core\Services\NotificationTemplates;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController extends BaseController {

    /**
     * @var SettingsService
     */
    private $settings;

    public function __construct() {
        $this->settings = App::instance()->make(SettingsService::class);
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // LINE Settings
        register_rest_route($this->namespace, '/settings/line', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_line_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_line_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Spaces API
        register_rest_route($this->namespace, '/spaces', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_spaces'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Fluent Settings
        register_rest_route($this->namespace, '/settings/fluent', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_fluent_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_fluent_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // General Settings
        register_rest_route($this->namespace, '/settings/general', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_general_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_general_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Notification Templates
        // LINE Keywords
        register_rest_route($this->namespace, '/settings/line-keywords', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_line_keywords'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_line_keywords'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/settings/notification-templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_notification_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_notification_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/settings/notification-templates/variables', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_template_variables'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Community Templates (FluentCommunity Post Templates)
        register_rest_route($this->namespace, '/settings/community-templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_community_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_community_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    public function get_settings() {
        return new WP_REST_Response($this->settings->all(), 200);
    }

    public function update_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (empty($params)) {
             return new WP_REST_Response(['message' => 'No data provided'], 400);
        }

        foreach ($params as $key => $value) {
            // Basic sanitization
            $this->settings->set(sanitize_text_field($key), sanitize_text_field($value));
        }

        return new WP_REST_Response(['message' => 'Settings updated', 'settings' => $this->settings->all()], 200);
    }

    public function get_line_settings() {
        try {
            // 使用統一的 SettingsService 讀取（會自動處理新舊系統遷移）
            $settings_service = App::instance()->make(\BuyGo\Core\Services\SettingsService::class);
            
            $data = [
                'channelAccessToken' => $settings_service->get_line_setting('line_channel_access_token', ''),
                'channelSecret' => $settings_service->get_line_setting('line_channel_secret', ''),
                'liffId' => $settings_service->get_line_setting('line_liff_id', ''),
                'loginChannelId' => $settings_service->get_line_setting('line_login_channel_id', ''),
                'loginChannelSecret' => $settings_service->get_line_setting('line_login_channel_secret', ''),
                'lineMessageEnabled' => $settings_service->get('line_message_enabled', true)
            ];
        } catch (\Exception $e) {
            // 如果 BuyGo_Core 不可用，回退到舊系統
        $data = [
            'channelAccessToken' => get_option('mygo_line_channel_access_token', ''),
            'channelSecret' => get_option('mygo_line_channel_secret', ''),
            'liffId' => get_option('mygo_liff_id', ''),
            'loginChannelId' => get_option('mygo_line_login_channel_id', ''),
            'loginChannelSecret' => get_option('mygo_line_login_channel_secret', ''),
            'lineMessageEnabled' => get_option('buygo_core_settings', [])['line_message_enabled'] ?? true
        ];
        }

        return new WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function update_line_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();

        try {
            // 使用統一的 SettingsService 儲存（會同時寫入新舊系統）
            $settings_service = App::instance()->make(\BuyGo\Core\Services\SettingsService::class);
            
            if (isset($params['channelAccessToken'])) {
                $settings_service->set_line_setting('line_channel_access_token', sanitize_text_field($params['channelAccessToken']));
            }
            if (isset($params['channelSecret'])) {
                $settings_service->set_line_setting('line_channel_secret', sanitize_text_field($params['channelSecret']));
            }
            if (isset($params['liffId'])) {
                $settings_service->set_line_setting('line_liff_id', sanitize_text_field($params['liffId']));
            }
            if (isset($params['loginChannelId'])) {
                $settings_service->set_line_setting('line_login_channel_id', sanitize_text_field($params['loginChannelId']));
            }
            if (isset($params['loginChannelSecret'])) {
                $settings_service->set_line_setting('line_login_channel_secret', sanitize_text_field($params['loginChannelSecret']));
            }
            if (isset($params['lineMessageEnabled'])) {
                $settings_service->set('line_message_enabled', (bool) $params['lineMessageEnabled']);
            }
        } catch (\Exception $e) {
            // 如果 BuyGo_Core 不可用，回退到舊系統
        if (isset($params['channelAccessToken'])) {
                update_option('mygo_line_channel_access_token', sanitize_text_field($params['channelAccessToken']), false);
        }
        if (isset($params['channelSecret'])) {
                update_option('mygo_line_channel_secret', sanitize_text_field($params['channelSecret']), false);
        }
        if (isset($params['liffId'])) {
                update_option('mygo_liff_id', sanitize_text_field($params['liffId']), false);
        }
        if (isset($params['loginChannelId'])) {
            update_option('mygo_line_login_channel_id', sanitize_text_field($params['loginChannelId']));
        }
        if (isset($params['loginChannelSecret'])) {
            update_option('mygo_line_login_channel_secret', sanitize_text_field($params['loginChannelSecret']));
        }
        if (isset($params['lineMessageEnabled'])) {
            $settings = get_option('buygo_core_settings', []);
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings['line_message_enabled'] = (bool) $params['lineMessageEnabled'];
            update_option('buygo_core_settings', $settings);
        }
        }

        return new WP_REST_Response(['success' => true, 'message' => '設定已儲存'], 200);
    }

    public function get_fluent_settings() {
        $data = [
            'fluentcartWebhookEnabled' => get_option('buygo_fluentcart_webhook_enabled', false),
            'fluentcartEvents' => get_option('buygo_fluentcart_webhook_events', []),
            'fluentcrmWebhookEnabled' => get_option('buygo_fluentcrm_webhook_enabled', false),
            'fluentcrmEvents' => get_option('buygo_fluentcrm_webhook_events', []),
            'fluentcartRoleMapping' => get_option('buygo_fluentcart_role_mapping', [
                'buygo_admin' => [],
                'buygo_seller' => [],
                'buygo_helper' => []
            ]),
            'fluentcommunityRoleMapping' => get_option('buygo_fluentcommunity_role_mapping', [
                'buygo_admin' => [],
                'buygo_seller' => [],
                'buygo_helper' => []
            ]),
            'defaultSpaceId' => get_option('mygo_default_space_id', ''),
            'loginRedirectUrl' => get_option('mygo_login_redirect_url', home_url())
        ];

        return new WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function update_fluent_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (isset($params['fluentcartWebhookEnabled'])) {
            update_option('buygo_fluentcart_webhook_enabled', (bool) $params['fluentcartWebhookEnabled']);
        }
        if (isset($params['fluentcartEvents'])) {
            update_option('buygo_fluentcart_webhook_events', array_map('sanitize_text_field', (array) $params['fluentcartEvents']));
        }
        if (isset($params['fluentcrmWebhookEnabled'])) {
            update_option('buygo_fluentcrm_webhook_enabled', (bool) $params['fluentcrmWebhookEnabled']);
        }
        if (isset($params['fluentcrmEvents'])) {
            update_option('buygo_fluentcrm_webhook_events', array_map('sanitize_text_field', (array) $params['fluentcrmEvents']));
        }
        if (isset($params['fluentcartRoleMapping'])) {
            $mapping = [];
            foreach ($params['fluentcartRoleMapping'] as $buygo_role => $fc_roles) {
                $mapping[sanitize_key($buygo_role)] = array_map('sanitize_text_field', (array) $fc_roles);
            }
            update_option('buygo_fluentcart_role_mapping', $mapping);
        }
        if (isset($params['fluentcommunityRoleMapping'])) {
            $mapping = [];
            foreach ($params['fluentcommunityRoleMapping'] as $buygo_role => $fc_roles) {
                $mapping[sanitize_key($buygo_role)] = array_map('sanitize_text_field', (array) $fc_roles);
            }
            update_option('buygo_fluentcommunity_role_mapping', $mapping);
        }
        if (isset($params['defaultSpaceId'])) {
            update_option('mygo_default_space_id', sanitize_text_field($params['defaultSpaceId']));
        }
        if (isset($params['loginRedirectUrl'])) {
            update_option('mygo_login_redirect_url', esc_url_raw($params['loginRedirectUrl']));
        }

        return new WP_REST_Response(['success' => true, 'message' => '設定已儲存'], 200);
    }

    public function get_general_settings() {
        $data = [
            'defaultSpaceId' => get_option('mygo_default_space_id', ''),
            'loginRedirectUrl' => get_option('mygo_login_redirect_url', home_url())
        ];

        return new WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function update_general_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (isset($params['defaultSpaceId'])) {
            update_option('mygo_default_space_id', sanitize_text_field($params['defaultSpaceId']));
        }
        if (isset($params['loginRedirectUrl'])) {
            update_option('mygo_login_redirect_url', esc_url_raw($params['loginRedirectUrl']));
        }

        return new WP_REST_Response(['success' => true, 'message' => '設定已儲存'], 200);
    }

    public function get_spaces() {
        $spaces = [];

        // 優先使用 FluentCommunity 的 Utility 方法
        if (class_exists('\FluentCommunity\App\Functions\Utility')) {
            try {
                $spaces_collection = \FluentCommunity\App\Functions\Utility::getSpaces(false);
                
                if ($spaces_collection) {
                    foreach ($spaces_collection as $space) {
                        if (isset($space->status) && $space->status === 'published') {
                            $spaces[] = [
                                'id' => $space->id,
                                'title' => $space->title ?? '',
                                'slug' => $space->slug ?? '',
                                'description' => $space->description ?? ''
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('BuyGo: Error fetching FluentCommunity spaces via Utility: ' . $e->getMessage());
            }
        }

        // Fallback: 直接查詢資料庫
        if (empty($spaces)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fcom_spaces';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $db_spaces = $wpdb->get_results(
                    "SELECT id, title, slug, privacy FROM {$table_name} WHERE type = 'community' AND status = 'published' ORDER BY title ASC",
                    ARRAY_A
                );
                
                foreach ($db_spaces as $space) {
                    $spaces[] = [
                        'id' => (int)$space['id'],
                        'title' => $space['title'] ?? '',
                        'slug' => $space['slug'] ?? '',
                        'description' => ''
                    ];
                }
            }
        }

        return new WP_REST_Response(['success' => true, 'data' => $spaces], 200);
    }

    public function get_notification_templates() {
        $templates = NotificationTemplates::get_all_templates();
        
        // 讀取自訂模板的 metadata（包含自訂模板的 title, description, trigger_condition, order 等）
        $custom_metadata = get_option('buygo_notification_templates_metadata', []);
        
        // 整理模板資訊，包含分類和說明
        $template_meta = self::get_template_metadata();
        $result = [];
        
        foreach ($templates as $key => $template) {
            // 優先使用自訂 metadata，如果沒有則使用系統預設
            $custom_meta = $custom_metadata[$key] ?? null;
            $default_meta = $template_meta[$key] ?? null;
            
            // 合併 metadata：優先使用自訂的，但確保 category 和 recipient 來自預設值（如果自訂沒有）
            $meta = [];
            if ($default_meta) {
                $meta = $default_meta; // 先使用預設值
            }
            if ($custom_meta) {
                // 合併自訂值，但保留預設的 category 和 recipient（除非自訂明確指定）
                $meta = array_merge($meta, $custom_meta);
                // 如果自訂 metadata 沒有 category，使用預設的
                if (empty($custom_meta['category']) && !empty($default_meta['category'])) {
                    $meta['category'] = $default_meta['category'];
                }
                // 如果自訂 metadata 沒有 recipient，使用預設的
                if (empty($custom_meta['recipient']) && !empty($default_meta['recipient'])) {
                    $meta['recipient'] = $default_meta['recipient'];
                }
            }
            
            // 如果完全沒有 metadata，使用預設值
            if (empty($meta)) {
                $meta = ['category' => '系統', 'title' => $key, 'description' => ''];
            }
            
            $line_message = $template['line']['message'] ?? '';
            
            $result[] = [
                'key' => $key,
                'title' => $meta['title'] ?? $key,
                'description' => $meta['description'] ?? '',
                'category' => $meta['category'] ?? '系統',
                'recipient' => $meta['recipient'] ?? 'user',
                'trigger_condition' => $meta['trigger_condition'] ?? '',
                'order' => $meta['order'] ?? 999,
                'email' => [
                    'subject' => $template['email']['subject'] ?? '',
                    'message' => $template['email']['message'] ?? ''
                ],
                'line' => [
                    'message' => $line_message
                ]
            ];
        }
        
        // 按照 order 排序
        usort($result, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return new WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function update_notification_templates(WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        if (empty($params) || !isset($params['templates'])) {
            return new WP_REST_Response(['success' => false, 'message' => '無效的資料'], 400);
        }
        
        // 取得現有的自訂模板和 metadata
        $existing_templates = get_option('buygo_notification_templates', []);
        $existing_metadata = get_option('buygo_notification_templates_metadata', []);
        
        // 取得所有提交的模板 key
        $submitted_keys = [];
        foreach ($params['templates'] as $template) {
            $key = sanitize_key($template['key'] ?? '');
            if (!empty($key)) {
                $submitted_keys[] = $key;
            }
        }
        
        // 移除被刪除的自訂模板（只保留提交的模板）
        foreach ($existing_templates as $key => $value) {
            if (strpos($key, 'custom_') === 0 && !in_array($key, $submitted_keys)) {
                unset($existing_templates[$key]);
                unset($existing_metadata[$key]);
            }
        }
        
        $templates = $existing_templates;
        $metadata = $existing_metadata;
        
        foreach ($params['templates'] as $index => $template) {
            $key = sanitize_key($template['key'] ?? '');
            if (empty($key)) continue;
            
            // 取得原始 LINE 訊息（在 sanitize 之前）
            $raw_line_message = $template['line']['message'] ?? '';
            
            // 使用 sanitize_textarea_field 保留換行符，而不是 sanitize_text_field
            $sanitized_line_message = sanitize_textarea_field($raw_line_message);
            
            // 更新或新增模板內容
            $templates[$key] = [
                'email' => [
                    'subject' => sanitize_text_field($template['email']['subject'] ?? ''),
                    'message' => wp_kses_post($template['email']['message'] ?? '')
                ],
                'line' => [
                    'message' => $sanitized_line_message
                ]
            ];
            
            // 更新或新增 metadata（包含自訂模板的資訊）
            // 如果是自訂模板，儲存完整的 metadata
            if (strpos($key, 'custom_') === 0) {
                $metadata[$key] = [
                    'title' => sanitize_text_field($template['title'] ?? ''),
                    'description' => sanitize_text_field($template['description'] ?? ''),
                    'category' => sanitize_text_field($template['category'] ?? '系統'),
                    'recipient' => sanitize_text_field($template['recipient'] ?? 'user'),
                    'trigger_condition' => sanitize_text_field($template['trigger_condition'] ?? ''),
                    'message_order' => intval($template['message_order'] ?? 1), // 訊息順序
                    'send_interval' => floatval($template['send_interval'] ?? 0.5), // 發送間隔（秒）
                    'order' => intval($template['order'] ?? $index)
                ];
            } else {
                // 系統預設模板儲存 order（必須儲存，用於排序）
                if (!isset($metadata[$key])) {
                    $metadata[$key] = [];
                }
                // 如果有傳入 order，使用傳入的值；否則保持現有的 order
                if (isset($template['order'])) {
                    $metadata[$key]['order'] = intval($template['order']);
                } elseif (!isset($metadata[$key]['order'])) {
                    // 如果沒有 order，使用索引作為預設值
                    $metadata[$key]['order'] = $index;
                }
                // 如果前端傳入了 title 或 description，也可以更新（但 category 和 recipient 保持預設）
                if (isset($template['title'])) {
                    $metadata[$key]['title'] = sanitize_text_field($template['title']);
                }
                if (isset($template['description'])) {
                    $metadata[$key]['description'] = sanitize_text_field($template['description']);
                }
            }
        }
        
        // 儲存模板內容
        NotificationTemplates::save_custom_templates($templates);
        
        // 儲存 metadata
        update_option('buygo_notification_templates_metadata', $metadata);
        
        return new WP_REST_Response(['success' => true, 'message' => '模板已儲存'], 200);
    }

    private static function get_template_metadata() {
        return [
            'admin_new_seller_application' => [
                'category' => '賣家申請',
                'title' => '新賣家申請通知（管理員）',
                'description' => '當有新賣家申請時，發送給管理員的通知',
                'recipient' => 'admin'
            ],
            'seller_application_approved' => [
                'category' => '賣家申請',
                'title' => '賣家申請核准通知',
                'description' => '當賣家申請通過審核時，發送給申請者的通知',
                'recipient' => 'seller'
            ],
            'seller_application_rejected' => [
                'category' => '賣家申請',
                'title' => '賣家申請拒絕通知',
                'description' => '當賣家申請未通過審核時，發送給申請者的通知',
                'recipient' => 'seller'
            ],
            'helper_assigned' => [
                'category' => '小幫手申請',
                'title' => '小幫手指派通知',
                'description' => '當使用者被指派為小幫手時的通知',
                'recipient' => 'helper'
            ],
            'line_binding_success' => [
                'category' => '帳號綁定',
                'title' => 'LINE 帳號綁定成功',
                'description' => '當使用者成功綁定 LINE 帳號時的通知',
                'recipient' => 'user'
            ],
            'order_arrived' => [
                'category' => '訂單通知',
                'title' => '訂單到貨通知（客戶）',
                'description' => '當訂單商品到貨時，發送給客戶的通知',
                'recipient' => 'buyer'
            ],
            'order_paid' => [
                'category' => '訂單通知',
                'title' => '付款確認通知（客戶）',
                'description' => '當賣家確認收到款項時，發送給客戶的通知',
                'recipient' => 'buyer'
            ],
            'order_shipped' => [
                'category' => '訂單通知',
                'title' => '訂單寄出通知（客戶）',
                'description' => '當訂單已寄出時，發送給客戶的通知',
                'recipient' => 'buyer'
            ],
            'order_cancelled' => [
                'category' => '訂單通知',
                'title' => '訂單取消通知（客戶）',
                'description' => '當訂單被取消時，發送給客戶的通知',
                'recipient' => 'buyer'
            ],
            'order_processing' => [
                'category' => '訂單通知',
                'title' => '訂單處理中通知（客戶）',
                'description' => '當訂單開始處理時，發送給客戶的通知',
                'recipient' => 'buyer'
            ],
            'seller_order_created' => [
                'category' => '訂單通知',
                'title' => '新訂單通知（賣家）',
                'description' => '當有新訂單建立時，發送給賣家的通知',
                'recipient' => 'seller'
            ],
            'seller_order_paid' => [
                'category' => '訂單通知',
                'title' => '訂單付款通知（賣家）',
                'description' => '當訂單已付款時，發送給賣家的通知',
                'recipient' => 'seller'
            ],
            'seller_order_cancelled' => [
                'category' => '訂單通知',
                'title' => '訂單取消通知（賣家）',
                'description' => '當訂單被取消時，發送給賣家的通知',
                'recipient' => 'seller'
            ],
            // System Messages
            'system_line_follow' => [
                'category' => '帳號綁定',
                'title' => 'LINE 加入歡迎訊息（系統）',
                'description' => '當用戶加入 LINE 官方帳號時，系統自動發送的歡迎訊息',
                'recipient' => 'system'
            ],
            'system_image_uploaded' => [
                'category' => '系統',
                'title' => '圖片上傳成功訊息（系統）',
                'description' => '當用戶上傳商品圖片成功時，系統自動發送的提示訊息',
                'recipient' => 'system'
            ],
            'system_copy_template' => [
                'category' => '系統',
                'title' => '複製上架訊息（系統）',
                'description' => '當圖片上傳成功後，系統自動發送的複製格式模板，讓用戶可以直接複製使用',
                'recipient' => 'system'
            ],
            'system_account_not_bound' => [
                'category' => '帳號綁定',
                'title' => '帳號未綁定提示（系統）',
                'description' => '當用戶未綁定 LINE 帳號時，系統自動發送的提示訊息',
                'recipient' => 'system'
            ],
            'system_no_permission' => [
                'category' => '系統',
                'title' => '權限不足提示（系統）',
                'description' => '當用戶沒有上傳商品權限時，系統自動發送的提示訊息',
                'recipient' => 'system'
            ],
            'system_image_upload_failed' => [
                'category' => '系統',
                'title' => '圖片上傳失敗提示（系統）',
                'description' => '當圖片上傳失敗時，系統自動發送的錯誤訊息',
                'recipient' => 'system'
            ],
            'system_user_error' => [
                'category' => '系統',
                'title' => '系統錯誤提示（系統）',
                'description' => '當系統無法識別使用者時，系統自動發送的錯誤訊息',
                'recipient' => 'system'
            ],
            'system_product_published' => [
                'category' => '系統',
                'title' => '商品上架成功訊息（系統）',
                'description' => '當商品成功上架時，系統自動發送的成功訊息（包含商品資訊和連結）',
                'recipient' => 'system'
            ],
            'system_product_publish_failed' => [
                'category' => '系統',
                'title' => '商品上架失敗訊息（系統）',
                'description' => '當商品上架失敗時，系統自動發送的錯誤訊息',
                'recipient' => 'system'
            ],
            'system_product_data_incomplete' => [
                'category' => '系統',
                'title' => '商品資料不完整提示（系統）',
                'description' => '當商品資料不完整時，系統自動發送的提示訊息',
                'recipient' => 'system'
            ]
        ];
    }

    /**
     * 取得 LINE 關鍵字列表
     */
    public function get_line_keywords() {
        $keywords = get_option('buygo_line_keywords', []);
        
        // 如果沒有關鍵字，提供預設的 /help 關鍵字
        if (empty($keywords)) {
            $keywords = [
                [
                    'id' => 'help',
                    'keyword' => '/help',
                    'aliases' => ['/幫助', '?help', '幫助'],
                    'message' => "📱 商品上架說明\n\n【步驟】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【必填欄位】\n商品名稱\n價格：350\n數量：20\n\n【選填欄位】\n原價：500\n分類：服飾\n到貨：01/25\n預購：01/20\n描述：商品描述\n\n【範例】\n冬季外套\n價格：1200\n原價：1800\n數量：15\n分類：服飾\n到貨：01/15\n\n💡 輸入 /分類 查看可用分類"
                ]
            ];
        }
        
        return new WP_REST_Response(['success' => true, 'data' => $keywords], 200);
    }

    /**
     * 更新 LINE 關鍵字列表
     */
    public function update_line_keywords(WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        if (!isset($params['keywords']) || !is_array($params['keywords'])) {
            return new WP_REST_Response(['success' => false, 'message' => '無效的資料'], 400);
        }
        
        // 驗證並清理資料
        $keywords = [];
        foreach ($params['keywords'] as $keyword) {
            if (empty($keyword['keyword'])) {
                continue;
            }
            
            $keywords[] = [
                'id' => sanitize_key($keyword['id'] ?? uniqid('kw_')),
                'keyword' => sanitize_text_field($keyword['keyword']),
                'aliases' => array_map('sanitize_text_field', $keyword['aliases'] ?? []),
                'message' => sanitize_textarea_field($keyword['message'] ?? '')
            ];
        }
        
        update_option('buygo_line_keywords', $keywords);
        
        return new WP_REST_Response(['success' => true, 'message' => '關鍵字已儲存'], 200);
    }

    /**
     * 取得模板變數列表
     */
    public function get_template_variables(WP_REST_Request $request) {
        $template_key = $request->get_param('template_key') ?? '';
        
        // 定義所有可用的變數及其說明
        $all_variables = [
            // 通用變數
            'display_name' => [
                'label' => '顯示名稱',
                'description' => '使用者的顯示名稱',
                'example' => '張三'
            ],
            'user_email' => [
                'label' => '使用者 Email',
                'description' => '使用者的電子郵件地址',
                'example' => 'user@example.com'
            ],
            // 訂單相關變數
            'order_id' => [
                'label' => '訂單編號',
                'description' => '訂單的唯一識別碼',
                'example' => '12345'
            ],
            'note' => [
                'label' => '備註',
                'description' => '賣家備註或訂單說明',
                'example' => '請於週一到貨'
            ],
            'buyer_name' => [
                'label' => '買家名稱',
                'description' => '買家的顯示名稱',
                'example' => '李四'
            ],
            'order_total' => [
                'label' => '訂單總額',
                'description' => '訂單的總金額',
                'example' => '1500'
            ],
            'order_url' => [
                'label' => '訂單連結',
                'description' => '訂單的詳細頁面連結',
                'example' => 'https://example.com/order/12345'
            ],
            // 賣家申請相關變數
            'real_name' => [
                'label' => '真實姓名',
                'description' => '申請者的真實姓名',
                'example' => '王五'
            ],
            'phone' => [
                'label' => '聯絡電話',
                'description' => '申請者的聯絡電話',
                'example' => '0912345678'
            ],
            'line_id' => [
                'label' => 'LINE ID',
                'description' => '申請者的 LINE ID',
                'example' => '@example'
            ],
            'submitted_at' => [
                'label' => '申請時間',
                'description' => '申請提交的時間',
                'example' => '2024-12-14 16:00:00'
            ],
            'admin_url' => [
                'label' => '管理後台連結',
                'description' => '管理後台的審核頁面連結',
                'example' => 'https://example.com/wp-admin/...'
            ],
            'review_note' => [
                'label' => '審核備註',
                'description' => '管理員的審核備註',
                'example' => '申請已通過'
            ],
            'review_note_section' => [
                'label' => '審核備註區塊',
                'description' => '包含格式化的審核備註區塊',
                'example' => "\n審核備註：申請已通過\n"
            ],
            // 小幫手相關變數
            'helper_name' => [
                'label' => '小幫手名稱',
                'description' => '小幫手的顯示名稱',
                'example' => '助手一號'
            ],
            'seller_name' => [
                'label' => '賣家名稱',
                'description' => '賣家的顯示名稱',
                'example' => '賣家A'
            ],
            'link' => [
                'label' => '連結',
                'description' => '相關頁面的連結',
                'example' => 'https://example.com/...'
            ],
            // 商品上架相關變數
            'product_name' => [
                'label' => '商品名稱',
                'description' => '商品的名稱',
                'example' => '冬季外套'
            ],
            'price' => [
                'label' => '商品價格',
                'description' => '商品的價格（已格式化）',
                'example' => '1,200'
            ],
            'quantity' => [
                'label' => '商品數量',
                'description' => '商品的庫存數量',
                'example' => '15'
            ],
            'category_section' => [
                'label' => '分類區塊',
                'description' => '商品分類（如果有）',
                'example' => "\n分類：服飾"
            ],
            'original_price_section' => [
                'label' => '原價區塊',
                'description' => '商品原價（如果有）',
                'example' => "\n原價：1800"
            ],
            'arrival_date_section' => [
                'label' => '到貨日期區塊',
                'description' => '商品到貨日期（如果有）',
                'example' => "\n到貨：01/25"
            ],
            'preorder_date_section' => [
                'label' => '預購日期區塊',
                'description' => '商品預購日期（如果有）',
                'example' => "\n預購：01/20"
            ],
            'product_url' => [
                'label' => '商品連結',
                'description' => '商品的直接下單連結',
                'example' => 'https://example.com/item/123'
            ],
            'community_url_section' => [
                'label' => '社群貼文連結區塊',
                'description' => '社群喊單連結（如果有）',
                'example' => "\n\n社群喊單連結：\nhttps://..."
            ],
            'error_message' => [
                'label' => '錯誤訊息',
                'description' => '系統錯誤訊息',
                'example' => '商品建立失敗'
            ],
            'missing_fields' => [
                'label' => '缺少的欄位',
                'description' => '缺少的必填欄位列表',
                'example' => '價格、數量'
            ],
            'currency' => [
                'label' => '幣別代碼',
                'description' => '商品的幣別代碼（JPY, USD, TWD, CNY, HKD）',
                'example' => 'JPY'
            ],
            'currency_symbol' => [
                'label' => '幣別符號',
                'description' => '商品的幣別符號（¥, $, NT$, ¥, HK$）',
                'example' => '¥'
            ],
        ];

        // 根據 template_key 過濾相關的變數
        $template_variables = [];
        
        if (empty($template_key)) {
            // 如果沒有指定 template_key，返回所有變數
            $template_variables = $all_variables;
        } else {
            // 根據模板類型過濾變數
            $template_groups = [
                // 訂單通知（客戶）
                'order_arrived' => ['display_name', 'user_email', 'order_id', 'note'],
                'order_paid' => ['display_name', 'user_email', 'order_id', 'note'],
                'order_shipped' => ['display_name', 'user_email', 'order_id', 'note'],
                'order_cancelled' => ['display_name', 'user_email', 'order_id', 'note'],
                'order_processing' => ['display_name', 'user_email', 'order_id', 'note'],
                // 訂單通知（賣家）
                'seller_order_created' => ['order_id', 'buyer_name', 'order_total', 'order_url'],
                'seller_order_paid' => ['order_id', 'buyer_name', 'order_total', 'order_url'],
                'seller_order_cancelled' => ['order_id', 'buyer_name', 'note', 'order_url'],
                // 賣家申請
                'admin_new_seller_application' => ['display_name', 'user_email', 'real_name', 'phone', 'line_id', 'submitted_at', 'admin_url'],
                'seller_application_approved' => ['display_name', 'review_note_section'],
                'seller_application_rejected' => ['display_name', 'review_note'],
                // 小幫手
                'helper_assigned' => ['helper_name', 'seller_name', 'link'],
                // 系統訊息
                'system_copy_template' => [], // 複製上架訊息通常不需要變數，只是格式範例
                'system_product_published' => ['product_name', 'price', 'quantity', 'currency', 'currency_symbol', 'category_section', 'arrival_date_section', 'preorder_date_section', 'product_url', 'community_url_section', 'original_price_section', 'original_price'],
                'system_product_publish_failed' => ['error_message'],
                'system_product_data_incomplete' => ['missing_fields'],
            ];

            $relevant_vars = $template_groups[$template_key] ?? [];
            
            foreach ($relevant_vars as $var_key) {
                if (isset($all_variables[$var_key])) {
                    $template_variables[$var_key] = $all_variables[$var_key];
                }
            }
            
            // 如果沒有找到特定變數，返回通用變數
            if (empty($template_variables)) {
                $template_variables = [
                    'display_name' => $all_variables['display_name'],
                    'user_email' => $all_variables['user_email'],
                ];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $template_variables
        ], 200);
    }

    /**
     * 取得社群上架模版設定
     */
    public function get_community_templates() {
        $defaults = [
            'single' => "🛒 {商品名稱}\n\n💰 價格：{價格}\n📦 數量：{庫存} 個\n\n👉 留言 +1 即可下單！\n👉 +數量 可購買多個（如 +2）\n📅 到貨：{到貨日}\n\n{描述}",
            'multi' => "🛒 {商品名稱}\n\n📦 商品樣式：\n\n{樣式清單}\n\n👉 留言格式：[代碼]+[數量]\n   例如：A+1 或 B+2\n📅 到貨：{到貨日}\n\n{描述}"
        ];
        
        $templates = get_option('buygo_community_templates', $defaults);
        
        // 確保兩個模版都存在
        if (!isset($templates['single'])) {
            $templates['single'] = $defaults['single'];
        }
        if (!isset($templates['multi'])) {
            $templates['multi'] = $defaults['multi'];
        }
        
        return new WP_REST_Response($templates, 200);
    }

    /**
     * 更新社群上架模版設定
     */
    public function update_community_templates(WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        $templates = [
            'single' => isset($params['single']) ? sanitize_textarea_field($params['single']) : '',
            'multi' => isset($params['multi']) ? sanitize_textarea_field($params['multi']) : ''
        ];
        
        update_option('buygo_community_templates', $templates);
        
        return new WP_REST_Response(['success' => true, 'message' => '模版已儲存'], 200);
    }
}
