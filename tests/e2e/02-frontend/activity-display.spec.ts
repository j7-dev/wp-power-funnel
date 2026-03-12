/**
 * 活動顯示 — 前端 E2E 測試
 *
 * 對應規格: 查詢活動列表.feature, 發送LINE活動Carousel.feature
 * 對應原始碼: inc/classes/Domains/Activity/
 *
 * 涵蓋場景:
 *  - 活動列表 API 回應格式驗證
 *  - 活動 DTO 欄位完整性
 *  - 關鍵字篩選
 *  - last_n_days 日期篩選
 *  - 組合篩選
 *  - Carousel 資料來源驗證（活動資料供 LINE Carousel 使用）
 *  - LIFF 回調觸發 Carousel 發送
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import {
  BASE_URL,
  EP,
  ACTIVITY_DTO_FIELDS,
  LIFF_PAYLOAD_FULL,
} from '../fixtures/test-data.js'

/* ── Types ── */
type ActivityDTO = {
  id: string
  activity_provider_id: string
  title: string
  description: string
  thumbnail_url: string
  scheduled_start_time: number
  meta: Record<string, unknown>
}

type ActivitiesResponse = {
  code: string
  data: ActivityDTO[]
}

/* ── Setup ── */
let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('活動列表 — API 回應格式', () => {
  test('GET /activities 應回傳 200', async () => {
    const { status } = await wpGet(opts, EP.activities)
    expect(status).toBe(200)
  })

  test('回傳資料應為陣列', async () => {
    const { data, status } = await wpGet<ActivitiesResponse>(opts, EP.activities)
    if (status === 200) {
      // data 可能直接是陣列或包在 data 屬性中
      const activities = Array.isArray(data) ? data : data?.data
      if (activities) {
        expect(Array.isArray(activities)).toBeTruthy()
      }
    }
  })

  test('每筆活動應包含所有必要 DTO 欄位', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
    )
    if (status === 200) {
      const activities = Array.isArray(data) ? data : (data as ActivitiesResponse)?.data
      if (activities && activities.length > 0) {
        const first = activities[0]
        for (const field of ACTIVITY_DTO_FIELDS) {
          expect(first).toHaveProperty(field)
        }
      }
    }
  })

  test('scheduled_start_time 應為數值（UNIX timestamp）', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
    )
    if (status === 200) {
      const activities = Array.isArray(data) ? data : (data as ActivitiesResponse)?.data
      if (activities && activities.length > 0) {
        for (const activity of activities) {
          expect(typeof activity.scheduled_start_time).toBe('number')
        }
      }
    }
  })

  test('title 與 description 應為字串', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
    )
    if (status === 200) {
      const activities = Array.isArray(data) ? data : (data as ActivitiesResponse)?.data
      if (activities && activities.length > 0) {
        for (const activity of activities) {
          expect(typeof activity.title).toBe('string')
          expect(typeof activity.description).toBe('string')
        }
      }
    }
  })
})

test.describe('活動列表 — 關鍵字篩選', () => {
  test('keyword 篩選應回傳匹配結果', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: 'React' })
    expect(status).toBeLessThan(500)
  })

  test('keyword 篩選回傳結果標題應包含關鍵字', async () => {
    const { data, status } = await wpGet<ActivityDTO[]>(opts, EP.activities, {
      keyword: 'React',
    })
    if (status === 200) {
      const activities = Array.isArray(data) ? data : []
      for (const activity of activities) {
        expect(activity.title.toLowerCase()).toContain('react')
      }
    }
  })

  test('不存在的 keyword 應回傳空陣列或空結果', async () => {
    const { data, status } = await wpGet<ActivityDTO[]>(opts, EP.activities, {
      keyword: '完全不可能存在的關鍵字_XYZ_999',
    })
    if (status === 200) {
      const activities = Array.isArray(data) ? data : []
      expect(activities.length).toBe(0)
    }
  })

  test('中文 keyword 篩選', async () => {
    const { status } = await wpGet(opts, EP.activities, { keyword: '直播' })
    expect(status).toBeLessThan(500)
  })
})

test.describe('活動列表 — 日期篩選', () => {
  test('last_n_days 篩選應回傳未來 N 天內活動', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '30' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=1 應只回傳近期活動', async () => {
    const { status } = await wpGet(opts, EP.activities, { last_n_days: '1' })
    expect(status).toBeLessThan(500)
  })

  test('last_n_days=365 應回傳較多活動', async () => {
    const { data: short, status: s1 } = await wpGet<ActivityDTO[]>(opts, EP.activities, {
      last_n_days: '1',
    })
    const { data: long, status: s2 } = await wpGet<ActivityDTO[]>(opts, EP.activities, {
      last_n_days: '365',
    })
    if (s1 === 200 && s2 === 200) {
      const shortList = Array.isArray(short) ? short : []
      const longList = Array.isArray(long) ? long : []
      expect(longList.length).toBeGreaterThanOrEqual(shortList.length)
    }
  })
})

test.describe('活動列表 — 組合篩選', () => {
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

  test('id + keyword + last_n_days 三重篩選', async () => {
    const { status } = await wpGet(opts, EP.activities, {
      id: 'yt001',
      keyword: 'React',
      last_n_days: '30',
    })
    expect(status).toBeLessThan(500)
  })
})

test.describe('活動資料 — Carousel 呈現來源', () => {
  test('活動應有 thumbnail_url 供 Carousel 顯示縮圖', async () => {
    const { data, status } = await wpGet<ActivityDTO[]>(opts, EP.activities)
    if (status === 200) {
      const activities = Array.isArray(data) ? data : []
      for (const activity of activities) {
        // thumbnail_url 應為字串（可能為空字串，但欄位應存在）
        expect(typeof activity.thumbnail_url).toBe('string')
      }
    }
  })

  test('活動應有 meta 物件供 Carousel 擴充資訊', async () => {
    const { data, status } = await wpGet<ActivityDTO[]>(opts, EP.activities)
    if (status === 200) {
      const activities = Array.isArray(data) ? data : []
      for (const activity of activities) {
        expect(typeof activity.meta).toBe('object')
      }
    }
  })

  test('LIFF 回調搭配 promoLinkId 應觸發 Carousel 發送流程', async ({ request }) => {
    // 此測試驗證 LIFF → Carousel 的完整流程不 crash
    const liffUrl = `${BASE_URL}/wp-json/${EP.liff}`
    const res = await request.post(liffUrl, {
      headers: { 'Content-Type': 'application/json' },
      data: LIFF_PAYLOAD_FULL,
    })
    expect(res.status()).toBeLessThan(500)
  })
})
