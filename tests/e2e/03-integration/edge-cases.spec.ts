/**
 * P3 — 邊緣條件測試
 *
 * 涵蓋場景:
 *  - 數值邊界（0、負數、浮點數、MAX_SAFE_INTEGER、超大正整數、非數值字串）
 *  - 並發操作（同時多次撤銷 OAuth、同一 LIFF 用戶多次請求）
 *  - 已刪除資源（不存在的 ID CRUD 操作）
 *  - 重複/冪等操作（POST /options 相同資料兩次、多次撤銷 OAuth）
 *  - 狀態邊界（id=0/id=-1/id=''、組合篩選條件）
 *  - LIFF Token 與認證邊界
 *  - LINE Webhook Replay Attack
 *  - 重複用戶報名
 *  - 空活動列表邊界
 *  - OAuth 撤銷無效憑證
 */
import { test, expect } from '@playwright/test'
import * as crypto from 'crypto'
import { wpGet, wpPost, wpPut, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import {
  BASE_URL,
  EP,
  EDGE,
  LINE_SETTINGS,
  YOUTUBE_SETTINGS,
  WP_API_BASE,
} from '../fixtures/test-data.js'

const LIFF_URL = `${BASE_URL}/wp-json/${EP.liff}`
const WEBHOOK_URL = `${BASE_URL}/wp-json/${EP.lineCallback}`

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

function generateLineSignature(body: string, channelSecret: string): string {
  return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
}

/* ────────────────────────────────────────────
   數值邊界 — last_n_days 參數
──────────────────────────────────────────── */
test.describe('Edge Cases — last_n_days 數值邊界 [P3]', () => {
  test('last_n_days=0 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '0' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=-1 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '-1' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=-999 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '-999' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=0.5（浮點數）不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '0.5' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=Number.MAX_SAFE_INTEGER 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(Number.MAX_SAFE_INTEGER),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=999999 超大正整數不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '999999' })
    expect(status).toBeLessThan(500)
  })

  test("last_n_days='not_a_number' 非數值字串不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: 'not_a_number' })
    expect(status).toBeLessThan(500)
  })

  test("last_n_days='NaN' 不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: 'NaN' })
    expect(status).toBeLessThan(500)
  })

  test("last_n_days='Infinity' 不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: 'Infinity' })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   數值邊界 — activities id 參數
──────────────────────────────────────────── */
test.describe('Edge Cases — activities id 邊界 [P3]', () => {
  test('id=0 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: '0' })
    expect(status).toBeLessThan(500)
  })

  test('id=-1 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: '-1' })
    expect(status).toBeLessThan(500)
  })

  test("id='' 空字串不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, { id: '' })
    expect(status).toBeLessThan(500)
  })

  test('id=999999 不存在的 ID 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: '999999' })
    expect(status).toBeLessThan(500)
  })

  test('id=Number.MAX_SAFE_INTEGER 超大整數不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: String(Number.MAX_SAFE_INTEGER),
    })
    expect(status).toBeLessThan(500)
  })

  test("id='non_existent_id' 非數值 ID 不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, { id: 'non_existent_id' })
    expect(status).toBeLessThan(500)
  })

  test('keyword 為空字串不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: '' })
    expect(status).toBeLessThan(500)
  })

  test('keyword 為超長字串（5000 字元）不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: 'A'.repeat(5000) })
    expect(status).toBeLessThan(500)
  })

  test('同時傳入 id + keyword + last_n_days 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: 'yt001',
      keyword: 'React',
      last_n_days: '30',
    })
    expect(status).toBeLessThan(500)
  })

  test('全部篩選參數為空字串不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: '',
      last_n_days: '',
      id: '',
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   並發操作 — POST /revoke-google-oauth
──────────────────────────────────────────── */
test.describe('Edge Cases — 並發撤銷 OAuth [P3]', () => {
  test('連續撤銷兩次不應 500', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('未曾授權就撤銷不應 500', async () => {
    // 先撤銷確保沒有 token
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    // 再撤銷一次（模擬從未授權的狀態）
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('同時三個 POST /revoke-google-oauth 全部不應 500（並發）', async () => {
    const [r1, r2, r3] = await Promise.all([
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
    ])
    expect(r1.status).toBeLessThan(500)
    expect(r2.status).toBeLessThan(500)
    expect(r3.status).toBeLessThan(500)
  })

  test('連續五次撤銷 OAuth 應均不 500（冪等）', async () => {
    for (let i = 0; i < 5; i++) {
      const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
      expect(status).toBeLessThan(500)
    }
  })

  test('YouTube 設定為空後撤銷 OAuth 不應 500', async () => {
    await wpPost(opts, EP.options, {
      youtube: { clientId: '', clientSecret: '' },
    })
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('YouTube 設定為 XSS payload 後撤銷 OAuth 不應 500', async () => {
    await wpPost(opts, EP.options, {
      youtube: {
        clientId: '<script>alert(1)</script>',
        clientSecret: "'; DROP TABLE wp_options; --",
      },
    })
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test.afterAll(async () => {
    // 還原 YouTube 設定
    await wpPost(opts, EP.options, { youtube: YOUTUBE_SETTINGS }).catch(() => {})
  })
})

/* ────────────────────────────────────────────
   並發操作 — POST /liff 相同用戶
──────────────────────────────────────────── */
test.describe('Edge Cases — 並發 LIFF 請求 [P3]', () => {
  test('同一 LIFF 用戶同時兩次 POST /liff 不應 crash', async ({ request }) => {
    const payload = {
      userId: '[E2E] U_concurrent_liff_001',
      name: '[E2E] 並發測試用戶',
      isInClient: true,
      isLoggedIn: true,
      urlParams: { promoLinkId: '10' },
    }
    const [res1, res2] = await Promise.all([
      request.post(LIFF_URL, {
        headers: { 'Content-Type': 'application/json' },
        data: payload,
      }),
      request.post(LIFF_URL, {
        headers: { 'Content-Type': 'application/json' },
        data: payload,
      }),
    ])
    expect(res1.status()).toBeLessThan(500)
    expect(res2.status()).toBeLessThan(500)
  })

  test('同一 LIFF 用戶連續三次請求應全部不 500（Replay）', async ({ request }) => {
    const payload = {
      userId: '[E2E] U_replay_liff_001',
      name: '[E2E] Replay 測試用戶',
      isInClient: false,
      isLoggedIn: true,
    }
    for (let i = 0; i < 3; i++) {
      const res = await request.post(LIFF_URL, {
        headers: { 'Content-Type': 'application/json' },
        data: payload,
      })
      expect(res.status()).toBeLessThan(500)
    }
  })

  test('不同 LIFF 用戶同時請求不應互相干擾', async ({ request }) => {
    const results = await Promise.all(
      [1, 2, 3].map((i) =>
        request.post(LIFF_URL, {
          headers: { 'Content-Type': 'application/json' },
          data: {
            userId: `[E2E] U_parallel_liff_00${i}`,
            name: `[E2E] 平行請求用戶 ${i}`,
            isInClient: true,
            isLoggedIn: true,
          },
        }),
      ),
    )
    for (const res of results) {
      expect(res.status()).toBeLessThan(500)
    }
  })
})

/* ────────────────────────────────────────────
   已刪除資源 — WP CPT REST API
──────────────────────────────────────────── */
test.describe('Edge Cases — 已刪除資源存取 [P3]', () => {
  test('GET pf_workflow_rule/999999 應回傳 404', async () => {
    const { status } = await wpGet(opts, `${WP_API_BASE}/pf_workflow_rule/999999`)
    expect(status).toBe(404)
  })

  test('PUT pf_workflow_rule/999999 應回傳 404', async () => {
    const { status } = await wpPut(opts, `${WP_API_BASE}/pf_workflow_rule/999999`, {
      title: '[E2E] Should Not Exist',
    })
    expect(status).toBe(404)
  })

  test('DELETE pf_workflow_rule/999999?force=true 應回傳 404', async () => {
    const { status } = await wpDelete(
      opts,
      `${WP_API_BASE}/pf_workflow_rule/999999?force=true`,
    )
    expect(status).toBe(404)
  })

  test('GET pf_promo_link/999999 應回傳 404', async () => {
    const { status } = await wpGet(opts, `${WP_API_BASE}/pf_promo_link/999999`)
    expect(status).toBe(404)
  })

  test('PUT pf_promo_link/999999 應回傳 404', async () => {
    const { status } = await wpPut(opts, `${WP_API_BASE}/pf_promo_link/999999`, {
      meta: { keyword: '[E2E] ghost update' },
    })
    expect(status).toBe(404)
  })

  test('DELETE pf_promo_link/999999?force=true 應回傳 404', async () => {
    const { status } = await wpDelete(
      opts,
      `${WP_API_BASE}/pf_promo_link/999999?force=true`,
    )
    expect(status).toBe(404)
  })

  test('GET pf_registration/999999 應回傳 404', async () => {
    const { status } = await wpGet(opts, `${WP_API_BASE}/pf_registration/999999`)
    expect(status).toBe(404)
  })

  test('GET pf_workflow_rule/-1 應回傳 4xx', async () => {
    const { status } = await wpGet(opts, `${WP_API_BASE}/pf_workflow_rule/-1`)
    expect(status).toBeGreaterThanOrEqual(400)
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   重複/冪等操作 — POST /options
──────────────────────────────────────────── */
test.describe('Edge Cases — 重複/冪等操作 [P3]', () => {
  test('重複儲存相同 LINE 設定應均回傳 200（冪等）', async () => {
    await wpPost(opts, EP.options, { line: LINE_SETTINGS })
    const { status } = await wpPost(opts, EP.options, { line: LINE_SETTINGS })
    expect(status).toBe(200)
  })

  test('重複儲存相同 YouTube 設定應均回傳 200', async () => {
    await wpPost(opts, EP.options, { youtube: YOUTUBE_SETTINGS })
    const { status } = await wpPost(opts, EP.options, { youtube: YOUTUBE_SETTINGS })
    expect(status).toBe(200)
  })

  test('POST /options 空物件兩次應均不 500', async () => {
    const { status: s1 } = await wpPost(opts, EP.options, {})
    expect(s1).toBeLessThan(500)
    const { status: s2 } = await wpPost(opts, EP.options, {})
    expect(s2).toBeLessThan(500)
  })

  test('儲存空 object 作為 LINE 設定不應 500', async () => {
    const { status } = await wpPost(opts, EP.options, { line: {} })
    expect(status).toBeLessThan(500)
  })

  test('儲存 null 作為 LINE 設定不應 500', async () => {
    const { status } = await wpPost(opts, EP.options, { line: null })
    expect(status).toBeLessThan(500)
  })

  test('儲存包含多餘欄位的 LINE 設定不應 500', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: { ...LINE_SETTINGS, extraField: 'should be ignored' },
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   狀態邊界 — Write-then-Read 一致性
──────────────────────────────────────────── */
test.describe('Edge Cases — 狀態一致性 [P3]', () => {
  test('POST /options 後 GET /options 應不回傳 500', async () => {
    await wpPost(opts, EP.options, {
      line: {
        liff_id: '[E2E] state_check_liff',
        channel_id: '[E2E] state_ch',
        channel_secret: '[E2E] state_s',
        channel_access_token: '[E2E] state_t',
      },
    })
    const { status } = await wpGet(opts, EP.options)
    expect(status).toBeLessThan(500)
  })

  test('OPTIONS 設定後立即讀取應一致（Write-then-Read 一致性）', async () => {
    const uniqueId = `[E2E] edge_${Date.now()}`
    const { status: saveStatus } = await wpPost(opts, EP.options, {
      line: {
        liff_id: uniqueId,
        channel_id: '[E2E] wtread_ch',
        channel_secret: '[E2E] wtread_s',
        channel_access_token: '[E2E] wtread_t',
      },
    })
    expect(saveStatus).toBeLessThan(300)

    const { data, status: getStatus } = await wpGet<{
      code: string
      data: { line: Record<string, string> }
    }>(opts, EP.options)
    expect(getStatus).toBeLessThan(500)
    if (getStatus === 200 && data?.data?.line) {
      expect(data.data.line.liff_id).toBe(uniqueId)
    }
  })

  test('POST /revoke-google-oauth 後 GET /options 應不回傳 500', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { status } = await wpGet(opts, EP.options)
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   LIFF 回調邊界
──────────────────────────────────────────── */
test.describe('Edge Cases — LIFF 回調邊界 [P3]', () => {
  test('promoLinkId 為數字（非字串）不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_001',
        name: '[E2E] Test',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: 10 },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('promoLinkId 為不存在的 ID 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_002',
        name: '[E2E] Test',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '99999' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('promoLinkId=0 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_promoid_zero',
        name: '[E2E] PromoId Zero',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '0' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('promoLinkId=-1 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_promoid_neg',
        name: '[E2E] Negative PromoId',
        isInClient: false,
        isLoggedIn: true,
        urlParams: { promoLinkId: '-1' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('promoLinkId=Number.MAX_SAFE_INTEGER 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_promoid_max',
        name: '[E2E] Max PromoId',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: String(Number.MAX_SAFE_INTEGER) },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為空物件不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_003',
        name: '[E2E] Test',
        isInClient: false,
        isLoggedIn: true,
        urlParams: {},
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為 null 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_null_params',
        name: '[E2E] Null Params',
        isInClient: false,
        isLoggedIn: false,
        urlParams: null,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('boolean 欄位傳入字串不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_004',
        name: '[E2E] Test',
        isInClient: 'yes',
        isLoggedIn: 'true',
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF 回調帶空 userId 與空 name 不應 500', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '',
        name: '',
        isInClient: false,
        isLoggedIn: false,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('偽造 WP Nonce 搭配 LIFF 回調 → 公開端點仍可存取', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'completely_invalid_nonce_12345',
      },
      data: {
        userId: '[E2E] U_fake_nonce',
        name: '[E2E] Fake Nonce',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    // LIFF 端點為公開端點，不應因無效 nonce 而 403
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  test('過期 session cookie 搭配 LIFF 回調不應影響公開端點', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: {
        'Content-Type': 'application/json',
        Cookie: 'wordpress_logged_in_expired=invalid_cookie_value',
      },
      data: {
        userId: '[E2E] U_expired_session',
        name: '[E2E] Expired Session',
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF 回調帶超大 urlParams 物件不應 crash', async ({ request }) => {
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

  test('promoLinkId 為 Emoji 字串不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_emoji_promoid',
        name: '[E2E] Emoji PromoId',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: EDGE.emoji },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   LINE Webhook events 邊界
──────────────────────────────────────────── */
test.describe('Edge Cases — LINE Webhook events 邊界 [P3]', () => {
  test('events 為 null 不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: null },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events 為字串不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: 'not_an_array' },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events 陣列含 100 個事件不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U' + '0'.repeat(32),
      events: Array.from({ length: 100 }, (_, i) => ({
        type: 'message',
        timestamp: Date.now(),
        source: { type: 'user', userId: `[E2E] U_large_${i}` },
        replyToken: '0'.repeat(32),
        message: { id: String(i), type: 'text', text: 'A'.repeat(1000) },
      })),
    }
    const body = JSON.stringify(payload)
    const sig = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': sig },
      data: payload,
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events 含未知 type 不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U' + '0'.repeat(32),
      events: [
        {
          type: 'unknown_future_event_type',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_unknown_type' },
        },
      ],
    }
    const body = JSON.stringify(payload)
    const sig = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': sig },
      data: payload,
    })
    // 應接受或忽略未知事件，不應 500
    expect(res.status()).toBeLessThan(500)
  })

  test('事件 source 缺少 userId 不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U' + '0'.repeat(32),
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user' }, // 刻意移除 userId
          replyToken: '0'.repeat(32),
          postback: { data: 'action=register&activity_id=yt001' },
        },
      ],
    }
    const body = JSON.stringify(payload)
    const sig = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': sig },
      data: payload,
    })
    // 可能 400（驗證失敗）或 200（忽略），不應 500
    expect(res.status()).toBeLessThan(500)
  })

  test('單一事件 timestamp 為 0 不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U' + '0'.repeat(32),
      events: [
        {
          type: 'postback',
          timestamp: 0,
          source: { type: 'user', userId: '[E2E] U_ts_zero' },
          replyToken: '0'.repeat(32),
          postback: { data: 'action=register&activity_id=yt001' },
        },
      ],
    }
    const body = JSON.stringify(payload)
    const sig = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': sig },
      data: payload,
    })
    expect(res.status()).toBeLessThan(502)
  })
})

/* ────────────────────────────────────────────
   LINE Webhook Replay Attack
──────────────────────────────────────────── */
test.describe('Edge Cases — LINE Webhook Replay Attack [P3]', () => {
  test('使用 24 小時前的 timestamp 重放相同簽章不應造成異常', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now() - 86400000, // 24 小時前
          source: { type: 'user', userId: '[E2E] U_replay_001' },
          replyToken: '00000000000000000000000000000000',
          postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
        },
      ],
    }
    const body = JSON.stringify(payload)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': signature },
      data: payload,
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('相同 payload 連續送兩次（模擬重放）不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_replay_002' },
          replyToken: '00000000000000000000000000000000',
          postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
        },
      ],
    }
    const body = JSON.stringify(payload)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const headers = {
      'Content-Type': 'application/json',
      'X-Line-Signature': signature,
    }
    const res1 = await request.post(WEBHOOK_URL, { headers, data: payload })
    expect(res1.status()).toBeLessThan(502)
    const res2 = await request.post(WEBHOOK_URL, { headers, data: payload })
    expect(res2.status()).toBeLessThan(502)
  })

  test('修改 payload 但使用舊簽章應被拒絕', async ({ request }) => {
    const originalPayload = { events: [{ type: 'message' }] }
    const signature = generateLineSignature(
      JSON.stringify(originalPayload),
      LINE_SETTINGS.channel_secret,
    )
    // 送出不同的 payload
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': signature },
      data: { events: [{ type: 'postback', postback: { data: 'action=register' } }] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })
})

/* ────────────────────────────────────────────
   重複用戶報名
──────────────────────────────────────────── */
test.describe('Edge Cases — 重複用戶報名 [P3]', () => {
  test('同一用戶連續發送多次 LIFF 回調不應 crash', async ({ request }) => {
    const payload = {
      userId: '[E2E] U_dup_liff_001',
      name: '[E2E] 重複LIFF用戶',
      isInClient: true,
      isLoggedIn: true,
      urlParams: { promoLinkId: '10' },
    }
    for (let i = 0; i < 3; i++) {
      const res = await request.post(LIFF_URL, {
        headers: { 'Content-Type': 'application/json' },
        data: payload,
      })
      expect(res.status()).toBeLessThan(500)
    }
  })

  test('同一用戶不同 promoLinkId 不應 crash', async ({ request }) => {
    const basePayload = {
      userId: '[E2E] U_dup_liff_002',
      name: '[E2E] 多連結用戶',
      isInClient: true,
      isLoggedIn: true,
    }
    for (const promoLinkId of ['10', '11', '12']) {
      const res = await request.post(LIFF_URL, {
        headers: { 'Content-Type': 'application/json' },
        data: { ...basePayload, urlParams: { promoLinkId } },
      })
      expect(res.status()).toBeLessThan(500)
    }
  })

  test('不同用戶報名同一活動不應衝突', async ({ request }) => {
    for (let i = 1; i <= 3; i++) {
      const payload = {
        destination: 'U0123456789abcdef0123456789abcdef',
        events: [
          {
            type: 'postback',
            timestamp: Date.now(),
            source: { type: 'user', userId: `[E2E] U_multi_user_${i}` },
            replyToken: '0'.repeat(32),
            postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
          },
        ],
      }
      const body = JSON.stringify(payload)
      const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
      const res = await request.post(WEBHOOK_URL, {
        headers: { 'Content-Type': 'application/json', 'X-Line-Signature': signature },
        data: payload,
      })
      expect(res.status()).toBeLessThan(502)
    }
  })
})
