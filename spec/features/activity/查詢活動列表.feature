@ignore @query
Feature: 查詢活動列表

  從所有活動提供商（目前僅 YouTube）取得活動列表，
  支援 keyword（標題關鍵字）與 last_n_days（未來 N 天）篩選。
  僅回傳排程時間在未來的可報名活動。

  Background:
    Given YouTube OAuth 已授權
    And YouTube API 回傳以下直播場次：
      | id    | title          | description     | scheduled_start_time |
      | yt001 | React 直播教學   | React 入門       | 2026-03-20T10:00:00  |
      | yt002 | Vue 直播教學     | Vue 入門         | 2026-03-25T14:00:00  |
      | yt003 | Python 基礎     | Python 入門      | 2026-04-15T10:00:00  |
      | yt004 | 過期的直播       | 已結束           | 2025-01-01T10:00:00  |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 可選 keyword、last_n_days、id 篩選

    Example: 不帶參數時回傳所有未來活動
      When 管理員發送 GET /wp-json/power-funnel/activities
      Then 回傳活動列表應包含 "yt001", "yt002", "yt003"
      And 回傳活動列表不應包含 "yt004"

    Example: 指定 id 時回傳單一活動
      When 管理員發送 GET /wp-json/power-funnel/activities?id=yt001
      Then 回傳活動列表應只有 1 筆
      And 活動 id 應為 "yt001"

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 僅回傳排程時間在未來的活動

    Example: 過期的直播不會出現在列表中
      When 管理員發送 GET /wp-json/power-funnel/activities
      Then 回傳活動列表不應包含 scheduled_start_time 在過去的活動

  Rule: 後置（回應）- keyword 篩選標題包含關鍵字的活動

    Example: 以 keyword 篩選
      When 管理員發送 GET /wp-json/power-funnel/activities?keyword=React
      Then 回傳活動列表應只包含標題含 "React" 的活動
      And 活動數量應為 1

  Rule: 後置（回應）- last_n_days 篩選未來 N 天內的活動

    Example: 以 last_n_days 篩選
      Given 今天是 2026-03-12
      When 管理員發送 GET /wp-json/power-funnel/activities?last_n_days=20
      Then 回傳活動列表應只包含 scheduled_start_time 在未來 20 天內的活動

  Rule: 後置（回應）- 同時指定 keyword 與 last_n_days 取交集

    Example: keyword 與 last_n_days 同時篩選
      Given 今天是 2026-03-12
      When 管理員發送 GET /wp-json/power-funnel/activities?keyword=React&last_n_days=20
      Then 回傳活動列表應只包含標題含 "React" 且在未來 20 天內的活動

  Rule: 後置（回應）- 回傳欄位包含完整 ActivityDTO

    Example: 回傳資料格式
      When 管理員發送 GET /wp-json/power-funnel/activities
      Then 每筆活動應包含以下欄位：
        | 欄位                  | 型別     |
        | id                   | string   |
        | activity_provider_id | string   |
        | title                | string   |
        | description          | string   |
        | thumbnail_url        | string   |
        | scheduled_start_time | integer  |
        | meta                 | object   |
