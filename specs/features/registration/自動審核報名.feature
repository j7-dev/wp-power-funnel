@ignore @command
Feature: 自動審核報名

  報名進入 pending 狀態後，若推廣連結的 auto_approved 為 yes，
  系統自動將報名狀態轉為 success。
  此邏輯掛載在 power_funnel/registration/pending action（priority=20），
  在基本通知發送之後執行。

  Background:
    Given 系統中有以下推廣連結：
      | id | name       | auto_approved |
      | 10 | 三月推廣連結 | yes           |
    And 系統中有以下報名紀錄：
      | id | activity_id | identity_id | identity_provider | promo_link_id | post_status |
      | 50 | yt001       | U1234       | line              | 10            | pending     |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 報名狀態必須為 pending

    Example: 非 pending 狀態不觸發自動審核
      Given 報名 #50 的狀態為 "success"
      When 系統觸發 action "power_funnel/registration/success"
      Then 系統不應執行自動審核邏輯

  Rule: 前置（狀態）- auto_approved 為 no 時不自動審核

    Example: auto_approved 為 no 時維持 pending
      Given 推廣連結 #10 的 auto_approved 為 "no"
      When 報名 #50 狀態轉為 "pending"
      Then 報名 #50 的狀態應維持 "pending"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- auto_approved 為 yes 時自動轉為 success

    Example: 自動審核通過
      Given 推廣連結 #10 的 auto_approved 為 "yes"
      When 報名 #50 狀態轉為 "pending"
      Then 系統應呼叫 wp_update_post 將報名 #50 的狀態更新為 "success"

  Rule: 後置（狀態）- 自動審核後觸發 success 狀態的 LINE 通知

    Example: 狀態轉為 success 時觸發通知
      Given 推廣連結 #10 的 auto_approved 為 "yes"
      When 報名 #50 狀態轉為 "pending"
      Then 報名 #50 的狀態應變為 "success"
      And 系統應觸發 action "power_funnel/registration/success"
