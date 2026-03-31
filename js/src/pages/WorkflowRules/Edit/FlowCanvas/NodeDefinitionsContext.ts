import { createContext } from 'react'
import type { TNodeDefinition } from '@/pages/WorkflowRules/types'

/**
 * NodeDefinitions Context
 * 提供節點定義對照表給 FlowCanvas 內的子元件使用（如 ActionNode）
 */
export const NodeDefinitionsContext = createContext<
	Record<string, TNodeDefinition>
>({})
