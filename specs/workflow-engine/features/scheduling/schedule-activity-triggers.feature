@ignore @command
Feature: ActivitySchedulerService 排程時間型觸發點

  Background:
    Given 系統中有以下 WorkflowRule（已發布）：
      | id | trigger_point                       | trigger_params              |
      | 1  | pf/trigger/activity_started         | {}                          |
      | 2  | pf/trigger/activity_before_start    | {"before_minutes": "30"}    |
      | 3  | pf/trigger/activity_before_start    | {"before_minutes": "60"}    |
      | 4  | pf/trigger/registration_approved    | {}                          |

  Rule: 後置（狀態）- 活動同步時應排程 ACTIVITY_STARTED

    Example: 活動資料同步後排程 ACTIVITY_STARTED 在活動開始時刻
      Given 系統中有以下活動：
        | id | title        | scheduled_start_time      |
        | A1 | 直播場次一   | 2026-04-15T20:00:00+08:00 |
      When 系統呼叫 ActivitySchedulerService::schedule_activity(ActivityDTO) 排程活動 "A1"
      Then 系統應透過 as_schedule_single_action 在 2026-04-15T20:00:00+08:00 排程 hook "power_funnel/activity_trigger/started"，參數為 ["A1"]

  Rule: 後置（狀態）- 應為每個 ACTIVITY_BEFORE_START 規則分別排程

    Example: 兩個 before_start 規則各自排程
      Given 系統中有以下活動：
        | id | title        | scheduled_start_time      |
        | A1 | 直播場次一   | 2026-04-15T20:00:00+08:00 |
      When 系統呼叫 ActivitySchedulerService::schedule_activity(ActivityDTO) 排程活動 "A1"
      Then 系統應建立以下排程：
        | hook                                         | scheduled_time                | params       |
        | power_funnel/activity_trigger/started         | 2026-04-15T20:00:00+08:00     | ["A1"]       |
        | power_funnel/activity_trigger/before_start    | 2026-04-15T19:30:00+08:00     | ["A1", "2"]  |
        | power_funnel/activity_trigger/before_start    | 2026-04-15T19:00:00+08:00     | ["A1", "3"]  |

  Rule: 前置（狀態）- 重新排程時應先取消舊排程

    Example: 活動時間更新後舊排程被取消並建立新排程
      Given 活動 "A1" 已有舊排程（原開始時間 2026-04-15T20:00:00+08:00）
      When 系統呼叫 ActivitySchedulerService::schedule_activity(ActivityDTO) 排程活動 "A1"（新開始時間 2026-04-16T20:00:00+08:00）
      Then 系統應先呼叫 as_unschedule_all_actions 取消活動 "A1" 的所有舊排程
      And 系統應建立新的排程，時間對應 2026-04-16T20:00:00+08:00

  Rule: 前置（狀態）- 活動無有效開始時間時跳過排程

    Example: 活動開始時間戳記為 0 時不排程
      Given 系統中有以下活動：
        | id | title        | scheduled_start_time |
        | A2 | 未定時間活動 | 0                    |
      When 系統呼叫 ActivitySchedulerService::schedule_activity(ActivityDTO) 排程活動 "A2"
      Then 系統不應建立任何排程

  Rule: 前置（參數）- before_minutes 必須大於 0

    Example: before_minutes 為 0 時跳過該規則的排程
      Given 系統中額外有以下 WorkflowRule：
        | id | trigger_point                       | trigger_params            |
        | 5  | pf/trigger/activity_before_start    | {"before_minutes": "0"}   |
      When 系統排程活動 "A1"
      Then 系統不應為 WorkflowRule 5 建立 before_start 排程
