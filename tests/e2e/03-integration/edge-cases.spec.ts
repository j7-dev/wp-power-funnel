/**
 * Edge Cases — 邊界條件測試
 *
 * 涵蓋場景:
 *  - 空活動列表
 *  - OAuth 未授權時撤銷
 *  - 重複儲存設定
 *  - 無效工作流節點（透過 API 間接測試）
 *  - LIFF 各種邊界輸入
 *  - LINE Webhook 異常 payload
 *  - LIFF 回調 token / 認證邊界
 *  - LINE Webhook replay attack 簽章
 *  - 重複用戶報名
 *  - OAuth 撤銷無效憑證
 */
import { test, expect } from '@playwright/test'
import * as crypto from 'crypto'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, LINE_SETTINGS, YOUTUBE_SETTINGS } from '../fixtures/test-data.js'

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('Edge Cases — 活動列表', () => {
  test('超大 last_n_days 值不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '999999' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days 為 0 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '0' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days 為負數 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '-1' })
    expect(status).toBeLessThan(500)
  })

  test('keyword 為空字串 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: '' })
    expect(status).toBeLessThan(500)
  })

  test('keyword 為超長字串 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: 'A'.repeat(5000) })
    expect(status).toBeLessThan(500)
  })

  test('同時傳入 id + keyword + last_n_days → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: 'yt001',
      keyword: 'React',
      last_n_days: '30',
    })
    expect(status).toBeLessThan(500)
  })
})

test.describe('Edge Cases — OAuth 撤銷', () => {
  test('連續撤銷兩次不應 500', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('未曾授權就撤銷不應 500', async () => {
    // 先撤銷確保沒有 token
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    // 再撤銷一次
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })
})

test.describe('Edge Cases — 儲存設定', () => {
  test('重複儲存相同設定 → 不應 500', async () => {
    await wpPost(opts, EP.options, { line: LINE_SETTINGS })
    const { status } = await wpPost(opts, EP.options, { line: LINE_SETTINGS })
    expect(status).toBe(200)
  })

  test('儲存空 object 作為 LINE 設定', async () => {
    const { status } = await wpPost(opts, EP.options, { line: {} })
    expect(status).toBeLessThan(500)
  })

  test('儲存 null 值 → 不應 500', async () => {
    const { status } = await wpPost(opts, EP.options, { line: null })
    expect(status).toBeLessThan(500)
  })

  test('儲存多餘欄位 → 不應 500', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: { ...LINE_SETTINGS, extraField: 'should be ignored' },
    })
    expect(status).toBeLessThan(500)
  })

  test('儲存 YouTube 設定包含 redirectUri', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBe(200)
  })
})

test.describe('Edge Cases — LIFF 回調', () => {
  const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`

  test('promoLinkId 為數字 (非字串) → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_001',
        name: 'Test',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: 10 },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('promoLinkId 為不存在的 ID → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_002',
        name: 'Test',
        isInClient: true,
        isLoggedIn: true,
        urlParams: { promoLinkId: '99999' },
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('urlParams 為空物件 → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_003',
        name: 'Test',
        isInClient: false,
        isLoggedIn: true,
        urlParams: {},
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('boolean 欄位傳入字串 → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_edge_004',
        name: 'Test',
        isInClient: 'yes',
        isLoggedIn: 'true',
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

test.describe('Edge Cases — LINE Webhook', () => {
  const webhookUrl = `${BASE_URL}/wp-json/${EP.lineCallback}`

  test('events 為 null → 不應 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: null },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events 為字串 → 不應 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: 'not_an_array' },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('超大 payload → 不應 crash', async ({ request }) => {
    const largePayload = {
      events: Array.from({ length: 100 }, (_, i) => ({
        type: 'message',
        timestamp: Date.now(),
        source: { type: 'user', userId: `[E2E] U_large_${i}` },
        replyToken: '0'.repeat(32),
        message: { id: String(i), type: 'text', text: 'A'.repeat(1000) },
      })),
    }
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: largePayload,
    })
    expect(res.status()).toBeLessThan(502)
  })
})

/* ── 以下為新增的邊界條件測試 ── */

function generateLineSignature(body: string, channelSecret: string): string {
  return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
}

test.describe('Edge Cases — LIFF 回調 Token 與認證邊界', () => {
  const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`

  test('偽造 WP Nonce 搭配 LIFF 回調 → 公開端點仍可存取', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': 'completely_invalid_nonce_12345',
      },
      data: {
        userId: '[E2E] U_fake_nonce',
        name: '[E2E] Fake Nonce User',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    // LIFF 端點為公開端點，不應因無效 nonce 而 403
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  test('過期的 session cookie 搭配 LIFF 回調 → 不應影響公開端點', async ({ request }) => {
    const res = await request.post(liffUrl, {
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

  test('LIFF 回調帶空 userId 與空 name → 不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
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

  test('LIFF 回調帶超大 urlParams 物件 → 不應 crash', async ({ request }) => {
    const hugeParams: Record<string, string> = {}
    for (let i = 0; i < 100; i++) {
      hugeParams[`param_${i}`] = 'A'.repeat(500)
    }
    const res = await request.post(liffUrl, {
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
})

test.describe('Edge Cases — LINE Webhook Replay Attack', () => {
  const webhookUrl = `${BASE_URL}/wp-json/${EP.lineCallback}`

  test('使用過去的 timestamp 重放相同簽章 → 不應造成異常', async ({ request }) => {
    const oldPayload = {
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
    const body = JSON.stringify(oldPayload)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: oldPayload,
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('相同 payload 連續送兩次（模擬重放）→ 不應 crash', async ({ request }) => {
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

    const res1 = await request.post(webhookUrl, { headers, data: payload })
    expect(res1.status()).toBeLessThan(502)

    const res2 = await request.post(webhookUrl, { headers, data: payload })
    expect(res2.status()).toBeLessThan(502)
  })

  test('修改 payload 但使用舊簽章 → 應被拒絕', async ({ request }) => {
    const originalPayload = { events: [{ type: 'message' }] }
    const signature = generateLineSignature(
      JSON.stringify(originalPayload),
      LINE_SETTINGS.channel_secret,
    )
    // 送出不同的 payload
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: { events: [{ type: 'postback', postback: { data: 'action=register' } }] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })
})

test.describe('Edge Cases — 重複用戶報名', () => {
  const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`

  test('同一用戶連續發送多次 LIFF 回調 → 不應 crash', async ({ request }) => {
    const payload = {
      userId: '[E2E] U_dup_liff_001',
      name: '[E2E] 重複LIFF用戶',
      isInClient: true,
      isLoggedIn: true,
      urlParams: { promoLinkId: '10' },
    }

    // 連續三次
    const res1 = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: payload,
    })
    expect(res1.status()).toBeLessThan(500)

    const res2 = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: payload,
    })
    expect(res2.status()).toBeLessThan(500)

    const res3 = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: payload,
    })
    expect(res3.status()).toBeLessThan(500)
  })

  test('同一用戶不同 promoLinkId → 不應 crash', async ({ request }) => {
    const basePayload = {
      userId: '[E2E] U_dup_liff_002',
      name: '[E2E] 多連結用戶',
      isInClient: true,
      isLoggedIn: true,
    }

    const res1 = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: { ...basePayload, urlParams: { promoLinkId: '10' } },
    })
    expect(res1.status()).toBeLessThan(500)

    const res2 = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: { ...basePayload, urlParams: { promoLinkId: '11' } },
    })
    expect(res2.status()).toBeLessThan(500)
  })

  test('不同用戶報名同一活動 → 不應衝突', async ({ request }) => {
    const webhookUrl = `${BASE_URL}/wp-json/${EP.lineCallback}`
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
      const res = await request.post(webhookUrl, {
        headers: {
          'Content-Type': 'application/json',
          'X-Line-Signature': signature,
        },
        data: payload,
      })
      expect(res.status()).toBeLessThan(502)
    }
  })
})

test.describe('Edge Cases — OAuth 撤銷無效憑證', () => {
  test('YouTube 設定為空後撤銷 OAuth → 不應 500', async () => {
    // 先清空 YouTube 設定
    await wpPost(opts, EP.options, {
      youtube: { clientId: '', clientSecret: '' },
    })
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('YouTube 設定為 XSS payload 後撤銷 OAuth → 不應 500', async () => {
    await wpPost(opts, EP.options, {
      youtube: {
        clientId: '<script>alert(1)</script>',
        clientSecret: '"; DROP TABLE wp_options; --',
      },
    })
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('連續快速撤銷 OAuth 三次 → 不應 crash', async () => {
    const results = await Promise.all([
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
    ])
    for (const r of results) {
      expect(r.status).toBeLessThan(500)
    }
  })

  // 還原設定
  test('還原 YouTube 設定', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBeLessThan(500)
  })
})
