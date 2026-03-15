@ignore @command
Feature: 發送報名狀態 LINE 通知

  報名狀態改變時（pending/success/rejected/failed/cancelled），
  系統發送 LINE 文字通知給報名用戶。
  若推廣連結有設定該狀態的訊息模板 ID，則額外發送模板訊息。

  Background:
    Given LINE 設定已完成
    And 系統中有以下活動：
      | id    | title        |
      | yt001 | React 直播教學 |
    And 系統中有以下報名紀錄：
      | id | activity_id | identity_id | identity_provider | promo_link_id | post_status |
      | 50 | yt001       | U1234       | line              | 10            | pending     |

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 狀態改變時發送基本文字通知

    Example: 報名進入 pending 時發送通知
      When 報名 #50 狀態轉為 "pending"
      Then 系統應透過 LINE Push Message 發送文字訊息給用戶 "U1234"
      And 訊息內容應包含 "已收到您 React 直播教學 的報名"

  Rule: 後置（狀態）- 推廣連結有訊息模板時額外發送模板訊息

    Example: 推廣連結設定了 pending 狀態的訊息模板
      Given 推廣連結 #10 的 message_tpl_ids 包含：
        | status  | message_tpl_id |
        | pending | 100            |
      And 訊息模板 #100 的 content_type 為 "plain_text"
      And 訊息模板 #100 的 content 為 "感謝您報名 {{activity.title}}"
      When 報名 #50 狀態轉為 "pending"
      Then 系統應額外發送 LINE 文字訊息 "感謝您報名 React 直播教學"

  Rule: 後置（狀態）- 訊息模板不存在或非純文字時不發送額外訊息

    Example: 訊息模板不存在
      Given 推廣連結 #10 的 message_tpl_ids 不包含 pending 狀態
      When 報名 #50 狀態轉為 "pending"
      Then 系統應只發送基本文字通知
      And 不應發送額外的模板訊息

  Rule: 後置（狀態）- 發送失敗時記錄錯誤日誌

    Example: LINE API 發送失敗
      Given LINE Messaging API 發送失敗
      When 報名 #50 狀態轉為 "pending"
      Then 系統應記錄 WC Logger 錯誤日誌
