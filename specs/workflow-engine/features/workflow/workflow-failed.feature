@ignore @command
Feature: Workflow 失敗

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  | workflow_rule_id | trigger_point                       | nodes_count |
      | 100 | 1                | pf/trigger/registration_approved    | 3           |

  Rule: 後置（狀態）- 任一節點執行失敗（code=500）後 Workflow 狀態應設為 failed

    Example: 第二個節點執行失敗後 Workflow 設為 failed
      Given Workflow 100 的第 1 個節點已成功（code=200）
      And 第 2 個節點執行時拋出 RuntimeException "找不到 unknown_node 節點定義"
      When 系統執行 Workflow 100 的第 2 個節點
      Then Workflow 100 的 results 應包含：
        | node_id | code | message                         |
        | n1      | 200  | 發信成功                        |
        | n2      | 500  | 找不到 unknown_node 節點定義    |
      And Workflow 100 的狀態應設為 "failed"
      And 第 3 個節點不應被執行

  Rule: 後置（狀態）- Workflow 失敗後應觸發 power_funnel/workflow/failed hook

    Example: 狀態轉為 failed 時觸發生命週期 hook
      Given Workflow 100 某節點執行失敗
      When Workflow 100 的狀態從 "running" 轉為 "failed"
      Then 系統應觸發 "power_funnel/workflow/failed" hook，參數為 Workflow ID "100"

  Rule: 後置（狀態）- NodeDefinition 執行回傳 is_success()=false 時應視為失敗

    Example: execute() 回傳 code=500 的 WorkflowResultDTO 後 Workflow 失敗
      Given Workflow 100 的第 1 個節點的 NodeDefinition::execute() 回傳 code=500
      When 系統執行 Workflow 100 的第 1 個節點
      Then NodeDTO::try_execute() 應拋出 RuntimeException
      And Workflow 100 的狀態應設為 "failed"
