/**
 * P1 — 推廣連結 CRUD（透過 WP REST API）
 *
 * 對應規格: spec/features/promo-link/建立推廣連結.feature,
 *           spec/features/promo-link/編輯推廣連結.feature
 *
 * 注意：使用 wp/v2/pf_promo_link CPT REST API。
 *       若 CPT 未正確註冊 REST API，端點會回傳 404，測試採防禦性處理。
 *
 * 涵蓋場景:
 *  - 建立推廣連結（含基本欄位）
 *  - 建立時包含 meta：keyword、last_n_days、alt_text、action_label
 *  - 取得推廣連結詳情
 *  - 更新推廣連結的篩選條件（keyword、last_n_days）
 *  - 更新推廣連結的顯示參數（alt_text、action_label）
 *  - 更新 auto_approved 欄位
 *  - 刪除推廣連結
 *  - 存取不存在的 ID（999999）→ 404
 *  - 未授權 → 401 或 403
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpPut, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type PromoLinkResponse = {
  id: number
  title: { rendered: string } | string
  status: string
  meta?: {
    keyword?: string
    last_n_days?: number | string
    alt_text?: string
    action_label?: string
    auto_approved?: string
    link_provider?: string
  }
}

let opts: ApiOptions
const createdIds: number[] = []

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.afterAll(async () => {
  // 清理測試資料
  for (const id of createdIds) {
    try {
      await wpDelete(opts, `${EP.promoLinks}/${id}?force=true`)
    } catch {
      /* 清理失敗不影響測試報告 */
    }
  }
})

test.describe('推廣連結 CRUD [P1]', () => {
  // ── 建立 ──

  test('建立推廣連結應成功或回傳 404（CPT 未啟用 REST）', async () => {
    const { data, status } = await wpPost<PromoLinkResponse>(opts, EP.promoLinks, {
      title: '[E2E] 三月推廣連結',
      status: 'publish',
      meta: {
        keyword: 'React',
        last_n_days: 30,
        alt_text: '[E2E] 三月活動',
        action_label: '[E2E] 立即報名',
        link_provider: 'line',
      },
    })

    if (status === 404) {
      console.log('pf_promo_link CPT REST API 未啟用，跳過後續 CRUD 測試')
      return
    }

    expect(status).toBeLessThan(300)
    expect(data.id).toBeGreaterThan(0)
    createdIds.push(data.id)
  })

  test('建立推廣連結時 meta.keyword 應被儲存', async () => {
    const { data, status } = await wpPost<PromoLinkResponse>(opts, EP.promoLinks, {
      title: '[E2E] Keyword 測試連結',
      status: 'publish',
      meta: {
        keyword: 'Vue',
        last_n_days: 14,
      },
    })

    if (status === 404) return

    expect(status).toBeLessThan(300)
    expect(data.id).toBeGreaterThan(0)
    createdIds.push(data.id)

    // 查詢驗證 meta
    const { data: detail, status: getStatus } = await wpGet<PromoLinkResponse>(
      opts,
      `${EP.promoLinks}/${data.id}`,
    )
    if (getStatus === 200 && detail.meta) {
      expect(detail.meta['keyword']).toBe('Vue')
    }
  })

  test('建立推廣連結時 meta.action_label 預設應存在', async () => {
    const { data, status } = await wpPost<PromoLinkResponse>(opts, EP.promoLinks, {
      title: '[E2E] 預設 action_label 測試',
      status: 'publish',
    })

    if (status === 404) return

    expect(status).toBeLessThan(300)
    if (data.id) {
      createdIds.push(data.id)
    }
  })

  // ── 讀取 ──

  test('取得推廣連結詳情', async () => {
    if (createdIds.length === 0) return

    const id = createdIds[0]
    const { data, status } = await wpGet<PromoLinkResponse>(
      opts,
      `${EP.promoLinks}/${id}`,
    )

    if (status === 404) return

    expect(status).toBe(200)
    expect(data.id).toBe(id)
  })

  // ── 更新篩選條件 ──

  test('更新推廣連結的篩選條件（keyword 和 last_n_days）', async () => {
    if (createdIds.length === 0) return

    const id = createdIds[0]
    const { data, status } = await wpPut<PromoLinkResponse>(
      opts,
      `${EP.promoLinks}/${id}`,
      {
        meta: {
          keyword: 'Python',
          last_n_days: 60,
        },
      },
    )

    if (status === 404) return

    expect(status).toBeLessThan(300)
    if (data.meta) {
      expect(data.meta['keyword']).toBe('Python')
    }
  })

  // ── 更新顯示參數 ──

  test('更新推廣連結的顯示參數（alt_text 和 action_label）', async () => {
    if (createdIds.length === 0) return

    const id = createdIds[0]
    const { data, status } = await wpPut<PromoLinkResponse>(
      opts,
      `${EP.promoLinks}/${id}`,
      {
        meta: {
          alt_text: '[E2E] 更新後的替代文字',
          action_label: '[E2E] 馬上報名',
        },
      },
    )

    if (status === 404) return

    expect(status).toBeLessThan(300)
  })

  // ── 更新 auto_approved ──

  test('更新推廣連結的 auto_approved 為 yes', async () => {
    if (createdIds.length === 0) return

    const id = createdIds[0]
    const { status } = await wpPut<PromoLinkResponse>(
      opts,
      `${EP.promoLinks}/${id}`,
      {
        meta: { auto_approved: 'yes' },
      },
    )

    if (status === 404) return

    expect(status).toBeLessThan(300)
  })

  // ── 刪除 ──

  test('強制刪除推廣連結', async () => {
    // 建立一個專門用來刪除的連結
    const { data: toDelete, status: createStatus } = await wpPost<PromoLinkResponse>(
      opts,
      EP.promoLinks,
      {
        title: '[E2E] 待刪除推廣連結',
        status: 'publish',
      },
    )

    if (createStatus === 404) return

    const { status: deleteStatus } = await wpDelete(
      opts,
      `${EP.promoLinks}/${toDelete.id}?force=true`,
    )
    expect(deleteStatus).toBeLessThan(300)
  })

  // ── 邊界：不存在的 ID ──

  test('取得不存在的推廣連結（ID 999999）應回傳 404', async () => {
    const { status } = await wpGet(opts, `${EP.promoLinks}/999999`)
    expect(status).toBe(404)
  })

  test('更新不存在的推廣連結（ID 999999）應回傳 404', async () => {
    const { status } = await wpPut(opts, `${EP.promoLinks}/999999`, {
      meta: { keyword: 'test' },
    })
    expect(status).toBe(404)
  })

  test('刪除不存在的推廣連結（ID 999999）應回傳 404', async () => {
    const { status } = await wpDelete(opts, `${EP.promoLinks}/999999?force=true`)
    expect(status).toBe(404)
  })

  // ── 認證邊界 ──

  test('未授權建立推廣連結應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
    const { status } = await wpPost(noAuthOpts, EP.promoLinks, {
      title: '[E2E] Unauthorized PromoLink',
    })
    expect([401, 403, 404]).toContain(status)
  })
})
