# Google OAuth

## 描述
Google OAuth 2.0 授權服務。管理員透過授權流程取得 YouTube API 的存取權杖（Access Token）與刷新權杖（Refresh Token）。系統自動處理 Token 過期刷新。

## 關鍵屬性
- 外部系統，OAuth 2.0 Authorization Code Flow
- Scope: `https://www.googleapis.com/auth/youtube.readonly`
- Token 儲存於 wp_options `_power_funnel_youtube_oauth_token`
- 過期前 30 秒自動刷新
