# `.github/` 目錄架構指引（Power Funnel）

> **用途**：本文件描述 power-funnel 專案 `.github/` 目錄的組織與設計，作為新功能/維護時的快速導引。
>
> **範本來源**：以 power-course 的 `.github/` 為通用範本，已針對 power-funnel 特性 adapt。
>
> **核心哲學**：CI-driven AI Agent Pipeline — 透過 workflow 層級串接多個 agent（clarifier → planner → tdd-coordinator → browser-tester），agent 之間以 **Git commit**、**GitHub Issue comment**、**step outputs** 為橋樑。

---

## 一、目錄結構

```
.github/
├── workflows/
│   ├── pipe.yml      # 主 pipeline：claude job + integration-tests job
│   ├── pipe.md       # 本 workflow 的中文規格書
│   ├── issue.yml     # Issue 開啟/編輯時的需求展開（issue-creator）
│   └── release.yml   # push tag 觸發的自動 build + GitHub Release
├── act/
│   └── test.yml      # 本機 act 測試用（刻意置於 workflows 之外，避免 GitHub 誤觸發）
├── actions/
│   └── claude-retry/action.yml   # claude-code-action 的 3 次重試包裝
├── prompts/
│   ├── clarifier-interactive.md  # 互動澄清（≥5 題）
│   ├── clarifier-pipeline.md     # 已澄清 → 直接生 specs
│   ├── planner.md                # 規劃實作計畫
│   └── tdd-coordinator.md        # TDD 實作協調
├── templates/
│   ├── pipeline-upgrade-comment.md  # 動態升級通知
│   ├── test-result-comment.md       # PHPUnit 結果
│   └── acceptance-comment.md        # AI 驗收報告
├── scripts/
│   └── upload-to-bunny.sh           # 上傳截圖/影片到 Bunny CDN
├── ISSUE_TEMPLATE/                  # （既有，保留）
└── instruction.md                   # 本文件
```

---

## 二、power-funnel 與通用範本的差異速查

| 項目 | 通用範本（power-course） | power-funnel 實際 |
|------|--------------------------|-------------------|
| `.wp-env.json` port | 8895 / 8905 | **8894 / 8904** |
| wp-env plugin mapping | `wp-content/plugins/wp-power-course`（mapping 重命名） | `wp-content/plugins/power-funnel`（依套件目錄名） |
| 前端 build 指令 | `pnpm run build && pnpm run build:wp` | **`pnpm run build`**（單一） |
| LC Bypass | 動態注入 `'lc' => false` | **整段移除**（plugin.php 已預設） |
| Plugin slug | `power-course` | `power-funnel` |
| Text domain | `power-course`（連字號） | `power_funnel`（**底線**） |
| Admin SPA 路由 | HashRouter `#/courses, #/teachers...` | Powerhouse 頂層 `?page=power-funnel`，CPT 走 `edit.php?post_type=pf_*` |
| 業務描述 | LMS 線上課程 | LINE 報名漏斗 + ReactFlow 工作流引擎 |
| AI 驗收 prompt CPT 入口 | n/a | `pf_promo_link` / `pf_registration` / `pf_workflow_rule` / `pf_workflow` 列表頁 |

---

## 三、Workflows 簡介

### 3.1 `pipe.yml` — 主 pipeline

詳見 `pipe.md`。觸發 `@claude` 留言時：
- `claude` job：clarifier → planner → tdd-coordinator
- `integration-tests` job：PHPUnit 3 循環 + AI 驗收 + 自動 PR

### 3.2 `issue.yml` — Issue 需求展開

Issue `opened` / `edited` 時，body 含 `@claude` 且帶關鍵字（展開/探討/dev/工程/領域）→ 執行 issue-creator skill：
- `dev` 模式：產出技術導向的需求文件
- 預設 PM 模式：產出客服/PM 導向的用戶旅程

含重複展開防護（HTML 註解標記 `<!-- issue-creator-expanded -->`）。

### 3.3 `.github/act/test.yml` — 本機結構驗證

用 `act` CLI 驗多 job 依賴與 artifact 傳遞，不啟動 wp-env / Claude（皆 mock）。
**刻意置於 `.github/act/` 而非 `.github/workflows/`**，確保 GitHub 不會在遠端觸發此 workflow；僅供本地 `act -W .github/act/test.yml` 執行。

---

## 四、Docker 防雷重點（已內建於 pipe.yml）

絕不可省略：

1. **wp-env start 3 次重試**：delays 15/45/90s + unhealthy 容器 `docker restart` recovery
2. **uploads 目錄前置處理**：wp-env start 前 `sudo rm -rf` + `mkdir -p` + `chmod 777`，避免 Docker 以 root 建立
3. **Composer 主機端安裝**：在 `composer install` 後再 `wp-env start`
4. **`set -o pipefail` + `tee`**：確保 `wp-env run | tee` 失敗碼能被捕捉
5. **`--env-cwd=wp-content/plugins/power-funnel`**：路徑必須對齊 `.wp-env.json` 的實際 mapping
6. **強制 git HTTPS**：避免 plugin 安裝走 SSH 失敗
7. **`fetch-depth: 0`**（claude job） / `50`（integration-tests job）：保留歷史供 `git diff origin/master..HEAD` 比對
8. **Playwright `--with-deps`** + CJK 字型 `fonts-noto-cjk`：截圖中文不缺字
9. **unhealthy 容器 recovery**：`tests-mysql` 偶發初始化競態，需重啟單一容器再 retry

---

## 五、必備 Secrets（repo settings）

| Secret | 用途 | 必備？ |
|--------|------|--------|
| `CLAUDE_CODE_OAUTH_TOKEN` | Claude Code Action 授權 | ✅ |
| `GITHUB_TOKEN` | Actions 預設提供 | ✅（自動） |
| `BUNNY_STORAGE_HOST` | Bunny CDN 上傳 endpoint | 選配（無則跳過上傳） |
| `BUNNY_STORAGE_ZONE` | Bunny storage zone 名稱 | 選配 |
| `BUNNY_STORAGE_PASSWORD` | Bunny AccessKey | 選配 |
| `BUNNY_CDN_URL` | Bunny CDN 公開 URL | 選配 |

---

## 六、修改維護指引

### 新增 prompt
1. 在 `prompts/` 建 `.md` 檔，使用 `{{PLACEHOLDER}}` 雙大括號全大寫佔位符
2. 在 `pipe.yml` 對應 step 用 `sed "s/{{ISSUE_NUM}}/${ISSUE_NUM}/g"` 注入

### 新增 comment template
1. 在 `templates/` 建 `.md` 檔，開頭以 `<!-- ... -->` 註解標明用途、佔位符清單
2. 在 `pipe.yml` 用 `actions/github-script@v7` 的 `renderTemplate()` 函式注入
3. 條件邏輯**全部放在 JS 端**，模板只負責插值

### 修改 Stage gating
- 涉及 B/D/E/F/G 五段時務必一起看
- 改動 `outputs:` 後檢查所有 `needs.claude.outputs.<key>` 引用
- step `id` 拼錯只會在 runtime 得到空字串，不會 yml validation fail，請格外小心

### 改 wp-env port
1. `.wp-env.json` 改 port
2. `pipe.yml` AI 驗收 prompt 內 `http://localhost:8894` 同步更新

### 新增 CPT
1. `inc/classes/Infrastructure/Repositories/{Name}/Register.php` 註冊
2. `pipe.yml` AI 驗收 prompt 的「CPT 管理入口」列表同步加入 `edit.php?post_type=pf_xxx`
