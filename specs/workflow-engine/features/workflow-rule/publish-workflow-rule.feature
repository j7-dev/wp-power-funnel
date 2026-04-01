@ignore @command
Feature: 發布 WorkflowRule

  Background:
    Given 系統已註冊 CPT "pf_workflow_rule"
    And 系統中有以下 WorkflowRule：
      | id | title            | post_status | trigger_point                       | nodes_count |
      | 1  | 報名通知工作流    | draft       | pf/trigger/registration_approved    | 2           |
      | 2  | 空觸發點工作流    | draft       |                                     | 1           |

  Rule: 後置（狀態）- 發布後 WorkflowRule 狀態應從 draft 變為 publish

    Example: 管理員發布 WorkflowRule 後狀態為 publish
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 將 WorkflowRule 1 的狀態設為 "publish"
      Then 操作成功
      And WorkflowRule 1 的狀態應為 "publish"

  Rule: 前置（狀態）- 發布的 WorkflowRule 必須有觸發點設定

    Example: 觸發點為空的 WorkflowRule 可以發布但不會被掛載監聽器
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 將 WorkflowRule 2 的狀態設為 "publish"
      Then 操作成功
      And WorkflowRule 2 的觸發點 hook 為空字串，系統不為其註冊 action
