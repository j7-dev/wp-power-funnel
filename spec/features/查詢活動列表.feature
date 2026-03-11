@ignore
Feature: 查詢活動列表

  管理員透過 REST API 查詢活動列表，
  支援 keyword（標題關鍵字）與 last_n_days（未來 N 天）篩選。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email            | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 用戶 "Admin" 已登入 WordPress 後台
    And YouTube OAuth 已授權
    And 系統中有以下活動（來自 YouTube）：
      | id    | title          | description    | scheduled_start_time |
      | yt001 | React 直播教學   | React 入門      | 2026-03-20T10:00:00  |
      | yt002 | Vue 直播教學     | Vue 進階        | 2026-03-25T10:00:00  |
      | yt003 | React 進階分享   | React 深入      | 2026-04-15T10:00:00  |
      | yt004 | PHP 基礎教學     | PHP 入門        | 2026-02-01T10:00:00  |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 無篩選條件時回傳所有可報名活動

    Example: 查詢所有活動（排除已過期）
      When 管理員發送 GET /wp-json/power-funnel/activities
      Then 回應狀態碼應為 200
      And 回傳活動列表不應包含 id 為 "yt004" 的活動（已過期）
      And 回傳活動列表應包含 id 為 "yt001", "yt002", "yt003" 的活動

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 支援 keyword 篩選

    Example: 使用 keyword 篩選標題包含 "React" 的活動
      When 管理員發送 GET /wp-json/power-funnel/activities?keyword=React
      Then 回應狀態碼應為 200
      And 回傳活動列表應包含 id 為 "yt001", "yt003" 的活動
      And 回傳活動列表不應包含 id 為 "yt002" 的活動

  Rule: 後置（回應）- 支援 last_n_days 篩選

    Example: 使用 last_n_days 篩選未來 20 天內的活動
      When 管理員發送 GET /wp-json/power-funnel/activities?last_n_days=20
      Then 回應狀態碼應為 200
      And 回傳活動列表應包含 id 為 "yt001", "yt002" 的活動
      And 回傳活動列表不應包含 id 為 "yt003" 的活動

  Rule: 後置（回應）- keyword 與 last_n_days 同時篩選取交集

    Example: 同時使用 keyword 與 last_n_days
      When 管理員發送 GET /wp-json/power-funnel/activities?keyword=React&last_n_days=20
      Then 回應狀態碼應為 200
      And 回傳活動列表應僅包含 id 為 "yt001" 的活動

  Rule: 後置（回應）- 支援 id 直接查找

    Example: 使用 id 查找特定活動
      When 管理員發送 GET /wp-json/power-funnel/activities?id=yt002
      Then 回應狀態碼應為 200
      And 回傳活動列表應僅包含 id 為 "yt002" 的活動

  Rule: 後置（回應）- 回傳欄位格式正確

    Example: 活動 DTO 包含所有必要欄位
      When 管理員發送 GET /wp-json/power-funnel/activities
      Then 每筆活動應包含以下欄位：
        | 欄位                  | 類型    |
        | id                   | string  |
        | activity_provider_id | string  |
        | title                | string  |
        | description          | string  |
        | thumbnail_url        | string  |
        | scheduled_start_time | integer |
        | meta                 | object  |
