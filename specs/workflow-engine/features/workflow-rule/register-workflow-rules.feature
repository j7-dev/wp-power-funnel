@ignore @command
Feature: 系統註冊已發布的 WorkflowRule 監聽器

  Background:
    Given 系統已註冊 CPT "pf_workflow_rule"
    And 系統中有以下 WorkflowRule：
      | id | title            | post_status | trigger_point                       |
      | 1  | 報名通知         | publish     | pf/trigger/registration_approved    |
      | 2  | LINE 關注歡迎     | publish     | pf/trigger/line_followed            |
      | 3  | 草稿工作流       | draft       | pf/trigger/registration_cancelled   |

  Rule: 後置（狀態）- 系統應在 init(priority=99) 為所有 publish 狀態的 WorkflowRule 掛載 action

    Example: 系統啟動後為已發布的 WorkflowRule 註冊 hook 監聽器
      Given 系統執行 init hook（priority=99）
      When 系統呼叫 Register::register_workflow_rules()
      Then 以下 hook 應有已註冊的 action：
        | hook                              | registered |
        | pf/trigger/registration_approved  | true       |
        | pf/trigger/line_followed          | true       |
        | pf/trigger/registration_cancelled | false      |

  Rule: 前置（狀態）- draft 狀態的 WorkflowRule 不應被掛載

    Example: 草稿狀態的 WorkflowRule 不會被註冊
      Given 系統執行 init hook（priority=99）
      When 系統呼叫 Register::register_workflow_rules()
      Then WorkflowRule 3 不應在 "pf/trigger/registration_cancelled" hook 上有監聽器
