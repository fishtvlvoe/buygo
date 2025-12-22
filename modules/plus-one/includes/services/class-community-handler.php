<?php
/**
 * 社群互動處理器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Community_Handler
 */
class BuyGo_Plus_One_Community_Handler {

	/**
	 * Logger
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * Order Manager (Deprecated for +1 flow, but kept for reference)
	 *
	 * @var BuyGo_Plus_One_Order_Manager
	 */
	private $order_manager;

	/**
	 * Cart Manager
	 *
	 * @var BuyGo_Plus_One_Cart_Manager
	 */
	private $cart_manager;

	/**
	 * Message Parser
	 *
	 * @var BuyGo_Plus_One_Message_Parser
	 */
	private $message_parser;

	/**
	 * 建構函數
	 */
	public function __construct() {
		require_once BUYGO_PLUS_ONE_PATH . 'includes/services/class-message-parser.php';
		require_once BUYGO_PLUS_ONE_PATH . 'includes/services/class-order-manager.php';
		require_once BUYGO_PLUS_ONE_PATH . 'includes/services/class-cart-manager.php'; // New

		$this->logger         = BuyGo_Plus_One_Logger::get_instance();
		$this->message_parser = new BuyGo_Plus_One_Message_Parser();
		$this->order_manager  = new BuyGo_Plus_One_Order_Manager();
		$this->cart_manager   = new BuyGo_Plus_One_Cart_Manager(); // New
	}

	/**
	 * 初始化
	 */
	public function init() {
		// error_log( 'MYGO Community Handler Init' );
		// 1. 監聽商品建立事件 -> 自動發布貼文
		add_action( 'buygo_line_fc/product_created', array( $this, 'create_community_post' ), 10, 3 );

		// 2. 監聽社群留言事件 -> 自動加入購物車
		// 根據官方文件，此 Hook 接收 $comment 和 $feed 兩個參數
		add_action( 'fluent_community/comment_added', array( $this, 'handle_community_comment' ), 10, 2 );
		
		// 備用: 針對已發布的留言 (Status: published)
		add_action( 'fluent_community/comment/new_comment_published', array( $this, 'handle_community_comment' ), 10, 2 );
	}

	/**
	 * 當商品建立時，自動在社群發布貼文
	 *
	 * @param int   $product_id   商品 Post ID.
	 * @param array $product_data 商品資料.
	 * @param string $line_uid    LINE 使用者 ID.
	 * @param string $workflow_id 流程 ID（可選，從 hook 參數或 meta 取得）.
	 */
	public function create_community_post( $product_id, $product_data, $line_uid = null, $workflow_id = null ) {
		$this->logger->info( 'Creating Community Post for product', array( 'product_id' => $product_id ) );

		// 取得 workflow_id（優先從 hook 參數取得，否則從 meta 取得）
		if ( empty( $workflow_id ) ) {
			$workflow_id = get_post_meta( $product_id, '_buygo_workflow_id', true );
		}
		
		// 如果還是沒有，嘗試從 product_data 中取得
		if ( empty( $workflow_id ) && isset( $product_data['workflow_id'] ) ) {
			$workflow_id = $product_data['workflow_id'];
		}

		try {
			// 決定發布的 Space ID
			// 優先順序：產品 Meta > 賣家映射 > Global Default > Hardcoded 7
			
			// 0. 取得商品作者（產品歸屬於誰，就由誰發布社群貼文）
			$product_author_id = get_post_field( 'post_author', $product_id );
			
			// 使用產品作者作為發布者（如果上傳者是小幫手，就是小幫手自己）
			// 如果產品作者無效，則使用管理員作為後備
			$poster_user_id = $product_author_id;
			
			if ( $poster_user_id <= 0 ) {
				// 如果產品作者無效，使用管理員作為後備
				$admin_users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
				if ( ! empty( $admin_users ) ) {
					$poster_user_id = $admin_users[0]->ID;
				} else {
					$poster_user_id = 1;
				}
				
				$this->logger->warning( 'Invalid product author, using admin as fallback', array(
					'product_id' => $product_id,
					'product_author_id' => $product_author_id,
					'fallback_user_id' => $poster_user_id,
				) );
			}
			$space_id = 0;

			// 1. 檢查產品 Meta 中是否有選擇的頻道（後台選擇的頻道）
			$product_space_id = get_post_meta( $product_id, '_buygo_community_space_id', true );
			if ( ! empty( $product_space_id ) ) {
				$space_id = intval( $product_space_id );
				$this->logger->info( 'Using Space ID from product meta', array( 
					'product_id' => $product_id, 
					'space_id'  => $space_id,
				) );
			}

			// 2. 如果產品 Meta 沒有，檢查賣家映射 (Seller Mappings)
			if ( empty( $space_id ) ) {
				$mappings = get_option( 'buygo_plus_one_seller_mappings', array() );
				if ( is_array( $mappings ) && ! empty( $product_author_id ) ) {
					foreach ( $mappings as $mapping ) {
						if ( isset( $mapping['user_id'] ) && $mapping['user_id'] == $product_author_id ) {
							if ( isset( $mapping['is_active'] ) && $mapping['is_active'] && isset( $mapping['space_id'] ) ) {
								$space_id = intval( $mapping['space_id'] );
								$this->logger->info( 'Using mapped Space ID for seller', array( 
									'seller_id' => $product_author_id, 
									'space_id'  => $space_id,
								) );
								break;
							}
						}
					}
				}
			}

			// 3. 如果沒有映射，使用 Global Default (優先順序：Product Meta > Mapping > Global Default > Hardcoded 7)
			if ( empty( $space_id ) ) {
				$global_space_id = get_option( 'buygo_plus_one_default_space_id' );
				if ( ! empty( $global_space_id ) ) {
					$space_id = intval( $global_space_id );
				} else {
					$space_id = 7; // Fallback to Announcements
				}
			}

			// 4. 驗證賣家映射權限（如果產品有選擇頻道，檢查產品作者是否有權限使用該頻道）
			$product_space_id = get_post_meta( $product_id, '_buygo_community_space_id', true );
			if ( ! empty( $product_space_id ) && intval( $product_space_id ) === $space_id ) {
				// 產品明確選擇了頻道，需要驗證產品作者是否有權限使用該頻道
				if ( ! $this->can_user_use_space( $product_author_id, $space_id ) ) {
					$user = get_userdata( $product_author_id );
					$user_display = $user ? ( $user->display_name ?: $user->user_login ) : "ID: {$product_author_id}";
					throw new Exception( "產品作者「{$user_display}」沒有權限使用選擇的頻道。請聯繫管理員設定賣家映射或選擇其他頻道。" );
				}
			}

			// 5. 取得產品作者的 LINE UID（用於通知等，非發布必要條件）
			$user_to_check_id = $poster_user_id; // 預設使用產品作者
			$current_user_id = get_current_user_id();
			
			// 如果是後台建立（有登入使用者且是管理後台），則檢查當前使用者
			if ( $current_user_id > 0 && is_admin() ) {
				$user_to_check_id = $current_user_id;
			}
			
			$user_line_uid = '';
			if ( $user_to_check_id > 0 ) {
				$user_line_uid = $this->get_line_uid( $user_to_check_id );
				
				// LINE UID 是可選的（用於通知），不強制要求
				if ( ! empty( $user_line_uid ) ) {
					$this->logger->info( 'Product author has LINE UID for notifications', array(
						'user_id' => $user_to_check_id,
						'line_uid' => $user_line_uid,
					) );
				} else {
					$this->logger->info( 'Product author has no LINE UID (optional for notifications)', array(
						'user_id' => $user_to_check_id,
					) );
				}
			}

			// 取得 Space Slug (API 需要 Slug)
			$space_slug = '';
			if ( $space_id > 0 ) {
				// Namespace 修正: Space Model 位於 FluentCommunity\App\Models\Space
				if ( class_exists( '\FluentCommunity\App\Models\Space' ) ) {
					$space_obj = \FluentCommunity\App\Models\Space::find( $space_id );
					if ( $space_obj ) {
						$space_slug = $space_obj->slug;
					}
				}
			}

			if ( empty( $space_slug ) ) {
				$space_slug = 'general';
				$this->logger->warning( 'Space Slug not found, checking general' );
			}

			// 準備內容
			$name = isset($product_data['name']) ? $product_data['name'] : '新商品';
			
			// 使用 HTML 格式化內容 (參考 FluentCommunityService)
			$lines = array();
			
			// 處理圖片
			$thumbnail_id = get_post_thumbnail_id( $product_id );
			if ( $thumbnail_id ) {
				$image_url = wp_get_attachment_url( $thumbnail_id );
				if ( $image_url ) {
					$lines[] = '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $name ) . '" style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 16px;">';
				}
			}
			
			$lines[] = '🛒 ' . $name;
			
			if ( ! empty( $product_data['price'] ) ) {
				$lines[] = '💰 價格：NT$ ' . number_format( $product_data['price'] );
			}
			
			// 顯示原價（從 product_data 或 post meta 取得）
			$original_price = 0;
			if ( ! empty( $product_data['compare_price'] ) ) {
				$original_price = floatval( $product_data['compare_price'] );
			} else {
				// 嘗試從 ProductVariation 取得
				if ( class_exists( 'FluentCart\App\Models\ProductVariation' ) ) {
					$variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $product_id )->first();
					if ( $variation && ! empty( $variation->compare_price ) ) {
						$original_price = floatval( $variation->compare_price ) / 100; // FluentCart 以「分」為單位
					}
				}
			}
			if ( $original_price > 0 ) {
				$lines[] = '💵 原價：NT$ ' . number_format( $original_price );
			}
			
			if ( ! empty( $product_data['quantity'] ) ) {
				$lines[] = '📦 數量：' . $product_data['quantity'] . ' 個';
			}
			
			// 顯示分類
			if ( ! empty( $product_data['category'] ) ) {
				$lines[] = '🏷️ 分類：' . $product_data['category'];
			}
			
			// 顯示到貨時間（從 post meta 或 product_data 取得）
			$arrival_date = get_post_meta( $product_id, '_buygo_arrival_date', true );
			if ( empty( $arrival_date ) && ! empty( $product_data['arrival_date'] ) ) {
				$arrival_date = $product_data['arrival_date'];
			}
			if ( ! empty( $arrival_date ) ) {
				$lines[] = '📅 到貨：' . $arrival_date;
			}
			
			// 顯示預購時間（從 post meta 或 product_data 取得）
			$preorder_date = get_post_meta( $product_id, '_buygo_preorder_date', true );
			if ( empty( $preorder_date ) && ! empty( $product_data['preorder_date'] ) ) {
				$preorder_date = $product_data['preorder_date'];
			}
			if ( ! empty( $preorder_date ) ) {
				$lines[] = '📅 預購：' . $preorder_date;
			}
			
			if ( ! empty( $product_data['description'] ) ) {
				$lines[] = '';
				$lines[] = nl2br( $product_data['description'] );
			}

			$lines[] = '';
			$lines[] = '👇 想要購買請在下方留言 +1';
			$lines[] = '👉 +數量 可購買多個（如 +2）';

			$message_html = implode( "<br>", $lines );

			// 準備 API 請求資料
			// 加入 slug 參數以優化網址結構 (避免 img-src-xxx 這種過長的網址)
			$post_data = array(
				'message' => $message_html,
				'space'   => $space_slug,
				'slug'    => 'product-' . $product_id,
			);

			$this->logger->info( 'Calling FluentCommunity API via Internal REST', array( 
				'data' => $post_data,
				'poster_id' => $poster_user_id
			) );

			// 切換使用者為管理員
			$current_user_id = get_current_user_id();
			wp_set_current_user( $poster_user_id );

			// 執行內部 REST 請求
			$request = new \WP_REST_Request( 'POST', '/fluent-community/v2/feeds' );
			$request->set_body_params( $post_data );
			$response = rest_do_request( $request );

			// 還原使用者
			wp_set_current_user( $current_user_id );

			if ( $response->is_error() ) {
				throw new Exception( 'API Error: ' . $response->get_error_message() );
			}

			$data = $response->get_data();
			$feed = isset( $data['feed'] ) ? $data['feed'] : $data;

			if ( ! isset( $feed['id'] ) ) {
				throw new Exception( 'Invalid API response: ' . json_encode( $data ) );
			}

			$feed_id = $feed['id'];
			$feed_slug = $feed['slug'];
			$this->logger->info( 'Feed created via API', array( 'id' => $feed_id, 'slug' => $feed_slug ) );

			// 儲存 Meta 關聯
			$feed_model = \FluentCommunity\App\Models\Feed::find( $feed_id );
			if ( $feed_model ) {
				// 強制更新 Slug
				$feed_model->slug = 'product-' . $product_id;
				$feed_model->save();
				
				// 更新這行以確保使用新的 Slug
				$feed_slug = $feed_model->slug;

				if ( method_exists( $feed_model, 'updateCustomMeta' ) ) {
					$feed_model->updateCustomMeta( '_buygo_product_id', $product_id );
				}
			}

			update_post_meta( $product_id, '_buygo_community_feed_id', $feed_id );
			
			// 取得連結
			$permalink = '';
			// 因為我們剛剛改了 Slug，API 回傳的 permalink 已經過期，所以直接用 Model 的或手動組裝
			if ( $feed_model && isset( $feed_model->permalink ) ) {
				$permalink = $feed_model->permalink;
			}
			
			if ( empty( $permalink ) ) {
				$permalink = site_url( "/portal/space/{$space_slug}/post/{$feed_slug}" );
			}

			update_post_meta( $product_id, '_buygo_community_feed_url', $permalink );
			$this->logger->info( 'Feed URL saved', array( 'url' => $permalink ) );

			// 記錄流程：FluentCommunity 貼文發布成功
			if ( $workflow_id && class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
				\BuyGo\Core\Services\WorkflowLoggerHelper::update_step( $workflow_id, 'fluentcommunity_post', 'completed', [
					'product_id' => $product_id,
					'feed_id' => $feed_id,
					'line_user_id' => $line_uid,
					'workflow_type' => 'product_upload',
					'step_order' => 4,
					'message' => 'FluentCommunity 貼文發布成功，貼文連結：' . $permalink
				] );
			}

			return $permalink;

		} catch ( Exception $e ) {
			// Log error but DO NOT crash the process
			$this->logger->error( 'Failed to create community post: ' . $e->getMessage() );
			
			// 記錄流程：FluentCommunity 貼文發布失敗
			if ( $workflow_id && class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
				\BuyGo\Core\Services\WorkflowLoggerHelper::update_step( $workflow_id, 'fluentcommunity_post', 'failed', [
					'product_id' => $product_id,
					'line_user_id' => $line_uid,
					'workflow_type' => 'product_upload',
					'error' => $e->getMessage()
				] );
			}
			
			return '';
		}
	}

	/**
	 * 處理社群留言
	 *
	 * @param object $comment  留言物件.
	 * @param object $feed     貼文物件.
	 * @param array  $mentions 提及的使用者.
	 */
	public function handle_community_comment( $comment, $feed, $mentions = array() ) {
		error_log( 'MYGO: handle_community_comment called for comment ' . ( isset($comment->id) ? $comment->id : 'unknown' ) );
		$this->logger->info( 'Handling Community Comment', array( 'comment_id' => $comment->id, 'feed_id' => $feed->id ) );

		// 1. 取得留言內容
		$message_text = $comment->message ?? ''; 
		
		// 2. 檢查是否為 +1 格式
		if ( ! $this->message_parser->is_plus_one( $message_text ) ) {
			return; // 不是喊單，忽略
		}
		
		// 3. 解析喊單資訊
		$parsed = $this->message_parser->parse_plus_one( $message_text );
		$quantity = $parsed['quantity'] ?? 1;

		// 4. 取得關聯商品 ID
		$product_id = 0;
		if ( method_exists( $feed, 'getCustomMeta' ) ) {
			$product_id = $feed->getCustomMeta( '_buygo_product_id' );
		} elseif ( isset( $feed->meta['_buygo_product_id'] ) ) {
			$product_id = $feed->meta['_buygo_product_id'];
		}

		if ( empty( $product_id ) ) {
			$this->logger->warning( 'Feed has no associated product', array( 'feed_id' => $feed->id ) );
			return;
		}

		// 5. 取得使用者
		$user_id = $comment->user_id;
		if ( ! $user_id ) {
			return;
		}

		// 6. 加入購物車 (取代原本的建立訂單)
        // 使用 CartManager
        $result = $this->cart_manager->add_to_cart( $user_id, $product_id, $quantity );

		// 7. 回覆留言結果
		if ( is_wp_error( $result ) ) {
			$reply_msg_md = "❌ 加入購物車失敗：" . $result->get_error_message();
            $reply_msg_html = $reply_msg_md;
			$this->logger->error( 'Plus One Add to Cart Failed', array( 'error' => $result->get_error_message() ) );
		} else {
            // 成功加入購物車
			// 結帳頁面 URL，假設為 /checkout，如果使用者有提供特定 URL 則使用之
			$checkout_url = site_url( '/checkout' ); 
            
            // Markdown for API
			$reply_msg_md = "✅ 已將 {$quantity} 件商品加入購物車！\n[前往結帳]({$checkout_url})";
            
            // HTML for Rendered View (Clickable Link) (Bold quantity and blue link)
			// Apply styles directly or rely on theme
            $reply_msg_html = "<p>✅ 已將 <strong>{$quantity}</strong> 件商品加入購物車！</p><p><a href=\"{$checkout_url}\" target=\"_blank\">👉 點此前往結帳</a></p>";
            
			$this->logger->info( 'Plus One Added to Cart', array( 'user_id' => $user_id ) );
		}

		// 呼叫 FluentCommunity API 回覆留言
		$this->reply_to_comment( $feed->id, $comment->id, $reply_msg_md, $reply_msg_html );
	}

	/**
	 * 回覆留言
	 *
	 * @param int    $feed_id    貼文 ID.
	 * @param int    $comment_id 留言 ID (Parent).
	 * @param string $message_md Markdown 內容.
     * @param string $message_html HTML 內容 (Optional).
	 */
	private function reply_to_comment( $feed_id, $comment_id, $message_md, $message_html = null ) {
		// 檢查 FluentCommunity Comment Model
		if ( ! class_exists( 'FluentCommunity\App\Models\Comment' ) ) {
			return;
		}

        if ( empty( $message_html ) ) {
            $message_html = $message_md;
        }

		try {
			$this->logger->info('Replying to comment', [
				'feed_id' => $feed_id,
				'comment_id' => $comment_id
			]);

			$admin_user_id = 1; // Default Admin
			$admin_users = get_users( ['role' => 'administrator', 'number' => 1] );
			if ( ! empty( $admin_users ) ) {
				$admin_user_id = $admin_users[0]->ID;
			}

			$comment_data = [
				'post_id'   => $feed_id,
				'parent_id' => $comment_id,
				'user_id'   => $admin_user_id,
				'message'   => $message_md,          // Raw Text / Markdown
				'message_rendered' => $message_html, // HTML for display
				'type'      => 'comment',
				'status'    => 'published',
			];

			$comment = \FluentCommunity\App\Models\Comment::create( $comment_data );

			if ( $comment ) {
				$this->logger->info( 'Reply created successfully', ['new_comment_id' => $comment->id] );
				
				// Update Feed Comment Count
				if ( class_exists( 'FluentCommunity\App\Models\Feed' ) ) {
					$feed = \FluentCommunity\App\Models\Feed::find( $feed_id );
					if ( $feed ) {
						$feed->comments_count = $feed->comments_count + 1;
						$feed->save();
					}
				}
			}

		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to reply to comment: ' . $e->getMessage() );
		}
	}

	/**
	 * 檢查使用者是否有權限使用指定的頻道
	 *
	 * @param int $user_id 使用者 ID
	 * @param int $space_id 頻道 ID
	 * @return bool
	 */
	private function can_user_use_space( $user_id, $space_id ) {
		if ( $user_id <= 0 || $space_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// 管理員和 BuyGo 管理員可以使用任何頻道
		if ( $user->has_cap( 'administrator' ) || in_array( 'buygo_admin', (array) $user->roles, true ) ) {
			return true;
		}

		// 檢查賣家映射
		$mappings = get_option( 'buygo_plus_one_seller_mappings', array() );
		if ( is_array( $mappings ) ) {
			foreach ( $mappings as $mapping ) {
				if ( isset( $mapping['user_id'] ) && intval( $mapping['user_id'] ) === $user_id ) {
					if ( isset( $mapping['space_id'] ) && intval( $mapping['space_id'] ) === $space_id ) {
						if ( isset( $mapping['is_active'] ) && $mapping['is_active'] ) {
							return true;
						}
					}
				}
			}
		}

		// 如果沒有明確的映射，檢查是否為賣家且使用預設頻道
		if ( in_array( 'buygo_seller', (array) $user->roles, true ) ) {
			$global_space_id = get_option( 'buygo_plus_one_default_space_id' );
			if ( ! empty( $global_space_id ) && intval( $global_space_id ) === $space_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 取得使用者的 LINE UID
	 *
	 * @param int $user_id 使用者 ID
	 * @return string
	 */
	private function get_line_uid( $user_id ) {
		if ( class_exists( '\BuyGo\Core\Services\LineService' ) ) {
			try {
				$line_service = \BuyGo\Core\App::instance()->make( \BuyGo\Core\Services\LineService::class );
				return $line_service->get_line_uid( $user_id );
			} catch ( Exception $e ) {
				// 忽略錯誤，使用 fallback
			}
		}

		// Fallback: 從 user meta 取得
		return get_user_meta( $user_id, 'buygo_line_uid', true );
	}

	/**
	 * 取得頻道綁定的 LINE UID
	 * 頻道的 LINE UID 儲存在頻道的設定中，或透過賣家映射取得
	 *
	 * @param int $space_id 頻道 ID
	 * @return string
	 */
	private function get_space_line_uid( $space_id ) {
		// 方法 1: 從頻道 meta 取得
		if ( class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			try {
				$space = \FluentCommunity\App\Models\Space::find( $space_id );
				if ( $space && method_exists( $space, 'getCustomMeta' ) ) {
					$line_uid = $space->getCustomMeta( '_buygo_line_uid' );
					if ( ! empty( $line_uid ) ) {
						return $line_uid;
					}
				}
			} catch ( Exception $e ) {
				// 忽略錯誤
			}
		}

		// 方法 2: 從賣家映射取得（根據頻道 ID 找到對應的使用者，再取得 LINE UID）
		$mappings = get_option( 'buygo_plus_one_seller_mappings', array() );
		if ( is_array( $mappings ) ) {
			foreach ( $mappings as $mapping ) {
				if ( isset( $mapping['space_id'] ) && intval( $mapping['space_id'] ) === $space_id ) {
					if ( isset( $mapping['user_id'] ) && isset( $mapping['is_active'] ) && $mapping['is_active'] ) {
						$user_id = intval( $mapping['user_id'] );
						$line_uid = $this->get_line_uid( $user_id );
						if ( ! empty( $line_uid ) ) {
							return $line_uid;
						}
					}
				}
			}
		}

		return '';
	}
}
