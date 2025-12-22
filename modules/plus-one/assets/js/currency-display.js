/**
 * 幣別顯示處理
 *
 * 根據商品的幣別 meta 資料修改價格顯示
 */

(function ($) {
  "use strict";

  // 當頁面載入完成後執行
  $(document).ready(function () {
    // 檢查是否為商品頁面
    if (!$("body").hasClass("single-fluent-products")) {
      return;
    }

    // 取得商品 ID
    var postId = $("body")
      .attr("class")
      .match(/postid-(\d+)/);
    if (!postId || !postId[1]) {
      return;
    }

    var productId = postId[1];

    // 透過 REST API 取得商品的幣別資訊
    $.ajax({
      url:
        buygoPlusOne.restUrl + "buygo-plus-one/v1/product-currency/" + productId,
      method: "GET",
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", buygoPlusOne.nonce);
      },
      success: function (response) {
        if (response.currency && response.currency !== "TWD") {
          updatePriceDisplay(
            response.currency,
            response.price,
            response.compare_price
          );
        }
      },
    });
  });

  /**
   * 更新價格顯示
   */
  function updatePriceDisplay(currency, price, comparePrice) {
    // 格式化價格
    var formattedPrice = formatPrice(price, currency);
    var formattedComparePrice =
      comparePrice > 0 ? formatPrice(comparePrice, currency) : "";

    // 找到價格元素並更新
    var priceElements = $(".fc-price, .fc-product-price, .price, .amount");

    priceElements.each(function () {
      var $el = $(this);

      // 如果有原價
      if (formattedComparePrice) {
        $el.html(
          "<del>" +
          formattedComparePrice +
          "</del> <ins>" +
          formattedPrice +
          "</ins>"
        );
      } else {
        $el.html(formattedPrice);
      }
    });
  }

  /**
   * 格式化價格
   */
  function formatPrice(price, currency) {
    var amount = parseFloat(price);

    switch (currency.toUpperCase()) {
      case "JPY":
        return (
          "¥" + amount.toLocaleString("ja-JP", { maximumFractionDigits: 0 })
        );

      case "USD":
        return (
          "$" +
          amount.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })
        );

      case "TWD":
      default:
        return (
          "NT$ " + amount.toLocaleString("zh-TW", { maximumFractionDigits: 0 })
        );
    }
  }
})(jQuery);
