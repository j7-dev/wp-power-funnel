<?php
/**
 * 查詢觸發條件列表 整合測試。
 *
 * 驗證 GET /wp-json/power-funnel/trigger-points 端點的行為。
 * 包含：未授權存取、成功查詢、filter 擴充、回應格式驗證。
 *
 * @group smoke
 * @group workflow-rule
 * @group trigger-points
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\WorkflowRule;

use J7\PowerFunnel\Applications\TriggerPointApi;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 查詢觸發條件列表測試
 *
 * Feature: 查詢觸發條件列表
 */
class QueryTriggerPointsTest extends IntegrationTestCase {

	/** @var \WP_REST_Server REST 伺服器實例 */
	protected \WP_REST_Server $server;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TODO: 確保 TriggerPointApi hooks 已註冊
		// TriggerPointApi::register_hooks();
	}

	/** 每個測試前設置 REST Server */
	public function set_up(): void {
		parent::set_up();
		// 初始化 REST Server
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		\do_action('rest_api_init');
	}

	/** 每個測試後清理 REST Server */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	// ========== Rule: 未授權存取 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 未登入用戶查詢觸發條件列表時操作失敗
	 *
	 * TODO: [事件風暴部位: Query - GetTriggerPoints]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 *
	 * @group smoke
	 */
	public function test_未登入用戶查詢觸發條件列表時操作失敗(): void {
		// Given 用戶未登入
		\wp_set_current_user(0);

		// When 用戶查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作失敗，錯誤為「未授權的操作」
		$this->assertSame(401, $response->get_status(), '未登入應回傳 401');

		$this->markTestIncomplete('尚未實作');
	}

	// ========== Rule: 無需提供參數 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 無需提供任何參數即可查詢
	 *
	 * TODO: [事件風暴部位: Query - GetTriggerPoints]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.success-failure 實作 Then
	 *
	 * @group happy
	 */
	public function test_無需提供任何參數即可查詢(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		$this->markTestIncomplete('尚未實作');
	}

	// ========== Rule: 回應應包含所有已註冊的觸發點 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 僅有預設觸發點時回傳預設列表
	 *
	 * TODO: [事件風暴部位: Query - GetTriggerPoints]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 *
	 * @group happy
	 */
	public function test_僅有預設觸發點時回傳預設列表(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// And 無第三方開發者透過 filter 擴充觸發點（無需額外操作，測試環境預設狀態）

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 查詢結果應包含預設觸發點 pf/trigger/registration_created
		$data = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');

		$hooks = \array_column($data['data'], 'hook');
		$this->assertContains('pf/trigger/registration_created', $hooks, '應包含 registration_created 觸發點');

		$this->markTestIncomplete('尚未實作');
	}

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 有第三方開發者擴充觸發點時回傳合併後的列表
	 *
	 * TODO: [事件風暴部位: Query - GetTriggerPoints]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 *
	 * @group happy
	 */
	public function test_有第三方開發者擴充觸發點時回傳合併後的列表(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// And 第三方開發者透過 filter 新增觸發點 pf/trigger/order_completed
		\add_filter(
			'power_funnel/workflow_rule/trigger_points',
			static function ( array $dtos ): array {
				$dtos['pf/trigger/order_completed'] = new \J7\PowerFunnel\Contracts\DTOs\TriggerPointDTO(
					[
						'hook' => 'pf/trigger/order_completed',
						'name' => '訂單完成後',
					]
				);
				return $dtos;
			}
		);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 查詢結果應包含預設與擴充的觸發點
		$data  = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');

		$hooks = \array_column($data['data'], 'hook');
		$this->assertContains('pf/trigger/registration_created', $hooks, '應包含預設觸發點');
		$this->assertContains('pf/trigger/order_completed', $hooks, '應包含第三方擴充的觸發點');

		$this->markTestIncomplete('尚未實作');
	}

	// ========== Rule: 每個觸發點應包含 hook 與 name 欄位 ==========

	/**
	 * Feature: 查詢觸發條件列表
	 * Example: 回應格式正確
	 *
	 * TODO: [事件風暴部位: Query - GetTriggerPoints]
	 * TODO: 參考 /wp-workflows:aibdd.auto.php.it.handlers.readmodel-then 實作 Then
	 *
	 * @group happy
	 */
	public function test_回應格式正確每個觸發點包含hook與name(): void {
		// Given 管理員 "Admin" 已登入
		$admin_id = $this->factory()->user->create(['role' => 'administrator']);
		\wp_set_current_user($admin_id);

		// When 管理員 "Admin" 查詢觸發條件列表
		$request  = new \WP_REST_Request('GET', '/power-funnel/trigger-points');
		$response = $this->server->dispatch($request);

		// Then 操作成功
		$this->assertSame(200, $response->get_status(), '應回傳 200');

		// And 每個觸發點項目應包含 hook(string) 與 name(string) 欄位
		$data = $response->get_data();
		$this->assertIsArray($data, 'response data 應為陣列');
		$this->assertArrayHasKey('code', $data, 'response 應有 code 欄位');
		$this->assertArrayHasKey('message', $data, 'response 應有 message 欄位');
		$this->assertArrayHasKey('data', $data, 'response 應有 data 欄位');
		$this->assertIsArray($data['data'], 'data.data 應為陣列');
		$this->assertNotEmpty($data['data'], 'data.data 不應為空');

		foreach ($data['data'] as $item) {
			$this->assertIsArray($item, '每個觸發點應為陣列');
			$this->assertArrayHasKey('hook', $item, '觸發點應有 hook 欄位');
			$this->assertArrayHasKey('name', $item, '觸發點應有 name 欄位');
			$this->assertIsString($item['hook'], 'hook 應為字串');
			$this->assertIsString($item['name'], 'name 應為字串');
		}

		$this->markTestIncomplete('尚未實作');
	}
}
