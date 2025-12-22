<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * FluentCommunity Auth Customizer
 * 
 * 客製化 FluentCommunity 的登入/註冊頁面，改為 LINE 登入
 */
class FluentCommunityAuthCustomizer
{
    private LineAuthHandler $authHandler;

    public function __construct()
    {
        $this->authHandler = new LineAuthHandler();
    }

    /**
     * 註冊 Hooks
     */
    public static function register(): void
    {
        $instance = new self();
        
        // 註冊短代碼
        add_shortcode('mygo_line_login', [$instance, 'renderShortcode']);
        
        // 攔截 FluentCommunity 的登入/註冊頁面（可選）
        add_action('template_redirect', [$instance, 'interceptAuthPages'], 1);
        
        // 設定未登入用戶的重導向 URL
        add_filter('fluent_community/portal_redirect_url', [$instance, 'setRedirectUrl'], 10, 2);
        
        // 允許在 FluentCommunity 中編輯 email（針對臨時 email）
        add_filter('fluent_community/user_can_edit_email', [$instance, 'allowEmailEdit'], 10, 2);
    }
    
    /**
     * 允許編輯 email（如果是臨時 email）
     */
    public function allowEmailEdit(bool $canEdit, int $userId): bool
    {
        $user = get_userdata($userId);
        if ($user && strpos($user->user_email, '@temp.line.mygo.local') !== false) {
            return true;
        }
        return $canEdit;
    }
    
    /**
     * 短代碼渲染
     * 使用方式：[mygo_line_login type="register"]
     */
    public function renderShortcode($atts): string
    {
        $atts = shortcode_atts([
            'type' => 'login', // login 或 register
            'style' => 'full', // full 或 button
        ], $atts);
        
        $redirectUri = home_url('/mygo-line-callback/');
        $authUrl = $this->authHandler->getAuthUrl($redirectUri);
        $isRegister = $atts['type'] === 'register';
        
        if ($atts['style'] === 'button') {
            return $this->renderButtonOnly($authUrl, $isRegister);
        }
        
        ob_start();
        $this->renderFullPage($authUrl, $isRegister);
        return ob_get_clean();
    }
    
    /**
     * 只渲染按鈕
     */
    private function renderButtonOnly(string $authUrl, bool $isRegister): string
    {
        // 使用 rawurlencode 確保 URL 參數正確
        $authUrl = html_entity_decode($authUrl);
        
        ob_start();
        ?>
        <div class="mygo-line-login-button" style="text-align: center; padding: 20px;">
            <a href="<?php echo esc_url($authUrl); ?>" class="mygo-line-btn" style="display: inline-flex; align-items: center; justify-content: center; gap: 12px; background: #06C755; color: white; text-decoration: none; padding: 16px 32px; border-radius: 14px; font-size: 17px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3);">
                <span style="width: 24px; height: 24px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #06C755; font-size: 16px;">L</span>
                使用 LINE <?php echo $isRegister ? '註冊' : '登入'; ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 渲染完整頁面
     */
    private function renderFullPage(string $authUrl, bool $isRegister): void
    {
        $validator = new UserProfileValidator();
        $shippingMethods = $validator->getAvailableShippingMethods();
        ?>
        <div class="mygo-line-login-container" style="max-width: 480px; margin: 40px auto; padding: 20px;">
            <div style="background: white; border-radius: 24px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 36px;">🛒</div>
                <h2 style="font-size: 28px; font-weight: 700; margin-bottom: 12px; color: #1a1a1a; text-align: center;">
                    <?php echo $isRegister ? '歡迎加入 BuyGo' : '歡迎回來'; ?>
                </h2>
                <p style="color: #666; margin-bottom: 32px; font-size: 15px; line-height: 1.6; text-align: center;">
                    <?php echo $isRegister 
                        ? '請先填寫基本資料，再使用 LINE 帳號完成註冊' 
                        : '請先填寫基本資料，再使用 LINE 帳號登入'; ?>
                </p>
                
                <!-- 資料表單 -->
                <form id="mygo-pre-login-form" style="margin-bottom: 24px;">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #333;">Email <span style="color: #ff3b30;">*</span></label>
                        <input type="email" name="email" placeholder="your@email.com" required style="width: 100%; padding: 14px 16px; border: 1px solid #c6c6c8; border-radius: 12px; font-size: 16px;">
                        <span class="error-msg" style="display: none; color: #ff3b30; font-size: 13px; margin-top: 6px;"></span>
                        <small style="display: block; color: #666; font-size: 12px; margin-top: 4px;">如果 LINE 未提供 email，將使用此 email 建立帳號</small>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #333;">電話</label>
                        <input type="tel" name="phone" placeholder="09xxxxxxxx" required style="width: 100%; padding: 14px 16px; border: 1px solid #c6c6c8; border-radius: 12px; font-size: 16px;">
                        <span class="error-msg" style="display: none; color: #ff3b30; font-size: 13px; margin-top: 6px;"></span>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #333;">地址</label>
                        <input type="text" name="address" placeholder="請輸入完整地址" required style="width: 100%; padding: 14px 16px; border: 1px solid #c6c6c8; border-radius: 12px; font-size: 16px;">
                        <span class="error-msg" style="display: none; color: #ff3b30; font-size: 13px; margin-top: 6px;"></span>
                    </div>
                    
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #333;">寄送方式</label>
                        <select name="shipping_method" required style="width: 100%; padding: 14px 16px; border: 1px solid #c6c6c8; border-radius: 12px; font-size: 16px;">
                            <option value="">請選擇</option>
                            <?php foreach ($shippingMethods as $method): ?>
                                <option value="<?php echo esc_attr($method); ?>"><?php echo esc_html($method); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="error-msg" style="display: none; color: #ff3b30; font-size: 13px; margin-top: 6px;"></span>
                    </div>
                    
                    <form id="mygo-line-form" method="GET" style="width: 100%;">
                        <button type="submit" id="mygo-continue-to-line" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 12px; background: #06C755; color: white; text-decoration: none; padding: 16px 32px; border-radius: 14px; font-size: 17px; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3); border: none; cursor: pointer;">
                            <span style="width: 24px; height: 24px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #06C755; font-size: 16px;">L</span>
                            繼續使用 LINE <?php echo $isRegister ? '註冊' : '登入'; ?>
                        </button>
                    </form>
                    <script>
                    // 直接解析 URL 字串，避免瀏覽器自動編碼
                    (function() {
                        var authUrl = <?php echo json_encode($authUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                        var form = document.getElementById('mygo-line-form');
                        
                        // 手動分割 URL
                        var parts = authUrl.split('?');
                        if (parts.length === 2) {
                            form.action = parts[0];
                            
                            // 手動解析查詢參數（避免使用 URL 物件）
                            var params = parts[1].split('&');
                            params.forEach(function(param) {
                                var keyValue = param.split('=');
                                if (keyValue.length === 2) {
                                    var input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = decodeURIComponent(keyValue[0]);
                                    input.value = decodeURIComponent(keyValue[1]);
                                    form.appendChild(input);
                                }
                            });
                        }
                        
                        console.log('MYGO: Form action:', form.action);
                        console.log('MYGO: Form inputs:', Array.from(form.querySelectorAll('input[type="hidden"]')).map(i => i.name + '=' + i.value));
                    })();
                    </script>
                </form>
                
                <div style="padding-top: 24px; border-top: 1px solid #e5e5e5;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: #f5f5f7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">⚡</div>
                        <div style="font-size: 13px; color: #666;">快速登入，無需記憶密碼</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="width: 32px; height: 32px; background: #f5f5f7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">🔒</div>
                        <div style="font-size: 13px; color: #666;">安全可靠的 LINE 官方認證</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 32px; height: 32px; background: #f5f5f7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">🎁</div>
                        <div style="font-size: 13px; color: #666;">一鍵下單，輕鬆購物</div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('mygo-pre-login-form');
            const continueBtn = document.getElementById('mygo-continue-to-line');
            
            const lineForm = document.getElementById('mygo-line-form');
            lineForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // 驗證表單
                const email = form.querySelector('[name="email"]').value.trim();
                const phone = form.querySelector('[name="phone"]').value.trim();
                const address = form.querySelector('[name="address"]').value.trim();
                const shipping = form.querySelector('[name="shipping_method"]').value;
                
                let hasError = false;
                
                // 清除之前的錯誤
                form.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');
                
                // 驗證 email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showError(form.querySelector('[name="email"]'), '請輸入有效的 email 地址');
                    hasError = true;
                }
                
                // 驗證電話
                const phoneClean = phone.replace(/[^\d]/g, '');
                if (!/^09\d{8}$/.test(phoneClean)) {
                    showError(form.querySelector('[name="phone"]'), '請輸入有效的手機號碼（09xxxxxxxx）');
                    hasError = true;
                }
                
                // 驗證地址
                if (address.length < 10) {
                    showError(form.querySelector('[name="address"]'), '請輸入完整的地址（至少 10 個字元）');
                    hasError = true;
                }
                
                // 驗證寄送方式
                if (!shipping) {
                    showError(form.querySelector('[name="shipping_method"]'), '請選擇寄送方式');
                    hasError = true;
                }
                
                if (hasError) {
                    return false;
                }
                
                // 儲存資料到 sessionStorage
                sessionStorage.setItem('mygo_pre_login_data', JSON.stringify({
                    email: email,
                    phone: phoneClean,
                    address: address,
                    shipping_method: shipping
                }));
                
                // 儲存原始要訪問的頁面
                const urlParams = new URLSearchParams(window.location.search);
                const redirectTo = urlParams.get('redirect_to') || '<?php echo esc_js(home_url('/portal/')); ?>';
                sessionStorage.setItem('mygo_redirect_after_login', redirectTo);
                
                // 提交 form 導向 LINE 登入
                console.log('MYGO: Submitting form to LINE');
                lineForm.submit();
                return false;
            });
            
            function showError(input, message) {
                const errorEl = input.parentElement.querySelector('.error-msg');
                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.style.display = 'block';
                }
                input.style.borderColor = '#ff3b30';
            }
        });
        </script>
        <?php
    }

    /**
     * 攔截 FluentCommunity 的登入/註冊頁面
     */
    public function interceptAuthPages(): void
    {
        // 檢查是否為 FluentCommunity 的登入/註冊頁面
        $fcomAction = $_GET['fcom_action'] ?? '';
        $form = $_GET['form'] ?? '';
        
        if ($fcomAction === 'auth' && in_array($form, ['login', 'register'])) {
            $this->renderLineLoginPage($form);
            exit;
        }
    }

    /**
     * 設定未登入用戶的重導向 URL
     */
    public function setRedirectUrl(string $url, string $context): string
    {
        // 將未登入用戶導向到 LINE 登入頁面
        return home_url('/mygo-line-login/');
    }

    /**
     * 渲染 LINE 登入頁面（用於攔截 FluentCommunity 預設頁面）
     */
    private function renderLineLoginPage(string $formType): void
    {
        $redirectUri = home_url('/mygo-line-callback/');
        $authUrl = $this->authHandler->getAuthUrl($redirectUri);
        $isRegister = $formType === 'register';
        
        // 儲存原始要訪問的頁面
        if (isset($_GET['redirect_to'])) {
            set_transient('mygo_login_redirect_' . session_id(), $_GET['redirect_to'], 600);
        }
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo $isRegister ? '註冊' : '登入'; ?> - <?php bloginfo('name'); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    padding: 20px; 
                }
                .container { 
                    background: white; 
                    border-radius: 24px; 
                    padding: 48px 40px; 
                    max-width: 420px; 
                    width: 100%; 
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                }
                .logo {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 36px;
                    color: white;
                }
                h1 { 
                    font-size: 28px; 
                    font-weight: 700; 
                    margin-bottom: 12px;
                    color: #1a1a1a;
                }
                .subtitle { 
                    color: #666; 
                    margin-bottom: 40px; 
                    font-size: 15px;
                    line-height: 1.6;
                }
                .line-btn { 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    gap: 12px; 
                    background: #06C755; 
                    color: white; 
                    text-decoration: none; 
                    padding: 16px 32px; 
                    border-radius: 14px; 
                    font-size: 17px; 
                    font-weight: 600;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3);
                }
                .line-btn:hover { 
                    background: #05b34d;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(6, 199, 85, 0.4);
                }
                .line-icon {
                    width: 24px;
                    height: 24px;
                    background: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    color: #06C755;
                    font-size: 16px;
                }
                .features {
                    margin-top: 40px;
                    padding-top: 32px;
                    border-top: 1px solid #e5e5e5;
                }
                .feature-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 16px;
                    text-align: left;
                }
                .feature-icon {
                    width: 40px;
                    height: 40px;
                    background: #f5f5f7;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    flex-shrink: 0;
                }
                .feature-text {
                    font-size: 14px;
                    color: #666;
                }
                .back-link { 
                    display: block; 
                    margin-top: 24px; 
                    color: #667eea; 
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                }
                .back-link:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="logo">🛒</div>
                <h1><?php echo $isRegister ? '歡迎加入 BuyGo' : '歡迎回來'; ?></h1>
                <p class="subtitle">
                    <?php echo $isRegister 
                        ? '使用 LINE 帳號快速註冊，立即開始購物體驗' 
                        : '使用 LINE 帳號登入，繼續您的購物旅程'; ?>
                </p>
                
                <a href="<?php echo esc_url($authUrl); ?>" class="line-btn">
                    <span class="line-icon">L</span>
                    使用 LINE <?php echo $isRegister ? '註冊' : '登入'; ?>
                </a>
                
                <div class="features">
                    <div class="feature-item">
                        <div class="feature-icon">⚡</div>
                        <div class="feature-text">快速登入，無需記憶密碼</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔒</div>
                        <div class="feature-text">安全可靠的 LINE 官方認證</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🎁</div>
                        <div class="feature-text">一鍵下單，輕鬆購物</div>
                    </div>
                </div>
                
                <a href="<?php echo esc_url(home_url()); ?>" class="back-link">← 返回首頁</a>
            </div>
        </body>
        </html>
        <?php
    }

}
