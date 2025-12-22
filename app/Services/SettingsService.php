<?php

namespace BuyGo\Core\Services;

class SettingsService {

    private $option_key = 'buygo_core_settings';
    private $encryption_key;
    private $cipher = 'AES-128-ECB'; // Simple encryption for now

    public function __construct() {
        // In production, this key should be in wp-config.php
        $this->encryption_key = defined('BUYGO_ENCRYPTION_KEY') ? BUYGO_ENCRYPTION_KEY : 'buygo-secret-key-default';
    }

    /**
     * Get a setting value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        $settings = get_option($this->option_key, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $value = $settings[$key] ?? $default;

        if ($this->is_encrypted_field($key) && !empty($value)) {
            return $this->decrypt($value);
        }

        return $value;
    }

    /**
     * Set a setting value.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set($key, $value) {
        $settings = get_option($this->option_key, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        if ($this->is_encrypted_field($key) && !empty($value)) {
            $value = $this->encrypt($value);
        }

        $settings[$key] = $value;
        return update_option($this->option_key, $settings);
    }

    /**
     * Get all settings (decrypted).
     *
     * @return array
     */
    public function all() {
        $settings = get_option($this->option_key, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        
        foreach ($settings as $key => $value) {
            if ($this->is_encrypted_field($key) && !empty($value)) {
                $settings[$key] = $this->decrypt($value);
            }
        }
        return $settings;
    }

    private function is_encrypted_field($key) {
        $encrypted_fields = [
            'line_channel_secret',
            'line_channel_access_token',
            'line_login_channel_secret',
            'google_maps_api_key',
            'logistics_api_secret'
        ];
        return in_array($key, $encrypted_fields);
    }

    private function encrypt($data) {
        return openssl_encrypt($data, $this->cipher, $this->encryption_key);
    }

    private function decrypt($data) {
        return openssl_decrypt($data, $this->cipher, $this->encryption_key);
    }

    /**
     * 統一讀取 LINE 設定（支援新舊系統自動遷移）
     * 
     * @param string $key 設定 key（例如 'line_channel_access_token'）
     * @param mixed $default 預設值
     * @return mixed
     */
    public function get_line_setting($key, $default = '') {
        // 先從新系統讀取
        $value = $this->get($key, null);
        
        if ($value !== null && $value !== '') {
            return $value;
        }
        
        // 如果新系統沒有，從舊系統讀取並遷移
        $old_key_map = [
            'line_channel_access_token' => 'mygo_line_channel_access_token',
            'line_channel_secret' => 'mygo_line_channel_secret',
            'line_liff_id' => 'mygo_liff_id',
            'line_login_channel_id' => 'mygo_line_login_channel_id',
            'line_login_channel_secret' => 'mygo_line_login_channel_secret'
        ];
        
        if (isset($old_key_map[$key])) {
            $old_value = get_option($old_key_map[$key], '');
            if (!empty($old_value)) {
                // 自動遷移到新系統
                $this->set($key, $old_value);
                return $old_value;
            }
        }
        
        return $default;
    }

    /**
     * 統一儲存 LINE 設定（同時寫入新舊系統以保持向後相容）
     * 
     * @param string $key 設定 key（例如 'line_channel_access_token'）
     * @param mixed $value 設定值
     * @return bool
     */
    public function set_line_setting($key, $value) {
        // 寫入新系統（主要儲存）
        $result = $this->set($key, $value);
        
        // 同時寫入舊系統以保持向後相容
        $old_key_map = [
            'line_channel_access_token' => 'mygo_line_channel_access_token',
            'line_channel_secret' => 'mygo_line_channel_secret',
            'line_liff_id' => 'mygo_liff_id',
            'line_login_channel_id' => 'mygo_line_login_channel_id',
            'line_login_channel_secret' => 'mygo_line_login_channel_secret'
        ];
        
        if (isset($old_key_map[$key])) {
            update_option($old_key_map[$key], $value, false);
        }
        
        return $result;
    }
}
