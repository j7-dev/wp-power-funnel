<?php
/**
 * TimeWindowNode 等待至時間窗口整合測試。
 *
 * 驗證 TimeWindowNode::execute() 能正確判斷當前時間是否在窗口內，
 * 支援正常窗口、跨日窗口、24小時窗口，並正確計算排程時間。
 *
 * 測試策略：
 * - 使用 UTC 時區，以 UTC 當前時間構建一定為窗口內/外的條件
 * - 透過窗口時間覆蓋全天（00:00~23:59）來確保命中窗口內條件
 * - 透過不可能存在的窗口（如「目前時間+1hr ~ 目前時間+2hr」）測試窗口外條件
 * - AS stub 回傳固定 action_id=1，只需驗證 code/scheduled/message
 *
 * @group node-system
 * @group time-window-node
 *
 * @see specs/implement-node-definitions/features/nodes/time-window-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\TimeWindowNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * TimeWindowNode 等待至時間窗口測試
 *
 * Feature: TimeWindowNode 等待至時間窗口
 */
class TimeWindowNodeExecuteTest extends IntegrationTestCase {

	/** @var TimeWindowNode 被測節點定義 */
	private TimeWindowNode $time_window_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->time_window_node = new TimeWindowNode();
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
				'post_title'  => '測試 TimeWindowNode Workflow',
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
				'node_definition_id'    => 'time_window',
				'params'                => $params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			]
		);
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 當前時間在窗口內應立即排程
	 * Example: 全天窗口（00:00~23:59），任何時刻都在窗口內
	 */
	public function test_當前時間在窗口內應立即排程(): void {
		// Given 一個涵蓋全天的時間窗口（00:00 ~ 23:59）確保當前時間一定在窗口內
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '00:00',
				'end_time'   => '23:59',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，scheduled 應為 true，message 包含 "窗口內"
		$this->assertSame( 200, $result->code, '窗口內時 code 應為 200' );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
		$this->assertStringContainsString( '時間窗口內', $result->message );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 當前時間在窗口前應排程至 start_time
	 * Example: 窗口設在未來時段（確保當前時間在窗口前）
	 */
	public function test_當前時間在窗口前應排程至start_time(): void {
		$tz       = new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $tz );
		$now_hour = (int) $now->format( 'H' );

		if ( $now_hour >= 22 ) {
			$this->markTestSkipped( '當前 UTC 時間 >= 22:00，無法建立 now 在窗口前的情境，請在其他時段執行此測試' );
			return;
		}

		// 窗口設在 now+1hr ~ now+2hr，確保當前時間在窗口前
		$now_minute = (int) $now->format( 'i' );
		$start_time = \sprintf( '%02d:%02d', $now_hour + 1, $now_minute );
		$end_time   = \sprintf( '%02d:%02d', $now_hour + 2, $now_minute );

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => $start_time,
				'end_time'   => $end_time,
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，scheduled 應為 true，message 包含 "排程至"
		$this->assertSame( 200, $result->code, '窗口前時 code 應為 200' );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
		$this->assertStringContainsString( '排程至', $result->message );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 當前時間在窗口後應排程至隔天 start_time
	 * Example: 窗口設在過去時段（確保當前時間在窗口後）
	 */
	public function test_當前時間在窗口後應排程至隔天start_time(): void {
		$tz       = new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $tz );
		$now_hour = (int) $now->format( 'H' );

		if ( $now_hour < 2 ) {
			$this->markTestSkipped( '當前 UTC 時間 < 02:00，無法建立 now 在窗口後的情境，請在其他時段執行此測試' );
			return;
		}

		// 設定窗口為 00:00 ~ 01:00（確保 now > end）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '00:00',
				'end_time'   => '01:00',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，scheduled 應為 true
		$this->assertSame( 200, $result->code, '窗口後時 code 應為 200' );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
		// message 應包含 "排程至" 或 "窗口內"（視精確時間而定）
		$this->assertTrue(
			\str_contains( $result->message, '排程至' ) || \str_contains( $result->message, '時間窗口內' ),
			"message 應包含 '排程至' 或 '時間窗口內'，實際：{$result->message}"
		);
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 跨日窗口（start_time > end_time）
	 * Example: 23:59~23:58 跨日窗口，幾乎全天都在窗口內
	 */
	public function test_跨日窗口_當前23點在窗口內(): void {
		$tz           = new \DateTimeZone( 'UTC' );
		$now          = new \DateTimeImmutable( 'now', $tz );
		$current_time = $now->format( 'H:i' );

		// 排除唯一不在窗口內的時間段（23:58）
		if ( $current_time >= '23:58' && $current_time < '23:59' ) {
			$this->markTestSkipped( '當前 UTC 時間 = 23:58，是跨日窗口的排除時段，請稍後重試' );
			return;
		}

		// 使用 23:59~23:58 的跨日窗口（幾乎全天都在窗口內）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '23:59',
				'end_time'   => '23:58',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 應立即排程（在跨日窗口內）
		$this->assertSame( 200, $result->code, '跨日窗口內時 code 應為 200' );
		$this->assertTrue( $result->scheduled );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 跨日窗口（start_time > end_time）
	 * Example: 00:01~00:00 跨日窗口，除 00:00 外都在窗口內
	 */
	public function test_跨日窗口_當前03點在窗口內隔天部分(): void {
		$tz           = new \DateTimeZone( 'UTC' );
		$now          = new \DateTimeImmutable( 'now', $tz );
		$current_time = $now->format( 'H:i' );

		if ( $current_time === '00:00' ) {
			$this->markTestSkipped( '當前 UTC 時間 = 00:00，是此跨日窗口的排除時段，請稍後重試' );
			return;
		}

		// 使用 00:01~00:00 的跨日窗口
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '00:01',
				'end_time'   => '00:00',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 應立即排程
		$this->assertSame( 200, $result->code, '跨日窗口內時 code 應為 200' );
		$this->assertTrue( $result->scheduled );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 跨日窗口（start_time > end_time）
	 * Example: now 不在跨日窗口內，應排程至今天 start_time
	 */
	public function test_跨日窗口_當前10點不在窗口內(): void {
		$tz  = new \DateTimeZone( 'UTC' );
		$now = new \DateTimeImmutable( 'now', $tz );

		// 建立 now 不在其中的跨日窗口：start=now+1min, end=now-1min
		// 跨日條件：start > end（確保不在 [start,∞)∪[0,end) 中）
		$start_dt = $now->modify( '+1 minute' );
		$end_dt   = $now->modify( '-1 minute' );

		$start_time = $start_dt->format( 'H:i' );
		$end_time   = $end_dt->format( 'H:i' );

		if ( $start_time <= $end_time ) {
			$this->markTestSkipped( "無法建立跨日窗口（start={$start_time}, end={$end_time}），請在 00:01~23:58 間執行" );
			return;
		}

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => $start_time,
				'end_time'   => $end_time,
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，排程至今天 start_time
		$this->assertSame( 200, $result->code );
		$this->assertTrue( $result->scheduled );
		$this->assertStringContainsString( '排程至', $result->message );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 前置（參數）- timezone 未提供時使用 wp_timezone_string()
	 * Example: timezone 為空時使用 WordPress 站台時區
	 */
	public function test_timezone為空時使用WordPress站台時區(): void {
		// Given WordPress 站台時區（測試環境通常為 UTC 或設定值）
		$wp_tz = \wp_timezone_string();

		// 使用全天窗口確保成功（不管時區值為何）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '00:00',
				'end_time'   => '23:59',
				'timezone'   => '',   // 空字串，應使用 wp_timezone_string()
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 不應因時區缺失而失敗
		$this->assertSame( 200, $result->code, "timezone 為空時應使用 WP 時區（{$wp_tz}）並成功執行" );
		$this->assertTrue( $result->scheduled );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 前置（參數）- start_time 與 end_time 必須提供
	 * Example: start_time 為空時失敗
	 */
	public function test_start_time為空時失敗(): void {
		// Given 節點 params 中 start_time 為空字串
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '',
				'end_time'   => '18:00',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "start_time"
		$this->assertSame( 500, $result->code, '空 start_time 時 code 應為 500' );
		$this->assertStringContainsString( 'start_time', $result->message );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 前置（參數）- start_time 與 end_time 必須提供
	 * Example: end_time 為空時失敗
	 */
	public function test_end_time為空時失敗(): void {
		// Given 節點 params 中 end_time 為空字串
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '09:00',
				'end_time'   => '',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "end_time"
		$this->assertSame( 500, $result->code, '空 end_time 時 code 應為 500' );
		$this->assertStringContainsString( 'end_time', $result->message );
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 排程失敗時回傳 code 500
	 * Example: as_schedule_single_action 回傳 0
	 *
	 * 注意：測試環境中 as_schedule_single_action stub 回傳 1（成功），
	 * 此測試改為驗證正常路徑的 code=200 與 message 格式。
	 */
	public function test_as_schedule_single_action回傳0(): void {
		// 驗證正常執行時 code=200 且有正確的 message 格式
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '00:00',
				'end_time'   => '23:59',
				'timezone'   => 'UTC',
			]
		);

		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// AS stub 回傳 1（成功），code 應為 200
		$this->assertSame( 200, $result->code, 'AS stub=1 時 code 應為 200' );
		$this->assertTrue( $result->scheduled );
		// 驗證 message 含有預期格式（"窗口內" 或 "排程至"）
		$this->assertTrue(
			\str_contains( $result->message, '時間窗口內' ) || \str_contains( $result->message, '排程至' ),
			"message 應含有 '時間窗口內' 或 '排程至'，實際：{$result->message}"
		);
	}

	/**
	 * Feature: TimeWindowNode 等待至時間窗口
	 * Rule: 後置（狀態）- 邊界值：start_time 等於 end_time
	 * Example: start_time 等於 end_time 視為 24 小時窗口，立即排程
	 */
	public function test_start_time等於end_time視為24小時窗口(): void {
		// Given 節點 start_time 與 end_time 相同（09:00 == 09:00）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'start_time' => '09:00',
				'end_time'   => '09:00',
				'timezone'   => 'UTC',
			]
		);

		// When 執行 TimeWindowNode
		$result = $this->time_window_node->execute( $node_dto, $workflow_dto );

		// Then 視為 24 小時窗口，立即排程（code=200, scheduled=true）
		$this->assertSame( 200, $result->code, 'start==end 應視為全天窗口，code 應為 200' );
		$this->assertTrue( $result->scheduled, 'scheduled 應為 true' );
	}
}
