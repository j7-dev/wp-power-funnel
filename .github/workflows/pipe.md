# `pipe.yml` 結構速查（Power Funnel）

> 對應檔案：`.github/workflows/pipe.yml`
> **兩個 Job**：`claude`（釐清 → 規劃 → 實作）→ `integration-tests`（測試 → 修復 → AI 驗收 → PR）
> **與 power-course 範本差異**：移除 LC Bypass（plugin.php 已預設 `'lc' => false`）、移除 `pnpm run build:wp`、`--env-cwd` 改為 `wp-content/plugins/power-funnel`、AI 驗收 prompt 對應 `pf_*` CPT 與 ReactFlow 工作流引擎業務描述。

---

## 一、觸發方式與模式對照

**觸發事件**：`issue_comment` / `pull_request_review_comment` / `pull_request_review`，body 須含 `@claude`。
**Concurrency**：同一 issue/PR 的新 `@claude` 會取消舊的。

### 關鍵字 → 模式對照

| 留言 | 開工（clarifier → tdd） | 整合測試 + AI 驗收 |
|------|------------------------|-------------------|
| `@claude`（需求還需釐清） | ❌ 僅提澄清問題 | ❌ |
| `@claude`（需求已清楚） | ✅ 由 clarifier 自動升級 pipeline 並一路跑到 tdd | ❌ 需再打 `@claude PR` |
| `@claude 開工`（含 確認/OK/沒問題/開始/go/start） | ✅ | ❌ 需再打 `@claude PR` |
| `@claude 全自動` | ✅ | ✅ 自動 |
| `@claude PR` | ❌ 跳過 | ✅ 於現有分支直接跑 |

**解析優先序**：`全自動` > `PR` > `開工等` > 互動。

---

## 二、Job 1：`claude`

**Runner** `ubuntu-latest` / **Timeout** 180 min / **Permissions**：`contents`/`pull-requests`/`issues: write`、`id-token: write`、`actions: read`

### Job Outputs

| output | 意義 |
|--------|------|
| `branch_name` / `issue_num` | 本輪 `issue/{N}-{timestamp}` 分支與 issue 編號 |
| `initial_sha` | 進入 workflow 時的 HEAD（用於偵測變更） |
| `claude_ok` | clarifier + (planner/tdd) 整體成敗；skipped 視為 OK |
| `has_changes` | 是否有 commit 或 working tree 變動 |
| `agent_name` | `clarifier` / `clarifier+planner` / `...+tdd-coordinator` / `pr-only` |
| `pipeline_mode` / `full_auto_mode` / `pr_mode` | 模式旗標 |
| `run_integration_tests` | `full_auto_mode OR pr_mode` → 控制 Job 2 觸發 |

### Steps 流程

| 段 | 核心動作 |
|----|---------|
| **A** 前置 | eyes reaction → checkout → `resolve_branch`（找或建 `issue/{N}-*`）→ HTTPS → `save_sha` |
| **B** 模式解析 | `parse_agent` 設 `PIPELINE_MODE`/`FULL_AUTO_MODE`/`PR_MODE` → `fetch_context`（issue 上下文）→ 組 clarifier prompt（`PR_MODE=true` 則跳過） |
| **C** Clarifier | `claude-retry` composite action，agent=`wp-workflows:clarifier`，`max_turns=200`(pipeline)/`120`(interactive)；`PR_MODE=true` 跳過 |
| **D** 橋接 | `detect_specs`（比對 `specs/` diff）→ `dynamic_upgrade`（interactive + 生成 specs → 升級 pipeline_mode）→ 通知留言 |
| **E** Planner | `specs_available && pipeline_mode` 才跑；agent=`wp-workflows:planner`，`max_turns=120` |
| **F** TDD | `planner_ok=true` 才跑；agent=`wp-workflows:tdd-coordinator`，`max_turns=200` |
| **G** 收尾 | `check_result` 匯整 outputs → 若有變更 `git push --force-with-lease` 兜底推送 |

---

## 三、Job 2：`integration-tests`

**依賴** `needs: claude` / **Timeout** 150 min

### 啟動條件

```yaml
run_integration_tests == 'true' &&
(
  pr_mode == 'true'                           # PR 模式旁路 claude_ok/has_changes
  OR
  (claude_ok == 'true' && has_changes == 'true')
)
```

### Steps 流程

| 段 | 核心動作 |
|----|---------|
| **H** 環境 | checkout(branch_name) → Node 20 / pnpm / composer → 建 uploads → wp-env start（3 次重試，delay 15/45/90s，含 unhealthy 容器 recovery） |
| **I** PHPUnit 3 循環 | `test_cycle_1` 失敗 → `claude_fix_1` → `test_cycle_2` 失敗 → `claude_fix_2` → `test_cycle_3`（final，無修復）。所有步驟 `continue-on-error: true`，fix 走 `anthropics/claude-code-action@v1`。**指令**：`npx wp-env run tests-cli --env-cwd=wp-content/plugins/power-funnel vendor/bin/phpunit --testdox` |
| **J** 彙整 | `final_result` parse PHPUnit summary（`OK (...)` 或 `Tests: ...`）→ 發測試結果留言 |
| **K** AI 驗收 | `detect_smoke` 檢查 diff 有無動到 `js/src/`、`inc/templates/`、`inc/assets/`、`inc/classes/` → 建置前端（`pnpm run build`） → Playwright 裝 chromium → `run_ai_acceptance`（agent=`wp-workflows:browser-tester`，prompt 涵蓋 admin SPA + 4 個 `pf_*` CPT 入口 + ReactFlow / LIFF 業務情境） |
| **L** 媒體 | `collect_smoke_media` 集中到 `/tmp/smoke-media` → 上傳 Bunny CDN（`ci/{branch}/smoke-test`）→ Artifact 備份 7 天 → 發 Smoke Test 報告留言（**已修正範本 gotcha**：使用 `collect_smoke_media.outputs.has_media`） |
| **M** PR 守門 | `run_ai_acceptance.outcome != 'failure'` → 自動建立 PR（gh pr create，body 含測試 badge + AI 驗收 badge + `Closes #N`）；反之發「驗收失敗不自動開 PR」通知 |

### Job Outputs

`final_result_*` 系列：`status` / `cycle` / `fix_count` / `test_total/passed/failures/errors/assertions/skipped/incomplete/warnings`

---

## 四、與 power-course 範本的關鍵差異

| 項目 | power-course | power-funnel |
|------|--------------|--------------|
| **LC Bypass** | 動態注入 `'lc' => false` 到 plugin.php | **整段移除**（plugin.php 已預設 `lc => false`） |
| **`.e2e-progress.json` 更新** | 設 `lc_bypass_applied = true` | 整段移除（無此邏輯） |
| **wp-env env-cwd** | `wp-content/plugins/wp-power-course` | `wp-content/plugins/power-funnel` |
| **前端 build** | `pnpm run build && pnpm run build:wp` | `pnpm run build`（單一指令，無 `build:wp` script） |
| **wp-env port** | 8895 | **8894**（tests 8904） |
| **AI 驗收 prompt** | LMS 課程描述 + Admin SPA HashRouter（#/courses 等） | LINE 報名漏斗 + ReactFlow 工作流引擎 + 4 個 `pf_*` CPT 入口 |
| **Smoke 報告 if 條件** | `steps.upload_smoke_media.outputs.has_media`（**永遠空** bug） | `steps.collect_smoke_media.outputs.has_media`（**已修正**） |

---

## 五、外部依賴資產

| 類型 | 路徑 |
|------|------|
| Composite action | `./.github/actions/claude-retry` |
| Prompt 模板 | `.github/prompts/{clarifier-pipeline,clarifier-interactive,planner,tdd-coordinator}.md` |
| 留言模板 | `.github/templates/{pipeline-upgrade-comment,test-result-comment,acceptance-comment}.md` |
| Shell script | `.github/scripts/upload-to-bunny.sh` |
| Marketplace | `https://github.com/j7-dev/wp-workflows.git`（提供 4 個 agents） |
| Secrets | `CLAUDE_CODE_OAUTH_TOKEN`、`BUNNY_STORAGE_{HOST,ZONE,PASSWORD}`、`BUNNY_CDN_URL` |

---

## 六、Gotchas（power-funnel 特有）

1. **`parse_agent` 英文關鍵字太寬**：`grep -qiE '...|OK|...|go|start'` 大小寫不敏感，一般對話中的 `ok`/`go` 會誤觸（沿用 power-course 行為，未修，未來可加字邊界）。
2. **Claude fix prompt 寫死在 workflow**（I 段兩處）：可搬到 `.github/prompts/claude-fix.md`。
3. **AI 驗收 prompt 寫死 `http://localhost:8894`**：與 `.wp-env.json` port 耦合，若改 port 需同步更新。
4. **Strauss 提示**：fix 1 prompt 已加入 Strauss namespace 提示（`PowerFunnel\` prefix），避免 Claude 誤以為原始 vendor 命名空間。
5. **無 `build:wp`**：`package.json` 只有 `build` script，與 power-course 不同。
6. **沒有 LC Bypass**：plugin.php 第 73 行 `'lc' => false`，已關閉授權檢查；不要再加回 LC Bypass step（會無效，因為 capability 字串不存在）。
7. **AI 驗收的 admin 入口**：power-funnel 主入口是 `?page=power-funnel`（Powerhouse 註冊），SPA HashRouter 細節由 ReactFlow 編輯器頁定義；CPT 列表頁則走 WordPress 原生 `edit.php?post_type=pf_*`。

---

## 七、修改自查清單

- [ ] 新增 `env.` / `steps.<id>.outputs.` 引用，名稱是否拼對？
- [ ] 跨 job 走 `needs.<job>.outputs.`，Job 1 `outputs:` 區塊同步新增？
- [ ] Stage gating 改動時，B/D/E/F/G 五段一起看
- [ ] Prompt / 留言模板的 `{{ISSUE_NUM}}` placeholder 有對應？
- [ ] Secrets 是否在 repo settings 備齊？
- [ ] `.wp-env.json` 改 port → AI 驗收 prompt 同步更新
- [ ] 新增/重命名 CPT → AI 驗收 prompt 的 CPT 入口列表同步更新
