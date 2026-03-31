import { memo, useContext } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { TFlowNodeData } from '../types'
import { NODE_TYPE } from '@/pages/WorkflowRules/types'
import { NodeDefinitionsContext } from '../NodeDefinitionsContext'
import './styles.css'

type TProps = NodeProps & {
	data: TFlowNodeData
}

/**
 * 動作節點元件
 * 根據 API 回傳的 NodeDefinition 顯示 SVG 圖示和標籤
 * 同時具有頂部 target Handle 和底部 source Handle
 */
const ActionNode = memo((props: TProps) => {
	const { data } = props
	const nodeDefinitions = useContext(NodeDefinitionsContext)
	const definition = nodeDefinitions[data.nodeModule]
	const label = definition?.name ?? data.label
	const iconUrl = definition?.icon
	const isSendMessage =
		(definition?.type ?? data.nodeType) === NODE_TYPE.SEND_MESSAGE
	const colorClass = isSendMessage
		? 'pf-flow-node__icon--blue'
		: 'pf-flow-node__icon--orange'

	return (
		<div className="pf-flow-node pf-flow-node--action">
			<Handle
				type="target"
				position={Position.Top}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
			<div className="pf-flow-node__header">
				<span className={`pf-flow-node__icon ${colorClass}`}>
					{iconUrl ? (
						<img
							src={iconUrl}
							alt={label}
							className="w-4 h-4"
							style={{ width: 16, height: 16 }}
						/>
					) : (
						<span>?</span>
					)}
				</span>
				<span className="pf-flow-node__title">{label}</span>
			</div>
			<Handle
				type="source"
				position={Position.Bottom}
				style={{ opacity: 0, pointerEvents: 'none' }}
			/>
		</div>
	)
})

ActionNode.displayName = 'ActionNode'

export default ActionNode
