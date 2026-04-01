@ignore @command
Feature: Workflow 執行流程

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  | workflow_rule_id | nodes                                                                                                  | results |
      | 100 | 1                | [{"id":"n1","node_definition_id":"email","params":{},"match_callback":["__return_true"]},{"id":"n2","node_definition_id":"wait","params":{},"match_callback":["__return_true"]}] | []      |

  Rule: 後置（狀態）- WorkflowDTO::try_execute() 應根據 results 數量決定執行哪個節點

    Example: results 為空時執行第 0 個節點
      Given Workflow 100 的 results 為空陣列
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應嘗試執行 nodes[0]（id="n1"）

    Example: results 有 1 筆時執行第 1 個節點
      Given Workflow 100 的 results 有 1 筆（n1 已完成）
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統應嘗試執行 nodes[1]（id="n2"）

  Rule: 後置（狀態）- 所有節點執行完畢後 Workflow 狀態應設為 completed

    Example: results 數量等於 nodes 數量時設為 completed
      Given Workflow 100 的 results 有 2 筆（n1, n2 皆已完成）
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的 get_current_index() 回傳 null
      And Workflow 100 的狀態應設為 "completed"

  Rule: 前置（狀態）- 非 running 狀態的 Workflow 不應執行

    Example: completed 狀態的 Workflow 呼叫 try_execute() 時直接返回
      Given Workflow 100 的狀態為 "completed"
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統不應執行任何節點

    Example: failed 狀態的 Workflow 呼叫 try_execute() 時直接返回
      Given Workflow 100 的狀態為 "failed"
      When 系統呼叫 WorkflowDTO::try_execute()
      Then 系統不應執行任何節點

  Rule: 後置（狀態）- 節點執行拋出例外時 Workflow 狀態應設為 failed

    Example: 節點執行拋出例外後 Workflow 設為 failed
      Given Workflow 100 的 results 為空
      And nodes[0] 執行時拋出 RuntimeException
      When 系統呼叫 WorkflowDTO::try_execute()
      Then Workflow 100 的狀態應設為 "failed"
