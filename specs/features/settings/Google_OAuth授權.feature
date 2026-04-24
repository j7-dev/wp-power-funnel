@ignore @command
Feature: Google OAuth 授權與撤銷

  管理員透過 Google OAuth 2.0 流程授權 YouTube Data API 存取權限。
  授權成功後系統可自動抓取 YouTube 直播場次。
  管理員也可撤銷授權以解除綁定。

  Background:
    Given 管理員已登入 WordPress 後台
    And YouTube 設定已填寫 clientId 和 clientSecret

  # ========== OAuth 授權流程 ==========

  Rule: 後置（狀態）- OAuth 回調成功時儲存 Token

    Example: 管理員完成 Google OAuth 授權
      Given 管理員瀏覽器帶有 query params：
        | key   | value                                            |
        | scope | https://www.googleapis.com/auth/youtube.readonly  |
        | code  | AUTH_CODE_001                                     |
      When WordPress 初始化 YoutubeService
      Then 系統使用授權碼向 Google Token 端點交換 Access Token
      And wp_option "_power_funnel_youtube_oauth_token" 應包含 access_token 和 refresh_token
      And YoutubeService 的 is_authorized 應為 true

  Rule: 後置（狀態）- Token 過期時自動刷新

    Example: Access Token 已過期但有 Refresh Token
      Given wp_option "_power_funnel_youtube_oauth_token" 的 access_token 已過期
      And wp_option 包含有效的 refresh_token
      When WordPress 初始化 YoutubeService
      Then 系統使用 refresh_token 向 Google Token 端點刷新 Access Token
      And wp_option "_power_funnel_youtube_oauth_token" 的 access_token 應被更新
      And 原有的 refresh_token 應被保留

  Rule: 前置（狀態）- Token 過期且無 Refresh Token 時標記未授權

    Example: Token 過期且無 Refresh Token
      Given wp_option "_power_funnel_youtube_oauth_token" 的 access_token 已過期
      And wp_option 不包含 refresh_token
      When WordPress 初始化 YoutubeService
      Then YoutubeService 的 is_authorized 應為 false

  # ========== 撤銷授權 ==========

  Rule: 後置（狀態）- 撤銷 Google OAuth 清除 Token

    Example: 管理員撤銷 Google OAuth 授權
      Given YoutubeService 已授權
      When 管理員發送 POST /wp-json/power-funnel/revoke-google-oauth
      Then wp_option "_power_funnel_youtube_oauth_token" 應被刪除
      And YoutubeService 的 is_authorized 應為 false
      And 回應 code 為 "revoke_google_oauth_success"
