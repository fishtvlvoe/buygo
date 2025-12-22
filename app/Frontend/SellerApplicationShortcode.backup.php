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
        if (in_array('buygo_seller', (array) $user->roles) || in_array('administrator', (array) $user->roles)) {
            return '<div style="padding: 20px; background: #d1fae5; color: #065f46; border-radius: 8px;">您已經是賣家了！</div>';
        }

        // Auto-fill Data
        $phone = get_user_meta($user->ID, 'billing_phone', true) ?: '';
        // Try getting line UID
        /** @var \BuyGo\Core\Services\LineService */
        $line_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\LineService::class);
        $line_uid = '';
        if ($line_service) {
            $line_uid = $line_service->get_line_uid($user->ID);
        }

        ob_start();
        ?>
        <div id="buygo-seller-app" class="buygo-form-container" style="max-width: 100%;">
            <h2 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; color: #1f2937;">申請成為賣家</h2>
            
            <div id="app-status-message" style="display: none; padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem;"></div>

            <form id="seller-application-form" style="display: block;">
                <div style="margin-bottom: 1rem;">
                    <label for="real_name" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">真實姓名 *</label>
                    <input type="text" id="real_name" name="real_name" required value="<?php echo esc_attr($user->display_name); ?>"
                        style="width: 100%; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; outline: none; transition: border-color 0.15s ease-in-out;"
                        onfocus="this.style.borderColor = '#3b82f6'" onblur="this.style.borderColor = '#d1d5db'">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label for="phone" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">聯絡電話 *</label>
                    <input type="tel" id="phone" name="phone" required value="<?php echo esc_attr($phone); ?>"
                        style="width: 100%; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; outline: none; transition: border-color 0.15s ease-in-out;"
                        onfocus="this.style.borderColor = '#3b82f6'" onblur="this.style.borderColor = '#d1d5db'">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label for="line_id" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">LINE ID *</label>
                    <input type="text" id="line_id" name="line_id" required value="<?php echo esc_attr($line_uid); ?>"
                        style="width: 100%; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; outline: none; transition: border-color 0.15s ease-in-out;"
                        onfocus="this.style.borderColor = '#3b82f6'" onblur="this.style.borderColor = '#d1d5db'">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label for="product_types" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">預計販售商品類型</label>
                    <textarea id="product_types" name="product_types" rows="3"
                        style="width: 100%; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; outline: none; transition: border-color 0.15s ease-in-out;"
                        onfocus="this.style.borderColor = '#3b82f6'" onblur="this.style.borderColor = '#d1d5db'"></textarea>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="reason" style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">申請理由</label>
                    <textarea id="reason" name="reason" rows="3"
                        style="width: 100%; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; border-radius: 0.375rem; outline: none; transition: border-color 0.15s ease-in-out;"
                        onfocus="this.style.borderColor = '#3b82f6'" onblur="this.style.borderColor = '#d1d5db'"></textarea>
                </div>

                <button type="submit" id="submit-btn"
                    style="width: 100%; background: linear-gradient(to right, #2563eb, #1d4ed8); color: white; padding: 0.75rem 1rem; border-radius: 0.375rem; border: none; font-weight: 500; cursor: pointer; transition: opacity 0.2s;">
                    提交申請
                </button>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('seller-application-form');
            const statusMsg = document.getElementById('app-status-message');
            const submitBtn = document.getElementById('submit-btn');

            // Check status first
            fetch('/wp-json/buygo/v1/applications/me', {
                headers: {
                    'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status && data.status !== 'none') {
                    form.style.display = 'none';
                    statusMsg.style.display = 'block';
                    if (data.status === 'pending') {
                        statusMsg.className = 'bg-yellow-50 text-yellow-800 border border-yellow-200';
                        statusMsg.style.backgroundColor = '#fefce8';
                        statusMsg.style.color = '#854d0e';
                        statusMsg.style.border = '1px solid #fef08a';
                        statusMsg.innerHTML = '<strong style="display:block;margin-bottom:0.5rem">申請審核中</strong>您的申請已於 ' + data.submitted_at + ' 提交，目前正在審核中，請耐心等候。';
                    } else if (data.status === 'rejected') {
                        statusMsg.style.backgroundColor = '#fee2e2';
                        statusMsg.style.color = '#991b1b';
                        statusMsg.style.border = '1px solid #fecaca';
                        statusMsg.innerHTML = '<strong style="display:block;margin-bottom:0.5rem">申請未通過</strong>很遺憾，您的申請未通過審核。<br>原因：' + (data.review_note || '未提供');
                    }
                }
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.7';
                submitBtn.innerText = '提交中...';

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
                        form.style.display = 'none';
                        statusMsg.style.display = 'block';
                        statusMsg.style.backgroundColor = '#d1fae5';
                        statusMsg.style.color = '#065f46';
                        statusMsg.style.border = '1px solid #a7f3d0';
                        statusMsg.innerHTML = '<strong style="display:block;margin-bottom:0.5rem">提交成功！</strong>' + response.message;
                    } else {
                        alert(response.message || '發生錯誤，請稍後再試。');
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.innerText = '提交申請';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('發生網路錯誤，請檢查連線。');
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.innerText = '提交申請';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
