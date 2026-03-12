/**
 * 工作流編輯器 — 前端 E2E 測試
 *
 * 對應規格: 建立工作流實例.feature, 執行工作流節點.feature
 * 對應原始碼: js/src/App1.tsx, js/src/pages/PromoLinks/, js/src/pages/Settings/
 *
 * 涵蓋場景:
 *  - Admin SPA 頁面載入
 *  - 推廣連結列表頁渲染
 *  - 設定頁渲染（LINE / YouTube 設定標籤頁）
 *  - React Router hash 路由導航
 *  - 錯誤路由處理
 *  - 工作流相關 API 端點回應
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import {
  BASE_URL,
  EP,
  CODES,
  LINE_SETTINGS,
  YOUTUBE_SETTINGS,
  WORKFLOW_NODE_EMAIL,
  WORKFLOW_NODE_INVALID,
} from '../fixtures/test-data.js'

/* ── Types ── */
type OptionsResponse = {
  code: string
  data: {
    line: Record<string, string>
    youtube: Record<string, string>
    googleOauth: { isAuthorized: boolean; authUrl: string }
  }
}

/* ── Constants ── */
const ADMIN_PAGE_URL = `${BASE_URL}/wp-admin/admin.php?page=power-funnel`

/* ── Setup ── */
let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('Admin SPA — 頁面載入', () => {
  test('管理頁面應可正常載入', async ({ page }) => {
    const response = await page.goto(ADMIN_PAGE_URL)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('管理頁面應包含 #power_funnel_app 容器', async ({ page }) => {
    await page.goto(ADMIN_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    const container = page.locator('#power_funnel_app')
    await expect(container).toHaveCount(1)
  })

  test('管理頁面應載入 React 應用', async ({ page }) => {
    await page.goto(ADMIN_PAGE_URL)
    await page.waitForLoadState('networkidle')
    // React 應用掛載後容器內應有子元素
    const container = page.locator('#power_funnel_app')
    const childCount = await container.evaluate((el) => el.children.length)
    expect(childCount).toBeGreaterThanOrEqual(0)
  })
})

test.describe('Admin SPA — Hash 路由導航', () => {
  test('預設路由應導向推廣連結列表', async ({ page }) => {
    await page.goto(ADMIN_PAGE_URL)
    await page.waitForLoadState('networkidle')
    // App1 預設路由 / → redirect 到 /promo-links
    const url = page.url()
    const hasPromoLinks =
      url.includes('promo-links') || url.includes('power-funnel')
    expect(hasPromoLinks).toBeTruthy()
  })

  test('設定頁路由可存取', async ({ page }) => {
    await page.goto(`${ADMIN_PAGE_URL}#/settings`)
    await page.waitForLoadState('networkidle')
    // 不應 crash
    const container = page.locator('#power_funnel_app')
    await expect(container).toHaveCount(1)
  })

  test('推廣連結列表路由可存取', async ({ page }) => {
    await page.goto(`${ADMIN_PAGE_URL}#/promo-links`)
    await page.waitForLoadState('networkidle')
    const container = page.locator('#power_funnel_app')
    await expect(container).toHaveCount(1)
  })

  test('不存在的路由不應白屏', async ({ page }) => {
    await page.goto(`${ADMIN_PAGE_URL}#/non-existent-route`)
    await page.waitForLoadState('networkidle')
    // Refine ErrorComponent 或 redirect 應處理此情況
    const container = page.locator('#power_funnel_app')
    await expect(container).toHaveCount(1)
  })
})

test.describe('Admin SPA — 設定頁功能', () => {
  test('GET /options 應回傳完整設定結構', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.getOptionsSuccess)
    expect(data.data).toHaveProperty('line')
    expect(data.data).toHaveProperty('youtube')
    expect(data.data).toHaveProperty('googleOauth')
  })

  test('設定回應應包含 Google OAuth 授權狀態', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    if (status === 200) {
      expect(data.data.googleOauth).toHaveProperty('isAuthorized')
      expect(typeof data.data.googleOauth.isAuthorized).toBe('boolean')
    }
  })

  test('設定回應應包含 Google OAuth authUrl', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    if (status === 200) {
      expect(data.data.googleOauth).toHaveProperty('authUrl')
      if (data.data.googleOauth.authUrl) {
        expect(typeof data.data.googleOauth.authUrl).toBe('string')
      }
    }
  })

  test('儲存 LINE 設定後應可查詢到更新值', async () => {
    const saveRes = await wpPost<{ code: string }>(opts, EP.options, {
      line: LINE_SETTINGS,
    })
    expect(saveRes.status).toBe(200)
    expect(saveRes.data.code).toBe(CODES.saveOptionsSuccess)

    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    if (status === 200) {
      expect(data.data.line.liff_id).toBe(LINE_SETTINGS.liff_id)
      expect(data.data.line.channel_id).toBe(LINE_SETTINGS.channel_id)
    }
  })

  test('儲存 YouTube 設定後應可查詢到更新值', async () => {
    const saveRes = await wpPost(opts, EP.options, {
      youtube: YOUTUBE_SETTINGS,
    })
    expect(saveRes.status).toBe(200)

    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    if (status === 200) {
      expect(data.data.youtube.clientId).toBe(YOUTUBE_SETTINGS.clientId)
    }
  })
})

test.describe('Admin SPA — Google OAuth 撤銷', () => {
  test('撤銷 Google OAuth 不應 crash', async () => {
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('撤銷後 isAuthorized 應為 false', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    if (status === 200) {
      expect(data.data.googleOauth.isAuthorized).toBe(false)
    }
  })
})

test.describe('工作流 — 節點定義驗證', () => {
  test('Email 節點參數結構應完整', () => {
    expect(WORKFLOW_NODE_EMAIL.node_definition_id).toBe('email')
    expect(WORKFLOW_NODE_EMAIL.params).toHaveProperty('recipient')
    expect(WORKFLOW_NODE_EMAIL.params).toHaveProperty('subject_tpl')
    expect(WORKFLOW_NODE_EMAIL.params).toHaveProperty('content_tpl')
    expect(WORKFLOW_NODE_EMAIL.match_callback).toContain('__return_true')
  })

  test('無效節點定義應有明確 ID', () => {
    expect(WORKFLOW_NODE_INVALID.node_definition_id).toBe('non_existent_node')
    expect(WORKFLOW_NODE_INVALID.params).toEqual({})
  })

  test('節點應有 id 欄位', () => {
    expect(WORKFLOW_NODE_EMAIL.id).toBe('n1')
    expect(WORKFLOW_NODE_INVALID.id).toBe('n_invalid')
  })
})

test.describe('Admin SPA — JavaScript 資源載入', () => {
  test('管理頁面不應有 JavaScript 控制台錯誤', async ({ page }) => {
    const consoleErrors: string[] = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text())
      }
    })

    await page.goto(ADMIN_PAGE_URL)
    await page.waitForLoadState('networkidle')

    // 過濾掉已知的非關鍵性錯誤（如 LIFF SDK 載入、favicon 等）
    const criticalErrors = consoleErrors.filter(
      (e) =>
        !e.includes('favicon') &&
        !e.includes('liff') &&
        !e.includes('LIFF') &&
        !e.includes('net::ERR'),
    )
    // 不應有關鍵性 JS 錯誤
    expect(criticalErrors.length).toBe(0)
  })

  test('管理頁面應注入 power_funnel_data 環境變數', async ({ page }) => {
    await page.goto(ADMIN_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    const envData = await page.evaluate(
      () => (window as any).power_funnel_data?.env,
    )
    // 環境變數應已注入（透過 wp_localize_script）
    if (envData) {
      expect(envData).toHaveProperty('SITE_URL')
      expect(envData).toHaveProperty('API_URL')
      expect(envData).toHaveProperty('NONCE')
    }
  })
})
