@ignore @command
Feature: 設定 WorkflowRule 節點序列

  Background:
    Given 系統已註冊 CPT "pf_workflow_rule"
    And 系統中有以下 WorkflowRule：
      | id | title            | post_status |
      | 1  | 報名通知工作流    | draft       |
    And 系統已註冊以下 NodeDefinition：
      | id             | name          | type         |
      | email          | 傳送 Email    | send_message |
      | wait           | 等待          | action       |
      | line           | 傳送 LINE 訊息 | send_message |
      | tag_user       | 標籤用戶      | action       |

  Rule: 後置（狀態）- nodes meta 應儲存為 NodeDTO 陣列

    Example: 設定兩個節點的序列後 nodes meta 正確儲存
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 將 WorkflowRule 1 的節點設定為：
        | id | node_definition_id | params                                                          | match_callback    |
        | n1 | email              | {"recipient":"context","subject_tpl":"歡迎","content_tpl":"感謝報名"} | ["__return_true"] |
        | n2 | wait               | {"duration":"30","unit":"minutes"}                               | ["__return_true"] |
      Then 操作成功
      And WorkflowRule 1 的 nodes meta 應包含 2 個節點

  Rule: 前置（參數）- 每個節點必須指定已註冊的 node_definition_id

    Example: 節點的 node_definition_id 不存在時由前端選單限制
      Given 管理員 "Admin" 已登入後台
      And 可用的節點定義為 "email", "wait", "line", "tag_user"
      When 管理員 "Admin" 在節點選單中選擇節點類型
      Then 選單僅顯示已註冊的 NodeDefinition

  Rule: 後置（狀態）- NodeDTO 的 match_callback 預設為 ["__return_true"]

    Example: 未設定 match_callback 時預設為總是執行
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 新增一個 email 節點，未設定 match_callback
      Then 該節點的 match_callback 應為 ["__return_true"]
