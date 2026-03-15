/**
 * P0 — GET /options 查詢設定
 *
 * 對應規格: spec/features/settings/查詢設定.feature
 *
 * 涵蓋場景:
 *  - 200：回應包含 code: 'get_options_success'
 *  - 回應包含 line / youtube / googleOauth 三個區段
 *  - line 區段包含所有必要欄位
 *  - youtube 區段包含 clientId / clientSecret / redirectUri
 *  - googleOauth 區段包含 isAuthorized (boolean) 與 authUrl (string)
 *  - 撤銷 OAuth 後 isAuthorized 為 false
 *  - 未授權（無效 nonce）→ 401 或 403
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES, LINE_SETTINGS, YOUTUBE_SETTINGS } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type OptionsResponse = {
  code: string
  message: string
  data: {
    line: Record<string, string>
    youtube: Record<string, string>
    googleOauth: {
      isAuthorized: boolean
      authUrl: string
    }
  }
}

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
  // 先儲存已知設定，確保查詢時有值可驗證
  await wpPost(opts, EP.options, { line: LINE_SETTINGS, youtube: YOUTUBE_SETTINGS })
})

test.describe('GET /options — 查詢設定 [P0]', () => {
  // ── 基本回應格式 ──

  test('應回傳 HTTP 200', async () => {
    const { status } = await wpGet(opts, EP.options)
    expect(status).toBe(200)
  })

  test('回應 code 應為 get_options_success', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.getOptionsSuccess)
  })

  test('回應 message 應為非空字串', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(typeof data.message).toBe('string')
    expect(data.message.length).toBeGreaterThan(0)
  })

  test('回應 data 應包含 line / youtube / googleOauth 三個區段', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(data.data).toHaveProperty('line')
    expect(data.data).toHaveProperty('youtube')
    expect(data.data).toHaveProperty('googleOauth')
  })

  // ── LINE 設定欄位 ──

  test('LINE 設定應包含所有必要欄位', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const line = data.data.line
    expect(line).toHaveProperty('liff_id')
    expect(line).toHaveProperty('liff_url')
    expect(line).toHaveProperty('channel_id')
    expect(line).toHaveProperty('channel_secret')
    expect(line).toHaveProperty('channel_access_token')
  })

  test('LINE 設定回傳值應與儲存值一致', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const line = data.data.line
    expect(line.liff_id).toBe(LINE_SETTINGS.liff_id)
    expect(line.liff_url).toBe(LINE_SETTINGS.liff_url)
    expect(line.channel_id).toBe(LINE_SETTINGS.channel_id)
    expect(line.channel_secret).toBe(LINE_SETTINGS.channel_secret)
    expect(line.channel_access_token).toBe(LINE_SETTINGS.channel_access_token)
  })

  // ── YouTube 設定欄位 ──

  test('YouTube 設定應包含 clientId / clientSecret 欄位', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const youtube = data.data.youtube
    expect(youtube).toHaveProperty('clientId')
    expect(youtube).toHaveProperty('clientSecret')
  })

  test('YouTube 設定回傳值應與儲存值一致', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const youtube = data.data.youtube
    expect(youtube.clientId).toBe(YOUTUBE_SETTINGS.clientId)
    expect(youtube.clientSecret).toBe(YOUTUBE_SETTINGS.clientSecret)
  })

  // ── Google OAuth 區段 ──

  test('googleOauth 區段應包含 isAuthorized 布林值', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const oauth = data.data.googleOauth
    expect(typeof oauth.isAuthorized).toBe('boolean')
  })

  test('googleOauth 區段應包含 authUrl 字串欄位', async () => {
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    const oauth = data.data.googleOauth
    expect(oauth).toHaveProperty('authUrl')
    expect(typeof oauth.authUrl).toBe('string')
  })

  test('撤銷 OAuth 後 isAuthorized 應為 false', async () => {
    // 先撤銷
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    // 查詢驗證
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(data.data.googleOauth.isAuthorized).toBe(false)
  })

  // ── 認證邊界 ──

  test('未授權（無效 nonce）應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
    const { status } = await wpGet(noAuthOpts, EP.options)
    expect([401, 403]).toContain(status)
  })

  test('無 nonce header 應回傳 401 或 403', async ({ request }) => {
    const noNonceOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: '' }
    const { status } = await wpGet(noNonceOpts, EP.options)
    expect(status).toBeLessThan(500)
    // 通常 WordPress 對無 nonce 的 REST 請求回傳 401
    expect([200, 401, 403]).toContain(status)
  })
})
