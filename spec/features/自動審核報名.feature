@ignore
Feature: 自動審核報名

  報名進入 pending 狀態後，若設定 auto_approved 為 true，
  系統自動將報名狀態轉為 success。

  Background:
    Given 系統中有以下活動：
      | id    | title        | scheduled_start_time |
      | yt001 | React 直播教學 | 2026-03-20T10:00:00  |
    And 系統中有以下報名紀錄：
      | id | activity_id | identity_id | identity_provider | promo_link_id | post_status |
      | 50 | yt001       | U1234       | line              | 10            | pending     |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- auto_approved 必須為 true

    Example: auto_approved 為 false 時不自動審核
      Given 報名 #50 的 auto_approved 為 "no"
      When 報名 #50 的狀態變為 "pending"
      Then 報名 #50 的狀態應保持為 "pending"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- auto_approved 為 true 時自動轉為 success

    Example: 自動審核通過
      Given 報名 #50 的 auto_approved 為 "yes"
      When 報名 #50 的狀態變為 "pending"
      Then 報名 #50 的狀態應自動變為 "success"
