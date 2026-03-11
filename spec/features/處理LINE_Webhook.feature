@ignore
Feature: 處理 LINE Webhook

  接收 LINE Platform 發送的 Webhook 事件，驗證簽章後分發至對應 handler。

  Background:
    Given LINE 設定已完成，包含：
      | channel_id           | 1234567890    |
      | channel_secret       | secret123     |
      | channel_access_token | token123      |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- LINE 設定必須完整

    Example: LINE 設定未完成時拋出異常
      Given LINE 設定的 channel_access_token 為空
      When LINE Platform 發送 Webhook 事件到 POST /wp-json/power-funnel/line-callback
      Then 系統應拋出異常 "LINE 設定尚未完成"

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須包含 X-Line-Signature header

    Example: 缺少簽章 header 時拋出異常
      When LINE Platform 發送 Webhook 事件到 POST /wp-json/power-funnel/line-callback
      And 請求不包含 X-Line-Signature header
      Then 系統應拋出異常 "缺少 LINE 簽章標頭"

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功解析並分發事件

    Example: Postback 事件觸發對應 action
      Given LINE 用戶 "U1234" 發送 Postback 事件
      And Postback 資料為：
        | action   | activity_id | promo_link_id |
        | register | yt001       | 10            |
      When LINE Platform 發送 Webhook 事件到 POST /wp-json/power-funnel/line-callback
      And 請求包含有效的 X-Line-Signature header
      Then 回應狀態碼應為 200
      And 回應 body 應為 {"status": "ok"}
      And 系統應觸發 action "power_funnel/line/webhook/postback/register"

    Example: 文字訊息事件記錄到 logger
      Given LINE 用戶 "U1234" 發送文字訊息 "你好"
      When LINE Platform 發送 Webhook 事件到 POST /wp-json/power-funnel/line-callback
      And 請求包含有效的 X-Line-Signature header
      Then 回應狀態碼應為 200
      And 系統應記錄 info 等級的 log
