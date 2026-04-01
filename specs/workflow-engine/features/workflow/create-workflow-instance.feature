@ignore @command
Feature: 建立 Workflow 執行實例

  Background:
    Given 系統中有以下 WorkflowRule（已發布且已註冊監聽器）：
      | id | title          | trigger_point                       | nodes                                     |
      | 1  | 報名通知       | pf/trigger/registration_approved    | [{"id":"n1","node_definition_id":"email"}] |

  Rule: 後置（狀態）- 觸發點 hook 觸發時應建立 status=running 的 Workflow

    Example: pf/trigger/registration_approved 觸發後建立 Workflow
      Given 報名審核通過事件觸發
      When 系統執行 WorkflowRuleDTO::register() 中的 action callback
      Then 系統呼叫 Repository::create_from()
      And 新 Workflow 的 meta 應為：
        | meta_key             | meta_value                          |
        | workflow_rule_id     | 1                                   |
        | trigger_point        | pf/trigger/registration_approved    |
        | results              | []                                  |
      And 新 Workflow 的 post_status 應為 "running"
      And 新 Workflow 的 post_type 應為 "pf_workflow"

  Rule: 後置（狀態）- Workflow 的 nodes 應從 WorkflowRule 複製

    Example: 建立的 Workflow 包含 WorkflowRule 的完整節點序列
      Given 報名審核通過事件觸發
      When 系統建立 Workflow 實例
      Then Workflow 的 nodes meta 應與 WorkflowRule 1 的 nodes 一致

  Rule: 後置（狀態）- Workflow 的 context_callable_set 應儲存觸發時的上下文

    Example: Workflow 儲存用於延遲取得 context 的 callable set
      Given 報名審核通過事件觸發，context_callable_set 包含 registration_id=100
      When 系統建立 Workflow 實例
      Then Workflow 的 context_callable_set meta 應包含 callable 與 params

  Rule: 後置（狀態）- 建立 Workflow 後應觸發 power_funnel/workflow/running hook 開始執行

    Example: Workflow 建立後 transition_post_status 觸發 running hook
      Given 報名審核通過事件觸發
      When 系統建立 Workflow 實例（status=running）
      Then 系統應觸發 "power_funnel/workflow/running" hook
      And Register::start_workflow() 被呼叫
