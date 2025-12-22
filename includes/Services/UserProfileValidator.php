<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * User Profile Validator
 * 
 * 驗證使用者資料完整性
 */
class UserProfileValidator
{
    /**
     * 下單必要欄位
     */
    private const REQUIRED_FIELDS_FOR_ORDER = ['phone', 'address', 'shipping_method'];
    
    /**
     * 帳號必要欄位（如果使用臨時 email）
     */
    private const REQUIRED_FIELDS_FOR_ACCOUNT = ['email'];

    /**
     * 欄位中文名稱
     */
    private const FIELD_NAMES = [
        'phone' => '電話',
        'address' => '地址',
        'shipping_method' => '寄送方式',
        'line_uid' => 'LINE UID',
        'line_name' => 'LINE 名稱',
        'line_email' => 'Email',
    ];

    /**
     * 可用的寄送方式
     */
    private const SHIPPING_METHODS = ['宅配', '超商取貨', '面交', '郵寄'];

    /**
     * 驗證使用者資料是否完整（可下單）
     *
     * @param int $userId WordPress 使用者 ID
     * @return array ['valid' => bool, 'missing' => array]
     */
    public function validateForOrder(int $userId): array
    {
        $missing = [];

        // 檢查下單必要欄位
        foreach (self::REQUIRED_FIELDS_FOR_ORDER as $field) {
            $value = get_user_meta($userId, '_mygo_' . $field, true);
            if (empty($value)) {
                $missing[] = $field;
            }
        }
        
        // 檢查是否需要補充 email
        $needsEmail = get_user_meta($userId, '_mygo_needs_email', true);
        if ($needsEmail) {
            $user = get_userdata($userId);
            // 如果 email 還是臨時的，就需要補充
            if ($user && strpos($user->user_email, '@temp.line.mygo.local') !== false) {
                $missing[] = 'email';
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * 取得缺失欄位的中文名稱
     *
     * @param array $missing 缺失欄位
     * @return array 中文名稱
     */
    public function getMissingFieldNames(array $missing): array
    {
        return array_map(fn($field) => self::FIELD_NAMES[$field] ?? $field, $missing);
    }

    /**
     * 取得使用者的 MYGO 資料
     *
     * @param int $userId WordPress 使用者 ID
     * @return array 使用者資料
     */
    public function getUserProfile(int $userId): array
    {
        return [
            'line_uid' => get_user_meta($userId, '_mygo_line_uid', true),
            'line_name' => get_user_meta($userId, '_mygo_line_name', true),
            'line_email' => get_user_meta($userId, '_mygo_line_email', true),
            'phone' => get_user_meta($userId, '_mygo_phone', true),
            'address' => get_user_meta($userId, '_mygo_address', true),
            'shipping_method' => get_user_meta($userId, '_mygo_shipping_method', true),
            'role' => get_user_meta($userId, '_mygo_role', true) ?: 'buyer',
        ];
    }

    /**
     * 更新使用者資料
     *
     * @param int $userId WordPress 使用者 ID
     * @param array $data 要更新的資料
     * @return bool 是否成功
     */
    public function updateUserProfile(int $userId, array $data): bool
    {
        $allowedFields = ['phone', 'address', 'shipping_method'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                update_user_meta($userId, '_mygo_' . $field, sanitize_text_field($data[$field]));
            }
        }
        
        // 如果提供了 email，更新 WordPress 使用者的 email
        if (isset($data['email']) && is_email($data['email'])) {
            $user = get_userdata($userId);
            if ($user) {
                // 檢查是否為臨時 email 或空 email
                $isTempEmail = strpos($user->user_email, '@temp.line.mygo.local') !== false;
                $isEmpty = empty($user->user_email);
                
                if ($isTempEmail || $isEmpty) {
                    // 更新 WordPress 使用者的 email
                    wp_update_user([
                        'ID' => $userId,
                        'user_email' => sanitize_email($data['email']),
                    ]);
                    
                    // 同步到 FluentCommunity（如果已安裝）
                    if (function_exists('fluent_community')) {
                        global $wpdb;
                        $table = $wpdb->prefix . 'fcom_users';
                        $wpdb->update(
                            $table,
                            ['user_email' => sanitize_email($data['email'])],
                            ['user_id' => $userId],
                            ['%s'],
                            ['%d']
                        );
                    }
                    
                    // 移除需要補充 email 的標記
                    delete_user_meta($userId, '_mygo_needs_email');
                }
            }
        }

        return true;
    }

    /**
     * 驗證電話格式
     *
     * @param string $phone 電話號碼
     * @return bool 是否有效
     */
    public function validatePhone(string $phone): bool
    {
        // 台灣手機號碼格式：09xxxxxxxx
        $phone = preg_replace('/[^\d]/', '', $phone);
        return preg_match('/^09\d{8}$/', $phone) === 1;
    }

    /**
     * 驗證地址
     *
     * @param string $address 地址
     * @return bool 是否有效
     */
    public function validateAddress(string $address): bool
    {
        // 地址至少要有 10 個字元
        return mb_strlen(trim($address)) >= 10;
    }

    /**
     * 驗證寄送方式
     *
     * @param string $method 寄送方式
     * @return bool 是否有效
     */
    public function validateShippingMethod(string $method): bool
    {
        return in_array($method, self::SHIPPING_METHODS, true);
    }

    /**
     * 取得可用的寄送方式
     *
     * @return array 寄送方式列表
     */
    public function getAvailableShippingMethods(): array
    {
        return self::SHIPPING_METHODS;
    }

    /**
     * 驗證並清理使用者輸入
     *
     * @param array $data 使用者輸入
     * @return array ['valid' => bool, 'errors' => array, 'sanitized' => array]
     */
    public function validateAndSanitize(array $data): array
    {
        $errors = [];
        $sanitized = [];

        // Email
        if (isset($data['email'])) {
            $email = sanitize_email($data['email']);
            if (!is_email($email)) {
                $errors['email'] = '請輸入有效的 email 地址';
            } else {
                $sanitized['email'] = $email;
            }
        }

        // 電話
        if (isset($data['phone'])) {
            $phone = preg_replace('/[^\d]/', '', $data['phone']);
            if (!$this->validatePhone($phone)) {
                $errors['phone'] = '請輸入有效的手機號碼（09xxxxxxxx）';
            } else {
                $sanitized['phone'] = $phone;
            }
        }

        // 地址
        if (isset($data['address'])) {
            $address = sanitize_text_field($data['address']);
            if (!$this->validateAddress($address)) {
                $errors['address'] = '請輸入完整的地址（至少 10 個字元）';
            } else {
                $sanitized['address'] = $address;
            }
        }

        // 寄送方式
        if (isset($data['shipping_method'])) {
            $method = sanitize_text_field($data['shipping_method']);
            if (!$this->validateShippingMethod($method)) {
                $errors['shipping_method'] = '請選擇有效的寄送方式';
            } else {
                $sanitized['shipping_method'] = $method;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * 檢查使用者是否已綁定 LINE
     *
     * @param int $userId WordPress 使用者 ID
     * @return bool 是否已綁定
     */
    public function hasLineBinding(int $userId): bool
    {
        $lineUid = get_user_meta($userId, '_mygo_line_uid', true);
        return !empty($lineUid);
    }

    /**
     * 透過 LINE UID 查找 WordPress 使用者
     *
     * @param string $lineUid LINE UID
     * @return int|null WordPress 使用者 ID
     */
    public function findUserByLineUid(string $lineUid): ?int
    {
        global $wpdb;

        $userId = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_mygo_line_uid' AND meta_value = %s LIMIT 1",
            $lineUid
        ));

        return $userId ? (int) $userId : null;
    }
}
