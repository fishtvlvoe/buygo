# 商品作者驗證測試指南

## 問題說明

在開發過程中，我們發現透過 LINE Bot 上傳的商品和圖片可能出現 `post_author = 0` 的問題，導致：

1. 無法正確識別商品賣家
2. 訂單通知系統無法找到賣家的 LINE UID
3. 賣家無法收到訂單通知

## 已修正的問題

### 1. 商品建立時的 post_author

**檔案：** `includes/services/class-product-creator.php`

**修正內容：**
- 在 `create_post()` 方法中，驗證 `user_id` 是否有效
- 如果 `user_id` 為 0 或無效，使用預設管理員 ID 並記錄警告
- 確保所有商品都有有效的 `post_author`

### 2. 圖片上傳時的 post_author

**檔案：** `includes/services/class-image-uploader.php`

**修正內容：**
- 在 `upload_to_wordpress()` 方法中，驗證 `user_id` 是否有效
- 在 `wp_insert_attachment()` 時設定 `post_author`
- 如果 `user_id` 為 0 或無效，使用預設管理員 ID 並記錄警告

### 3. Webhook Handler 傳遞 user_id

**檔案：** `includes/services/class-webhook-handler.php`

**修正內容：**
- 在建立商品前，將 `$user->ID` 加入到 `$product_data['user_id']`
- 確保 `Product_Creator` 能取得正確的使用者 ID

## 測試腳本使用方式

### 前置條件

1. 確保 Local 環境已啟動
2. 確保已透過 LINE Bot 上傳至少一個商品
3. 確保已建立至少一個訂單（選用，用於測試訂單通知）

### 執行測試

#### 方法 1：使用命令列（推薦）

```bash
cd /Users/fishtv/Local\ Sites/buygo/app/public/wp-content/plugins/buygo-line-fluentcart
php test-product-author-verification.php
```

#### 方法 2：透過 WordPress

如果無法使用命令列，可以建立一個臨時的 WordPress 頁面或使用 WP-CLI：

```bash
wp eval-file test-product-author-verification.php
```

### 測試內容

測試腳本會檢查以下項目：

1. **最近建立的商品 post_author**
   - 檢查最近 10 個商品的 `post_author` 是否有效
   - 檢查商品作者是否有 LINE UID

2. **最近上傳的圖片附件 post_author**
   - 檢查最近 10 個圖片附件的 `post_author` 是否有效
   - 只檢查 LINE 上傳的圖片（檔名包含 'line-product-'）

3. **最近訂單的賣家 LINE UID**
   - 檢查最近 10 個訂單
   - 驗證能否從商品作者找到賣家的 LINE UID

4. **特定商品的完整資訊**
   - 顯示最近 3 個商品的詳細資訊
   - 包括商品作者、LINE UID、特色圖片等

### 測試結果說明

#### ✅ 通過

所有測試通過，表示：
- 所有商品的 `post_author` 都有效
- 所有圖片附件的 `post_author` 都有效
- 所有訂單都能找到賣家的 LINE UID

#### ❌ 失敗

如果發現問題，會顯示：
- 哪些商品的 `post_author` 無效
- 哪些圖片附件的 `post_author` 無效
- 哪些訂單無法找到賣家 LINE UID

#### ⚠️ 警告

警告訊息包括：
- 沒有找到任何商品/訂單（需要先上傳商品或建立訂單）
- FluentCart 未安裝或未啟用

## 測試流程建議

### 完整測試流程

1. **清理測試環境**（選用）
   ```sql
   -- 刪除測試商品（請謹慎使用）
   DELETE FROM wp_posts WHERE post_type = 'fluent-products' AND post_author = 0;
   ```

2. **透過 LINE Bot 上傳商品**
   - 發送圖片到 LINE Bot
   - 發送商品資訊（名稱、價格、數量等）

3. **執行測試腳本**
   ```bash
   php test-product-author-verification.php
   ```

4. **檢查測試結果**
   - 確認所有測試通過
   - 如果有錯誤，查看錯誤訊息

5. **建立訂單測試**
   - 在 FluentCart 建立訂單
   - 再次執行測試腳本
   - 確認訂單通知功能正常

6. **檢查日誌**
   - 查看 `wp-content/buygo-line-fc.log`
   - 確認沒有警告訊息

## 常見問題

### Q: 測試腳本顯示「沒有找到任何商品」

**A:** 請先透過 LINE Bot 上傳商品，然後再執行測試腳本。

### Q: 測試腳本顯示「FluentCart 未安裝或未啟用」

**A:** 請確認 FluentCart 外掛已安裝並啟用。

### Q: 測試腳本顯示某些商品的 post_author 是 0

**A:** 這些是修正前的舊商品。可以：
1. 使用後台修復工具（如果有的話）
2. 手動更新這些商品的 `post_author`
3. 刪除這些商品並重新上傳

### Q: 如何修復舊商品的 post_author？

**A:** 可以使用以下 SQL 查詢找到需要修復的商品：

```sql
-- 找出 post_author = 0 的商品
SELECT ID, post_title, post_author, post_date 
FROM wp_posts 
WHERE post_type = 'fluent-products' 
AND post_author = 0;
```

然後手動更新或使用修復工具。

## 預防措施

為了避免未來再次出現這個問題：

1. **定期執行測試腳本**
   - 每次部署後執行
   - 每次修改相關程式碼後執行

2. **監控日誌**
   - 定期檢查 `wp-content/buygo-line-fc.log`
   - 注意警告訊息

3. **程式碼審查**
   - 確保所有建立 Post 或 Attachment 的地方都設定了 `post_author`
   - 確保所有 `user_id` 都有驗證邏輯

## 相關檔案

- `test-product-author-verification.php` - 測試腳本
- `includes/services/class-product-creator.php` - 商品建立器
- `includes/services/class-image-uploader.php` - 圖片上傳器
- `includes/services/class-webhook-handler.php` - Webhook 處理器
- `wp-content/buygo-line-fc.log` - 日誌檔案

## 更新記錄

- **2025-01-XX**: 建立測試腳本和修正 post_author 問題
- **2025-01-XX**: 修正圖片上傳時的 post_author 問題
