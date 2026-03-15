@ignore @command
Feature: 發送已報名通知

  當用戶重複報名同一活動時，系統發送 LINE 文字訊息告知用戶已報名。
  此功能由 RegisterActivityViaLine::check_registered 驅動。

  Background:
    Given LINE 設定已完成
    And 系統中有以下活動：
      | id    | title        |
      | yt001 | React 直播教學 |
    And LINE 用戶 "U1234" 已報名活動 "yt001"（identity_provider = line）

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 發送已報名的 LINE 文字訊息

    Example: 重複報名時發送通知
      Given LINE 用戶 "U1234" 再次嘗試報名活動 "yt001"
      When 系統檢查報名資格
      Then 系統應透過 LINE Push Message 發送文字訊息給用戶 "U1234"
      And 訊息內容應包含活動標題
