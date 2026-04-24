@ignore @command
Feature: 編輯推廣連結

  管理員編輯推廣連結的活動篩選條件與 LINE Carousel 顯示參數。
  設定 keyword 與 last_n_days 決定用戶收到哪些活動。

  Background:
    Given 管理員已登入 WordPress 後台
    And 系統中有以下推廣連結：
      | id | name       | keyword | last_n_days | alt_text | action_label |
      | 10 | 三月推廣連結 |         | 0           |          | 立即報名       |

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 更新推廣連結的篩選條件

    Example: 設定 keyword 與 last_n_days
      When 管理員編輯推廣連結 #10
      And 設定 keyword 為 "React"
      And 設定 last_n_days 為 30
      Then 推廣連結 #10 的 keyword 應為 "React"
      And 推廣連結 #10 的 last_n_days 應為 30

  Rule: 後置（狀態）- 更新推廣連結的顯示參數

    Example: 設定 alt_text 與 action_label
      When 管理員編輯推廣連結 #10
      And 設定 alt_text 為 "三月活動推廣"
      And 設定 action_label 為 "馬上報名"
      Then 推廣連結 #10 的 alt_text 應為 "三月活動推廣"
      And 推廣連結 #10 的 action_label 應為 "馬上報名"
