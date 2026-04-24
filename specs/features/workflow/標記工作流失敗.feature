@ignore @command
Feature: 標記工作流失敗

  當 Workflow 執行某個節點時拋出例外（Throwable），
  WorkflowDTO::try_execute() 的 catch 區塊將狀態設為 failed。
  失敗的節點結果（code=500）會被記錄在 results 中。

  Background:
    Given 系統中有以下工作流：
      | id | name            | workflow_rule_id | trigger_point                   | post_status |
      | 30 | Workflow 實例 1 | 20               | pf/trigger/registration_created | running     |
    And 工作流 #30 有 2 個節點

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 僅 running 狀態的 Workflow 才會標記失敗

    Example: 已經是 failed 狀態時不重複處理
      Given 工作流 #30 的狀態為 "failed"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統不應執行任何節點
      And 工作流 #30 的狀態應維持 "failed"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 節點執行拋出例外時標記 failed

    Example: 節點定義不存在導致工作流失敗
      Given 工作流 #30 尚未執行任何節點（results 為空）
      And 工作流 #30 的第一個節點 node_definition_id 為 "non_existent"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點的結果 code 應為 500
      And 節點的結果 message 應包含 "找不到"
      And 系統應呼叫 wp_update_post 將工作流 #30 的狀態更新為 "failed"

  Rule: 後置（狀態）- 節點執行結果 is_success() 為 false 時標記 failed

    Example: wp_mail 發送失敗導致工作流失敗
      Given 工作流 #30 尚未執行任何節點（results 為空）
      And Email NodeDefinition 已註冊
      And wp_mail 回傳 false
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點的結果 code 應為 500
      And 工作流 #30 的狀態應變為 "failed"

  Rule: 後置（狀態）- 失敗後不繼續執行後續節點

    Example: 第一個節點失敗時不執行第二個節點
      Given 工作流 #30 尚未執行任何節點（results 為空）
      And 工作流 #30 的第一個節點執行失敗
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 工作流 #30 的 results 應僅包含 1 筆結果
      And 系統不應執行第二個節點
