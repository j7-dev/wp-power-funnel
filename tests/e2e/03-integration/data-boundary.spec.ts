/**
 * P2 — 資料邊界測試
 *
 * 涵蓋場景:
 *  - Unicode（中日韓、阿拉伯文）
 *  - RTL 文字（阿拉伯文）
 *  - Emoji（多種組合）
 *  - 超長字串（5000～10000 字元）
 *  - HTML 特殊字元
 *  - NULL byte 注入
 *  - 空字串 / 純空白
 *  - 空值（null、空物件）
 *  - 數值邊界（0、負數、最大安全整數、浮點數）
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

const LIFF_URL = `${BASE_URL}/wp-json/${EP.liff}`

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

/* ────────────────────────────────────────────
   Unicode 支援
──────────────────────────────────────────── */
test.describe('Data Boundary — Unicode 支援 [P2]', () => {
  test('LINE 設定值支援中日韓混合 Unicode 字元', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: `[E2E] ${EDGE.unicode}`,
        channel_id: '[E2E] unicode_ch',
        channel_secret: `[E2E] ${EDGE.unicode}`,
        channel_access_token: '[E2E] unicode_tok',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LINE 設定值支援日文字元', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: `[E2E] ${EDGE.japaneseText}`,
        channel_id: '[E2E] ja_ch',
        channel_secret: '[E2E] ja_secret',
        channel_access_token: '[E2E] ja_tok',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF callback 用戶名支援中文', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_zh_001',
        name: `[E2E] ${EDGE.chineseText}`,
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF callback 用戶名支援日文', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_ja_001',
        name: `[E2E] ${EDGE.japaneseText}`,
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

  test('活動 keyword 支援日文搜尋', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.japaneseText })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   RTL 文字
──────────────────────────────────────────── */
test.describe('Data Boundary — RTL 文字 [P2]', () => {
  test('LIFF callback 用戶名支援阿拉伯文（RTL）', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_rtl_001',
        name: `[E2E] ${EDGE.rtlText}`,
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('活動 keyword 支援阿拉伯文（RTL）搜尋', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.rtlText })
    expect(status).toBeLessThan(500)
  })

  test('LINE 設定值支援 RTL 文字', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: `[E2E] ${EDGE.rtlText}`,
        channel_id: '[E2E] rtl_ch',
        channel_secret: '[E2E] rtl_s',
        channel_access_token: '[E2E] rtl_t',
      },
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   Emoji 支援
──────────────────────────────────────────── */
test.describe('Data Boundary — Emoji 支援 [P2]', () => {
  test('LINE 設定值包含 Emoji 不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: `[E2E] ${EDGE.emoji}`,
        channel_id: '[E2E] em_ch',
        channel_secret: '[E2E] em_s',
        channel_access_token: `[E2E] ${EDGE.emoji}`,
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF callback 名稱包含 Emoji 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_emoji_001',
        name: `[E2E] 測試 ${EDGE.emoji}`,
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF callback userId 包含 Emoji 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: `[E2E] ${EDGE.emojiMixed}`,
        name: '[E2E] Emoji UserId',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('活動 keyword 包含 Emoji 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.emoji })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   超長字串
──────────────────────────────────────────── */
test.describe('Data Boundary — 超長字串 [P2]', () => {
  test('LINE channel_id 為 10,000 字元超長字串不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: '[E2E] short',
        channel_id: `[E2E] ${EDGE.longString}`,
        channel_secret: '[E2E] s',
        channel_access_token: '[E2E] t',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('YouTube clientId 為 10,000 字元超長字串不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: {
        clientId: `[E2E] ${EDGE.longString}`,
        clientSecret: '[E2E] normal_secret',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF userId 為 5,000 字元超長字串不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: `[E2E] ${'U'.repeat(5000)}`,
        name: '[E2E] Long UserId',
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('活動 keyword 為 5,000 字元超長字串不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: EDGE.longStringShort,
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   NULL Byte 注入
──────────────────────────────────────────── */
test.describe('Data Boundary — NULL Byte 注入 [P2]', () => {
  test('活動 keyword 含 NULL byte 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.nullByte })
    expect(status).toBeLessThan(500)
  })

  test('LIFF userId 含 NULL byte 不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: EDGE.nullByte,
        name: '[E2E] Null Byte User',
        isInClient: false,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LINE 設定值含 NULL byte 不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.nullByte,
        channel_id: '[E2E] null_ch',
        channel_secret: EDGE.nullByte,
        channel_access_token: '[E2E] null_t',
      },
    })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   HTML 特殊字元
──────────────────────────────────────────── */
test.describe('Data Boundary — HTML 特殊字元 [P2]', () => {
  test('LINE 設定含 HTML 實體字元不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: `[E2E] ${EDGE.htmlEntities}`,
        channel_id: '[E2E] html_ch',
        channel_secret: '[E2E] html_s',
        channel_access_token: '[E2E] html_t',
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LIFF name 含 HTML 標籤不應 crash', async ({ request }) => {
    const res = await request.post(LIFF_URL, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_html_001',
        name: EDGE.specialChars,
        isInClient: true,
        isLoggedIn: true,
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   空值與空白
──────────────────────────────────────────── */
test.describe('Data Boundary — 空值與空白 [P2]', () => {
  test('LINE 設定值為空字串不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.emptyString,
        channel_id: EDGE.emptyString,
        channel_secret: EDGE.emptyString,
        channel_access_token: EDGE.emptyString,
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('LINE 設定值為純空白字串不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      line: {
        liff_id: EDGE.whitespaceOnly,
        channel_id: EDGE.whitespaceOnly,
        channel_secret: EDGE.whitespaceOnly,
        channel_access_token: EDGE.whitespaceOnly,
      },
    })
    expect(status).toBeLessThan(500)
  })

  test('YouTube 設定部分欄位為空不應 crash', async () => {
    const { status } = await wpPost(opts, EP.options, {
      youtube: { clientId: EDGE.emptyString, clientSecret: EDGE.emptyString },
    })
    expect(status).toBeLessThan(500)
  })

  test('活動 keyword 為空字串不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.emptyString })
    expect(status).toBeLessThan(500)
  })

  test('活動 keyword 為純空白不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: EDGE.whitespaceOnly })
    expect(status).toBeLessThan(500)
  })
})

/* ────────────────────────────────────────────
   數值邊界
──────────────────────────────────────────── */
test.describe('Data Boundary — 數值邊界 [P2]', () => {
  test('last_n_days=0 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: String(EDGE.zero) })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=-1 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.negativeInt),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=-999 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.negativeInt2),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=0.5（浮點數）不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.floatValue),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=Number.MAX_SAFE_INTEGER 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.maxInt),
    })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=999999 超大數值不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: String(EDGE.largePositive),
    })
    expect(status).toBeLessThan(500)
  })

  test("last_n_days='not_a_number' 非數值字串不應 crash", async () => {
    const { status } = await wpGet(opts, EP.activities, {
      last_n_days: 'not_a_number',
    })
    expect(status).toBeLessThan(500)
  })

  test('活動 id=0 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: String(EDGE.zero) })
    expect(status).toBeLessThan(500)
  })

  test('活動 id=-1 不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: String(EDGE.negativeInt),
    })
    expect(status).toBeLessThan(500)
  })

  test('活動 id=999999 不存在不應 crash', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: String(EDGE.largePositive),
    })
    expect(status).toBeLessThan(500)
  })
})
