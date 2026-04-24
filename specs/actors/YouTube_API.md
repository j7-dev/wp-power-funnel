# YouTube Data API

## 描述
Google YouTube Data API v3 的 Live Streaming API。提供 `liveBroadcasts.list` 端點，用來取得 YouTube 頻道的即將直播場次（upcoming live broadcasts）作為活動來源。

## 關鍵屬性
- 外部系統，需要 Google OAuth 2.0 授權
- 使用 Bearer Token 存取
- 取得 upcoming 直播，最多 100 筆
- 回傳 id, snippet（title, description, thumbnails, scheduledStartTime）
