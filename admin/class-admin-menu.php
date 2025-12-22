<?php
/**
 * 後台選單管理類別
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BuyGo_RP_Admin_Menu_Final {
    
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
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }
    
    /**
     * 載入資源
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'buygo-' ) === false ) {
            return;
        }

        // Vite Manifest Loader Strategy
        $plugin_dir = BUYGO_RP_PLUGIN_DIR; 
        $manifest_path = $plugin_dir . 'assets/.vite/manifest.json';
        
        if (file_exists($manifest_path)) {
            $manifest = json_decode(file_get_contents($manifest_path), true);
            $entry = $manifest['src/main.js'] ?? null;

            if ($entry) {
                // Enqueue Main JS
                wp_enqueue_script(
                    'buygo-rp-admin',
                    BUYGO_RP_PLUGIN_URL . 'assets/' . $entry['file'],
                    array('jquery'),
                    BUYGO_RP_VERSION,
                    true
                );

                // Enqueue Main CSS
                if (!empty($entry['css'])) {
                    foreach ($entry['css'] as $css_file) {
                        wp_enqueue_style(
                            'buygo-rp-admin-ui',
                            BUYGO_RP_PLUGIN_URL . 'assets/' . $css_file,
                            array(),
                            BUYGO_RP_VERSION
                        );
                    }
                }
            }
        } else {
             // Fallback or Dev mode warning
             // Ideally implement dev server proxy here if needed, but for now just warn
        }
    }
    
    /**
     * 註冊選單
     */
    /**
     * 註冊選單
     */
    public function register_menus() {
        // [Integration] Use 'buygo-core' as parent, defined in App/Admin/AdminMenu.php
        
        /*
        add_menu_page(
            'BuyGo 角色管理',
            'BuyGo 角色',
            'manage_options',
            'buygo-role-management',
            array( $this, 'render_role_management_page' ),
            'dashicons-groups',
            58
        );
        */
        
        // 角色列表 (PHP Legacy View - Optional, can coexist with Vue Members)
        add_submenu_page(
            'buygo-core',
            '角色列表 (PHP)',
            '角色列表 (PHP)',
            'manage_options',
            'buygo-role-management', // Keeping original slug for back-compat
            array( $this, 'render_role_management_page' )
        );
        
        add_submenu_page(
            'buygo-core',
            '賣家申請',
            '賣家申請',
            'manage_options',
            'buygo-seller-applications',
            array( $this, 'render_seller_applications_page' )
        );
        
        // 子選單將由 Vue Router 統一管理，此處僅保留 PHP 進入點 (若有必要) 或讓 Vue 接管
        // 目前維持 buygo-core 作為主要掛載點
        
        // Remove standalone usage guide submenu
    }
    
    /**
     * 渲染頁面標頭 (Update to accept current tab)
     */
    private function render_header( $title, $current_tab = 'roles' ) {
        ?>
        <div class="buygo-header">
            <div>
                <h1><?php echo esc_html( $title ); ?></h1>
            </div>
        </div>
        
        <div class="buygo-tabs">
            <a href="<?php echo admin_url('admin.php?page=buygo-role-management&tab=roles'); ?>" class="buygo-tab-item <?php echo $current_tab === 'roles' ? 'active' : ''; ?>">角色列表</a>
            <a href="<?php echo admin_url('admin.php?page=buygo-role-management&tab=guide'); ?>" class="buygo-tab-item <?php echo $current_tab === 'guide' ? 'active' : ''; ?>">使用說明</a>
        </div>
        <?php
    }

    /**
     * 渲染角色管理頁面
     */
    public function render_role_management_page() {
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'roles';

        if ( $tab === 'guide' ) {
            $this->render_usage_guide_content();
        } else {
            // Localize script for Admin usage
            // Note: This must match the handle used in enqueue_assets ('buygo-rp-admin')
            // And pass 'buygo_admin' object
            $data = [
                 'api_url' => get_rest_url(null, 'buygo/v1'),
                 'nonce' => wp_create_nonce('wp_rest'),
                 'initial_route' => '/members' // Default to members list
            ];
            wp_localize_script('buygo-rp-admin', 'buygo_admin', $data);

            require_once plugin_dir_path( __FILE__ ) . 'class-role-list-table.php';
            $table = new BuyGo_RP_Role_List_Table();
            $table->prepare_items();
            ?>
            <div class="wrap buygo-admin-wrap">
                <?php $this->render_header('角色管理', 'roles'); ?>
                
                <div class="buygo-card">
                    <form method="get">
                        <input type="hidden" name="page" value="buygo-role-management" />
                        <?php $table->search_box( '搜尋使用者', 'user' ); ?>
                        <?php $table->display(); ?>
                    </form>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Helper to render just the content for usage guide
     */
    private function render_usage_guide_content() {
        ?>
        <div class="wrap buygo-admin-wrap">
            <?php $this->render_header('使用說明與短代碼', 'guide'); ?>
            
            <div class="buygo-guide-grid">
                
                <!-- 賣家申請 -->
                <div class="buygo-guide-card">
                    <h3>👥 賣家申請表單</h3>
                    <p>讓使用者申請成為賣家的表單。如果已經是賣家，會顯示提示訊息。</p>
                    <div class="buygo-code-block">
                        <code>[buygo_seller_application_form]</code>
                    </div>
                    <p class="description">建議建立一個頁面「賣家中心」，並貼上此短代碼。</p>
                </div>

                <!-- 小幫手管理 -->
                <div class="buygo-guide-card">
                    <h3>🤝 小幫手管理</h3>
                    <p>讓賣家新增、移除與管理小幫手的權限。只有賣家身份可見。</p>
                    <div class="buygo-code-block">
                        <code>[buygo_seller_helpers]</code>
                    </div>
                </div>

                <!-- LINE 綁定 -->
                <div class="buygo-guide-card">
                    <h3>📱 LINE 帳號綁定</h3>
                    <p>顯示 LINE 綁定狀態，並提供產生綁定碼的功能。</p>
                    <div class="buygo-code-block">
                        <code>[buygo_line_binding]</code>
                    </div>
                </div>

                <!-- 申請狀態 -->
                <div class="buygo-guide-card">
                    <h3>📋 申請狀態查詢</h3>
                    <p>顯示使用者目前的賣家申請進度與審核備註。</p>
                    <div class="buygo-code-block">
                        <code>[buygo_seller_application_status]</code>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * 渲染賣家申請頁面
     */
    public function render_seller_applications_page() {
        require_once plugin_dir_path( __FILE__ ) . 'class-seller-application-table.php';
        
        // 處理操作
        if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) ) {
            $application_id = intval( $_GET['id'] );
            $action = $_GET['action'];
            $nonce = $_REQUEST['_wpnonce'] ?? '';
            
            if ( wp_verify_nonce( $nonce, 'buygo_approve_application' ) ) {
                $app_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\SellerApplicationService::class);
                $admin_id = get_current_user_id();
                
                if ( $action === 'approve' ) {
                    $result = $app_service->approve( $application_id, $admin_id );
                    if (is_wp_error($result)) {
                         echo '<div class="notice notice-error inline"><p>' . $result->get_error_message() . '</p></div>';
                    } else {
                         echo '<div class="notice notice-success inline"><p>申請已核准</p></div>';
                    }
                } elseif ( $action === 'reject' ) {
                    $result = $app_service->reject( $application_id, $admin_id );
                    if (is_wp_error($result)) {
                         echo '<div class="notice notice-error inline"><p>' . $result->get_error_message() . '</p></div>';
                    } else {
                         echo '<div class="notice notice-success inline"><p>申請已拒絕</p></div>';
                    }
                }
            }
        }
    
        $table = new BuyGo_RP_Seller_Application_Table();
        $table->prepare_items();
        ?>
        <div class="wrap buygo-admin-wrap">
            <div class="buygo-header">
                <div><h1>賣家申請審核</h1></div>
            </div>
            
            <div class="buygo-card">
                <form method="get">
                    <input type="hidden" name="page" value="buygo-seller-applications" />
                    <?php $table->search_box( '搜尋', 'search_id' ); ?>
                    <?php $table->display(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    // Vue App handles all routing based on hash or logic

    
    // Remove old render_usage_guide_page completely as logic is moved to render_usage_guide_content
    // But since I'm replacing from line 104, I need to make sure I cover everything or structure it right.
    // The previous file ended at line 283.
    // I need to be careful with the replacement chunk.

}
