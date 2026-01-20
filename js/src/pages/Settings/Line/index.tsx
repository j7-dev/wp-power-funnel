import { memo } from 'react'
import { Form, FormItemProps, Input } from 'antd'
import { Heading, SimpleImage } from 'antd-toolkit'

const { Item } = Form
const lineFields: FormItemProps[] = [
	{
		name: 'channel_access_token',
		label: 'Channel Access Token',
	},
	{
		name: 'channel_id',
		label: 'Channel ID',
	},
	{
		name: 'channel_secret',
		label: 'Channel Secret',
	},
	{
		name: 'liff_id',
		label: 'LIFF ID',
	},
]

const index = () => {
	return (
		<div className="flex flex-col md:flex-row gap-8">
			<div className="w-full max-w-[400px]">
				<Heading className="mt-8">LINE 設定</Heading>
				{lineFields.map(({ name, label }) => (
					<Item key={name} name={['line', name]} label={label}>
						<Input allowClear />
					</Item>
				))}
			</div>
			<div className="flex-1 h-auto md:h-[calc(100%-5.375rem)] md:overflow-y-auto">
				<Heading className="mt-8">如果浮水印功能沒有顯示出來</Heading>
				<p>請到Bunny關閉DRM功能，浮水印才會正常顯示喔</p>
				<SimpleImage src="" ratio="aspect-[2.1]" />
			</div>
		</div>
	)
}

export default memo(index)
