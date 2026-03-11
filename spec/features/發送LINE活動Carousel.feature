@ignore
Feature: 發送 LINE 活動 Carousel

  用戶打開 LIFF App 後，系統根據推廣連結的篩選條件，
  透過 LINE Push Message 發送 Carousel Template 活動列表給用戶。

  Background:
    Given 系統中有以下活動：
      | id    | title          | description    | thumbnail_url                  | scheduled_start_time |
      | yt001 | React 直播教學   | React 入門教學   | https://i.ytimg.com/vi/1/sd.jpg | 2026-03-20T10:00:00  |
      | yt002 | Vue 直播教學     | Vue 進階教學     | https://i.ytimg.com/vi/2/sd.jpg | 2026-03-25T10:00:00  |
      | yt003 | PHP 直播教學     | PHP 基礎教學     | https://i.ytimg.com/vi/3/sd.jpg | 2026-04-15T10:00:00  |
    And 系統中有以下推廣連結：
      | id | name       | keyword | last_n_days | alt_text       | action_label |
      | 10 | 三月推廣連結 | React   | 30          |                | 立即報名       |
      | 11 | 全部活動     |         | 0           | 所有的活動       | 馬上報名       |
    And LINE 設定已完成

  # ========== 前置（參數）==========

  Rule: 前置（參數）- promoLinkId 不可為空

    Example: 缺少 promoLinkId 時不發送訊息
      Given LINE 用戶 "U1234" 透過 LIFF App 傳送資料
      And urlParams 不包含 promoLinkId
      When 系統處理 LIFF 回調
      Then 系統不應發送任何 LINE 訊息

    Example: promoLinkId 不是字串時不發送訊息
      Given LINE 用戶 "U1234" 透過 LIFF App 傳送資料
      And urlParams 的 promoLinkId 為數字 10
      When 系統處理 LIFF 回調
      Then 系統不應發送任何 LINE 訊息

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 篩選後的活動列表不可為空

    Example: 無符合條件的活動時不發送訊息
      Given 系統中有以下推廣連結：
        | id | name         | keyword    | last_n_days |
        | 12 | 無結果推廣連結 | 不存在的關鍵字 | 1           |
      And LINE 用戶 "U1234" 透過 LIFF App 傳送資料
      And urlParams 的 promoLinkId 為 "12"
      When 系統處理 LIFF 回調
      Then 系統不應發送任何 LINE 訊息

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功發送 Carousel Template 給用戶

    Example: 根據推廣連結篩選條件發送活動 Carousel
      Given LINE 用戶 "U1234" 透過 LIFF App 傳送資料
      And urlParams 的 promoLinkId 為 "10"
      When 系統處理 LIFF 回調
      Then 系統應發送 LINE Carousel Template 給用戶 "U1234"
      And Carousel 應包含以下活動：
        | title        |
        | React 直播教學 |
      And 每個 Carousel Column 的 action 類型應為 "postback"
      And 每個 Carousel Column 的 action label 應為 "立即報名"

    Example: 無篩選條件時發送所有活動
      Given LINE 用戶 "U5678" 透過 LIFF App 傳送資料
      And urlParams 的 promoLinkId 為 "11"
      When 系統處理 LIFF 回調
      Then 系統應發送 LINE Carousel Template 給用戶 "U5678"
      And Carousel 的 altText 應為 "所有的活動"
