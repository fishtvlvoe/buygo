<?php
/**
 * 訂單通知處理器
 *
 * 監聽 FluentCart 訂單事件並發送 LINE 通知
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Order_Notification
 */
class BuyGo_Plus_One_Order_Notification {

	/**
	 * LINE Channel Access Token
	 *
	 * @var string
	 */
	private $channel_access_token;

	/**
	 * Logger
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * 建構函數
	 */
	public function __construct() {
		$this->logger = BuyGo_Plus_One_Logger::get_instance();
	}

	/**
	 * 初始化
	 */
	public function init() {
		// 監聽 FluentCart 訂單建立事件.
		add_action( 'fluent_cart/order_created', array( $this, 'on_order_created' ), 10, 1 );

		// 監聽 FluentCart 訂單狀態變更事件.
		add_action( 'fluent_cart/order_status_changed', array( $this, 'on_order_status_changed' ), 10, 1 );
		
		// 監聽延遲的賣家通知事件.
		add_action( 'buygo_line_fc_delayed_seller_notification', array( $this, 'on_delayed_seller_notification' ), 10, 1 );
	}

	/**
	 * 處理訂單建立事件
	 *
	 * @param mixed $order_data FluentCart 訂單資料（可能是陣列或物件）.
	 */
	public function on_order_created( $order_data ) {
		// FluentCart 傳遞的是陣列：['order' => $order_object].
		if ( is_array( $order_data ) && isset( $order_data['order'] ) ) {
			$order = $order_data['order'];
		} elseif ( is_object( $order_data ) && isset( $order_data->order ) ) {
			$order = $order_data->order;
		} else {
			$order = $order_data;
		}

		// 轉換為物件（如果是陣列）.
		if ( is_array( $order ) ) {
			$order = (object) $order;
		}

		if ( ! $order || ! isset( $order->id ) ) {
			$this->logger->error(
				'Order object is null or invalid',
				array(
					'type'      => gettype( $order_data ),
					'has_order' => is_array( $order_data ) ? isset( $order_data['order'] ) : ( is_object( $order_data ) ? isset( $order_data->order ) : false ),
				)
			);
			return;
		}

		$this->logger->info(
			'Order created',
			array(
				'order_id'    => $order->id,
				'customer_id' => $order->customer_id,
				'status'      => $order->status,
				'total'       => $order->total_amount,
			)
		);

		// 發送通知給買家.
		$this->send_buyer_notification( $order, 'created' );

		// 發送通知給賣家.
		$this->send_seller_notification( $order, 'created' );
	}

	/**
	 * 處理訂單狀態變更事件
	 *
	 * @param array $data FluentCart 訂單狀態變更資料.
	 */
	public function on_order_status_changed( $data ) {
		// 從 $data 陣列取得訂單和狀態資訊.
		if ( ! is_array( $data ) || ! isset( $data['order'] ) ) {
			$this->logger->error( 'Invalid order status changed data' );
			return;
		}
		
		$order       = $data['order'];
		$old_status  = $data['old_status'] ?? '';
		$new_status  = $data['new_status'] ?? '';

		// 轉換為物件（如果是陣列）.
		if ( is_array( $order ) ) {
			$order = (object) $order;
		}

		if ( ! $order || ! isset( $order->id ) ) {
			$this->logger->error( 'Order object is null or invalid in status change' );
			return;
		}

		$this->logger->info(
			'Order status changed',
			array(
				'order_id'   => $order->id,
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);

		// 只在特定狀態變更時發送通知.
		$notify_statuses = array( 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded' );

		if ( in_array( $new_status, $notify_statuses, true ) ) {
			// 發送通知給買家.
			$this->send_buyer_notification( $order, 'status_changed' );

			// 發送通知給賣家.
			$this->send_seller_notification( $order, 'status_changed' );
		}
	}

	/**
	 * 處理延遲的賣家通知
	 *
	 * @param array $args 包含 order_id, event_type, customer_name, retry_count 的陣列.
	 */
	public function on_delayed_seller_notification( $args ) {
		$order_id      = $args['order_id'];
		$event_type    = $args['event_type'];
		$customer_name = $args['customer_name'];
		$retry_count   = $args['retry_count'] ?? 0;
		
		$this->logger->info( 'Processing delayed seller notification', array(
			'order_id'    => $order_id,
			'retry_count' => $retry_count,
		) );
		
		// 使用 FluentCart Model 取得訂單.
		if ( ! class_exists( 'FluentCart\App\Models\Order' ) ) {
			$this->logger->error( 'FluentCart Order model not found' );
			return;
		}
		
		// 使用 FluentCart Model 取得訂單（不使用 with，直接存取 items 屬性）.
		if ( ! class_exists( 'FluentCart\App\Models\Order' ) ) {
			$this->logger->error( 'FluentCart Order model not found' );
			return;
		}
		
		try {
			$order = \FluentCart\App\Models\Order::find( $order_id );
			
			if ( ! $order ) {
				$this->logger->error( 'Order not found for delayed notification', array( 'order_id' => $order_id ) );
				return;
			}
			
			$this->logger->info( 'Order loaded successfully', array( 'order_id' => $order_id ) );
			
		} catch ( Exception $e ) {
			$this->logger->error(
				'Error loading order',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
			return;
		}
		
		// 嘗試多種方式取得訂單商品.
		$items = array();
		
		// 方法 1：使用 items() 方法查詢.
		try {
			if ( method_exists( $order, 'items' ) ) {
				$items_query = $order->items();
				if ( $items_query ) {
					$items = $items_query->get();
					if ( $items && method_exists( $items, 'toArray' ) ) {
						$items = $items->toArray();
					}
					$this->logger->info( 'Got items using items() method', array(
						'count' => is_array( $items ) ? count( $items ) : 0,
					) );
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Error using items() method', array( 'error' => $e->getMessage() ) );
		}
		
		// 方法 2：如果方法 1 失敗，直接查詢 OrderItem 表.
		if ( empty( $items ) && class_exists( 'FluentCart\App\Models\OrderItem' ) ) {
			try {
				$items_collection = \FluentCart\App\Models\OrderItem::where( 'order_id', $order_id )->get();
				if ( $items_collection && method_exists( $items_collection, 'toArray' ) ) {
					$items = $items_collection->toArray();
				}
				$this->logger->info( 'Got items using OrderItem::where()', array(
					'count' => is_array( $items ) ? count( $items ) : 0,
				) );
			} catch ( Exception $e ) {
				$this->logger->error( 'Error using OrderItem::where()', array( 'error' => $e->getMessage() ) );
			}
		}
		
		if ( empty( $items ) ) {
			// 如果還是沒有商品，且重試次數少於 3 次，再延遲 5 秒重試.
			if ( $retry_count < 3 ) {
				$this->logger->info(
					'Still no items, scheduling retry',
					array(
						'order_id'    => $order_id,
						'retry_count' => $retry_count + 1,
					)
				);
				
				wp_schedule_single_event(
					time() + 5,
					'buygo_line_fc_delayed_seller_notification',
					array(
						array(
							'order_id'      => $order_id,
							'event_type'    => $event_type,
							'customer_name' => $customer_name,
							'retry_count'   => $retry_count + 1,
						),
					)
				);
				return;
			}
			
			$this->logger->error(
				'No items found after all retries',
				array(
					'order_id'    => $order_id,
					'retry_count' => $retry_count,
				)
			);
			return;
		}
		
		// 收集所有賣家的 LINE UID.
		$seller_uids = array();
		
		foreach ( $items as $item ) {
			// 根據 FluentCart API 文件，item 有 post_id 欄位（WordPress Post ID）.
			// 處理不同的商品資料格式.
			if ( is_array( $item ) ) {
				$product_id = $item['post_id'] ?? $item['product_id'] ?? null;
			} else {
				$product_id = $item->post_id ?? $item->product_id ?? null;
			}
			
			$this->logger->info( 'Processing item in delayed notification', array(
				'item_type'  => gettype( $item ),
				'product_id' => $product_id,
			) );
			
			if ( empty( $product_id ) ) {
				$this->logger->warning( 'Empty product_id in delayed notification' );
				continue;
			}
			
			$product = get_post( $product_id );
			if ( ! $product ) {
				$this->logger->warning( 'Product not found in delayed notification', array( 'product_id' => $product_id ) );
				continue;
			}
			
			$seller_id = $product->post_author;
			
			// 如果 post_author 是 0，嘗試從當前登入的使用者取得.
			if ( empty( $seller_id ) || '0' === $seller_id ) {
				$this->logger->warning( 'Product has no author, trying to get from current user', array(
					'product_id' => $product_id,
					'post_author' => $seller_id,
				) );
				
				// 由於商品是透過 LINE 建立的，我們需要從訂單的買家反推賣家.
				// 暫時跳過這個商品.
				continue;
			}
			
			$this->logger->info( 'Found product seller', array(
				'product_id' => $product_id,
				'seller_id'  => $seller_id,
			) );
			
			// (Using BuyGo Core)
			$line_uid = \BuyGo_Core::line()->get_line_uid( $seller_id );
			
			$this->logger->info( 'Seller LINE UID lookup result', array(
				'seller_id' => $seller_id,
				'line_uid' => $line_uid ? $line_uid : 'NOT FOUND',
			) );
			
			if ( ! empty( $line_uid ) && ! in_array( $line_uid, $seller_uids, true ) ) {
				$seller_uids[] = $line_uid;
				$this->logger->info( 'Added seller to notification list', array( 'line_uid' => $line_uid ) );
			}
		}
		
		if ( empty( $seller_uids ) ) {
			$this->logger->warning( 'No seller LINE UIDs found for delayed notification', array( 'order_id' => $order_id ) );
			return;
		}
		
		// 建立訊息.
		$message = $this->build_seller_message( $order, $event_type, $customer_name );
		
		// 發送給所有賣家.
		foreach ( $seller_uids as $line_uid ) {
			$this->logger->info( 'Sending delayed seller notification', array( 'line_uid' => $line_uid ) );
			$this->send_push_message( $line_uid, $message );
		}
	}

	/**
	 * 發送通知給買家
	 *
	 * @param object $order FluentCart 訂單物件.
	 * @param string $event_type 事件類型.
	 */
	private function send_buyer_notification( $order, $event_type ) {
		// FluentCart customer 物件包含 user_id.
		$user_id = null;
		if ( is_object( $order->customer ) && isset( $order->customer->user_id ) ) {
			$user_id = $order->customer->user_id;
		} elseif ( is_array( $order->customer ) && isset( $order->customer['user_id'] ) ) {
			$user_id = $order->customer['user_id'];
		}

		if ( empty( $user_id ) ) {
			$this->logger->warning(
				'Buyer user_id not found',
				array(
					'customer_id' => $order->customer_id,
					'customer'    => $order->customer,
				)
			);
			return;
		}

		// 取得買家的 LINE UID (Using BuyGo Core)
		$line_uid = \BuyGo_Core::line()->get_line_uid( $user_id );

		if ( empty( $line_uid ) ) {
			$this->logger->warning(
				'Buyer LINE UID not found',
				array(
					'user_id'     => $user_id,
					'customer_id' => $order->customer_id,
				)
			);
			return;
		}

		// 建立訊息.
		$message = $this->build_buyer_message( $order, $event_type );

		// 發送 LINE 訊息.
		$this->send_push_message( $line_uid, $message );
	}

	/**
	 * 發送通知給賣家
	 *
	 * @param object $order FluentCart 訂單物件.
	 * @param string $event_type 事件類型.
	 */
	private function send_seller_notification( $order, $event_type ) {
		// 取得客戶名稱（從 customer 物件）.
		$customer_name = '訪客';
		if ( is_object( $order->customer ) ) {
			$customer_name = $order->customer->full_name ?? $order->customer->first_name ?? '訪客';
		} elseif ( is_array( $order->customer ) ) {
			$customer_name = $order->customer['full_name'] ?? $order->customer['first_name'] ?? '訪客';
		}

		// 嘗試從訂單物件取得商品（FluentCart 可能在 hook 觸發時還沒寫入資料庫）.
		$items = array();
		
		// 方法 1：從訂單物件的 items 屬性取得.
		if ( isset( $order->items ) && ! empty( $order->items ) ) {
			$items = is_array( $order->items ) ? $order->items : (array) $order->items;
			$this->logger->info( 'Got items from order object', array( 'count' => count( $items ) ) );
		}
		
		// 方法 2：從資料庫查詢（如果方法 1 失敗）.
		if ( empty( $items ) ) {
			$items = $this->get_order_items( $order->id );
			$this->logger->info( 'Got items from database (immediate)', array( 'count' => count( $items ) ) );
		}
		
		// 方法 3：如果還是沒有商品，延遲 5 秒後再查詢一次.
		if ( empty( $items ) ) {
			$this->logger->info( 'No items found, scheduling delayed notification', array( 'order_id' => $order->id ) );
			
			// 使用 WordPress 的 wp_schedule_single_event 來延遲執行.
			wp_schedule_single_event(
				time() + 5,
				'buygo_line_fc_delayed_seller_notification',
				array(
					array(
						'order_id'      => $order->id,
						'event_type'    => $event_type,
						'customer_name' => $customer_name,
						'retry_count'   => 0,
					),
				)
			);
			return;
		}

		$this->logger->info(
			'Getting seller UIDs',
			array(
				'order_id'    => $order->id,
				'items_count' => count( $items ),
			)
		);

		// 收集所有賣家的 LINE UID.
		$seller_uids = array();

		foreach ( $items as $item ) {
			// 根據 FluentCart API 文件，item 有 post_id 欄位（WordPress Post ID）.
			if ( is_array( $item ) ) {
				$product_id = $item['post_id'] ?? $item['product_id'] ?? null;
			} else {
				$product_id = $item->post_id ?? $item->product_id ?? null;
			}
			
			if ( empty( $product_id ) ) {
				$this->logger->warning(
					'Item has no product_id',
					array(
						'item_type' => gettype( $item ),
						'item_keys' => is_array( $item ) ? array_keys( $item ) : ( is_object( $item ) ? array_keys( get_object_vars( $item ) ) : array() ),
					)
				);
				continue;
			}

			$product = get_post( $product_id );
			if ( ! $product ) {
				$this->logger->warning( 'Product not found', array( 'product_id' => $product_id ) );
				continue;
			}

			$seller_id = $product->post_author;
			$this->logger->info(
				'Processing product',
				array(
					'product_id' => $product_id,
					'seller_id'  => $seller_id,
				)
			);

			// (Using BuyGo Core)
			$line_uid = \BuyGo_Core::line()->get_line_uid( $seller_id );

			if ( ! empty( $line_uid ) && ! in_array( $line_uid, $seller_uids, true ) ) {
				$seller_uids[] = $line_uid;
				$this->logger->info( 'Added seller LINE UID', array( 'line_uid' => $line_uid ) );
			}
		}

		if ( empty( $seller_uids ) ) {
			$this->logger->warning(
				'No seller LINE UIDs found for order',
				array(
					'order_id'    => $order->id,
					'items_count' => count( $items ),
				)
			);
			return;
		}

		// 建立訊息.
		$message = $this->build_seller_message( $order, $event_type, $customer_name );

		// 發送給所有賣家.
		foreach ( $seller_uids as $line_uid ) {
			$this->logger->info( 'Sending seller notification', array( 'line_uid' => $line_uid ) );
			$this->send_push_message( $line_uid, $message );
		}
	}

	/**
	 * 從資料庫取得訂單
	 *
	 * @param int $order_id 訂單 ID.
	 * @return object|null
	 */
	private function get_order_by_id( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fct_orders';

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$order_id
			)
		);

		return $order;
	}

	/**
	 * 取得訂單商品
	 *
	 * @param int $order_id 訂單 ID.
	 * @return array
	 */
	private function get_order_items( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fct_order_items';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_id = %d",
				$order_id
			)
		);

		return $items ? $items : array();
	}

	/**
	 * 建立買家訊息
	 *
	 * @param object $order FluentCart 訂單物件.
	 * @param string $event_type 事件類型.
	 * @return string
	 */
	private function build_buyer_message( $order, $event_type ) {
		$site_url     = get_site_url();
		$orders_url   = $site_url . '/account/';
		$status_text  = $this->get_status_text( $order->status );
		// FluentCart 以「分」為單位儲存，需要除以 100 轉換為「元」.
		$total_cents  = $order->total_amount ?? $order->total ?? 0;
		$total        = number_format( $total_cents / 100, 0, '.', ',' );

		if ( 'created' === $event_type ) {
			// 訂單建立.
			$message  = "✅ 訂單已建立\n\n";
			$message .= "訂單編號：#{$order->id}\n";
			$message .= "訂單金額：NT$ {$total}\n\n";
			$message .= "感謝您的訂購！\n";
			$message .= "我們會盡快為您處理。\n\n";
			$message .= "查看所有訂單：\n{$orders_url}";
		} else {
			// 狀態變更.
			switch ( $order->status ) {
				case 'paid':
				case 'processing':
					$message  = "💰 訂單已付款\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "訂單金額：NT$ {$total}\n\n";
					$message .= "我們已收到您的付款。\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;

				case 'shipped':
					$message  = "📦 訂單已出貨\n\n";
					$message .= "訂單編號：#{$order->id}\n\n";
					$message .= "您的訂單已經出貨囉！\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;

				case 'delivered':
					$message  = "🎉 訂單已送達\n\n";
					$message .= "訂單編號：#{$order->id}\n\n";
					$message .= "您的訂單已送達，請確認收貨。\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;

				case 'cancelled':
					$message  = "❌ 訂單已取消\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "訂單金額：NT$ {$total}\n\n";
					$message .= "如有疑問，請聯繫客服。\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;

				case 'refunded':
					$message  = "💰 訂單已退款\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "退款金額：NT$ {$total}\n\n";
					$message .= "退款將在 3-5 個工作天內到帳。\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;

				default:
					$message  = "📦 訂單狀態更新\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "新狀態：{$status_text}\n\n";
					$message .= "查看所有訂單：\n{$orders_url}";
					break;
			}
		}

		return $message;
	}

	/**
	 * 建立賣家訊息
	 *
	 * @param object $order FluentCart 訂單物件.
	 * @param string $event_type 事件類型.
	 * @param string $customer_name 客戶名稱.
	 * @return string
	 */
	private function build_seller_message( $order, $event_type, $customer_name ) {
		$site_url     = get_site_url();
		$order_url    = $site_url . '/account/';
		$status_text  = $this->get_status_text( $order->status );
		// FluentCart 以「分」為單位儲存，需要除以 100 轉換為「元」.
		$total_cents  = $order->total_amount ?? $order->total ?? 0;
		$total        = number_format( $total_cents / 100, 0, '.', ',' );

		if ( 'created' === $event_type ) {
			// 新訂單.
			$message  = "🔔 新訂單通知\n\n";
			$message .= "訂單編號：#{$order->id}\n";
			$message .= "客戶：{$customer_name}\n";
			$message .= "訂單金額：NT$ {$total}\n\n";
			$message .= "請盡快處理訂單。\n\n";
			$message .= "查看訂單：\n{$order_url}";
		} else {
			// 狀態變更.
			switch ( $order->status ) {
				case 'paid':
				case 'processing':
					$message  = "💰 訂單已付款\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "客戶：{$customer_name}\n";
					$message .= "訂單金額：NT$ {$total}\n\n";
					$message .= "請準備出貨。\n\n";
					$message .= "查看訂單：\n{$order_url}";
					break;

				case 'cancelled':
					$message  = "❌ 訂單已取消\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "客戶：{$customer_name}\n";
					$message .= "訂單金額：NT$ {$total}\n\n";
					$message .= "查看訂單：\n{$order_url}";
					break;

				case 'refunded':
					$message  = "💰 訂單已退款\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "客戶：{$customer_name}\n";
					$message .= "退款金額：NT$ {$total}\n\n";
					$message .= "查看訂單：\n{$order_url}";
					break;

				default:
					$message  = "📦 訂單狀態更新\n\n";
					$message .= "訂單編號：#{$order->id}\n";
					$message .= "客戶：{$customer_name}\n";
					$message .= "新狀態：{$status_text}\n\n";
					$message .= "查看訂單：\n{$order_url}";
					break;
			}
		}

		return $message;
	}

	/**
			'_buygo_line_uid',
			'social-id_line',
			'nsl_line_id',
		);

		foreach ( $possible_meta_keys as $meta_key ) {
			$line_uid = get_user_meta( $user_id, $meta_key, true );
			if ( ! empty( $line_uid ) ) {
				$this->logger->debug(
					'LINE UID found from user_meta',
					array(
						'user_id'  => $user_id,
						'meta_key' => $meta_key,
	 * 取得狀態文字
	 *
	 * @param string $status 狀態代碼.
	 * @return string
	 */
	private function get_status_text( $status ) {
		$status_map = array(
			'pending'    => '待付款',
			'paid'       => '已付款',
			'processing' => '處理中',
			'completed'  => '已完成',
			'cancelled'  => '已取消',
			'refunded'   => '已退款',
			'failed'     => '失敗',
			'on-hold'    => '保留中',
			'shipped'    => '已出貨',
			'delivered'  => '已送達',
		);

		return $status_map[ $status ] ?? $status;
	}

	/**
	 * 發送 Push 訊息
	 *
	 * @param string $line_uid LINE User ID.
	 * @param string $message 訊息內容.
	 * @return bool
	 */
	private function send_push_message( $line_uid, $message ) {
		// 檢查 LINE 訊息通知是否啟用
		$settings = \BuyGo_Core::settings();
		$line_message_enabled = $settings->get('line_message_enabled', true);
		
		if ( ! $line_message_enabled ) {
			$this->logger->info( 'LINE message notification is disabled, skipping push message' );
			return false;
		}
		
		// Using BuyGo Core settings
		$token = $settings->get('line_channel_access_token', '');
		
		if ( empty( $token ) ) {
			$this->logger->warning( 'Channel Access Token not set, cannot send push message' );
			return false;
		}

		$url = 'https://api.line.me/v2/bot/message/push';

		$data = array(
			'to'       => $line_uid,
			'messages' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'Failed to send push message',
				array(
					'error' => $response->get_error_message(),
				)
			);
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$this->logger->error(
				'LINE API error',
				array(
					'status_code' => $status_code,
					'response'    => wp_remote_retrieve_body( $response ),
				)
			);
			return false;
		}

		$this->logger->info( 'Push message sent successfully', array( 'line_uid' => $line_uid ) );
		return true;
	}
}
