<?php
/**
 * LINE Flex Message Templates
 *
 * @package BuyGo_LINE_FluentCart
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Line_Flex_Templates
 */
class BuyGo_Plus_One_Line_Flex_Templates {

	/**
	 * Get Product Upload Menu Flex Message
	 *
	 * @return array Flex Message Object
	 */
	public static function get_product_upload_menu() {
		return [
			"type" => "flex",
			"altText" => "收到商品圖片，請選擇上架方式",
			"contents" => [
				"type" => "bubble",
				"hero" => [
					"type" => "image",
					"url" => "https://pub-5ec21b01ebe8403c850311d4ddf55acd.r2.dev/2025/12/line-buygo-logo.png",
					"size" => "full",
					"aspectRatio" => "20:13",
					"aspectMode" => "cover",
				],
				"body" => [
					"type" => "box",
					"layout" => "vertical",
					"contents" => [
						[
							"type" => "text",
							"text" => "圖片已收到！",
							"weight" => "bold",
							"size" => "xl",
							"color" => "#111827"
						],
						[
							"type" => "text",
							"text" => "請選擇您要使用的上架格式：",
							"wrap" => true,
							"color" => "#666666",
							"size" => "sm",
							"margin" => "md"
						]
					]
				],
				"footer" => [
					"type" => "box",
					"layout" => "vertical",
					"spacing" => "sm",
					"contents" => [
						[
							"type" => "button",
							"style" => "primary",
							"color" => "#111827",
							"action" => [
								"type" => "message",
								"label" => "單一商品模板",
								"text" => "/one"
							]
						],
						[
							"type" => "button",
							"style" => "secondary",
							"color" => "#E5E7EB",
							"action" => [
								"type" => "message",
								"label" => "多樣商品模板",
								"text" => "/many"
							]
						],
						[
							"type" => "separator",
							"margin" => "md"
						],
						[
							"type" => "button",
							"style" => "link",
							"action" => [
								"type" => "message",
								"label" => "需要幫助",
								"text" => "/help"
							]
						]
					]
				]
			]
		];
	}

}
