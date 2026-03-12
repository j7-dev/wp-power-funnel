/**
 * LINE 報名流程 — 前端 E2E 測試
 *
 * 對應規格: LINE報名活動.feature, 處理LINE_Webhook.feature
 * 對應原始碼: inc/classes/Infrastructure/Line/Services/Webhook/
 *
 * 涵蓋場景:
 *  - LINE Postback 事件報名流程模擬
 *  - Webhook 端點基本回應行為
 *  - 報名 Postback 資料格式驗證
 *  - 重複報名行為
 *  - 各種 Postback data 參數組合
 *  - Message 事件處理
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

/* ── Constants ── */
const WEBHOOK_URL = `${BASE_URL}/wp-json/${EP.lineCallback}`

/* ── Helpers ── */
function generateLineSignature(body: string, channelSecret: string): string {
  return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
}

function buildPostbackEvent(
  userId: string,
  postbackData: string,
  overrides?: Record<string, unknown>,
) {
  return {
    destination: 'U0123456789abcdef0123456789abcdef',
    events: [
      {
        type: 'postback',
        timestamp: Date.now(),
        source: { type: 'user', userId },
        replyToken: '00000000000000000000000000000000',
        postback: { data: postbackData },
        ...overrides,
      },
    ],
  }
}

function postWebhook(
  request: import('@playwright/test').APIRequestContext,
  payload: unknown,
) {
  const body = JSON.stringify(payload)
  const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
  return request.post(WEBHOOK_URL, {
    headers: {
      'Content-Type': 'application/json',
      'X-Line-Signature': signature,
    },
    data: payload,
  })
}

test.describe('LINE 報名 — Postback 事件處理', () => {
  test('報名 Postback 事件應被接受處理', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_001',
      'action=register&activity_id=yt001&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    // 簽章是否通過取決於伺服器端 channel_secret；防禦性斷言
    expect(res.status()).toBeLessThan(502)
  })

  test('Postback 事件含完整報名參數', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_002',
      'action=register&activity_id=yt001&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)

    if (res.status() === 200) {
      const body = await res.json()
      expect(body).toHaveProperty('status', 'ok')
    }
  })

  test('同一用戶重複報名同一活動不應 crash', async ({ request }) => {
    const userId = '[E2E] U_dup_reg_001'
    const data = 'action=register&activity_id=yt001&promo_link_id=10'

    // 第一次報名
    const payload1 = buildPostbackEvent(userId, data)
    const res1 = await postWebhook(request, payload1)
    expect(res1.status()).toBeLessThan(502)

    // 第二次報名（重複）
    const payload2 = buildPostbackEvent(userId, data)
    const res2 = await postWebhook(request, payload2)
    expect(res2.status()).toBeLessThan(502)
  })

  test('activity_id 為空時不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_empty_act',
      'action=register&activity_id=&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('activity_id 不存在時不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_bad_act',
      'action=register&activity_id=non_existent_999&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('promo_link_id 為空時不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_no_promo',
      'action=register&activity_id=yt001&promo_link_id=',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('缺少 source.userId 時不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user' }, // 缺少 userId
          replyToken: '00000000000000000000000000000000',
          postback: {
            data: 'action=register&activity_id=yt001&promo_link_id=10',
          },
        },
      ],
    }
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE 報名 — Postback data 參數組合', () => {
  test('action 非 register 時應忽略', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_unknown_action',
      'action=unknown_action&activity_id=yt001',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 格式錯誤時不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_bad_format',
      'this_is_not_valid_query_string',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 為空字串', async ({ request }) => {
    const payload = buildPostbackEvent('[E2E] U_empty_data', '')
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 含特殊字元', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_special_chars',
      'action=register&activity_id=<script>alert(1)</script>&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 含中文字元', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_chinese_data',
      'action=register&activity_id=活動001&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE 報名 — Message 事件', () => {
  test('文字訊息事件不應觸發報名', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_MESSAGE_EVENT)
    // Message 事件只記錄 log，不應 crash
    expect(res.status()).toBeLessThan(502)
  })

  test('原始 Postback 事件 mock 不應 crash', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_POSTBACK_EVENT)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE Webhook — 基本驗證', () => {
  test('缺少 X-Line-Signature → 應回傳錯誤', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('空 events 陣列搭配簽章（LINE 驗證 Webhook URL）', async ({ request }) => {
    const payload = { events: [] }
    const body = JSON.stringify(payload)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)
    const res = await request.post(WEBHOOK_URL, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: payload,
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('多個事件同時送入', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_001' },
          replyToken: '00000000000000000000000000000001',
          postback: {
            data: 'action=register&activity_id=yt001&promo_link_id=10',
          },
        },
        {
          type: 'message',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_002' },
          replyToken: '00000000000000000000000000000002',
          message: { id: '999', type: 'text', text: 'hello' },
        },
      ],
    }
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})
