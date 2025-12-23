# Plus One 模組同步修改指南

## ⚠️ 重要：雙重程式碼結構

BuyGo 插件有**兩套並行的程式碼結構**，修改功能時**必須同時檢查兩處**：

### 1. Core 模組（主要）
- 路徑：`includes/Services/`
- 用途：核心功能、基礎服務

### 2. Plus One 模組（LINE 相關）
- 路徑：`modules/plus-one/includes/services/`
- 用途：LINE 相關功能、商品上架、訊息處理

---

## 🔄 常見需要同步修改的檔案對應

| 功能 | Core 模組 | Plus One 模組 | 說明 |
|------|-----------|---------------|------|
| **訊息解析** | `ProductDataParser.php` | `class-message-parser.php` | 解析商品資訊（價格、數量、幣別等） |
| **Webhook 處理** | `LineWebhookHandler.php` | `class-webhook-handler.php` | 處理 LINE webhook、傳遞模板變數 |
| **商品建立** | `FluentCartService.php` | `class-product-creator.php` | 建立商品、儲存 meta 資料 |

---

## 📋 檢查清單：修改功能時必須確認

修改以下功能時，**必須同時檢查兩個模組**：

### ✅ Parser 相關（商品資訊解析）
- [ ] 新增欄位解析（例如：幣別、到貨日期）
- [ ] 修改解析 regex 規則
- [ ] 新增資料驗證邏輯
- [ ] 修改欄位類型轉換

**檢查檔案：**
- Core: `includes/Services/ProductDataParser.php`
- Plus One: `modules/plus-one/includes/services/class-message-parser.php`

### ✅ Webhook 相關（LINE 訊息處理）
- [ ] 新增模板變數（例如：currency_symbol）
- [ ] 修改回傳訊息格式
- [ ] 新增輔助方法（例如：getCurrencySymbol）
- [ ] 修改 template_args 傳遞

**檢查檔案：**
- Core: `includes/Services/LineWebhookHandler.php`
- Plus One: `modules/plus-one/includes/services/class-webhook-handler.php`

### ✅ 商品建立相關
- [ ] 新增商品 meta 欄位
- [ ] 修改商品建立邏輯
- [ ] 新增資料儲存

**檢查檔案：**
- Core: `includes/Services/FluentCartService.php`
- Plus One: `modules/plus-one/includes/services/class-product-creator.php`

---

## 🚨 實際案例：幣別功能修改

### 案例說明
新增多幣別支援（日幣、美金、台幣等）

### 需要修改的地方
1. **Parser 模組（兩處）**
   - Core: `ProductDataParser.php` - 新增幣別識別 regex
   - Plus One: `class-message-parser.php` - 同步新增幣別識別 regex

2. **Webhook Handler 模組（兩處）**
   - Core: `LineWebhookHandler.php` - 新增 currency、currency_symbol 變數
   - Plus One: `class-webhook-handler.php` - 同步新增變數和輔助方法

3. **Settings Controller（一處）**
   - `app/Api/SettingsController.php` - 新增變數到可用變數列表

4. **Notification Templates（一處）**
   - `app/Services/NotificationTemplates.php` - 修改模板使用新變數

### 如果忘記同步會發生什麼？
- ❌ 雲端主機用 Plus One 模組，會顯示 `{currency_symbol}` 字面文字（變數未替換）
- ❌ Parser 無法識別幣別格式，會回傳「缺少：價格」錯誤
- ❌ 功能在本機測試正常，但雲端主機失效

---

## 🛠️ 修改流程建議

### Step 1：確認影響範圍
```
修改前先問：
- 這個功能會影響 Parser 嗎？
- 這個功能會影響 Webhook Handler 嗎？
- 這個功能會影響商品建立嗎？
```

### Step 2：同時修改兩處
```
修改順序：
1. 先修改 Core 模組
2. 立即修改 Plus One 模組（複製相同邏輯）
3. 測試兩處都能正常運作
```

### Step 3：驗證
```
驗證方式：
1. grep 搜尋關鍵字，確認兩處都有修改
2. 測試本機和雲端都能正常運作
3. 檢查 log 確認沒有遺漏
```

---

## 🔍 如何快速找到需要同步的檔案

### 使用 grep 搜尋
```bash
# 搜尋特定方法或變數
grep -r "getCurrencySymbol" app/public/wp-content/plugins/buygo/

# 搜尋 template_args
grep -r "template_args" app/public/wp-content/plugins/buygo/modules/plus-one/

# 搜尋 Parser 相關
find app/public/wp-content/plugins/buygo/ -name "*parser*"
```

### 常用搜尋關鍵字
- `template_args` - 模板變數傳遞
- `parse` - 解析相關
- `currency` - 幣別相關
- `product_data` - 商品資料
- `webhook` - Webhook 處理

---

## 📝 結論

**黃金規則：**
> 修改 Parser、Webhook Handler、商品建立相關功能時，
> 必須同時檢查 Core 和 Plus One 兩個模組！

**檢查方式：**
1. 閱讀本文件確認影響範圍
2. grep 搜尋確認兩處都有修改
3. 測試本機和雲端都能正常運作

**最後更新：** 2025-12-23
