@ignore @command
Feature: TriggerPointService 橋接業務域事件到觸發點 Hook

  Background:
    Given 系統中有以下 WorkflowRule（已發布且已註冊監聽器）：
      | id | trigger_point                       |
      | 1  | pf/trigger/registration_approved    |
      | 2  | pf/trigger/line_followed            |
      | 3  | pf/trigger/workflow_completed       |
    And TriggerPointService 已透過 register_hooks() 註冊所有監聽器

  # ===== P0: 報名狀態觸發點 =====

  Rule: 後置（狀態）- 報名審核通過時應觸發 REGISTRATION_APPROVED hook 並帶入 context

    Example: 報名狀態從 pending 轉為 success 時觸發 pf/trigger/registration_approved
      Given 系統中有以下報名紀錄：
        | id  | identity_id | identity_provider | activity_id | promo_link_id | post_status |
        | 100 | U123        | line              | A1          | PL1           | pending     |
      When 報名紀錄 100 的狀態從 "pending" 轉為 "success"
      Then 系統應觸發 "pf/trigger/registration_approved" hook
      And context_callable_set 執行後應產生：
        | key               | value |
        | registration_id   | 100   |
        | identity_id       | U123  |
        | identity_provider | line  |
        | activity_id       | A1    |
        | promo_link_id     | PL1   |

  Rule: 前置（狀態）- 同狀態轉換不應觸發

    Example: 報名狀態不變時不觸發
      Given 系統中有以下報名紀錄：
        | id  | post_status |
        | 100 | success     |
      When 報名紀錄 100 的狀態從 "success" 轉為 "success"
      Then 系統不應觸發 "pf/trigger/registration_approved" hook

  # ===== P1: LINE 互動觸發點 =====

  Rule: 後置（狀態）- LINE follow 事件應觸發 LINE_FOLLOWED hook

    Example: 收到 LINE follow webhook 時觸發 pf/trigger/line_followed
      Given LINE webhook 收到 follow 事件，userId 為 "U456"
      When TriggerPointService::on_line_followed() 被呼叫
      Then 系統應觸發 "pf/trigger/line_followed" hook
      And context_callable_set 執行後應產生：
        | key           | value  |
        | line_user_id  | U456   |
        | event_type    | follow |

  Rule: 後置（狀態）- LINE message 事件應額外包含 message_text

    Example: 收到 LINE 文字訊息時觸發 pf/trigger/line_message_received 並帶入訊息文字
      Given LINE webhook 收到 message 事件，userId 為 "U789"，訊息為 "你好"
      When TriggerPointService::on_line_message_received() 被呼叫
      Then 系統應觸發 "pf/trigger/line_message_received" hook
      And context_callable_set 執行後應產生：
        | key           | value   |
        | line_user_id  | U789    |
        | event_type    | message |
        | message_text  | 你好    |

  Rule: 前置（狀態）- LINE 事件缺少 userId 時不應觸發

    Example: LINE 事件無 userId 時跳過觸發
      Given LINE webhook 收到 follow 事件，userId 為空
      When TriggerPointService::on_line_followed() 被呼叫
      Then 系統不應觸發 "pf/trigger/line_followed" hook

  # ===== P2: 工作流引擎觸發點 =====

  Rule: 後置（狀態）- Workflow 完成時應觸發 WORKFLOW_COMPLETED hook

    Example: Workflow 狀態轉為 completed 時觸發 pf/trigger/workflow_completed
      Given 系統中有以下 Workflow：
        | id  | workflow_rule_id | trigger_point                    |
        | 200 | 1                | pf/trigger/registration_approved |
      When Workflow 200 狀態轉為 "completed"
      Then 系統應觸發 "pf/trigger/workflow_completed" hook
      And context_callable_set 執行後應產生：
        | key               | value                            |
        | workflow_id        | 200                              |
        | workflow_rule_id   | 1                                |
        | trigger_point      | pf/trigger/registration_approved |

  # ===== P3: 用戶行為觸發點 =====

  Rule: 後置（狀態）- TagUserNode 執行後應觸發 USER_TAGGED hook

    Example: 用戶被貼標籤後觸發 pf/trigger/user_tagged
      Given TagUserNode 對用戶 "U111" 貼了標籤 "VIP"
      When TriggerPointService::fire_user_tagged("U111", "VIP") 被呼叫
      Then 系統應觸發 "pf/trigger/user_tagged" hook
      And context_callable_set 執行後應產生：
        | key       | value |
        | user_id   | U111  |
        | tag_name  | VIP   |
