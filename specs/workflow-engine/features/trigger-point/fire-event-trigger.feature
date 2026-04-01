@ignore @command
Feature: 事件型觸發點即時觸發工作流

  Background:
    Given 系統中有以下 WorkflowRule（已發布且已註冊監聽器）：
      | id | trigger_point                       | nodes_count |
      | 1  | pf/trigger/registration_approved    | 2           |

  Rule: 後置（狀態）- 事件型觸發點觸發時應建立 Workflow 實例

    Example: 報名審核通過時建立 Workflow 並開始執行
      Given 系統中有以下報名紀錄：
        | id  | identity_id | identity_provider | activity_id | promo_link_id | post_status |
        | 100 | U123        | line              | A1          | PL1           | pending     |
      When 報名紀錄 100 的狀態從 "pending" 轉為 "success"
      Then 系統應建立一個新的 Workflow 實例
      And 該 Workflow 的狀態應為 "running"
      And 該 Workflow 的 workflow_rule_id 應為 "1"
      And 該 Workflow 的 trigger_point 應為 "pf/trigger/registration_approved"

  Rule: 後置（狀態）- 同一個觸發點可有多個 WorkflowRule 監聽

    Example: 兩個 WorkflowRule 監聽同一觸發點時各自建立 Workflow
      Given 系統中額外有以下 WorkflowRule：
        | id | trigger_point                       | nodes_count |
        | 5  | pf/trigger/registration_approved    | 1           |
      When 報名審核通過事件觸發 "pf/trigger/registration_approved"
      Then 系統應建立 2 個 Workflow 實例
