/* eslint-disable quote-props */
import '@/assets/scss/index.scss'
import DefaultPage from '@/pages'
import About from '@/pages/about'

import { Refine } from '@refinedev/core'

import {
	ErrorComponent,
	useNotificationProvider,
	ThemedLayoutV2,
	ThemedSiderV2,
} from '@refinedev/antd'
import '@refinedev/antd/dist/reset.css'
import routerBindings, {
	DocumentTitleHandler,
	UnsavedChangesNotifier,
} from '@refinedev/react-router-v6'
import { dataProvider } from './rest-data-provider'
import { HashRouter, Outlet, Route, Routes } from 'react-router-dom'
import { API_URL, KEBAB } from '@/utils'
import { resources } from '@/resources'
import { ConfigProvider } from 'antd'

function App() {
	return (
		<div className='overflow-x-auto'>
			<div className="w-[1200px] xl:w-full">
				<HashRouter>
					<Refine
						dataProvider={{
							default: dataProvider(`${API_URL}/${KEBAB}`),
							'wp-rest': dataProvider(`${API_URL}/wp/v2`),
							'wc-rest': dataProvider(`${API_URL}/wc/v3`),
							'wc-store': dataProvider(`${API_URL}/wc/store/v1`),
						}}
						notificationProvider={useNotificationProvider}
						routerProvider={routerBindings}
						resources={resources}
						options={{
							syncWithLocation: true,
							warnWhenUnsavedChanges: true,
							projectId: 'power-funnel',
							reactQuery: {
								clientConfig: {
									defaultOptions: {
										queries: {
											staleTime: 1000 * 60 * 10,
											cacheTime: 1000 * 60 * 10,
											retry: 0,
										},
									},
								},
							},
						}}
					>
						<Routes>
							<Route
								element={
									<ConfigProvider
										theme={{
											components: {
												Collapse: {
													contentPadding: '8px 8px',
												},
											},
										}}
									>
										<ThemedLayoutV2
											Sider={(props: any) => <ThemedSiderV2 {...props} fixed />}
											Title={({ collapsed }: any) => <></>}
										>
											<Outlet />
										</ThemedLayoutV2>
									</ConfigProvider>
								}
							>
								<Route index element={<DefaultPage />} />
								<Route path="about" element={<About />} />
								<Route path="*" element={<ErrorComponent />} />
							</Route>
						</Routes>
						<UnsavedChangesNotifier />
						<DocumentTitleHandler />
					</Refine>
				</HashRouter>
			</div>
		</div>
	)
}

export default App
