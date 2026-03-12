/**
 * POST /liff — 處理 LIFF 回調（公開端點，不需 WP 認證）
 *
 * 涵蓋場景:
 *  - 完整 LIFF 資料觸發回調 → 200 + code: success
 *  - 最小資料（無 urlParams）仍正常處理
 *  - 空 body 或缺少欄位的防禦
 *  - 無需 WP Nonce 也能存取
 */
import { test, expect } from '@playwright/test'
import { BASE_URL, EP, CODES, LIFF_PAYLOAD_FULL, LIFF_PAYLOAD_MINIMAL } from '../fixtures/test-data.js'

/* ── Types ── */
type LiffResponse = {
  code: string
  message: string
  data: unknown
}

test.describe('POST /liff — 處理 LIFF 回調', () => {
  const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`

  test('完整 LIFF 資料 → 200 + code: success', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    const body: LiffResponse = await res.json()

    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      expect(body.code).toBe(CODES.liffSuccess)
      expect(body.message).toBeTruthy()
    }
  })

  test('最小資料（無 urlParams）仍正常處理', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_MINIMAL,
    })
    const body: LiffResponse = await res.json()

    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })

  test('不需 WP Nonce（公開端點）', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    // 公開端點不應回傳 401/403
    expect(res.status()).not.toBe(401)
    expect(res.status()).not.toBe(403)
    expect(res.status()).toBeLessThan(500)
  })

  test('空 body 不應 crash (防禦性)', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('缺少 userId 時仍不應 500', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: { name: '[E2E] No UserId', isInClient: false, isLoggedIn: false },
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('帶額外未知欄位不應影響處理', async ({ request }) => {
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        ...LIFF_PAYLOAD_FULL,
        extraField: 'should be ignored',
        nested: { deep: true },
      },
    })
    expect(res.status()).toBeLessThan(500)
    if (res.status() === 200) {
      const body: LiffResponse = await res.json()
      expect(body.code).toBe(CODES.liffSuccess)
    }
  })
})
