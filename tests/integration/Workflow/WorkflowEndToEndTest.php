<?php
/**
 * Workflow 全鏈路 E2E 整合測試。
 *
 * 驗證從觸發點觸發 → 建立 Workflow → 執行節點（含 WaitNode 暫停/恢復）→ completed 的完整流程。
 * 覆蓋 YesNoBranchNode 的 yes/no 路徑分支執行。
 *
 * @group workflow
 * @group workflow-e2e
 *
 * @see specs/workflow-integration-testing/features/
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register as WorkflowRuleRegister;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * Workflow 全鏈路 E2E 測試
 *
 * Feature: Workflow 全鏈路執行（ORDER_COMPLETED → completed）
 */
class WorkflowEndToEndTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 重置 RecursionGuard 靜態深度計數器，避免前一個測試殘留的深度影響
		\J7\PowerFunnel\Domains\Workflow\Services\RecursionGuard::reset();

		WorkflowRuleRegister::register_hooks();
		Register::register_hooks();

		// init hook 在測試環境中可能已觸發，直接呼叫 register_cpt / register_status
		// 確保 pf_workflow CPT 與自訂狀態在測試中可被 get_posts 查詢到
		Register::register_cpt();
		Register::register_status();

		// 移除 powerhouse EmailValidator 的 pre_wp_mail 過濾器
		\remove_all_filters('pre_wp_mail');

		// 覆寫 wp_mail 的 From 地址，避免 wordpress@localhost 被 PHPMailer 拒絕
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 注入測試用節點定義（繞過 ReplaceHelper null obj bug）
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
				$definitions['test_success'] = new class extends \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition {
					public string $id          = 'test_success';
					public string $name        = '測試成功節點';
					public string $description = '測試用，永遠回傳 code=200';
					public \J7\PowerFunnel\Shared\Enums\ENodeType $type = \J7\PowerFunnel\Shared\Enums\ENodeType::SEND_MESSAGE;

					/**
					 * 執行節點（永遠回傳 code=200）
					 *
					 * @param \J7\PowerFunnel\Contracts\DTOs\NodeDTO     $node     節點
					 * @param \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow 工作流
					 * @return \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO
					 */
					public function execute( \J7\PowerFunnel\Contracts\DTOs\NodeDTO $node, \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow ): \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO {
						return new \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO(
							[
								'node_id' => $node->id,
								'code'    => 200,
								'message' => '執行成功',
							]
						);
					}
				};

				return $definitions;
			}
		);
	}

	/**
	 * 清理 Action Scheduler pending actions 與 context 暫存，避免測試間互相干擾
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\as_unschedule_all_actions('power_funnel/workflow/running');
		TestCallable::$test_context = [];
		parent::tear_down();
	}

	/**
	 * 建立並發布 WorkflowRule，再手動呼叫 register() 掛載 hook
	 *
	 * WordPress 測試環境的 `init` hook 在 register_hooks() 被呼叫時可能已觸發，
	 * 因此需要手動呼叫 WorkflowRuleRegister::register_workflow_rules() 補充掛載。
	 *
	 * @param string                      $trigger_point  觸發點 hook
	 * @param array<array<string, mixed>> $nodes          節點陣列
	 * @return int WorkflowRule post ID
	 */
	private function create_and_register_workflow_rule( string $trigger_point, array $nodes ): int {
		$rule_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow_rule',
				'post_status' => 'publish',
				'post_title'  => 'E2E 測試規則',
				'meta_input'  => \wp_slash(
					[
						'trigger_point' => $trigger_point,
						'nodes'         => $nodes,
					]
				),
			]
		);
		$this->assertIsInt($rule_id, '建立 WorkflowRule 應成功');
		$this->ids['rule'] = $rule_id;

		// 手動呼叫 register_workflow_rules() 掛載規則到 hook
		// （init hook 在測試環境中可能已觸發，無法等待 init priority=99 自動執行）
		WorkflowRuleRegister::register_workflow_rules();

		return $rule_id;
	}

	/**
	 * 查詢由本次測試建立的所有 pf_workflow 記錄
	 *
	 * @return array<int> workflow post IDs
	 */
	private function get_all_workflow_ids(): array {
		// 使用 suppress_filters=false 確保 get_posts 能查詢到自訂 post_status
		// 注意：wp-env 測試環境下需使用 'any' 或直接查詢 DB 確保自訂狀態可被找到
		/** @var int[] $ids */
		$ids = \get_posts(
			[
				'post_type'        => 'pf_workflow',
				'post_status'      => [ 'running', 'completed', 'failed', 'draft', 'publish' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			]
		);
		return $ids;
	}

	/**
	 * Feature: Workflow 全鏈路執行（ORDER_COMPLETED → completed）
	 * Scenario: ORDER_COMPLETED 觸發後建立 running workflow
	 *
	 * Given 系統中有一個已發布的 WorkflowRule，trigger_point=pf/trigger/order_completed
	 * When do_action('pf/trigger/order_completed', $context_callable_set)
	 * Then 系統應建立至少一筆 pf_workflow 記錄，且狀態為 running 或更後的狀態
	 */
	public function test_ORDER_COMPLETED觸發後建立running_workflow(): void {
		// Given 記錄觸發前的 workflow 數量
		$before_ids = $this->get_all_workflow_ids();
		$before_count = count($before_ids);

		// Given context_callable_set
		TestCallable::$test_context = [
			'order_total'    => '1500',
			'customer_email' => 'buyer@example.com',
		];
		$context_callable_set = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		// Given 已發布的 WorkflowRule
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];
		$this->create_and_register_workflow_rule('pf/trigger/order_completed', $nodes);

		// 暫時移除 start_workflow hook 確保只建立不執行（方便檢查初始狀態）
		\remove_action(
			'power_funnel/workflow/running',
			[ Register::class, 'start_workflow' ]
		);

		// When 觸發 ORDER_COMPLETED
		\do_action('pf/trigger/order_completed', $context_callable_set);

		// 重新掛回 start_workflow hook
		\add_action(
			'power_funnel/workflow/running',
			[ Register::class, 'start_workflow' ]
		);

		// Then 應建立至少 1 筆新的 pf_workflow 記錄
		$after_ids    = $this->get_all_workflow_ids();
		$after_count  = count($after_ids);
		$new_ids      = array_diff($after_ids, $before_ids);

		$this->assertGreaterThan($before_count, $after_count, '觸發 ORDER_COMPLETED 後應建立新的 workflow');
		$this->assertNotEmpty($new_ids, '應有新建立的 workflow ID');

		// Then 新 workflow 的 post_type 應為 pf_workflow
		$new_workflow_id = (int) reset($new_ids);
		$post            = \get_post($new_workflow_id);
		$this->assertNotNull($post, '應建立 pf_workflow 文章');
		$this->assertSame('pf_workflow', $post->post_type, 'post_type 應為 pf_workflow');
	}

	/**
	 * Feature: Workflow 全鏈路執行
	 * Scenario: 三個節點（test_success + test_email + WaitNode），執行至 WaitNode 暫停
	 *
	 * Given Workflow 有 test_success(n1) + test_email(n2) + wait(n3) 三個節點
	 * When 依序手動觸發執行，執行至 WaitNode
	 * Then results 應包含 n1, n2, n3 的結果（共 3 筆）
	 * And workflow status 仍為 running（WaitNode 暫停）
	 * And as_has_scheduled_action() 回傳 true（WaitNode 已自排程）
	 */
	public function test_三個節點依序執行至WaitNode暫停(): void {
		// Given Workflow 有三個節點
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '歡迎',
					'content_tpl' => '感謝訂購',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'wait',
				'params'                => [
					'duration' => '30',
					'unit'     => 'minutes',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/order_completed',
			'nodes'                => $nodes,
			'context_callable_set' => [],
			'results'              => [],
		];
		$workflow_id = (int) \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'E2E WaitNode 暫停測試',
				'meta_input'  => \wp_slash($meta),
			]
		);
		$this->assertGreaterThan(0, $workflow_id, '建立 workflow 應成功');
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 第一次 try_execute()（執行 n1，成功 → 引擎排程 n2）
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 模擬 AS 觸發（手動 do_action，執行 n2）
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 模擬 AS 觸發（手動 do_action，執行 n3 = WaitNode）
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// Then results 應包含 3 筆（n1, n2, n3）
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertCount(3, $updated_dto->results, 'n1+n2+n3 執行後應有 3 筆結果');

		// And n3（WaitNode）的結果 scheduled=true
		$n3_result = $updated_dto->results[2] ?? null;
		$this->assertNotNull($n3_result, '應有第三個節點結果');
		$this->assertSame('n3', $n3_result->node_id, '第三個結果應為 n3');
		$this->assertSame(200, $n3_result->code, 'WaitNode 排程成功 code=200');
		$this->assertTrue($n3_result->scheduled, 'WaitNode 結果 scheduled 應為 true');

		// And workflow status 應仍為 running（WaitNode 暫停中）
		$post_status = \get_post_status($workflow_id);
		$this->assertSame('running', $post_status, 'WaitNode 排程後 workflow 應仍為 running');

		// And WaitNode 已自排程 AS action
		$has_action = \as_has_scheduled_action(
			'power_funnel/workflow/running',
			[ 'workflow_id' => (string) $workflow_id ]
		);
		$this->assertTrue($has_action, 'WaitNode 應已自排程 AS action');
	}

	/**
	 * Feature: Workflow 全鏈路執行
	 * Scenario: WaitNode 到期後繼續執行至 completed
	 *
	 * Given Workflow 已執行 test_success(n1) + test_email(n2) + wait(n3)，n3 已暫停
	 * When 手動觸發 do_action('power_funnel/workflow/running', $workflow_id)（模擬 AS 到期）
	 * Then 執行 n4（test_success），workflow status 變為 completed
	 */
	public function test_WaitNode到期後繼續執行至completed(): void {
		// Given Workflow 有四個節點，n3=WaitNode 已排程，n4 尚未執行
		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n2',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => '訂單確認',
					'content_tpl' => '您的訂單已成立',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n3',
				'node_definition_id'    => 'wait',
				'params'                => [
					'duration' => '30',
					'unit'     => 'minutes',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n4',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		// 預設 results 包含 n1, n2, n3（已執行），n4 尚未執行（WaitNode 已暫停）
		$existing_results = [
			[
				'node_id'     => 'n1',
				'code'        => 200,
				'message'     => '執行成功',
				'scheduled'   => false,
				'next_node_id' => '',
				'data'        => null,
			],
			[
				'node_id'     => 'n2',
				'code'        => 200,
				'message'     => '發信成功',
				'scheduled'   => false,
				'next_node_id' => '',
				'data'        => null,
			],
			[
				'node_id'     => 'n3',
				'code'        => 200,
				'message'     => '等待 30 分鐘，排程完成',
				'scheduled'   => true,
				'next_node_id' => '',
				'data'        => null,
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/order_completed',
			'nodes'                => $nodes,
			'context_callable_set' => [],
			'results'              => $existing_results,
		];
		$workflow_id = (int) \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'E2E WaitNode 恢復測試',
				'meta_input'  => \wp_slash($meta),
			]
		);
		$this->assertGreaterThan(0, $workflow_id, '建立 workflow 應成功');
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 手動觸發 AS 到期（模擬 Action Scheduler 執行 power_funnel/workflow/running）
		// 第一次觸發：執行 n4
		\do_action('power_funnel/workflow/running', (string) $workflow_id);

		// n4 執行完成後引擎排程了下一個 AS action（讓 try_execute 再次確認完成）
		// 清除排程並手動再次觸發（模擬 AS 到期），這次 get_current_index() 回傳 null → completed
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		\do_action('power_funnel/workflow/running', (string) $workflow_id);

		// Then n4 應已執行，workflow status 應為 completed
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);

		$this->assertCount(4, $updated_dto->results, 'WaitNode 到期後 n4 執行，共應有 4 筆結果');

		$n4_result = $updated_dto->results[3] ?? null;
		$this->assertNotNull($n4_result, '應有第四個節點結果');
		$this->assertSame('n4', $n4_result->node_id, '第四個結果應為 n4');
		$this->assertSame(200, $n4_result->code, 'n4 應執行成功（code=200）');

		$post_status = \get_post_status($workflow_id);
		$this->assertSame('completed', $post_status, 'WaitNode 到期後所有節點完成，workflow 應為 completed');
	}

	/**
	 * Feature: YesNoBranchNode 條件分支
	 * Scenario: order_total=1500（> 1000），走 yes 路徑（n_vip），不走 no 路徑（n_regular）
	 *
	 * Given Workflow 有 yes_no_branch(n_branch) + test_success(n_vip) + test_success(n_regular)
	 * And context: order_total=1500
	 * When 執行 workflow
	 * Then results 中應有 n_branch 和 n_vip，不含 n_regular
	 */
	public function test_YesNoBranchNode_yes路徑執行(): void {
		// Given context: order_total=1500（yes 路徑）
		TestCallable::$test_context = [ 'order_total' => '1500' ];
		$context_callable_set       = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		$nodes = [
			[
				'id'                    => 'n_branch',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => [
					'condition_field'  => 'order_total',
					'operator'         => 'gt',
					'condition_value'  => '1000',
					'yes_next_node_id' => 'n_vip',
					'no_next_node_id'  => 'n_regular',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n_vip',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n_regular',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/order_completed',
			'nodes'                => $nodes,
			'context_callable_set' => $context_callable_set,
			'results'              => [],
		];
		$workflow_id = (int) \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'E2E YesNoBranchNode yes 路徑',
				'meta_input'  => \wp_slash($meta),
			]
		);
		$this->assertGreaterThan(0, $workflow_id, '建立 workflow 應成功');
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 執行第一步（n_branch）
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 清除 AS 並執行第二步（n_branch 排程了 n_vip → 模擬 AS 到期）
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 清除 AS（n_vip 完成後排程 completed 檢查），再執行一次讓 workflow 進入 completed
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// Then results 應包含 n_branch 和 n_vip
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$result_node_ids = array_map(static fn( $r ) => $r->node_id, $updated_dto->results);

		$this->assertContains('n_branch', $result_node_ids, 'results 應包含 n_branch');
		$this->assertContains('n_vip', $result_node_ids, 'yes 路徑：results 應包含 n_vip');
		$this->assertNotContains('n_regular', $result_node_ids, 'yes 路徑：results 不應包含 n_regular');

		// And workflow status 應為 completed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame('completed', $post_status, 'YesNoBranchNode yes 路徑執行後 workflow 應為 completed');
	}

	/**
	 * Feature: YesNoBranchNode 條件分支
	 * Scenario: order_total=500（< 1000），走 no 路徑（n_regular），不走 yes 路徑（n_vip）
	 *
	 * Given Workflow 有 yes_no_branch(n_branch) + test_success(n_vip) + test_success(n_regular)
	 * And context: order_total=500
	 * When 執行 workflow
	 * Then results 中應有 n_branch 和 n_regular，不含 n_vip
	 */
	public function test_YesNoBranchNode_no路徑執行(): void {
		// Given context: order_total=500（no 路徑）
		TestCallable::$test_context = [ 'order_total' => '500' ];
		$context_callable_set       = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		$nodes = [
			[
				'id'                    => 'n_branch',
				'node_definition_id'    => 'yes_no_branch',
				'params'                => [
					'condition_field'  => 'order_total',
					'operator'         => 'gt',
					'condition_value'  => '1000',
					'yes_next_node_id' => 'n_vip',
					'no_next_node_id'  => 'n_regular',
				],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n_vip',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
			[
				'id'                    => 'n_regular',
				'node_definition_id'    => 'test_success',
				'params'                => [],
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/order_completed',
			'nodes'                => $nodes,
			'context_callable_set' => $context_callable_set,
			'results'              => [],
		];
		$workflow_id = (int) \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'E2E YesNoBranchNode no 路徑',
				'meta_input'  => \wp_slash($meta),
			]
		);
		$this->assertGreaterThan(0, $workflow_id, '建立 workflow 應成功');
		$this->set_post_status_bypass_hooks($workflow_id, 'running');

		// When 執行第一步（n_branch）
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 清除 AS 並執行第二步（n_branch 排程了 n_regular → 模擬 AS 到期）
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// 清除 AS 並執行第三步（讓 workflow 進入 completed）
		\as_unschedule_all_actions('power_funnel/workflow/running');
		\clean_post_cache($workflow_id);
		$dto = WorkflowDTO::of((string) $workflow_id);
		$dto->try_execute();

		// Then results 應包含 n_branch 和 n_regular
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$result_node_ids = array_map(static fn( $r ) => $r->node_id, $updated_dto->results);

		$this->assertContains('n_branch', $result_node_ids, 'results 應包含 n_branch');
		$this->assertContains('n_regular', $result_node_ids, 'no 路徑：results 應包含 n_regular');
		$this->assertNotContains('n_vip', $result_node_ids, 'no 路徑：results 不應包含 n_vip');

		// And workflow status 應為 completed
		$post_status = \get_post_status($workflow_id);
		$this->assertSame('completed', $post_status, 'YesNoBranchNode no 路徑執行後 workflow 應為 completed');
	}

}
