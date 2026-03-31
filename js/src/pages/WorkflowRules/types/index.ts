// @ts-ignore
import { TPostStatus } from 'antd-toolkit/wp'

/**
 * 節點類型列舉
 * 對應後端 ENodeType enum
 */
export const NODE_TYPE = {
	SEND_MESSAGE: 'send_message',
	ACTION: 'action',
} as const

export type TNodeType = (typeof NODE_TYPE)[keyof typeof NODE_TYPE]

/**
 * 觸發點型別
 * 改為 string，由後端 API 動態取得，不再於前端硬編碼
 */
export type TTriggerPoint = string

// region NodeDefinition API 型別

/** 表單欄位類型 */
export type TFormFieldType =
	| 'text'
	| 'number'
	| 'select'
	| 'textarea'
	| 'template_editor'
	| 'switch'
	| 'date'
	| 'json'

/** select 類型選項 */
export type TSelectOption = {
	value: string
	label: string
}

/** 驗證規則 */
export type TValidationRule = {
	rule: string
	value: unknown
	message: string
}

/** 條件顯示規則 */
export type TDependsOn = {
	field: string
	operator: 'equals' | 'not_equals' | 'empty' | 'not_empty'
	value: unknown
}

/** 表單欄位定義（對應後端 FormFieldDTO） */
export type TFormField = {
	/** 欄位 key，對應 NodeDTO.args 的 key */
	name: string
	/** 顯示標籤 */
	label: string
	/** 欄位類型 */
	type: TFormFieldType
	/** 是否必填 */
	required: boolean
	/** 預設值 */
	default_value?: unknown
	/** placeholder 文字 */
	placeholder?: string
	/** 欄位說明（tooltip） */
	description?: string
	/** select 類型的選項 */
	options?: TSelectOption[]
	/** 額外驗證規則 */
	validation?: TValidationRule[]
	/** 欄位排序 */
	sort: number
	/** 條件顯示規則 */
	depends_on?: TDependsOn[]
}

/** 節點定義（對應後端 BaseNodeDefinition::to_array()） */
export type TNodeDefinition = {
	/** 節點唯一識別碼 */
	id: string
	/** 節點名稱 */
	name: string
	/** 節點描述 */
	description: string
	/** 節點圖示 SVG URL */
	icon: string
	/** 節點分類 */
	type: TNodeType
	/** 節點表單欄位定義 */
	form_fields: TFormField[]
}

/** 節點定義列表 API 回應型別 */
export type TNodeDefinitionsResponse = {
	code: string
	message: string
	data: TNodeDefinition[]
}

// endregion NodeDefinition API 型別

/**
 * 後端 NodeDTO 結構
 * 對應 PHP Contracts\DTOs\NodeDTO
 */
export type TNodeDTO = {
	/** 節點模組名稱 */
	node_module: string
	/** 節點類型 */
	node_type: TNodeType
	/** 排序序號 */
	sort: number
	/** 節點特定參數 */
	args: Record<string, unknown>
	/** 條件匹配回調 */
	match_callback?: string
}

/**
 * WorkflowRule CPT 記錄型別
 * 對應後端 pf_workflow_rule post type
 */
export type TWorkflowRuleRecord = {
	/** 文章 ID */
	id: string
	/** 規則名稱 */
	name: string
	/** 文章狀態 */
	status: TPostStatus
	/** 建立日期 */
	date_created: string
	/** 修改日期 */
	date_modified: string
	/** 作者 ID */
	author: number
	/** 觸發點 hook name */
	trigger_point: TTriggerPoint
	/** 節點 DTO 陣列 */
	nodes: TNodeDTO[]
	/** 文章層級 */
	depth: number
	/** 別名 */
	slug: string
}
