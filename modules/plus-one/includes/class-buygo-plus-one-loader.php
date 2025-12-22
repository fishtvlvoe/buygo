<?php
/**
 * 核心載入器類別
 *
 * 負責載入所有依賴、初始化服務、註冊 Hooks
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Loader
 */
class BuyGo_Plus_One_Loader {

	/**
	 * 建構函數
	 */
	public function __construct() {
		$this->load_dependencies();
	}

	/**
	 * 載入所有依賴檔案
	 */
	private function load_dependencies() {
		// 載入自動載入器
		require_once BUYGO_PLUS_ONE_PATH . 'includes/class-autoloader.php';
		
		// 初始化自動載入
		BuyGo_Plus_One_Autoloader::init();
	}

	/**
	 * 執行外掛
	 */
	public function run() {
		// 初始化服務
		$this->init_services();
		
		// 註冊 REST API 路由
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}
	
	/**
	 * 註冊 REST API 路由
	 */
	public function register_rest_routes() {
		$logger = BuyGo_Plus_One_Logger::get_instance();
		$logger->info( 'Registering REST API routes' );
		
		// 註冊 LINE Webhook
		$webhook_handler = new BuyGo_Plus_One_Webhook_Handler();
		$webhook_handler->register_routes();
		
		// 註冊 FluentCart Webhook
		$fluentcart_webhook = new BuyGo_Plus_One_FluentCart_Webhook();
		$fluentcart_webhook->register_routes();

		// 註冊設定 API
		$settings_controller = new BuyGo_Plus_One_Settings_Controller();
		$settings_controller->register_routes();
		
		$logger->info( 'REST API routes registered' );
	}

	/**
	 * 檢查依賴外掛
	 */
	public function check_dependencies() {
		$missing_plugins = array();

		// 檢查 BuyGo Core
		if ( ! class_exists( 'BuyGo_Core' ) ) {
			$missing_plugins[] = 'BuyGo Role Permission (Central Core)';
		}

		// 檢查 FluentCart
		if ( ! class_exists( 'FluentCart\App\App' ) ) {
			$missing_plugins[] = 'FluentCart';
		}

		// 檢查 FluentCommunity
		if ( ! defined( 'FLUENT_COMMUNITY_VERSION' ) && ! class_exists( 'FluentCommunity\App' ) ) {
			$missing_plugins[] = 'FluentCommunity';
		}

		// 檢查 Nextend Social Login（警告但不強制）
		if ( ! class_exists( 'NextendSocialLogin' ) ) {
			add_action( 'admin_notices', array( $this, 'nextend_warning_notice' ) );
		}

		// 如果缺少必要外掛，停用並顯示錯誤
		if ( ! empty( $missing_plugins ) ) {
			$this->missing_plugins_list = $missing_plugins;
			add_action( 'admin_notices', array( $this, 'missing_dependencies_notice' ) );
			deactivate_plugins( BUYGO_PLUS_ONE_BASENAME );
			
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}
	}

	/**
	 * 檢查依賴是否滿足
	 *
	 * @return bool
	 */
	private function dependencies_met() {
		return class_exists( 'BuyGo_Core' ) && class_exists( 'FluentCart\App\App' ) && ( defined( 'FLUENT_COMMUNITY_VERSION' ) || class_exists( 'FluentCommunity\App' ) );
	}

	/**
	 * 顯示缺少依賴的錯誤訊息
	 */
	public function missing_dependencies_notice() {
		$missing_list = isset( $this->missing_plugins_list ) ? implode( ', ', $this->missing_plugins_list ) : '相關外掛';
		?>
		<div class="notice notice-error">
			<p>
				<strong>BuyGo 喊單 (BuyGo Plus One)</strong> 需要安裝並啟用 <strong><?php echo esc_html( $missing_list ); ?></strong> 才能運作。
			</p>
			<p>
				請先安裝並啟用上述外掛 (FluentCommunity 支援免費版)，然後再啟用 BuyGo 喊單外掛。
			</p>
		</div>
		<?php
	}

	/**
	 * 顯示 Nextend Social Login 警告訊息
	 */
	public function nextend_warning_notice() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong>BuyGo 喊單 (BuyGo Plus One)</strong> 建議安裝 <strong>Nextend Social Login</strong> 外掛以支援 LINE Login 功能。
			</p>
			<p>
				雖然外掛可以運作，但使用者將無法透過 LINE 登入。
			</p>
		</div>
		<?php
	}

	/**
	 * 載入文字域
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'buygo-plus-one',
			false,
			dirname( BUYGO_PLUS_ONE_BASENAME ) . '/languages'
		);
	}

	/**
	 * 初始化所有服務
	 */
	public function init_services() {
		// 初始化日誌系統.
		$logger = BuyGo_Plus_One_Logger::get_instance();
		$logger->info( 'BuyGo Plus One init_services called' );

		// 初始化角色管理器.
		$role_manager = new BuyGo_Plus_One_Role_Manager();
		$role_manager->init();
		$logger->info( 'Role Manager initialized' );

		// 初始化訂單通知.
		$order_notification = new BuyGo_Plus_One_Order_Notification();
		$order_notification->init();
		$logger->info( 'Order Notification initialized' );

		// 初始化產品管理工具（僅後台）.
		if ( is_admin() ) {
			$product_manager = new BuyGo_Plus_One_Product_Manager();
			$product_manager->init();
			$logger->info( 'Product Manager initialized' );
			
			// 初始化產品作者修正工具.
			add_action( 'admin_menu', array( $this, 'add_fix_authors_menu' ) );
		}

		// 初始化社群處理器.
		$community_handler = new BuyGo_Plus_One_Community_Handler();
		$community_handler->init();
		$logger->info( 'Community Handler initialized' );

		// 初始化前台功能（僅前台）
		if ( ! is_admin() ) {
			$frontend = new BuyGo_Plus_One_Frontend();
			$frontend->init();
			$logger->info( 'Frontend initialized' );

			// 初始化價格標籤處理器
			$price_label_handler = new BuyGo_Plus_One_Price_Label_Handler();
			$price_label_handler->init();
			$logger->info( 'Price Label Handler initialized' );

			// 初始化款式數量限制顯示處理器
			$variation_quantity_handler = new BuyGo_Plus_One_Variation_Quantity_Display_Handler();
			$variation_quantity_handler->init();
			$logger->info( 'Variation Quantity Display Handler initialized' );
		}

		$logger->info( 'Services initialized' );

		// 初始化後台管理介面
		if ( is_admin() ) {
			$admin = new BuyGo_Plus_One_Admin();
			$admin->init();
			$logger->info( 'Admin interface initialized' );
			
			// 初始化產品頻道 Meta Box
			$channel_meta_box = new BuyGo_Plus_One_Product_Channel_Meta_Box();
			$channel_meta_box->init();
			$logger->info( 'Product Channel Meta Box initialized' );
		}
	}
	
	/**
	 * 新增修正商品作者的選單
	 */
	public function add_fix_authors_menu() {
		add_submenu_page(
			'tools.php',
			'修正商品作者',
			'修正商品作者',
			'manage_options',
			'buygo-fix-authors',
			array( $this, 'render_fix_authors_page' )
		);
	}
	
	/**
	 * 渲染修正商品作者頁面
	 */
	public function render_fix_authors_page() {
		// 處理表單提交.
		if ( isset( $_POST['fix_authors'] ) && check_admin_referer( 'buygo_fix_authors' ) ) {
			$user_id = intval( $_POST['user_id'] );
			
			if ( $user_id > 0 && get_userdata( $user_id ) ) {
				$this->fix_product_authors( $user_id );
			} else {
				echo '<div class="notice notice-error"><p>無效的使用者 ID</p></div>';
			}
		}
		
		// 取得當前使用者.
		$current_user = wp_get_current_user();
		
		// 查詢需要修正的商品數量.
		$args = array(
			'post_type'      => 'fluent-products',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'author'         => 0,
			'fields'         => 'ids',
		);
		$product_ids = get_posts( $args );
		$count       = count( $product_ids );
		
		?>
		<div class="wrap">
			<h1>修正商品作者</h1>
			<p>將所有 post_author = 0 的商品設定為指定的使用者。</p>
			
			<?php if ( $count > 0 ) : ?>
				<div class="notice notice-warning">
					<p><strong>找到 <?php echo $count; ?> 個需要修正的商品</strong></p>
				</div>
				
				<form method="post" action="">
					<?php wp_nonce_field( 'buygo_fix_authors' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="user_id">設定為使用者</label>
							</th>
							<td>
								<input type="number" name="user_id" id="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>" class="regular-text" required>
								<p class="description">
									當前使用者：<?php echo esc_html( $current_user->display_name ); ?> (ID: <?php echo $current_user->ID; ?>)
								</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="fix_authors" class="button button-primary" value="開始修正">
					</p>
				</form>
			<?php else : ?>
				<div class="notice notice-success">
					<p>沒有需要修正的商品。</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * 修正商品作者
	 *
	 * @param int $user_id 使用者 ID.
	 */
	private function fix_product_authors( $user_id ) {
		$args = array(
			'post_type'      => 'fluent-products',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'author'         => 0,
			'fields'         => 'ids',
		);
		
		$product_ids = get_posts( $args );
		
		if ( empty( $product_ids ) ) {
			echo '<div class="notice notice-info"><p>沒有找到需要修正的商品。</p></div>';
			return;
		}
		
		$updated_count = 0;
		$failed_count  = 0;
		
		foreach ( $product_ids as $product_id ) {
			$result = wp_update_post(
				array(
					'ID'          => $product_id,
					'post_author' => $user_id,
				),
				true
			);
			
			if ( is_wp_error( $result ) ) {
				$failed_count++;
			} else {
				$updated_count++;
			}
		}
		
		echo '<div class="notice notice-success"><p>';
		echo '成功更新 ' . $updated_count . ' 個商品';
		if ( $failed_count > 0 ) {
			echo '，失敗 ' . $failed_count . ' 個';
		}
		echo '</p></div>';
	}
}
