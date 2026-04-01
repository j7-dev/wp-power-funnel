@ignore @command
Feature: 建立 WorkflowRule

  Background:
    Given 系統已註冊 CPT "pf_workflow_rule"

  Rule: 後置（狀態）- 新建立的 WorkflowRule 狀態應為 draft

    Example: 管理員建立空白 WorkflowRule 後狀態為 draft
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 建立一個 WorkflowRule，標題為 "報名通知工作流"
      Then 操作成功
      And 該 WorkflowRule 的狀態應為：
        | post_status | post_type        |
        | draft       | pf_workflow_rule |

  Rule: 後置（狀態）- 新建立的 WorkflowRule 應有空的 nodes 和 trigger_point

    Example: 新 WorkflowRule 的 meta 欄位預設為空
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 建立一個 WorkflowRule，標題為 "新工作流"
      Then 操作成功
      And 該 WorkflowRule 的 meta 應為：
        | meta_key      | meta_value |
        | trigger_point |            |
        | nodes         | []         |

  Rule: 前置（參數）- 必要參數必須提供

    Example: 未提供標題時操作失敗
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 建立一個 WorkflowRule，標題為 ""
      Then 操作成功
      And 該 WorkflowRule 的標題應為空字串
