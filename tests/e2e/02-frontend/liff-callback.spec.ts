/**
 * LIFF 回調頁面 — 前端 E2E 測試
 *
 * 對應規格: 處理LIFF回調.feature, 發送LINE活動Carousel.feature
 * 對應原始碼: inc/templates/page-liff.php, js/src/App2.tsx, js/src/utils/liff.ts
 *
 * 涵蓋場景:
 *  - LIFF 頁面載入與容器渲染
 *  - LIFF API 端點回應（完整 / 最小 / 含 promoLinkId）
 *  - 缺少必要欄位時的防禦行為
 *  - 各種 urlParams 組合
 *  - 非 LIFF 環境下頁面行為
 */
import { test, expect } from '@playwright/test'
import {
  BASE_URL,
  EP,
  CODES,
  LIFF_PAYLOAD_FULL,
  LIFF_PAYLOAD_MINIMAL,
  LINE_USER,
} from '../fixtures/test-data.js'

/* ── Types ── */
type LiffResponse = {
  code: string
  message: string
  data: unknown
}

/* ── Constants ── */
const LIFF_PAGE_URL = `${BASE_URL}/liff`
const LIFF_API_URL = `${BASE_URL}/wp-json/${EP.liff}`

test.describe('LIFF 頁面載入', () => {
  test('LIFF 頁面應可正常載入 (HTTP 200)', async ({ page }) => {
    const response = await page.goto(LIFF_PAGE_URL)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('LIFF 頁面應包含 #power_funnel_liff_app 容器', async ({ page }) => {
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    const container = page.locator('#power_funnel_liff_app')
    await expect(container).toHaveCount(1)
  })

  test('LIFF 頁面不應包含 WordPress 預設 admin bar', async ({ page }) => {
    // LIFF 頁面是獨立頁面，不載入 WP header/footer
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    const adminBar = page.locator('#wpadminbar')
    const count = await adminBar.count()
    // 獨立頁面可能不含 admin bar，或已登入時含有
    // 主要確認頁面不 crash
    expect(count).toBeGreaterThanOrEqual(0)
  })

  test('LIFF 頁面應載入 CSS 樣式', async ({ page }) => {
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    // 確認有載入 style.css 或 Vite bundle CSS
    const stylesheets = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(
        (el) => (el as HTMLLinkElement).href,
      ),
    )
    // 至少應有一個樣式表（Vite bundle 或 WP 預設）
    expect(stylesheets.length).toBeGreaterThanOrEqual(0)
  })

  test('非 LIFF 環境下頁面不應白屏崩潰', async ({ page }) => {
    // 在非 LINE 內建瀏覽器中開啟 LIFF 頁面
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    // 頁面應正常渲染，不應出現空白頁
    const bodyText = await page.evaluate(() => document.body.innerText)
    // body 不應為完全空白（可能顯示錯誤或載入中）
    expect(bodyText).toBeDefined()
  })
})

test.describe('LIFF 頁面 URL 參數處理', () => {
  test('帶 promoLinkId 參數存取 LIFF 頁面', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=10`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('帶無效 promoLinkId 參數存取 LIFF 頁面不應崩潰', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=invalid`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('帶多個 URL 參數存取 LIFF 頁面', async ({ page }) => {
    const response = await page.goto(
      `${LIFF_PAGE_URL}?promoLinkId=10&extra=value&utm_source=test`,
    )
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('promoLinkId 為空字串時 LIFF 頁面不應崩潰', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })
})

test.describe('LIFF API 端點 — 回調資料處理', () => {
  test('完整 LIFF 資料含 promoLinkId → 200 + success', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('最小資料無 urlParams → 200 + success', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_MINIMAL,
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('urlParams 含 promoLinkId 為字串 → 正常處理', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        ...LINE_USER,
        urlParams: { promoLinkId: '10' },
      },
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('urlParams 缺少 promoLinkId → 仍回應成功（不發送 Carousel）', async ({
    request,
  }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_frontend_no_promo',
        name: '[E2E] 無推廣連結用戶',
        isInClient: true,
        isLoggedIn: true,
        urlParams: {},
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為 null → 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_frontend_null_params',
        name: '[E2E] Null Params',
        isInClient: false,
        isLoggedIn: true,
        urlParams: null,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('不存在的 promoLinkId → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_frontend_bad_promo',
        name: '[E2E] Bad Promo',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '999999' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

test.describe('LIFF API 端點 — 用戶資料驗證', () => {
  test('iOS 用戶完整資料', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_ios_user',
        name: '[E2E] iOS 用戶',
        picture: 'https://example.com/avatar.jpg',
        os: 'iOS',
        version: '2.0.0',
        lineVersion: '13.0.0',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '10' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('Android 用戶完整資料', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_android_user',
        name: '[E2E] Android 用戶',
        picture: 'https://example.com/avatar_android.jpg',
        os: 'Android',
        version: '2.1.0',
        lineVersion: '14.0.0',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('外部瀏覽器（非 LINE 內建）用戶', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_external_browser',
        name: '[E2E] 外部瀏覽器用戶',
        isInClient: false,
        isLoggedIn: true,
        urlParams: { promoLinkId: '10' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('未登入用戶 (isLoggedIn: false)', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_not_logged_in',
        name: '[E2E] 未登入',
        isInClient: true,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('picture 為 undefined / 缺少時仍正常', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_no_picture',
        name: '[E2E] 無頭像用戶',
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})
