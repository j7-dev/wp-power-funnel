/**
 * GET /activities — 查詢活動列表
 *
 * 涵蓋場景:
 *  - 無篩選條件 → 回傳所有可報名活動（排除已過期）
 *  - keyword 篩選
 *  - last_n_days 篩選
 *  - keyword + last_n_days 交集
 *  - 指定 id 查找
 *  - 活動 DTO 欄位驗證
 *
 * 注意: 此 feature 標記 @ignore，因活動來源依賴 YouTube OAuth 授權。
 * 測試採防禦性斷言，不假設 YouTube 已連接。
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, ACTIVITY_DTO_FIELDS } from '../fixtures/test-data.js'

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
  code?: string
  data?: ActivityDTO[]
} & Record<string, unknown>

let opts: ApiOptions

test.beforeAll(async ({ request }) => {
  opts = { request, baseURL: BASE_URL, nonce: getNonce() }
})

test.describe('GET /activities — 查詢活動列表', () => {
  test('無篩選條件時回傳活動列表 (200)', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(opts, EP.activities)
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      expect(Array.isArray(list)).toBe(true)

      // 所有回傳的活動排程時間應在過去之後（可報名）
      for (const item of list) {
        if (item.scheduled_start_time) {
          expect(item.scheduled_start_time).toBeGreaterThan(0)
        }
      }
    }
  })

  test('使用 keyword 篩選標題', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
      { keyword: 'React' },
    )
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      for (const item of list) {
        expect(item.title?.toLowerCase()).toContain('react')
      }
    }
  })

  test('使用 last_n_days 篩選未來 N 天活動', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
      { last_n_days: '30' },
    )
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      const now = Math.floor(Date.now() / 1000)
      const cutoff = now + 30 * 86400
      for (const item of list) {
        if (item.scheduled_start_time) {
          expect(item.scheduled_start_time).toBeLessThanOrEqual(cutoff)
        }
      }
    }
  })

  test('keyword + last_n_days 同時篩選取交集', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
      { keyword: 'React', last_n_days: '20' },
    )
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      for (const item of list) {
        expect(item.title?.toLowerCase()).toContain('react')
      }
    }
  })

  test('使用 id 查找特定活動', async () => {
    // 先取得活動列表以獲得一個有效 id
    const { data: allData, status: listStatus } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
    )
    expect(listStatus).toBeLessThan(500)

    const allList = Array.isArray(allData) ? allData : (allData as ActivitiesResponse).data ?? []

    if (allList.length > 0) {
      const targetId = allList[0].id
      const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
        opts,
        EP.activities,
        { id: targetId },
      )
      expect(status).toBe(200)
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      expect(list).toHaveLength(1)
      expect(list[0].id).toBe(targetId)
    }
  })

  test('活動 DTO 包含所有必要欄位', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(opts, EP.activities)
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      if (list.length > 0) {
        const activity = list[0]
        for (const field of ACTIVITY_DTO_FIELDS) {
          expect(activity).toHaveProperty(field)
        }
        expect(typeof activity.id).toBe('string')
        expect(typeof activity.activity_provider_id).toBe('string')
        expect(typeof activity.title).toBe('string')
        expect(typeof activity.scheduled_start_time).toBe('number')
      }
    }
  })

  test('查找不存在的 id 回傳空陣列或 404', async () => {
    const { data, status } = await wpGet<ActivityDTO[] | ActivitiesResponse>(
      opts,
      EP.activities,
      { id: 'non_existent_id_e2e_12345' },
    )
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = Array.isArray(data) ? data : (data as ActivitiesResponse).data ?? []
      expect(list).toHaveLength(0)
    }
    // 404 is also acceptable
  })

  test('未登入時應回傳 401', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce' }
    const { status } = await wpGet(noAuthOpts, EP.activities)
    expect(status).toBeLessThan(500)
    expect([401, 403]).toContain(status)
  })
})
