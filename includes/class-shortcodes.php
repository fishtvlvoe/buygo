<?php
/**
 * 前端 Shortcodes 類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Shortcodes {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 取得單例實例
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函數
     */
    private function __construct() {
        add_shortcode( 'buygo_seller_application_form', array( $this, 'render_application_form' ) );
        add_shortcode( 'buygo_seller_application_status', array( $this, 'render_application_status' ) );
        add_shortcode( 'buygo_seller_helpers', array( $this, 'render_helper_management' ) );
        add_shortcode( 'buygo_line_binding', array( $this, 'render_line_binding' ) );
        add_action( 'init', array( $this, 'process_application_form' ) );
        add_action( 'init', array( $this, 'process_helper_action' ) );
        add_action( 'init', array( $this, 'process_binding_action' ) );
    }

    /**
     * 處理綁定操作
     */
    public function process_binding_action() {
        if ( ! isset( $_POST['buygo_binding_action'] ) || ! wp_verify_nonce( $_POST['buygo_binding_nonce'], 'buygo_line_binding' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $binding_manager = BuyGo_RP_Line_Binding::get_instance();
        
        // 產生綁定碼
        if ( $_POST['buygo_binding_action'] === 'generate' ) {
            $code = $binding_manager->generate_binding_code( $user_id );
            
            if ( is_wp_error( $code ) ) {
                wc_add_notice( $code->get_error_message(), 'error' );
            } else {
                wc_add_notice( '綁定碼已產生，請在 10 分鐘內於 LINE 輸入：' . $code, 'success' );
            }
        }
        
        // 解除綁定
        if ( $_POST['buygo_binding_action'] === 'unbind' ) {
            $result = $binding_manager->unbind_line( $user_id );
            
            if ( is_wp_error( $result ) ) {
                wc_add_notice( $result->get_error_message(), 'error' );
            } else {
                wc_add_notice( '已解除 LINE 綁定', 'success' );
            }
        }
    }

    /**
     * 處理小幫手操作
     */
    public function process_helper_action() {
        if ( ! isset( $_POST['buygo_helper_action'] ) || ! wp_verify_nonce( $_POST['buygo_helper_nonce'], 'buygo_manage_helpers' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $seller_id = get_current_user_id();
        $helper_manager = BuyGo_RP_Helper_Manager::get_instance();
        
        // 新增小幫手
        if ( $_POST['buygo_helper_action'] === 'add' ) {
            $identifier = sanitize_text_field( $_POST['helper_identifier'] );
            $user = get_user_by( 'email', $identifier );
            if ( ! $user ) {
                $user = get_user_by( 'login', $identifier );
            }
            
            if ( ! $user ) {
                wc_add_notice( '找不到該使用者', 'error' );
                return;
            }
            
            if ( $user->ID === $seller_id ) {
                wc_add_notice( '不能將自己設為小幫手', 'error' );
                return;
            }
            
            $permissions = array(
                BUYGO_PERM_VIEW_ORDERS => isset( $_POST['perm_view_orders'] ),
                BUYGO_PERM_UPDATE_ORDERS => isset( $_POST['perm_update_orders'] ),
                BUYGO_PERM_MANAGE_PRODUCTS => isset( $_POST['perm_manage_products'] ),
                BUYGO_PERM_REPLY_CUSTOMERS => isset( $_POST['perm_reply_customers'] ),
            );
            
            $result = $helper_manager->assign_helper( $seller_id, $user->ID, $permissions, $seller_id );
            
            if ( is_wp_error( $result ) ) {
                wc_add_notice( $result->get_error_message(), 'error' );
            } else {
                wc_add_notice( '小幫手已新增', 'success' );
            }
        }
        
        // 移除小幫手
        if ( $_POST['buygo_helper_action'] === 'remove' ) {
            $helper_id = intval( $_POST['helper_id'] );
            $result = $helper_manager->remove_helper( $seller_id, $helper_id );
            
            if ( is_wp_error( $result ) ) {
                wc_add_notice( $result->get_error_message(), 'error' );
            } else {
                wc_add_notice( '小幫手已移除', 'success' );
            }
        }
    }

    /**
     * 處理表單提交
     */
    public function process_application_form() {
        if ( ! isset( $_POST['buygo_seller_application_nonce'] ) || ! wp_verify_nonce( $_POST['buygo_seller_application_nonce'], 'buygo_submit_application' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        
        $data = array(
            'real_name' => isset( $_POST['real_name'] ) ? sanitize_text_field( $_POST['real_name'] ) : '',
            'phone'     => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
            'line_id'   => isset( $_POST['line_id'] ) ? sanitize_text_field( $_POST['line_id'] ) : '',
            'reason'    => isset( $_POST['reason'] ) ? sanitize_textarea_field( $_POST['reason'] ) : '',
            'product_types' => isset( $_POST['product_types'] ) ? sanitize_textarea_field( $_POST['product_types'] ) : '',
        );

        $result = $application_manager->submit_application( $user_id, $data );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
        } else {
            // 如果成功，重定向到狀態頁面或顯示成功訊息
            // 這裡簡單使用 query arg 來顯示成功訊息
            wp_safe_redirect( add_query_arg( 'application_submitted', 'true' ) );
            exit;
        }
    }
    
    /**
     * 渲染申請表單
     */
    public function render_application_form( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>請先<a href="' . wp_login_url( get_permalink() ) . '">登入</a>後再申請。</p>';
        }

        $user_id = get_current_user_id();
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        
        // 檢查是否已經是賣家
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        if ( $role_manager->get_user_role( $user_id ) === BUYGO_ROLE_SELLER ) {
             return '<div class="buygo-message success">您已經是賣家了！</div>';
        }

        // 檢查是否已有待審核申請
        $existing = $application_manager->get_user_application( $user_id, BUYGO_APP_STATUS_PENDING );
        if ( $existing ) {
             return '<div class="buygo-message info">您的申請正在審核中，請耐心等候。 <a href="' . esc_url( home_url( '/account/seller-application/status' ) ) . '">查看狀態</a></div>';
        }
        
        // 檢查是否提交成功
        if ( isset( $_GET['application_submitted'] ) && $_GET['application_submitted'] === 'true' ) {
            return '<div class="buygo-message success">申請提交成功！我們會盡快審核。</div>';
        }

        ob_start();
        ?>
        <div class="buygo-application-form-wrapper">
            <form method="post" class="buygo-form">
                <?php wp_nonce_field( 'buygo_submit_application', 'buygo_seller_application_nonce' ); ?>
                
                <div class="form-group">
                    <label for="real_name">真實姓名 <span class="required">*</span></label>
                    <input type="text" name="real_name" id="real_name" required value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">聯絡電話 <span class="required">*</span></label>
                    <input type="tel" name="phone" id="phone" required placeholder="0912345678">
                </div>
                
                <div class="form-group">
                    <label for="line_id">LINE ID <span class="required">*</span></label>
                    <input type="text" name="line_id" id="line_id" required>
                </div>
                
                <div class="form-group">
                    <label for="product_types">預計銷售商品類型</label>
                    <textarea name="product_types" id="product_types" rows="3" placeholder="例如：韓國服飾、日本零食..."></textarea>
                </div>

                <div class="form-group">
                    <label for="reason">申請原因</label>
                    <textarea name="reason" id="reason" rows="3"></textarea>
                </div>
                
                <div class="form-submit">
                    <button type="submit" class="button button-primary">提交申請</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 渲染申請狀態
     */
    public function render_application_status( $atts ) {
        if ( ! is_user_logged_in() ) {
             return '<p>請先登入。</p>';
        }

        $user_id = get_current_user_id();
        $application_manager = BuyGo_RP_Seller_Application::get_instance();
        $application = $application_manager->get_user_application( $user_id );

        if ( ! $application ) {
            return '<p>您尚未提交任何賣家申請。</p>';
        }

        $status_labels = array(
            BUYGO_APP_STATUS_PENDING   => '待審核',
            BUYGO_APP_STATUS_APPROVED  => '已核准',
            BUYGO_APP_STATUS_REJECTED  => '已拒絕',
            BUYGO_APP_STATUS_CANCELLED => '已取消',
        );
        $status_label = $status_labels[ $application->status ] ?? '未知';
        $status_class = 'status-' . $application->status;

        ob_start();
        ?>
        <div class="buygo-application-status-card">
            <h3>賣家申請狀態</h3>
            
            <div class="status-row">
                <span class="label">申請編號：</span>
                <span class="value">#<?php echo esc_html( $application->id ); ?></span>
            </div>
            
            <div class="status-row">
                <span class="label">提交時間：</span>
                <span class="value"><?php echo date( 'Y-m-d H:i', strtotime( $application->submitted_at ) ); ?></span>
            </div>
            
            <div class="status-row">
                <span class="label">目前狀態：</span>
                <span class="value badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
            </div>
            
            <?php if ( $application->reviewed_at ) : ?>
            <div class="status-row">
                <span class="label">審核時間：</span>
                <span class="value"><?php echo date( 'Y-m-d H:i', strtotime( $application->reviewed_at ) ); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ( $application->review_note ) : ?>
            <div class="status-row note">
                <span class="label">審核備註：</span>
                <div class="value note-content"><?php echo nl2br( esc_html( $application->review_note ) ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * 渲染小幫手管理頁面
     */
    public function render_helper_management( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>請先登入。</p>';
        }

        $seller_id = get_current_user_id();
        $role_manager = BuyGo_RP_Role_Manager::get_instance();
        
        // 只有賣家和管理員可以使用
        $role = $role_manager->get_user_role( $seller_id );
        if ( $role !== BUYGO_ROLE_SELLER && $role !== BUYGO_ROLE_ADMIN ) {
            return '<div class="buygo-message error">只有賣家可以使用此功能。</div>';
        }

        $helper_manager = BuyGo_RP_Helper_Manager::get_instance();
        $helpers = $helper_manager->get_seller_helpers( $seller_id );

        ob_start();
        ?>
        <div class="buygo-helper-management">
            <h3>我的小幫手</h3>
            
            <?php if ( empty( $helpers ) ) : ?>
                <p>目前沒有指派小幫手。</p>
            <?php else : ?>
                <div class="buygo-helper-list">
                    <?php foreach ( $helpers as $helper ) : 
                        $user = get_userdata( $helper->helper_id );
                        if ( ! $user ) continue;
                    ?>
                    <div class="buygo-helper-card">
                        <div class="helper-info">
                            <strong><?php echo esc_html( $user->display_name ); ?></strong>
                            <span class="email"><?php echo esc_html( $user->user_email ); ?></span>
                            <div class="perms">
                                <span class="badge <?php echo $helper->can_view_orders ? 'active' : ''; ?>">查看</span>
                                <span class="badge <?php echo $helper->can_update_orders ? 'active' : ''; ?>">更新</span>
                                <span class="badge <?php echo $helper->can_manage_products ? 'active' : ''; ?>">商品</span>
                                <span class="badge <?php echo $helper->can_reply_customers ? 'active' : ''; ?>">客服</span>
                            </div>
                        </div>
                        <div class="helper-actions">
                            <form method="post" onsubmit="return confirm('確定要移除此小幫手嗎？');">
                                <?php wp_nonce_field( 'buygo_manage_helpers', 'buygo_helper_nonce' ); ?>
                                <input type="hidden" name="buygo_helper_action" value="remove">
                                <input type="hidden" name="helper_id" value="<?php echo $helper->helper_id; ?>">
                                <button type="submit" class="button button-small delete">移除</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="buygo-add-helper-form">
                <h4>新增小幫手</h4>
                <form method="post" class="buygo-form">
                    <?php wp_nonce_field( 'buygo_manage_helpers', 'buygo_helper_nonce' ); ?>
                    <input type="hidden" name="buygo_helper_action" value="add">
                    
                    <div class="form-group">
                        <label for="helper_identifier">使用者 Email 或帳號 <span class="required">*</span></label>
                        <input type="text" name="helper_identifier" id="helper_identifier" required placeholder="例如：helper@example.com">
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>權限設定：</label>
                        <div>
                            <label><input type="checkbox" name="perm_view_orders" value="1" checked> 查看訂單</label>
                            <label><input type="checkbox" name="perm_update_orders" value="1"> 更新訂單</label>
                            <label><input type="checkbox" name="perm_manage_products" value="1"> 管理商品</label>
                            <label><input type="checkbox" name="perm_reply_customers" value="1"> 回覆客戶</label>
                        </div>
                    </div>
                    
                    <div class="form-submit">
                        <button type="submit" class="button button-primary">邀請小幫手</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * 渲染 LINE 綁定頁面
     */
    public function render_line_binding( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>請先登入。</p>';
        }

        $user_id = get_current_user_id();
        $binding_manager = BuyGo_RP_Line_Binding::get_instance();
        $is_bound = $binding_manager->is_line_bound( $user_id );
        $line_uid = $binding_manager->get_user_line_uid( $user_id );

        ob_start();
        ?>
        <div class="buygo-line-binding-card">
            <h3>LINE 帳號綁定</h3>
            
            <?php if ( $is_bound ) : ?>
                <div class="binding-status bound">
                    <span class="icon">✅</span>
                    <div class="info">
                        <h4>已綁定 LINE 帳號</h4>
                        <p>UID: <?php echo esc_html( substr( $line_uid, 0, 8 ) . '...' ); ?></p>
                    </div>
                </div>
                
                <form method="post" onsubmit="return confirm('確定要解除綁定嗎？');">
                    <?php wp_nonce_field( 'buygo_line_binding', 'buygo_binding_nonce' ); ?>
                    <input type="hidden" name="buygo_binding_action" value="unbind">
                    <button type="submit" class="button button-secondary">解除綁定</button>
                </form>
            <?php else : ?>
                <div class="binding-status unbound">
                    <span class="icon">⚠️</span>
                    <div class="info">
                        <h4>尚未綁定 LINE 帳號</h4>
                        <p>綁定後可接收訂單通知與使用更多功能。</p>
                    </div>
                </div>
                
                <div class="binding-instructions">
                    <ol>
                        <li>點擊下方按鈕產生綁定碼</li>
                        <li>加入 BuyGo 官方 LINE 帳號</li>
                        <li>在對話框輸入產生的綁定碼 (6位數字)</li>
                    </ol>
                </div>
                
                <form method="post">
                    <?php wp_nonce_field( 'buygo_line_binding', 'buygo_binding_nonce' ); ?>
                    <input type="hidden" name="buygo_binding_action" value="generate">
                    <button type="submit" class="button button-primary big-button">產生綁定碼</button>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
