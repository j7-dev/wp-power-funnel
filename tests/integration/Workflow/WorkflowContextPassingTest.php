<?php
/**
 * Workflow Context 跨節點傳遞整合測試。
 *
 * 驗證 context_callable_set 的 serialize/unserialize 機制、
 * context 解析後可被節點使用，以及空 context 的邊緣處理。
 *
 * @group workflow
 * @group workflow-context
 *
 * @see specs/workflow-integration-testing/features/
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * Workflow Context 跨節點傳遞測試
 *
 * Feature: Workflow context 跨節點傳遞
 */
class WorkflowContextPassingTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		Register::register_hooks();

		// 移除 powerhouse EmailValidator 的 pre_wp_mail 過濾器
		\remove_all_filters('pre_wp_mail');

		// 覆寫 wp_mail 的 From 地址，避免 wordpress@localhost 被 PHPMailer 拒絕
		\add_filter('wp_mail_from', static fn() => 'test@example.com');

		// 注入測試用節點定義
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

				// 測試用 context_email 節點（從 context['customer_email'] 取收件人）
				$definitions['test_context_email'] = new class extends \J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition {
					public string $id          = 'test_context_email';
					public string $name        = '測試 Context Email 節點';
					public string $description = '測試用，從 context 取得收件人並呼叫 wp_mail()';
					public \J7\PowerFunnel\Shared\Enums\ENodeType $type = \J7\PowerFunnel\Shared\Enums\ENodeType::SEND_MESSAGE;

					/**
					 * 執行節點（從 workflow context 讀取收件人）
					 *
					 * @param \J7\PowerFunnel\Contracts\DTOs\NodeDTO     $node     節點
					 * @param \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow 工作流
					 * @return \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO
					 */
					public function execute( \J7\PowerFunnel\Contracts\DTOs\NodeDTO $node, \J7\PowerFunnel\Contracts\DTOs\WorkflowDTO $workflow ): \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO {
						// 從 context 取得收件人（驗證 context 傳遞機制）
						$recipient = (string) ( $workflow->context['customer_email'] ?? 'fallback@example.com' );
						$subject   = $node->params['subject_tpl'] ?? 'Test Subject';
						$content   = $node->params['content_tpl'] ?? 'Test Content';
						$result    = \wp_mail( $recipient, $subject, $content );
						$code      = $result ? 200 : 500;
						$message   = $result ? "發信至 {$recipient} 成功" : "發信至 {$recipient} 失敗";
						return new \J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO(
							[
								'node_id' => $node->id,
								'code'    => $code,
								'message' => $message,
							]
						);
					}
				};

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
		TestCallable::$test_context = [];
		parent::tear_down();
	}

	/**
	 * 建立帶有 context_callable_set 的 Workflow post
	 *
	 * @param array<string, mixed>        $context_callable_set context callable 設定
	 * @param array<array<string, mixed>> $nodes                節點陣列
	 * @return int workflow post ID
	 */
	private function create_workflow_with_context( array $context_callable_set, array $nodes = [] ): int {
		$default_nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_email',
				'params'                => [
					'recipient'   => 'test@example.com',
					'subject_tpl' => 'Test',
					'content_tpl' => 'Hello',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$meta = [
			'workflow_rule_id'     => '20',
			'trigger_point'        => 'pf/trigger/registration_created',
			'nodes'                => empty($nodes) ? $default_nodes : $nodes,
			'context_callable_set' => $context_callable_set,
			'results'              => [],
		];

		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => 'WorkflowContextPassing 測試',
				'meta_input'  => \wp_slash($meta),
			]
		);

		if (!is_int($post_id) || $post_id <= 0) {
			throw new \RuntimeException('建立 workflow post 失敗');
		}

		$this->set_post_status_bypass_hooks($post_id, 'running');
		return $post_id;
	}

	/**
	 * Feature: Workflow context 跨節點傳遞
	 * Scenario: context_callable_set 經 serialize/unserialize 後可呼叫
	 *
	 * 驗證 context_callable_set 儲存至 wp_postmeta 再讀取後，仍可正確呼叫。
	 * 這是 Serializable Context Callable 原則的核心驗證。
	 */
	public function test_context_callable_set經serialize_unserialize後可呼叫(): void {
		// Given context_callable_set 使用 TestCallable::return_test_context（可序列化的 static method）
		TestCallable::$test_context = [
			'order_id'       => '888',
			'customer_email' => 'serialize-test@example.com',
		];
		$context_callable_set = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		// When 儲存至 wp_postmeta（wp_insert_post → update_post_meta → wp_unslash 流程）
		$workflow_id = $this->create_workflow_with_context($context_callable_set);

		// When 從 wp_postmeta 讀取並呼叫（模擬 WaitNode 恢復後的情境）
		$stored = \get_post_meta($workflow_id, 'context_callable_set', true);
		$this->assertIsArray($stored, '儲存後讀取的 context_callable_set 應為陣列');

		$callable = $stored['callable'] ?? null;
		$params   = $stored['params'] ?? [];
		$this->assertTrue(\is_callable($callable), '讀取後的 callable 應仍可呼叫');

		$result = \call_user_func_array($callable, $params);
		$this->assertIsArray($result, '呼叫後應回傳陣列');
		$this->assertSame('888', $result['order_id'], '序列化後仍可正確讀取 order_id');
		$this->assertSame('serialize-test@example.com', $result['customer_email'], '序列化後仍可正確讀取 customer_email');

		// Then WorkflowDTO::of() 讀取的 context 也應正確
		$dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertSame('888', $dto->context['order_id'] ?? null, 'WorkflowDTO->context 應包含 order_id');
	}

	/**
	 * Feature: Workflow context 跨節點傳遞
	 * Scenario: 空的 context_callable_set 回傳空陣列
	 *
	 * 驗證空的 context_callable_set 不會造成錯誤，context 應為空陣列。
	 */
	public function test_空的context_callable_set回傳空陣列(): void {
		// Given context_callable_set 為空陣列
		$workflow_id = $this->create_workflow_with_context([]);

		// When 建立 WorkflowDTO
		$dto = WorkflowDTO::of((string) $workflow_id);

		// Then context 應為空陣列
		$this->assertIsArray($dto->context, 'context 應為陣列型別');
		$this->assertEmpty($dto->context, '空的 context_callable_set 應使 context 為空陣列');
	}

	/**
	 * Feature: Workflow context 跨節點傳遞
	 * Scenario: context 解析後正確包含所有 key
	 *
	 * 驗證 context_callable_set 回傳的所有 key 都正確映射至 WorkflowDTO::context。
	 */
	public function test_context解析後正確包含所有key(): void {
		// Given context_callable_set 回傳含多個 key 的陣列
		TestCallable::$test_context = [
			'order_id'       => '999',
			'customer_email' => 'buyer@example.com',
			'order_total'    => '2500',
		];
		$context_callable_set = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		$workflow_id = $this->create_workflow_with_context($context_callable_set);

		// When 建立 WorkflowDTO
		$dto = WorkflowDTO::of((string) $workflow_id);

		// Then context 應包含所有 key
		$this->assertArrayHasKey('order_id', $dto->context, 'context 應包含 order_id');
		$this->assertArrayHasKey('customer_email', $dto->context, 'context 應包含 customer_email');
		$this->assertArrayHasKey('order_total', $dto->context, 'context 應包含 order_total');

		$this->assertSame('999', $dto->context['order_id'], 'context[order_id] 值應正確');
		$this->assertSame('buyer@example.com', $dto->context['customer_email'], 'context[customer_email] 值應正確');
		$this->assertSame('2500', $dto->context['order_total'], 'context[order_total] 值應正確');
	}

	/**
	 * Feature: Workflow context 跨節點傳遞
	 * Scenario: context 中的值可傳給節點使用
	 *
	 * 驗證節點執行時可以讀取 workflow->context 中的值，
	 * 使用 test_context_email 節點（從 context['customer_email'] 取收件人），
	 * 捕捉 wp_mail 的 $to 參數，確認 context 替換成功。
	 */
	public function test_context中的值可傳給節點使用(): void {
		// Given context 包含 customer_email
		TestCallable::$test_context = [
			'customer_email' => 'context-test@example.com',
			'order_total'    => '1200',
		];
		$context_callable_set = [
			'callable' => [ TestCallable::class, 'return_test_context' ],
			'params'   => [],
		];

		$nodes = [
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'test_context_email',
				'params'                => [
					'subject_tpl' => 'Context Email Test',
					'content_tpl' => 'This is a context-driven email',
				],
				'match_callback'        => [TestCallable::class, 'return_true'],
				'match_callback_params' => [],
			],
		];

		$workflow_id = $this->create_workflow_with_context($context_callable_set, $nodes);

		// 捕捉 wp_mail 呼叫的 $to 參數
		$captured_to = null;
		\add_filter(
			'pre_wp_mail',
			static function ( $null, array $atts ) use ( &$captured_to ): bool {
				$captured_to = $atts['to'] ?? null;
				return true; // 攔截並回傳 true，避免實際發送
			},
			10,
			2
		);

		// When 執行 try_execute()
		$workflow_dto = WorkflowDTO::of((string) $workflow_id);
		$workflow_dto->try_execute();

		\remove_all_filters('pre_wp_mail');

		// Then wp_mail 的 $to 參數應為 context 中的 customer_email
		$this->assertSame(
			'context-test@example.com',
			$captured_to,
			'節點應從 context 取得 customer_email 作為收件人'
		);

		// And workflow results 應含有 n1 的成功結果
		\clean_post_cache($workflow_id);
		$updated_dto = WorkflowDTO::of((string) $workflow_id);
		$this->assertNotEmpty($updated_dto->results, '應有執行結果');
		$first_result = $updated_dto->results[0] ?? null;
		$this->assertNotNull($first_result, '應有第一個節點結果');
		$this->assertSame(200, $first_result->code, 'test_context_email 節點應成功（code=200）');
		$this->assertStringContainsString('context-test@example.com', $first_result->message, 'message 應包含收件人地址');
	}
}
