@ignore @command
Feature: 儲存設定

  管理員透過後台設定頁面儲存 LINE 和 YouTube API 連線參數。
  系統根據 EOptionName 列舉值逐一儲存對應的 wp_option。

  Background:
    Given 管理員已登入 WordPress 後台
    And 系統支援以下設定項：
      | option_name  | 說明                |
      | line         | LINE Messaging API 設定 |
      | youtube      | YouTube API 設定        |
      | googleOauth  | Google OAuth 狀態（唯讀）  |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- body 必須包含對應的 EOptionName key

    Example: 請求不包含任何設定 key 時仍回傳成功但不更新
      Given 請求 body 為空物件
      When 管理員發送 POST /wp-json/power-funnel/options
      Then 回應 code 為 "save_options_success"
      And 回應 message 為 "儲存成功"
      And 系統不更新任何 wp_option

  Rule: 前置（參數）- 設定值必須為陣列

    Example: 設定值為字串時跳過該項
      Given 請求 body 為：
        | key  | value       |
        | line | "not_array" |
      When 管理員發送 POST /wp-json/power-funnel/options
      Then 系統不更新 _power_funnel_line_setting
      And 回應 code 為 "save_options_success"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- LINE 設定成功儲存至 wp_options

    Example: 儲存 LINE 設定
      Given 請求 body 為：
        | key  | value                                                                                                                   |
        | line | {"liff_id":"1234","liff_url":"https://liff.line.me/1234","channel_id":"CH01","channel_secret":"SEC01","channel_access_token":"TOKEN01"} |
      When 管理員發送 POST /wp-json/power-funnel/options
      Then wp_option "_power_funnel_line_setting" 應包含：
        | key                  | value                        |
        | liff_id              | 1234                         |
        | liff_url             | https://liff.line.me/1234    |
        | channel_id           | CH01                         |
        | channel_secret       | SEC01                        |
        | channel_access_token | TOKEN01                      |

  Rule: 後置（狀態）- YouTube 設定成功儲存至 wp_options

    Example: 儲存 YouTube 設定
      Given 請求 body 為：
        | key     | value                                                       |
        | youtube | {"clientId":"GCID01","clientSecret":"GCSECRET01"}           |
      When 管理員發送 POST /wp-json/power-funnel/options
      Then wp_option "_power_funnel_youtube_setting" 應包含：
        | key          | value      |
        | clientId     | GCID01     |
        | clientSecret | GCSECRET01 |

  Rule: 後置（狀態）- googleOauth 區段為唯讀，儲存時跳過

    Example: 請求包含 googleOauth 時不報錯但不處理
      Given 請求 body 包含 googleOauth 區段
      When 管理員發送 POST /wp-json/power-funnel/options
      Then 系統不修改 Google OAuth 相關設定
      And 回應 code 為 "save_options_success"
