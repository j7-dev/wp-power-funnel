@ignore @command
Feature: 建立工作流規則

  管理員透過後台建立工作流規則（WorkflowRule），
  設定觸發時機（trigger_point）與節點序列（nodes）。
  規則建立後預設為 draft 狀態。

  Background:
    Given 系統中有以下用戶：
      | userId | name  | email             | role          |
      | 1      | Admin | admin@example.com | administrator |
    And 系統中有以下 trigger_points：
      | hook                            | name       |
      | pf/trigger/registration_created | 報名建立時 |
    And 系統中有以下 node_definitions：
      | id    | name   | type         |
      | email | Email  | send_message |
      | wait  | 等待   | action       |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須提供規則名稱

    Example: 未提供名稱時建立失敗
      Given 管理員未提供規則名稱
      When 管理員建立工作流規則
      Then 操作失敗，錯誤為「規則名稱不可為空」

  Rule: 前置（參數）- trigger_point 必須為已註冊的 hook

    Example: trigger_point 不存在時建立失敗
      Given 管理員設定 trigger_point 為 "non_existent_hook"
      When 管理員建立工作流規則
      Then 操作失敗，錯誤為「找不到觸發點 non_existent_hook」

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功建立 draft 狀態的工作流規則

    Example: 建立包含 Email 節點的工作流規則
      Given 管理員填寫以下工作流規則：
        | name         | trigger_point                   |
        | 報名後發 Email | pf/trigger/registration_created |
      And 節點序列為：
        | id | node_definition_id | params                                                                  |
        | n1 | email              | {"recipient":"context","subject_tpl":"歡迎","content_tpl":"感謝報名"} |
      When 管理員建立工作流規則
      Then 系統應建立一筆 pf_workflow_rule 紀錄
      And 規則的狀態應為 "draft"
      And 規則的 trigger_point 應為 "pf/trigger/registration_created"
      And 規則的 nodes 應包含 1 個節點
