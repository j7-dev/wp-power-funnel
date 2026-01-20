import { Heading } from 'antd-toolkit'
import { memo } from 'react'
import { Form, FormItemProps, Input } from 'antd'
import { SimpleImage } from 'antd-toolkit'

const { Item } = Form

const youtubeFields: FormItemProps[] = [
	{
		name: 'channelId',
		label: 'Channel Id',
	},
	{
		name: 'clientId',
		label: 'Client Id',
	},
	{
		name: 'clientSecret',
		label: 'Client Secret',
	},
]

const index = () => {
	return (
		<div className="flex flex-col md:flex-row gap-8">
			<div className="w-full max-w-[400px]">
				<Heading className="mt-8">Youtube 設定</Heading>
				{youtubeFields.map(({ name, label }) => (
					<Item key={name} name={['youtube', name]} label={label}>
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
