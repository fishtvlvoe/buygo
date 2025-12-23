<?php
/**
 * LINE Webhook 處理器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Webhook_Handler
 */
class BuyGo_Plus_One_Webhook_Handler {

	/**
	 * Message Parser
	 *
	 * @var BuyGo_Plus_One_Message_Parser
	 */
	private $message_parser;

	/**
	 * Image Uploader
	 *
	 * @var BuyGo_Plus_One_Image_Uploader
	 */
	private $image_uploader;

	/**
	 * Product Creator
	 *
	 * @var BuyGo_Plus_One_Product_Creator
	 */
	private $product_creator;

	/**
	 * Role Manager
	 *
	 * @var BuyGo_Plus_One_Role_Manager
	 */
	private $role_manager;

	/**
	 * Order Manager
	 *
	 * @var BuyGo_Plus_One_Order_Manager
	 */
	private $order_manager;

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
		$this->logger                = BuyGo_Plus_One_Logger::get_instance();
		
		// Load Templates Class if not autoloaded
		if ( ! class_exists( 'BuyGo_Plus_One_Line_Flex_Templates' ) ) {
			$template_file = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/templates/class-line-flex-templates.php';
			if ( file_exists( $template_file ) ) {
				require_once $template_file;
			}
		}

		// 初始化服務（後續任務會實作這些類別）
		$this->message_parser  = new BuyGo_Plus_One_Message_Parser();
		// Image Uploader needs token, pass it from Core Settings
		$token = \BuyGo_Core::settings()->get('line_channel_access_token', '');
		$this->image_uploader  = new BuyGo_Plus_One_Image_Uploader( $token );
		
		$this->product_creator = new BuyGo_Plus_One_Product_Creator();
		$this->role_manager    = new BuyGo_Plus_One_Role_Manager();
		$this->order_manager   = new BuyGo_Plus_One_Order_Manager();
	}

	/**
	 * 註冊 REST API 路由
	 */
	public function register_routes() {
		register_rest_route(
			'buygo-plus-one/v1',
			'/webhook',
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
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( $request ) {
		// 防止客戶端斷線導致腳本終止
		ignore_user_abort( true );
		set_time_limit( 0 );

		$this->logger->info( 'Webhook received' );

		// 驗證簽章
		if ( ! $this->verify_signature( $request ) ) {
			$this->logger->error( 'Invalid signature' );
			return new WP_Error( 'invalid_signature', 'Invalid signature', array( 'status' => 401 ) );
		}

		// 取得請求內容
		$body = json_decode( $request->get_body(), true );

		if ( empty( $body['events'] ) ) {
			$this->logger->warning( 'No events in webhook' );
			return rest_ensure_response( array( 'success' => true ) );
		}

		// 處理每個事件
		foreach ( $body['events'] as $event ) {
			// 1. Check for Verify Event (Dummy Token)
			$reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
			if ( '00000000000000000000000000000000' === $reply_token ) {
				$this->logger->info( 'Verify Event detected (000...000), returning success immediately' );
				return rest_ensure_response( array( 'success' => true ) );
			}

			// 2. Deduplication using Webhook Event ID
			$event_id = isset( $event['webhookEventId'] ) ? $event['webhookEventId'] : '';
			if ( $event_id ) {
				$cache_key = 'buygo_line_event_' . $event_id;
				if ( get_transient( $cache_key ) ) {
					$this->logger->info( 'Duplicate event detected, skipping', array( 'event_id' => $event_id ) );
					continue;
				}
				// Cache for 60 seconds
				set_transient( $cache_key, true, 60 );
			}

			$this->handle_event( $event );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * 驗證 LINE 簽章
	 *
	 * @param WP_REST_Request $request 請求物件
	 * @return bool
	 */
	private function verify_signature( $request ) {
		$channel_secret = \BuyGo_Core::settings()->get('line_channel_secret', '');

		// 如果沒有設定 Channel Secret，跳過驗證（開發模式）
		if ( empty( $channel_secret ) ) {
			$this->logger->warning( 'Channel Secret not set, skipping signature verification' );
			return true;
		}

		$signature = $request->get_header( 'x-line-signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$body = $request->get_body();
		$hash = base64_encode( hash_hmac( 'sha256', $body, $channel_secret, true ) );

		return hash_equals( $signature, $hash );
	}

	/**
	 * 處理事件
	 *
	 * @param array $event 事件資料
	 */
	private function handle_event( $event ) {
		$event_type = $event['type'] ?? '';

		$this->logger->info( 'Event type: ' . $event_type, $event );

		switch ( $event_type ) {
			case 'message':
				$this->handle_message( $event );
				break;

			case 'follow':
				$this->handle_follow( $event );
				break;

			case 'unfollow':
				$this->handle_unfollow( $event );
				break;

			default:
				$this->logger->info( 'Unhandled event type: ' . $event_type );
				break;
		}
	}

	/**
	 * 處理訊息事件
	 *
	 * @param array $event 事件資料
	 */
	private function handle_message( $event ) {
		$message_type = $event['message']['type'] ?? '';
		$reply_token  = $event['replyToken'] ?? '';

		switch ( $message_type ) {
			case 'image':
				$this->handle_image_message( $event );
				break;

			case 'text':
				$this->handle_text_message( $event );
				break;

			default:
				$this->logger->info( 'Unhandled message type: ' . $message_type );
				$this->send_reply( $reply_token, '抱歉，目前只支援圖片和文字訊息。' );
				break;
		}
	}

	/**
	 * 處理圖片訊息
	 *
	 * @param array $event 事件資料
	 */
	private function handle_image_message( $event ) {
		$message_id  = $event['message']['id'] ?? '';
		$line_uid    = $event['source']['userId'] ?? '';
		$reply_token = $event['replyToken'] ?? '';

		$this->logger->info( 'Image message received', array(
			'message_id' => $message_id,
			'line_uid'   => $line_uid,
		) );

		// 取得 WordPress 使用者 (Using BuyGo Core)
		$this->logger->info( 'Calling BuyGo_Core::line()->get_user_by_line_uid', array( 'line_uid' => $line_uid ) );
		$user = \BuyGo_Core::line()->get_user_by_line_uid( $line_uid );
		$this->logger->info( 'get_user_by_line_uid result', array(
			'user_found' => ! empty( $user ),
			'user_id'    => $user ? $user->ID : null,
		) );

		if ( ! $user ) {
			$this->logger->warning( 'User not found, sending binding message', array( 'line_uid' => $line_uid ) );
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 準備變數參數（用於所有可能需要變數的模板）
		$template_args = array(
			'display_name' => $user->display_name ?: $user->user_login,
			'user_email' => $user->user_email,
		);

		// 檢查權限
		$can_upload = $this->role_manager->can_upload_product( $user->ID );

		if ( ! $can_upload ) {
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_no_permission', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '您沒有上傳商品的權限。請先申請成為賣家。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 驗證 user ID 是否有效
		if ( $user->ID <= 0 ) {
			$this->logger->error(
				'Invalid user ID in handle_image_message',
				array(
					'line_uid' => $line_uid,
					'user_id'  => $user->ID,
				)
			);
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_user_error', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '系統錯誤：無法識別使用者。請重新綁定 LINE 帳號。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 下載並上傳圖片
		$attachment_id = $this->image_uploader->download_and_upload( $message_id, $user->ID );

		if ( is_wp_error( $attachment_id ) ) {
			$this->logger->error( 'Image upload failed', array(
				'error' => $attachment_id->get_error_message(),
			) );
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_image_upload_failed', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '圖片上傳失敗，請稍後再試。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		$this->logger->info( 'Image uploaded', array(
			'attachment_id' => $attachment_id,
		) );

		// New Flow (2025-12-16): Reply with Flex Message Menu
		// 使用 Flex Template 讓用戶選擇上架格式 (0 Cost Loop)
		if ( class_exists( 'BuyGo_Plus_One_Line_Flex_Templates' ) ) {
			$flex_message = BuyGo_Plus_One_Line_Flex_Templates::get_product_upload_menu();
			$this->send_reply( $reply_token, $flex_message );
		} else {
			// Fallback if template class missing
			// 使用 NotificationTemplates 系統讀取自訂模板
			$templates_array = \BuyGo\Core\Services\NotificationTemplates::get_by_trigger_condition( 'system_image_uploaded', $template_args );
			
			// 如果沒有模板，使用預設訊息
			if (empty($templates_array)) {
				$default_message = "✅ 圖片已收到！\n\n請發送商品資訊：\n商品名稱、價格、數量\n\n💡 輸入 /help 查看格式說明";
				$this->send_reply( $reply_token, $default_message );
			} else {
				// (原有的 Push Logic，暫時註解掉或作為 fallback)
				// 這裡如果失敗回退到舊邏輯
				foreach ($templates_array as $index => $template) {
					if (!empty($template['line']['text'])) {
						$this->send_reply( $reply_token, $template['line']['text'] );
						break; // Only send one reply logic here for simplicity in fallback
					}
				}
			}
		}

		// 注意：舊邏輯在回覆後會發送 system_copy_template (Push Message)。
		// 在新流程中，我們不再主動推播，而是等待用戶點擊按鈕 (Reply Message)。
		// 所以這裡移除 (或是註解掉) 原本的 Push Logic，以節省成本。
		/*
		$copy_template_array = \BuyGo\Core\Services\NotificationTemplates::get_by_trigger_condition( 'system_copy_template', $template_args );
		if (!empty($copy_template_array)) {
			// ... Push Logic ...
		}
		*/
	}

	/**
	 * 處理文字訊息
	 *
	 * @param array $event 事件資料
	 */
	private function handle_text_message( $event ) {
		$text        = $event['message']['text'] ?? '';
		$line_uid    = $event['source']['userId'] ?? '';
		$reply_token = $event['replyToken'] ?? '';

		$this->logger->info( 'Text message received', array(
			'text'     => $text,
			'line_uid' => $line_uid,
		) );

		// 允許其他外掛介入處理文字訊息
		// 如果 Hook 回傳 true，表示已經處理完畢，不再繼續執行
		$handled = apply_filters( 'buygo_plus_one_pre_handle_text_message', false, $text, $line_uid, $reply_token, $this );
		if ( $handled ) {
			$this->logger->info( 'Text message handled by external filter' );
			return;
		}

		// 檢查是否為指令（指令不需要檢查使用者綁定）
		if ( $this->message_parser->is_command( $text ) ) {
			$this->logger->info( 'Command detected, skipping user check', array( 'command' => $text ) );
			$this->handle_command( $text, $reply_token );
			return;
		}

		// 取得 WordPress 使用者 (Using BuyGo Core)
		$this->logger->info( 'Calling BuyGo_Core::line()->get_user_by_line_uid', array( 'line_uid' => $line_uid ) );
		$user = \BuyGo_Core::line()->get_user_by_line_uid( $line_uid );
		$this->logger->info( 'get_user_by_line_uid result', array(
			'user_found' => ! empty( $user ),
			'user_id'    => $user ? $user->ID : null,
		) );

		if ( ! $user ) {
			$this->logger->warning( 'User not found, sending binding message', array( 'line_uid' => $line_uid ) );
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_account_not_bound', [] );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '請先使用 LINE Login 綁定您的帳號。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 準備變數參數（用於所有可能需要變數的模板）
		$template_args = array(
			'display_name' => $user->display_name ?: $user->user_login,
			'user_email' => $user->user_email,
		);

		// 檢查權限 (Product Upload)
		if ( ! $this->role_manager->can_upload_product( $user->ID ) ) {
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_no_permission', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '您沒有上傳商品的權限。請先申請成為賣家。';
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 解析商品資訊
		$product_data = $this->message_parser->parse( $text );
		$validation   = $this->message_parser->validate( $product_data );

		if ( ! $validation['valid'] ) {
			$missing_fields = $this->get_field_names( $validation['missing'] );
			
			// 使用 NotificationTemplates 系統讀取自訂模板
			$template_args = array(
				'missing_fields' => implode( '、', $missing_fields ),
			);
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_product_data_incomplete', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : "商品資料不完整，缺少：" . implode( '、', $missing_fields ) . "\n\n請使用以下格式：\n商品名稱\n價格：350\n數量：20";

			$this->send_reply( $reply_token, $message );
			return;
		}

		// 驗證 user ID 是否有效
		if ( $user->ID <= 0 ) {
			$this->logger->error(
				'Invalid user ID in handle_text_message',
				array(
					'line_uid' => $line_uid,
					'user_id'  => $user->ID,
				)
			);
			$this->send_reply( $reply_token, '系統錯誤：無法識別使用者。請重新綁定 LINE 帳號。' );
			return;
		}

		// 取得暫存的圖片
		$image_ids = $this->image_uploader->get_temp_images( $user->ID );

		// 將 user_id 加入到商品資料中
		$product_data['user_id'] = $user->ID;

		$this->logger->info(
			'Creating product with user_id',
			array(
				'user_id'      => $user->ID,
				'product_name' => $product_data['name'] ?? '',
			)
		);

		// 開始流程監控記錄
		$workflow_id = null;
		if ( class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
			$workflow_id = \BuyGo\Core\Services\WorkflowLoggerHelper::start_workflow( 'product_upload', $line_uid );
			\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'line_message_received', 1, 'completed', [
				'line_user_id' => $line_uid,
				'workflow_type' => 'product_upload',
				'message' => '已接收 LINE 訊息'
			] );
			\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'parse_image_text', 2, 'completed', [
				'line_user_id' => $line_uid,
				'workflow_type' => 'product_upload',
				'message' => '成功解析商品資料',
				'metadata' => [ 'product_name' => $product_data['name'] ?? '' ]
			] );
			
			// 將 workflow_id 和 line_uid 加入到 product_data 中，以便 create 方法可以使用
			$product_data['workflow_id'] = $workflow_id;
			$product_data['line_uid'] = $line_uid;
		}

		// 建立商品
		$post_id = $this->product_creator->create( $product_data, $image_ids );

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error( 'Product creation failed', array(
				'error' => $post_id->get_error_message(),
			) );
			
			// 記錄流程失敗
			if ( $workflow_id && class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
				\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'fluentcart_create', 3, 'failed', [
					'line_user_id' => $line_uid,
					'workflow_type' => 'product_upload',
					'error' => $post_id->get_error_message()
				] );
			}
			
			// 使用 NotificationTemplates 系統讀取自訂模板
			$template_args = array(
				'error_message' => $post_id->get_error_message(),
			);
			$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_product_publish_failed', $template_args );
			$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : '商品建立失敗：' . $post_id->get_error_message();
			
			$this->send_reply( $reply_token, $message );
			return;
		}

		// 記錄流程：FluentCart 商品建立成功
		if ( $workflow_id && class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
			// 先記錄步驟（如果還沒記錄的話）
			\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'fluentcart_create', 3, 'processing', [
				'product_id' => $post_id,
				'line_user_id' => $line_uid,
				'workflow_type' => 'product_upload'
			] );
			
			// 然後更新為完成
			\BuyGo\Core\Services\WorkflowLoggerHelper::update_step( $workflow_id, 'fluentcart_create', 'completed', [
				'product_id' => $post_id,
				'line_user_id' => $line_uid,
				'workflow_type' => 'product_upload',
				'message' => 'FluentCart 商品建立成功'
			] );
		}

		// 清除暫存圖片
		$this->image_uploader->clear_temp_images( $user->ID );

		$this->logger->info( 'Product created', array(
			'post_id' => $post_id,
		) );

		// 發送成功訊息
		// 使用 get_permalink() 取得商品連結（現在會是簡短的 /item/{post_id}）
		$product_url = get_permalink( $post_id );

		// 取得社群貼文連結
		$community_url = get_post_meta( $post_id, '_buygo_community_feed_url', true );

		// 取得原價（從商品變體中取得，如果沒有則從 product_data 中取得）
		$original_price = 0;
		if ( class_exists( 'FluentCart\App\Models\ProductVariation' ) ) {
			$variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $post_id )->first();
			if ( $variation && ! empty( $variation->compare_price ) ) {
				// FluentCart 以「分」為單位儲存，需要除以 100 轉換為元
				$original_price = floatval( $variation->compare_price ) / 100;
			}
		}
		// 如果變體中沒有原價，嘗試從 product_data 中取得
		if ( $original_price <= 0 && isset( $product_data['compare_price'] ) && $product_data['compare_price'] > 0 ) {
			$original_price = floatval( $product_data['compare_price'] );
		}

		// 取得到貨時間（優先從 post meta 取得，如果沒有則從 product_data 取得）
		$arrival_date = get_post_meta( $post_id, '_buygo_arrival_date', true );
		if ( empty( $arrival_date ) && ! empty( $product_data['arrival_date'] ) ) {
			$arrival_date = $product_data['arrival_date'];
		}

		// 取得預購時間（優先從 post meta 取得，如果沒有則從 product_data 取得）
		$preorder_date = get_post_meta( $post_id, '_buygo_preorder_date', true );
		if ( empty( $preorder_date ) && ! empty( $product_data['preorder_date'] ) ) {
			$preorder_date = $product_data['preorder_date'];
		}

		// 準備模板變數
		$currency = $product_data['currency'] ?? 'TWD';
		$currency_symbol = $this->get_currency_symbol($currency);
		
		$template_args = array(
			'product_name' => $product_data['name'] ?? '',
			'price' => $product_data['price'] ?? 0,
			'quantity' => $product_data['quantity'] ?? 0,
			'currency' => $currency,
			'currency_symbol' => $currency_symbol,
			'product_url' => $product_url,
			'community_url_section' => $community_url ? "\n\n社群 +1下單連結：\n{$community_url}" : '', // 修正變數名稱：使用 community_url_section
			'category_section' => ! empty( $product_data['category'] ) ? "分類：{$product_data['category']}" : '', // 移除開頭的換行符，讓用戶可以自己控制格式
			'arrival_date_section' => ! empty( $arrival_date ) ? "到貨：{$arrival_date}" : '', // 移除開頭的換行符
			'preorder_date_section' => ! empty( $preorder_date ) ? "預購：{$preorder_date}" : '', // 移除開頭的換行符
			'original_price_section' => $original_price > 0 ? "原價：{$original_price}" : '', // 原價區塊（移除開頭的換行符，讓用戶可以自己控制格式）
			'original_price' => $original_price > 0 ? $original_price : '', // 原價數值（不包含「原價：」文字）
		);

		// 使用 NotificationTemplates 系統讀取自訂模板（支援多個自訂模板）
		$templates_array = \BuyGo\Core\Services\NotificationTemplates::get_by_trigger_condition( 'system_product_published', $template_args );
		
		// 如果沒有模板，使用預設訊息
		if (empty($templates_array)) {
			$message = "{$product_data['name']}\n價格：NT$ {$product_data['price']}\n數量：{$product_data['quantity']} 個\n\n直接下單連結：\n{$product_url}";
		} else {
			// 發送所有匹配的模板（按 message_order 排序）
			$messages = [];
			foreach ($templates_array as $template_data) {
				if (isset($template_data['line']['text']) && !empty($template_data['line']['text'])) {
					$messages[] = $template_data['line']['text'];
				}
			}
			
			// 如果有多個訊息，合併它們（用雙換行分隔）
			if (count($messages) > 1) {
				$message = implode("\n\n", $messages);
			} else {
				$message = $messages[0] ?? "{$product_data['name']}\n價格：NT$ {$product_data['price']}\n數量：{$product_data['quantity']} 個\n\n直接下單連結：\n{$product_url}";
			}
		}

		$this->send_reply( $reply_token, $message );
		
		// 記錄流程：LINE 成功訊息回傳（在 send_reply 之後）
		// 注意：此時社群貼文可能還沒完成，所以這裡先記錄，社群貼文完成後會再次更新
		if ( $workflow_id && class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
			\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'line_success_notify', 5, 'completed', [
				'product_id' => $post_id,
				'line_user_id' => $line_uid,
				'workflow_type' => 'product_upload',
				'message' => 'LINE 成功訊息已回傳：' . substr( $message, 0, 200 )
			] );
		}
	}



	/**
	 * 處理指令
	 *
	 * @param string $command 指令
	 * @param string $reply_token Reply Token
	 */
	private function handle_command( $command, $reply_token ) {
		$command = trim( $command );

		// 先從資料庫讀取動態關鍵字
		$keywords = get_option( 'buygo_line_keywords', [] );
		$matched = false;
		
		foreach ( $keywords as $keyword_data ) {
			$keyword = trim( $keyword_data['keyword'] ?? '' );
			$aliases = $keyword_data['aliases'] ?? [];
			$message = $keyword_data['message'] ?? '';
			
			// 檢查是否匹配主關鍵字
			if ( $command === $keyword ) {
				$this->send_reply( $reply_token, $message );
				$matched = true;
				break;
			}
			
			// 檢查是否匹配別名
			foreach ( $aliases as $alias ) {
				if ( $command === trim( $alias ) ) {
					$this->send_reply( $reply_token, $message );
					$matched = true;
					break 2;
				}
			}
		}
		
		// 如果已匹配動態關鍵字，就不處理固定指令
		if ( $matched ) {
			return;
		}

		// 新增：上架指令處理 (Reply API - Free Cost)
		if ( strpos( $command, '指令：' ) === 0 ) {
			$action = mb_substr( $command, 3 ); // 移除 "指令："
			switch ( $action ) {
				case '單一商品模板':
					$msg = "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
					$this->send_reply( $reply_token, $msg );
					return; // End
				case '多樣商品模板':
					$msg = "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
					$this->send_reply( $reply_token, $msg );
					return; // End
				case '真人客服':
					// 讓它繼續往下跑，如果有設定關鍵字 "真人客服" 則會觸發，
					// 或者在這裡直接回覆。
					// 假設目前的關鍵字系統有 "真人客服" 設定，
					// 我們可以將 command 改為純粹的 "真人客服" 讓下方邏輯處理?
					// 但因為上方有 `if ( $matched )` 邏輯，
					// 我們可以直接在這裡送出，或者修改 $command 讓它跑下方邏輯。
					// 最簡單是直接這裡處理:
					$msg = "👩‍💻 好的，已通知真人客服，將儘快為您服務！";
					$this->send_reply( $reply_token, $msg );
					return;
			}
		}

		// 新增：上架指令處理 (Reply API - Free Cost)
		// 處理單一/多樣商品模板指令
		if ( $command === '/one' ) {
			$msg = "📋 複製以下格式發送：\n\n商品名稱\n價格：\n數量：";
			$this->send_reply( $reply_token, $msg );
			return;
		}

		if ( $command === '/many' ) {
			$msg = "📋 複製以下格式發送 (多樣)：\n\n商品名稱\n價格：\n數量：\n款式1：\n款式2：";
			$this->send_reply( $reply_token, $msg );
			return;
		}

		// "/help" 保留給下方的通用幫助指令處理，不需要在這裡攔截，除非要客製化。
		// 當前的 send_help() 已經包含了完整的說明。

		// 保留原有的固定指令（向後相容）
		// 分類指令
		if ( in_array( $command, array( '/分類', '?分類', '分類列表' ), true ) ) {
			$this->send_category_list( $reply_token );
		}
		// 幫助指令
		elseif ( in_array( $command, array( '/help', '/幫助', '?help', '幫助' ), true ) ) {
			$this->send_help( $reply_token );
		}
	}

	/**
	 * 發送價格格式說明
	 *
	 * @param string $reply_token Reply Token
	 */
	private function send_currency_help( $reply_token ) {
		$message  = "💰 價格格式說明\n\n";
		$message .= "支援以下格式：\n";
		$message .= "• 價格：350\n";
		$message .= "• 價格：NT$350\n";
		$message .= "• 價格：350 TWD\n\n";
		$message .= "💡 目前僅支援台幣（TWD）";

		$this->send_reply( $reply_token, $message );
	}

	/**
	 * 發送分類列表
	 *
	 * @param string $reply_token Reply Token
	 */
	private function send_category_list( $reply_token ) {
		$categories = get_terms( array(
			'taxonomy'   => 'fluent-product-category',
			'hide_empty' => false,
		) );

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			$this->send_reply( $reply_token, '目前沒有可用的分類。' );
			return;
		}

		$message = "📂 可用的商品分類：\n\n";
		foreach ( $categories as $category ) {
			$message .= "• {$category->name}\n";
		}
		$message .= "\n💡 您也可以直接輸入新的分類名稱。";

		$this->send_reply( $reply_token, $message );
	}

	/**
	 * 發送幫助訊息
	 *
	 * @param string $reply_token Reply Token
	 */
	private function send_help( $reply_token ) {
		$message  = "📱 商品上架說明\n\n";
		$message .= "【步驟】\n";
		$message .= "1️⃣ 發送商品圖片\n";
		$message .= "2️⃣ 發送商品資訊\n\n";
		$message .= "【必填欄位】\n";
		$message .= "商品名稱\n";
		$message .= "價格：350\n";
		$message .= "數量：20\n\n";
		$message .= "【選填欄位】\n";
		$message .= "原價：500\n";
		$message .= "分類：服飾\n";
		$message .= "到貨：01/25\n";
		$message .= "預購：01/20\n";
		$message .= "描述：商品描述\n\n";
		$message .= "【範例】\n";
		$message .= "冬季外套\n";
		$message .= "價格：1200\n";
		$message .= "原價：1800\n";
		$message .= "數量：15\n";
		$message .= "分類：服飾\n";
		$message .= "到貨：01/15\n\n";
		$message .= "💡 輸入 /分類 查看可用分類";

		$this->send_reply( $reply_token, $message );
	}

	/**
	 * 處理關注事件
	 *
	 * @param array $event 事件資料
	 */
	private function handle_follow( $event ) {
		$line_uid    = $event['source']['userId'] ?? '';
		$reply_token = $event['replyToken'] ?? '';

		$this->logger->info( 'User followed', array( 'line_uid' => $line_uid ) );

		// 使用 NotificationTemplates 系統讀取自訂模板
		$template = \BuyGo\Core\Services\NotificationTemplates::get( 'system_line_follow', [] );
		$message = $template && isset( $template['line']['text'] ) ? $template['line']['text'] : "歡迎使用 BuyGo 商品上架 🎉\n\n【快速開始】\n1️⃣ 發送商品圖片\n2️⃣ 發送商品資訊\n\n【格式範例】\n商品名稱\n價格：350\n數量：20\n\n💡 輸入 /help 查看完整說明";

		$this->send_reply( $reply_token, $message );
	}

	/**
	 * 處理取消關注事件
	 *
	 * @param array $event 事件資料
	 */
	private function handle_unfollow( $event ) {
		$line_uid = $event['source']['userId'] ?? '';
		$this->logger->info( 'User unfollowed', array( 'line_uid' => $line_uid ) );
	}

	/**
	 * 發送回覆訊息
	 *
	 * @param string $reply_token Reply Token
	 * @param string|array $message 訊息內容 (String or Array object)
	 * @return bool
	 */
	public function send_reply( $reply_token, $message ) {
		$token = \BuyGo_Core::settings()->get('line_channel_access_token', '');
		
		if ( empty( $token ) ) {
			$this->logger->warning( 'Channel Access Token not set, cannot send reply' );
			return false;
		}

		$url = 'https://api.line.me/v2/bot/message/reply';

		// Handle Text vs Flex/Array
		$messages_payload = [];
		if ( is_array( $message ) ) {
			// Check if it's a single message object (has 'type') or multiple
			if ( isset( $message['type'] ) ) {
				$messages_payload = array( $message );
			} else {
				$messages_payload = $message;
			}
		} else {
			$messages_payload = array(
				array(
					'type' => 'text',
					'text' => $message,
				)
			);
		}

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => $messages_payload,
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
			$this->logger->error( 'Failed to send reply', array(
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

		$this->logger->info( 'Reply sent successfully' );
		return true;
	}

	/**
	 * 取得欄位名稱
	 *
	 * @param array $fields 欄位陣列
	 * @return array
	 */
	private function get_field_names( $fields ) {
		$names = array();
		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'name':
					$names[] = '商品名稱';
					break;
				case 'price':
					$names[] = '價格';
					break;
				case 'quantity':
					$names[] = '數量';
					break;
				default:
					$names[] = $field;
					break;
			}
		}
		return $names;
	}

	/**
	 * 取得幣別符號
	 *
	 * @param string $currency 幣別代碼
	 * @return string
	 */
	private function get_currency_symbol( $currency ) {
		$symbols = array(
			'JPY' => '¥',
			'USD' => '$',
			'TWD' => 'NT$',
			'CNY' => '¥',
			'HKD' => 'HK$',
		);
		
		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : 'NT$';
	}

}
