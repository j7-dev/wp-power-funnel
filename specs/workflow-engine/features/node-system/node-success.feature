@ignore @command
Feature: 節點執行成功

  Background:
    Given 系統已註冊 EmailNode（id="email"）
    And 系統中有以下 Workflow（status=running）：
      | id  | context                                          |
      | 100 | {"recipient":"user@test.com"}                    |
    And Workflow 100 有以下節點：
      | id | node_definition_id | match_callback    | params                                                                    |
      | n1 | email              | ["__return_true"] | {"recipient":"user@test.com","subject_tpl":"歡迎","content_tpl":"感謝報名"} |

  Rule: 後置（狀態）- NodeDefinition::execute() 回傳 code=200 時應記錄成功結果

    Example: EmailNode 發信成功後記錄 code=200
      Given wp_mail() 回傳 true
      When 系統執行 Workflow 100 的節點 "n1"
      Then Workflow 100 的 results 應包含：
        | node_id | code | message    |
        | n1      | 200  | 發信成功   |
      And results 應透過 update_post_meta 持久化

  Rule: 後置（狀態）- 成功後不應自動呼叫 do_next()

    Example: NodeDTO::try_execute() 成功後由 WorkflowDTO::try_execute() 驅動下一節點
      Given 節點 "n1" 執行成功
      When NodeDTO::try_execute() 完成
      Then NodeDTO::try_execute() 不應主動呼叫 do_next()
      And 控制權回到 WorkflowDTO::try_execute() 判斷是否有下一節點
