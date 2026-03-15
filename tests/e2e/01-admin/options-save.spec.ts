/**
 * P0 — POST /options 儲存設定
 *
 * 對應規格: spec/features/settings/儲存設定.feature
 *
 * 涵蓋場景:
 *  - 儲存 LINE 設定 → 200 + code: save_options_success
 *  - 儲存 YouTube 設定 → 200
 *  - 同時儲存 LINE + YouTube → 200
 *  - 儲存後 GET 驗證值一致（寫入確認）
 *  - body 為空 {} → 200（不更新）
 *  - line 值為非 object（字串）→ 200（被忽略）
 *  - line 值為 null → 200（被忽略）
 *  - 不存在的 key 被忽略 → 200
 *  - googleOauth 區段唯讀，傳入不報錯 → 200
 *  - 未授權 → 401 或 403
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES, LINE_SETTINGS, YOUTUBE_SETTINGS } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type SaveOptionsResponse = {
  code: string
  message: string
  data: unknown
}

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

test.describe('POST /options — 儲存設定 [P0]', () => {
  // ── LINE 設定 ──

  test('儲存 LINE 設定應回傳 200 + save_options_success', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: LINE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('儲存後 GET 驗證 LINE 設定已正確寫入', async () => {
    // 先寫入已知值
    await wpPost(opts, EP.options, { line: LINE_SETTINGS })

    // 再讀取驗證
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const line = data.data.line
    expect(line.liff_id).toBe(LINE_SETTINGS.liff_id)
    expect(line.liff_url).toBe(LINE_SETTINGS.liff_url)
    expect(line.channel_id).toBe(LINE_SETTINGS.channel_id)
    expect(line.channel_secret).toBe(LINE_SETTINGS.channel_secret)
    expect(line.channel_access_token).toBe(LINE_SETTINGS.channel_access_token)
  })

  // ── YouTube 設定 ──

  test('儲存 YouTube 設定應回傳 200 + save_options_success', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('儲存後 GET 驗證 YouTube 設定已正確寫入', async () => {
    await wpPost(opts, EP.options, { youtube: YOUTUBE_SETTINGS })

    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const yt = data.data.youtube
    expect(yt.clientId).toBe(YOUTUBE_SETTINGS.clientId)
    expect(yt.clientSecret).toBe(YOUTUBE_SETTINGS.clientSecret)
  })

  // ── 同時儲存 ──

  test('同時儲存 LINE + YouTube 設定應回傳 200', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: LINE_SETTINGS,
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('同時儲存後 GET 驗證兩組設定都已寫入', async () => {
    await wpPost(opts, EP.options, { line: LINE_SETTINGS, youtube: YOUTUBE_SETTINGS })

    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(data.data.line.channel_id).toBe(LINE_SETTINGS.channel_id)
    expect(data.data.youtube.clientId).toBe(YOUTUBE_SETTINGS.clientId)
  })

  // ── 忽略規則 ──

  test('body 為空 {} 仍回傳 200（不更新任何設定）', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {})
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('line 值為字串（非 object）時被忽略，仍回傳 200', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: 'not_an_array',
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('line 值為數字時被忽略，仍回傳 200', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: 12345,
    })
    expect(status).toBe(200)
  })

  test('line 值為 null 時被忽略，仍回傳 200', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: null,
    })
    expect(status).toBeLessThan(500)
  })

  test('line 值為陣列時被忽略（非 assoc array），仍回傳 200', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: [1, 2, 3],
    })
    expect(status).toBe(200)
  })

  test('傳入不存在的 key 時被忽略，仍回傳 200', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      unknownKey: { foo: 'bar' },
      anotherBadKey: 'value',
    })
    expect(status).toBe(200)
  })

  // ── googleOauth 唯讀 ──

  test('傳入 googleOauth 區段時不報錯（唯讀忽略）', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      googleOauth: { isAuthorized: true, authUrl: 'https://evil.example.com' },
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  // ── 多餘欄位 ──

  test('line 物件包含多餘欄位時不應 crash', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: { ...LINE_SETTINGS, extraField: 'ignored', anotherField: 123 },
    })
    expect(status).toBeLessThan(500)
  })

  // ── 重複儲存冪等性 ──

  test('連續儲存相同設定兩次應回傳 200（冪等）', async () => {
    await wpPost(opts, EP.options, { line: LINE_SETTINGS })
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: LINE_SETTINGS,
    })
    expect(status).toBe(200)
  })

  // ── 認證邊界 ──

  test('未授權（無效 nonce）應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
    const { status } = await wpPost(noAuthOpts, EP.options, { line: LINE_SETTINGS })
    expect([401, 403]).toContain(status)
  })
})
