@ignore @command
Feature: Workflow 完成

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  | workflow_rule_id | trigger_point                       | nodes_count |
      | 100 | 1                | pf/trigger/registration_approved    | 2           |

  Rule: 後置（狀態）- 所有節點成功完成後 Workflow 狀態應設為 completed

    Example: 兩個節點皆成功後 Workflow 完成
      Given Workflow 100 已執行完所有 2 個節點，results 為：
        | node_id | code | message    |
        | n1      | 200  | 發信成功   |
        | n2      | 200  | 等待中     |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的狀態應設為 "completed"

  Rule: 後置（狀態）- Workflow 完成後應觸發 power_funnel/workflow/completed hook

    Example: 狀態轉為 completed 時觸發生命週期 hook
      Given Workflow 100 所有節點已完成
      When Workflow 100 的狀態從 "running" 轉為 "completed"
      Then 系統應觸發 "power_funnel/workflow/completed" hook，參數為 Workflow ID "100"

  Rule: 後置（狀態）- 含 code=301（跳過）結果的節點也算已完成

    Example: 節點被跳過仍計入已完成數量
      Given Workflow 100 的 results 為：
        | node_id | code | message                   |
        | n1      | 301  | 不符合執行條件，跳過       |
        | n2      | 200  | 發信成功                  |
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的狀態應設為 "completed"
