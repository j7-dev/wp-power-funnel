<?php

/**
 * 工作流狀態觸發點整合測試。
 *
 * 驗證工作流狀態變更時能正確觸發對應的 pf/trigger/* hook。
 *
 * @group trigger-points
 * @group workflow-trigger
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 工作流狀態觸發點測試
 *
 * Feature: 工作流完成後觸發工作流
 * Feature: 工作流失敗後觸發工作流
 */
class WorkflowTriggerTest extends IntegrationTestCase {

	/** @var array<string, array<array<string, mixed>>> 已觸發的事件記錄 */
	private array $fired_triggers = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// TriggerPointService 已透過 Bootstrap 的 plugin 載入完成，不需重複呼叫
		// 只需確保 Workflow\Register::register_lifecycle 已掛載以觸發 power_funnel/workflow/{status}
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_triggers = [];

		// 監聽所有相關觸發點
		foreach ([
			ETriggerPoint::WORKFLOW_COMPLETED->value,
			ETriggerPoint::WORKFLOW_FAILED->value,
		] as $hook) {
			$short_name                          = str_replace('pf/trigger/', '', $hook);
			$this->fired_triggers[ $short_name ] = [];
			\add_action(
				$hook,
				/**
				 * @param array<string, mixed> $context_callable_set
				 */
				function ( array $context_callable_set ) use ( $short_name ): void {
					$this->fired_triggers[ $short_name ][] = $context_callable_set;
				},
				999
			);
		}
	}

	// ========== Rule: 工作流狀態變更時觸發對應的 pf/trigger/* hook ==========

	/**
	 * Feature: 工作流完成後觸發工作流
	 * Example: 工作流狀態更新為 completed 時觸發 workflow_completed
	 *
	 * @group happy
	 */
	public function test_工作流完成時觸發workflow_completed(): void {
		// Given 一個 running 狀態的工作流（使用繞過 hooks 的方式建立，避免 start_workflow 自動執行）
		$workflow_id = $this->create_workflow([ 'post_status' => 'draft' ]);
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 工作流狀態更新為 completed
		\wp_update_post([
			'ID'          => $workflow_id,
			'post_status' => 'completed',
		]);

		// Then pf/trigger/workflow_completed 被觸發
		$this->assertCount(1, $this->fired_triggers['workflow_completed'], 'workflow_completed 應被觸發一次');
		$this->assertEmpty($this->fired_triggers['workflow_failed'], 'workflow_failed 不應被觸發');
	}

	/**
	 * Feature: 工作流失敗後觸發工作流
	 * Example: 工作流狀態更新為 failed 時觸發 workflow_failed
	 *
	 * @group happy
	 */
	public function test_工作流失敗時觸發workflow_failed(): void {
		// Given 一個 running 狀態的工作流（使用繞過 hooks 的方式建立，避免 start_workflow 自動執行）
		$workflow_id = $this->create_workflow([ 'post_status' => 'draft' ]);
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 工作流狀態更新為 failed
		\wp_update_post([
			'ID'          => $workflow_id,
			'post_status' => 'failed',
		]);

		// Then pf/trigger/workflow_failed 被觸發
		$this->assertCount(1, $this->fired_triggers['workflow_failed'], 'workflow_failed 應被觸發一次');
		$this->assertEmpty($this->fired_triggers['workflow_completed'], 'workflow_completed 不應被觸發');
	}

	/**
	 * Example: context_callable 包含 workflow_id、workflow_rule_id、trigger_point
	 *
	 * @group happy
	 */
	public function test_workflow_context包含正確欄位(): void {
		// Given 一個包含完整 meta 的 running 工作流（使用繞過 hooks 的方式建立）
		$rule_id     = $this->create_workflow_rule([ 'post_status' => 'publish' ]);
		$workflow_id = $this->create_workflow([ 'post_status' => 'draft' ]);
		$this->set_post_status_bypass_hooks($workflow_id, 'running');
		\update_post_meta($workflow_id, 'workflow_rule_id', (string) $rule_id);
		\update_post_meta($workflow_id, 'trigger_point', 'pf/trigger/registration_approved');

		// When 工作流狀態更新為 completed
		\wp_update_post([
			'ID'          => $workflow_id,
			'post_status' => 'completed',
		]);

		$this->assertCount(1, $this->fired_triggers['workflow_completed'], 'workflow_completed 應被觸發');

		$context_callable_set = $this->fired_triggers['workflow_completed'][0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含正確欄位
		$this->assertSame((string) $workflow_id, $context['workflow_id'], 'workflow_id 應相符');
		$this->assertSame((string) $rule_id, $context['workflow_rule_id'], 'workflow_rule_id 應相符');
		$this->assertSame('pf/trigger/registration_approved', $context['trigger_point'], 'trigger_point 應相符');
	}
}
