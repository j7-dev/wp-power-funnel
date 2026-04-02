<?php
/**
 * Action Scheduler 統一節點排程整合測試。
 *
 * 驗證 NodeDTO::try_execute() 在成功路徑呼叫 as_schedule_single_action()，
 * 延遲節點（scheduled=true）不二次排程，失敗時不排程。
 *
 * @group workflow
 * @group action-scheduler-chaining
 *
 * @see specs/implement-node-definitions/features/engine/action-scheduler-chaining.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;
use J7\PowerFunnel\Tests\Integration\Workflow\Stubs\TestSuccessNodeDefinition;

/**
 * Action Scheduler 統一節點排程測試
 *
 * Feature: Action Scheduler 統一節點排程
 */
class ActionSchedulerChainingTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		Register::register_hooks();

		// 移除 powerhouse EmailValidator 的 pre_wp_mail 過濾器
		\remove_all_filters('pre_wp_mail');

		// 覆寫 wp_mail 的 From 地址，避免 wordpress@localhost 被 PHPMailer 拒絕
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 注入測試用節點（繞過 ReplaceHelper null obj bug）
		\add_filter(
			'power_funnel/workflow_rule/node_definitions',
			static function ( array $definitions ): array {
				// 測試用 email 節點（直接呼叫 wp_mail，不使用 ParamHelper）
				$definitions['test_email'] = new class extends \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition {
					public string $id          = 'test_email';
					public string $name        = '測試 Email 節點';
					public string $description = '測試用，直接呼叫 wp_mail()';
					public \J7\PowerFunnel\Shared\Enums\ENodeType $type = \J7\PowerFunnel\Shared\Enums\ENodeType::SEND_MESSAGE;

					/**
					 * 執行節點
					 *
					 * @param \J7\PowerFunnel\Contracts\DTOs\NodeDTO     $node     節點
					 * @param \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow 工作流
					 * @return \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO
					 */
					public function execute( \J7\PowerFunnel\Contracts\DTOs\NodeDTO $node, \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow ): \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO {
						$recipient = $node->params['recipient'] ?? 'test@example.com';
						$subject   = $node->params['subject_tpl'] ?? 'Test Subject';
						$content   = $node->params['content_tpl'] ?? 'Test Content';
						$result    = \wp_mail( (string) $recipient, (string) $subject, (string) $content );
						$code      = $result ? 200 : 500;
						$message   = $result ? '發信成功' : '發信失敗';
						return new \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO(
							[
								'node_id' => $node->id,
								'code'    => $code,
								'message' => $message,
							]
						);
					}
				};

				// 測試用成功節點（永遠回傳 code=200，不依賴外部服務）
				$definitions['test_success'] = new TestSuccessNodeDefinition();

				return $definitions;
			}
		);
	}

	/**
	 * 清理 Action Scheduler pending actions，避免測試間互相干擾
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\as_unschedule_all_actions('power_funnel/workflow/running');
		parent::tear_down();
	}

	/**
	 * 建立測試用 Workflow post
	 *
	 * @param array<array<string, mixed>> $nodes      節點陣列
	 * @param array<array<string, mixed>> $results    已有的結果陣列
	 * @param string                      $status     工作流狀態
	 * @return int workflow post ID
	 */
	private function create_workflow_post( array $nodes, array $results = [], string $status = 'running' ): int {
		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/registration_created',
			'nodes'                => $nodes,
			'context_callable_set' => [],
			'results'              => $results,
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'ActionSchedulerChaining 測試',
				'meta_input'  => \wp_slash($meta),
			]
		);

		if (!is_int($post_id) || $post_id <= 0) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		if ($status !== 'draft') {
			$this->set_post_status_bypass_hooks($post_id, $status);
		}

		return $post_id;
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 非延遲節點成功後引擎應排程立即執行下一節點
	 * Example: EmailNode 成功後引擎排程 as_schedule_single_action(time(), ...)
	 *
	 * Scenario: EmailNode 成功後引擎排程 as_schedule_single_action()
	 *   Given Workflow 有兩個 test_email 節點（n1 → n2）
	 *   When try_execute() 執行第一個節點
	 *   Then as_has_scheduled_action() 回傳 true
	 *   And workflow results 應含有 n1 的成功結果
	 */
	public function test_EmailNode成功後引擎排程as_schedule_single_action(): void {
		// Given Workflow 有兩個 test_email 節點
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Hi',
					'content_tpl' => 'Hello',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Follow up',
					'content_tpl' => 'Hi again',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_workflow_post($nodes);

		// When try_execute() 執行第一個節點（n1）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then as_has_scheduled_action() 應回傳 true（引擎已為 n2 排程）
		$has_action = \as_has_scheduled_action(
			'power_funnel/workflow/running',
			[ 'workflow_id' => (string) $workflow_id ]
		);
		$this->assertTrue($has_action, 'EmailNode 成功後引擎應呼叫 as_schedule_single_action()');

		// And workflow results 應含有 n1 的成功結果
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertNotEmpty($updated_dto->results, '應有執行結果');
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame('n1', $first_result->node_id, '結果節點 id 應為 n1');
		$this->assertSame(200, $first_result->code, 'EmailNode 成功時 code 應為 200');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 延遲節點成功後引擎不應二次排程
	 * Example: WaitNode 回傳 scheduled=true 時引擎不排程
	 *
	 * Scenario: WaitNode 自行排程後引擎不二次排程
	 *   Given Workflow 有 wait 節點（n1）再接 test_email 節點（n2）
	 *   When try_execute() 執行 WaitNode
	 *   Then 只有 WaitNode 自排程的 AS action，不存在引擎的二次排程
	 *   And workflow results 含有 n1 的成功結果（scheduled=true）
	 */
	public function test_WaitNode回傳scheduled_true時引擎不排程(): void {
		// Given Workflow 有 wait → test_email 節點
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'wait',
				'params'                => [
					'duration' => '30',
					'unit'     => 'minutes',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'After wait',
					'content_tpl' => 'Hi',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		// 記錄執行前 AS action 數量（使用 hook 過濾）
		$before_actions = \as_get_scheduled_actions([ 'hook' => 'power_funnel/workflow/running' ], 'ids');
		$before_count   = is_array($before_actions) ? count($before_actions) : 0;

		$workflow_id = $this->create_workflow_post($nodes);

		// When try_execute() 執行 WaitNode
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 查詢 AS action 數量
		$after_actions = \as_get_scheduled_actions([ 'hook' => 'power_funnel/workflow/running' ], 'ids');
		$after_count   = is_array($after_actions) ? count($after_actions) : 0;

		// WaitNode 自行排程 1 個，引擎不應二次排程（總增加量應 = 1）
		$increase = $after_count - $before_count;
		$this->assertSame(1, $increase, 'WaitNode 排程後 AS action 增加量應為 1（只有 WaitNode 自排程）');

		// And workflow results 應含有 n1 的成功結果，scheduled=true
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertNotEmpty($updated_dto->results, '應有執行結果');
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame('n1', $first_result->node_id, '結果節點 id 應為 n1');
		$this->assertSame(200, $first_result->code, 'WaitNode 成功時 code 應為 200');
		$this->assertTrue($first_result->scheduled, 'WaitNode 結果 scheduled 應為 true');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 節點執行失敗時不應排程下一節點
	 * Example: 節點回傳 code=500 時不排程
	 *
	 * Scenario: 找不到節點定義時不排程，workflow 標記 failed
	 *   Given Workflow 有 non_existent 節點（n1）接 test_email 節點（n2）
	 *   When try_execute() 嘗試執行 n1
	 *   Then as_has_scheduled_action() 回傳 false
	 *   And workflow status 應為 "failed"
	 */
	public function test_節點回傳code_500時不排程(): void {
		// Given Workflow 有不存在的節點定義
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'non_existent',
				'params'                => [],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Follow up',
					'content_tpl' => 'Hi',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_workflow_post($nodes);

		// When try_execute() 嘗試執行 n1（會失敗）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then as_has_scheduled_action() 應回傳 false（失敗後不排程）
		$has_action = \as_has_scheduled_action(
			'power_funnel/workflow/running',
			[ 'workflow_id' => (string) $workflow_id ]
		);
		$this->assertFalse($has_action, '節點失敗時不應排程下一節點');

		// And workflow status 應為 "failed"
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame('failed', $post_status, '節點失敗後 workflow 狀態應為 failed');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 最後一個節點成功後引擎排程使 workflow 進入 completed
	 * Example: 最後一個節點成功後排程觸發 completed
	 *
	 * Scenario: 單節點 workflow，執行後排程下一步，再次觸發後 completed
	 *   Given Workflow 只有 1 個 test_email 節點（n1），results 為空
	 *   When 第一次 try_execute()（執行 n1，成功）
	 *   Then as_has_scheduled_action() 回傳 true
	 *   When 第二次 try_execute()（get_current_index() 回傳 null）
	 *   Then workflow status 應為 "completed"
	 */
	public function test_最後一個節點成功後排程觸發completed(): void {
		// Given Workflow 只有 1 個 test_email 節點
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Hi',
					'content_tpl' => 'Hello',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];
		$workflow_id = $this->create_workflow_post($nodes);

		// When 第一次 try_execute()（執行 n1）
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		// Then 引擎應排程下一步
		$has_action = \as_has_scheduled_action(
			'power_funnel/workflow/running',
			[ 'workflow_id' => (string) $workflow_id ]
		);
		$this->assertTrue($has_action, '最後一個節點成功後引擎應排程 AS action');

		// When 第二次 try_execute()（模擬 AS 到期觸發，n1 已執行完，進入 completed）
		\clean_post_cache($workflow_id);
		$workflow_dto2 = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto2->try_execute();

		// Then workflow status 應為 "completed"
		\clean_post_cache($workflow_id);
		$post_status = \get_post_status($workflow_id);
		$this->assertSame('completed', $post_status, '所有節點完成後 workflow 狀態應為 completed');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- WorkflowResultDTO 新增 scheduled 欄位
	 * Example: WorkflowResultDTO 預設 scheduled 為 false
	 *
	 * Scenario: 直接建立 WorkflowResultDTO，驗證 scheduled 預設值
	 */
	public function test_WorkflowResultDTO預設scheduled為false(): void {
		// When 建立 WorkflowResultDTO(node_id='n1', code=200, message='OK')
		$dto = new WorkflowResultDTO(
			[
				'node_id' => 'n1',
				'code'    => 200,
				'message' => 'OK',
			]
		);

		// Then scheduled 欄位應為 false
		$this->assertFalse($dto->scheduled, 'WorkflowResultDTO 的 scheduled 預設值應為 false');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- WorkflowResultDTO 新增 scheduled 欄位
	 * Example: WorkflowResultDTO 可設定 scheduled 為 true
	 *
	 * Scenario: 建立 WorkflowResultDTO 並設定 scheduled=true
	 */
	public function test_WorkflowResultDTO可設定scheduled為true(): void {
		// When 建立 WorkflowResultDTO(node_id='n1', code=200, message='等待中', scheduled=true)
		$dto = new WorkflowResultDTO(
			[
				'node_id'   => 'n1',
				'code'      => 200,
				'message'   => '等待中',
				'scheduled' => true,
			]
		);

		// Then scheduled 欄位應為 true
		$this->assertTrue($dto->scheduled, 'WorkflowResultDTO 設定 scheduled=true 後應為 true');
	}

	/**
	 * Feature: Action Scheduler 統一節點排程
	 * Rule: 後置（狀態）- 帶有 next_node_id 的分支節點也應排程
	 * Example: YesNoBranchNode 回傳 next_node_id 時引擎仍排程下一步
	 *
	 * Scenario: YesNoBranchNode 條件成立，指向 n2（yes 路徑），引擎排程繼續
	 *   Given Workflow 有 yes_no_branch(n1) + test_email(n2,vip) + test_email(n3,regular)
	 *   And context: order_total=1500（大於 1000，yes 路徑 → n2）
	 *   When try_execute() 執行 n1（YesNoBranchNode）
	 *   Then as_has_scheduled_action() 回傳 true
	 */
	public function test_YesNoBranchNode回傳next_node_id時引擎仍排程(): void {
		// Given context: order_total=1500（yes 路徑）
		TestCallable::$test_context = [ 'order_total' => '1500' ];
		$context_callable_set       = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => [
					'condition_field'  => 'order_total',
					'operator'         => 'gt',
					'condition_value'  => '1000',
					'yes_next_node_id' => 'n2',
					'no_next_node_id'  => 'n3',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'VIP',
					'content_tpl' => 'Hi VIP',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Thanks',
					'content_tpl' => 'Hi',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/registration_created',
			'nodes'                => $nodes,
			'context_callable_set' => $context_callable_set,
			'results'              => [],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'YesNoBranchNode 測試',
				'meta_input'  => \wp_slash($meta),
			]
		);
		$this->assertIsInt($post_id);
		$this->set_post_status_bypass_hooks((int) $post_id, 'running');

		// When try_execute() 執行 n1（YesNoBranchNode）
		$workflow_dto = WorkflowDTO::of((string) $post_id);
		$workflow_dto->try_execute();

		// Then as_has_scheduled_action() 應回傳 true（引擎為 n2 排程）
		$has_action = \as_has_scheduled_action(
			'power_funnel/workflow/running',
			[ 'workflow_id' => (string) $post_id ]
		);
		$this->assertTrue($has_action, 'YesNoBranchNode 成功後引擎應排程下一節點');

		// And n1 的 results 應有 next_node_id='n2'
		\clean_post_cache((int) $post_id);
		$updated_dto = WorkflowDTO::of((string) $post_id);
		$this->assertNotEmpty($updated_dto->results, '應有執行結果');
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame('n2', $first_result->next_node_id, 'order_total=1500 時 yes 路徑應指向 n2');

		// 清理 context
		TestCallable::$test_context = [];
	}
}
