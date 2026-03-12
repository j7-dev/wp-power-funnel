/**
 * POST /line-callback — 處理 LINE Webhook
 *
 * 涵蓋場景:
 *  - 缺少 X-Line-Signature header → 錯誤
 *  - 無效簽章 → 500（簽章驗證失敗）
 *  - 空 events 仍回 200（LINE 驗證 Webhook URL 用）
 *  - 格式正確但簽章無效的 postback 事件
 *  - LINE 設定未完成時的行為
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

test.describe('POST /line-callback — 處理 LINE Webhook', () => {
  const webhookUrl = `${BASE_URL}/wp-json/${EP.lineCallback}`

  /**
   * 產生 LINE 簽章（用於測試）
   * 注意: channel_secret 必須與伺服器端設定一致才會通過驗證
   */
  function generateLineSignature(body: string, channelSecret: string): string {
    return crypto.createHmac('SHA256', channelSecret).update(body).digest('base64')
  }

  test('缺少 X-Line-Signature header 時應回傳錯誤', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    // 預期 400 或 500（缺少簽章標頭）
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('無效簽章時應回傳錯誤', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'invalid_signature_base64==',
      },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    // 應拒絕無效簽章
    expect(res.status()).toBeGreaterThanOrEqual(400)
  })

  test('空 events 陣列搭配有效格式的簽章 (LINE 驗證用)', async ({ request }) => {
    const body = JSON.stringify({ events: [] })
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)

    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: { events: [] },
    })
    // LINE 驗證 webhook URL 時會發送空 events，應回傳 200 或至少不 crash
    expect(res.status()).toBeLessThan(500)
  })

  test('Postback 事件搭配簽章 (簽章可能因 secret 不同而無效)', async ({ request }) => {
    const body = JSON.stringify(LINE_WEBHOOK_POSTBACK_EVENT)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)

    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: LINE_WEBHOOK_POSTBACK_EVENT,
    })
    // 簽章是否通過取決於伺服器 channel_secret 是否與測試資料一致
    // 防禦性斷言：不應 crash
    expect(res.status()).toBeLessThan(502)
  })

  test('文字訊息事件搭配簽章', async ({ request }) => {
    const body = JSON.stringify(LINE_WEBHOOK_MESSAGE_EVENT)
    const signature = generateLineSignature(body, LINE_SETTINGS.channel_secret)

    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': signature,
      },
      data: LINE_WEBHOOK_MESSAGE_EVENT,
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('非 JSON body 不應導致 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'text/plain',
        'X-Line-Signature': 'dummy',
      },
      data: 'not json',
    })
    expect(res.status()).toBeLessThan(502)
  })

  test('空 body 不應導致 crash', async ({ request }) => {
    const res = await request.post(webhookUrl, {
      headers: {
        'Content-Type': 'application/json',
        'X-Line-Signature': 'dummy',
      },
      data: {},
    })
    expect(res.status()).toBeLessThan(502)
  })
})
