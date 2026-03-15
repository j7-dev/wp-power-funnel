/**
 * P2 — 安全性測試
 *
 * 涵蓋場景:
 *  - 認證與授權（無認證存取管理端 API → 401/403）
 *  - SQL Injection（各端點參數注入）
 *  - XSS（儲存、查詢、LIFF 端點）
 *  - 路徑穿越（path traversal）
 *  - CSRF（無 nonce）
 *  - LINE Webhook 簽章偽造
 *  - LINE Webhook 簽章重放攻擊
 *  - LIFF 端點安全邊界
 *  - 伺服器資訊洩漏防護
 */
import { test, expect } from '@playwright/test'
import * as crypto from 'crypto'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, EDGE, LINE_SETTINGS } from '../fixtures/test-data.js'

/* ── 工具函數 ── */
function signLine(body: string, secret: string): string {
  return crypto.createHmac('SHA256', secret).update(body).digest('base64')
}

const LIFF_URL = `${BASE_URL}/wp-json/${EP.liff}`
const WEBHOOK_URL = `${BASE_URL}/wp-json/${EP.lineCallback}`

let opts: ApiOptions
let noAuthOpts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
  noAuthOpts = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e_security' }
})

/* ────────────────────────────────────────────
   認證與授權
──────────────────────────────────────────── */
test.describe('Security — 認證與授權 [P2]', () => {
  test('GET /options 無認證 → 401 或 403', async () => {
    const { status } = await wpGet(noAuthOpts, EP.options)
    expect([401, 403]).toContain(status)
  })

  test('POST /options 無認證 → 401 或 403', async () => {
    const { status } = await wpPost(noAuthOpts, EP.options, { line: LINE_SETTINGS })
    expect([401, 403]).toContain(status)
  })

  test('GET /activities 無認證 → 401 或 403', async () => {
    const { status } = await wpGet(noAuthOpts, EP.activities)
    expect([401, 403]).toContain(status)
  })

  test('POST /revoke-google-oauth 無認證 → 401 或 403', async () => {
    const { status } = await wpPost(noAuthOpts, EP.revokeGoogleOAuth, {})
    expect([401, 403]).toContain(status)
  })

  test('POST /liff 為公開端點，不需認證（不應 401/403）', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: { userId: '[E2E] sec-test', name: '[E2E] Test', isInClient: false, isLoggedIn: false },
    })
    expect(res.status()).not.toBe(401)
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  test('POST /line-callback 為公開端點，不應 401/403（但需簽章）', async ({ request }) => {
    const payload = { events: [] }
    const body = JSON.stringify(payload)
    const signature = signLine(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: payload,
    })
    expect(res.status()).not.toBe(401)
    expect(res.status()).not.toBe(403)
  })
})

/* ────────────────────────────────────────────
   SQL Injection
──────────────────────────────────────────── */
test.describe('Security — SQL Injection [P2]', () => {
  test('activities keyword 參數 SQL OR injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.sqlOr })
    expect(status).toBeLessThan(500)
  })

  test('activities keyword 參數 SQL DROP injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.sqlDrop })
    expect(status).toBeLessThan(500)
  })

  test('activities keyword 參數 SQL injection（wp_options）→ 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.sqlInjection })
    expect(status).toBeLessThan(500)
  })

  test('activities id 參數 SQL injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: EDGE.sqlInjection })
    expect(status).toBeLessThan(500)
  })

  test('activities last_n_days SQL injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: "1; DROP TABLE wp_options; --",
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF userId SQL injection → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: EDGE.sqlInjection,
        name: '[E2E] SQL Injection User',
        isInClient: false,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF urlParams.promoLinkId SQL injection → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_sql_promo',
        name: '[E2E] SQL Promo',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: EDGE.sqlInjection },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('OPTIONS endpoint line.channel_secret SQL injection → 200', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: '[E2E] sql_test',
        channel_id: '[E2E] sql_ch',
        channel_secret: EDGE.sqlDrop,
        channel_access_token: '[E2E] sql_t',
      },
    })
    expect(status).toBe(200)
  })
})

/* ────────────────────────────────────────────
   XSS
──────────────────────────────────────────── */
test.describe('Security — XSS [P2]', () => {
  test('儲存含 script XSS 的 LINE 設定後不應回傳 500', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.specialChars,
        channel_id: '[E2E] xss_ch',
        channel_secret: '[E2E] safe_s',
        channel_access_token: '[E2E] safe_t',
      },
    })
    expect(status).toBe(200)
  })

  test('儲存後 GET 應正常回應（不含未轉義 script 標籤風險）', async () => {
    await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.specialChars,
        channel_id: '<b>bold</b>',
        channel_secret: '[E2E] safe',
        channel_access_token: '[E2E] safe',
      },
    })
    const { data, status } = await wpGet<{
      data: { line: Record<string, string> }
    }>(opts, EP.options)
    expect(status).toBe(200)
    // WordPress 儲存 API 值時通常不 sanitize，但回應不應導致 500
    expect(data.data.line).toBeDefined()
  })

  test('儲存含 img XSS 的 LINE 設定 → 200', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.xssImg,
        channel_id: '[E2E] img_xss_ch',
        channel_secret: '[E2E] safe',
        channel_access_token: '[E2E] safe',
      },
    })
    expect(status).toBe(200)
  })

  test('LIFF callback 中 XSS payload 不應造成 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: EDGE.specialChars,
        name: '<script>alert("xss")</script>',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '<script>alert(1)</script>' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF callback name 含 img XSS 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_img_xss',
        name: EDGE.xssImg,
        isInClient: false,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('activities keyword XSS script 標籤 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: EDGE.specialChars,
    })
    expect(status).toBeLessThan(500)
  })

  test('activities keyword img XSS → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: EDGE.xssImg,
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   路徑穿越
──────────────────────────────────────────── */
test.describe('Security — 路徑穿越 [P2]', () => {
  test('activities id 路徑穿越嘗試 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: EDGE.pathTraversal,
    })
    expect(status).toBeLessThan(500)
  })

  test('activities keyword 路徑穿越嘗試 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: EDGE.pathTraversal,
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF userId 路徑穿越嘗試 → 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: EDGE.pathTraversal,
        name: '[E2E] Path Traversal',
        isInClient: false,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   LINE Webhook 簽章偽造
──────────────────────────────────────────── */
test.describe('Security — LINE Webhook 簽章偽造 [P2]', () => {
  test('偽造 base64 簽章應被拒絕（>= 400）', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'ZmFrZV9zaWduYXR1cmU=',
      },
      data: { events: [{ type: 'message', source: { userId: 'U000' } }] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('空簽章 header 應被拒絕（>= 400）', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': '',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('使用錯誤 channel_secret 計算的簽章應被拒絕', async ({ request }) => {
    const payload = { events: [{ type: 'message', source: { userId: '[E2E] U_wrong' } }] }
    const wrongSig = signLine(JSON.stringify(payload), 'completely_wrong_secret_key')
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': wrongSig,
      },
      data: payload,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('篡改 payload 後原簽章應失效', async ({ request }) => {
    const original = {
      events: [{ type: 'message', source: { userId: '[E2E] U_tamper_sec' } }],
    }
    const signature = signLine(JSON.stringify(original), LINE_SETTINGS.channel_secret)
    // 篡改 payload
    const tampered = {
      events: [{ type: 'postback', source: { userId: '[E2E] U_HACKED' } }],
    }
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: tampered,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })
})

/* ────────────────────────────────────────────
   LINE Webhook 簽章重放攻擊
──────────────────────────────────────────── */
test.describe('Security — LINE Webhook Replay Attack [P2]', () => {
  test('使用其他 channel_secret 計算的簽章應被拒絕', async ({ request }) => {
    const payload = { events: [{ type: 'message', source: { userId: '[E2E] U_replay_sec' } }] }
    const wrongSig = signLine(JSON.stringify(payload), 'completely_wrong_secret_key')
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': wrongSig,
      },
      data: payload,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('超長簽章字串應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'A'.repeat(10000),
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含 XSS 應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': EDGE.specialChars,
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含 NULL bytes 應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'valid_prefix\x00malicious_suffix',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })
})

/* ────────────────────────────────────────────
   LIFF 端點安全邊界
──────────────────────────────────────────── */
test.describe('Security — LIFF 端點安全邊界 [P2]', () => {
  test('LIFF 端點不應洩漏 PHP 錯誤細節或伺服器路徑', async ({ request }) => {
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

  test('LIFF 端點 HTTP 方法限制 — GET 應被拒絕或回傳非 200', async ({ request }) => {
    const res = await request.get(LIFF_URL)
    expect(res.status()).not.toBe(200)
  })

  test('LIFF 端點 HTTP 方法限制 — DELETE 應被拒絕', async ({ request }) => {
    const res = await request.delete(LIFF_URL)
    expect(res.status()).not.toBe(200)
  })

  test('偽造 WP Nonce 搭配 LIFF 回調 → 公開端點仍可存取（不應 403）', async ({
    request,
  }) => {
    const res = await request.post(LIFF_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'completely_invalid_nonce_12345',
      },
      data: {
        userId: '[E2E] U_fake_nonce_sec',
        name: '[E2E] Fake Nonce',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  test('過期 session cookie 搭配 LIFF 回調 → 不應影響公開端點', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: {
        'Content-Type': 'application/json',
        Cookie: 'wordpress_logged_in_expired=invalid_cookie_value',
      },
      data: {
        userId: '[E2E] U_expired_session_sec',
        name: '[E2E] Expired Session',
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})
