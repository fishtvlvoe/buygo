<?php
/**
 * 訂單管理器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Order_Manager
 */
class BuyGo_Plus_One_Order_Manager {

	/**
	 * Logger.
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * 建構函數.
	 */
	public function __construct() {
		$this->logger = BuyGo_Plus_One_Logger::get_instance();
	}

	/**
	 * 初始化.
	 */
	public function init() {
		// 監聽 FluentCart 訂單 Hooks.
		add_action( 'fluent_cart_order_created', array( $this, 'on_order_created' ) );
		add_action( 'fluent_cart_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 );

		// 註冊定時任務.
		add_action( 'buygo_line_fc_check_expired_orders', array( $this, 'check_expired_orders' ) );
	}

	/**
	 * 訂單建立時的處理.
	 *
	 * @param object $order FluentCart Order 物件.
	 */
	public function on_order_created( $order ) {
		$this->logger->info(
			'Order created',
			array(
				'order_id' => $order->id,
				'status'   => $order->status,
			)
		);

		// 設定付款期限.
		$this->set_payment_deadline( $order );
	}

	/**
	 * 訂單狀態變更時的處理.
	 *
	 * @param object $order FluentCart Order 物件.
	 * @param string $old_status 舊狀態.
	 * @param string $new_status 新狀態.
	 */
	public function on_order_status_changed( $order, $old_status, $new_status ) {
		$this->logger->info(
			'Order status changed',
			array(
				'order_id'   => $order->id,
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);

		// 如果訂單被取消或失敗，釋放庫存.
		if ( in_array( $new_status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			$this->release_stock( $order );
			$this->notify_seller( $order, $new_status );
		}
	}

	/**
	 * 設定付款期限.
	 *
	 * @param object $order FluentCart Order 物件.
	 */
	private function set_payment_deadline( $order ) {
		// 取得付款期限設定（預設 3 天）.
		$deadline_days = get_option( 'buygo_line_fc_payment_deadline', 3 );

		// 計算付款期限時間戳.
		$deadline = strtotime( "+{$deadline_days} days", strtotime( $order->created_at ) );

		// 儲存到訂單 meta.
		update_post_meta( $order->id, '_buygo_payment_deadline', $deadline );

		$this->logger->debug(
			'Payment deadline set',
			array(
				'order_id' => $order->id,
				'deadline' => gmdate( 'Y-m-d H:i:s', $deadline ),
			)
		);
	}

	/**
	 * 釋放庫存.
	 *
	 * @param object $order FluentCart Order 物件.
	 */
	private function release_stock( $order ) {
		global $wpdb;

		$this->logger->info(
			'Releasing stock for order',
			array( 'order_id' => $order->id )
		);

		// 取得訂單項目.
		$order_items = \FluentCart\App\Models\OrderItem::where( 'order_id', $order->id )->get();

		foreach ( $order_items as $item ) {
			// 取得商品變體.
			$variation = \FluentCart\App\Models\ProductVariation::find( $item->variation_id );

			if ( ! $variation ) {
				$this->logger->warning(
					'Variation not found',
					array( 'variation_id' => $item->variation_id )
				);
				continue;
			}

			// 計算要釋放的數量.
			$quantity = $item->quantity;

			// 更新庫存：on_hold 減少，available 增加.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}fc_product_variations 
					SET on_hold = GREATEST(0, on_hold - %d),
						available = available + %d
					WHERE id = %d",
					$quantity,
					$quantity,
					$variation->id
				)
			);

			$this->logger->debug(
				'Stock released',
				array(
					'variation_id' => $variation->id,
					'quantity'     => $quantity,
				)
			);
		}

		$this->logger->info(
			'Stock released successfully',
			array( 'order_id' => $order->id )
		);
	}

	/**
	 * 通知賣家.
	 *
	 * @param object $order FluentCart Order 物件.
	 * @param string $status 訂單狀態.
	 */
	private function notify_seller( $order, $status ) {
		$this->logger->info(
			'Notifying seller',
			array(
				'order_id' => $order->id,
				'status'   => $status,
			)
		);

		// 取得訂單項目.
		$order_items = \FluentCart\App\Models\OrderItem::where( 'order_id', $order->id )->get();

		// 收集需要通知的賣家（商品作者）.
		$sellers = array();

		foreach ( $order_items as $item ) {
			$post = get_post( $item->post_id );
			if ( $post && ! in_array( $post->post_author, $sellers, true ) ) {
				$sellers[] = $post->post_author;
			}
		}

		// 發送通知給每個賣家.
		foreach ( $sellers as $seller_id ) {
			$seller = get_userdata( $seller_id );

			if ( ! $seller ) {
				continue;
			}

			$subject = sprintf( '訂單 #%d 已取消', $order->id );
			$message = sprintf(
				"您好 %s，\n\n您的商品訂單 #%d 已被取消。\n\n訂單狀態：%s\n取消時間：%s\n\n庫存已自動釋放。",
				$seller->display_name,
				$order->id,
				$this->get_status_label( $status ),
				gmdate( 'Y-m-d H:i:s' )
			);

			wp_mail( $seller->user_email, $subject, $message );

			$this->logger->debug(
				'Seller notified',
				array(
					'seller_id' => $seller_id,
					'email'     => $seller->user_email,
				)
			);
		}
	}

	/**
	 * 取得狀態標籤.
	 *
	 * @param string $status 狀態代碼.
	 * @return string 狀態標籤.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'cancelled' => '已取消',
			'failed'    => '失敗',
			'refunded'  => '已退款',
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * 檢查逾期訂單.
	 */
	public function check_expired_orders() {
		$this->logger->info( 'Checking expired orders...' );

		global $wpdb;

		// 取得當前時間戳.
		$current_time = time();

		// 查詢所有 pending 狀態的訂單.
		$orders = \FluentCart\App\Models\Order::where( 'status', 'pending' )->get();

		$expired_count = 0;

		foreach ( $orders as $order ) {
			// 取得付款期限.
			$deadline = get_post_meta( $order->id, '_buygo_payment_deadline', true );

			if ( ! $deadline ) {
				continue;
			}

			// 檢查是否逾期.
			if ( $current_time > $deadline ) {
				$this->logger->info(
					'Order expired',
					array(
						'order_id' => $order->id,
						'deadline' => gmdate( 'Y-m-d H:i:s', $deadline ),
					)
				);

				// 更新訂單狀態為 cancelled.
				$order->status = 'cancelled';
				$order->save();

				// 釋放庫存.
				$this->release_stock( $order );

				// 通知賣家.
				$this->notify_seller( $order, 'cancelled' );

				$expired_count++;
			}
		}

		$this->logger->info(
			'Expired orders check completed',
			array(
				'total_checked'  => count( $orders ),
				'expired_count'  => $expired_count,
			)
		);
	}
	/**
	 * 建立喊單訂單
	 *
	 * @param int $user_id 使用者 ID.
	 * @param int $product_id 商品 Post ID.
	 * @param int $quantity 數量.
	 * @return int|WP_Error 訂單 ID 或錯誤.
	 */
	public function create_plus_one_order( $user_id, $product_id, $quantity = 1 ) {
		// 檢查相依性
		if ( ! class_exists( 'FluentCart\App\Models\ProductVariation' ) || ! class_exists( 'FluentCart\App\Models\Order' ) ) {
			return new WP_Error( 'dependency_missing', 'FluentCart models not found' );
		}

		$this->logger->info( 'Creating Plus One Order', array(
			'user_id'    => $user_id,
			'product_id' => $product_id,
			'quantity'   => $quantity,
		) );

		// 1. 取得商品資訊
		$product_post = get_post( $product_id );
		if ( ! $product_post ) {
			return new WP_Error( 'product_not_found', 'Product not found' );
		}

		// 2. 取得商品變體 (預設取第一個)
		$variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $product_id )->first();
		if ( ! $variation ) {
			return new WP_Error( 'variation_not_found', 'Product variation not found' );
		}

		// 3. 建立新訂單
		$price      = $variation->item_price; // 價格 (分)
		$total_price = $price * $quantity;

		// 準備訂單資料
		$order_data = array(
			'customer_id'   => $user_id,
			'status'        => 'pending', // 待付款
			'currency'      => 'TWD',
			'total_amount'  => $total_price,
			'payment_status'=> 'unpaid',
			'payment_method'=> 'line_plus_one',
			'type'          => 'order',
		);

		try {
			// 建立訂單
			$order = \FluentCart\App\Models\Order::create( $order_data );
			
			if ( ! $order ) {
				throw new Exception( 'Failed to create order record' );
			}

			// 建立訂單項目
			// 建立訂單項目
			$item_data = array(
				'order_id'     => $order->id,
				'post_id'      => $product_id, 
				'product_id'   => $product_id, 
				'variation_id' => $variation->id,
				'quantity'     => $quantity,
				'unit_price'   => $price,
				'line_total'   => $total_price,
				'title'        => $product_post->post_title, // Changed from product_title
				'product_title'=> $product_post->post_title, // Keep backup just in case
				'status'       => 'pending', 
			);

			// 檢查 OrderItem model
			if ( class_exists( 'FluentCart\App\Models\OrderItem' ) ) {
				\FluentCart\App\Models\OrderItem::create( $item_data );
			} else {
				throw new Exception( 'OrderItem model not found' );
			}

			$this->logger->info( 'Order created successfully', array( 'order_id' => $order->id ) );
			
			// 觸發 FluentCart 訂單建立事件 (如果 Model::create 沒有自動觸發)
			do_action( 'fluent_cart_order_created', $order );

			return $order->id;

		} catch ( Exception $e ) {
			$this->logger->error( 'Order creation failed', array( 'error' => $e->getMessage() ) );
			return new WP_Error( 'create_order_failed', $e->getMessage() );
		}
	}
}
