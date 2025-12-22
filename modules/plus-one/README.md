# BuyGo 喊單 (BuyGo Plus One)

讓賣家透過 LINE 官方帳號上傳商品資訊和圖片，自動在 FluentCart 建立商品。並整合 BuyGo Core 進行權限管理。

## 功能特色

- 透過 LINE 上傳商品圖片和資訊
- 自動建立 FluentCart 商品
- 關鍵字 "+1" 自動喊單 (新)
- 庫存管理和訂單追蹤
- 賣家權限控制
- 自動取消逾期訂單

## 系統需求

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- **BuyGo Role Permission (Core)**
- FluentCart 外掛（必須）
- FluentCommunity 外掛（必須，免費版即可）
- Nextend Social Login 外掛（建議）

## 安裝

1. 上傳外掛到 `wp-content/plugins/buygo-plus-one/` 目錄
2. 在 WordPress 後台啟用外掛
3. 確保 **BuyGo Core** 已啟用並設定完成

## 設定

### LINE Channel 設定

(請在 BuyGo Core 設定中完成)

1. 前往 BuyGo Core 設定 > LINE Messaging API
2. 填入 Channel Info
3. 設定 Webhook URL：`https://your-site.com/wp-json/buygo-plus-one/v1/webhook`

### Webhook URL

```
https://your-site.com/wp-json/buygo-plus-one/v1/webhook
```

## 使用方式

### 賣家上傳商品

1. 在 LINE 發送商品圖片
2. 發送商品資訊：

```
商品名稱
價格：350
原價：500
庫存：20
分類：服飾
到貨：01/25
預購：01/20
描述：這是商品的簡短描述
```

3. 系統自動建立商品並回傳確認訊息

### 申請成為賣家

1. 使用 LINE Login 登入網站
2. 前往賣家申請頁面
3. 填寫申請表單
4. 等待管理員審核

## 開發

### 檔案結構

```
buygo-plus-one/
├── buygo-plus-one.php       # 主外掛檔案
├── includes/
│   ├── class-activator.php      # 啟用處理
│   ├── class-deactivator.php    # 停用處理
│   ├── class-autoloader.php     # 自動載入器
│   ├── class-buygo-plus-one-loader.php  # 核心載入器
│   ├── class-logger.php         # 日誌系統
│   ├── admin/                   # 後台功能
│   ├── frontend/                # 前台功能
│   └── services/                # 核心服務
│       ├── class-webhook-handler.php
│       ├── class-message-parser.php
│       ├── class-image-uploader.php
│       ├── class-product-creator.php
│       ├── class-order-manager.php
│       └── class-role-manager.php
└── README.md
```

### 命名規範

- 函數：`buygo_plus_one_*`
- 類別：`BuyGo_Plus_One_*`
- 常數：`BUYGO_PLUS_ONE_*`
- 選項：`buygo_plus_one_*`
- 資料表：`wp_buygo_*`
- Meta 欄位：`_buygo_*`

## 授權

GPL v2 or later

## 支援

如有問題，請聯絡：support@buygo.me
