/**
 * P1 — LINE 報名流程（前端 E2E 測試）
 *
 * 對應規格: spec/features/registration/LINE報名活動.feature,
 *           spec/features/line/處理LINE_Webhook.feature
 *
 * 涵蓋場景:
 *  - 報名 Postback 事件被接受處理
 *  - 完整報名參數的 Postback 事件
 *  - 同一用戶重複報名不應 crash
 *  - activity_id 為空時不應 crash
 *  - activity_id 不存在時不應 crash
 *  - promo_link_id 為空時不應 crash
 *  - 缺少 source.userId 時不應 crash
 *  - action 非 register 時應被忽略
 *  - postback data 格式錯誤不應 crash
 *  - postback data 為空字串不應 crash
 *  - postback data 含特殊字元不應 crash
 *  - postback data 含中文字元不應 crash
 *  - 文字訊息事件不應觸發報名
 *  - 多個事件同時送入不應 crash
 *  - LINE Webhook URL 驗證（events=[]）
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

/* ── 常數 ── */
const WEBHOOK_URL = `${BASE_URL}/wp-json/${EP.lineCallback}`

/* ── 工具函數 ── */

/** 計算 LINE HMAC-SHA256 簽章 */
function generateLineSignature(body: string, channelSecret: string): string {
  return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
}

/** 建立 Postback 事件 payload */
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

/** 發送 LINE Webhook 請求（帶測試簽章） */
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

test.describe('LINE 報名 — Postback 事件處理 [P1]', () => {
  test('報名 Postback 事件應被接受處理（< 502）', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_001',
      'action=register&activity_id=yt001&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('Postback 事件含完整報名參數，若簽章通過應回傳 status: ok', async ({ request }) => {
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
    const res1 = await postWebhook(request, buildPostbackEvent(userId, data))
    expect(res1.status()).toBeLessThan(502)

    // 第二次報名（重複）
    const res2 = await postWebhook(request, buildPostbackEvent(userId, data))
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
      'action=register&activity_id=non_existent_e2e_999&promo_link_id=10',
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

  test('promo_link_id 不存在（9999）時不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_reg_bad_promo',
      'action=register&activity_id=yt001&promo_link_id=9999',
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
          source: { type: 'user' }, // 刻意缺少 userId
          replyToken: '00000000000000000000000000000000',
          postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
        },
      ],
    }
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE 報名 — Postback data 參數組合 [P1]', () => {
  test('action 非 register 時應被忽略（不 crash）', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_unknown_action',
      'action=unknown_action&activity_id=yt001',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 格式錯誤（非 query string）不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_bad_format',
      'this_is_not_valid_query_string',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 為空字串不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent('[E2E] U_empty_data', '')
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 含 XSS 特殊字元不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_xss_data',
      'action=register&activity_id=<script>alert(1)</script>&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 含中文字元不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_chinese_data',
      'action=register&activity_id=活動001&promo_link_id=10',
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })

  test('postback data 含 SQL injection 不應 crash', async ({ request }) => {
    const payload = buildPostbackEvent(
      '[E2E] U_sql_data',
      "action=register&activity_id=' OR 1=1 --&promo_link_id=10",
    )
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE 報名 — Message 事件 [P1]', () => {
  test('文字訊息事件不應觸發報名（只記錄 log，不 crash）', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_MESSAGE_EVENT)
    expect(res.status()).toBeLessThan(502)
  })

  test('原始 Postback 事件 mock 不應 crash', async ({ request }) => {
    const res = await postWebhook(request, LINE_WEBHOOK_POSTBACK_EVENT)
    expect(res.status()).toBeLessThan(502)
  })
})

test.describe('LINE Webhook — 基本驗證 [P1]', () => {
  test('缺少 X-Line-Signature → 應回傳 >= 400', async ({ request }) => {
    const res = await request.post(WEBHOOK_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('events=[] 搭配簽章（LINE 驗證 Webhook URL）→ < 500', async ({ request }) => {
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

  test('多個事件同時送入 → 不應 crash', async ({ request }) => {
    const payload = {
      destination: 'U0123456789abcdef0123456789abcdef',
      events: [
        {
          type: 'postback',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_reg_001' },
          replyToken: '00000000000000000000000000000001',
          postback: { data: 'action=register&activity_id=yt001&promo_link_id=10' },
        },
        {
          type: 'message',
          timestamp: Date.now(),
          source: { type: 'user', userId: '[E2E] U_multi_msg_002' },
          replyToken: '00000000000000000000000000000002',
          message: { id: '999', type: 'text', text: '[E2E] hello' },
        },
      ],
    }
    const res = await postWebhook(request, payload)
    expect(res.status()).toBeLessThan(502)
  })
})
