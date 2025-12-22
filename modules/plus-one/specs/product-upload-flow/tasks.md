# 產品上架流程 (Flex Message) 開發任務

## 階段 1：前置準備 & 規格 (Spec & Plan)
- [x] 建立規格目錄 `specs/product-upload-flow/`
- [x] 撰寫 `requirements.md`
- [x] 撰寫 `design.md`
- [x] 撰寫 `tasks.md`
- [x] **老闆審閱規格** (Owner Review Passed)

## 階段 2：沙盒測試 (Sandbox Testing)
- [x] **建立測試腳本 `sandbox/test-flex-json.php`**
    - [x] 用來輸出 Flex Message JSON，方便在 LINE Simulator (或 Developer Console) 預覽樣式。
    - [x] 調整顏色與排版符合 BuyGo 風格。
- [x] **建立模擬 Webhook 腳本 `sandbox/mock-webhook.php`** (Verified via code review)
    - [x] 模擬收到 Image Event，驗證程式邏輯是否正確呼叫 Reply 方法。
    - [x] 模擬收到 Text Event (關鍵字)，驗證是否回傳模板。

## 階段 3：核心功能實作 (Implementation)
- [x] **重構/確認 Webhook 入口**
    - [x] 確定目前的 `buygo-plus-one` Webhook 處理點。
- [x] **實作 Flex Message Builder**
    - [x] 建立 `includes/templates/class-line-flex-templates.php`。
    - [x] 實作 `getProductUploadMenu()` 方法。
- [x] **實作邏輯處理**
    - [x] 修改 Webhook Controller，加入 Image Message 判斷。
    - [x] 連接 Flex Message 回應。
    - [x] 加入關鍵字判斷 ("指令：單一商品模板" 等)。
    - [x] 連接文字模板回應。

## 階段 4：測試與驗收 (Test & Verify)
- [x] **工程師自測**
    - [x] 實際上傳一張圖片，確認收到卡片。
    - [x] 點擊所有按鈕，確認關鍵字觸發正確。
- [x] **老闆複測 (Owner Review)**
    - [x] 確認 UI 美感與文字語氣。
    - [x] 確認流程順暢。
- [x] **清理戰場**
    - [x] 移除 sandbox 檔案。
