@ignore
Feature: 發送報名狀態 LINE 通知

  報名狀態改變時，系統發送 LINE 文字通知給報名用戶。
  若推廣連結有設定該狀態的訊息模板，則額外發送模板訊息。

  Background:
    Given 系統中有以下活動：
      | id    | title        | scheduled_start_time |
      | yt001 | React 直播教學 | 2026-03-20T10:00:00  |
    And 系統中有以下推廣連結：
      | id | name       | message_tpl_ids                 |
      | 10 | 三月推廣連結 | {"success": "100", "rejected": "101"} |
    And 系統中有以下報名紀錄：
      | id | activity_id | identity_id | identity_provider | promo_link_id | post_status |
      | 50 | yt001       | U1234       | line              | 10            | pending     |
    And LINE 設定已完成

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 狀態改變時發送基本文字通知

    Example: 報名進入 pending 時發送收到報名的通知
      When 報名 #50 的狀態從 "new" 變為 "pending"
      Then 系統應發送 LINE 文字訊息給用戶 "U1234"
      And 訊息內容應包含 "已收到您 React 直播教學 的報名"

  Rule: 後置（狀態）- 若推廣連結有設定訊息模板則額外發送模板訊息

    Example: 報名成功且有訊息模板時發送模板訊息
      Given 訊息模板 #100 的內容為 "恭喜報名成功！活動：{{title}}"
      And 訊息模板 #100 的 content_type 為 "plain_text"
      When 報名 #50 的狀態從 "pending" 變為 "success"
      Then 系統應發送 LINE 文字訊息給用戶 "U1234"
      And 系統應額外發送一則包含 "恭喜報名成功！活動：React 直播教學" 的訊息

    Example: 報名被拒絕且有訊息模板時發送模板訊息
      Given 訊息模板 #101 的內容為 "很抱歉，您的報名已被拒絕"
      And 訊息模板 #101 的 content_type 為 "plain_text"
      When 報名 #50 的狀態從 "pending" 變為 "rejected"
      Then 系統應發送 LINE 文字訊息給用戶 "U1234"
      And 系統應額外發送一則包含 "很抱歉，您的報名已被拒絕" 的訊息

  Rule: 後置（狀態）- 沒有訊息模板時只發送基本通知

    Example: 報名取消但無對應訊息模板
      When 報名 #50 的狀態從 "pending" 變為 "cancelled"
      Then 系統應發送 LINE 文字訊息給用戶 "U1234"
      And 系統不應發送額外的模板訊息
