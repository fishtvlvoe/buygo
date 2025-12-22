<?php

namespace BuyGo\Core\Frontend;

class SellerApplicationShortcode {

    public function __construct() {
        add_shortcode('buygo_seller_application', [$this, 'render']);
    }

    public function render() {
        if (!is_user_logged_in()) {
            return '<p>請先<a href="' . wp_login_url(get_permalink()) . '">登入</a>後再申請成為賣家。</p>';
        }

        $user = wp_get_current_user();
        if (in_array('buygo_seller', (array) $user->roles) || in_array('buygo_admin', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            return $this->render_already_seller();
        }

        $user_id = $user->ID;

        // Check LINE Binding
        $line_uid = $this->get_line_uid($user_id);
        if (empty($line_uid)) {
            return $this->render_line_required_notice();
        }

        // Check Existing Application
        global $wpdb;
        $table = $wpdb->prefix . 'buygo_seller_applications';
        $application = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id));

        if ($application && $application->status !== 'none' && $application->status !== 'approved') {
            return $this->render_status_page($application);
        }

        return $this->render_form($user, $line_uid);
    }

    private function get_line_uid($user_id) {
        // Try getting from LineService if available
        if (class_exists('\\BuyGo\\Core\\Services\\LineService')) {
            $service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\LineService::class);
            return $service->get_line_uid($user_id);
        }
        
        // Fallback: Check user meta directly
        $possible_keys = ['line_account_id', 'social_id_line', 'nsl_line_id', '_buygo_line_uid'];
        foreach ($possible_keys as $key) {
            $uid = get_user_meta($user_id, $key, true);
            if (!empty($uid)) return $uid;
        }
        return null;
    }

    private function render_line_required_notice() {
        $binding_url = home_url('/account/line-binding');
        return '
            <div class="buygo-page-header" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0;">申請成為賣家</h3>
                <p style="font-size: 14px; color: #6b7280; margin: 0;">請先完成前置要求。</p>
            </div>
            <div class="buygo-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px; text-align: center;">
                <div style="margin-bottom: 20px;">
                    <svg style="width: 64px; height: 64px; margin: 0 auto; display: block; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">請先綁定 LINE 帳號</h3>
                <p style="font-size: 14px; color: #6b7280; margin: 0 0 24px 0;">為了確保您能收到即時的訂單與審核通知，申請成為賣家前必須先完成 LINE 帳號綁定。</p>
                <a href="' . esc_url($binding_url) . '" style="display: inline-flex; background: #06C755; color: #fff; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                    前往綁定 LINE
                </a>
            </div>
        ';
    }

    private function render_already_seller() {
        return '
            <div class="buygo-page-header" style="margin-bottom: 24px;">
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0;">賣家中心</h3>
                <p style="font-size: 14px; color: #6b7280; margin: 0;">歡迎回來，祝您生意興隆。</p>
            </div>
            <div class="buygo-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 40px; text-align: center;">
                <div style="margin-bottom: 20px;">
                    <svg style="width: 64px; height: 64px; margin: 0 auto; display: block; color: #059669;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">您已經是賣家了！</h3>
                <p style="font-size: 14px; color: #6b7280; margin: 0 0 24px 0;">您可以前往賣家中心管理您的商品與訂單。</p>
                <!-- Add Link if needed -->
            </div>
        ';
    }

    private function render_status_page($application) {
        if ($application->status === 'pending') {
            return '
                <div class="buygo-page-header" style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0;">申請成為賣家</h3>
                    <p style="font-size: 14px; color: #6b7280; margin: 0;">查看您的申請進度。</p>
                </div>
                <div class="buygo-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 60px 20px; text-align: center;">
                    <div style="margin-bottom: 24px;">
                        <svg style="width: 64px; height: 64px; margin: 0 auto; display: block; color: #d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">申請審核中</h3>
                    <p style="font-size: 14px; color: #6b7280; margin: 0 0 4px 0;">您的申請已於 ' . date('Y-m-d H:i', strtotime($application->created_at)) . ' 提交。</p>
                    <p style="font-size: 14px; color: #6b7280;">管理員正在審核您的資料，結果將透過 LINE 通知發送給您。</p>
                </div>
            ';
        } else if ($application->status === 'rejected') {
            return '
                <div class="buygo-page-header" style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 4px 0;">申請成為賣家</h3>
                    <p style="font-size: 14px; color: #6b7280; margin: 0;">查看您的申請進度。</p>
                </div>
                <div class="buygo-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 60px 20px; text-align: center;">
                    <div style="margin-bottom: 24px;">
                        <svg style="width: 64px; height: 64px; margin: 0 auto; display: block; color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">申請未通過</h3>
                    <p style="font-size: 14px; color: #6b7280; margin: 0 0 24px 0;">很遺憾，您的申請未通過審核。</p>
                    <p style="font-size: 14px; color: #6b7280;">如有疑問，請聯繫客服。</p>
                </div>
            ';
        }
        return '';
    }

    private function render_form($user, $line_uid) {
        $phone = get_user_meta($user->ID, 'billing_phone', true) ?: '';
        
        ob_start();
        ?>
        <style>
            .buygo-app-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
            .buygo-app-header { background: #f9fafb; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; }
            .buygo-app-header h2 { margin: 0; font-size: 18px; font-weight: 600; color: #111827; }
            .buygo-app-header p { margin: 4px 0 0; font-size: 14px; color: #6b7280; }
            .buygo-app-body { padding: 24px; }
            .buygo-grid { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
            @media (min-width: 768px) { .buygo-grid { grid-template-columns: 1fr 1fr; } }
            .buygo-field { margin-bottom: 0; }
            .buygo-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .buygo-input { display: block; width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 14px; color: #111827; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: border-color 0.15s; box-sizing: border-box; }
            .buygo-input:focus { outline: none; border-color: #3b82f6; ring: 2px solid #3b82f6; }
            .buygo-helper-text { font-size: 12px; color: #6b7280; margin-top: 4px; }
            .buygo-btn-submit { display: inline-flex; justify-content: center; width: auto; background: #111827; color: #fff; border: none; padding: 10px 24px; font-size: 14px; font-weight: 500; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
            .buygo-btn-submit:hover { background: #1f2937; }
            .buygo-btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
            .buygo-form-actions { padding-top: 20px; border-top: 1px solid #f3f4f6; text-align: right; }
        </style>

        <div class="buygo-app-card">
            <div class="buygo-app-header">
                <h2>申請成為賣家</h2>
                <p>請填寫以下資訊，審核通過後即可開始銷售。</p>
            </div>
            
            <div class="buygo-app-body">
                <form id="seller-application-form">
                    <div class="buygo-grid">
                        <div class="buygo-field">
                            <label for="real_name" class="buygo-label">真實姓名 *</label>
                            <input type="text" id="real_name" name="real_name" required value="<?php echo esc_attr($user->display_name); ?>" class="buygo-input">
                        </div>

                        <div class="buygo-field">
                            <label for="phone" class="buygo-label">聯絡電話 *</label>
                            <input type="tel" id="phone" name="phone" required value="<?php echo esc_attr($phone); ?>" class="buygo-input">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="line_id" class="buygo-label">LINE ID (用於搜尋) *</label>
                        <input type="text" id="line_id" name="line_id" required value="" placeholder="請輸入您的 LINE ID (非 UID)" class="buygo-input">
                        <p class="buygo-helper-text">系統已綁定您的 LINE UID (<?php echo substr($line_uid, 0, 8); ?>...)，此處請填寫可被搜尋的 ID。</p>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="product_types" class="buygo-label">預計販售商品類型</label>
                        <textarea id="product_types" name="product_types" rows="3" class="buygo-input"></textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="reason" class="buygo-label">申請理由</label>
                        <textarea id="reason" name="reason" rows="3" class="buygo-input"></textarea>
                    </div>

                    <div class="buygo-form-actions">
                        <button type="submit" id="submit-btn" class="buygo-btn-submit">
                            提交申請
                        </button>
                    </div>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.getElementById('seller-application-form');
                    const submitBtn = document.getElementById('submit-btn');

                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '提交中...';

                        const formData = new FormData(form);
                        const data = {};
                        formData.forEach((value, key) => data[key] = value);

                        fetch('/wp-json/buygo/v1/applications', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                            },
                            body: JSON.stringify(data)
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.message || '發生錯誤，請稍後再試。');
                                submitBtn.disabled = false;
                                submitBtn.innerText = '提交申請';
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('發生網路錯誤，請檢查連線。');
                            submitBtn.disabled = false;
                            submitBtn.innerText = '提交申請';
                        });
                    });
                });
                </script>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
