@ignore
Feature: 查詢設定

  管理員透過 REST API 查詢所有系統設定，
  包含 LINE、YouTube API 設定與 Google OAuth 授權狀態。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email            | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 用戶 "Admin" 已登入 WordPress 後台
    And 系統中有以下 LINE 設定：
      | liff_id              | 1234567890-abcdefgh |
      | channel_id           | 1234567890          |
      | channel_secret       | secret123           |
      | channel_access_token | token123            |
    And 系統中有以下 YouTube 設定：
      | clientId     | client-id-123     |
      | clientSecret | client-secret-456 |

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 回傳所有 EOptionName 對應的設定值

    Example: 成功取得所有設定
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應狀態碼應為 200
      And 回應的 code 應為 "get_options_success"
      And 回應的 data 應包含 "line" 區段
      And 回應的 data 應包含 "youtube" 區段
      And 回應的 data 應包含 "googleOauth" 區段

  Rule: 後置（回應）- LINE 設定包含完整欄位

    Example: LINE 設定欄位正確
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應的 data.line 應包含：
        | key                  | value                       |
        | liff_id              | 1234567890-abcdefgh         |
        | channel_id           | 1234567890                  |
        | channel_secret       | secret123                   |
        | channel_access_token | token123                    |

  Rule: 後置（回應）- Google OAuth 區段包含授權狀態與授權連結

    Example: 已授權時回傳狀態
      Given YouTube OAuth 已授權
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應的 data.googleOauth.isAuthorized 應為 true
      And 回應的 data.googleOauth.authUrl 應為有效的 URL

    Example: 未授權時回傳狀態
      Given YouTube OAuth 未授權
      When 管理員發送 GET /wp-json/power-funnel/options
      Then 回應的 data.googleOauth.isAuthorized 應為 false
      And 回應的 data.googleOauth.authUrl 應為有效的 Google OAuth URL
