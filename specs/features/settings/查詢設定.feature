@ignore @query
Feature: 查詢設定

  管理員查詢系統的 LINE、YouTube API 設定，
  以及 Google OAuth 的授權狀態與授權 URL。

  Background:
    Given 管理員已登入 WordPress 後台

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 回傳所有 EOptionName 對應的設定值

    Example: 查詢所有設定
      Given LINE 設定已儲存：
        | key                  | value                     |
        | liff_id              | 1234                      |
        | liff_url             | https://liff.line.me/1234 |
        | channel_id           | CH01                      |
        | channel_secret       | SEC01                     |
        | channel_access_token | TOKEN01                   |
      And YouTube 設定已儲存：
        | key          | value      |
        | clientId     | GCID01     |
        | clientSecret | GCSECRET01 |
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應 code 為 "get_options_success"
      And 回應 data.line 應包含上述 LINE 設定
      And 回應 data.youtube 應包含上述 YouTube 設定

  Rule: 後置（回應）- Google OAuth 區段包含授權狀態與授權 URL

    Example: 已授權時回傳 isAuthorized 為 true
      Given YoutubeService 已完成 OAuth 授權
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應 data.googleOauth.isAuthorized 應為 true
      And 回應 data.googleOauth.authUrl 應為 Google OAuth 授權 URL

    Example: 未授權時回傳 isAuthorized 為 false
      Given YoutubeService 尚未授權
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應 data.googleOauth.isAuthorized 應為 false
      And 回應 data.googleOauth.authUrl 應為 Google OAuth 授權 URL
