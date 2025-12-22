<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\App;
use BuyGo\Core\Services\LineService;
use BuyGo\Core\Services\SellerApplicationService;
use BuyGo\Core\Services\NotificationTemplates;
use BuyGo\Core\Services\SettingsService;

class NotificationService {

    /**
     * @var LineService
     */
    private $line_service;

    public function __construct() {
        // Register Hooks
        add_action('buygo_seller_application_submitted', [$this, 'notify_admin_new_application'], 10, 1);
        add_action('buygo_seller_approved', [$this, 'notify_application_approved'], 10, 2);
        add_action('buygo_seller_rejected', [$this, 'notify_application_rejected'], 10, 2);
        add_action('buygo_helper_assigned', [$this, 'notify_helper_assigned'], 10, 2);
        add_action('buygo_line_binding_completed', [$this, 'notify_line_binding_success'], 10, 2);
    }

    private function get_line_service() {
        if (!$this->line_service) {
            $this->line_service = App::instance()->make(LineService::class);
        }
        return $this->line_service;
    }

    /**
     * Core Send Method
     * 
     * @param int|WP_User $user_or_id  Target User (Applies to both Email and LINE)
     * @param string      $template_key Key defined in NotificationTemplates
     * @param array       $args         Variables for template replacement
     */
    public function send($user_or_id, $template_key, $args = []) {
        $user = is_numeric($user_or_id) ? get_userdata($user_or_id) : $user_or_id;
        
        if (!$user) {
            error_log("[NotificationService] User not found for ID: " . (is_numeric($user_or_id) ? $user_or_id : 'object'));
            return;
        }

        $content = NotificationTemplates::get($template_key, $args);
        if (!$content) {
            error_log("[NotificationService] Template not found: {$template_key}");
            return;
        }

        // 1. Send Email
        if (!empty($content['email']['subject']) && !empty($content['email']['message'])) {
            $sent = wp_mail($user->user_email, $content['email']['subject'], $content['email']['message']);
            $this->log_notification($user->ID, $template_key, 'email', $content['email'], $args, $sent ? 'sent' : 'failed');
        }

        // 2. Send LINE
        if (!empty($content['line'])) {
            // 檢查 LINE 訊息通知是否啟用
            $settings_service = App::instance()->make(SettingsService::class);
            $line_message_enabled = $settings_service->get('line_message_enabled', true);
            
            if (!$line_message_enabled) {
                error_log("[NotificationService] LINE message notification is disabled, skipping message for user: {$user->ID}, template: {$template_key}");
                $this->log_notification($user->ID, $template_key, 'line', $content['line'], $args, 'disabled');
                return;
            }
            
            $line_uid = $this->get_line_service()->get_line_uid($user->ID);
            
            if ($line_uid) {
                // Check if text is not empty
                $msg_text = $content['line']['text'] ?? '';
                if (!empty($msg_text)) {
                    $result = $this->get_line_service()->send_push_message($line_uid, $content['line']);
                    $this->log_notification($user->ID, $template_key, 'line', $content['line'], $args, $result ? 'sent' : 'failed');
                }
            } else {
                error_log("[NotificationService] No LINE UID for user: {$user->ID}");
                $this->log_notification($user->ID, $template_key, 'line', ['error' => 'No LINE UID'], $args, 'failed');
            }
        }
    }

    /**
     * Log Notification to Database
     */
    private function log_notification($user_id, $type, $channel, $content, $args, $status = 'sent') {
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_notification_logs';
        
        $order_id = $args['order_id'] ?? null;
        $title = '';
        $message = '';

        if ($channel === 'email') {
            $title = $content['subject'] ?? '';
            $message = $content['message'] ?? '';
        } else {
            $title = 'LINE Push';
            $message = $content['text'] ?? json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'order_id' => $order_id,
                'type' => $type,
                'channel' => $channel,
                'title' => substr($title, 0, 255),
                'message' => $message,
                'status' => $status,
                'sent_at' => current_time('mysql'),
                'meta' => json_encode($args, JSON_UNESCAPED_UNICODE)
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Notify Admins of New Seller Application
     */
    public function notify_admin_new_application($application_id) {
        // Fetch application details
        $app_service = App::instance()->make(SellerApplicationService::class);
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $application = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $application_id));

        if (!$application) {
            return;
        }

        $user = get_userdata($application->user_id);
        
        $args = [
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'real_name'    => $application->real_name,
            'phone'        => $application->phone,
            'line_id'      => $application->line_id,
            'submitted_at' => $application->submitted_at,
            'admin_url'    => admin_url('admin.php?page=buygo-seller-applications')
        ];

        // Get Admins
        $admins = get_users(['role' => 'administrator']);
        foreach ($admins as $admin) {
            $this->send($admin, 'admin_new_seller_application', $args);
        }
    }

    /**
     * Notify Applicant of Approval
     */
    public function notify_application_approved($user_id, $application) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $review_note_section = !empty($application->review_note) ? "審核備註：{$application->review_note}\n" : '';
        
        $args = [
            'display_name' => $user->display_name,
            'review_note_section' => $review_note_section
        ];

        // $this->send($user, 'seller_application_approved', $args);
        // Notification handed over to FluentCRM Automation (Trigger: buygo_seller_approved)
    }

    /**
     * Notify Applicant of Rejection
     */
    public function notify_application_rejected($user_id, $application) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $args = [
            'display_name' => $user->display_name,
            'review_note'  => $application->review_note ?: '未提供'
        ];

        $this->send($user, 'seller_application_rejected', $args);
    }

    /**
     * Notify Helper of Assignment
     */
    public function notify_helper_assigned($seller_id, $helper_id) {
        $seller = get_userdata($seller_id);
        $helper = get_userdata($helper_id);
        
        if (!$seller || !$helper) return;

        $args = [
            'helper_name' => $helper->display_name,
            'seller_name' => $seller->display_name,
            'link'        => site_url('/account/my-helpers')
        ];

        $this->send($helper, 'helper_assigned', $args);
    }

    /**
     * Notify LINE Binding Success
     */
    public function notify_line_binding_success($user_id, $line_uid) {
        // Technically we have the user_id, so we can send via generic method
        // But for this specific event, we might want to ensure we are sending to THIS line_uid
        // However, generic send() looks up line_uid from DB.
        // Since binding is "completed" before this hook fires (in LineService), 
        // get_line_uid($user_id) should return the correct $line_uid.
        
        $this->send($user_id, 'line_binding_success', []);
    }
}
