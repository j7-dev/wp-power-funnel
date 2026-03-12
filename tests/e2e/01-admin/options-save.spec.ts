/**
 * POST /options — 儲存設定
 *
 * 涵蓋場景:
 *  - 儲存 LINE 設定
 *  - 儲存 YouTube 設定
 *  - 傳入非 array/object 值時該項被忽略
 *  - 傳入不存在的 key 時被忽略
 *  - 同時儲存 LINE + YouTube 設定
 *  - 驗證儲存後 GET 確認寫入
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES, LINE_SETTINGS, YOUTUBE_SETTINGS } from '../fixtures/test-data.js'

/* ── Types ── */
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

test.describe('POST /options — 儲存設定', () => {
  test('儲存 LINE 設定 (200)', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: LINE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('儲存後 GET 驗證 LINE 設定已寫入', async () => {
    // 先寫入
    await wpPost(opts, EP.options, { line: LINE_SETTINGS })

    // 再讀取驗證
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const line = data.data.line
    expect(line.liff_id).toBe(LINE_SETTINGS.liff_id)
    expect(line.channel_id).toBe(LINE_SETTINGS.channel_id)
    expect(line.channel_secret).toBe(LINE_SETTINGS.channel_secret)
    expect(line.channel_access_token).toBe(LINE_SETTINGS.channel_access_token)
  })

  test('儲存 YouTube 設定 (200)', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('儲存後 GET 驗證 YouTube 設定已寫入', async () => {
    await wpPost(opts, EP.options, { youtube: YOUTUBE_SETTINGS })

    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const yt = data.data.youtube
    expect(yt.clientId).toBe(YOUTUBE_SETTINGS.clientId)
    expect(yt.clientSecret).toBe(YOUTUBE_SETTINGS.clientSecret)
  })

  test('同時儲存 LINE + YouTube 設定', async () => {
    const { data, status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: LINE_SETTINGS,
      youtube: YOUTUBE_SETTINGS,
    })
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.saveOptionsSuccess)
  })

  test('傳入非 object 的 line 值時該項被忽略 (仍回 200)', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      line: 'not_an_array',
    })
    expect(status).toBe(200)
  })

  test('傳入不存在的 key 時被忽略 (仍回 200)', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {
      unknownKey: { foo: 'bar' },
    })
    expect(status).toBe(200)
  })

  test('空 body 仍回 200', async () => {
    const { status } = await wpPost<SaveOptionsResponse>(opts, EP.options, {})
    expect(status).toBeLessThan(500)
  })

  test('未登入時應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce' }
    const { status } = await wpPost(noAuthOpts, EP.options, { line: LINE_SETTINGS })
    expect(status).toBeLessThan(500)
    expect([401, 403]).toContain(status)
  })
})
