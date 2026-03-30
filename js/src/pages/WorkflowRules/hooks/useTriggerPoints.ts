import { useCustom, useApiUrl } from '@refinedev/core'

/** 觸發條件項目型別（對應後端 TriggerPointDTO） */
export type TTriggerPointItem = {
	hook: string
	name: string
}

/** API 回應型別 */
type TTriggerPointsResponse = {
	code: string
	message: string
	data: TTriggerPointItem[]
}

/** Select options 型別 */
export type TTriggerPointOption = {
	label: string
	value: string
}

/**
 * 取得所有已註冊觸發條件的 hook
 *
 * 呼叫 GET /wp-json/power-funnel/trigger-points，
 * 回傳 options（供 Select 使用）、labelMap（hook => 顯示名稱）與 isLoading 狀態。
 */
const useTriggerPoints = () => {
	const apiUrl = useApiUrl('power-funnel')
	const result = useCustom<TTriggerPointsResponse>({
		url: `${apiUrl}/trigger-points`,
		method: 'get',
		queryOptions: {
			queryKey: ['get_trigger_points'],
			staleTime: 5 * 60 * 1000,
		},
	})

	const items: TTriggerPointItem[] = result.data?.data?.data ?? []

	const options: TTriggerPointOption[] = items.map((item) => ({
		label: item.name,
		value: item.hook,
	}))

	const labelMap: Record<string, string> = items.reduce(
		(acc, item) => {
			acc[item.hook] = item.name
			return acc
		},
		{} as Record<string, string>,
	)

	return {
		options,
		labelMap,
		isLoading: result.isLoading,
	}
}

export default useTriggerPoints
