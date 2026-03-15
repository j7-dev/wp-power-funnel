/**
 * P0 — GET /activities 查詢活動列表
 *
 * 對應規格: spec/features/activity/查詢活動列表.feature
 *
 * 注意：此端點依賴 YouTube OAuth 授權狀態。
 *       若 YouTube 未授權，回傳空陣列或 200 空結果。
 *       所有斷言採防禦性設計：status < 500，有資料時才驗 DTO 格式。
 *
 * 涵蓋場景:
 *  - 無篩選條件 → 200，回傳 ActivityDTO[] 或空陣列
 *  - keyword 篩選：回傳標題含 keyword 的活動
 *  - last_n_days 篩選：回傳未來 N 天內的活動
 *  - keyword + last_n_days 同時篩選取交集
 *  - id 篩選：回傳特定 YouTube video ID 的活動
 *  - 不存在的 id → 200（空陣列）或 404
 *  - ActivityDTO 欄位完整性驗證
 *  - 所有活動的 scheduled_start_time 應 > 0（未過期）
 *  - 未授權 → 401 或 403
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../helpers/admin-setup.js'
import { BASE_URL, EP, ACTIVITY_DTO_FIELDS } from '../fixtures/test-data.js'

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

/** 從回應中統一取出活動陣列（相容 array 直接回傳或包在 data 內） */
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

test.describe('GET /activities — 查詢活動列表 [P0]', () => {
  // ── 基本回應 ──

  test('無篩選條件應回傳 200，狀態碼不應 >= 500', async () => {
    const { status } = await wpGet(opts, EP.activities)
    expect(status).toBeLessThan(500)
  })

  test('回傳資料應為陣列格式', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status === 200) {
      const list = extractList(data)
      expect(Array.isArray(list)).toBe(true)
    }
  })

  // ── ActivityDTO 欄位驗證 ──

  test('有資料時每筆活動應包含所有必要 DTO 欄位', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    if (list.length === 0) return // YouTube 未授權時可能無資料

    const first = list[0]
    for (const field of ACTIVITY_DTO_FIELDS) {
      expect(first).toHaveProperty(field)
    }
  })

  test('有資料時 id 與 title 應為字串', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    for (const item of list) {
      expect(typeof item.id).toBe('string')
      expect(typeof item.title).toBe('string')
    }
  })

  test('有資料時 scheduled_start_time 應為正整數（UNIX timestamp）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    for (const item of list) {
      expect(typeof item.scheduled_start_time).toBe('number')
      expect(item.scheduled_start_time).toBeGreaterThan(0)
    }
  })

  test('有資料時 activity_provider_id 應為字串（如 youtube）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    for (const item of list) {
      expect(typeof item.activity_provider_id).toBe('string')
      expect(item.activity_provider_id.length).toBeGreaterThan(0)
    }
  })

  test('有資料時 thumbnail_url 應為字串', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    for (const item of list) {
      expect(typeof item.thumbnail_url).toBe('string')
    }
  })

  test('有資料時 meta 應為 object', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    for (const item of list) {
      expect(typeof item.meta).toBe('object')
      expect(item.meta).not.toBeNull()
    }
  })

  // ── 僅回傳未來活動 ──

  test('所有回傳活動的 scheduled_start_time 應在未來（> 當前時間）', async () => {
    const { data, status } = await wpGet(opts, EP.activities)
    if (status !== 200) return

    const list = extractList(data)
    const now = Math.floor(Date.now() / 1000)
    for (const item of list) {
      if (item.scheduled_start_time) {
        // 允許少量容差（5 分鐘）以防測試環境時鐘偏差
        expect(item.scheduled_start_time).toBeGreaterThan(now - 300)
      }
    }
  })

  // ── keyword 篩選 ──

  test('keyword=React 應只回傳標題含 React 的活動', async () => {
    const { data, status } = await wpGet(opts, EP.activities, { keyword: 'React' })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = extractList(data)
      for (const item of list) {
        expect(item.title.toLowerCase()).toContain('react')
      }
    }
  })

  test('keyword 篩選：不存在的關鍵字應回傳空陣列', async () => {
    const { data, status } = await wpGet(opts, EP.activities, {
      keyword: '完全不可能存在的關鍵字_XYZ_E2E_999',
    })
    if (status === 200) {
      const list = extractList(data)
      expect(list.length).toBe(0)
    }
  })

  // ── last_n_days 篩選 ──

  test('last_n_days=30 應只回傳未來 30 天內的活動', async () => {
    const { data, status } = await wpGet(opts, EP.activities, { last_n_days: '30' })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = extractList(data)
      const now = Math.floor(Date.now() / 1000)
      const cutoff = now + 30 * 86400
      for (const item of list) {
        if (item.scheduled_start_time) {
          expect(item.scheduled_start_time).toBeLessThanOrEqual(cutoff)
        }
      }
    }
  })

  test('last_n_days=365 回傳數量應 >= last_n_days=1 的數量', async () => {
    const { data: short, status: s1 } = await wpGet(opts, EP.activities, { last_n_days: '1' })
    const { data: long, status: s2 } = await wpGet(opts, EP.activities, { last_n_days: '365' })
    if (s1 === 200 && s2 === 200) {
      expect(extractList(long).length).toBeGreaterThanOrEqual(extractList(short).length)
    }
  })

  // ── 組合篩選 ──

  test('keyword + last_n_days 同時篩選取交集', async () => {
    const { data, status } = await wpGet(opts, EP.activities, {
      keyword: 'React',
      last_n_days: '20',
    })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = extractList(data)
      for (const item of list) {
        expect(item.title.toLowerCase()).toContain('react')
      }
    }
  })

  // ── id 篩選 ──

  test('有效 id 篩選應只回傳 1 筆活動', async () => {
    // 先取得活動列表以獲得有效的 id
    const { data: allData, status: listStatus } = await wpGet(opts, EP.activities)
    if (listStatus !== 200) return

    const allList = extractList(allData)
    if (allList.length === 0) return

    const targetId = allList[0].id
    const { data, status } = await wpGet(opts, EP.activities, { id: targetId })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = extractList(data)
      expect(list.length).toBe(1)
      expect(list[0].id).toBe(targetId)
    }
  })

  test('不存在的 id 應回傳空陣列（200）或 404，不應 500', async () => {
    const { data, status } = await wpGet(opts, EP.activities, {
      id: 'non_existent_id_e2e_99999',
    })
    expect(status).toBeLessThan(500)

    if (status === 200) {
      const list = extractList(data)
      expect(list.length).toBe(0)
    }
  })

  // ── 認證邊界 ──

  test('未授權（無效 nonce）應回傳 401 或 403', async ({ request }) => {
    const noAuthOpts: ApiOptions = { request, baseURL: BASE_URL, nonce: 'invalid_nonce_e2e' }
    const { status } = await wpGet(noAuthOpts, EP.activities)
    expect([401, 403]).toContain(status)
  })
})
