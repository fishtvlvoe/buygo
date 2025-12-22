<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\App;
use WP_Error;

class LineService {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'buygo_line_bindings';
        
        // Register cleanup hook
        add_action('buygo_daily_cleanup', [$this, 'cleanup_expired_bindings']);
    }

    /**
     * Generate a binding code for a user.
     *
     * @param int $user_id
     * @return string|WP_Error
     */
    public function generate_binding_code($user_id) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'User not found');
        }

        $code = $this->generate_unique_code();
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'binding_code' => $code,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('db_error', 'Database error');
        }

        return $code;
    }

    /**
     * Verify a binding code and link LINE UID.
     *
     * @param string $code
     * @param string $line_uid
     * @return array|WP_Error
     */
    public function verify_binding_code($code, $line_uid) {
        global $wpdb;

        $binding = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE binding_code = %s ORDER BY id DESC LIMIT 1",
            $code
        ));

        if (!$binding) {
            return new WP_Error('invalid_code', 'Invalid binding code');
        }

        if ($binding->status !== 'pending') {
            return new WP_Error('invalid_status', 'Code already used or expired');
        }

        if (strtotime($binding->expires_at) < time()) {
            $wpdb->update($this->table_name, ['status' => 'expired'], ['id' => $binding->id]);
            return new WP_Error('expired_code', 'Code expired');
        }

        // Complete Binding
        $wpdb->update(
            $this->table_name,
            [
                'line_uid' => $line_uid,
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $binding->id]
        );

        // Fire Event for other services (Notification, CRM Sync)
        do_action('buygo_line_binding_completed', $binding->user_id, $line_uid);

        return [
            'user_id' => $binding->user_id,
            'line_uid' => $line_uid
        ];
    }

    public function get_line_uid($user_id) {
        global $wpdb;
        $local_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT line_uid FROM {$this->table_name} WHERE user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ($local_uid) {
            return $local_uid;
        }

        // Fallback: Check NSL table
        $nsl_table = $wpdb->prefix . 'social_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '$nsl_table'") === $nsl_table) {
            $nsl_uid = $wpdb->get_var($wpdb->prepare(
                "SELECT identifier FROM {$nsl_table} WHERE ID = %d AND type = 'line'",
                $user_id
            ));
            if ($nsl_uid) {
                // Should we auto-sync here? Maybe not, keep it read-only for now or sync on the fly.
                // Let's just return it for display purposes.
                return $nsl_uid;
            }
        }

        return null;
    }

    /**
     * Get User ID by LINE UID.
     *
     * @param string $line_uid
     * @return \WP_User|null
     */
    public function get_user_by_line_uid($line_uid) {
        global $wpdb;

        // 1. Check local bindings first
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name} WHERE line_uid = %s AND status = 'completed' ORDER BY id DESC LIMIT 1",
            $line_uid
        ));

        if ($user_id) {
            return get_userdata($user_id);
        }

        // 2. Check NSL table
        $nsl_table = $wpdb->prefix . 'social_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '$nsl_table'") === $nsl_table) {
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$nsl_table} WHERE identifier = %s AND type = 'line'",
                $line_uid
            ));
            if ($user_id) {
                return get_userdata($user_id);
            }
        }

        return null;
    }

    /**
     * Manually bind a user to a LINE UID (e.g., via NSL).
     *
     * @param int $user_id
     * @param string $line_uid
     * @return bool|WP_Error
     */
    public function manual_bind($user_id, $line_uid) {
        global $wpdb;

        // Check if already bound
        $existing = $this->get_line_uid($user_id);
        if ($existing) {
             if ($existing === $line_uid) {
                 return true;
             }
             // For now, allow overwriting or multiple bindings? 
             // Let's assume we update the latest record or insert new one. 
             // Ideally we should check if THIS line_uid is bound to someone else?
             // But NSL handles unique constraints usually.
        }

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'binding_code' => 'manual-nsl-' . time(), // Dummy code for manual binding
                'line_uid' => $line_uid,
                'status' => 'completed',
                'created_at' => current_time('mysql'),
                'expires_at' => current_time('mysql'), // Expired immediately as it's done
                'completed_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return new WP_Error('db_error', 'Database error duing manual bind');
        }

        do_action('buygo_line_binding_completed', $user_id, $line_uid);

        return true;
    }

    private function generate_unique_code() {
        global $wpdb;
        do {
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE binding_code = %s AND status = 'pending'",
                $code
            ));
        } while ($exists > 0);
        return $code;
    }

    public function cleanup_expired_bindings() {
        global $wpdb;
        $wpdb->query("UPDATE {$this->table_name} SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()");
    }

    /**
     * Send Push Message to LINE User
     * 
     * @param string $to_line_uid
     * @param array|string $messages Can be a single string or array of message objects
     * @return bool|WP_Error
     */
    public function send_push_message($to_line_uid, $messages) {
        // 檢查 LINE 訊息通知是否啟用
        $settings_service = App::instance()->make(SettingsService::class);
        $line_message_enabled = $settings_service->get('line_message_enabled', true);
        
        if (!$line_message_enabled) {
            error_log('BuyGo LineService: LINE message notification is disabled, skipping push message');
            return new WP_Error('disabled', 'LINE message notification is disabled.');
        }
        
        // Use SettingsService to get decrypted token
        $access_token = $settings_service->get('line_channel_access_token', '');

        if (empty($access_token)) {
            error_log('BuyGo LineService: Missing Access Token');
            return new WP_Error('missing_token', 'LINE Channel Access Token is not configured.');
        }

        // Normalize messages to array of objects
        $formatted_messages = [];
        if (is_string($messages)) {
            $formatted_messages[] = ['type' => 'text', 'text' => $messages];
        } elseif (is_array($messages)) {
            // Check if it's a simple array of strings or complex objects
            if (isset($messages['type'])) {
                $formatted_messages[] = $messages; // Single object
            } else {
                foreach ($messages as $msg) {
                    if (is_string($msg)) {
                        $formatted_messages[] = ['type' => 'text', 'text' => $msg];
                    } else {
                        $formatted_messages[] = $msg;
                    }
                }
            }
        }

        $url = 'https://api.line.me/v2/bot/message/push';
        $body = [
            'to' => $to_line_uid,
            'messages' => $formatted_messages
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ],
            'body' => json_encode($body),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            error_log('BuyGo LineService Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            error_log("BuyGo LineService API Error ($code): " . $body);
            return new WP_Error('api_error', 'LINE API Error: ' . $body);
        }

        return true;
    }
}
