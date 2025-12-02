# Copilot 編碼代理指南 - Power Funnel

## 專案概述

Power Funnel 是一個 WordPress 外掛，用於自動抓取 YouTube 直播場次，讓用戶可以透過 LINE 報名。此專案採用現代化混合架構：後端使用 PHP (WordPress)，前端使用 React SPA 渲染於 WordPress 後台。

**專案狀態**: 剛建立，尚未開始開發

## 技術棧

### 後端 (PHP)
- PHP 8.0+
- WordPress Coding Standards (WPCS)
- PHPStan (level 9)
- PHPCS/PHPCBF 代碼格式化
- Composer 依賴管理
- PSR-4 自動載入 (namespace: `J7\PowerFunnel`)

### 前端 (JavaScript/TypeScript)
- React 18 + TypeScript
- Vite 建構工具
- Ant Design 5 (UI 框架)
- Tailwind CSS + Sass
- Refine.dev (資料管理框架)
- React Query v4 (資料獲取)
- React Router v6
- pnpm 套件管理器

## 專案結構

```
wp-power-funnel/
├── plugin.php              # 外掛入口點
├── inc/                    # PHP 後端代碼
│   └── classes/
│       ├── Bootstrap.php   # 主要掛鉤註冊與腳本載入
│       ├── Domains/        # 業務邏輯領域
│       │   └── Admin/
│       │       └── Entry.php  # 後台頁面入口
│       └── Utils/
│           └── Base.php    # 基礎常數 (APP1_SELECTOR, APP2_SELECTOR)
├── js/                     # React 前端代碼
│   ├── src/
│   │   ├── main.tsx        # React 入口點
│   │   ├── App1.tsx        # 主應用元件
│   │   ├── App2.tsx        # Metabox 應用元件
│   │   ├── pages/          # 頁面元件
│   │   ├── components/     # 共用元件
│   │   ├── api/            # API 資源定義
│   │   ├── utils/          # 工具函式與環境變數
│   │   ├── types/          # TypeScript 型別定義
│   │   └── rest-data-provider/  # Refine 資料提供者
│   └── dist/               # 建構輸出 (已 gitignore)
├── composer.json           # PHP 依賴
├── package.json            # JS 依賴
├── phpcs.xml               # PHP 代碼風格設定
├── phpstan.neon            # PHP 靜態分析設定
├── vite.config.ts          # Vite 建構設定
├── tailwind.config.cjs     # Tailwind 設定
├── tsconfig.json           # TypeScript 設定
└── .eslintrc.json          # ESLint 設定
```

## 架構說明

1. **渲染方式**: PHP 輸出 `id="power_funnel"` (Base::APP1_SELECTOR) 的 DOM 容器，React 掛載於此容器渲染 SPA
2. **資料流**: 透過 WordPress REST API (`/wp-json/`) 提供資料，前端使用 Refine.dev + React Query 管理
3. **全螢幕模式**: Admin Entry 類別實現 WordPress 後台全螢幕介面
4. **環境變數**: 後端透過 `wp_localize_script` 傳遞環境變數至前端 `window.power_funnel_data.env`

## 建構指令

### 初始化 (首次設定)
```bash
# 安裝 pnpm (如果尚未安裝)
npm install -g pnpm

# 安裝所有依賴 (使用 bootstrap 腳本一次完成)
pnpm run bootstrap

# 或分開執行
pnpm install
composer install --no-interaction
```

### 前端開發
```bash
pnpm dev          # 啟動開發伺服器
pnpm build        # 生產建構
pnpm preview      # 預覽建構結果
```

### 代碼品質
```bash
# PHP
composer lint     # 執行 phpcs 檢查
composer analyse  # 執行 PHPStan 分析
phpcbf            # 自動修復 PHP 代碼格式

# JavaScript/TypeScript
pnpm lint         # 執行 ESLint + phpcbf
pnpm lint:fix     # 自動修復 ESLint 問題 + phpcbf
pnpm format       # 使用 Prettier 格式化 tsx 檔案
```

## 重要注意事項

### 必須遵守
1. **PHP 代碼**: 永遠使用 `declare(strict_types=1)` 並遵循 WordPress Coding Standards
2. **命名空間**: PHP 類別必須使用 `J7\PowerFunnel` 命名空間
3. **Tailwind 衝突**: 部分 Tailwind class 與 WordPress 衝突，使用 `tw-` 前綴替代 (`tw-hidden`, `tw-block`, `tw-fixed` 等)
4. **安全性**: 使用 `\wp_create_nonce('wp_rest')` 產生的 nonce 進行 API 認證
5. **TypeScript**: 前端代碼必須使用 TypeScript，型別定義在 `js/src/types/`

### 已知限制
1. ESLint 與 prettier-plugin-multiline-arrays 可能有相容性問題
2. TypeScript 版本可能超出 @typescript-eslint 官方支援範圍 (警告但可忽略)
3. 專案使用 `legacy-peer-deps=true` 處理 peer dependency 衝突

### 環境變數
前端可用環境變數 (在 `js/src/utils/env.tsx`):
- `API_URL`: REST API 基礎 URL
- `NONCE`: WordPress REST API nonce
- `KEBAB`: 外掛 kebab-case 名稱 (`power-funnel`)
- `SNAKE`: 外掛 snake_case 名稱 (`power_funnel`)
- `APP1_SELECTOR` / `APP2_SELECTOR`: React 掛載選擇器

## 開發工作流程

1. 修改 PHP 後端代碼 → 執行 `composer lint` 和 `composer analyse`
2. 修改前端代碼 → 執行 `pnpm dev` 即時預覽，完成後執行 `pnpm build`
3. 新增 REST API → 在 `inc/classes/` 新增控制器類別，前端在 `js/src/api/` 定義資源

## 信任此文件

請信任此指南中的指令。只有在資訊不完整或發現錯誤時才需要額外搜尋。建構與測試指令皆已驗證可正常運作。
