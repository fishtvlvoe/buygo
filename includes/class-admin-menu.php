<?php
/**
 * 後台選單類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Admin_Menu {
    
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
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }
    
    /**
     * 註冊選單
     */
    public function register_menu() {
        // 主選單 - 放在 BuyGo 訂單下方（位置 57）
        add_menu_page(
            'BuyGo 角色管理',
            'BuyGo 角色',
            'manage_options',
            'buygo-role-management',
            array( $this, 'render_role_management_page' ),
            'dashicons-groups',
            57
        );
        
        // 子選單：角色管理
        add_submenu_page(
            'buygo-role-management',
            '角色管理',
            '角色管理',
            'manage_options',
            'buygo-role-management',
            array( $this, 'render_role_management_page' )
        );
        
        // 子選單：賣家申請
        add_submenu_page(
            'buygo-role-management',
            '賣家申請',
            '賣家申請',
            'manage_options',
            'buygo-seller-applications',
            array( $this, 'render_seller_applications_page' )
        );
        
        // 子選單：小幫手管理
        add_submenu_page(
            'buygo-role-management',
            '小幫手管理',
            '小幫手管理',
            'manage_options',
            'buygo-helpers',
            array( $this, 'render_helpers_page' )
        );
        
        // 子選單：LINE 綁定
        add_submenu_page(
            'buygo-role-management',
            'LINE 綁定',
            'LINE 綁定',
            'manage_options',
            'buygo-line-binding',
            array( $this, 'render_line_binding_page' )
        );
        
        // 子選單：設定
        add_submenu_page(
            'buygo-role-management',
            '設定',
            '設定',
            'manage_options',
            'buygo-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * 角色管理頁面
     */
    public function render_role_management_page() {
        // 處理批次操作
        if ( isset( $_POST['action'] ) && isset( $_POST['users'] ) ) {
            check_admin_referer( 'bulk-users' );
            
            $action = sanitize_text_field( $_POST['action'] );
            $user_ids = array_map( 'intval', $_POST['users'] );
            
            $role_map = array(
                'set_admin'  => BUYGO_ROLE_ADMIN,
                'set_seller' => BUYGO_ROLE_SELLER,
                'set_helper' => BUYGO_ROLE_HELPER,
                'set_buyer'  => BUYGO_ROLE_BUYER,
            );
            
            if ( isset( $role_map[ $action ] ) ) {
                $role_manager = BuyGo_RP_Role_Manager::get_instance();
                $new_role = $role_map[ $action ];
                
                foreach ( $user_ids as $user_id ) {
                    $role_manager->set_user_role( $user_id, $new_role );
                }
                
                echo '<div class="notice notice-success"><p>已成功變更 ' . count( $user_ids ) . ' 個使用者的角色</p></div>';
            }
        }
        
        // 載入列表表格類別
        require_once BUYGO_RP_PLUGIN_DIR . 'admin/class-role-list-table.php';
        
        // 建立表格實例
        $table = new BuyGo_RP_Role_List_Table();
        $table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">BuyGo 角色管理</h1>
            <hr class="wp-header-end">
            
            <form method="get">
                <input type="hidden" name="page" value="buygo-role-management" />
                <?php
                $table->search_box( '搜尋使用者', 'user' );
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 賣家申請頁面
     */
    public function render_seller_applications_page() {
        // 處理審核操作
        if ( isset( $_POST['action'] ) && isset( $_POST['application_id'] ) ) {
            check_admin_referer( 'buygo_approve_application' );
            
            $application_id = intval( $_POST['application_id'] );
            $action = sanitize_text_field( $_POST['action'] );
            $note = isset( $_POST['review_note'] ) ? sanitize_textarea_field( $_POST['review_note'] ) : '';
            
            $application_manager = BuyGo_RP_Seller_Application::get_instance();
            
            if ( $action === 'approve' ) {
                $result = $application_manager->approve_application( $application_id, get_current_user_id(), $note );
                if ( ! is_wp_error( $result ) ) {
                    echo '<div class="notice notice-success"><p>申請已核准</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                }
            } elseif ( $action === 'reject' ) {
                $result = $application_manager->reject_application( $application_id, get_current_user_id(), $note );
                if ( ! is_wp_error( $result ) ) {
                    echo '<div class="notice notice-success"><p>申請已拒絕</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                }
            }
        }
        
        // 載入列表表格類別
        require_once BUYGO_RP_PLUGIN_DIR . 'admin/class-seller-application-table.php';
        
        // 建立表格實例
        $table = new BuyGo_RP_Seller_Application_Table();
        $table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">賣家申請審核</h1>
            <hr class="wp-header-end">
            
            <form method="get">
                <input type="hidden" name="page" value="buygo-seller-applications" />
                <?php $table->display(); ?>
            </form>
        </div>
        
        <!-- 審核彈窗 -->
        <div id="buygo-review-modal" class="buygo-modal" style="display:none;">
            <div class="buygo-modal-content">
                <span class="buygo-modal-close">&times;</span>
                <h2>審核賣家申請</h2>
                <div id="buygo-review-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 開啟審核彈窗
            $('.buygo-review-application').on('click', function(e) {
                e.preventDefault();
                var appId = $(this).data('app-id');
                
                // AJAX 載入申請詳情
                $.post(ajaxurl, {
                    action: 'buygo_get_application_details',
                    application_id: appId,
                    nonce: '<?php echo wp_create_nonce( 'buygo_get_application' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#buygo-review-content').html(response.data.html);
                        $('#buygo-review-modal').fadeIn();
                    }
                });
            });
            
            // 關閉彈窗
            $('.buygo-modal-close').on('click', function() {
                $('#buygo-review-modal').fadeOut();
            });
        });
        </script>
        <?php
    }
    
    /**
     * 小幫手管理頁面
     */
    public function render_helpers_page() {
        echo '<div class="wrap">';
        echo '<h1>小幫手管理</h1>';
        echo '<p>小幫手管理功能開發中...</p>';
        echo '</div>';
    }
    
    /**
     * LINE 綁定頁面
     */
    public function render_line_binding_page() {
        echo '<div class="wrap">';
        echo '<h1>LINE 綁定狀態</h1>';
        echo '<p>LINE 綁定功能開發中...</p>';
        echo '</div>';
    }
    
    /**
     * 設定頁面
     */
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>BuyGo 角色權限設定</h1>';
        echo '<p>設定功能開發中...</p>';
        echo '</div>';
    }
}
