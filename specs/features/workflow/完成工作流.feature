@ignore @command
Feature: 完成工作流

  當 Workflow 的所有節點都已執行完畢（results 數量 = nodes 數量），
  系統將 Workflow 狀態標記為 completed。
  此邏輯在 WorkflowDTO::try_execute() 中，
  當 get_current_index() 回傳 null 時觸發。

  Background:
    Given 系統中有以下工作流：
      | id | name            | workflow_rule_id | trigger_point                   | post_status |
      | 30 | Workflow 實例 1 | 20               | pf/trigger/registration_created | running     |
    And 工作流 #30 有 2 個節點
    And 工作流 #30 的 results 已有 2 筆成功結果

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- Workflow 狀態必須為 running

    Example: 非 running 狀態不會標記完成
      Given 工作流 #30 的狀態為 "failed"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統不應變更工作流狀態

  Rule: 前置（狀態）- results 數量必須等於 nodes 數量

    Example: 尚有未執行的節點時不標記完成
      Given 工作流 #30 的 results 僅有 1 筆（nodes 有 2 個）
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統應繼續執行下一個節點
      And 工作流 #30 的狀態應維持 "running"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 所有節點完成後標記 completed

    Example: 全部節點成功執行後工作流完成
      Given 工作流 #30 的 results 已有 2 筆（與 nodes 數量相同）
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統應呼叫 wp_update_post 將工作流 #30 的狀態更新為 "completed"
