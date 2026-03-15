@ignore @command
Feature: 從規則建立工作流實例

  當 WorkflowRule 的 trigger_point hook 被觸發時，
  系統從該規則建立一個新的 Workflow 實例並開始執行。
  Workflow 實例的 nodes 從規則複製，狀態為 running，
  並立即觸發 action "power_funnel/workflow/running"。

  Background:
    Given 系統中有以下工作流規則：
      | id | name         | trigger_point                   | post_status | nodes                                                                                                                        |
      | 20 | 報名後發 Email | pf/trigger/registration_created | publish     | [{"id":"n1","node_definition_id":"email","params":{"recipient":"context","subject_tpl":"歡迎","content_tpl":"感謝報名"}}] |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- WorkflowRule 必須已發布

    Example: 草稿狀態的 WorkflowRule 不會被觸發
      Given 工作流規則 #20 的狀態為 "draft"
      When 系統觸發 hook "pf/trigger/registration_created"
      Then 系統不應建立新的 pf_workflow 紀錄

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功建立 running 狀態的 Workflow 實例

    Example: trigger_point 被觸發後建立 Workflow
      Given 工作流規則 #20 已發布且已註冊 trigger_point
      When 系統觸發 hook "pf/trigger/registration_created" 並傳入 context_callable_set
      Then 系統應建立一筆 pf_workflow 紀錄
      And 工作流的狀態應為 "running"
      And 工作流的 workflow_rule_id 應為 "20"
      And 工作流的 trigger_point 應為 "pf/trigger/registration_created"
      And 工作流的 nodes 應從規則複製
      And 工作流的 context_callable_set 應儲存傳入的 callable set
      And 工作流的 results 應為空陣列
      And 系統應觸發 action "power_funnel/workflow/running"
