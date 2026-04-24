@ignore @command
Feature: 處理 LIFF 回調

  LIFF App 前端取得 LINE 用戶 Profile 後，
  將資料與 URL 參數傳送至後端 API。
  後端觸發 power_funnel/liff_callback action 進行後續處理。
  此端點不需要 WordPress 登入驗證（permission_callback = __return_true）。

  Background:
    Given LIFF App 已初始化（liff.init 成功）
    And LINE 用戶已登入

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 觸發 liff_callback action

    Example: LIFF 回調成功
      Given LINE 用戶 Profile 為：
        | userId | name | picture                | isInClient | isLoggedIn |
        | U1234  | 小明  | https://pic.example.com | true       | true       |
      And URL 參數為：
        | key         | value |
        | promoLinkId | 10    |
      When 前端發送 POST /wp-json/power-funnel/liff
      Then 回應 code 為 "success"
      And 系統應觸發 action "power_funnel/liff_callback"
      And action 參數應包含 ProfileDTO（userId="U1234"）
      And action 參數應包含 urlParams（promoLinkId="10"）
