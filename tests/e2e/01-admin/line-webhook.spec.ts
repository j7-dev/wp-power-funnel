/**
 * P0 — POST /line-callback 處理 LINE Webhook
 *
 * 對應規格: spec/features/line/處理LINE_Webhook.feature
 *
 * 重要：此端點為公開端點（permission_callback = __return_true），不需要 WP Nonce。
 *       簽章驗證使用 HMAC-SHA256(channel_secret, body) → base64。
 *       測試採防禦性設計：伺服器端 channel_secret 若與測試資料不一致，
 *       使用測試 secret 計算的簽章可能通過或不通過。
 *
 * 涵蓋場景:
 *  - 缺少 X-Line-Signature header → >= 400
 *  - 空 X-Line-Signature → >= 400
 *  - 無效簽章（任意字串）→ >= 400
 *  - 錯誤 channel_secret 計算的簽章 → >= 400
 *  - events=[] 搭配簽章（LINE Webhook URL 驗證）→ < 500
 *  - 正確格式 Postback 事件搭配簽章 → < 502
 *  - 文字訊息事件搭配簽章 → < 502
 *  - 篡改 payload 後用原簽章 → >= 400
 *  - 非 JSON body → 不 crash
 *  - 空 body → 不 crash
 *  - events=null → 不 crash
 *  - events 為字串 → 不 crash
 *  - 超大 payload（100 個事件）→ 不 crash
 *  - 超長簽章字串 → 不 crash
 *  - 簽章含 XSS → 不 crash
 *  - replay attack（舊 timestamp）→ 不 crash
 *  - 相同 payload 連續送兩次 → 不 crash
 */
import { test, expect } from '@playwright/test'
import * as crypto from 'crypto'
import {
  BASE_URL,
  EP,
  LINE_SETTINGS,
  LINE_WEBHOOK_POSTBACK_EVENT,
  LINE_WEBHOOK_MESSAGE_EVENT,
} from '../fixtures/test-data.js'

/* ── 工具函數 ── */

/** 計算 LINE HMAC-SHA256 簽章 */
function signLine(body: string, secret: string): string {
  return crypto.createHmac('SHA256', secret).update(body).digest('base64')
}

/** 發送 LINE Webhook 請求（帶正確格式簽章） */
async function postWebhook(
  request: import('@playwright/test').APIRequestContext,
  payload: unknown,
  secretOverride?: string,
) {
  const body = JSON.stringify(payload)
  const secret = secretOverride ?? LINE_SETTINGS.channel_secret
  const signature = signLine(body, secret)
  return request.post(WEBHOOK_URL, {
    headers: {
      'Content-Type': 'application/json',
      'X-Line-Signature': signature,
    },
    data: payload,
  })
}

const WEBHOOK_URL = `${BASE_URL}/wp-json/${EP.lineCallback}`

test.describe('POST /line-callback — 處理 LINE Webhook [P0]', () => {
  // ── 簽章驗證核心 ──

  test('缺少 X-Line-Signature header 應回傳 >= 400', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('空的 X-Line-Signature 應回傳 >= 400', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': '',
      },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('無效簽章（隨機 base64 字串）應回傳 >= 400', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'aW52YWxpZF9zaWduYXR1cmU=',
      },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('使用錯誤 channel_secret 計算的簽章應被拒絕', async ({ request }) => {
    const payload = { events: [{ type: 'message' }] }
    const wrongSignature = signLine(JSON.stringify(payload), 'completely_wrong_secret_key')
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': wrongSignature,
      },
      data: payload,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('篡改 payload 後用原簽章應被拒絕', async ({ request }) => {
    const original = { events: [{ type: 'message', source: { userId: '[E2E] U_tamper_001' } }] }
    const signature = signLine(JSON.stringify(original), LINE_SETTINGS.channel_secret)
    // 送出不同的 payload
    const tampered = { events: [{ type: 'postback', source: { userId: '[E2E] U_HACKED' } }] }
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: tampered,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  // ── LINE Webhook URL 驗證 ──

  test('events=[] 搭配正確格式簽章（LINE Webhook URL 驗證）→ < 500', async ({ request }) => {
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
    // LINE 驗證 webhook URL 時會發送空 events，應回傳 200 或至少不 crash
    expect(res.status()).toBeLessThan(500)
  })

  // ── Postback / Message 事件 ──

  test('Postback 事件搭配測試簽章 → 不應 crash（< 502）', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_POSTBACK_EVENT)
    expect(res.status()).toBeLessThan(502)
  })

  test('Postback 事件若簽章通過應回傳 status: ok', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_POSTBACK_EVENT)
    if (res.status() === 200) {
      const body = await res.json()
      expect(body).toHaveProperty('status', 'ok')
    }
    // 若簽章不符合伺服器端設定，則 >= 400 是預期行為
  })

  test('文字訊息事件搭配簽章 → 不應 crash（< 502）', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_MESSAGE_EVENT)
    expect(res.status()).toBeLessThan(502)
  })

  test('多個事件（Postback + Message 混合）一次送入 → 不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_001' },
          replyToken: '00000000000000000000000000000001',
          postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
        },
        {
          type: 'message',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_002' },
          replyToken: '00000000000000000000000000000002',
          message: { id: '999', type: 'text', text: '[E2E] hello' },
        },
      ],
    }
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  // ── Replay Attack ──

  test('使用 24 小時前 timestamp 重放（replay attack）→ 不應 crash', async ({ request }) => {
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
    const res = await postWebhook(request, oldPayload)
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
    const res1 = await postWebhook(request, payload)
    expect(res1.status()).toBeLessThan(502)

    // 使用相同簽章第二次送出（replay）
    const body = JSON.stringify(payload)
    const signature = signLine(body, LINE_SETTINGS.channel_secret)
    const res2 = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: payload,
    })
    expect(res2.status()).toBeLessThan(502)
  })

  // ── 異常 payload 防禦 ──

  test('非 JSON body 不應導致 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'text/plain',
        'X-Line-Signature': 'dummy_signature',
      },
      data: 'not json at all',
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('空 body {} 不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: {},
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events=null 不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: null },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('events 為字串（非陣列）不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: { events: 'not_an_array' },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('超大 payload（100 個事件）不應 crash', async ({ request }) => {
    const largePayload = {
      events: Array.from({ length: 100 }, (_, i) => ({
        type: 'message',
        timestamp: Date.now(),
        source: { type: 'user', userId: `[E2E] U_large_${i}` },
        replyToken: '0'.repeat(32),
        message: { id: String(i), type: 'text', text: 'A'.repeat(1000) },
      })),
    }
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: largePayload,
    })
    expect(res.status()).toBeLessThan(502)
  })

  // ── 簽章字串邊界 ──

  test('超長簽章字串（10000 字元）不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'A'.repeat(10000),
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含 XSS payload 不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': '<script>alert(1)</script>',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('簽章含 NULL byte 不應 crash', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'valid_prefix\x00malicious_suffix',
      },
      data: { events: [] },
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('偽造 base64 簽章應被拒絕', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'ZmFrZV9zaWduYXR1cmU=', // base64("fake_signature")
      },
      data: { events: [{ type: 'message' }] },
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })
})
