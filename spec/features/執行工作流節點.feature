@ignore
Feature: 執行工作流節點

  Workflow 狀態為 running 時，系統依序執行節點。
  每個節點執行後記錄結果，全部完成則標記 completed，
  任一失敗則標記 failed。

  Background:
    Given 系統中有以下工作流：
      | id | name           | workflow_rule_id | trigger_point                   | post_status | nodes |
      | 30 | Workflow 實例 1 | 20               | pf/trigger/registration_created | running     | [{"id":"n1","node_definition_id":"email","params":{"recipient":"test@example.com","subject_tpl":"歡迎","content_tpl":"感謝報名"},"match_callback":["__return_true"],"match_callback_params":[]},{"id":"n2","node_definition_id":"email","params":{"recipient":"test@example.com","subject_tpl":"提醒","content_tpl":"活動即將開始"},"match_callback":["__return_true"],"match_callback_params":[]}] |
    And Email NodeDefinition 已註冊

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- Workflow 狀態必須為 running

    Example: 非 running 狀態不執行
      Given 工作流 #30 的狀態為 "completed"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 系統不應執行任何節點

  Rule: 前置（狀態）- 所有節點都已執行完畢則標記 completed

    Example: 最後一個節點執行完成後工作流標記 completed
      Given 工作流 #30 的 results 已有 2 筆（與 nodes 數量相同）
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 工作流 #30 的狀態應變為 "completed"

  Rule: 前置（狀態）- 節點的 match_callback 不滿足時跳過

    Example: match_callback 回傳 false 時跳過該節點
      Given 工作流 #30 的第一個節點 match_callback 回傳 false
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點 "n1" 的結果 code 應為 301
      And 節點 "n1" 的結果 message 應包含 "不符合執行條件，跳過"
      And 系統應繼續執行下一個節點

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- Email 節點成功執行

    Example: Email 節點發送成功
      Given 工作流 #30 尚未執行任何節點（results 為空）
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      And wp_mail 發送成功
      Then 節點 "n1" 的結果 code 應為 200
      And 節點 "n1" 的結果 message 應為 "發信成功"
      And 系統應呼叫 do_next() 繼續下一節點

  Rule: 後置（狀態）- 節點執行失敗時 Workflow 標記 failed

    Example: Email 節點發送失敗導致工作流失敗
      Given 工作流 #30 尚未執行任何節點（results 為空）
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      And wp_mail 發送失敗
      Then 節點 "n1" 的結果 code 應為 500
      And 工作流 #30 的狀態應變為 "failed"

  Rule: 後置（狀態）- 找不到節點定義時工作流失敗

    Example: node_definition_id 不存在時工作流失敗
      Given 工作流 #30 的第一個節點 node_definition_id 為 "non_existent"
      When 系統觸發 action "power_funnel/workflow/running" 傳入 workflow_id "30"
      Then 節點 "n1" 的結果 code 應為 500
      And 節點 "n1" 的結果 message 應包含 "找不到"
      And 工作流 #30 的狀態應變為 "failed"
