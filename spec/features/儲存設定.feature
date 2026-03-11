@ignore
Feature: 儲存設定

  管理員透過後台 REST API 儲存 LINE 和 YouTube API 設定。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email            | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 用戶 "Admin" 已登入 WordPress 後台

  # ========== 前置（參數）==========

  Rule: 前置（參數）- body 必須包含對應的 EOptionName key 且值為 array

    Example: 傳入非 array 的值時該項被忽略
      When 管理員發送 POST /wp-json/power-funnel/options 請求：
        """json
        {
          "line": "not_an_array"
        }
        """
      Then 回應狀態碼應為 200
      And LINE 設定不應被更新

    Example: 傳入不存在的 key 時被忽略
      When 管理員發送 POST /wp-json/power-funnel/options 請求：
        """json
        {
          "unknownKey": {"foo": "bar"}
        }
        """
      Then 回應狀態碼應為 200
      And 不應有任何設定被更新

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功儲存 LINE 設定

    Example: 儲存 LINE Messaging API 設定
      When 管理員發送 POST /wp-json/power-funnel/options 請求：
        """json
        {
          "line": {
            "liff_id": "1234567890-abcdefgh",
            "liff_url": "https://liff.line.me/1234567890-abcdefgh",
            "channel_id": "1234567890",
            "channel_secret": "secret123",
            "channel_access_token": "token123"
          }
        }
        """
      Then 回應狀態碼應為 200
      And 回應的 code 應為 "save_options_success"
      And wp_option "_power_funnel_line_setting" 應包含：
        | key                  | value                                     |
        | liff_id              | 1234567890-abcdefgh                       |
        | channel_id           | 1234567890                                |
        | channel_secret       | secret123                                 |
        | channel_access_token | token123                                  |

  Rule: 後置（狀態）- 成功儲存 YouTube 設定

    Example: 儲存 YouTube API 設定
      When 管理員發送 POST /wp-json/power-funnel/options 請求：
        """json
        {
          "youtube": {
            "clientId": "client-id-123",
            "clientSecret": "client-secret-456"
          }
        }
        """
      Then 回應狀態碼應為 200
      And wp_option "_power_funnel_youtube_setting" 應包含：
        | key          | value              |
        | clientId     | client-id-123      |
        | clientSecret | client-secret-456  |
