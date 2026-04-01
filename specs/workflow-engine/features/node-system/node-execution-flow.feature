@ignore @command
Feature: NodeDTO 節點執行流程

  Background:
    Given 系統已註冊以下 NodeDefinition：
      | id    | name        | type         |
      | email | 傳送 Email  | send_message |
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                                    |
      | 100 | {"registration_id":"R1","identity_id":"U123"}              |
    And Workflow 100 有以下節點：
      | id | node_definition_id | match_callback    | params                                                               |
      | n1 | email              | ["__return_true"] | {"recipient":"context","subject_tpl":"歡迎","content_tpl":"感謝報名"} |

  Rule: 後置（狀態）- match_callback 通過後應查找 NodeDefinition 並呼叫 execute()

    Example: match_callback 回傳 true 時執行 NodeDefinition::execute()
      Given 節點 "n1" 的 match_callback 為 ["__return_true"]
      When 系統呼叫 NodeDTO::try_execute(Workflow 100)
      Then 系統應呼叫 Repository::get_node_definition("email")
      And 系統應呼叫 EmailNode::execute(NodeDTO, WorkflowDTO)
      And Workflow 100 的 results 應新增一筆 node_id="n1" 的結果

  Rule: 前置（狀態）- node_definition_id 對應的 NodeDefinition 必須存在

    Example: 找不到 NodeDefinition 時拋出 RuntimeException
      Given 節點 "n1" 的 node_definition_id 為 "nonexistent"
      When 系統呼叫 NodeDTO::try_execute(Workflow 100)
      Then 系統應拋出 RuntimeException "找不到 nonexistent 節點定義"
      And Workflow 100 的 results 應新增：
        | node_id | code | message                     |
        | n1      | 500  | 找不到 nonexistent 節點定義 |

  Rule: 前置（狀態）- match_callback 必須為 callable

    Example: match_callback 不是 callable 時視為不符合條件
      Given 節點 "n1" 的 match_callback 為 "not_a_valid_callable"
      When 系統呼叫 NodeDTO::try_execute(Workflow 100)
      Then can_execute() 回傳 false
      And 節點被跳過（code=301）
