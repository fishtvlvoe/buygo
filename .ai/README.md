# AI 工作規則 - BuyGo 專案

> **重要：任何 AI（Cursor、Antigravity、Kiro 等）開始工作前，必須先讀取此檔案。**

---

## 專案定位

**專案名稱**：BuyGo WordPress 外掛
**角色定義**：外掛開發工程師
**技術棧**：Vue 3 + Vite + Tailwind CSS（前端）、Laravel 風格 WordPress 外掛（後端）
**知識庫位置**：`/Users/fishtv/Desktop/老魚工作流/00_系統核心/知識庫/`

---

## 核心開發原則

### 0. 強制性除錯規範 ⚠️

**血淚教訓：2025-12-22 我們花了 3 個多小時解決檔案太大的問題，只因為前端沒有顯示具體錯誤訊息。**

**所有功能開發都必須包含完整的除錯機制，沒有例外：**

⇨ 前端必須顯示具體錯誤訊息，不能只顯示 HTTP 狀態碼
⇨ 前端必須有除錯面板或按鈕，錯誤訊息不能自動消失
⇨ 後端必須記錄詳細的處理過程和錯誤資訊
⇨ 管理後台必須有除錯記錄查看頁面
⇨ 手機環境的除錯功能必須正常工作

**詳細規範請參考：`🐟 老魚工作流/.ai/MANDATORY_DEBUG_RULES.md`**

**違反此規範的開發視為不合格，必須重做。**

### 1. 規格先行 (Specs First)
- 任何超過 2 小時工時的任務，必須先撰寫 `specs/{功能名稱}/` (Requirements -> Design -> Tasks)
- AI 代理必須依據 Tasks 執行，不可擅自脫稿演出

### 2. 不重複造輪子 (Don't Repeat Yourself)
- 開發新功能前，必須先查閱 `/Users/fishtv/Local Sites/buygo/app/public/IDE_doc/設計藍圖_UI_UX/` 是否有現成元件
- 優先使用共用服務 (Services) 而非直接 SQL 查詢

### 3. 程式碼即文件 (Code as Documentation)
- 核心邏輯必須寫在程式碼註解 (JSDoc/PHPDoc) 中
- 外部文件 (.md) 僅記錄「架構決策 (Why)」與「使用總則 (How)」

---

## 技術堆疊規範

### 後台介面 (Admin UI)
- **必須** 使用 Vue.js 3 (SPA) 進行開發
- **禁止** 使用傳統 PHP (`WP_List_Table`, `include template.php`) 輸出 HTML 介面
- **樣式標準**：必須使用 Tailwind CSS，嚴禁手寫 `admin-ui.css` 或 `style.css`
- **資料獲取**：前端僅透過 REST API 獲取 JSON 資料

### 後端邏輯 (Backend)
- **職責單一**：PHP 僅負責處理資料邏輯與提供 REST API endpoints
- **API First**：在開發 UI 前，必須先定義並實作 API

---

## 開發流程

### 開發三部曲
1. **討論與定規**：產出 Spec 文件（requirements.md、design.md、tasks.md）
2. **沙盒測試**：撰寫獨立的 PHP 測試腳本，在不影響正式資料的前提下進行邏輯驗證
3. **正式上架**：沙盒測試通過後，才將代碼整合進正式外掛

### 5 次原則
- 執行某個特定任務遇到錯誤或卡住時，最多嘗試 5 次
- 超過 5 次失敗，必須立刻停止操作並向老闆回報

---

## 工作方法

所有任務都必須遵循：
1. 五階段確認流程（接收與複述 → 確認核心目的 → 預判風險與邊界 → 優化建議 → 最終授權）
2. 第一性原理分析（問題本質、約束條件、最簡方案）
3. WBS 工作分解（拆解成可執行、可驗收的子任務）
4. 卡片盒筆記記錄（記錄知識點、決策、發現）
5. 流程圖與 Spec（超過 2 小時工時的任務，在最終授權前必須完成）

### 工作流整合規範
- **所有工作記錄必須同步到老魚工作流知識庫**：`🐟 老魚工作流/01_知識庫/4-BuyGo外掛/`
- **完成階段性任務後建立覆盤檔案**：存放到 `🐟 老魚工作流/04_歸檔區/`
- **遵循卡片盒筆記分類**：concepts、decisions、discoveries、tasks
- **跨專案工作時保持文件同步**：技術實作在 BuyGo 專案，記錄歸檔在老魚工作流

詳細規則請參考：`/Users/fishtv/Desktop/老魚工作流/00_系統核心/知識庫/` 下的相關卡片。

---

## 專案文件位置

### 第三方文件（API 文件、開發者文件）
所有第三方文件都存放在 `app/public/IDE_doc/` 目錄下：
- Fluent 系列：`app/public/IDE_doc/fluentcart.com_doc/`、`fluentcrm.com_doc/` 等
- LINE API：`app/public/IDE_doc/LINE_doc/`
- 其他整合：ECPay、ezPay Invoice、SHOPLINE 等

### 專案設計規範
- UI/UX 設計系統：`app/public/IDE_doc/設計藍圖_UI_UX/`
- 核心開發協議：`app/public/IDE_doc/核心開發協議 /`
- 功能規格文件：`app/public/IDE_doc/specs/`

詳細文件位置請參考：`/Users/fishtv/Local Sites/buygo/CLAUDE.md`

---

## 參考文件

以下檔案存放在 `.ai/` 資料夾中，作為詳細規則的參考文件：

- `0-DEVELOPMENT_SOP.mdc`：開發流程與協作標準作業程序（開發三部曲、5 次原則）
- `1-Vue.js3.mdc`：前端技術棧規範（Vue 3 + Vite + Tailwind CSS）
- `2-CORE_PROTOCOL.mdc`：核心開發協議（規格撰寫標準、技術堆疊規範）
- `3-溝通討論模式.mdc`：五階段確認流程
- `4-SMART_SELECTOR.mdc`：智慧搜尋選人模組使用指南
- `5-卡片盒WBS第一性原理工作法.mdc`：統一工作方法（第一性原理、WBS、卡片盒筆記法）
- `8-YT_SUBTITLE_FETCHER.mdc`：YouTube 字幕自動抓取協定
- `PLUS_ONE_MODULE_SYNC.md`：**⚠️ Plus One 模組同步修改指南（重要！）**
- `工作流檢查清單模板.md`：工作流檢查清單模板

**使用方式**：當需要查閱詳細規則時，可以參考這些檔案。日常工作中，優先遵循本 README.md 的規則即可。

---

## 版本資訊

- **建立日期**：2025-12-21
- **最後更新**：2025-12-21
- **負責人**：老魚
