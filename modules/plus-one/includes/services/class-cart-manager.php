<?php
/**
 * 購物車管理器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Cart_Manager
 */
class BuyGo_Plus_One_Cart_Manager {

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
	 * 將商品加入使用者的購物車
	 *
	 * @param int $user_id    使用者 ID.
	 * @param int $product_id 商品 Post ID.
	 * @param int $quantity   數量.
	 * @return bool|WP_Error 成功回傳 true，失敗回傳 WP_Error.
	 */
	public function add_to_cart( $user_id, $product_id, $quantity = 1 ) {
        // Dependency Check
        if ( ! class_exists( 'FluentCart\App\Models\ProductVariation' ) ) {
            return new WP_Error( 'fluent_cart_missing', 'FluentCart not loaded' );
        }

		$this->logger->info( 'Adding to Cart', array(
			'user_id'    => $user_id,
			'product_id' => $product_id,
			'quantity'   => $quantity,
		) );

        // 1. 取得商品變體
        $variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $product_id )->first();
		if ( ! $variation ) {
			return new WP_Error( 'variation_not_found', 'Product variation not found' );
		}

        // 2. 切換使用者環境以操作購物車
        $current_user_id = get_current_user_id();
        wp_set_current_user( $user_id );

        $success = false;
        $error = null;

        try {
            // 3. 呼叫 FluentCart API 加入購物車
            // 使用 FluentCart\Api\Resource\FrontendResource\CartResource
            if ( class_exists( 'FluentCart\Api\Resource\FrontendResource\CartResource' ) ) {
                $data = array(
                    'id'       => $variation->id,
                    'quantity' => $quantity,
                );
                
                // create 方法回傳 array or WP_Error
                $response = \FluentCart\Api\Resource\FrontendResource\CartResource::create( $data );

                if ( is_wp_error( $response ) ) {
                    $error = $response;
                } elseif ( isset( $response['status'] ) && $response['status'] === 'failed' ) {
                     // check response structure from source code
                     // error response: ['data' => ['status'=>400], 'message'=>...]
                     $error = new WP_Error( 'cart_error', $response['message'] ?? 'Unknown error' );
                } else {
                    $success = true;
                }
            } else {
                $error = new WP_Error( 'api_missing', 'CartResource not found' );
            }

        } catch ( Exception $e ) {
            $error = new WP_Error( 'exception', $e->getMessage() );
        } finally {
            // 4. 還原使用者
            wp_set_current_user( $current_user_id );
        }

        if ( $success ) {
            $this->logger->info( 'Added to cart successfully' );
            return true;
        } else {
            $this->logger->error( 'Failed to add to cart', array( 'error' => $error->get_error_message() ) );
            return $error;
        }
	}
}
