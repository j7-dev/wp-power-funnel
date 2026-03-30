<?php

/**
 * 觸發點 meta 向後相容性測試。
 *
 * 驗證 WorkflowRuleDTO 能正確解析舊版（純字串）和新版（JSON 物件）格式的 trigger_point meta。
 *
 * @group workflow-rule
 * @group trigger-points
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 觸發點 meta 相容性測試
 *
 * Feature: 觸發點 meta 向後相容性
 */
class TriggerPointMetaTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		Register::register_hooks();
	}

	// ========== Rule: 舊版純字串格式向後相容 ==========

	/**
	 * Feature: 觸發點 meta 向後相容性
	 * Example: 舊版純字串格式的 trigger_point 正確解析
	 *
	 * @group happy
	 */
	public function test_舊版字串格式的trigger_point正確解析(): void {
		// Given 一個使用舊版純字串格式的 WorkflowRule
		$hook    = 'pf/trigger/registration_approved';
		$rule_id = Repository::create([
			'post_title' => '舊版規則',
			'meta_input' => [
				'trigger_point' => $hook,
				'nodes'         => [],
			],
		]);

		// 確認 meta 以純字串格式儲存
		$raw_meta = \get_post_meta($rule_id, 'trigger_point', true);
		$this->assertIsString($raw_meta, 'meta 應為字串');
		$this->assertSame($hook, $raw_meta, '舊版格式的 meta 應原樣儲存');

		// When 建立 WorkflowRuleDTO
		$dto = WorkflowRuleDTO::of((string) $rule_id);

		// Then trigger_point 應正確解析為 hook 名稱
		$this->assertSame($hook, $dto->trigger_point, 'trigger_point 應正確解析');
		$this->assertSame($hook, $dto->get_trigger_hook(), 'get_trigger_hook 應回傳正確 hook');
		$this->assertEmpty($dto->get_trigger_params(), 'get_trigger_params 應回傳空陣列');
	}

	/**
	 * Example: 舊版格式的 WorkflowRule 的 register() 正確掛載 hook
	 *
	 * @group happy
	 */
	public function test_舊版格式能正確掛載hook(): void {
		// Given 一個使用舊版純字串格式的已發布 WorkflowRule
		$hook    = 'pf/trigger/test_legacy_' . \uniqid();
		$rule_id = Repository::create([
			'post_title'  => '舊版規則',
			'post_status' => 'publish',
			'meta_input'  => [
				'trigger_point' => $hook,
				'nodes'         => [],
			],
		]);

		// When 建立 DTO 並呼叫 register()
		$dto = WorkflowRuleDTO::of((string) $rule_id);
		$dto->register();

		// Then 系統應在 hook 上掛載 callback
		$this->assertNotFalse(\has_action($hook), "舊版規則應在 {$hook} 上掛載 callback");
	}

	// ========== Rule: 新版 JSON 物件格式正確解析 ==========

	/**
	 * Example: 新版 JSON 物件格式的 trigger_point 正確解析
	 *
	 * @group happy
	 */
	public function test_新版JSON格式的trigger_point正確解析(): void {
		// Given 一個使用新版 JSON 物件格式的 WorkflowRule
		$hook          = 'pf/trigger/activity_before_start';
		$before_minutes = 30;
		$trigger_data  = [ 'hook' => $hook, 'params' => [ 'before_minutes' => $before_minutes ] ];
		$rule_id       = Repository::create([
			'post_title' => '新版規則',
			'meta_input' => [
				'trigger_point' => (string) \wp_json_encode($trigger_data),
				'nodes'         => [],
			],
		]);

		// When 建立 WorkflowRuleDTO
		$dto = WorkflowRuleDTO::of((string) $rule_id);

		// Then trigger_point 應正確解析為 hook 名稱
		$this->assertSame($hook, $dto->trigger_point, 'trigger_point 應正確解析為 hook 名稱');
		$this->assertSame($hook, $dto->get_trigger_hook(), 'get_trigger_hook 應回傳正確 hook');

		// Then trigger_params 應包含 before_minutes
		$params = $dto->get_trigger_params();
		$this->assertIsArray($params, 'trigger_params 應為陣列');
		$this->assertArrayHasKey('before_minutes', $params, 'trigger_params 應有 before_minutes');
		$this->assertSame($before_minutes, (int) $params['before_minutes'], 'before_minutes 應相符');
	}

	/**
	 * Example: 兩種格式的 WorkflowRule 都能正常運作
	 *
	 * @group happy
	 */
	public function test_兩種格式並存時均能正常運作(): void {
		// Given 舊版規則
		$legacy_hook = 'pf/trigger/test_legacy_parallel_' . \uniqid();
		$legacy_id   = Repository::create([
			'post_title'  => '舊版規則',
			'post_status' => 'publish',
			'meta_input'  => [
				'trigger_point' => $legacy_hook,
				'nodes'         => [],
			],
		]);

		// Given 新版規則
		$new_hook       = 'pf/trigger/activity_before_start';
		$trigger_data   = [ 'hook' => $new_hook, 'params' => [ 'before_minutes' => 15 ] ];
		$new_id         = Repository::create([
			'post_title'  => '新版規則',
			'post_status' => 'publish',
			'meta_input'  => [
				'trigger_point' => (string) \wp_json_encode($trigger_data),
				'nodes'         => [],
			],
		]);

		// When 建立兩個 DTO 並呼叫 register()
		$legacy_dto = WorkflowRuleDTO::of((string) $legacy_id);
		$new_dto    = WorkflowRuleDTO::of((string) $new_id);
		$legacy_dto->register();
		$new_dto->register();

		// Then 舊版規則應在 legacy_hook 上掛載 callback
		$this->assertNotFalse(\has_action($legacy_hook), "舊版規則應在 {$legacy_hook} 上掛載 callback");

		// Then 新版規則應在 new_hook 上掛載 callback
		$this->assertNotFalse(\has_action($new_hook), "新版規則應在 {$new_hook} 上掛載 callback");

		// Then 兩個 DTO 的 trigger_hook 應正確
		$this->assertSame($legacy_hook, $legacy_dto->get_trigger_hook(), '舊版 trigger_hook 應相符');
		$this->assertSame($new_hook, $new_dto->get_trigger_hook(), '新版 trigger_hook 應相符');
	}

	// ========== Rule: get_publish_workflow_rules 整合測試 ==========

	/**
	 * Example: get_publish_workflow_rules 回傳包含兩種格式的已發布規則
	 *
	 * @group happy
	 */
	public function test_get_publish_workflow_rules回傳兩種格式的規則(): void {
		// Given 一個舊版和一個新版的已發布規則
		$legacy_hook = 'pf/trigger/test_legacy_query_' . \uniqid();
		Repository::create([
			'post_title'  => '舊版已發布規則',
			'post_status' => 'publish',
			'meta_input'  => [ 'trigger_point' => $legacy_hook, 'nodes' => [] ],
		]);

		$new_hook   = 'pf/trigger/activity_before_start';
		$new_data   = [ 'hook' => $new_hook, 'params' => [ 'before_minutes' => 20 ] ];
		Repository::create([
			'post_title'  => '新版已發布規則',
			'post_status' => 'publish',
			'meta_input'  => [ 'trigger_point' => (string) \wp_json_encode($new_data), 'nodes' => [] ],
		]);

		// When 查詢已發布規則
		$rules = Repository::get_publish_workflow_rules();

		// Then 應回傳至少 2 個規則
		$this->assertGreaterThanOrEqual(2, count($rules), '應至少有 2 個已發布規則');

		// Then 每個規則的 trigger_hook 應為有效字串
		foreach ($rules as $rule) {
			$this->assertIsString($rule->get_trigger_hook(), 'trigger_hook 應為字串');
			$this->assertNotEmpty($rule->get_trigger_hook(), 'trigger_hook 不應為空');
		}
	}
}
