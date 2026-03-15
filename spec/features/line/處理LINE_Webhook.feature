@ignore @command
Feature: 處理 LINE Webhook

  接收 LINE Platform 發送的 Webhook 事件，驗證簽章後分發至對應 handler。
  此端點不需要 WordPress 登入驗證（permission_callback = __return_true）。
  事件根據 eventType 與 action 組合分發至對應的 WordPress action hook。

  Background:
    Given LINE 設定已完成：
      | key                  | value   |
      | channel_id           | CH01    |
      | channel_secret       | SEC01   |
      | channel_access_token | TOKEN01 |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- LINE 設定必須完整

    Example: LINE 設定未完成時拋出異常
      Given LINE 設定的 channel_access_token 為空
      When LINE Platform 發送 Webhook 至 POST /wp-json/power-funnel/line-callback
      Then 系統應拋出異常 "LINE 設定尚未完成"

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須包含 X-Line-Signature header

    Example: 缺少簽章 header
      Given Webhook 請求缺少 X-Line-Signature header
      When LINE Platform 發送 Webhook 至 POST /wp-json/power-funnel/line-callback
      Then 系統應拋出異常 "缺少 LINE 簽章標頭"

  Rule: 前置（參數）- 簽章驗證必須通過

    Example: 簽章不合法
      Given Webhook 請求的 X-Line-Signature 與 body 不匹配
      When LINE Platform 發送 Webhook 至 POST /wp-json/power-funnel/line-callback
      Then 系統應拋出簽章驗證失敗異常

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- Postback 事件觸發對應 action

    Example: 收到報名 Postback 事件
      Given Webhook body 包含一個 PostbackEvent：
        | source.userId | U1234                                                    |
        | type          | postback                                                 |
        | postback.data | {"action":"register","activity_id":"yt001","promo_link_id":"10"} |
      And 簽章驗證通過
      When LINE Platform 發送 Webhook 至 POST /wp-json/power-funnel/line-callback
      Then 系統應觸發 action "power_funnel/line/webhook/postback/register"
      And action 參數應為 LINE Event 物件

  Rule: 後置（狀態）- Message 事件記錄日誌

    Example: 收到文字訊息事件
      Given Webhook body 包含一個 MessageEvent（TextMessage）
      And 簽章驗證通過
      When LINE Platform 發送 Webhook 至 POST /wp-json/power-funnel/line-callback
      Then 系統應記錄 WC Logger 日誌
      And 回應 status 為 "ok"
