# LINE 用戶

## 描述
透過 LINE LIFF App 瀏覽活動並報名的終端使用者。用戶點擊推廣連結後進入 LIFF App，系統取得其 LINE Profile 資訊並發送活動 Carousel。用戶透過 Carousel 的 Postback 按鈕完成報名。

## 關鍵屬性
- 以 LINE userId 作為身份識別（identity_provider = line）
- 透過 LIFF SDK 取得 Profile（userId, displayName, pictureUrl）
- 不需要 WordPress 帳號
