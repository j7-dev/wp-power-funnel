@ignore @command
Feature: Context 與 ParamHelper 參數系統

  # 最高指導原則：Serializable Context Callable
  # context_callable_set 的 callable 必須為 string|string[]（禁止 Closure），
  # params 必須為純值陣列。詳見 specs/design-principles.md

  Background:
    Given 系統中有以下 Workflow（status=running）：
      | id  |
      | 100 |
    And Workflow 100 的 context_callable_set 為：
      | callable                                                        | params |
      | [TriggerPointService::class, 'resolve_registration_context']    | [42]   |
    And 呼叫 callable 後產生的 context 為：
      | key               | value              |
      | registration_id   | R1                 |
      | identity_id       | U123               |
      | recipient         | user@example.com   |
    And Workflow 100 有以下節點：
      | id | node_definition_id | params                                                                          |
      | n1 | email              | {"recipient":"context","subject_tpl":"歡迎 {{user.name}}","content_tpl":"感謝報名"} |

  Rule: 後置（回應）- param 值為 "context" 時應從 workflow.context 取得對應 key 的值

    Example: recipient 參數為 "context" 時從 context 取得 recipient 值
      Given ParamHelper 初始化，node="n1", workflow=100
      When ParamHelper::try_get_param("recipient") 被呼叫
      Then 回傳值應為 "user@example.com"

  Rule: 後置（回應）- param 值非 "context" 時直接回傳 param 自身的值

    Example: subject_tpl 參數直接回傳原值
      Given ParamHelper 初始化，node="n1", workflow=100
      When ParamHelper::try_get_param("subject_tpl") 被呼叫
      Then 回傳值應為 "歡迎 {{user.name}}"

  Rule: 後置（回應）- param key 不存在時回傳 null

    Example: 查詢不存在的 param key 時回傳 null
      Given ParamHelper 初始化，node="n1", workflow=100
      When ParamHelper::try_get_param("nonexistent_key") 被呼叫
      Then 回傳值應為 null

  Rule: 後置（回應）- ParamHelper::replace() 應對模板字串執行 ReplaceHelper 替換

    Example: 模板字串中的 {{variable}} 被替換為實際值
      Given ParamHelper 初始化，node="n1", workflow=100
      And context 中 user 物件的 name 為 "Alice"
      When ParamHelper::replace("歡迎 {{user.name}}") 被呼叫
      Then 回傳值應為 "歡迎 Alice"

  Rule: 後置（狀態）- context 的生命週期為 Workflow 實例全程

    Example: context 在 Workflow 建立時由 context_callable_set 產生，全程共用
      Given Workflow 100 建立時儲存了 context_callable_set
      When WorkflowDTO::of("100") 載入 Workflow
      Then context 由 context_callable_set 的 callable 呼叫 params 產生
      And 所有節點透過 ParamHelper 共享同一份 context

  Rule: 約束 - context_callable_set 必須可序列化（Serializable Context Callable 原則）

    Example: callable 為靜態方法引用時能安全通過 serialize/unserialize
      Given context_callable_set 的 callable 為 [TriggerPointService::class, 'resolve_registration_context']
      When context_callable_set 經過 WordPress serialize() 存入 wp_postmeta
      And 後續由 Action Scheduler 恢復時 unserialize() 取回
      Then is_callable(callable) 應為 true
      And call_user_func_array(callable, params) 應回傳正確的 context 陣列

    Example: callable 為 Closure 時 serialize 會失敗導致 context 丟失
      Given context_callable_set 的 callable 為 Closure（anonymous function）
      When context_callable_set 經過 WordPress serialize() 存入 wp_postmeta
      And 後續由 Action Scheduler 恢復時 unserialize() 取回
      Then is_callable(callable) 應為 false
      And context 變成空陣列，節點無法取得業務資料
