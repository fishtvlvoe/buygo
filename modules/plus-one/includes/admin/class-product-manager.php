<?php
/**
 * 產品管理工具
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Product_Manager
 */
class BuyGo_Plus_One_Product_Manager {

	/**
	 * 初始化
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_buygo_delete_all_products', array( $this, 'handle_delete_all_products' ) );
		add_action( 'admin_post_buygo_delete_all_orders', array( $this, 'handle_delete_all_orders' ) );
	}

	/**
	 * 新增管理選單
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			'BuyGo 產品管理',
			'BuyGo 產品管理',
			'manage_options',
			'buygo-product-manager',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * 渲染管理頁面
	 */
	public function render_admin_page() {
		// 偵測 FluentCart 產品的 post type.
		global $wpdb;
		$post_types = $wpdb->get_col(
			"SELECT DISTINCT post_type FROM {$wpdb->posts} 
			WHERE post_type LIKE '%product%' OR post_type LIKE '%fc%' 
			ORDER BY post_type"
		);

		// 嘗試常見的 post type.
		$possible_types = array( 'fluent-products', 'fc_product', 'fluentcart_product', 'product', 'fluent_product' );
		$product_type   = '';
		$total          = 0;

		foreach ( $possible_types as $type ) {
			$count = wp_count_posts( $type );
			if ( $count ) {
				$type_total = $count->publish + $count->draft + $count->pending + $count->private;
				if ( $type_total > 0 ) {
					$product_type = $type;
					$total        = $type_total;
					break;
				}
			}
		}

		// 取得訂單數量.
		global $wpdb;
		$orders_table = $wpdb->prefix . 'fct_orders';
		$orders_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table}" );
		if ( is_null( $orders_count ) ) {
			$orders_count = 0;
		}
		?>

		<div class="wrap">
			<h1>BuyGo 資料管理</h1>

			<?php if ( isset( $_GET['deleted_orders'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ 已成功刪除 <?php echo esc_html( $_GET['deleted_orders'] ); ?> 筆訂單資料。</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $post_types ) ) : ?>
				<div class="notice notice-info">
					<p><strong>偵測到的 Post Types：</strong> <?php echo esc_html( implode( ', ', $post_types ) ); ?></p>
					<?php if ( $product_type ) : ?>
						<p><strong>使用的產品類型：</strong> <?php echo esc_html( $product_type ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2>🗑️ 批量刪除訂單</h2>
				<p>目前有 <strong><?php echo esc_html( $orders_count ); ?></strong> 筆訂單。</p>

				<?php if ( $orders_count > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('確定要刪除所有 <?php echo esc_js( $orders_count ); ?> 筆訂單嗎？此操作無法復原！');">
						<?php wp_nonce_field( 'buygo_delete_all_orders', 'buygo_orders_nonce' ); ?>
						<input type="hidden" name="action" value="buygo_delete_all_orders">
						<p>
							<button type="submit" class="button button-primary button-large" style="background: #dc3232; border-color: #dc3232;">
								🗑️ 刪除所有訂單
							</button>
						</p>
						<p class="description">
							⚠️ 警告：此操作將永久刪除所有訂單、訂單項目、交易記錄等相關資料，無法復原！
						</p>
					</form>
				<?php else : ?>
					<p>✅ 目前沒有訂單。</p>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2>🗑️ 批量刪除產品</h2>
				<p>目前有 <strong><?php echo esc_html( $total ); ?></strong> 個 FluentCart 產品。</p>

				<?php if ( $total > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('確定要刪除所有 <?php echo esc_js( $total ); ?> 個產品嗎？此操作無法復原！');">
						<?php wp_nonce_field( 'buygo_delete_all_products', 'buygo_nonce' ); ?>
						<input type="hidden" name="action" value="buygo_delete_all_products">
						<input type="hidden" name="product_type" value="<?php echo esc_attr( $product_type ); ?>">
						<p>
							<button type="submit" class="button button-primary button-large" style="background: #dc3232; border-color: #dc3232;">
								🗑️ 刪除所有產品
							</button>
						</p>
						<p class="description">
							⚠️ 警告：此操作將永久刪除所有 FluentCart 產品，無法復原！
						</p>
					</form>
				<?php else : ?>
					<p>✅ 目前沒有產品。</p>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2>產品列表</h2>
				<?php
				$products = array();
				if ( $product_type ) {
					$products = get_posts(
						array(
							'post_type'      => $product_type,
							'posts_per_page' => 20,
							'post_status'    => 'any',
						)
					);
				}

				if ( ! empty( $products ) ) :
					?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>ID</th>
								<th>標題</th>
								<th>作者</th>
								<th>狀態</th>
								<th>建立時間</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $products as $product ) : ?>
								<?php
								$author = get_userdata( $product->post_author );
								$author_name = $author ? $author->display_name : '未知';
								?>
								<tr>
									<td><?php echo esc_html( $product->ID ); ?></td>
									<td><?php echo esc_html( $product->post_title ); ?></td>
									<td><?php echo esc_html( $author_name ); ?></td>
									<td><?php echo esc_html( $product->post_status ); ?></td>
									<td><?php echo esc_html( $product->post_date ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $total > 20 ) : ?>
						<p>顯示前 20 個產品，共 <?php echo esc_html( $total ); ?> 個。</p>
					<?php endif; ?>
				<?php else : ?>
					<p>目前沒有產品。</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * 處理刪除所有產品
	 */
	public function handle_delete_all_products() {
		// 驗證權限.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '您沒有權限執行此操作。' );
		}

		// 驗證 nonce.
		if ( ! isset( $_POST['buygo_nonce'] ) || ! wp_verify_nonce( $_POST['buygo_nonce'], 'buygo_delete_all_products' ) ) {
			wp_die( '安全驗證失敗。' );
		}

		// 取得產品類型.
		$product_type = isset( $_POST['product_type'] ) ? sanitize_text_field( $_POST['product_type'] ) : 'fc_product';

		// 取得所有產品.
		$products = get_posts(
			array(
				'post_type'      => $product_type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		$deleted_count = 0;

		// 刪除所有產品.
		foreach ( $products as $product ) {
			if ( wp_delete_post( $product->ID, true ) ) {
				$deleted_count++;
			}
		}

		// 重定向回管理頁面.
		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'buygo-product-manager',
					'deleted' => $deleted_count,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * 處理刪除所有訂單
	 */
	public function handle_delete_all_orders() {
		// 驗證權限.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '您沒有權限執行此操作。' );
		}

		// 驗證 nonce.
		if ( ! isset( $_POST['buygo_orders_nonce'] ) || ! wp_verify_nonce( $_POST['buygo_orders_nonce'], 'buygo_delete_all_orders' ) ) {
			wp_die( '安全驗證失敗。' );
		}

		global $wpdb;

		$deleted_count = 0;

		// 1. 刪除訂單項目.
		$order_items_table = $wpdb->prefix . 'fct_order_items';
		$items_deleted = $wpdb->query( "DELETE FROM {$order_items_table}" );
		$deleted_count += $items_deleted;

		// 2. 刪除訂單地址.
		$order_addresses_table = $wpdb->prefix . 'fct_order_addresses';
		$wpdb->query( "DELETE FROM {$order_addresses_table}" );

		// 3. 刪除訂單交易記錄.
		$transactions_table = $wpdb->prefix . 'fct_order_transactions';
		$wpdb->query( "DELETE FROM {$transactions_table}" );

		// 4. 刪除訂單活動記錄（如果有的話）.
		$activities_table = $wpdb->prefix . 'fct_activity';
		$wpdb->query( "DELETE FROM {$activities_table} WHERE module_name = 'order'" );

		// 5. 刪除訂單操作記錄.
		$operations_table = $wpdb->prefix . 'fct_order_operations';
		$wpdb->query( "DELETE FROM {$operations_table}" );

		// 6. 刪除訂單 meta.
		$order_meta_table = $wpdb->prefix . 'fct_order_meta';
		$wpdb->query( "DELETE FROM {$order_meta_table}" );

		// 7. 刪除訂單.
		$orders_table = $wpdb->prefix . 'fct_orders';
		$orders_deleted = $wpdb->query( "DELETE FROM {$orders_table}" );
		$deleted_count += $orders_deleted;

		// 重定向回管理頁面.
		wp_redirect(
			add_query_arg(
				array(
					'page'           => 'buygo-product-manager',
					'deleted_orders' => $deleted_count,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
