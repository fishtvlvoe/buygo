# BuyGo 角色權限管理外掛

## 開發進度

### ✅ 階段 1：基礎架構建立（已完成）

- [x] 建立主檔案 buygo-role-permission.php
- [x] 建立資料庫結構（3 個資料表）
- [x] 建立核心類別框架（7 個類別）
- [x] 建立前端資源（CSS、JS）

### ✅ 階段 2：角色管理系統（已完成）

- [x] 實作 RoleManager 核心功能
- [x] 建立 WordPress 自訂角色
- [x] 建立角色管理後台介面
- [x] 實作角色列表表格
- [x] 實作角色篩選和搜尋
- [x] 實作批次角色變更

### ✅ 階段 3：賣家申請系統（已完成）

- [x] 實作 SellerApplication 核心功能
- [x] 建立後台審核介面
- [x] 實作申請列表表格
- [x] 實作申請詳情彈窗
- [x] 實作核准/拒絕功能
- [x] 實作 AJAX 載入申請詳情

### 🔄 待完成階段

#### 階段 4：小幫手管理系統

- [ ] 建立後台小幫手管理介面
- [ ] 建立前台小幫手管理頁面（賣家用）
- [ ] 實作權限設定功能

#### 階段 5：LINE 綁定系統

- [ ] 建立前台綁定介面
- [ ] 建立後台管理介面
- [ ] 整合 LINE Bot

#### 階段 6：資料同步系統

- [ ] 完善 FluentCRM 整合
- [ ] 完善 FluentCart 整合
- [ ] 建立 FluentCRM 標籤和列表

#### 階段 7：通知系統

- [ ] 完善 Email 通知模板
- [ ] 整合 LINE Messaging API

#### 階段 8：安全性與效能

- [ ] 完善權限驗證
- [ ] 優化資料庫查詢
- [ ] 完善錯誤處理

#### 階段 9：前端資源

- [ ] 完善 CSS 樣式
- [ ] 完善 JavaScript 功能

#### 階段 10：測試與部署

- [ ] 功能測試
- [ ] 整合測試
- [ ] 撰寫文件

## 安裝說明

1. 將外掛資料夾上傳到 `wp-content/plugins/`
2. 在 WordPress 後台啟用外掛
3. 外掛會自動建立資料表和自訂角色

## 使用說明

### 後台選單

外掛啟用後，會在 WordPress 後台新增「BuyGo 角色」選單，包含以下子選單：

- **角色管理**：管理所有使用者的 BuyGo 角色
- **賣家申請**：審核賣家申請
- **小幫手管理**：管理小幫手關係
- **LINE 綁定**：查看 LINE 綁定狀態
- **設定**：外掛設定

### 角色說明

- **Admin**：管理員，擁有所有權限
- **Seller**：賣家，可以上架商品和管理訂單
- **Helper**：小幫手，協助賣家處理訂單
- **Buyer**：買家，可以購買商品

## 資料表結構

### wp_buygo_seller_applications

賣家申請記錄

### wp_buygo_helpers

小幫手關係記錄

### wp_buygo_line_bindings

LINE 綁定記錄

## 開發者資訊

### 核心 API

```php
// 角色管理
$role_manager = BuyGo_RP_Role_Manager::get_instance();
$role = $role_manager->get_user_role( $user_id );
$role_manager->set_user_role( $user_id, BUYGO_ROLE_SELLER );

// 賣家申請
$app_manager = BuyGo_RP_Seller_Application::get_instance();
$app_id = $app_manager->submit_application( $user_id, $data );
$app_manager->approve_application( $app_id, $admin_id, $note );

// 小幫手管理
$helper_manager = BuyGo_RP_Helper_Manager::get_instance();
$helper_manager->assign_helper( $seller_id, $helper_id, $permissions, $assigned_by );

// LINE 綁定
$line_binding = BuyGo_RP_Line_Binding::get_instance();
$code = $line_binding->generate_binding_code( $user_id );
$line_binding->verify_binding_code( $code, $line_uid );
```

### Hooks

```php
// 角色變更時
do_action( 'buygo_rp_role_changed', $user_id, $old_role, $new_role );

// 申請提交時
do_action( 'buygo_rp_application_submitted', $application_id, $user_id );

// 申請核准時
do_action( 'buygo_rp_application_approved', $application_id, $user_id );

// 小幫手指派時
do_action( 'buygo_rp_helper_assigned', $seller_id, $helper_id, $permissions );

// LINE 綁定完成時
do_action( 'buygo_rp_line_binding_completed', $user_id, $line_uid );
```

## 版本歷史

### 1.0.0 (2024-12-08)

- 初始版本
- 完成基礎架構
- 完成角色管理系統
- 完成賣家申請系統（後台部分）

## 授權

Copyright © 2024 BuyGo Team
