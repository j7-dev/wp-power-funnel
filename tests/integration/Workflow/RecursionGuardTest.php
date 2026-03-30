<?php

/**
 * 工作流遞迴防護整合測試。
 *
 * 驗證 RecursionGuard 能正確防止工作流無限觸發鏈。
 *
 * @group trigger-points
 * @group recursion-guard
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register as WorkflowRegister;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Repository as WorkflowRepository;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register as WorkflowRuleRegister;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 遞迴防護測試
 *
 * Feature: 工作流遞迴防護
 */
class RecursionGuardTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		WorkflowRuleRegister::register_hooks();
		WorkflowRegister::register_hooks();
	}

	/** 每個測試前重置 RecursionGuard */
	public function set_up(): void {
		parent::set_up();
		RecursionGuard::reset();
	}

	/** 每個測試後重置 RecursionGuard */
	public function tear_down(): void {
		RecursionGuard::reset();
		parent::tear_down();
	}

	// ========== Rule: RecursionGuard 深度計數器 ==========

	/**
	 * Feature: 工作流遞迴防護
	 * Example: enter/leave 正確更新深度計數器
	 *
	 * @group happy
	 */
	public function test_enter_leave正確更新深度(): void {
		// Given 初始深度為 0
		$this->assertSame(0, RecursionGuard::depth(), '初始深度應為 0');

		// When 進入一層
		RecursionGuard::enter();
		$this->assertSame(1, RecursionGuard::depth(), 'enter 後深度應為 1');

		// When 再進入一層
		RecursionGuard::enter();
		$this->assertSame(2, RecursionGuard::depth(), '再次 enter 後深度應為 2');

		// When 離開一層
		RecursionGuard::leave();
		$this->assertSame(1, RecursionGuard::depth(), 'leave 後深度應為 1');

		// When 再離開一層
		RecursionGuard::leave();
		$this->assertSame(0, RecursionGuard::depth(), '再次 leave 後深度應為 0');
	}

	/**
	 * Example: is_exceeded 在深度超過 MAX_DEPTH 時回傳 true
	 *
	 * @group happy
	 */
	public function test_is_exceeded在深度超過MAX_DEPTH時回傳true(): void {
		// Given MAX_DEPTH = 3
		$this->assertSame(3, RecursionGuard::MAX_DEPTH, 'MAX_DEPTH 應為 3');

		// When 深度為 1、2、3 時不超過
		for ($i = 1; $i <= RecursionGuard::MAX_DEPTH; $i++) {
			RecursionGuard::enter();
			$this->assertFalse(RecursionGuard::is_exceeded(), "深度 {$i} 不應超過限制");
		}

		// When 再進入一層（深度為 4）
		RecursionGuard::enter();
		$this->assertTrue(RecursionGuard::is_exceeded(), '深度 4 應超過限制');
	}

	/**
	 * Example: reset 重置計數器為 0
	 *
	 * @group happy
	 */
	public function test_reset重置計數器(): void {
		// Given 深度為 5
		for ($i = 0; $i < 5; $i++) {
			RecursionGuard::enter();
		}
		$this->assertSame(5, RecursionGuard::depth(), '重置前深度應為 5');

		// When 重置
		RecursionGuard::reset();

		// Then 深度應為 0
		$this->assertSame(0, RecursionGuard::depth(), '重置後深度應為 0');
	}

	// ========== Rule: WorkflowRuleDTO::register 整合遞迴防護 ==========

	/**
	 * Example: 深度在 MAX_DEPTH 以內時正常建立 Workflow
	 *
	 * @group happy
	 */
	public function test_深度在限制內時正常建立Workflow(): void {
		// Given 一個 WorkflowRule，使用隨機 hook 避免干擾
		$hook    = 'pf/trigger/test_recursion_' . \uniqid();
		$rule_id = $this->create_workflow_rule([
			'post_status' => 'publish',
			'meta_input'  => [ 'trigger_point' => $hook, 'nodes' => [] ],
		]);

		$rule_dto = WorkflowRuleDTO::of((string) $rule_id);
		$rule_dto->register();

		// When 深度為 1（在限制內）時觸發 hook
		RecursionGuard::enter(); // 深度 = 1，尚未超過 MAX_DEPTH=3
		\do_action($hook, []);

		// Then 不應建立失敗 Workflow（只有正常 Workflow）
		$workflows = \get_posts([
			'post_type'      => 'pf_workflow',
			'post_status'    => 'failed',
			'posts_per_page' => -1,
			'meta_key'       => 'workflow_rule_id',
			'meta_value'     => (string) $rule_id,
		]);
		$this->assertEmpty($workflows, '深度在限制內時不應建立失敗 Workflow');
	}

	/**
	 * Example: 深度超過 MAX_DEPTH 時建立失敗 Workflow 並記錄錯誤
	 *
	 * @group happy
	 */
	public function test_深度超過限制時建立失敗Workflow(): void {
		// Given 一個 WorkflowRule，使用隨機 hook 避免干擾
		$hook    = 'pf/trigger/test_recursion_exceed_' . \uniqid();
		$rule_id = $this->create_workflow_rule([
			'post_status' => 'publish',
			'meta_input'  => [ 'trigger_point' => $hook, 'nodes' => [] ],
		]);

		$rule_dto = WorkflowRuleDTO::of((string) $rule_id);
		$rule_dto->register();

		// When 深度為 MAX_DEPTH + 1（超過限制）時觸發 hook
		for ($i = 0; $i <= RecursionGuard::MAX_DEPTH; $i++) {
			RecursionGuard::enter(); // 深度 = MAX_DEPTH + 1
		}
		\do_action($hook, []);

		// Then 應建立一個失敗狀態的 Workflow
		$failed_workflows = \get_posts([
			'post_type'      => 'pf_workflow',
			'post_status'    => 'failed',
			'posts_per_page' => -1,
			'meta_key'       => 'workflow_rule_id',
			'meta_value'     => (string) $rule_id,
		]);
		$this->assertNotEmpty($failed_workflows, '超過遞迴限制時應建立失敗 Workflow');
		$this->assertCount(1, $failed_workflows, '應只建立一個失敗 Workflow');
	}

	/**
	 * Example: 遞迴防護觸發後深度計數器正確遞減
	 *
	 * @group happy
	 */
	public function test_遞迴防護後深度計數器正確遞減(): void {
		// Given 一個 WorkflowRule
		$hook    = 'pf/trigger/test_recursion_decrement_' . \uniqid();
		$rule_id = $this->create_workflow_rule([
			'post_status' => 'publish',
			'meta_input'  => [ 'trigger_point' => $hook, 'nodes' => [] ],
		]);

		$rule_dto = WorkflowRuleDTO::of((string) $rule_id);
		$rule_dto->register();

		// Given 深度超過限制
		for ($i = 0; $i <= RecursionGuard::MAX_DEPTH; $i++) {
			RecursionGuard::enter();
		}
		$depth_before = RecursionGuard::depth();

		// When 觸發 hook（應觸發遞迴防護）
		\do_action($hook, []);

		// Then 深度應回到觸發前 - 1（因為 register callback 自己呼叫 enter/leave）
		// 注意：register() callback 會呼叫 enter 後檢查，然後呼叫 leave
		// 所以觸發後深度應與觸發前相同
		$depth_after = RecursionGuard::depth();
		$this->assertSame($depth_before, $depth_after, '觸發後深度應回到觸發前（enter/leave 平衡）');
	}

	/**
	 * Example: 深度恰好在邊界值 MAX_DEPTH 時不觸發防護
	 *
	 * @group edge
	 */
	public function test_深度恰好等於MAX_DEPTH時不觸發防護(): void {
		// Given 深度為 MAX_DEPTH（剛好在邊界，enter 後為 MAX_DEPTH，is_exceeded 為 false）
		for ($i = 0; $i < RecursionGuard::MAX_DEPTH; $i++) {
			RecursionGuard::enter(); // 深度 = MAX_DEPTH
		}

		// Then is_exceeded 應回傳 false
		$this->assertFalse(RecursionGuard::is_exceeded(), '深度等於 MAX_DEPTH 時不應超過限制');

		// When 再進入一層（深度 = MAX_DEPTH + 1）
		RecursionGuard::enter();

		// Then is_exceeded 應回傳 true
		$this->assertTrue(RecursionGuard::is_exceeded(), '深度超過 MAX_DEPTH 時應超過限制');
	}
}
