<?php
/**
 * 商品建立器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Product_Creator
 */
class BuyGo_Plus_One_Product_Creator {

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
	 * 建立商品
	 *
	 * @param array $product_data 商品資料（必須包含 'user_id' 欄位）.
	 * @param array $image_ids 圖片 ID 陣列.
	 * @return int|WP_Error 商品 ID 或錯誤.
	 */
	public function create( $product_data, $image_ids = array() ) {
		global $wpdb;

		// 將 image_ids 加入 product_data，供後續使用
		if ( ! empty( $image_ids ) ) {
			$product_data['image_ids'] = $image_ids;
		}

		// 記錄 user_id 資訊
		$user_id = isset( $product_data['user_id'] ) ? intval( $product_data['user_id'] ) : null;

		$this->logger->info(
			'Starting product creation',
			array(
				'product_name' => $product_data['name'] ?? '',
				'image_count'  => count( $image_ids ),
				'user_id'      => $user_id,
			)
		);

		// 驗證 user_id 是否在 product_data 中
		if ( ! isset( $product_data['user_id'] ) || empty( $product_data['user_id'] ) ) {
			$this->logger->warning(
				'user_id not found in product_data',
				array(
					'product_data_keys' => array_keys( $product_data ),
				)
			);
		}

		// 開始資料庫交易.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. 建立 WordPress Post.
			$post_id = $this->create_post( $product_data );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( $post_id->get_error_message() );
			}

			// 2. 設定特色圖片.
			if ( ! empty( $image_ids ) ) {
				set_post_thumbnail( $post_id, $image_ids[0] );
				$this->logger->debug( 'Featured image set', array( 'attachment_id' => $image_ids[0] ) );
			}

		// 3. 設定分類.
		$this->set_category( $post_id, $product_data );

		// 4. 分流點：判斷是否為多款式商品
		if ( ! empty( $product_data['is_variable'] ) && $product_data['is_variable'] ) {
			// [新軌道] 執行變化商品建立邏輯
			$this->create_variable_product_logic( $post_id, $product_data );
		} else {
			// [舊軌道] 執行原本的單一商品建立邏輯
			$this->create_product_detail( $post_id, $product_data );
			$this->create_product_variation( $post_id, $product_data );
		}

		// 5. 儲存自訂欄位.
		$this->save_custom_fields( $post_id, $product_data );

			// 提交交易.
			$wpdb->query( 'COMMIT' );

			$this->logger->info(
				'Product created successfully',
				array(
					'post_id'      => $post_id,
					'product_name' => $product_data['name'],
				)
			);

			// 取得 workflow_id 和 line_uid（從 product_data 中）
			$workflow_id = isset( $product_data['workflow_id'] ) ? $product_data['workflow_id'] : '';
			$line_uid = isset( $product_data['line_uid'] ) ? $product_data['line_uid'] : ( isset( $product_data['line_user_id'] ) ? $product_data['line_user_id'] : null );
			
			// 儲存 workflow_id 到商品 meta
			if ( ! empty( $workflow_id ) ) {
				update_post_meta( $post_id, '_buygo_workflow_id', $workflow_id );
				
				// 記錄流程：開始 FluentCommunity 貼文發布
				if ( class_exists( '\BuyGo\Core\Services\WorkflowLoggerHelper' ) ) {
					\BuyGo\Core\Services\WorkflowLoggerHelper::log_step( $workflow_id, 'fluentcommunity_post', 4, 'processing', [
						'product_id' => $post_id,
						'line_user_id' => $line_uid,
						'workflow_type' => 'product_upload'
					] );
				}
			}
			
			// 觸發 Hook（傳遞 workflow_id 以便後續步驟記錄）
			do_action( 'buygo_line_fc/product_created', $post_id, $product_data, $line_uid, $workflow_id );

			return $post_id;

		} catch ( Exception $e ) {
			// 回滾交易.
			$wpdb->query( 'ROLLBACK' );

			$this->logger->error(
				'Product creation failed',
				array(
					'error'   => $e->getMessage(),
					'product' => $product_data,
				)
			);

			return new WP_Error( 'creation_failed', $e->getMessage() );
		}
	}

	/**
	 * 建立 WordPress Post
	 *
	 * @param array $product_data 商品資料.
	 * @return int|WP_Error Post ID 或錯誤.
	 */
	private function create_post( $product_data ) {
		$description = $product_data['description'] ?? '';

		// 加入自訂欄位資訊到描述.
		$custom_info = array();

		if ( ! empty( $product_data['arrival_date'] ) ) {
			$custom_info[] = __( '到貨日期：', 'buygo-plus-one' ) . gmdate( 'm/d', strtotime( $product_data['arrival_date'] ) );
		}

		if ( ! empty( $product_data['preorder_date'] ) ) {
			$custom_info[] = __( '預購截止：', 'buygo-plus-one' ) . gmdate( 'm/d', strtotime( $product_data['preorder_date'] ) );
		}

		// 組合完整描述：先顯示自訂資訊，再顯示原始描述
		$full_description = '';
		if ( ! empty( $custom_info ) ) {
			$full_description = implode( ' | ', $custom_info );
		}
		
		if ( ! empty( $description ) ) {
			if ( ! empty( $full_description ) ) {
				$full_description .= "\n\n" . $description;
			} else {
				$full_description = $description;
			}
		}
		
		// 確保至少有內容（如果都沒有，至少顯示到貨日期）
		if ( empty( $full_description ) && ! empty( $custom_info ) ) {
			$full_description = implode( ' | ', $custom_info );
		}
		
		$description = $full_description;

		// 取得 post_author，確保不是 0.
		$user_id = isset( $product_data['user_id'] ) ? intval( $product_data['user_id'] ) : get_current_user_id();

		if ( $user_id <= 0 ) {
			// 如果 user_id 無效，使用預設管理員 ID 並記錄警告.
			$admin_users = get_users( array(
				'role'   => 'administrator',
				'number' => 1,
			) );

			if ( ! empty( $admin_users ) ) {
				$user_id = $admin_users[0]->ID;
			} else {
				$user_id = 1;
			}

			$this->logger->warning(
				'Invalid user_id provided, using default admin',
				array(
					'provided_user_id' => $product_data['user_id'] ?? null,
					'fallback_user_id' => $user_id,
				)
			);
		}

		$post_data = array(
			'post_title'   => sanitize_text_field( $product_data['name'] ),
			'post_content' => wp_kses_post( $description ),
			'post_excerpt' => wp_kses_post( $description ),
			'post_status'  => 'publish',
			'post_type'    => 'fluent-products',
			'post_author'  => $user_id,
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// 更新 post_name 為 post ID，產生簡短的 URL.
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => (string) $post_id,
			)
		);

		$this->logger->debug(
			'Post created',
			array(
				'post_id'    => $post_id,
				'post_title' => $product_data['name'],
			)
		);

		return $post_id;
	}

	/**
	 * 設定商品分類
	 *
	 * @param int   $post_id Post ID.
	 * @param array $product_data 商品資料.
	 */
	private function set_category( $post_id, $product_data ) {
		$category_name = $product_data['category'] ?? '';

		// 如果沒有指定分類，使用預設分類.
		if ( empty( $category_name ) ) {
			$category_name = get_option( 'buygo_line_fc_default_category', __( 'LINE 商品', 'buygo-plus-one' ) );
		}

		// 檢查分類是否存在.
		$term = term_exists( $category_name, 'fluent-product-category' );

		if ( ! $term ) {
			// 檢查是否允許自動建立分類.
			$auto_create = get_option( 'buygo_line_fc_auto_create_category', 'yes' );

			if ( 'yes' === $auto_create ) {
				// 自動建立分類.
				$term = wp_insert_term( $category_name, 'fluent-product-category' );

				if ( ! is_wp_error( $term ) ) {
					$this->logger->info( 'Category created', array( 'category' => $category_name ) );
				}
			} else {
				// 使用預設分類.
				$default_category = get_option( 'buygo_line_fc_default_category', __( 'LINE 商品', 'buygo-plus-one' ) );
				$term             = term_exists( $default_category, 'fluent-product-category' );

				if ( ! $term ) {
					$term = wp_insert_term( $default_category, 'fluent-product-category' );
				}

				$category_name = $default_category;
			}
		}

		// 設定分類.
		if ( ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? $term['term_id'] : $term;
			wp_set_object_terms( $post_id, array( $term_id ), 'fluent-product-category' );

			$this->logger->debug(
				'Category set',
				array(
					'post_id'  => $post_id,
					'category' => $category_name,
				)
			);
		}
	}

	/**
	 * 建立 ProductDetail
	 *
	 * @param int   $post_id Post ID.
	 * @param array $product_data 商品資料.
	 * @throws Exception 如果建立失敗.
	 */
	private function create_product_detail( $post_id, $product_data ) {
		// 檢查 FluentCart 是否已安裝.
		if ( ! class_exists( 'FluentCart\App\Models\ProductDetail' ) ) {
			throw new Exception( 'FluentCart ProductDetail model not found' );
		}

		// FluentCart 以「分」為單位儲存價格（100 分 = 1 元）.
		$price = floatval( $product_data['price'] ) * 100;

		$detail_data = array(
			'post_id'            => $post_id,
			'fulfillment_type'   => 'physical',
			'variation_type'     => 'simple',
			'manage_stock'       => 1,
			'stock_availability' => 'in-stock',
			'min_price'          => $price,
			'max_price'          => $price,
			'other_info'         => array(
				'sold_individually' => 'no', // 允許購買多個，顯示數量選擇器和加入購物車按鈕
			),
		);

		$detail = \FluentCart\App\Models\ProductDetail::create( $detail_data );

		if ( ! $detail ) {
			throw new Exception( 'Failed to create ProductDetail' );
		}

		$this->logger->debug(
			'ProductDetail created',
			array(
				'post_id' => $post_id,
				'price'   => $price,
			)
		);
	}

	/**
	 * 建立 ProductVariation
	 *
	 * @param int   $post_id Post ID.
	 * @param array $product_data 商品資料.
	 * @throws Exception 如果建立失敗.
	 */
	private function create_product_variation( $post_id, $product_data ) {
		// 檢查 FluentCart 是否已安裝.
		if ( ! class_exists( 'FluentCart\App\Models\ProductVariation' ) ) {
			throw new Exception( 'FluentCart ProductVariation model not found' );
		}

		// FluentCart 以「分」為單位儲存價格（100 分 = 1 元）.
		$price         = floatval( $product_data['price'] ) * 100;
		$compare_price = isset( $product_data['compare_price'] ) ? floatval( $product_data['compare_price'] ) * 100 : 0;
		$quantity      = isset( $product_data['quantity'] ) ? intval( $product_data['quantity'] ) : 0;

		// 設定 other_info，確保 payment_type 為 onetime（單一購買，非訂閱）
		$other_info = array(
			'payment_type' => 'onetime',
		);

		$variation_data = array(
			'post_id'          => $post_id,
			'variation_title'  => __( '預設', 'buygo-plus-one' ),
			'item_price'       => $price,
			'compare_price'    => $compare_price,
			'manage_stock'     => 1,
			'total_stock'      => $quantity,
			'available'        => $quantity,
			'stock_status'     => $quantity > 0 ? 'in-stock' : 'out-of-stock',
			'fulfillment_type' => 'physical',
			'payment_type'     => 'onetime', // 保留直接欄位設定（相容性）
			'other_info'       => $other_info, // 設定 other_info JSON 欄位
		);

		$variation = \FluentCart\App\Models\ProductVariation::create( $variation_data );

		if ( ! $variation ) {
			throw new Exception( 'Failed to create ProductVariation' );
		}

		$this->logger->debug(
			'ProductVariation created',
			array(
				'post_id'  => $post_id,
				'price'    => $price,
				'quantity' => $quantity,
			)
		);
	}

	/**
	 * 建立多款式商品邏輯（新軌道）
	 *
	 * @param int   $post_id Post ID.
	 * @param array $product_data 商品資料（必須包含 'variations' 陣列）.
	 * @throws Exception 如果建立失敗.
	 */
	private function create_variable_product_logic( $post_id, $product_data ) {
		// 檢查 FluentCart 是否已安裝.
		if ( ! class_exists( 'FluentCart\App\Models\ProductDetail' ) ) {
			throw new Exception( 'FluentCart ProductDetail model not found' );
		}

		if ( ! class_exists( 'FluentCart\App\Models\ProductVariation' ) ) {
			throw new Exception( 'FluentCart ProductVariation model not found' );
		}

		// 檢查是否有 variations 資料
		if ( empty( $product_data['variations'] ) || ! is_array( $product_data['variations'] ) ) {
			throw new Exception( 'Variations data not found in product_data' );
		}

		// FluentCart 以「分」為單位儲存價格（100 分 = 1 元）
		$price = floatval( $product_data['price'] ) * 100;

		// 1. 建立 ProductDetail（設定為 simple_variations 類型）
		$detail_data = array(
			'post_id'            => $post_id,
			'fulfillment_type'   => 'physical',
			'variation_type'     => 'simple_variations',
			'manage_stock'       => 1,
			'stock_availability' => 'in-stock',
			'min_price'          => $price,
			'max_price'          => $price,
			'other_info'         => array(
				'sold_individually' => 'no', // 允許購買多個，顯示數量選擇器和加入購物車按鈕
			),
		);

		$detail = \FluentCart\App\Models\ProductDetail::create( $detail_data );

		if ( ! $detail ) {
			throw new Exception( 'Failed to create ProductDetail for variable product' );
		}

		$this->logger->debug(
			'ProductDetail created (simple_variations)',
			array(
				'post_id' => $post_id,
				'price'   => $price,
			)
		);

		// 2. 建立多個 ProductVariation
		$default_compare_price = isset( $product_data['compare_price'] ) ? floatval( $product_data['compare_price'] ) * 100 : 0;
		$quantity      = isset( $product_data['quantity'] ) ? intval( $product_data['quantity'] ) : 0;

		// 取得圖片 ID（如果只有一張圖片，所有變體共用）
		$image_id = null;
		if ( ! empty( $product_data['image_ids'] ) && is_array( $product_data['image_ids'] ) ) {
			$image_id = intval( $product_data['image_ids'][0] );
		}

		$first_variation_id = null;
		$min_price = $price;
		$max_price = $price;

		foreach ( $product_data['variations'] as $index => $variation_data ) {
			// 取得變體標題（例如：(A) 漢頓）
			$variation_title = isset( $variation_data['variation_title'] ) 
				? $variation_data['variation_title'] 
				: sprintf( '(%s) %s', $variation_data['code'] ?? 'A', $variation_data['name'] ?? '' );

			// 如果變體有獨立的價格，使用變體的價格；否則使用主商品價格
			$variation_price = isset( $variation_data['price'] ) 
				? floatval( $variation_data['price'] ) * 100 
				: $price;

			// 如果變體有獨立的原價，使用變體的原價；否則使用主商品原價
			$variation_compare_price = isset( $variation_data['compare_price'] ) 
				? floatval( $variation_data['compare_price'] ) * 100 
				: $default_compare_price;

			// 計算最小和最大價格（用於 ProductDetail）
			if ( $variation_price < $min_price ) {
				$min_price = $variation_price;
			}
			if ( $variation_price > $max_price ) {
				$max_price = $variation_price;
			}

			// 如果變體有獨立的庫存，使用變體的庫存；否則使用主商品庫存
			$variation_quantity = isset( $variation_data['quantity'] ) 
				? intval( $variation_data['quantity'] ) 
				: $quantity;

			// 設定 other_info，包含 payment_type（FluentCart 從 other_info 讀取 payment_type）
			$other_info = array(
				'payment_type' => 'onetime',
			);

			$variation_record = array(
				'post_id'          => $post_id,
				'variation_title'  => $variation_title,
				'item_price'       => $variation_price,
				'compare_price'    => $variation_compare_price,
				'manage_stock'     => 1,
				'total_stock'      => $variation_quantity,
				'available'        => $variation_quantity,
				'stock_status'     => $variation_quantity > 0 ? 'in-stock' : 'out-of-stock',
				'fulfillment_type' => 'physical',
				'payment_type'     => 'onetime', // 保留直接欄位設定（相容性）
				'other_info'       => $other_info, // 設定 other_info JSON 欄位
				'serial_index'     => $index, // 設定順序
			);

			// 如果有圖片，設定 media_id（所有變體共用同一張圖片）
			if ( $image_id ) {
				$variation_record['media_id'] = $image_id;
			}

			$variation = \FluentCart\App\Models\ProductVariation::create( $variation_record );

			if ( ! $variation ) {
				throw new Exception( sprintf( 'Failed to create ProductVariation: %s', $variation_title ) );
			}

			// 如果有圖片，建立 ProductMeta 來儲存變體圖片
			if ( $image_id ) {
				$this->set_variation_image( $variation->id, $image_id, $variation_title );
			}

			// 記錄第一個 variation 的 ID（作為 default_variation_id）
			if ( $index === 0 ) {
				$first_variation_id = $variation->id;
			}

			$this->logger->debug(
				'ProductVariation created',
				array(
					'post_id'         => $post_id,
					'variation_id'    => $variation->id,
					'variation_title' => $variation_title,
					'price'           => $variation_price,
					'compare_price'   => $variation_compare_price,
					'quantity'        => $variation_quantity,
					'image_id'        => $image_id,
				)
			);
		}

		// 3. 更新 ProductDetail：設定 default_variation_id 和正確的價格範圍
		if ( $first_variation_id ) {
			$detail->default_variation_id = $first_variation_id;
			$detail->min_price = $min_price;
			$detail->max_price = $max_price;
			$detail->save();

			$this->logger->debug(
				'ProductDetail updated with default_variation_id and price range',
				array(
					'post_id'              => $post_id,
					'default_variation_id' => $first_variation_id,
					'min_price'            => $min_price,
					'max_price'            => $max_price,
				)
			);
		}
	}

	/**
	 * 設定變體圖片
	 *
	 * @param int    $variation_id 變體 ID
	 * @param int    $media_id 媒體 ID
	 * @param string $variation_title 變體標題
	 */
	private function set_variation_image( $variation_id, $media_id, $variation_title ) {
		global $wpdb;

		// 取得圖片詳細資訊
		$image_url = wp_get_attachment_url( $media_id );
		$image_title = get_the_title( $media_id );

		if ( ! $image_url ) {
			$this->logger->warning( 'Failed to get image URL', array( 'media_id' => $media_id ) );
			return;
		}

		// 準備 meta_value 陣列（與 FluentCart 的格式一致）
		$meta_value = array(
			array(
				'id'    => (int) $media_id,
				'title' => $image_title ?: $variation_title,
				'url'   => $image_url,
			),
		);

		// 檢查是否已存在 meta
		$existing_meta = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fct_product_meta WHERE object_id = %d AND object_type = 'product_variant_info' AND meta_key = 'product_thumbnail'",
			$variation_id
		) );

		if ( $existing_meta ) {
			// 更新現有的 meta
			$wpdb->update(
				$wpdb->prefix . 'fct_product_meta',
				array(
					'meta_value' => json_encode( $meta_value ),
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'object_id'   => $variation_id,
					'object_type' => 'product_variant_info',
					'meta_key'    => 'product_thumbnail',
				),
				array( '%s', '%s' ),
				array( '%d', '%s', '%s' )
			);
		} else {
			// 建立新的 meta
			$wpdb->insert(
				$wpdb->prefix . 'fct_product_meta',
				array(
					'object_id'   => $variation_id,
					'object_type' => 'product_variant_info',
					'meta_key'    => 'product_thumbnail',
					'meta_value'  => json_encode( $meta_value ),
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		$this->logger->debug(
			'Variation image set',
			array(
				'variation_id' => $variation_id,
				'media_id'     => $media_id,
				'image_url'    => $image_url,
			)
		);
	}

	/**
	 * 儲存自訂欄位
	 *
	 * @param int   $post_id Post ID.
	 * @param array $product_data 商品資料.
	 */
	private function save_custom_fields( $post_id, $product_data ) {
		if ( ! empty( $product_data['arrival_date'] ) ) {
			update_post_meta( $post_id, '_buygo_arrival_date', sanitize_text_field( $product_data['arrival_date'] ) );
			$this->logger->debug( 'Arrival date saved', array( 'date' => $product_data['arrival_date'] ) );
		}

		if ( ! empty( $product_data['preorder_date'] ) ) {
			update_post_meta( $post_id, '_buygo_preorder_date', sanitize_text_field( $product_data['preorder_date'] ) );
			$this->logger->debug( 'Preorder date saved', array( 'date' => $product_data['preorder_date'] ) );
		}

	}
}
