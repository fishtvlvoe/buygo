<?php
/**
 * 價格標籤處理器
 * 
 * 為 FluentCart 商品頁面添加價格標籤（原價、售價等）
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Price_Label_Handler
 */
class BuyGo_Plus_One_Price_Label_Handler {

	/**
	 * 初始化
	 */
	public function init() {
		// 在頁面底部載入腳本（確保 DOM 已載入）
		// 使用 wp_footer 而不是檢查 is_singular，因為 FluentCart 可能使用自訂模板
		add_action( 'wp_footer', array( $this, 'enqueue_scripts' ), 99 );
	}


	/**
	 * 載入 CSS 和 JavaScript
	 */
	public function enqueue_scripts() {
		// 只載入一次
		static $loaded = false;
		if ( $loaded ) {
			return;
		}
		$loaded = true;

		// 檢查頁面是否有 FluentCart 商品元素（避免在其他頁面載入）
		// 這個檢查會在 JavaScript 中進行

		// 添加內聯 CSS
		$css = '
		<style id="buygo-price-labels-css">
		.buygo-price-label {
			font-size: 0.9em;
			color: #666;
			margin-right: 5px;
		}
		.buygo-sale-label {
			font-size: 0.9em;
			color: #666;
			margin-right: 5px;
			margin-left: 15px;
		}
		.buygo-original-label {
			font-size: 0.9em;
			color: #999;
			margin-right: 5px;
		}
		/* 隱藏價格中的 .00 小數點 */
		.fct-product-item-price .buygo-sale-label + *,
		.fct-product-item-price .buygo-original-label + * {
			position: relative;
		}
		/* 隱藏多款式商品的上方價格範圍，只保留下方款式價格 */
		.fct-single-product-page:has(.fct-product-variants) .fct-price-range {
			display: none;
		}
		</style>
		';

		echo $css;

		// 添加內聯 JavaScript（用於動態價格切換）
		$js = '
		<script id="buygo-price-labels-js">
		(function() {
			// 檢查頁面是否有 FluentCart 商品元素
			function hasFluentCartProduct() {
				return document.querySelector(".fct-price-range") !== null || 
				       document.querySelector(".fct-product-item-price") !== null;
			}
			
			// 移除價格中的 .00 小數點（只針對 TWD 和 NT$ 幣別）
			function removeDecimalZeros(container) {
				if (!container) {
					return;
				}
				
				// 先處理整個容器的完整文字內容（處理價格被拆分的情況）
				const fullText = container.textContent || \'\';
				if (fullText && (fullText.includes("TWD") || fullText.includes("NT$"))) {
					// 找到所有包含價格的元素
					const priceElements = container.querySelectorAll(\'.fct-price-range, .fct-product-item-price, [role="term"], .fct-compare-price, .fct-product-price\');
					
					priceElements.forEach(function(element) {
						if (!element || element.classList.contains(\'buygo-decimal-processed\')) {
							return;
						}
						
						// 標記為已處理，避免重複處理
						element.classList.add(\'buygo-decimal-processed\');
						
						// 取得元素的完整文字內容
						const elementText = element.textContent || \'\';
						if (!elementText) {
							return;
						}
						
						// 處理 TWD 格式：TWD 1,800.00 -> TWD 1,800 或 TWD 1800.00 -> TWD 1800
						// 匹配模式：支援有千位分隔符（1,800）和沒有千位分隔符（1800）兩種格式
						// 重要：只處理文字節點，保留 HTML 結構（如 <del> 標籤）
						if (elementText.includes("TWD")) {
							// 使用 TreeWalker 來處理文字節點，保留 HTML 結構
							const textWalker = document.createTreeWalker(
								element,
								NodeFilter.SHOW_TEXT,
								null,
								false
							);
							
							while (textWalker.nextNode()) {
								const textNode = textWalker.currentNode;
								const originalText = textNode.textContent;
								if (!originalText) {
									continue;
								}
								
								// 先處理有千位分隔符的格式：TWD 1,800.00
								let newText = originalText.replace(/\bTWD\s*(\d{1,3}(?:,\d{3})+)\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "TWD $1");
								// 再處理沒有千位分隔符的格式：TWD 1800.00（只匹配至少 2 位數字的價格，避免誤匹配）
								newText = newText.replace(/\bTWD\s*(\d{2,})\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "TWD $1");
								
								if (newText !== originalText) {
									textNode.textContent = newText;
								}
							}
						}
						
						// 處理 NT$ 格式：NT$1,800.00 -> NT$1,800 或 NT$1800.00 -> NT$1800
						// 注意：在替換字串中，$ 需要轉義為 $$（因為 $1 是反向引用）
						// 匹配模式：支援有千位分隔符（1,800）和沒有千位分隔符（1800）兩種格式
						// 重要：只處理文字節點，保留 HTML 結構（如 <del> 標籤）
						if (elementText.includes("NT$")) {
							// 使用 TreeWalker 來處理文字節點，保留 HTML 結構
							const textWalker = document.createTreeWalker(
								element,
								NodeFilter.SHOW_TEXT,
								null,
								false
							);
							
							while (textWalker.nextNode()) {
								const textNode = textWalker.currentNode;
								const originalText = textNode.textContent;
								if (!originalText) {
									continue;
								}
								
								// 先處理有千位分隔符的格式：NT$1,800.00
								let newText = originalText.replace(/\bNT\$\s*(\d{1,3}(?:,\d{3})+)\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "NT$$1");
								// 再處理沒有千位分隔符的格式：NT$1800.00（只匹配至少 2 位數字的價格，避免誤匹配）
								newText = newText.replace(/\bNT\$\s*(\d{2,})\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "NT$$1");
								
								if (newText !== originalText) {
									textNode.textContent = newText;
								}
							}
						}
					});
				}
				
				// 同時也處理逐個文字節點（處理其他可能的情況）
				const walker = document.createTreeWalker(
					container,
					NodeFilter.SHOW_TEXT,
					null,
					false
				);
				
				while (walker.nextNode()) {
					const node = walker.currentNode;
					if (!node.textContent || (node.parentElement && node.parentElement.classList.contains(\'buygo-decimal-processed\'))) {
						continue;
					}
					
					// 處理 TWD 和 NT$ 幣別，移除 .00（不影響其他小數點如 .67）
					const originalText = node.textContent;
					let newText = originalText;
					
					// 處理 TWD 格式：TWD 1,800.00 -> TWD 1,800 或 TWD 1800.00 -> TWD 1800
					// 匹配模式：支援有千位分隔符（1,800）和沒有千位分隔符（1800）兩種格式
					if (originalText.includes("TWD")) {
						// 先處理有千位分隔符的格式：TWD 1,800.00
						newText = newText.replace(/\bTWD\s*(\d{1,3}(?:,\d{3})+)\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "TWD $1");
						// 再處理沒有千位分隔符的格式：TWD 1800.00（只匹配至少 2 位數字的價格，避免誤匹配）
						newText = newText.replace(/\bTWD\s*(\d{2,})\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "TWD $1");
					}
					
					// 處理 NT$ 格式：NT$1,800.00 -> NT$1,800 或 NT$1800.00 -> NT$1800
					// 注意：在替換字串中，$ 需要轉義為 $$（因為 $1 是反向引用）
					// 匹配模式：支援有千位分隔符（1,800）和沒有千位分隔符（1800）兩種格式
					if (originalText.includes("NT$")) {
						// 先處理有千位分隔符的格式：NT$1,800.00
						newText = newText.replace(/\bNT\$\s*(\d{1,3}(?:,\d{3})+)\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "NT$$1");
						// 再處理沒有千位分隔符的格式：NT$1800.00（只匹配至少 2 位數字的價格，避免誤匹配）
						newText = newText.replace(/\bNT\$\s*(\d{2,})\.00(?=\s|$|,|\)|，|：|：|\.|$)/g, "NT$$1");
					}
					
					if (originalText !== newText) {
						node.textContent = newText;
					}
					// 其他幣別（USD, JPY, KRW 等）保留所有小數點，不做任何處理
				}
			}
			
			// 處理整個頁面的所有 TWD 價格（用於購物車、結帳頁等）
			function processAllTwdPrices() {
				// 處理整個 body（包括動態載入的內容）
				removeDecimalZeros(document.body);
			}

			function addPriceLabels() {
				// 如果沒有 FluentCart 商品元素，不執行
				if (!hasFluentCartProduct()) {
					return;
				}
				
				// 檢查是否為多款式商品（有 .fct-product-variants）
				const hasVariants = document.querySelector(".fct-product-variants") !== null;
				
				// 如果是多款式商品，隱藏上方價格範圍
				if (hasVariants) {
					const priceRange = document.querySelector(".fct-price-range");
					if (priceRange) {
						priceRange.style.display = "none";
					}
				} else {
					// 單一商品：處理價格範圍（上方價格）
					const priceRange = document.querySelector(".fct-price-range");
					if (priceRange && !priceRange.classList.contains("buygo-labeled")) {
						// 檢查是否有原價（compare_price）
						const comparePrice = priceRange.querySelector(".fct-compare-price");
						const itemPrice = priceRange.querySelector(".fct-item-price");
						const hasComparePrice = comparePrice && comparePrice.textContent.trim().match(/[\d,]+/);
						
						if (hasComparePrice) {
							// 有原價：原價在前（劃線），特價在後
							// 1. 在原價前添加「原價：」標籤
							if (!comparePrice.querySelector(".buygo-original-label")) {
								const originalLabel = document.createElement("span");
								originalLabel.className = "buygo-original-label";
								originalLabel.textContent = "原價：";
								comparePrice.insertBefore(originalLabel, comparePrice.firstChild);
							}
							
							// 2. 在特價前添加「特價：」標籤
							if (itemPrice && !itemPrice.querySelector(".buygo-sale-label")) {
								const saleLabel = document.createElement("span");
								saleLabel.className = "buygo-sale-label";
								saleLabel.textContent = "特價：";
								itemPrice.insertBefore(saleLabel, itemPrice.firstChild);
							}
						} else if (itemPrice) {
							// 沒有原價，只有特價
							if (!itemPrice.querySelector(".buygo-sale-label")) {
								const saleLabel = document.createElement("span");
								saleLabel.className = "buygo-sale-label";
								saleLabel.textContent = "特價：";
								itemPrice.insertBefore(saleLabel, itemPrice.firstChild);
							}
						}
						
						// 標記已處理，避免重複處理
						priceRange.classList.add("buygo-labeled");
						
						// 移除價格中的 .00 小數點（支援 TWD 和 NT$）
						if (priceRange) {
							removeDecimalZeros(priceRange);
						}
					}
					
					// 處理單一商品的價格顯示（如果價格不在 .fct-price-range 中）
					// 有些 FluentCart 版本可能使用不同的結構（role="term"）
					const priceTerm = document.querySelector("[role=\"term\"][aria-label*=\"Original Price\"], [role=\"term\"][aria-label*=\"Price\"]");
					if (priceTerm && !priceTerm.classList.contains("buygo-labeled")) {
						removeDecimalZeros(priceTerm);
						priceTerm.classList.add("buygo-labeled");
					}
					
					// 也處理所有包含價格的元素（確保覆蓋所有情況）
					const allPriceElements = document.querySelectorAll(".fct-price-range, .fct-product-prices, [role=\"term\"]");
					allPriceElements.forEach(function(el) {
						if (!el.classList.contains("buygo-decimal-processed")) {
							removeDecimalZeros(el);
							el.classList.add("buygo-decimal-processed");
						}
					});
				}

				// 處理款式價格（下方價格）- 動態切換的價格
				const visibleItemPrice = document.querySelector(".fct-product-item-price:not(.is-hidden)");
				if (visibleItemPrice && !visibleItemPrice.classList.contains("buygo-labeled")) {
					// 先移除舊標籤（如果有的話）
					const oldLabels = visibleItemPrice.querySelectorAll(".buygo-sale-label, .buygo-original-label");
					oldLabels.forEach(function(label) {
						label.remove();
					});

					// 檢查是否有原價（compare_price，用 <del> 標籤包起來）
					const comparePrice = visibleItemPrice.querySelector(".fct-compare-price");
					const hasComparePrice = comparePrice && comparePrice.textContent.trim().match(/[\d,]+/);
					
					if (hasComparePrice) {
						// 有原價：原價在前（劃線），特價在後
						// 1. 在原價前添加「原價：」標籤
						if (!comparePrice.querySelector(".buygo-original-label")) {
							const originalLabel = document.createElement("span");
							originalLabel.className = "buygo-original-label";
							originalLabel.textContent = "原價：";
							comparePrice.insertBefore(originalLabel, comparePrice.firstChild);
						}
						
						// 2. 找到實際售價（特價）的位置，在它前面添加「特價：」標籤
						// 實際售價應該在原價元素之後的文字節點
						if (!visibleItemPrice.querySelector(".buygo-sale-label")) {
							const saleLabel = document.createElement("span");
							saleLabel.className = "buygo-sale-label";
							saleLabel.textContent = "特價：";
							
							// 找到實際售價（特價）的位置
							// FluentCart 的結構：<span class="fct-compare-price"><del>原價</del></span>特價文字
							// 所以特價是在原價元素之後的文字節點
							
							// 方法1：直接找原價元素後面的第一個文字節點
							let priceTextNode = null;
							let currentNode = comparePrice.nextSibling;
							
							// 跳過空白文字節點，找到第一個包含數字的文字節點
							while (currentNode) {
								if (currentNode.nodeType === 3) {
									// 文字節點
									if (currentNode.textContent.trim().match(/[\d,]+/)) {
										priceTextNode = currentNode;
										break;
									}
								}
								currentNode = currentNode.nextSibling;
							}
							
							// 方法2：如果找不到，遍歷所有文字節點，排除原價內的
							if (!priceTextNode) {
								const walker = document.createTreeWalker(
									visibleItemPrice,
									NodeFilter.SHOW_TEXT,
									null,
									false
								);
								while (walker.nextNode()) {
									const node = walker.currentNode;
									if (!comparePrice.contains(node) && node.textContent.trim().match(/[\d,]+/)) {
										priceTextNode = node;
										break;
									}
								}
							}
							
							if (priceTextNode && priceTextNode.parentNode) {
								priceTextNode.parentNode.insertBefore(saleLabel, priceTextNode);
							} else {
								// 如果找不到，插入到原價元素之後
								if (comparePrice.nextSibling) {
									comparePrice.parentNode.insertBefore(saleLabel, comparePrice.nextSibling);
								} else {
									comparePrice.parentNode.appendChild(saleLabel);
								}
							}
						}
						
						// 3. 移除價格中的 .00 小數點
						removeDecimalZeros(visibleItemPrice);
					} else {
						// 沒有原價，只有特價
						if (!visibleItemPrice.querySelector(".buygo-sale-label")) {
							const saleLabel = document.createElement("span");
							saleLabel.className = "buygo-sale-label";
							saleLabel.textContent = "特價：";
							
							// 找到價格文字節點
							const walker = document.createTreeWalker(
								visibleItemPrice,
								NodeFilter.SHOW_TEXT,
								null,
								false
							);
							
							let priceTextNode = null;
							while (walker.nextNode()) {
								if (walker.currentNode.textContent.trim().match(/[\d,]+/)) {
									priceTextNode = walker.currentNode;
									break;
								}
							}
							
							if (priceTextNode && priceTextNode.parentNode) {
								priceTextNode.parentNode.insertBefore(saleLabel, priceTextNode);
							} else {
								visibleItemPrice.insertBefore(saleLabel, visibleItemPrice.firstChild);
							}
						}
						
						// 移除價格中的 .00 小數點
						removeDecimalZeros(visibleItemPrice);
					}

					visibleItemPrice.classList.add("buygo-labeled");
				}
			}

			// 頁面載入後執行
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", function() {
					setTimeout(addPriceLabels, 200);
					setTimeout(processAllTwdPrices, 300);
				});
			} else {
				setTimeout(addPriceLabels, 200);
				setTimeout(processAllTwdPrices, 300);
			}

			// 監聽款式選擇變化（使用事件委派）
			document.addEventListener("change", function(e) {
				if (e.target.type === "radio" && e.target.name && e.target.name.includes("variant")) {
					setTimeout(addPriceLabels, 200);
				}
			});

			// 使用 MutationObserver 監聽價格元素變化
			const observer = new MutationObserver(function(mutations) {
				let shouldUpdate = false;
				let shouldProcessPrices = false;
				
				mutations.forEach(function(mutation) {
					if (mutation.type === "attributes" && mutation.attributeName === "class") {
						const target = mutation.target;
						if (target.classList.contains("fct-product-item-price")) {
							shouldUpdate = true;
						}
					}
					
					// 監聽新增的節點（購物車、結帳頁動態載入的內容）
					if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
						mutation.addedNodes.forEach(function(node) {
							if (node.nodeType === 1) {
								// 檢查是否為購物車或結帳相關元素
								if (node.classList && (
									node.classList.contains("fct-cart-item") ||
									node.classList.contains("fct-cart-drawer") ||
									node.classList.contains("fct-checkout") ||
									node.querySelector && (
										node.querySelector(".fct-cart-item") ||
										node.querySelector(".fct-line-item-total") ||
										node.querySelector("[data-fluent-cart-cart-list-item-price]") ||
										node.querySelector("[data-fluent-cart-cart-list-item-total-price]")
									)
								)) {
									shouldProcessPrices = true;
								}
							}
						});
					}
				});
				
				if (shouldUpdate) {
					setTimeout(addPriceLabels, 200);
				}
				
				if (shouldProcessPrices) {
					setTimeout(processAllTwdPrices, 200);
				}
			});

			// 觀察整個 body（包括購物車、結帳頁等動態內容）
			if (document.body) {
				observer.observe(document.body, {
					attributes: true,
					attributeFilter: ["class"],
					childList: true,
					subtree: true
				});
			}
			
			// 也觀察價格容器（產品頁）
			const priceContainer = document.querySelector(".fct-product-summary");
			if (priceContainer) {
				observer.observe(priceContainer, {
					attributes: true,
					attributeFilter: ["class"],
					subtree: true
				});
			}
			
			// 監聽購物車抽屜開啟事件（FluentCart 可能使用自訂事件）
			document.addEventListener("click", function(e) {
				// 當點擊購物車相關按鈕時，延遲處理價格
				if (e.target.closest("[data-fluent-cart-cart-toggle]") || 
				    e.target.closest(".fct-cart-drawer") ||
				    e.target.closest("[data-fluent-cart-cart-button]")) {
					setTimeout(processAllTwdPrices, 500);
				}
			});
		})();
		</script>
		';

		echo $js;
	}
}
