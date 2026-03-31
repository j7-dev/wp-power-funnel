import { memo, useCallback } from 'react'
import { Drawer, Form, Button, Space, Popconfirm, Alert } from 'antd'
import { DeleteOutlined, SaveOutlined } from '@ant-design/icons'
import type { TFlowNode, TFlowNodeData } from '../FlowCanvas/types'
import type { TNodeDefinition } from '@/pages/WorkflowRules/types'
import DynamicNodeForm from './forms/DynamicNodeForm'

type TNodeDrawerProps = {
	/** 是否開啟 */
	isOpen: boolean
	/** 當前選取的節點 */
	node: TFlowNode | null
	/** 節點 data（型別縮窄後） */
	nodeData: TFlowNodeData | undefined
	/** 節點定義對照表（id => 定義） */
	nodeDefinitions: Record<string, TNodeDefinition>
	/** 關閉 Drawer */
	onClose: () => void
	/** 更新節點 data */
	onUpdate: (nodeId: string, data: Partial<TFlowNodeData>) => void
	/** 刪除節點 */
	onDelete: (nodeId: string) => void
}

/**
 * 節點設定抽屜元件
 * 根據 API 回傳的 NodeDefinition form_fields 動態渲染設定表單
 */
const NodeDrawer = memo(
	({
		isOpen,
		node,
		nodeData,
		nodeDefinitions,
		onClose,
		onUpdate,
		onDelete,
	}: TNodeDrawerProps) => {
		const [form] = Form.useForm()

		/** 儲存節點設定 */
		const handleSave = useCallback(() => {
			if (!node || !nodeData) return
			const values = form.getFieldsValue()
			const args = values.args ?? {}
			onUpdate(node.id, { args })
			onClose()
		}, [node, nodeData, form, onUpdate, onClose])

		/** 刪除節點 */
		const handleDelete = useCallback(() => {
			if (!node) return
			onDelete(node.id)
		}, [node, onDelete])

		if (!node || !nodeData) return null

		const definition = nodeDefinitions[nodeData.nodeModule]
		const label = definition?.name ?? nodeData.nodeModule

		/** 根據節點定義渲染動態表單 */
		const renderForm = () => {
			if (!definition) {
				return (
					<Alert
						message={`無法辨識的節點類型：${nodeData.nodeModule}`}
						type="warning"
						showIcon
					/>
				)
			}
			return (
				<DynamicNodeForm
					formFields={definition.form_fields}
					args={nodeData.args}
				/>
			)
		}

		return (
			<Drawer
				open={isOpen}
				onClose={onClose}
				title={`設定：${label}`}
				width={400}
				extra={
					<Space>
						<Popconfirm
							title="確認刪除此節點？"
							onConfirm={handleDelete}
							okText="刪除"
							cancelText="取消"
							okButtonProps={{ danger: true }}
						>
							<Button
								danger
								icon={<DeleteOutlined />}
								size="small"
							>
								刪除
							</Button>
						</Popconfirm>
						<Button
							type="primary"
							icon={<SaveOutlined />}
							size="small"
							onClick={handleSave}
						>
							套用
						</Button>
					</Space>
				}
			>
				<Form form={form} layout="vertical">
					{renderForm()}
				</Form>
			</Drawer>
		)
	},
)

NodeDrawer.displayName = 'NodeDrawer'

export default NodeDrawer
