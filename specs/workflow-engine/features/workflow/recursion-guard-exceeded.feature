@ignore @command
Feature: RecursionGuard 遞迴深度超過上限

  Background:
    Given RecursionGuard 的 MAX_DEPTH 為 3
    And 系統中有以下 WorkflowRule（已發布）：
      | id | trigger_point                       |
      | 1  | pf/trigger/workflow_completed       |

  Rule: 後置（狀態）- 遞迴深度超過 MAX_DEPTH 時應建立 failed 狀態的 Workflow

    Example: 遞迴深度為 4（超過 MAX_DEPTH=3）時建立失敗 Workflow
      Given RecursionGuard 當前深度為 3
      When 觸發 "pf/trigger/workflow_completed" hook
      Then RecursionGuard::enter() 使深度變為 4
      And RecursionGuard::is_exceeded() 回傳 true
      And 系統呼叫 Repository::create_failed_from_recursion_exceeded()
      And 建立的 Workflow 狀態應為 "failed"
      And 建立的 Workflow results 應包含：
        | node_id          | code | message                              |
        | recursion_guard  | 500  | 工作流建立遞迴深度超過上限（最大 3） |

  Rule: 前置（狀態）- 遞迴深度未超過上限時正常建立 Workflow

    Example: 遞迴深度為 2（未超過 MAX_DEPTH=3）時正常建立
      Given RecursionGuard 當前深度為 1
      When 觸發 "pf/trigger/workflow_completed" hook
      Then RecursionGuard::enter() 使深度變為 2
      And RecursionGuard::is_exceeded() 回傳 false
      And 系統正常呼叫 Repository::create_from()

  Rule: 後置（狀態）- 無論成功或失敗都應呼叫 RecursionGuard::leave() 減少深度

    Example: 正常執行後深度恢復
      Given RecursionGuard 當前深度為 0
      When 觸發 "pf/trigger/workflow_completed" hook 並正常建立 Workflow
      Then 執行完畢後 RecursionGuard 深度應回到 0

    Example: 遞迴超限後深度恢復
      Given RecursionGuard 當前深度為 3
      When 觸發 "pf/trigger/workflow_completed" hook 並被遞迴防護攔截
      Then 執行完畢後 RecursionGuard 深度應回到 3
