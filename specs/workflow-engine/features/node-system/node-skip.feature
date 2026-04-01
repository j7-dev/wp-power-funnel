@ignore @command
Feature: 節點跳過（match_callback 不滿足）

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 有以下節點：
      | id | node_definition_id | match_callback              |
      | n1 | email              | ["some_condition_checker"]  |
      | n2 | email              | ["__return_true"]           |

  Rule: 後置（狀態）- match_callback 回傳 false 時應記錄 code=301 並跳至下一節點

    Example: 條件不符時跳過當前節點並執行下一個
      Given 節點 "n1" 的 match_callback 回傳 false
      When 系統執行 Workflow 100 的節點 "n1"
      Then Workflow 100 的 results 應包含：
        | node_id | code | message                                           |
        | n1      | 301  | workflow #100 node #n1 不符合執行條件，跳過        |
      And 系統應呼叫 workflow_dto->do_next() 繼續執行下一節點

  Rule: 後置（狀態）- 跳過後應透過 do_next() 觸發 power_funnel/workflow/running 繼續

    Example: 跳過節點後重新觸發 running hook
      Given 節點 "n1" 的 match_callback 回傳 false
      When 系統執行 Workflow 100 的節點 "n1"
      Then 系統應觸發 "power_funnel/workflow/running" hook，參數為 "100"
