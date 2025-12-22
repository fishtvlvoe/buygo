<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * User Meta Fields
 * 
 * 在 WordPress 用戶資料中加入自訂欄位
 */
class UserMetaFields
{
    /**
     * 註冊 Hooks
     */
    public static function register(): void
    {
        $instance = new self();
        
        // 加入自訂聯絡方式到 WordPress 用戶資料
        add_filter('user_contactmethods', [$instance, 'addContactMethods']);
        
        // 在用戶個人資料頁面顯示額外欄位
        add_action('show_user_profile', [$instance, 'showExtraProfileFields']);
        add_action('edit_user_profile', [$instance, 'showExtraProfileFields']);
        
        // 儲存額外欄位
        add_action('personal_options_update', [$instance, 'saveExtraProfileFields']);
        add_action('edit_user_profile_update', [$instance, 'saveExtraProfileFields']);
    }
    
    /**
     * 加入自訂聯絡方式
     */
    public function addContactMethods(array $methods): array
    {
        $methods['_mygo_phone'] = '電話';
        $methods['_mygo_address'] = '地址';
        
        return $methods;
    }
    
    /**
     * 顯示額外的個人資料欄位
     */
    public function showExtraProfileFields(\WP_User $user): void
    {
        $validator = new UserProfileValidator();
        $profile = $validator->getUserProfile($user->ID);
        $shippingMethods = $validator->getAvailableShippingMethods();
        
        ?>
        <h2>📱 MYGO 聯絡資訊</h2>
        <table class="form-table">
            <tr>
                <th><label for="_mygo_phone">電話</label></th>
                <td>
                    <input type="tel" 
                           name="_mygo_phone" 
                           id="_mygo_phone" 
                           value="<?php echo esc_attr($profile['phone']); ?>" 
                           class="regular-text" 
                           placeholder="09xxxxxxxx">
                    <p class="description">請輸入您的手機號碼</p>
                </td>
            </tr>
            <tr>
                <th><label for="_mygo_address">地址</label></th>
                <td>
                    <input type="text" 
                           name="_mygo_address" 
                           id="_mygo_address" 
                           value="<?php echo esc_attr($profile['address']); ?>" 
                           class="regular-text" 
                           placeholder="請輸入完整地址">
                    <p class="description">請輸入您的完整地址</p>
                </td>
            </tr>
            <tr>
                <th><label for="_mygo_shipping_method">寄送方式</label></th>
                <td>
                    <select name="_mygo_shipping_method" id="_mygo_shipping_method">
                        <option value="">請選擇配送方式</option>
                        <?php foreach ($shippingMethods as $method): ?>
                            <option value="<?php echo esc_attr($method); ?>" <?php selected($profile['shipping_method'], $method); ?>>
                                <?php echo esc_html($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">請選擇您偏好的配送方式</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * 儲存額外的個人資料欄位
     */
    public function saveExtraProfileFields(int $userId): void
    {
        if (!current_user_can('edit_user', $userId)) {
            return;
        }
        
        $validator = new UserProfileValidator();
        
        $data = [
            'phone' => sanitize_text_field($_POST['_mygo_phone'] ?? ''),
            'address' => sanitize_text_field($_POST['_mygo_address'] ?? ''),
            'shipping_method' => sanitize_text_field($_POST['_mygo_shipping_method'] ?? ''),
        ];
        
        $validation = $validator->validateAndSanitize($data);
        
        if ($validation['valid']) {
            $validator->updateUserProfile($userId, $validation['sanitized']);
        }
    }
}
