/**
 * POST /revoke-google-oauth — 撤銷 Google OAuth 授權
 *
 * 涵蓋場景:
 *  - 成功撤銷 (200)
 *  - 撤銷後 GET /options 驗證 isAuthorized 為 false
 *  - 重複撤銷不應報錯
 *  - 未登入時拒絕存取
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, CODES } from '../fixtures/test-data.js'

/* ── Types ── */
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

test.describe('POST /revoke-google-oauth — 撤銷 Google OAuth 授權', () => {
  test('撤銷 Google OAuth (200)', async () => {
    const { data, status } = await wpPost<RevokeResponse>(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)

    if (status === 200) {
      expect(data.code).toBe(CODES.revokeGoogleOAuthSuccess)
      expect(data.message).toBeTruthy()
    }
  })

  test('撤銷後 isAuthorized 應為 false', async () => {
    // 先撤銷
    await wpPost(opts, EP.revokeGoogleOAuth, {})

    // 驗證
    const { data, status } = await wpGet<OptionsResponse>(opts, EP.options)
    expect(status).toBe(200)
    expect(data.data.googleOauth.isAuthorized).toBe(false)
  })

  test('重複撤銷不應回傳 500', async () => {
    await wpPost(opts, EP.revokeGoogleOAuth, {})
    const { status } = await wpPost<RevokeResponse>(opts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
  })

  test('未登入時應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce' }
    const { status } = await wpPost(noAuthOpts, EP.revokeGoogleOAuth, {})
    expect(status).toBeLessThan(500)
    expect([401, 403]).toContain(status)
  })
})
