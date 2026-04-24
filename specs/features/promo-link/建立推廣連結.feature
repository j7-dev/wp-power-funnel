@ignore @command
Feature: 建立推廣連結

  管理員在後台建立新的推廣連結（PromoLink），
  用於產生 LIFF URL 讓 LINE 用戶點擊後收到活動 Carousel。

  Background:
    Given 管理員已登入 WordPress 後台

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 建立 pf_promo_link CPT

    Example: 建立新推廣連結
      When 管理員透過 Refine.dev 前端建立推廣連結
      And 名稱為 "新 LINE 連結"
      Then 系統應建立一筆 pf_promo_link CPT
      And post_title 應為 "新 LINE 連結"
      And post_type 應為 "pf_promo_link"
