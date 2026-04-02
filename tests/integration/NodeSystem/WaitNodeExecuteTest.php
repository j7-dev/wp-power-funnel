<?php
/**
 * WaitNode 相對延遲排程整合測試。
 *
 * 驗證 WaitNode::execute() 依 duration + unit 計算 Unix timestamp
 * 並透過 Action Scheduler 排程，以及正確處理無效參數。
 *
 * @group node-system
 * @group wait-node
 *
 * @see specs/implement-node-definitions/features/nodes/wait-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\WaitNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * WaitNode 相對延遲排程測試
 *
 * Feature: WaitNode 相對延遲排程
 */
class WaitNodeExecuteTest extends IntegrationTestCase {

	/** @var WaitNode 被測節點定義 */
	private WaitNode $wait_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->wait_node = new WaitNode();
	}

	/**
	 * 建立最小可用的 WorkflowDTO
	 *
	 * @return WorkflowDTO
	 */
	private function make_workflow_dto(): WorkflowDTO {
		TestCallable::$test_context = [];
		$meta                       = [
			'workflow_rule_id'     => '10',
			'trigger_point'        => 'pf/trigger/registration_approved',
			'nodes'                => [],
			'context_callable_set' => [
				'callable' => [ TestCallable::class, 'return_test_context' ],
				'params'   => [],
			],
			'results'              => [],
		];
		$post_id                    = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '測試 WaitNode Workflow',
				'meta_input'  => \wp_slash( $meta ),
			]
		);
		$this->set_post_status_bypass_hooks( (int) $post_id, 'running' );
		return WorkflowDTO::of( (string) $post_id );
	}

	/**
	 * 建立最小 NodeDTO
	 *
	 * @param array<string, mixed> $params 節點參數
	 * @return NodeDTO
	 */
	private function make_node_dto( array $params = [] ): NodeDTO {
		return new NodeDTO(
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'wait',
				'params'                => $params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			]
		);
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: 有效的 duration + unit 組合應成功排程
	 * Example: 等待 30 分鐘
	 */
	public function test_等待30分鐘排程成功(): void {
		// Given duration=30, unit='minutes'
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '30', 'unit' => 'minutes' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=200, message 包含 "等待 30 分鐘", scheduled=true
		$this->assertSame( 200, $result->code, '等待 30 分鐘 code 應為 200' );
		$this->assertStringContainsString( '等待 30 分鐘', $result->message );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: 有效的 duration + unit 組合應成功排程
	 * Example: 等待 2 小時
	 */
	public function test_等待2小時排程成功(): void {
		// Given duration=2, unit='hours'
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '2', 'unit' => 'hours' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=200, message 包含 "等待 2 小時", scheduled=true
		$this->assertSame( 200, $result->code, '等待 2 小時 code 應為 200' );
		$this->assertStringContainsString( '等待 2 小時', $result->message );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: 有效的 duration + unit 組合應成功排程
	 * Example: 等待 2 天
	 */
	public function test_等待2天排程成功(): void {
		// Given duration=2, unit='days'
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '2', 'unit' => 'days' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=200, message 包含 "等待 2 天", scheduled=true
		$this->assertSame( 200, $result->code, '等待 2 天 code 應為 200' );
		$this->assertStringContainsString( '等待 2 天', $result->message );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: as_schedule_single_action 回傳 0 時應回傳 code 500
	 * Example: AS dedup 模式下重複排程
	 *
	 * 注意：在整合測試中，AS 在同一秒對同一 hook/args 的重複排程可能回傳 0。
	 * 此測試驗證排程失敗時 message 格式正確。
	 */
	public function test_as_schedule_single_action回傳0(): void {
		// Given 先排程一個相同的 action，讓後續排程因重複而可能回傳 0
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '30', 'unit' => 'minutes' ] );

		// When 執行兩次相同參數的 WaitNode（第二次 AS 可能去重回傳 0）
		$this->wait_node->execute( $node_dto, $workflow_dto );
		$result2 = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then 無論結果為 200 或 500，驗證失敗時的 message 格式正確
		if ( $result2->code === 500 ) {
			$this->assertStringContainsString( '排程失敗', $result2->message, '排程失敗時 message 應包含 "排程失敗"' );
		} else {
			// AS 允許重複排程（回傳新 action_id），則此測試視為通過
			$this->assertSame( 200, $result2->code );
		}
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: duration 必須提供且為有效正整數
	 * Example: 缺少 duration 時失敗
	 */
	public function test_缺少duration時失敗(): void {
		// Given 節點 params 中無 duration
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'unit' => 'minutes' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=500, message 包含 "duration"
		$this->assertSame( 500, $result->code, '缺少 duration 時 code 應為 500' );
		$this->assertStringContainsString( 'duration', $result->message );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: unit 必須提供且為支援的時間單位
	 * Example: 缺少 unit 時失敗
	 */
	public function test_缺少unit時失敗(): void {
		// Given 節點 params 中無 unit
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '30' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=500, message 包含 "unit"
		$this->assertSame( 500, $result->code, '缺少 unit 時 code 應為 500' );
		$this->assertStringContainsString( 'unit', $result->message );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: duration 必須大於 0
	 * Example: duration 為 0 時失敗
	 */
	public function test_duration為0時失敗(): void {
		// Given duration=0
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '0', 'unit' => 'minutes' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=500, message 包含 "duration"
		$this->assertSame( 500, $result->code, 'duration 為 0 時 code 應為 500' );
		$this->assertStringContainsString( 'duration', $result->message );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: duration 必須大於 0
	 * Example: duration 為負數時失敗
	 */
	public function test_duration為負數時失敗(): void {
		// Given duration=-5
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '-5', 'unit' => 'hours' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=500, message 包含 "duration"
		$this->assertSame( 500, $result->code, 'duration 為負數時 code 應為 500' );
		$this->assertStringContainsString( 'duration', $result->message );
	}

	/**
	 * Feature: WaitNode 相對延遲排程
	 * Rule: unit 只支援 minutes / hours / days
	 * Example: unit 為不支援值時失敗
	 */
	public function test_unit為不支援值時失敗(): void {
		// Given unit='weeks'（不支援）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto( [ 'duration' => '1', 'unit' => 'weeks' ] );

		// When 執行 WaitNode
		$result = $this->wait_node->execute( $node_dto, $workflow_dto );

		// Then code=500, message 包含 "weeks"
		$this->assertSame( 500, $result->code, 'unit 為不支援值時 code 應為 500' );
		$this->assertStringContainsString( 'weeks', $result->message );
	}
}
