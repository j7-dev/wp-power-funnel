@ignore @command
Feature: 發送 LINE 活動 Carousel

  LIFF 回調觸發後，系統根據推廣連結的篩選條件查詢活動，
  組裝 LINE Carousel Template Message 並透過 Push Message 發送給用戶。
  每張 Carousel Card 包含活動縮圖、標題、描述與 Postback 報名按鈕。

  Background:
    Given LINE 設定已完成
    And YouTube OAuth 已授權
    And 系統中有以下推廣連結：
      | id | name       | keyword | last_n_days | action_label |
      | 10 | 三月推廣連結 | React   | 30          | 立即報名       |
    And YouTube API 回傳以下直播場次：
      | id    | title          | scheduled_start_time | thumbnail_url               |
      | yt001 | React 直播教學   | 2026-03-20T10:00:00  | https://i.ytimg.com/yt001.jpg |
      | yt002 | Vue 直播教學     | 2026-03-25T14:00:00  | https://i.ytimg.com/yt002.jpg |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- promoLinkId 不可為空

    Example: promoLinkId 為空時不發送
      Given LIFF 回調的 urlParams 不含 promoLinkId
      When 系統處理 power_funnel/liff_callback action
      Then 系統不應發送任何 LINE 訊息

  Rule: 前置（參數）- promoLinkId 必須為字串

    Example: promoLinkId 為非字串時不發送
      Given LIFF 回調的 urlParams.promoLinkId 為數字 10
      When 系統處理 power_funnel/liff_callback action
      Then 系統不應發送任何 LINE 訊息

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 篩選後的活動列表不可為空

    Example: 篩選結果為空時不發送
      Given 推廣連結 #10 的 keyword 為 "Angular"
      And 沒有標題含 "Angular" 的活動
      When 系統處理 power_funnel/liff_callback action（promoLinkId="10"）
      Then 系統不應發送任何 LINE 訊息

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 發送 Carousel Template Message

    Example: 成功發送活動 Carousel
      Given LINE 用戶 "U1234" 觸發 LIFF 回調（promoLinkId="10"）
      When 系統處理 power_funnel/liff_callback action
      Then 系統應透過 LINE Push Message 發送 Carousel Template 給用戶 "U1234"
      And Carousel 應只包含標題含 "React" 的活動
      And 每張 Card 應包含：
        | 欄位                  | 值                    |
        | thumbnailImageUrl    | 活動縮圖 URL            |
        | title                | 活動標題               |
        | text                 | 活動描述               |
        | actions[0].type      | postback              |
        | actions[0].label     | 立即報名               |
      And Postback data 應為 JSON 包含：
        | key            | 值             |
        | action         | register       |
        | activity_id    | 活動 ID         |
        | promo_link_id  | 10             |
