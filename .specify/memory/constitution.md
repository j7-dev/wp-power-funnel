<!--
同步影響報告 (Sync Impact Report)
版本變更: 初始版本 → 1.0.0
修改過的原則: N/A (初次建立)
新增章節: 全部
移除章節: 無
需同步更新的模板:
  ✅ /templates/plan-template.md - 待同步憲章檢查項目
  ✅ /templates/spec-template.md - 待同步範疇與需求一致性
  ✅ /templates/tasks-template.md - 待同步任務分類與原則
  ✅ /templates/commands/*.md - 待同步指令檔引用
-->

# Power Funnel 專案憲章

## 核心原則

### I. WordPress 標準優先 (WordPress Standards First)

所有後端開發必須嚴格遵循 WordPress 官方標準與最佳實踐：

- **強制執行**: 使用 `declare(strict_types=1);` 於所有 PHP 檔案開頭
- **代碼風格**: 遵循 WordPress Coding Standards (WPCS)，通過 `composer lint` 檢查
- **靜態分析**: 代碼必須通過 PHPStan level 9 分析 (`composer analyse`)
- **API 優先**: 優先使用 WordPress 核心 API (Hook 系統、REST API、Meta API 等)，禁止繞過 WordPress API
- **命名規範**: PHP 使用 snake_case (變數/函數)、PascalCase (類別)、UPPER_SNAKE_CASE (常數)
- **命名空間**: 所有類別必須使用 `J7\PowerFunnel` 命名空間，遵循 PSR-4 自動載入

**理由**: WordPress 標準確保外掛與核心及其他外掛的相容性，強型別與靜態分析可在開發階段捕捉錯誤，降低執行時期風險。

### II. React 18 與 TypeScript 嚴格型別 (React 18 & Strict TypeScript)

前端開發必須充分利用 React 18 特性並維持型別安全：

- **React 18 優先**: 使用 Concurrent Rendering、Transitions、Suspense 等最新功能
- **TypeScript 強制**: 所有前端代碼使用 TypeScript，禁止使用 `any` 型別
- **Refine.dev 為核心**: 所有 CRUD 操作必須透過 Refine.dev hooks (useTable, useForm, useCustom 等)
- **Ant Design 5**: UI 組件優先使用 Ant Design 5，搭配 Tailwind CSS 工具類別
- **型別定義位置**: 所有型別定義集中於 `js/src/types/`，使用明確的介面與泛型
- **路徑別名**: 使用 `@/` 作為 `src/` 的別名

**理由**: TypeScript 型別系統可在編譯時期捕捉錯誤，React 18 提供更好的效能與使用者體驗，Refine.dev 統一資料管理模式降低複雜度。

### III. 代碼品質門檻 (Code Quality Gate)

所有代碼提交前必須通過以下檢查：

**PHP 後端**:
- `composer lint` (phpcs) - 無錯誤與警告
- `composer analyse` (phpstan) - level 9 無錯誤
- `phpcbf` - 自動修復可修復的格式問題

**JavaScript/TypeScript 前端**:
- `pnpm lint` (eslint + phpcbf) - 無錯誤
- `pnpm build` - 建構成功無錯誤
- TypeScript 編譯 - 無型別錯誤

**理由**: 自動化代碼品質檢查可確保程式碼一致性、可維護性，並在早期發現潛在問題。

### IV. 安全優先 (Security First)

安全性是不可協商的基礎需求：

**後端 (PHP)**:
- **輸入驗證**: 使用 `sanitize_*` 函數清理所有使用者輸入
- **輸出跳脫**: 使用 `esc_html()`, `esc_attr()`, `esc_url()` 等函數進行輸出跳脫
- **CSRF 防護**: 使用 `wp_nonce_field()` 和 `wp_verify_nonce()` 進行 CSRF 防護
- **權限檢查**: 使用 `current_user_can()` 檢查所有需要權限的操作
- **SQL 安全**: 使用 `$wpdb->prepare()` 防止 SQL 注入

**前端 (React)**:
- **API 認證**: 使用 `\wp_create_nonce('wp_rest')` 產生的 nonce 進行 REST API 認證
- **XSS 防護**: 避免使用 `dangerouslySetInnerHTML`，若必要則需嚴格清理
- **環境變數**: 敏感資訊不得暴露於前端代碼

**理由**: 安全漏洞可能導致資料洩漏、網站被入侵等嚴重後果，必須在設計階段就納入安全考量。

### V. 文件與註解規範 (Documentation Standards)

代碼必須具備清晰的文件與註解：

**PHP 註解** (繁體中文):
```php
/**
 * 取得文章詳細資訊
 *
 * @param int $post_id 文章 ID
 * @param bool $with_meta 是否包含 meta 資料
 * @return array<string, mixed> 文章資料陣列
 * @throws \Exception 當文章不存在時拋出異常
 */
```

**TypeScript 註解** (繁體中文):
```typescript
/**
 * 取得使用者列表
 * @param filters 篩選條件
 * @returns 使用者資料陣列
 */
```

**必要文件**:
- 複雜邏輯需行內註解說明
- 公開 API 必須有完整 PHPDoc/JSDoc
- 架構決策需記錄於 README 或相關文件

**理由**: 清晰的文件與註解可降低維護成本，幫助團隊成員理解代碼意圖，提升協作效率。

### VI. 架構分層與職責分離 (Layered Architecture & Separation of Concerns)

專案採用清晰的分層架構：

**後端分層**:
- `inc/classes/Bootstrap.php` - 外掛啟動與 hook 註冊
- `inc/classes/Domains/` - 業務邏輯領域 (Admin, Api, Theme 等)
- `inc/classes/Utils/` - 工具類別與常數定義
- `inc/classes/Api/` - REST API 端點 (繼承 ApiBase)

**前端分層**:
- `js/src/pages/` - 頁面元件 (路由層)
- `js/src/components/` - 可重用 UI 元件
- `js/src/api/` - API 資源定義 (Refine.dev resources)
- `js/src/utils/` - 工具函式與環境變數
- `js/src/types/` - TypeScript 型別定義

**職責原則**:
- 類別使用單例模式 (SingletonTrait) 管理實例
- 一般方法優先使用靜態方法
- WordPress hook 註冊統一於 `register_hooks()` 方法
- API 類別繼承 `ApiBase` 統一結構

**理由**: 分層架構使代碼組織清晰、易於測試與維護，職責分離避免代碼耦合，提升可擴展性。

### VII. 效能與優化 (Performance & Optimization)

效能是使用者體驗的關鍵：

**前端優化**:
- 使用 `React.memo` 避免不必要的重新渲染
- 使用 `useMemo`, `useCallback` 記憶化計算與回調
- 條件查詢使用 `queryOptions.enabled` 避免不必要的 API 請求
- 使用 React Query 的快取策略 (staleTime, cacheTime)
- 使用 Vite 的程式碼分割 (code splitting) 與 lazy loading

**後端優化**:
- 使用 transient API 快取 API 回應
- 避免 N+1 查詢問題
- 使用 `wp_cache` 物件快取
- 資料庫查詢使用適當的索引

**理由**: 效能直接影響使用者體驗與 SEO 排名，優化可降低伺服器負載與頻寬成本。

## 技術堆疊限制

### 後端技術棧

- **PHP 版本**: PHP 8.0+ (最低 8.0，建議 8.1+)
- **WordPress 版本**: 5.7+
- **依賴管理**: Composer
- **必要套件**:
  - `kucrut/vite-for-wp`: ^0.12 (Vite 整合)
  - `j7-dev/wp-utils`: ^0.3 (工具函式庫)
- **開發依賴**:
  - `squizlabs/php_codesniffer`: 代碼風格檢查
  - `wp-coding-standards/wpcs`: WordPress 代碼標準
  - `phpstan/phpstan`: 靜態分析
  - `php-stubs/wordpress-stubs`: WordPress 型別存根
  - `php-stubs/woocommerce-stubs`: WooCommerce 型別存根 (若需要)

### 前端技術棧

- **React**: 18.2+
- **TypeScript**: 最新穩定版
- **建構工具**: Vite
- **套件管理**: pnpm (不使用 npm 或 yarn)
- **核心框架**:
  - `@refinedev/core`: 4.57.5 (資料管理)
  - `@refinedev/antd`: 5.45.1 (Ant Design 整合)
  - `antd`: ^5.29.1 (UI 組件)
  - `react-router-dom`: ^7.9.6 (路由)
  - `@tanstack/react-query`: 4.36.1 (資料獲取)
- **樣式**:
  - Tailwind CSS (工具類別)
  - Sass/SCSS (複雜樣式)
  - Ant Design 5 (組件樣式)

### 禁用技術

- 不使用 jQuery (前端已採用 React)
- 不使用 class 組件 (僅使用 React 函式組件與 hooks)
- 避免使用過時的 WordPress API (如 `mysql_*` 函數)

## 開發流程與品質保證

### 開發環境設定

1. **初次設定**:
   ```bash
   pnpm run bootstrap  # 安裝所有依賴 (pnpm + composer)
   ```

2. **前端開發**:
   ```bash
   pnpm dev      # 啟動 Vite 開發伺服器 (熱重載)
   pnpm build    # 生產建構
   pnpm preview  # 預覽建構結果
   ```

3. **代碼檢查**:
   ```bash
   composer lint     # PHP 代碼風格檢查
   composer analyse  # PHP 靜態分析
   pnpm lint        # JS/TS 代碼檢查
   pnpm lint:fix    # 自動修復問題
   ```

### Git 工作流程

1. **分支策略**:
   - `master` - 主分支，保持穩定
   - `develop` - 開發分支
   - `feature/*` - 功能分支
   - `fix/*` - 修復分支

2. **Commit 規範**:
   - 使用語意化提交訊息 (Conventional Commits)
   - 格式: `<type>(<scope>): <subject>`
   - 類型: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

3. **提交前檢查**:
   - 所有代碼品質檢查必須通過
   - 建構必須成功
   - 相關文件已更新

### 發布流程

1. **版本號規則** (Semantic Versioning):
   - MAJOR: 向下不相容的 API 變更
   - MINOR: 向下相容的新功能
   - PATCH: 向下相容的錯誤修復

2. **發布指令**:
   ```bash
   pnpm release:patch  # 修補版本 (0.0.1 → 0.0.2)
   pnpm release:minor  # 次版本 (0.0.1 → 0.1.0)
   pnpm release:major  # 主版本 (0.0.1 → 1.0.0)
   ```

3. **發布檢查清單**:
   - [ ] 所有測試通過
   - [ ] 代碼品質檢查通過
   - [ ] CHANGELOG.md 已更新
   - [ ] 版本號已同步至 package.json 與 plugin.php
   - [ ] 建立 Git tag
   - [ ] 建立 GitHub Release

## 特殊規範與已知限制

### Tailwind CSS 衝突處理

部分 Tailwind class 與 WordPress 後台樣式衝突，需使用 `tw-` 前綴：

```typescript
// ❌ 錯誤
<div className="hidden block fixed">

// ✅ 正確
<div className="tw-hidden tw-block tw-fixed">
```

受影響的 class: `hidden`, `block`, `fixed`, `absolute`, `relative`, `sticky`

### 環境變數傳遞

後端透過 `wp_localize_script` 傳遞環境變數至前端：

```php
// PHP (後端)
wp_localize_script('power_funnel', 'power_funnel_data', [
    'env' => [
        'API_URL' => rest_url(),
        'NONCE' => wp_create_nonce('wp_rest'),
    ],
]);
```

```typescript
// TypeScript (前端)
const { API_URL, NONCE } = window.power_funnel_data.env;
```

### 已知工具相容性問題

1. **ESLint 與 prettier-plugin-multiline-arrays**: 可能有相容性問題，已配置但需注意
2. **TypeScript 版本**: 可能超出 @typescript-eslint 官方支援範圍，警告可忽略
3. **Peer Dependencies**: 專案使用 `legacy-peer-deps=true` 處理衝突

### 指引檔案參考

開發時必須遵守以下指引：

- PHP 代碼: `.github/instructions/wordpress.instructions.md`
- React 代碼: `.github/instructions/react.instructions.md`
- 專案概述: `.github/copilot-instructions.md`

## 治理機制

### 憲章優先級

本憲章優先於所有其他開發實踐與文件。若發現衝突，以本憲章為準。

### 修訂程序

1. **提案階段**: 任何成員可提出修訂提案，需包含：
   - 修訂理由
   - 影響範圍評估
   - 遷移計畫 (若有向下不相容變更)

2. **審核階段**: 修訂提案需經過團隊審核與討論

3. **通過標準**: 需達成共識或多數同意

4. **版本更新**: 修訂通過後需更新版本號：
   - MAJOR: 原則移除或重新定義
   - MINOR: 新增原則或章節
   - PATCH: 文字釐清或錯字修正

### 合規驗證

所有代碼審查與 Pull Request 必須驗證：

- [ ] 是否遵循 WordPress 標準
- [ ] 是否通過代碼品質檢查
- [ ] 是否符合安全規範
- [ ] 是否具備適當文件
- [ ] 是否遵循架構分層原則

### 執行階段指引

- 開發階段指引: `.github/copilot-instructions.md`
- PHP 開發指引: `.github/instructions/wordpress.instructions.md`
- React 開發指引: `.github/instructions/react.instructions.md`

**版本**: 1.0.0 | **通過日期**: 2025-12-03 | **最後修訂**: 2025-12-03
