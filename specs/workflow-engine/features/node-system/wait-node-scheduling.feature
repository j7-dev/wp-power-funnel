@ignore @command
Feature: WaitNode 使用 Action Scheduler 排程延遲

  Background:
    Given 系統已註冊 WaitNode（id="wait"）
    And 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                   |
      | n1 | wait               | {"duration":"30","unit":"minutes"}        |

  Rule: 後置（狀態）- WaitNode 執行時應排程 as_schedule_single_action 延遲觸發

    Example: WaitNode 排程 30 分鐘後重新觸發 Workflow
      Given 當前時間為 2026-04-15T10:00:00+08:00
      When 系統執行 Workflow 100 的 WaitNode "n1"
      Then 系統應呼叫 as_schedule_single_action，參數為：
        | timestamp                     | hook                            | args                   |
        | 2026-04-15T10:30:00+08:00     | power_funnel/workflow/running    | {"workflow_id":"100"}  |
      And Workflow 100 的 results 應包含：
        | node_id | code | message |
        | n1      | 200  | 等待中  |

  Rule: 後置（狀態）- WaitNode 不應呼叫 do_next()（等待排程到期後由 Action Scheduler 驅動）

    Example: WaitNode 執行後 Workflow 暫停等待
      When 系統執行 Workflow 100 的 WaitNode "n1"
      Then WaitNode::execute() 回傳 code=200
      And Workflow 100 的狀態仍為 "running"（不呼叫 do_next，等待排程）

  Rule: 後置（狀態）- Action Scheduler 排程到期後重新觸發 Workflow 從下一節點繼續

    Example: 排程到期後 Workflow 繼續執行下一節點
      Given WaitNode "n1" 已排程並記錄 code=200
      And 排程到期
      When Action Scheduler 觸發 "power_funnel/workflow/running"，參數為 "100"
      Then 系統呼叫 Register::start_workflow("100")
      And WorkflowDTO::try_execute() 從 results 數量（=1）決定執行 nodes[1]

  Rule: 後置（狀態）- as_schedule_single_action 回傳 0 時記錄排程失敗

    Example: 排程失敗時記錄 code=500
      Given as_schedule_single_action 回傳 0
      When 系統執行 Workflow 100 的 WaitNode "n1"
      Then Workflow 100 的 results 應包含：
        | node_id | code | message        |
        | n1      | 500  | 等待排程失敗   |
