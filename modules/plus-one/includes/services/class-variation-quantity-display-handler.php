<?php
/**
 * 款式數量限制顯示處理器
 * 
 * 為多款式商品顯示每個款式的數量限制，讓客戶清楚知道每個款式可以購買的數量
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Variation_Quantity_Display_Handler
 */
class BuyGo_Plus_One_Variation_Quantity_Display_Handler {

	/**
	 * 初始化
	 */
	public function init() {
		// 在頁面底部載入腳本（確保 DOM 已載入）
		add_action( 'wp_footer', array( $this, 'enqueue_scripts' ), 99 );
		
		// 透過 hook 在 quantity container 上添加 data-available-stock 屬性（單一產品）
		add_filter( 'fluent_cart/product/single/before_quantity_block', array( $this, 'add_quantity_data_attributes' ), 10, 2 );
	}
	
	/**
	 * 在 quantity container 上添加 data 屬性（單一產品）
	 */
	public function add_quantity_data_attributes( $args ) {
		$product = $args['product'] ?? null;
		if ( ! $product ) {
			return $args;
		}
		
		// 只在單一產品時添加
		if ( $product->detail->variation_type === 'simple' ) {
			$default_variant = $product->variants()->first();
			if ( $default_variant && $default_variant->manage_stock ) {
				// 透過 JavaScript 變數傳遞數量資訊
				$available = $default_variant->available;
				if ( $available !== null && $available !== 'unlimited' ) {
					// 將數量資訊儲存到 JavaScript 變數（在頁面載入時執行）
					add_action( 'wp_footer', function() use ( $default_variant, $available ) {
						static $outputted = false;
						if ( ! $outputted ) {
							$script = sprintf(
								'<script>if(typeof window.buygoProductStock === "undefined"){window.buygoProductStock = {};} window.buygoProductStock[%d] = %d;</script>',
								$default_variant->id,
								intval( $available )
							);
							echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$outputted = true;
						}
					}, 98 );
				}
			}
		}
		
		return $args;
	}

	/**
	 * 載入 JavaScript
	 */
	public function enqueue_scripts() {
		// 只載入一次
		static $loaded = false;
		if ( $loaded ) {
			return;
		}
		$loaded = true;

		// 添加內聯 JavaScript
		$js = '
		<script id="buygo-variation-quantity-display-js">
		(function() {
			// 檢查頁面是否有 FluentCart 商品（單一或多款式）
			function hasFluentCartProduct() {
				return document.querySelector(".fct-product-quantity-container") !== null;
			}

			// 顯示款式數量限制（支援單一產品和多款式產品）
			function displayVariationQuantityLimit() {
				if (!hasFluentCartProduct()) {
					return;
				}

				const quantityContainer = document.querySelector(".fct-product-quantity-container");
				if (!quantityContainer) {
					return;
				}

				// 檢查是否為多款式商品
				const variationType = quantityContainer.getAttribute("data-variation-type");
				const isVariableProduct = variationType === "simple_variations" || variationType === "advanced_variations";

				// 移除舊的數量限制顯示
				const oldLimit = quantityContainer.querySelector(".buygo-variation-quantity-limit");
				if (oldLimit) {
					oldLimit.remove();
				}

				// 移除標籤內的舊限制顯示
				const quantityLabel = quantityContainer.querySelector(".quantity-title");
				if (quantityLabel) {
					const labelOldLimit = quantityLabel.querySelector(".buygo-variation-quantity-limit");
					if (labelOldLimit) {
						labelOldLimit.remove();
					}
				}

				let available = null;

				if (isVariableProduct) {
					// 多款式商品：從選中的款式取得數量
					const selectedVariant = document.querySelector(".fct-product-variant-item.selected");
					if (!selectedVariant) {
						// 如果沒有選中的款式，嘗試取得第一個款式
						const firstVariant = document.querySelector(".fct-product-variant-item");
						if (firstVariant) {
							// 從第一個款式取得數量（作為預設值）
							const availableStockAttr = firstVariant.getAttribute("data-available-stock");
							if (availableStockAttr !== null && availableStockAttr !== "" && availableStockAttr !== "unlimited") {
								const parsed = parseInt(availableStockAttr, 10);
								if (!isNaN(parsed) && parsed > 0) {
									available = parsed;
								}
							}
						}
						// 如果還是沒有，就不顯示
						if (available === null) {
							return;
						}
					} else {
						// 從 FluentCart 的 data 屬性取得數量
						const availableStockAttr = selectedVariant.getAttribute("data-available-stock");
						
						// 檢查是否有庫存（不依賴 data-stock-management，直接檢查 available-stock）
						if (availableStockAttr !== null && availableStockAttr !== "" && availableStockAttr !== "unlimited") {
							const parsed = parseInt(availableStockAttr, 10);
							if (!isNaN(parsed) && parsed > 0) {
								available = parsed;
							}
						}
					}
				} else {
					// 單一產品：從 quantity container 的 data-cart-id 取得 variant ID
					const cartId = quantityContainer.getAttribute("data-cart-id");
					if (cartId) {
						// 方法1：從 JavaScript 變數取得（透過 PHP hook 設定）
						if (window.buygoProductStock && window.buygoProductStock[cartId]) {
							const stock = parseInt(window.buygoProductStock[cartId], 10);
							if (!isNaN(stock) && stock > 0) {
								available = stock;
							}
						}
						
						// 方法2：透過 FluentCart 的 JavaScript API 取得（如果方法1失敗）
						if (available === null && window.fluentCartProductData && window.fluentCartProductData.variants) {
							const variant = window.fluentCartProductData.variants.find(function(v) {
								return String(v.id) === String(cartId);
							});
							if (variant && variant.manage_stock && variant.available !== null) {
								if (variant.available !== "unlimited") {
									const parsed = parseInt(variant.available, 10);
									if (!isNaN(parsed) && parsed > 0) {
										available = parsed;
									}
								}
							}
						}
					}
				}

				// 如果有數量限制，顯示
				if (available !== null && available > 0) {
					const limitSpan = document.createElement("span");
					limitSpan.className = "buygo-variation-quantity-limit";
					limitSpan.style.cssText = "color: #666; font-size: 0.9em; margin-left: 8px;";
					limitSpan.textContent = "（限購 " + available + " 個）";
					
					// 插入到數量標籤後面
					const quantityLabel = quantityContainer.querySelector(".quantity-title");
					if (quantityLabel) {
						// 移除舊的限制顯示
						const oldLimit = quantityLabel.querySelector(".buygo-variation-quantity-limit");
						if (oldLimit) {
							oldLimit.remove();
						}
						quantityLabel.appendChild(limitSpan);
					} else {
						// 如果找不到標籤，插入到容器內
						const oldLimit = quantityContainer.querySelector(".buygo-variation-quantity-limit");
						if (oldLimit) {
							oldLimit.remove();
						}
						quantityContainer.insertBefore(limitSpan, quantityContainer.firstChild);
					}
				}
			}

			// 監聽款式選擇變化
			function setupVariationChangeListener() {
				// 監聽 radio button 變化
				document.addEventListener("change", function(e) {
					if (e.target.type === "radio" && e.target.name && e.target.name.includes("variant")) {
						setTimeout(displayVariationQuantityLimit, 300);
					}
				});

				// 使用 MutationObserver 監聽款式選擇變化
				const observer = new MutationObserver(function(mutations) {
					let shouldUpdate = false;
					mutations.forEach(function(mutation) {
						if (mutation.type === "attributes" && mutation.attributeName === "class") {
							const target = mutation.target;
							if (target.classList && target.classList.contains("fct-product-variant-item")) {
								if (target.classList.contains("selected")) {
									shouldUpdate = true;
								}
							}
						}
					});
					if (shouldUpdate) {
						setTimeout(displayVariationQuantityLimit, 300);
					}
				});

				// 觀察款式容器
				const variantContainer = document.querySelector(".fct-product-variants");
				if (variantContainer) {
					observer.observe(variantContainer, {
						attributes: true,
						attributeFilter: ["class"],
						subtree: true
					});
				}

				// 監聽 FluentCart 的款式切換事件（如果有的話）
				if (window.fluentCartEvents) {
					window.fluentCartEvents.on("variant:selected", function() {
						setTimeout(displayVariationQuantityLimit, 300);
					});
				}
			}

			// 頁面載入後執行
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", function() {
					setTimeout(function() {
						displayVariationQuantityLimit();
						setupVariationChangeListener();
					}, 300);
				});
			} else {
				setTimeout(function() {
					displayVariationQuantityLimit();
					setupVariationChangeListener();
				}, 300);
			}
		})();
		</script>
		';

		echo $js;
	}
}
