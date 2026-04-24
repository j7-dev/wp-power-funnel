@ignore @command
Feature: 建立報名紀錄

  系統為通過資格檢查的用戶建立 pf_registration CPT，
  初始狀態為 pending，並觸發報名狀態生命週期。

  Background:
    Given LINE 設定已完成
    And 系統中有以下活動：
      | id    | title        |
      | yt001 | React 直播教學 |

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 建立 pending 狀態的 pf_registration

    Example: 報名紀錄建立成功
      Given LINE 用戶 "U1234" 通過報名資格檢查
      When 系統建立報名紀錄
      Then 應建立一筆 pf_registration CPT：
        | 欄位              | 值       |
        | post_status       | pending  |
        | post_type         | pf_registration |
        | meta.activity_id  | yt001    |
        | meta.identity_id  | U1234    |
        | meta.identity_provider | line |
      And 系統應觸發 action "power_funnel/registration/pending"

  Rule: 後置（狀態）- wp_insert_post 失敗時拋出異常

    Example: 建立紀錄失敗
      Given wp_insert_post 回傳 WP_Error
      When 系統建立報名紀錄
      Then 系統應拋出異常 "創建報名失敗"
