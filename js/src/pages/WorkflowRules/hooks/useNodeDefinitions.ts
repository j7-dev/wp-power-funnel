import { useCustom, useApiUrl } from '@refinedev/core'
import type {
	TNodeDefinition,
	TNodeDefinitionsResponse,
} from '@/pages/WorkflowRules/types'

/**
 * 取得所有已註冊的節點定義
 *
 * 呼叫 GET /wp-json/power-funnel/node-definitions，
 * 回傳 definitions（完整列表）、definitionsMap（id => 定義）與 isLoading 狀態。
 */
const useNodeDefinitions = () => {
	const apiUrl = useApiUrl('power-funnel')
	const result = useCustom<TNodeDefinitionsResponse>({
		url: `${apiUrl}/node-definitions`,
		method: 'get',
		queryOptions: {
			queryKey: ['get_node_definitions'],
			staleTime: 5 * 60 * 1000,
		},
	})

	const definitions: TNodeDefinition[] = result.data?.data?.data ?? []

	const definitionsMap: Record<string, TNodeDefinition> = definitions.reduce(
		(acc, def) => {
			acc[def.id] = def
			return acc
		},
		{} as Record<string, TNodeDefinition>,
	)

	return {
		definitions,
		definitionsMap,
		isLoading: result.isLoading,
	}
}

export default useNodeDefinitions
