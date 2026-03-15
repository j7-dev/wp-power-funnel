@ignore @command
Feature: LINE 報名活動

  用戶透過 LINE Carousel 的 Postback 按鈕報名指定活動。
  系統驗證報名資格後建立 pending 狀態的報名紀錄，
  並觸發報名生命週期事件。

  Background:
    Given 系統中有以下活動提供商：
      | provider_id | type    |
      | youtube     | YouTube |
    And 系統中有以下活動：
      | id    | title          | scheduled_start_time |
      | yt001 | React 直播教學   | 2026-03-20T10:00:00  |
    And 系統中有以下推廣連結：
      | id | name       | keyword | last_n_days |
      | 10 | 三月推廣連結 | React   | 30          |
    And LINE 設定已完成

  # ========== 前置（參數）==========

  Rule: 前置（參數）- activity_id 與 identity_id 不可為空

    Example: activity_id 為空時報名失敗
      Given LINE 用戶 "U1234" 發送 Postback 事件
      And Postback 資料為：
        | action   | activity_id | promo_link_id |
        | register |             | 10            |
      When 系統處理 LINE Webhook Postback 事件
      Then 系統應拋出異常包含 "無法取得"

    Example: identity_id 無法取得時報名失敗
      Given LINE 事件缺少 source.userId
      And Postback 資料為：
        | action   | activity_id | promo_link_id |
        | register | yt001       | 10            |
      When 系統處理 LINE Webhook Postback 事件
      Then 系統應拋出異常包含 "無法取得"

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 活動必須存在

    Example: 活動不存在時報名失敗
      Given LINE 用戶 "U1234" 發送 Postback 事件
      And Postback 資料為：
        | action   | activity_id  | promo_link_id |
        | register | non_existent | 10            |
      When 系統處理 LINE Webhook Postback 事件
      Then 系統應拋出異常 "找不到活動 #non_existent"

  Rule: 前置（狀態）- 用戶不可重複報名同一活動

    Example: 已報名過的用戶再次報名時收到已報名通知
      Given LINE 用戶 "U1234" 已報名活動 "yt001"
      And LINE 用戶 "U1234" 發送 Postback 事件
      And Postback 資料為：
        | action   | activity_id | promo_link_id |
        | register | yt001       | 10            |
      When 系統處理 LINE Webhook Postback 事件
      Then 系統不應建立新的報名紀錄
      And 系統應發送 LINE 文字訊息給用戶 "U1234" 表示已報名

  Rule: 前置（狀態）- can_register filter 必須回傳 true

    Example: filter 回傳 false 時不報名
      Given power_funnel/registration/can_register filter 回傳 false
      When 系統處理 LINE Webhook Postback 事件
      Then 系統不應建立新的報名紀錄

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功報名後建立 pending 狀態的報名紀錄

    Example: 首次報名成功
      Given LINE 用戶 "U1234" 發送 Postback 事件
      And Postback 資料為：
        | action   | activity_id | promo_link_id |
        | register | yt001       | 10            |
      When 系統處理 LINE Webhook Postback 事件
      Then 系統應建立一筆 pf_registration 紀錄
      And 報名紀錄的狀態應為 "pending"
      And 報名紀錄的 activity_id 應為 "yt001"
      And 報名紀錄的 identity_id 應為 "U1234"
      And 報名紀錄的 identity_provider 應為 "line"
      And 報名紀錄的 promo_link_id 應為 "10"
