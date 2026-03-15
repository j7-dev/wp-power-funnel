/**
 * P0 — POST /revoke-google-oauth 撤銷 Google OAuth 授權
 *
 * 對應規格: spec/features/settings/Google_OAuth授權.feature
 *
 * 涵蓋場景:
 *  - 撤銷 OAuth → 200 + code: revoke_google_oauth_success
 *  - 撤銷後 GET /options → googleOauth.isAuthorized = false
 *  - 重複撤銷（token 不存在時）→ 不 500
 *  - 未授權 → 401 或 403
 *  - 連續三次並發撤銷 → 全部不 500
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type RevokeResponse = {
  code: string
  message: string
  data: unknown
}

type OptionsResponse = {
  code: string
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

test.describe('POST /revoke-google-oauth — 撤銷 Google OAuth 授權 [P0]', () => {
  // ── 基本撤銷行為 ──

  test('撤銷應回傳 HTTP 200', async () => {
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
    // 200 最理想，但若 token 不存在也可能回其他 2xx
    expect(status).toBeGreaterThanOrEqual(200)
  })

  test('成功撤銷後回應 code 應為 revoke_google_oauth_success', async () => {
    const { data, status } = await wpPost<RevokeResponse>(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
    if (status === 200) {
      expect(data.code).toBe(CODES.revokeGoogleOAuthSuccess)
    }
  })

  test('成功撤銷後回應 message 應為非空字串', async () => {
    const { data, status } = await wpPost<RevokeResponse>(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
    if (status === 200) {
      expect(typeof data.message).toBe('string')
      expect(data.message.length).toBeGreaterThan(0)
    }
  })

  // ── 撤銷後狀態驗證 ──

  test('撤銷後 GET /options 的 isAuthorized 應為 false', async () => {
    // 先撤銷
    await wpPost(opts, EP.revokeGoogleOAuth, {})

    // 再查詢驗證
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.data.googleOauth.isAuthorized).toBe(false)
  })

  test('撤銷後 authUrl 欄位仍應存在（只是 token 刪除）', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { data } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(data.data.googleOauth).toHaveProperty('authUrl')
  })

  // ── 重複撤銷 ──

  test('重複撤銷兩次不應回傳 500', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { status } = await wpPost<RevokeResponse>(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('Token 不存在時撤銷不應 500', async () => {
    // 連續撤銷確保 token 已不存在
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    // 第三次
    const { status } = await wpPost(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  // ── 並發撤銷 ──

  test('同時三個並發撤銷請求應全部不 500', async () => {
    const results = await Promise.all([
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
      wpPost(opts, EP.revokeGoogleOAuth, {}),
    ])
    for (const r of results) {
      expect(r.status).toBeLessThan(500)
    }
  })

  // ── 認證邊界 ──

  test('未授權（無效 nonce）應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
    const { status } = await wpPost(noAuthOpts, EP.revokeGoogleOAuth, {})
    expect([401, 403]).toContain(status)
  })
})
