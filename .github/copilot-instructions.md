# Power Funnel - Copilot 開發指南

## 專案概述

Power Funnel 是一個 WordPress 外掛，用於自動抓取 YouTube 直播場次，讓用戶可以透過 LINE 報名。此專案採用現代化開發架構，與傳統 WordPress 外掛不同：

- **後端**：PHP 8.0+，遵循 WordPress 編碼標準
- **前端**：React 18 + TypeScript + Vite，使用 Ant Design UI 框架
- **資料層**：WordPress REST API + Refine.dev 數據提供者

外掛會在 WordPress 後台輸出 `id="power_funnel"` 的容器（定義於 `Base::APP1_SELECTOR`），透過 React 渲染畫面，數據由 RESTful API 提供。

## 專案結構

```
wp-power-funnel/
├── plugin.php           # 外掛主入口，命名空間 J7\PowerFunnel
├── inc/                 # PHP 代碼目錄
│   └── classes/         # PSR-4 自動載入類別
│       ├── Bootstrap.php    # 主要鉤子註冊與腳本載入
│       ├── Domains/         # 功能模組
│       │   └── Admin/Entry.php  # 後台頁面渲染
│       └── Utils/Base.php   # 常量定義 (APP1_SELECTOR, APP2_SELECTOR)
├── js/                  # 前端代碼
│   ├── src/             # TypeScript 源碼
│   │   ├── main.tsx     # React 入口點
│   │   ├── App1.tsx     # 主應用程式 (後台介面)
│   │   ├── App2.tsx     # 次要應用程式 (Metabox)
│   │   ├── resources/   # Refine 資源定義
│   │   ├── rest-data-provider/  # API 數據提供者
│   │   ├── utils/       # 工具函數與環境變數
│   │   └── types/       # TypeScript 類型定義
│   └── dist/            # 編譯輸出 (git ignored)
├── composer.json        # PHP 依賴管理
├── package.json         # Node.js 依賴管理
├── phpcs.xml            # PHP CodeSniffer 配置
├── phpstan.neon         # PHPStan 靜態分析配置
├── .eslintrc.json       # ESLint 配置
├── .prettierrc          # Prettier 配置
├── tsconfig.json        # TypeScript 配置
├── vite.config.ts       # Vite 建置配置
└── tailwind.config.cjs  # Tailwind CSS 配置
```

## 開發環境需求

- **PHP**: 8.0+
- **Node.js**: 20.x
- **pnpm**: 10.x（建議使用 `npm install -g pnpm` 安裝）
- **Composer**: 2.x

## 建置與開發指令

### 初始化專案

```bash
# 安裝所有依賴（必須先執行）
pnpm run bootstrap

# 或分開執行
pnpm install
composer install
```

### 前端開發

```bash
# 開發模式（Hot Reload）
pnpm run dev

# 生產建置
pnpm run build

# 格式化代碼
pnpm run format
```

### 代碼品質檢查

```bash
# TypeScript/JavaScript Lint（使用 ESLint + Prettier）
pnpm run lint

# PHP Lint（使用 PHP CodeSniffer）
composer lint
# 或
./vendor/bin/phpcs

# PHP 靜態分析（使用 PHPStan，level 9）
composer analyse
# 或
./vendor/bin/phpstan analyse inc --memory-limit=6G

# PHP 代碼格式化（使用 phpcbf）
./vendor/bin/phpcbf
```

## PHP 開發規範

1. **遵循 WordPress 編碼標準**（WordPress-Core, WordPress-Docs, WordPress-Extra）
2. **使用 `declare(strict_types=1)`** 於所有 PHP 檔案
3. **命名空間**：`J7\PowerFunnel\`，類別位於 `inc/classes/`
4. **自動載入**：遵循 PSR-4，類別檔名需與類別名稱相符
5. **安全性**：使用 `\esc_*` 函數輸出，使用 nonce 驗證請求

### PHP 類別範例

```php
<?php
declare(strict_types=1);

namespace J7\PowerFunnel\Domains\YourFeature;

final class YourClass {
    public static function register_hooks(): void {
        \add_action('init', [__CLASS__, 'init']);
    }
}
```

## 前端開發規範

1. **使用 TypeScript**，嚴格模式
2. **路徑別名**：使用 `@/` 引用 `js/src/` 下的檔案
3. **狀態管理**：使用 React Query（TanStack Query）
4. **UI 框架**：Ant Design 5.x + Tailwind CSS 3.x
5. **路由**：HashRouter（`#/path` 格式）

### 環境變數存取

前端環境變數透過 `js/src/utils/env.tsx` 統一管理：

```tsx
import { API_URL, KEBAB, NONCE, APP1_SELECTOR } from '@/utils'
```

## 重要常量

| 常量 | PHP 位置 | 值 | 說明 |
|------|----------|-----|------|
| `APP1_SELECTOR` | `Utils\Base` | `#power_funnel` | 主應用容器 ID |
| `APP2_SELECTOR` | `Utils\Base` | `#power_funnel_metabox` | Metabox 容器 ID |
| `$kebab` | `Plugin::$kebab` | `power-funnel` | 外掛識別名稱（靜態屬性） |
| `$snake` | `Plugin::$snake` | `power_funnel` | 外掛識別名稱-底線格式（靜態屬性） |

## REST API 開發

1. **端點前綴**：`/wp-json/power-funnel/`
2. **認證**：使用 WordPress nonce（`wp_rest`）
3. **數據提供者**：Refine.dev 的 `dataProvider`

```tsx
// 可用的 data providers
dataProvider={{
  default: dataProvider(`${API_URL}/${KEBAB}`),  // 自訂 API
  'wp-rest': dataProvider(`${API_URL}/wp/v2`),   // WordPress REST API
  'wc-rest': dataProvider(`${API_URL}/wc/v3`),   // WooCommerce REST API
}}
```

## 注意事項

1. **Tailwind 類別衝突**：部分類別與 WordPress 衝突，使用 `tw-` 前綴替代（如 `tw-hidden`、`tw-block`）
2. **phpstan.neon 本地路徑**：`bootstrapFiles` 中的 Powerhouse 外掛路徑（`C:\Users\...`）為開發者本地環境路徑，在其他環境執行 PHPStan 時可能需要移除或調整這些路徑
3. **TypeScript 版本警告**：ESLint 可能顯示 TypeScript 版本警告，可忽略
4. **vendor 目錄**：執行 `composer install` 後才會生成

## 驗證變更

修改代碼後，執行以下指令確保品質：

```bash
# 1. 前端建置
pnpm run build

# 2. PHP 格式檢查
./vendor/bin/phpcs

# 3. PHP 靜態分析
./vendor/bin/phpstan analyse inc --memory-limit=6G
```

如果指令找不到或執行失敗，請先確認依賴已安裝（`pnpm run bootstrap`）。
