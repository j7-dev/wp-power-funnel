@ignore @command
Feature: 設定 WorkflowRule 觸發點

  Background:
    Given 系統已註冊 CPT "pf_workflow_rule"
    And 系統中有以下 WorkflowRule：
      | id | title            | post_status |
      | 1  | 報名通知工作流    | draft       |

  Rule: 後置（狀態）- trigger_point meta 應儲存為新版物件格式

    Example: 設定不含參數的觸發點（事件型）時以 JSON 物件格式儲存
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 將 WorkflowRule 1 的觸發點設為 "pf/trigger/registration_approved"
      Then 操作成功
      And WorkflowRule 1 的 trigger_point meta 應解析為：
        | hook                              | params |
        | pf/trigger/registration_approved  | {}     |

    Example: 設定含參數的觸發點（時間型）時以 JSON 物件格式儲存
      Given 管理員 "Admin" 已登入後台
      When 管理員 "Admin" 將 WorkflowRule 1 的觸發點設為 "pf/trigger/activity_before_start"，參數為 {"before_minutes": 30}
      Then 操作成功
      And WorkflowRule 1 的 trigger_point meta 應解析為：
        | hook                                  | params                  |
        | pf/trigger/activity_before_start      | {"before_minutes": "30"} |

  Rule: 前置（狀態）- trigger_point 必須為已註冊的 ETriggerPoint hook 值

    Example: 設定不存在的觸發點時由前端 Select 元件限制選項
      Given 管理員 "Admin" 已登入後台
      And 可用的觸發點列表為：
        | hook                              | name                |
        | pf/trigger/registration_approved  | 用戶報名審核通過後    |
        | pf/trigger/line_followed          | 用戶關注 LINE 官方帳號後 |
      When 管理員 "Admin" 在下拉選單中選擇觸發點
      Then 下拉選單僅顯示已註冊的觸發點

  Rule: 前置（狀態）- 向後相容舊版純字串格式

    Example: 舊版純字串格式的 trigger_point 可正確解析
      Given WorkflowRule 1 的 trigger_point meta 為純字串 "pf/trigger/registration_approved"
      When 系統讀取 WorkflowRule 1 的觸發點設定
      Then 解析結果應為：
        | hook                              | params |
        | pf/trigger/registration_approved  | {}     |
