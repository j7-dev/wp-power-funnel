@ignore @command
Feature: Action Scheduler 到期觸發時間型觸發點

  Background:
    Given 系統中有以下 WorkflowRule（已發布且已註冊監聯器）：
      | id | trigger_point                       |
      | 1  | pf/trigger/activity_started         |
      | 2  | pf/trigger/activity_before_start    |

  Rule: 後置（狀態）- ACTIVITY_STARTED 排程到期時應觸發對應 hook 並帶入 context

    Example: 活動開始時間到達時觸發 pf/trigger/activity_started
      Given 活動 "A1" 的 ACTIVITY_STARTED 排程已到期
      When Action Scheduler 觸發 "power_funnel/activity_trigger/started"，參數為 "A1"
      Then ActivitySchedulerService::on_activity_started("A1") 被呼叫
      And 系統應觸發 "pf/trigger/activity_started" hook
      And context_callable_set 執行後應產生：
        | key           | value             |
        | activity_id   | A1                |
        | event_type    | activity_started  |

  Rule: 後置（狀態）- ACTIVITY_BEFORE_START 排程到期時應觸發對應 hook 並帶入 workflow_rule_id

    Example: 活動開始前 30 分鐘時觸發 pf/trigger/activity_before_start
      Given 活動 "A1" 的 ACTIVITY_BEFORE_START 排程已到期（對應 WorkflowRule 2）
      When Action Scheduler 觸發 "power_funnel/activity_trigger/before_start"，參數為 "A1", "2"
      Then ActivitySchedulerService::on_activity_before_start("A1", "2") 被呼叫
      And 系統應觸發 "pf/trigger/activity_before_start" hook
      And context_callable_set 執行後應產生：
        | key               | value                   |
        | activity_id       | A1                      |
        | workflow_rule_id  | 2                       |
        | event_type        | activity_before_start   |
