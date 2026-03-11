@ignore
Feature: 撤銷 Google OAuth 授權

  管理員透過後台 REST API 撤銷 YouTube 的 Google OAuth 授權。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email            | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 用戶 "Admin" 已登入 WordPress 後台
    And YouTube OAuth 已授權，token 存在於 wp_options

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 撤銷後清除 OAuth Token

    Example: 成功撤銷 Google OAuth 授權
      When 管理員發送 POST /wp-json/power-funnel/revoke-google-oauth 請求
      Then 回應狀態碼應為 200
      And 回應的 code 應為 "revoke_google_oauth_success"
      And wp_option "_power_funnel_youtube_oauth_token" 應被刪除
      And YoutubeService 的 is_authorized 應為 false
