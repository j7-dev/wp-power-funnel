# Event Storming: Power Funnel

> 自動抓取 YouTube 直播場次，讓用戶可以透過 LINE 報名活動；並提供工作流引擎，在觸發條件滿足時自動執行節點動作（發 Email、等待等）。
> **版本:** 0.0.1 | **文件日期:** 2026-03-11

---

## Actors

- **管理員** [人]: WordPress 後台管理者，設定 LINE/YouTube API、管理推廣連結、審核報名、設計工作流規則
- **LINE 用戶** [人]: 透過 LINE LIFF App 瀏覽活動並報名
- **LINE Platform** [外部系統]: LINE Messaging API / Webhook，負責傳遞用戶互動事件
- **YouTube Data API** [外部系統]: 提供直播場次資料
- **Google OAuth** [外部系統]: 處理 YouTube API 的 OAuth 2.0 授權流程
- **Action Scheduler** [系統]: WordPress 背景排程引擎，處理延遲節點執行
- **WordPress** [系統]: 提供 CPT、wp_options、wp_mail 等基礎設施

---

## Aggregates

### Activity（活動）
> 來自外部活動提供商（目前僅 YouTube），非 WP_Post，存在於記憶體中

| 屬性 | 說明 |
|------|------|
| id | 活動 ID（YouTube video ID） |
| activity_provider_id | 活動提供商 ID（`youtube`） |
| title | 活動標題 |
| description | 活動描述 |
| thumbnail_url | 縮圖 URL |
| scheduled_start_time | 排程開始時間 |
| meta | 額外中繼資料 |

### PromoLink（推廣連結）
> CPT: `pf_promo_link`，管理員建立的推廣連結，綁定活動篩選條件與 LINE Carousel 顯示參數

| 屬性 | 說明 |
|------|------|
| id | 文章 ID |
| name | 推廣連結名稱（post_title） |
| link_provider | 連結提供商（預設 `line`） |
| keyword | 篩選活動標題關鍵字 |
| last_n_days | 篩選未來 N 天內的活動 |
| alt_text | LINE Carousel 替代文字 |
| action_label | LINE 動作按鈕文字（預設「立即報名」） |
| message_tpl_ids | 各報名狀態對應的訊息模板 ID map |

### Registration（活動報名）
> CPT: `pf_registration`，記錄用戶的活動報名，含自訂狀態生命週期

| 屬性 | 說明 |
|------|------|
| id | 文章 ID |
| activity_id | 關聯活動 ID（post_meta） |
| identity_id | 用戶識別 ID（LINE userId 或 WP user ID）（post_meta） |
| identity_provider | 用戶識別提供商（`line` / `WordPress`）（post_meta） |
| promo_link_id | 從哪個推廣連結報名的（post_meta） |
| auto_approved | 是否自動審核通過（post_meta） |
| post_status | 報名狀態：pending / success / rejected / failed / cancelled |

### WorkflowRule（工作流規則）
> CPT: `pf_workflow_rule`，管理員設計的工作流模板，含節點定義與觸發點

| 屬性 | 說明 |
|------|------|
| id | 文章 ID |
| name | 規則名稱（post_title） |
| trigger_point | 觸發時機 hook name（post_meta） |
| nodes | 節點陣列 `NodeDTO[]`（post_meta） |
| post_status | 規則狀態：publish / draft / trash |

### Workflow（工作流實例）
> CPT: `pf_workflow`，由 WorkflowRule 觸發後建立的執行實例

| 屬性 | 說明 |
|------|------|
| id | 文章 ID |
| name | 工作流名稱（post_title） |
| workflow_rule_id | 來源規則 ID（post_meta） |
| trigger_point | 觸發時機 hook name（post_meta） |
| nodes | 節點陣列 `NodeDTO[]`（post_meta） |
| context_callable_set | 上下文取得方式（post_meta） |
| results | 執行結果 `WorkflowResultDTO[]`（post_meta） |
| post_status | 執行狀態：running / completed / failed |

### Settings（設定）
> wp_options，儲存 LINE 與 YouTube API 設定

| Option Name | 說明 |
|-------------|------|
| `_power_funnel_line_setting` | LINE Messaging API 設定（liff_id, liff_url, channel_id, channel_secret, channel_access_token） |
| `_power_funnel_youtube_setting` | YouTube API 設定（clientId, clientSecret, redirectUri） |
| `_power_funnel_youtube_oauth_token` | YouTube OAuth Token（access_token, refresh_token, expires_in, created） |
| `_power_funnel_installed_version` | 已安裝版本號（相容性管理用） |

---

## Commands

### RegisterActivityViaLine（LINE 報名活動）
- **Actor**: LINE 用戶（透過 LINE Postback）
- **Aggregate**: Registration
- **Predecessors**: SendLineCarousel
- **參數**: activity_id, identity_id (LINE userId), promo_link_id, identity_provider
- **Description**
  - What: 用戶透過 LINE Carousel 點擊報名按鈕，系統建立一筆 pending 狀態的報名紀錄
  - Why: 讓用戶透過 LINE 完成活動報名
  - When: LINE Postback 事件觸發 `power_funnel/line/webhook/postback/register`

#### Rules
- 前置（參數）: activity_id 與 identity_id 不可為空
- 前置（狀態）: 活動必須存在（ActivityService 可查到）
- 前置（狀態）: 用戶不可重複報名同一活動（已報名則發送「已報名」通知後中止）
- 前置（狀態）: `power_funnel/registration/can_register` filter 必須回傳 true
- 後置（狀態）: 建立 `pf_registration` CPT，狀態為 `pending`
- 後置（狀態）: 觸發 `power_funnel/registration/pending` action
- 後置（狀態）: 若 auto_approved 為 true，自動將狀態轉為 `success`

### AutoApproveRegistration（自動審核報名）
- **Actor**: WordPress（系統觸發）
- **Aggregate**: Registration
- **Predecessors**: RegisterActivityViaLine
- **參數**: registration post ID
- **Description**
  - What: 報名進入 pending 狀態後，若設定自動審核，則自動轉為 success
  - Why: 簡化不需人工審核的報名流程
  - When: `power_funnel/registration/pending` action 觸發（priority 20）

#### Rules
- 前置（狀態）: Registration 的 auto_approved 必須為 true
- 後置（狀態）: Registration 狀態從 pending 轉為 success

### SendLineCarousel（發送 LINE Carousel 活動列表）
- **Actor**: LINE 用戶（透過 LIFF App）
- **Aggregate**: PromoLink, Activity
- **Predecessors**: 無（用戶打開 LIFF App）
- **參數**: LINE ProfileDTO (userId, name...), urlParams.promoLinkId
- **Description**
  - What: 用戶打開 LIFF App 後，系統根據推廣連結的篩選條件，發送活動 Carousel 訊息給用戶
  - Why: 讓用戶在 LINE 中瀏覽可報名的活動
  - When: `power_funnel/liff_callback` action 觸發

#### Rules
- 前置（參數）: promoLinkId 不可為空且必須為字串
- 前置（狀態）: PromoLink 必須存在
- 前置（狀態）: 篩選後的活動列表不可為空
- 後置（狀態）: 透過 LINE Push Message 發送 Carousel Template 給用戶

### SendRegistrationLineNotification（發送報名狀態 LINE 通知）
- **Actor**: WordPress（系統觸發）
- **Aggregate**: Registration
- **Predecessors**: RegisterActivityViaLine, AutoApproveRegistration
- **參數**: new_status, old_status, registration post
- **Description**
  - What: 報名狀態改變時，發送 LINE 文字通知給報名用戶
  - Why: 讓用戶即時知道報名狀態變化
  - When: `power_funnel/registration/{status}` action 觸發（priority 10）

#### Rules
- 前置（狀態）: Registration 必須可解析為 RegistrationDTO
- 前置（狀態）: 若 PromoLink 有設定該狀態的訊息模板 ID，則額外發送模板訊息
- 後置（狀態）: 透過 LINE Push Message 發送文字訊息給用戶

### SaveOptions（儲存設定）
- **Actor**: 管理員
- **Aggregate**: Settings
- **Predecessors**: 無
- **參數**: line (SettingDTO 欄位), youtube (SettingDTO 欄位)
- **Description**
  - What: 管理員透過後台設定頁面儲存 LINE 和 YouTube API 設定
  - Why: 設定外部服務的連線參數
  - When: POST `/wp-json/power-funnel/options`

#### Rules
- 前置（參數）: body 必須包含對應的 EOptionName key
- 前置（參數）: 值必須為 array
- 後置（狀態）: 對應的 wp_option 被更新

### RevokeGoogleOAuth（撤銷 Google OAuth 授權）
- **Actor**: 管理員
- **Aggregate**: Settings
- **Predecessors**: 無
- **參數**: 無
- **Description**
  - What: 管理員撤銷 YouTube 的 Google OAuth 授權
  - Why: 需要重新授權或解除綁定
  - When: POST `/wp-json/power-funnel/revoke-google-oauth`

#### Rules
- 後置（狀態）: `_power_funnel_youtube_oauth_token` option 被刪除
- 後置（狀態）: YoutubeService 標記為未授權

### HandleLineWebhook（處理 LINE Webhook）
- **Actor**: LINE Platform（外部系統）
- **Aggregate**: 無（事件分發器）
- **Predecessors**: 無
- **參數**: LINE Webhook 事件（含簽章）
- **Description**
  - What: 接收 LINE Platform 發送的 Webhook 事件，驗證簽章後分發至對應 handler
  - Why: 作為 LINE 互動事件的入口
  - When: POST `/wp-json/power-funnel/line-callback`

#### Rules
- 前置（狀態）: LINE 設定必須完整（channel_access_token, channel_id, channel_secret）
- 前置（參數）: 必須包含 `X-Line-Signature` header
- 前置（參數）: 簽章驗證必須通過
- 後置（狀態）: 觸發 `power_funnel/line/webhook/{eventType}/{action}` action

### HandleLiffCallback（處理 LIFF 回調）
- **Actor**: LINE 用戶（透過 LIFF App）
- **Aggregate**: 無（事件分發器）
- **Predecessors**: 無
- **參數**: LINE Profile 資料, URL 參數
- **Description**
  - What: 接收 LIFF App 傳送的用戶資料與 URL 參數，分發至對應 handler
  - Why: 作為 LIFF App 前端與後端的橋接
  - When: POST `/wp-json/power-funnel/liff`

#### Rules
- 後置（狀態）: 觸發 `power_funnel/liff_callback` action，傳入 ProfileDTO 與 url_params

### CreateWorkflowFromRule（從規則建立工作流實例）
- **Actor**: WordPress（系統觸發）
- **Aggregate**: Workflow, WorkflowRule
- **Predecessors**: 觸發點 hook 被觸發
- **參數**: workflow_rule_id, context_callable_set
- **Description**
  - What: 當 WorkflowRule 的 trigger_point hook 被觸發時，建立一個新的 Workflow 實例並開始執行
  - Why: 自動化流程引擎的核心功能
  - When: 已發布的 WorkflowRule 註冊的 trigger_point hook 被觸發

#### Rules
- 前置（狀態）: WorkflowRule 必須已發布（publish）
- 後置（狀態）: 建立 `pf_workflow` CPT，狀態為 `running`
- 後置（狀態）: 觸發 `power_funnel/workflow/running` action，開始執行節點

### ExecuteWorkflowNode（執行工作流節點）
- **Actor**: WordPress（系統觸發）
- **Aggregate**: Workflow
- **Predecessors**: CreateWorkflowFromRule 或前一個節點完成
- **參數**: workflow_id
- **Description**
  - What: 依序執行 Workflow 中的下一個節點，記錄執行結果
  - Why: 逐步完成工作流的自動化流程
  - When: `power_funnel/workflow/running` action 觸發

#### Rules
- 前置（狀態）: Workflow 狀態必須為 running
- 前置（狀態）: 若無下一個節點，將狀態設為 completed
- 前置（狀態）: 節點的 match_callback 必須回傳 true（否則跳過，code 301）
- 前置（狀態）: 節點的 node_definition_id 必須能找到對應的 NodeDefinition
- 後置（狀態）: 節點執行結果記入 results（post_meta）
- 後置（狀態）: 執行成功（code 200）→ do_next() 繼續下一節點
- 後置（狀態）: 執行失敗（code 500）→ Workflow 狀態設為 failed

---

## Read Models

### GetActivities（查詢活動列表）
- **Actor**: 管理員
- **Aggregates**: Activity
- **回傳欄位**: id, activity_provider_id, title, description, thumbnail_url, scheduled_start_time
- **Description**: 從所有活動提供商取得活動列表，支援 keyword 與 last_n_days 篩選

#### Rules
- 前置（參數）: 可選 keyword（標題關鍵字）、last_n_days（未來 N 天）、id（指定活動 ID）
- 後置（回應）: 僅回傳排程時間在未來的活動（可報名活動）
- 後置（回應）: 若指定 id 且找到，回傳單一元素陣列
- 後置（回應）: 若同時指定 keyword 與 last_n_days，取交集

### GetOptions（查詢設定）
- **Actor**: 管理員
- **Aggregates**: Settings
- **回傳欄位**: line (SettingDTO), youtube (SettingDTO), googleOauth (isAuthorized, authUrl)
- **Description**: 取得所有系統設定，包含 LINE、YouTube API 設定與 Google OAuth 授權狀態

#### Rules
- 後置（回應）: 回傳所有 EOptionName 對應的設定值
- 後置（回應）: Google OAuth 區段包含 isAuthorized 布林值與 authUrl 授權連結
