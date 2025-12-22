<?php
/**
 * 後台管理類別
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Admin
 */
class BuyGo_Plus_One_Admin {

	/**
	 * 初始化
	 */
	public function init() {
		// 註冊管理選單
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		
		// 顯示啟用訊息
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
	}

	/**
	 * 新增管理選單
	 */
	public function add_admin_menu() {
		// 將 BuyGo Plus One 掛載在 BuyGo Core 之下
		/*
		add_submenu_page(
			'buygo-core',                            // Parent Slug
			__( '喊單設定', 'buygo-plus-one' ),     // 頁面標題
			__( '喊單設定 (Plus One)', 'buygo-plus-one' ), // 選單標題
			'manage_options',                        // 權限
			'buygo-plus-one',                        // 選單 slug
			array( $this, 'render_settings_page' )   // 回調函數
		);
		*/

		// 商品驗證子分頁 (維持在 Plus One 之下? 不, add_submenu_page 第一個參數要是 buygo-plus-one, 但因為 buygo-plus-one 已經是子選單, 所以它不能有子選單)
		// 解決方案: 使用 Tab 頁面切換，而不是 WP 子選單
		// 或者將這些功能也掛在 buys-core 下? 會太亂。
		// 建議: 在 render_settings_page 裡做 Tab 切換 (Settings / Verification / Logs)。
	}

	/**
	 * 渲染設定頁面（主頁面，顯示狀態）
	 */
	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'BuyGo 喊單 (Plus One) 設定', 'buygo-plus-one' ); ?></h1>
			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper">
				<a href="?page=buygo-plus-one&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '一般設定', 'buygo-plus-one' ); ?></a>
				<a href="?page=buygo-plus-one&tab=products" class="nav-tab <?php echo $active_tab == 'products' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '商品驗證', 'buygo-plus-one' ); ?></a>
				<a href="?page=buygo-plus-one&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '系統日誌', 'buygo-plus-one' ); ?></a>
			</nav>

			<div class="tab-content" style="padding-top: 20px;">
				<?php
				switch ( $active_tab ) {
					case 'products':
						$this->render_products_tab_content();
						break;
					case 'logs':
						$this->render_logs_tab_content();
						break;
					case 'settings':
					default:
						$this->render_general_settings_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_general_settings_tab() {
		// 處理儲存
		if ( isset( $_POST['buygo_plus_one_save_settings'] ) && check_admin_referer( 'buygo_plus_one_settings' ) ) {
			$space_id = isset( $_POST['default_space_id'] ) ? intval( $_POST['default_space_id'] ) : 0;
			update_option( 'buygo_plus_one_default_space_id', $space_id );
			echo '<div class="notice notice-success is-dismissible"><p>設定已儲存。</p></div>';
		}

		$default_space_id = get_option( 'buygo_plus_one_default_space_id', 7 ); // Default 7 as per request
		?>
		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e( '社群整合設定', 'buygo-plus-one' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'buygo_plus_one_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="default_space_id"><?php esc_html_e( '預設發布 Space ID', 'buygo-plus-one' ); ?></label></th>
						<td>
							<input name="default_space_id" type="number" id="default_space_id" value="<?php echo esc_attr( $default_space_id ); ?>" class="small-text">
							<p class="description">
								<?php esc_html_e( '當使用者未設定個人 Space 時，LINE 上架商品將預設發布至此 Space。', 'buygo-plus-one' ); ?><br>
								<?php esc_html_e( '請輸入 FluentCommunity 的 Space ID (例如 7)。', 'buygo-plus-one' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="buygo_plus_one_save_settings" id="submit" class="button button-primary" value="<?php esc_attr_e( '儲存變更', 'buygo-plus-one' ); ?>">
				</p>
			</form>
		</div>

		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( '系統狀態', 'buygo-plus-one' ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( '外掛版本', 'buygo-plus-one' ); ?></strong></td>
						<td><?php echo esc_html( BUYGO_PLUS_ONE_VERSION ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Webhook URL', 'buygo-plus-one' ); ?></strong></td>
						<td><code><?php echo esc_url( rest_url( 'buygo-plus-one/v1/webhook' ) ); ?></code></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * 渲染商品驗證標籤頁
	 */
	private function render_products_tab_content() {
		// 取得最近的商品
		$recent_products = get_posts( array(
			'post_type'      => 'fluent-products',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		// 統計資訊
		$total_products = wp_count_posts( 'fluent-products' )->publish;
		$invalid_products = 0;
		$products_without_line_uid = 0;

		foreach ( $recent_products as $product ) {
			$post_author = get_post_field( 'post_author', $product->ID );
			if ( $post_author <= 0 ) {
				$invalid_products++;
			} else {
				$line_uid = $this->get_user_line_uid( $post_author );
				if ( ! $line_uid ) {
					$products_without_line_uid++;
				}
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( '商品驗證', 'buygo-plus-one' ); ?></h1>
			<hr class="wp-header-end">

			<div class="card">
				<h2><?php esc_html_e( '商品驗證狀態', 'buygo-plus-one' ); ?></h2>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
				<div style="padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
					<strong style="font-size: 24px; display: block;"><?php echo esc_html( $total_products ); ?></strong>
					<span>總商品數</span>
				</div>
				<div style="padding: 15px; background: <?php echo $invalid_products > 0 ? '#fcf0f1' : '#f0f9ff'; ?>; border-left: 4px solid <?php echo $invalid_products > 0 ? '#d63638' : '#00a32a'; ?>;">
					<strong style="font-size: 24px; display: block;"><?php echo esc_html( $invalid_products ); ?></strong>
					<span>post_author 無效</span>
				</div>
				<div style="padding: 15px; background: <?php echo $products_without_line_uid > 0 ? '#fff3cd' : '#f0f9ff'; ?>; border-left: 4px solid <?php echo $products_without_line_uid > 0 ? '#dba617' : '#00a32a'; ?>;">
					<strong style="font-size: 24px; display: block;"><?php echo esc_html( $products_without_line_uid ); ?></strong>
					<span>作者無 LINE UID</span>
				</div>
			</div>
		</div>

		<div class="card" style="margin-top: 20px;">
			<h2><?php esc_html_e( '最近商品列表（最多 20 個）', 'buygo-plus-one' ); ?></h2>
			<?php if ( empty( $recent_products ) ) : ?>
				<p><?php esc_html_e( '目前沒有商品。請先透過 LINE Bot 上傳商品。', 'buygo-plus-one' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th>ID</th>
							<th>標題</th>
							<th>作者</th>
							<th>作者 ID</th>
							<th>LINE UID</th>
							<th>狀態</th>
							<th>建立時間</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_products as $product ) : ?>
							<?php
							$post_author = get_post_field( 'post_author', $product->ID );
							$author = $post_author > 0 ? get_userdata( $post_author ) : null;
							$author_name = $author ? $author->display_name : '無效';
							$line_uid = $this->get_user_line_uid( $post_author );
							$status_icon = $post_author > 0 ? '✅' : '❌';
							$status_text = $post_author > 0 ? '正常' : 'post_author 無效';
							?>
							<tr>
								<td><?php echo esc_html( $product->ID ); ?></td>
								<td><strong><?php echo esc_html( $product->post_title ); ?></strong></td>
								<td><?php echo esc_html( $author_name ); ?></td>
								<td><?php echo esc_html( $post_author ); ?></td>
								<td><?php echo $line_uid ? esc_html( $line_uid ) : '<span style="color: #d63638;">無</span>'; ?></td>
								<td><?php echo esc_html( $status_icon . ' ' . $status_text ); ?></td>
								<td><?php echo esc_html( $product->post_date ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="card" style="margin-top: 20px;">
			<h2><?php esc_html_e( '圖片附件驗證', 'buygo-plus-one' ); ?></h2>
			<?php
			$recent_attachments = get_posts( array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );

			$line_images = array();
			foreach ( $recent_attachments as $attachment ) {
				$filename = basename( get_attached_file( $attachment->ID ) );
				if ( strpos( $filename, 'line-product-' ) !== false ) {
					$line_images[] = $attachment;
				}
			}
			?>

			<?php if ( empty( $line_images ) ) : ?>
				<p><?php esc_html_e( '目前沒有 LINE 上傳的圖片。', 'buygo-plus-one' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th>附件 ID</th>
							<th>檔名</th>
							<th>作者</th>
							<th>作者 ID</th>
							<th>狀態</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $line_images as $attachment ) : ?>
							<?php
							$post_author = get_post_field( 'post_author', $attachment->ID );
							$author = $post_author > 0 ? get_userdata( $post_author ) : null;
							$author_name = $author ? $author->display_name : '無效';
							$filename = basename( get_attached_file( $attachment->ID ) );
							$status_icon = $post_author > 0 ? '✅' : '❌';
							$status_text = $post_author > 0 ? '正常' : 'post_author 無效';
							?>
							<tr>
								<td><?php echo esc_html( $attachment->ID ); ?></td>
								<td><?php echo esc_html( $filename ); ?></td>
								<td><?php echo esc_html( $author_name ); ?></td>
								<td><?php echo esc_html( $post_author ); ?></td>
								<td><?php echo esc_html( $status_icon . ' ' . $status_text ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * 渲染日誌標籤頁
	 */
	private function render_logs_tab_content() {
		$logger = BuyGo_Plus_One_Logger::get_instance();
		$log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
		$log_exists = file_exists( $log_file );
		$log_size = $log_exists ? filesize( $log_file ) : 0;
		$lines = isset( $_GET['lines'] ) ? intval( $_GET['lines'] ) : 100;
		$logs = $log_exists ? $logger->get_logs( $lines ) : '';

		// 處理清除日誌
		if ( isset( $_POST['clear_logs'] ) && check_admin_referer( 'buygo_clear_logs' ) ) {
			$logger->clear();
			echo '<div class="notice notice-success is-dismissible"><p>日誌已清除。</p></div>';
			$logs = '';
			$log_size = 0;
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( '日誌', 'buygo-plus-one' ); ?></h1>
			<hr class="wp-header-end">

			<div class="card">
				<h2><?php esc_html_e( '日誌檔案資訊', 'buygo-plus-one' ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong>檔案路徑</strong></td>
						<td><code><?php echo esc_html( $log_file ); ?></code></td>
					</tr>
					<tr>
						<td><strong>檔案狀態</strong></td>
						<td><?php echo $log_exists ? '<span style="color: #00a32a;">✅ 存在</span>' : '<span style="color: #d63638;">❌ 不存在</span>'; ?></td>
					</tr>
					<tr>
						<td><strong>檔案大小</strong></td>
						<td><?php echo esc_html( size_format( $log_size ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="card" style="margin-top: 20px;">
			<h2><?php esc_html_e( '日誌內容', 'buygo-plus-one' ); ?></h2>
			<div style="margin-bottom: 15px;">
				<form method="get" style="display: inline-block;">
					<input type="hidden" name="page" value="buygo-plus-one-logs">
					<label>
						顯示行數：
						<select name="lines" onchange="this.form.submit()">
							<option value="50" <?php selected( $lines, 50 ); ?>>50</option>
							<option value="100" <?php selected( $lines, 100 ); ?>>100</option>
							<option value="200" <?php selected( $lines, 200 ); ?>>200</option>
							<option value="500" <?php selected( $lines, 500 ); ?>>500</option>
						</select>
					</label>
				</form>

				<form method="post" style="display: inline-block; margin-left: 15px;">
					<?php wp_nonce_field( 'buygo_clear_logs' ); ?>
					<input type="hidden" name="clear_logs" value="1">
					<button type="submit" class="button" onclick="return confirm('確定要清除所有日誌嗎？');">清除日誌</button>
				</form>
			</div>

			<?php if ( $log_exists && ! empty( $logs ) ) : ?>
				<textarea readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; padding: 10px; background: #1e1e1e; color: #d4d4d4; border: 1px solid #ccc;"><?php echo esc_textarea( $logs ); ?></textarea>
			<?php elseif ( $log_exists && empty( $logs ) ) : ?>
				<p><?php esc_html_e( '日誌檔案存在但為空。', 'buygo-plus-one' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( '日誌檔案不存在。當有活動時會自動建立。', 'buygo-plus-one' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * 取得使用者的 LINE UID
	 *
	 * @param int $user_id WordPress 使用者 ID
	 * @return string|null LINE UID 或 null
	 */
	private function get_user_line_uid( $user_id ) {
		if ( $user_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$social_table = $wpdb->prefix . 'social_users';

		$line_uid = $wpdb->get_var( $wpdb->prepare(
			"SELECT identifier FROM {$social_table} WHERE type = 'line' AND ID = %d LIMIT 1",
			$user_id
		) );

		return $line_uid;
	}

	/**
	 * 顯示啟用訊息
	 */
	public function activation_notice() {
		if ( get_transient( 'buygo_plus_one_activated' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'BuyGo 喊單 (Plus One) 已成功啟用！', 'buygo-plus-one' ); ?></strong>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: settings page URL */
						esc_html__( '請前往 %s 完成設定。', 'buygo-plus-one' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=buygo-plus-one' ) ) . '">' . esc_html__( '設定頁面', 'buygo-plus-one' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( 'buygo_plus_one_activated' );
		}
	}
}
