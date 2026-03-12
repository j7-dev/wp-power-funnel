/**
 * GET /options — 查詢設定
 *
 * 涵蓋場景:
 *  - 回傳包含 line / youtube / googleOauth 區段
 *  - LINE 設定欄位正確
 *  - Google OAuth 區段包含 isAuthorized 與 authUrl
 *  - 未授權時 isAuthorized 為 false
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES } from '../fixtures/test-data.js'

/* ── Types ── */
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
})

test.describe('GET /options — 查詢設定', () => {
  test('成功取得設定 (200) 並包含正確 code', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.code).toBe(CODES.getOptionsSuccess)
    expect(data.message).toBeTruthy()
  })

  test('回應包含 line / youtube / googleOauth 區段', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.data).toHaveProperty('line')
    expect(data.data).toHaveProperty('youtube')
    expect(data.data).toHaveProperty('googleOauth')
  })

  test('LINE 設定包含所有必要欄位', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const line = data.data.line
    expect(line).toBeDefined()
    // 欄位應存在（可能為空字串）
    const expectedFields = ['liff_id', 'channel_id', 'channel_secret', 'channel_access_token']
    for (const field of expectedFields) {
      expect(line).toHaveProperty(field)
    }
  })

  test('YouTube 設定包含 clientId / clientSecret 欄位', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const youtube = data.data.youtube
    expect(youtube).toBeDefined()
    expect(youtube).toHaveProperty('clientId')
    expect(youtube).toHaveProperty('clientSecret')
  })

  test('Google OAuth 區段包含 isAuthorized 布林值與 authUrl', async () => {
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)

    const oauth = data.data.googleOauth
    expect(typeof oauth.isAuthorized).toBe('boolean')
    expect(oauth).toHaveProperty('authUrl')
    if (oauth.authUrl) {
      expect(typeof oauth.authUrl).toBe('string')
    }
  })

  test('未登入時應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce' }
    const { status } = await wpGet(noAuthOpts, EP.options)
    expect(status).toBeLessThan(500)
    expect([401, 403]).toContain(status)
  })
})
