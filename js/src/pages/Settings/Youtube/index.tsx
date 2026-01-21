import { Heading } from 'antd-toolkit'
import { memo } from 'react'
import { Button, Form, FormItemProps, Input, Popconfirm, Tag } from 'antd'
import { SimpleImage } from 'antd-toolkit'
import useOptions from '@/pages/Settings/hooks/useOptions'
import { CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons'
import { SITE_URL } from '@/utils'

const { Item } = Form

const youtubeFields: FormItemProps[] = [
	{
		name: 'clientId',
		label: '用戶端 ID ( Client Id )',
	},
	{
		name: 'clientSecret',
		label: '用戶端密碼 ( Client Secret )',
	},
]

const TUTORIALS: React.ReactNode[][] = [
	[
		<>
			前往{' '}
			<a href="https://console.cloud.google.com/" target="_blank">
				Google Cloud Console
			</a>
		</>,
	],
	['新增專案'],
	['選取剛剛新增的專案，並且搜尋選取 「YouTube Data API v3」'],
	['啟用 「YouTube Data API v3」'],
	['前往品牌，建立 Google OAuth 同意畫面'],
	['建立憑證，選擇 「OAuth 用戶端 ID」'],
	['填寫基本資料，並選擇「外部」'],
	['輸入你的網站網域(不含 https)以及基本資料'],
	['前往用戶端，建立用戶端'],
	['選擇「網頁應用程式」'],
	[`設定授權後導向網址  ${SITE_URL}`],
	['複製 「用戶端 ID」 「用戶端密碼」'],
]

const HEADINGS = {
	1: '創建 Google Cloud Console 專案',
	6: '創建 OAuth 用戶端憑證',
}

const index = () => {
	const form = Form.useFormInstance()
	const { data } = useOptions({ form })
	const googleOauth = data?.data?.data?.googleOauth
	const isAuthorized = !!googleOauth?.isAuthorized
	const authUrl = googleOauth?.authUrl
	const handleRevoke = () => {}

	return (
		<div className="flex flex-col md:flex-row gap-8">
			<div className="w-full max-w-[400px]">
				<Heading className="mt-8">Google OAuth 連接狀態</Heading>
				{isAuthorized && (
					<>
						<Tag
							icon={<CheckCircleOutlined />}
							color="success"
							className="py-[1px]"
						>
							Google OAuth 授權成功
						</Tag>
						<Popconfirm
							title="撤銷授權"
							description="撤銷授權後，用戶將收不到 Youtube 直播活動場次訊息，下方資訊也需要重新輸入"
							onConfirm={handleRevoke}
							okText="確認"
							cancelText="取消"
						>
							<Button color="danger" variant="outlined" size="small">
								撤銷授權
							</Button>
						</Popconfirm>
					</>
				)}

				{!isAuthorized && (
					<>
						<Tag
							icon={<CloseCircleOutlined />}
							color="error"
							className="py-[1px]"
						>
							Google OAuth 尚未授權
						</Tag>
						<Button
							color="primary"
							variant="outlined"
							size="small"
							target="_blank"
							href={authUrl}
							rel="noreferrer noopener"
							disabled={!authUrl}
						>
							前往授權
						</Button>
					</>
				)}

				<Heading className="mt-8">Youtube 設定</Heading>
				{youtubeFields.map(({ name, label }) => (
					<Item key={name} name={['youtube', name]} label={label}>
						<Input.Password allowClear disabled={isAuthorized} />
					</Item>
				))}
			</div>
			<div className="flex-1 md:h-[calc(100vh-8rem)] md:overflow-y-auto">
				{TUTORIALS.map((items, index) => {
					const order = index + 1
					// @ts-ignore
					const heading: string | undefined = HEADINGS[order] || undefined
					return (
						<>
							{heading && <Heading className="mt-8">{heading}</Heading>}
							{items.map((item, i) => (
								<p>
									{i === 0 && `${order}. `}
									{item}
								</p>
							))}
							<SimpleImage
								src={`${SITE_URL}/wp-content/plugins/power-funnel/inc/assets/youtube/${String(order).padStart(2, '0')}.jpg`}
								ratio="aspect-[2.1]"
								className="mb-8"
							/>
						</>
					)
				})}
			</div>
		</div>
	)
}

export default memo(index)
