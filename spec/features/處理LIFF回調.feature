@ignore
Feature: 處理 LIFF 回調

  接收 LIFF App 傳送的用戶 Profile 資料與 URL 參數，
  觸發 power_funnel/liff_callback action 供其他模組處理。

  Background:
    Given LINE 設定已完成

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功觸發 liff_callback action

    Example: 完整的 LIFF 資料觸發回調
      When LIFF App 發送 POST /wp-json/power-funnel/liff 請求：
        """json
        {
          "userId": "U1234",
          "name": "測試用戶",
          "picture": "https://example.com/avatar.jpg",
          "os": "iOS",
          "isInClient": true,
          "isLoggedIn": true,
          "urlParams": {
            "promoLinkId": "10"
          }
        }
        """
      Then 回應狀態碼應為 200
      And 回應的 code 應為 "success"
      And 系統應觸發 action "power_funnel/liff_callback"
      And action 傳入的 ProfileDTO 的 userId 應為 "U1234"
      And action 傳入的 urlParams 的 promoLinkId 應為 "10"

    Example: 缺少 urlParams 時仍可正常處理
      When LIFF App 發送 POST /wp-json/power-funnel/liff 請求：
        """json
        {
          "userId": "U5678",
          "name": "另一個用戶",
          "isInClient": false,
          "isLoggedIn": true
        }
        """
      Then 回應狀態碼應為 200
      And 系統應觸發 action "power_funnel/liff_callback"
      And action 傳入的 urlParams 應為空陣列
