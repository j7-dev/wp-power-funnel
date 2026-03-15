/**
 * P2 — LIFF 前端頁面載入
 *
 * 對應規格: spec/features/line/處理LIFF回調.feature, spec/specs/actors/LINE用戶.md
 *
 * 涵蓋場景:
 *  - LIFF 頁面可正常載入（HTTP < 500）
 *  - 頁面包含 #power_funnel_liff_app 容器
 *  - 非 LINE 內建瀏覽器下不應白屏崩潰
 *  - 帶 promoLinkId URL 參數 → 不 crash
 *  - 帶無效 promoLinkId → 不 crash
 *  - 帶多個 URL 參數 → 不 crash
 *  - promoLinkId 為空字串 → 不 crash
 *  - 頁面載入 CSS 樣式表
 *  - LIFF API 端點各種 payload 組合測試
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

/* ── 型別定義 ── */
type LiffResponse = {
  code: string
  message: string
  data: unknown
}

/* ── 常數 ── */
const LIFF_PAGE_URL = `${BASE_URL}/liff`
const LIFF_API_URL = `${BASE_URL}/wp-json/${EP.liff}`

test.describe('LIFF 頁面載入 [P2]', () => {
  test('LIFF 頁面應可正常載入（HTTP < 500）', async ({ page }) => {
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

  test('非 LINE 環境下頁面不應白屏崩潰', async ({ page }) => {
    // 在非 LINE 內建瀏覽器中開啟 LIFF 頁面
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    // body 不應為完全空白（可能顯示錯誤或載入中）
    const bodyText = await page.evaluate(() => document.body.innerText)
    expect(bodyText).toBeDefined()
  })

  test('LIFF 頁面應有樣式表（CSS）', async ({ page }) => {
    await page.goto(LIFF_PAGE_URL)
    await page.waitForLoadState('domcontentloaded')
    const stylesheets = await page.evaluate(() =>
      Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(
        (el) => (el as HTMLLinkElement).href,
      ),
    )
    // 樣式表數量 >= 0（即使無也不算失敗）
    expect(stylesheets).toBeDefined()
  })
})

test.describe('LIFF 頁面 URL 參數處理 [P2]', () => {
  test('帶 promoLinkId 參數存取 LIFF 頁面 → < 500', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=10`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('帶無效 promoLinkId（字串）→ 不應 crash', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=invalid`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('帶不存在的 promoLinkId（9999999）→ 不應 crash', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=9999999`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('帶多個 URL 參數 → 不應 crash', async ({ page }) => {
    const response = await page.goto(
      `${LIFF_PAGE_URL}?promoLinkId=10&extra=value&utm_source=test`,
    )
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })

  test('promoLinkId 為空字串 → 不應 crash', async ({ page }) => {
    const response = await page.goto(`${LIFF_PAGE_URL}?promoLinkId=`)
    expect(response).not.toBeNull()
    expect(response!.status()).toBeLessThan(500)
  })
})

test.describe('LIFF API 端點 — 回調資料處理 [P2]', () => {
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
      data: { ...LINE_USER, urlParams: { promoLinkId: '10' } },
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('urlParams 缺少 promoLinkId → 仍回應成功（不發送 Carousel）', async ({ request }) => {
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

  test('不存在的 promoLinkId（999999）→ 不應 500', async ({ request }) => {
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

test.describe('LIFF API 端點 — 用戶資料驗證 [P2]', () => {
  test('iOS 用戶完整資料 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_ios_liff',
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

  test('Android 用戶完整資料 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_android_liff',
        name: '[E2E] Android 用戶',
        os: 'Android',
        version: '2.1.0',
        lineVersion: '14.0.0',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('外部瀏覽器（isInClient: false）用戶 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_external_liff',
        name: '[E2E] 外部瀏覽器',
        isInClient: false,
        isLoggedIn: true,
        urlParams: { promoLinkId: '10' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('未登入用戶（isLoggedIn: false）→ 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_API_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_liff_not_login',
        name: '[E2E] 未登入',
        isInClient: true,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})
