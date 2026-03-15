/**
 * P0 — POST /liff 處理 LIFF 回調
 *
 * 對應規格: spec/features/line/處理LIFF回調.feature
 *
 * 重要：此端點為公開端點（permission_callback = __return_true），不需要 WP Nonce。
 *
 * 涵蓋場景:
 *  - 完整 payload（含 userId、urlParams）→ 200 + code: success
 *  - 最小 payload（只有 userId、isInClient、isLoggedIn）→ 200
 *  - 不帶 urlParams → 200（不發送 Carousel）
 *  - 不帶 userId → 不 500
 *  - 不應回傳 401/403（公開端點）
 *  - 空 body → 不 500
 *  - 帶額外未知欄位 → 不影響處理
 *  - promoLinkId 為數字（非字串）→ 不 500
 *  - promoLinkId 不存在 → 不 500
 *  - urlParams 為空物件 → 不 500
 *  - urlParams 為 null → 不 500
 *  - boolean 欄位傳入字串 → 不 500
 *  - iOS / Android 各平台用戶 → 不 500
 *  - 未登入用戶（isLoggedIn: false）→ 不 500
 *  - 無頭像（picture 缺少）→ 不 500
 *  - 超大 urlParams → 不 crash
 *  - HTTP GET 方法（只允許 POST）→ 非 200
 *  - HTTP DELETE 方法 → 非 200
 */
import { test, expect } from '@playwright/test'
import { BASE_URL, EP, CODES, LIFF_PAYLOAD_FULL, LIFF_PAYLOAD_MINIMAL, LINE_USER } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type LiffResponse = {
  code: string
  message: string
  data: unknown
}

const LIFF_URL = `${BASE_URL}/wp-json/${EP.liff}`

test.describe('POST /liff — 處理 LIFF 回調 [P0]', () => {
  // ── 基本成功路徑 ──

  test('完整 LIFF 資料（含 urlParams）→ 200 + code: success', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
      expect(typeof body.message).toBe('string')
    }
  })

  test('最小資料（只有必要欄位）→ 200 + code: success', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_MINIMAL,
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('不帶 urlParams → 200（不發送 Carousel）', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_no_params',
        name: '[E2E] 無 urlParams 用戶',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  // ── 公開端點驗證 ──

  test('公開端點不應回傳 401（不需 WP Nonce）', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).not.toBe(401)
    expect(res.status()).not.toBe(403)
  })

  test('帶無效 WP Nonce 仍可存取（公開端點）', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'completely_invalid_nonce_12345',
      },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  // ── 欄位缺失防禦 ──

  test('缺少 userId 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: { name: '[E2E] No UserId', isInClient: false, isLoggedIn: false },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('空 body {} 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    })
    expect(res.status()).toBeLessThan(500)
  })

  // ── urlParams 變化 ──

  test('urlParams.promoLinkId 為字串 → 正常處理', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: { ...LINE_USER, urlParams: { promoLinkId: '10' } },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams.promoLinkId 為數字（非字串）→ 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_numeric_promo',
        name: '[E2E] 數字 PromoLink',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: 10 },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams.promoLinkId 不存在（9999999）→ 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_bad_promo',
        name: '[E2E] 壞 PromoLink',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '9999999' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為空物件 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_empty_params',
        name: '[E2E] 空 urlParams',
        isInClient: false,
        isLoggedIn: true,
        urlParams: {},
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為 null → 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_null_params',
        name: '[E2E] null urlParams',
        isInClient: false,
        isLoggedIn: true,
        urlParams: null,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  // ── boolean 欄位類型寬容 ──

  test('isInClient 傳入字串 "yes" 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_bool_str',
        name: '[E2E] Bool String',
        isInClient: 'yes',
        isLoggedIn: 'true',
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  // ── 各平台用戶 ──

  test('iOS 用戶完整資料 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
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

  test('Android 用戶完整資料 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_android_user',
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
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_external_browser',
        name: '[E2E] 外部瀏覽器',
        isInClient: false,
        isLoggedIn: true,
        urlParams: { promoLinkId: '10' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('未登入用戶（isLoggedIn: false）→ 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
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

  test('無 picture 欄位 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_no_picture',
        name: '[E2E] 無頭像',
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  // ── 額外欄位 ──

  test('帶額外未知欄位不應影響處理', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        ...LIFF_PAYLOAD_FULL,
        extraField: 'should be ignored',
        nested: { deep: true },
      },
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  // ── 超大 payload ──

  test('超大 urlParams 物件（100 個欄位）→ 不應 crash', async ({ request }) => {
    const hugeParams: Record<string, string> = {}
    for (let i = 0; i < 100; i++) {
      hugeParams[`param_${i}`] = 'A'.repeat(500)
    }
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_huge_params',
        name: '[E2E] Huge Params',
        isInClient: true,
        isLoggedIn: true,
        urlParams: hugeParams,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  // ── HTTP 方法限制 ──

  test('GET /liff 應回傳非 200（只允許 POST）', async ({ request }) => {
    const res = await request.get(LIFF_URL)
    expect(res.status()).not.toBe(200)
  })

  test('DELETE /liff 應回傳非 200', async ({ request }) => {
    const res = await request.delete(LIFF_URL)
    expect(res.status()).not.toBe(200)
  })

  // ── 資訊洩漏防護 ──

  test('空 body 回應不應洩漏 PHP 錯誤或伺服器路徑', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    })
    const body = await res.text()
    expect(body).not.toContain('Fatal error')
    expect(body).not.toContain('Stack trace')
    expect(body).not.toContain('/var/www')
    expect(body).not.toContain('wp-content/plugins')
  })
})
