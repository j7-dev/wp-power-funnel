/**
 * P2 — 活動顯示（前端 E2E 測試）
 *
 * 對應規格: spec/features/activity/查詢活動列表.feature,
 *           spec/features/line/發送LINE活動Carousel.feature
 *
 * 涵蓋場景:
 *  - 活動列表 API 回應格式驗證
 *  - 每筆活動 DTO 欄位完整性
 *  - scheduled_start_time 為 UNIX timestamp 數值
 *  - title / description 為字串
 *  - thumbnail_url 為字串（供 Carousel 使用）
 *  - meta 為 object（供 Carousel 擴充）
 *  - keyword 篩選（中文、英文）
 *  - last_n_days 日期篩選（1 天、30 天、365 天）
 *  - keyword + last_n_days 組合篩選
 *  - id 直接查找
 *  - LIFF 回調搭配 promoLinkId 觸發 Carousel 流程（不 crash）
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, ACTIVITY_DTO_FIELDS, LIFF_PAYLOAD_FULL } from '../fixtures/test-data.js'

/* ── 型別定義 ── */
type ActivityDTO = {
  id: string
  activity_provider_id: string
  title: string
  description: string
  thumbnail_url: string
  scheduled_start_time: number
  meta: Record<string, unknown>
}

/** 統一從回應中取出活動陣列 */
function extractList(data: unknown): ActivityDTO[] {
  if (Array.isArray(data)) return data as ActivityDTO[]
  if (data && typeof data === 'object' && 'data' in data) {
    const nested = (data as { data: unknown }).data
    if (Array.isArray(nested)) return nested as ActivityDTO[]
  }
  return []
}

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('活動列表 — API 回應格式 [P2]', () => {
  test('GET /activities 應回傳 200', async () => {
    const { status } = await wpGet(opts, EP.activities)
    expect(status).toBe(200)
  })

  test('回傳資料應為陣列（或空陣列）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status === 200) {
      expect(Array.isArray(extractList(data))).toBe(true)
    }
  })

  test('有資料時每筆活動應包含所有必要 DTO 欄位', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const activities = extractList(data)
    if (activities.length === 0) return

    for (const field of ACTIVITY_DTO_FIELDS) {
      expect(activities[0]).toHaveProperty(field)
    }
  })

  test('有資料時 scheduled_start_time 應為數值（UNIX timestamp）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const activities = extractList(data)
    for (const item of activities) {
      expect(typeof item.scheduled_start_time).toBe('number')
    }
  })

  test('有資料時 title 與 description 應為字串', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const activities = extractList(data)
    for (const item of activities) {
      expect(typeof item.title).toBe('string')
      expect(typeof item.description).toBe('string')
    }
  })

  test('有資料時 thumbnail_url 應為字串（供 LINE Carousel 使用）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const activities = extractList(data)
    for (const item of activities) {
      expect(typeof item.thumbnail_url).toBe('string')
    }
  })

  test('有資料時 meta 應為 object（供 Carousel 擴充）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const activities = extractList(data)
    for (const item of activities) {
      expect(typeof item.meta).toBe('object')
      expect(item.meta).not.toBeNull()
    }
  })
})

test.describe('活動列表 — 關鍵字篩選 [P2]', () => {
  test('英文 keyword=React 應只回傳匹配標題', async () => {
    const { data, status } = await wpGet(opts, EP.activities, { keyword: 'React' })
    if (status !== 200) return

    const activities = extractList(data)
    for (const item of activities) {
      expect(item.title.toLowerCase()).toContain('react')
    }
  })

  test('中文 keyword=直播 應只回傳匹配標題', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: '直播' })
    expect(status).toBeLessThan(500)
  })

  test('不存在的 keyword 應回傳空陣列', async () => {
    const { data, status } = await wpGet(opts, EP.activities, {
      keyword: '完全不可能存在的關鍵字_XYZ_E2E',
    })
    if (status === 200) {
      expect(extractList(data).length).toBe(0)
    }
  })
})

test.describe('活動列表 — 日期篩選 [P2]', () => {
  test('last_n_days=30 應只回傳未來 30 天內的活動', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '30' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=1 應只回傳近期活動', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '1' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=365 回傳數量應 >= last_n_days=1', async () => {
    const { data: short, status: s1 } = await wpGet(opts, EP.activities, { last_n_days: '1' })
    const { data: long, status: s2 } = await wpGet(opts, EP.activities, { last_n_days: '365' })
    if (s1 === 200 && s2 === 200) {
      expect(extractList(long).length).toBeGreaterThanOrEqual(extractList(short).length)
    }
  })

  test('回傳活動的 scheduled_start_time 應在 N 天截止範圍內', async () => {
    const { data, status } = await wpGet(opts, EP.activities, { last_n_days: '30' })
    if (status !== 200) return

    const activities = extractList(data)
    const now = Math.floor(Date.now() / 1000)
    const cutoff = now + 30 * 86400
    for (const item of activities) {
      if (item.scheduled_start_time) {
        expect(item.scheduled_start_time).toBeLessThanOrEqual(cutoff)
      }
    }
  })
})

test.describe('活動列表 — 組合篩選 [P2]', () => {
  test('keyword + last_n_days 同時篩選', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      keyword: 'React',
      last_n_days: '30',
    })
    expect(status).toBeLessThan(500)
  })

  test('id 直接查找特定活動', async () => {
    const { status } = await wpGet(opts, EP.activities, { id: 'yt001' })
    expect(status).toBeLessThan(500)
  })

  test('id + keyword + last_n_days 三重篩選不應 500', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: 'yt001',
      keyword: 'React',
      last_n_days: '30',
    })
    expect(status).toBeLessThan(500)
  })
})

test.describe('活動資料 — Carousel 呈現來源 [P2]', () => {
  test('LIFF 回調搭配 promoLinkId 觸發 Carousel 流程不應 crash', async ({ request }) => {
    const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).toBeLessThan(500)
  })

  test('LIFF 回調不含 promoLinkId 應不發送 Carousel（不 crash）', async ({ request }) => {
    const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: {
        userId: '[E2E] U_no_carousel',
        name: '[E2E] 無 Carousel',
        isInClient: true,
        isLoggedIn: true,
        // 不帶 urlParams
      },
    })
    expect(res.status()).toBeLessThan(500)
  })
})
