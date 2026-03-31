import { memo, useMemo } from 'react'
import {
	Form,
	Input,
	InputNumber,
	Select,
	Switch,
	DatePicker,
	Tooltip,
} from 'antd'
import { QuestionCircleOutlined } from '@ant-design/icons'
import type { TFormField, TDependsOn } from '@/pages/WorkflowRules/types'
import type { Rule } from 'antd/es/form'

const { Item } = Form
const { TextArea } = Input

type TDynamicNodeFormProps = {
	/** 表單欄位定義（來自 API） */
	formFields: TFormField[]
	/** 節點目前的參數值 */
	args: Record<string, unknown>
}

/** 將 API 驗證規則轉為 Ant Design Form rules */
const toAntdRules = (field: TFormField): Rule[] => {
	const rules: Rule[] = []

	if (field.required) {
		rules.push({
			required: true,
			message: `${field.label} 為必填欄位`,
		})
	}

	if (field.validation) {
		for (const v of field.validation) {
			switch (v.rule) {
				case 'min':
					rules.push({
						type: 'number',
						min: v.value as number,
						message: v.message,
					})
					break
				case 'max':
					rules.push({
						type: 'number',
						max: v.value as number,
						message: v.message,
					})
					break
				case 'pattern':
					rules.push({
						pattern: new RegExp(v.value as string),
						message: v.message,
					})
					break
				default:
					break
			}
		}
	}

	return rules
}

/** 條件顯示欄位：根據 depends_on 判斷是否顯示 */
const DependsOnField = memo(
	({
		field,
		args,
		children,
	}: {
		field: TFormField
		args: Record<string, unknown>
		children: React.ReactNode
	}) => {
		const dependsOn = field.depends_on
		if (!dependsOn || dependsOn.length === 0) {
			return <>{children}</>
		}

		const shouldShow = dependsOn.every((dep: TDependsOn) => {
			const depValue = args[dep.field]
			switch (dep.operator) {
				case 'equals':
					return depValue === dep.value
				case 'not_equals':
					return depValue !== dep.value
				case 'empty':
					return (
						depValue === undefined ||
						depValue === null ||
						depValue === ''
					)
				case 'not_empty':
					return (
						depValue !== undefined &&
						depValue !== null &&
						depValue !== ''
					)
				default:
					return true
			}
		})

		if (!shouldShow) return null
		return <>{children}</>
	},
)

DependsOnField.displayName = 'DependsOnField'

/** 根據欄位類型渲染對應的 Ant Design 元件 */
const renderFieldInput = (field: TFormField) => {
	switch (field.type) {
		case 'text':
			return (
				<Input
					placeholder={field.placeholder}
					allowClear
				/>
			)
		case 'number':
			return (
				<InputNumber
					placeholder={field.placeholder}
					className="w-full"
				/>
			)
		case 'select':
			return (
				<Select
					options={field.options}
					placeholder={field.placeholder || '請選擇'}
					allowClear
				/>
			)
		case 'textarea':
			return (
				<TextArea
					rows={4}
					placeholder={field.placeholder}
				/>
			)
		case 'template_editor':
			return (
				<TextArea
					rows={6}
					placeholder={field.placeholder || '輸入模板內容'}
				/>
			)
		case 'switch':
			return <Switch />
		case 'date':
			return (
				<DatePicker
					showTime
					className="w-full"
					placeholder={field.placeholder || '選擇日期時間'}
				/>
			)
		case 'json':
			return (
				<TextArea
					rows={4}
					placeholder={field.placeholder || '{"key": "value"}'}
				/>
			)
		default:
			return (
				<Input
					placeholder={field.placeholder}
					allowClear
				/>
			)
	}
}

/**
 * 動態節點表單元件
 * 根據 API 回傳的 form_fields schema 動態渲染表單欄位
 */
const DynamicNodeForm = memo(
	({ formFields, args }: TDynamicNodeFormProps) => {
		/** 依 sort 排序 */
		const sortedFields = useMemo(
			() => [...formFields].sort((a, b) => a.sort - b.sort),
			[formFields],
		)

		if (sortedFields.length === 0) {
			return (
				<p className="text-gray-400 text-sm">
					此節點無需額外設定
				</p>
			)
		}

		return (
			<>
				{sortedFields.map((field) => {
					const initialValue =
						args[field.name] !== undefined
							? args[field.name]
							: field.default_value ?? undefined

					const label = field.description ? (
						<span>
							{field.label}{' '}
							<Tooltip title={field.description}>
								<QuestionCircleOutlined className="text-gray-400" />
							</Tooltip>
						</span>
					) : (
						field.label
					)

					return (
						<DependsOnField
							key={field.name}
							field={field}
							args={args}
						>
							<Item
								label={label}
								name={['args', field.name]}
								initialValue={initialValue}
								rules={toAntdRules(field)}
								valuePropName={
									field.type === 'switch'
										? 'checked'
										: 'value'
								}
							>
								{renderFieldInput(field)}
							</Item>
						</DependsOnField>
					)
				})}
			</>
		)
	},
)

DynamicNodeForm.displayName = 'DynamicNodeForm'

export default DynamicNodeForm
