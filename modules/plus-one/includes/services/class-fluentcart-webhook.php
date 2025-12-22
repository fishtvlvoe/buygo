<?php
/**
 * FluentCart Webhook 處理器
 *
 * 接收 FluentCart 的 Webhook 並發送 LINE 通知
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_FluentCart_Webhook
 */
class BuyGo_Plus_One_FluentCart_Webhook {

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
	 * 註冊 REST API 路由
	 */
	public function register_routes() {
		register_rest_route(
			'buygo-plus-one/v1',
			'/fluentcart-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * 處理 Webhook 請求
	 *
	 * @param WP_REST_Request $request 請求物件
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$this->logger->info( 'FluentCart Webhook received' );

		// 取得請求內容
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			$this->logger->warning( 'Empty webhook body' );
			return rest_ensure_response( array( 'success' => false, 'message' => 'Empty body' ) );
		}

		$this->logger->info( 'FluentCart Webhook data', $body );

		// 處理訂單資料
		$this->handle_order_event( $body );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * 處理訂單事件
	 *
	 * @param array $data Webhook 資料
	 */
	private function handle_order_event( $data ) {
		// FluentCart Webhook 會發送完整的訂單資料
		// 可能的事件：訂單已付款、訂單已取消、訂單已全額退款等

		$order_id = $data['id'] ?? 0;
		$status   = $data['status'] ?? '';

		if ( empty( $order_id ) ) {
			$this->logger->error( 'Order ID not found in webhook data' );
			return;
		}

		$this->logger->info( 'Processing order event', array(
			'order_id' => $order_id,
			'status'   => $status,
			'data'     => $data,
		) );

		// 發送通知給買家
		$this->send_buyer_notification( $data );

		// 發送通知給賣家
		$this->send_seller_notification( $data );
	}

	/**
	 * 發送通知給買家
	 *
	 * @param array $order_data 訂單資料
	 */
	private function send_buyer_notification( $order_data ) {
		$order_id    = $order_data['id'] ?? 0;
		$customer_id = $order_data['customer_id'] ?? 0;
		$status      = $order_data['status'] ?? '';
		$total       = $order_data['total'] ?? 0;

		// 取得買家的 LINE UID (Using BuyGo Core)
		$line_uid = \BuyGo_Core::line()->get_line_uid( $customer_id );

		if ( empty( $line_uid ) ) {
			$this->logger->warning( 'Buyer LINE UID not found', array( 'customer_id' => $customer_id ) );
			return;
		}

		// 建立訊息
		$message = $this->build_buyer_message( $order_id, $status, $total );

		// 發送 LINE 訊息
		$this->send_push_message( $line_uid, $message );
	}

	/**
	 * 發送通知給賣家
	 *
	 * @param array $order_data 訂單資料.
	 */
	private function send_seller_notification( $order_data ) {
		$order_id    = $order_data['id'] ?? 0;
		$customer_id = $order_data['customer_id'] ?? 0;
		$status      = $order_data['status'] ?? '';
		$total       = $order_data['total'] ?? 0;
		$items       = $order_data['items'] ?? array();

		$this->logger->info(
			'Starting seller notification',
			array(
				'order_id'    => $order_id,
				'items_count' => count( $items ),
			)
		);

		// 取得客戶名稱.
		$customer      = get_userdata( $customer_id );
		$customer_name = $customer ? $customer->display_name : '訪客';

		// 收集所有賣家的 LINE UID.
		$seller_uids = array();

		foreach ( $items as $item ) {
			$product_id = $item['product_id'] ?? 0;
			if ( empty( $product_id ) ) {
				$this->logger->warning( 'Item has no product_id', array( 'item' => $item ) );
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
					'order_id'    => $order_id,
					'items_count' => count( $items ),
				)
			);
			return;
		}

		// 建立訊息.
		$message = $this->build_seller_message( $order_id, $status, $total, $customer_name );

		// 發送給所有賣家.
		foreach ( $seller_uids as $line_uid ) {
			$this->logger->info( 'Sending seller notification', array( 'line_uid' => $line_uid ) );
			$this->send_push_message( $line_uid, $message );
		}
	}

	/**
	 * 建立買家訊息
	 *
	 * @param int    $order_id 訂單 ID.
	 * @param string $status 訂單狀態.
	 * @param float  $total 訂單金額.
	 * @return string
	 */
	private function build_buyer_message( $order_id, $status, $total ) {
		// FluentCart 訂單頁面 URL - 使用完整 URL 避免跳轉問題.
		$order_url   = 'https://test.buygo.me/account/orders/view/' . $order_id;
		$status_text = $this->get_status_text( $status );

		// 根據狀態建立不同的訊息
		switch ( $status ) {
			case 'paid':
			case 'processing':
				// 訂單已付款
				$message  = "✅ 訂單已建立\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "訂單金額：NT$ {$total}\n\n";
				$message .= "感謝您的訂購！\n";
				$message .= "我們會盡快為您處理。\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			case 'cancelled':
				// 訂單已取消
				$message  = "❌ 訂單已取消\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "訂單金額：NT$ {$total}\n\n";
				$message .= "如有疑問，請聯繫客服。\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			case 'refunded':
				// 訂單已退款
				$message  = "💰 訂單已退款\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "退款金額：NT$ {$total}\n\n";
				$message .= "退款將在 3-5 個工作天內到帳。\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			default:
				// 其他狀態變更
				$message  = "📦 訂單狀態更新\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "新狀態：{$status_text}\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;
		}

		return $message;
	}

	/**
	 * 建立賣家訊息
	 *
	 * @param int    $order_id 訂單 ID.
	 * @param string $status 訂單狀態.
	 * @param float  $total 訂單金額.
	 * @param string $customer_name 客戶名稱.
	 * @return string
	 */
	private function build_seller_message( $order_id, $status, $total, $customer_name ) {
		// FluentCart 訂單頁面 URL - 使用完整 URL 避免跳轉問題.
		$order_url   = 'https://test.buygo.me/account/orders/view/' . $order_id;
		$status_text = $this->get_status_text( $status );

		// 根據狀態建立不同的訊息
		switch ( $status ) {
			case 'paid':
			case 'processing':
				// 新訂單
				$message  = "🔔 新訂單通知\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "客戶：{$customer_name}\n";
				$message .= "訂單金額：NT$ {$total}\n\n";
				$message .= "請盡快處理訂單。\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			case 'cancelled':
				// 訂單已取消
				$message  = "❌ 訂單已取消\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "客戶：{$customer_name}\n";
				$message .= "訂單金額：NT$ {$total}\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			case 'refunded':
				// 訂單已退款
				$message  = "💰 訂單已退款\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "客戶：{$customer_name}\n";
				$message .= "退款金額：NT$ {$total}\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;

			default:
				// 其他狀態變更
				$message  = "📦 訂單狀態更新\n\n";
				$message .= "訂單編號：#{$order_id}\n";
				$message .= "客戶：{$customer_name}\n";
				$message .= "新狀態：{$status_text}\n\n";
				$message .= "查看訂單：\n{$order_url}";
				break;
		}

		return $message;
	}

	/**
	 * 取得狀態文字
	 *
	 * @param string $status 狀態代碼
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
	 * @param string $line_uid LINE User ID
	 * @param string $message 訊息內容
	 * @return bool
	 */
	private function send_push_message( $line_uid, $message ) {
		// Using BuyGo Core Settings
		$token = \BuyGo_Core::settings()->get('line_channel_access_token', '');

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
			$this->logger->error( 'Failed to send push message', array(
				'error' => $response->get_error_message(),
			) );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$this->logger->error( 'LINE API error', array(
				'status_code' => $status_code,
				'response'    => wp_remote_retrieve_body( $response ),
			) );
			return false;
		}

		$this->logger->info( 'Push message sent successfully', array( 'line_uid' => $line_uid ) );
		return true;
	}
}
