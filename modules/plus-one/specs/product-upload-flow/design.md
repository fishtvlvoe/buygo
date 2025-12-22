# 產品上架流程 (Flex Message) 技術設計

## 系統架構

```mermaid
graph TD
    User[供應商] -->|1. 傳送圖片| LinePlatform[LINE Platform]
    LinePlatform -->|2. Webhook Event (Image)| BuyGo[BuyGo Plugin Webhook]
    
    BuyGo -->|3. 分析事件類型| Logic{是圖片?}
    Logic -->|Yes| ReplyFlex[4. Reply Flex Message (Token A)]
    Logic -->|No| Ignore[忽略或原流程]
    
    ReplyFlex -->|5. 顯示卡片| User
    
    User -->|6. 點擊'單一商品'| LinePlatform
    LinePlatform -->|7. Webhook Event (Text)| BuyGo
    
    BuyGo -->|8. 分析關鍵字| Keyword{是模板關鍵字?}
    Keyword -->|Yes| ReplyTemplate[9. Reply Text Template (Token B)]
    
    ReplyTemplate -->|10. 顯示模板| User
```

## 核心技術邏輯 (The "Free Loop")

重點在於每次使用者互動，我們都拿到一個新的 `replyToken`。

1.  **Event: Message (Image)**
    *   取得 `replyToken` (Token A)。
    *   呼叫 `replyMessage(Token A, FlexMessageObject)`。
    *   **Cost: 0**。

2.  **Flex Message Button Action**
    *   使用 `message` action type。
    *   `label`: "單一商品"
    *   `text`: "請給我單一商品上架模板" (這是使用者實際發出的字)。

3.  **Event: Message (Text)**
    *   偵測文字內容。
    *   取得 `replyToken` (Token B)。
    *   呼叫 `replyMessage(Token B, TextMessageObject)`。
    *   **Cost: 0**。

## Flex Message 結構 (JSON 示意)

```json
{
  "type": "bubble",
  "hero": {
    "type": "image",
    "url": "https://buygo.com/assets/upload-header.png", // 預留或生成
    "size": "full",
    "aspectRatio": "20:13",
    "aspectMode": "cover"
  },
  "body": {
    "type": "box",
    "layout": "vertical",
    "contents": [
      {
        "type": "text",
        "text": "圖片已收到！",
        "weight": "bold",
        "size": "xl"
      },
      {
        "type": "text",
        "text": "請選擇您要使用的上架格式：",
        "wrap": true,
        "color": "#666666",
        "size": "sm"
      }
    ]
  },
  "footer": {
    "type": "box",
    "layout": "vertical",
    "spacing": "sm",
    "contents": [
      {
        "type": "button",
        "style": "primary",
        "color": "#111827", // BuyGo Black
        "action": {
          "type": "message",
          "label": "單一商品模板",
          "text": "指令：單一商品模板"
        }
      },
      {
        "type": "button",
        "style": "secondary",
        "action": {
          "type": "message",
          "label": "多樣商品模板",
          "text": "指令：多樣商品模板"
        }
      },
       {
        "type": "button",
        "style": "link",
        "action": {
          "type": "message",
          "label": "真人客服",
          "text": "指令：真人客服"
        }
      }
    ]
  }
}
```

## 檔案變更
1.  **`app/Api/LineService.php`** (或其他處理 Webhook 的核心類別):
    *   新增 `handleImageMessage()` 方法。
    *   新增 `handleKeywordMessage()` 方法（擴充既有的關鍵字邏輯）。
2.  **`app/Templates/LineFlexTemplates.php`** (新檔案):
    *   封裝 Flex Message JSON 建構邏輯，避免 Controller 髒亂。
