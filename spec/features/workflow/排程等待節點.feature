@ignore @command
Feature: 排程等待節點

  WaitNode 使用 WordPress Action Scheduler（as_schedule_single_action）
  在指定的 timestamp 排程一個延遲任務。
  排程成功後記錄 code=200，到期時重新觸發 "power_funnel/workflow/running"
  讓工作流從下一個節點繼續執行。

  Background:
    Given 系統中有以下工作流：
      | id | name            | workflow_rule_id | trigger_point                   | post_status |
      | 30 | Workflow 實例 1 | 20               | pf/trigger/registration_created | running     |
    And 工作流 #30 的第一個節點為：
      | id | node_definition_id | params                           |
      | n1 | wait               | {"timestamp":1711000000}         |
    And Wait NodeDefinition 已註冊

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- Workflow 狀態必須為 running

    Example: 非 running 狀態不排程
      Given 工作流 #30 的狀態為 "completed"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統不應呼叫 as_schedule_single_action

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 排程成功時記錄結果

    Example: as_schedule_single_action 回傳 action_id
      Given 工作流 #30 尚未執行任何節點（results 為空）
      And as_schedule_single_action 回傳 action_id 為 999
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點 "n1" 的結果 code 應為 200
      And 節點 "n1" 的結果 message 應為 "等待中"
      And 排程的 hook 應為 "power_funnel/workflow/running"
      And 排程的參數應包含 workflow_id "30"

  Rule: 後置（狀態）- 排程失敗時工作流失敗

    Example: as_schedule_single_action 回傳 0（失敗）
      Given 工作流 #30 尚未執行任何節點（results 為空）
      And as_schedule_single_action 回傳 0
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點 "n1" 的結果 code 應為 500
      And 節點 "n1" 的結果 message 應為 "等待排程失敗"
      And 工作流 #30 的狀態應變為 "failed"

  Rule: 後置（狀態）- 排程到期時重新觸發工作流

    Example: 到期後 Action Scheduler 觸發 workflow running
      Given 工作流 #30 的 Wait 節點已排程成功
      And 排程的 timestamp 已到期
      When Action Scheduler 觸發 "power_funnel/workflow/running" 帶入 workflow_id "30"
      Then 系統應從下一個節點繼續執行工作流
