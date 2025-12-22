<?php
/**
 * 幣別顯示處理類別
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Currency_Display
 */
class BuyGo_Plus_One_Currency_Display {

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
		$this->init_hooks();
	}

	/**
	 * 初始化 hooks
	 */
	private function init_hooks() {
		// 註冊 REST API 路由
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		
		// 載入前端腳本
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * 註冊 REST API 路由
	 */
	public function register_rest_routes() {
		register_rest_route(
			'buygo-plus-one/v1',
			'/product-currency/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_currency' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * 取得商品幣別資訊
	 *
	 * @param WP_REST_Request $request 請求物件
	 * @return WP_REST_Response
	 */
	public function get_product_currency( $request ) {
		$product_id = $request->get_param( 'id' );

		// 取得幣別
		$currency = get_post_meta( $product_id, '_buygo_currency', true );

		// 如果沒有設定，預設為台幣
		if ( empty( $currency ) ) {
			$currency = 'TWD';
		}

		// 取得價格資訊
		global $wpdb;
		$variation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT item_price, compare_price FROM {$wpdb->prefix}fc_product_variations WHERE post_id = %d LIMIT 1",
				$product_id
			)
		);

		$price         = $variation ? $variation->item_price / 100 : 0;
		$compare_price = $variation && $variation->compare_price ? $variation->compare_price / 100 : 0;

		return rest_ensure_response(
			array(
				'currency'      => $currency,
				'price'         => $price,
				'compare_price' => $compare_price,
			)
		);
	}

	/**
	 * 載入前端腳本
	 */
	public function enqueue_scripts() {
		// 只在商品頁面載入
		if ( ! is_singular( 'fluent-products' ) ) {
			return;
		}

		wp_enqueue_script(
			'buygo-plus-one-currency-display',
			BUYGO_PLUS_ONE_URL . 'assets/js/currency-display.js',
			array( 'jquery' ),
			BUYGO_PLUS_ONE_VERSION,
			true
		);

		wp_localize_script(
			'buygo-plus-one-currency-display',
			'buygoPlusOne',
			array(
				'restUrl' => rest_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

}

