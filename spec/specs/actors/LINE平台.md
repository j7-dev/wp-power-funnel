# LINE 平台

## 描述
LINE Messaging API 與 Webhook 系統。當用戶在 LINE 內與官方帳號互動（發送訊息、點擊 Postback 按鈕等），LINE 平台會將事件以 Webhook 形式傳送至 WordPress 端點 `/wp-json/power-funnel/line-callback`。

## 關鍵屬性
- 外部系統，透過 HTTP Webhook 通訊
- 使用 `X-Line-Signature` header 進行簽章驗證
- 事件類型包含：MessageEvent、PostbackEvent 等
