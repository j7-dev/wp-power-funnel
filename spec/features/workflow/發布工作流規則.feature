@ignore @command
Feature: 發布工作流規則

  管理員將工作流規則從 draft 發布為 publish，
  系統在對應的 trigger_point hook 上掛載監聽器。
  當 hook 被觸發時，從此規則建立 Workflow 實例。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email             | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 系統中有以下工作流規則：
      | id | name         | trigger_point                   | post_status | nodes                                                                                                   |
      | 20 | 報名後發 Email | pf/trigger/registration_created | draft       | [{"id":"n1","node_definition_id":"email","params":{"recipient":"context","subject_tpl":"歡迎","content_tpl":"感謝報名"}}] |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 規則必須為 draft 狀態才能發布

    Example: 已發布的規則不可重複發布
      Given 工作流規則 #20 的狀態為 "publish"
      When 管理員發布工作流規則 #20
      Then 系統不應執行任何變更

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功發布後狀態變為 publish

    Example: 發布工作流規則
      Given 工作流規則 #20 的狀態為 "draft"
      When 管理員發布工作流規則 #20
      Then 工作流規則 #20 的狀態應變為 "publish"

  Rule: 後置（狀態）- 發布後在 trigger_point 上掛載監聽器

    Example: 發布後註冊 hook 監聽器
      Given 工作流規則 #20 已發布
      When 系統執行 register_workflow_rules()
      Then 系統應在 hook "pf/trigger/registration_created" 上掛載 callback
      And 當 hook 觸發時，系統應呼叫 Repository::create_from() 建立 Workflow 實例
