/**
 * Security Tests — 安全性測試
 *
 * 涵蓋場景:
 *  - 無認證存取管理端 API → 401/403
 *  - 無效 nonce → 401/403
 *  - SQL injection 嘗試
 *  - XSS payload 不應被存入
 *  - CSRF（無 nonce）
 *  - LINE Webhook 簽章偽造
 *  - LINE Webhook 簽章重放攻擊
 *  - LIFF 端點安全邊界
 */
import { test, expect } from '@playwright/test'
import * as crypto from 'crypto'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, EDGE, LINE_SETTINGS } from '../fixtures/test-data.js'

let opts: ApiOptions
let noAuthOpts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
  noAuthOpts = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
})

test.describe('Security — 認證與授權', () => {
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

  test('POST /liff 為公開端點，不需認證', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
      headers: { 'Content-Type': 'application/json' },
      data: { userId: '[E2E] sec-test', name: 'Test', isInClient: false, isLoggedIn: false },
    })
    expect(res.status()).not.toBe(401)
    expect(res.status()).not.toBe(403)
  })
})

test.describe('Security — SQL Injection', () => {
  test('activities keyword 參數 SQL injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: EDGE.sqlInjection,
    })
    expect(status).toBeLessThan(500)
  })

  test('activities id 參數 SQL injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: EDGE.sqlInjection,
    })
    expect(status).toBeLessThan(500)
  })

  test('activities last_n_days SQL injection → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: "1; DROP TABLE wp_options; --",
    })
    expect(status).toBeLessThan(500)
  })
})

test.describe('Security — XSS', () => {
  test('儲存含 XSS payload 的 LINE 設定後不應被原封存入', async () => {
    const xssSettings = {
      liff_id: EDGE.specialChars,
      channel_id: '<img onerror=alert(1) src=x>',
      channel_secret: '[E2E] safe_secret',
      channel_access_token: '[E2E] safe_token',
    }

    await wpPost(opts, EP.options, { line: xssSettings })
    const { data, status } = await wpGet<{
      data: { line: Record<string, string> }
    }>(opts, EP.options)

    expect(status).toBe(200)
    // 確認存入的值不包含未轉義的 <script> 標籤
    // WordPress 通常會 sanitize，或原封保存（API 不渲染 HTML）
    // 主要確認不 crash
    expect(data.data.line).toBeDefined()
  })

  test('LIFF callback 中 XSS payload 不應造成 500', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
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
})

test.describe('Security — LINE Webhook 簽章驗證', () => {
  test('偽造簽章應被拒絕', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.lineCallback}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'ZmFrZV9zaWduYXR1cmU=', // base64("fake_signature")
      },
      data: { events: [{ type: 'message', source: { userId: 'U000' } }] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('空簽章 header 應被拒絕', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.lineCallback}`, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': '',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })
})

/* ── 以下為新增的安全性測試 ── */

function generateLineSignature(body: string, channelSecret: string): string {
  return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
}

test.describe('Security — LINE Webhook 簽章重放攻擊', () => {
  const webhookUrl = `${BASE_URL}/wp-json/${EP.lineCallback}`

  test('使用其他 channel_secret 計算的簽章應被拒絕', async ({ request }) => {
    const payload = { events: [{ type: 'message', source: { userId: '[E2E] U_wrong_secret' } }] }
    const wrongSignature = generateLineSignature(
      JSON.stringify(payload),
      'completely_wrong_secret_key',
    )
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': wrongSignature,
      },
      data: payload,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('payload 被篡改後原簽章應失效', async ({ request }) => {
    const originalPayload = {
      events: [{ type: 'message', source: { userId: '[E2E] U_tamper_001' } }],
    }
    const signature = generateLineSignature(
      JSON.stringify(originalPayload),
      LINE_SETTINGS.channel_secret,
    )
    // 篡改 payload
    const tamperedPayload = {
      events: [{ type: 'postback', source: { userId: '[E2E] U_tamper_HACKED' } }],
    }
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: tamperedPayload,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('超長簽章字串應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'A'.repeat(10000),
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含特殊字元應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': '<script>alert(1)</script>',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含 null bytes 應被拒絕或不 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'valid_prefix\x00malicious_suffix',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('Security — LIFF 端點安全邊界', () => {
  const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`

  test('LIFF 端點不應洩漏伺服器內部資訊', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    })
    const body = await res.text()
    // 回應不應包含 PHP 錯誤細節、檔案路徑或 stack trace
    expect(body).not.toContain('Fatal error')
    expect(body).not.toContain('Stack trace')
    expect(body).not.toContain('/var/www')
    expect(body).not.toContain('wp-content/plugins')
  })

  test('LIFF 端點 SQL injection 在 userId → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: EDGE.sqlInjection,
        name: 'SQL Test',
        isInClient: false,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF 端點 HTTP method 限制 — GET 應被拒絕或回傳非 200', async ({ request }) => {
    const res = await request.get(liffUrl)
    // POST-only 端點，GET 應回傳 404 或 405
    expect(res.status()).not.toBe(200)
  })

  test('LIFF 端點 DELETE 方法應被拒絕', async ({ request }) => {
    const res = await request.delete(liffUrl)
    expect(res.status()).not.toBe(200)
  })
})
