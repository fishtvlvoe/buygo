# 統一設定系統架構說明

## 問題背景

系統中存在兩個並行的設定儲存系統：

1. **舊系統**：直接使用 `get_option('mygo_line_*')` 
   - 多處直接使用，分散在各個檔案中
   - 不支援加密
   - 簡單直接，但缺乏統一管理

2. **新系統**：使用 `BuyGo_Core::settings()->get('line_*')`
   - 統一儲存在 `buygo_core_settings` option 中
   - 支援敏感資料加密（Token、Secret 等）
   - 集中管理，但需要 Core 外掛存在

## 問題

- 兩個系統並存，資料可能不一致
- 客戶可能只安裝一個外掛，兩個系統都需要支援
- 舊代碼直接使用 `get_option()`，難以統一管理

## 解決方案

### 1. 統一儲存策略

**以新系統為主，但保持向後相容**

- 主要儲存：`BuyGo_Core::settings()` (新系統)
- 備份儲存：`get_option('mygo_line_*')` (舊系統，向後相容)

### 2. 讀取策略

**優先從新系統讀取，自動遷移舊資料**

```php
// SettingsService::get_line_setting()
1. 先從新系統讀取
2. 如果新系統沒有，從舊系統讀取
3. 如果舊系統有資料，自動遷移到新系統
4. 返回資料
```

### 3. 寫入策略

**同時寫入新舊系統**

```php
// SettingsService::set_line_setting()
1. 寫入新系統（主要儲存，支援加密）
2. 同時寫入舊系統（向後相容）
```

### 4. 向後相容

- 舊代碼繼續使用 `get_option('mygo_line_*')` 仍可正常運作
- 新代碼使用 `BuyGo_Core::settings()->get_line_setting()` 獲得統一管理
- 兩個系統會自動同步

## 實作細節

### SettingsService 新增方法

```php
// 統一讀取 LINE 設定（支援新舊系統自動遷移）
public function get_line_setting($key, $default = '')

// 統一儲存 LINE 設定（同時寫入新舊系統）
public function set_line_setting($key, $value)
```

### Key 對應表

| 新系統 Key | 舊系統 Option Key |
|-----------|------------------|
| `line_channel_access_token` | `mygo_line_channel_access_token` |
| `line_channel_secret` | `mygo_line_channel_secret` |
| `line_liff_id` | `mygo_liff_id` |
| `line_login_channel_id` | `mygo_line_login_channel_id` |
| `line_login_channel_secret` | `mygo_line_login_channel_secret` |

### 加密欄位

以下欄位在新系統中會自動加密：
- `line_channel_secret`
- `line_channel_access_token`
- `line_login_channel_secret`

## 使用範例

### 讀取設定

```php
// 新方式（推薦）
$token = BuyGo_Core::settings()->get_line_setting('line_channel_access_token', '');

// 舊方式（仍可運作）
$token = get_option('mygo_line_channel_access_token', '');
```

### 儲存設定

```php
// 新方式（推薦）
BuyGo_Core::settings()->set_line_setting('line_channel_access_token', $token);

// 舊方式（仍可運作，但建議遷移到新方式）
update_option('mygo_line_channel_access_token', $token, false);
```

## 遷移建議

### 短期（向後相容）

- 保持兩個系統並存
- 新代碼使用新方式
- 舊代碼繼續運作

### 長期（逐步遷移）

1. 將所有 `get_option('mygo_line_*')` 改為使用 `BuyGo_Core::settings()->get_line_setting()`
2. 將所有 `update_option('mygo_line_*')` 改為使用 `BuyGo_Core::settings()->set_line_setting()`
3. 完成遷移後，可以考慮移除舊系統的寫入邏輯（但保留讀取以確保相容性）

## 優點

1. **統一管理**：所有設定集中在一個地方
2. **安全性**：敏感資料自動加密
3. **向後相容**：舊代碼無需修改即可運作
4. **自動遷移**：舊資料自動遷移到新系統
5. **彈性**：即使只安裝一個外掛也能運作

## 注意事項

- 如果 `BuyGo_Core` 不可用，系統會自動回退到舊系統
- 加密功能只在有新系統時生效
- 兩個系統會自動同步，但建議統一使用新方式
