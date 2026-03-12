/**
 * Data Boundary Tests — 資料邊界測試
 *
 * 涵蓋場景:
 *  - Unicode（中日韓、阿拉伯文）
 *  - Emoji
 *  - 超長字串
 *  - HTML 特殊字元
 *  - 空白字串 / 空值
 *  - 數值邊界
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, EDGE } from '../fixtures/test-data.js'

type OptionsResponse = {
  code: string
  data: {
    line: Record<string, string>
    youtube: Record<string, string>
    googleOauth: Record<string, unknown>
  }
}

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('Data Boundary — Unicode 支援', () => {
  test('LINE 設定支援 Unicode 字元', async () => {
    const unicodeSettings = {
      liff_id: `[E2E] ${EDGE.unicode}`,
      channel_id: '[E2E] unicode_channel',
      channel_secret: `[E2E] ${EDGE.unicode}`,
      channel_access_token: '[E2E] unicode_token',
    }
    const { status } = await wpPost(opts, EP.options, { line: unicodeSettings })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const { data } = await wpGet<OptionsResponse>(opts, EP.options)
      expect(data.data.line.liff_id).toContain('[E2E]')
    }
  })

  test('LIFF callback 支援 Unicode 用戶名', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_unicode_01',
        name: `[E2E] ${EDGE.unicode}`,
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('活動 keyword 支援中文搜尋', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: '直播教學' })
    expect(status).toBeLessThan(500)
  })
})

test.describe('Data Boundary — Emoji 支援', () => {
  test('LINE 設定包含 Emoji 不應 crash', async () => {
    const emojiSettings = {
      liff_id: `[E2E] ${EDGE.emoji}`,
      channel_id: '[E2E] emoji_channel',
      channel_secret: '[E2E] emoji_secret',
      channel_access_token: `[E2E] ${EDGE.emoji}`,
    }
    const { status } = await wpPost(opts, EP.options, { line: emojiSettings })
    expect(status).toBeLessThan(500)
  })

  test('LIFF callback 名稱包含 Emoji', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_emoji_01',
        name: `[E2E] 測試 ${EDGE.emoji}`,
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('活動 keyword 包含 Emoji', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.emoji })
    expect(status).toBeLessThan(500)
  })
})

test.describe('Data Boundary — 超長字串', () => {
  test('LINE 設定值為 10,000 字長字串', async () => {
    const longSettings = {
      liff_id: `[E2E] ${EDGE.longString.slice(0, 1000)}`,
      channel_id: '[E2E] long_channel',
      channel_secret: `[E2E] ${EDGE.longString.slice(0, 500)}`,
      channel_access_token: `[E2E] ${EDGE.longString.slice(0, 500)}`,
    }
    const { status } = await wpPost(opts, EP.options, { line: longSettings })
    expect(status).toBeLessThan(500)
  })

  test('YouTube clientId 為超長字串', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: {
        clientId: `[E2E] ${EDGE.longString}`,
        clientSecret: '[E2E] normal_secret',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF userId 為超長字串', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: `[E2E] ${'U'.repeat(5000)}`,
        name: '[E2E] Long User',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

test.describe('Data Boundary — HTML 特殊字元', () => {
  test('LINE 設定含 HTML 實體', async () => {
    const htmlSettings = {
      liff_id: `[E2E] ${EDGE.htmlEntities}`,
      channel_id: '[E2E] html_channel',
      channel_secret: `[E2E] ${EDGE.specialChars}`,
      channel_access_token: '[E2E] html_token',
    }
    const { status } = await wpPost(opts, EP.options, { line: htmlSettings })
    expect(status).toBeLessThan(500)
  })

  test('LIFF name 含 HTML 標籤', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/wp-json/${EP.liff}`, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_html_01',
        name: EDGE.specialChars,
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

test.describe('Data Boundary — 空值與空白', () => {
  test('LINE 設定值為空字串', async () => {
    const emptySettings = {
      liff_id: '',
      channel_id: '',
      channel_secret: '',
      channel_access_token: '',
    }
    const { status } = await wpPost(opts, EP.options, { line: emptySettings })
    expect(status).toBeLessThan(500)
  })

  test('LINE 設定值為空白字串', async () => {
    const wsSettings = {
      liff_id: EDGE.whitespaceOnly,
      channel_id: EDGE.whitespaceOnly,
      channel_secret: EDGE.whitespaceOnly,
      channel_access_token: EDGE.whitespaceOnly,
    }
    const { status } = await wpPost(opts, EP.options, { line: wsSettings })
    expect(status).toBeLessThan(500)
  })

  test('YouTube 設定部分欄位為空', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: { clientId: '', clientSecret: '' },
    })
    expect(status).toBeLessThan(500)
  })
})

test.describe('Data Boundary — 數值邊界', () => {
  test('last_n_days 為最大安全整數', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.maxInt),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days 為浮點數', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.floatValue),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days 為非數值字串 → 不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: 'not_a_number',
    })
    expect(status).toBeLessThan(500)
  })
})
