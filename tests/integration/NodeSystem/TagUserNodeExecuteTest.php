<?php
/**
 * TagUserNode 新增/移除用戶標籤整合測試。
 *
 * 驗證 TagUserNode::execute() 能正確操作 pf_user_tags_{line_user_id} wp_options，
 * 新增時觸發 fire_user_tagged，移除時不觸發，並處理各種錯誤情境。
 *
 * @group node-system
 * @group tag-user-node
 *
 * @see specs/implement-node-definitions/features/nodes/tag-user-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\TagUserNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * TagUserNode 新增/移除用戶標籤測試
 *
 * Feature: TagUserNode 新增/移除用戶標籤
 */
class TagUserNodeExecuteTest extends IntegrationTestCase {

	/** @var TagUserNode 被測節點定義 */
	private TagUserNode $tag_user_node;

	/** @var string 測試用 LINE user ID */
	private string $line_user_id = 'U123';

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->tag_user_node = new TagUserNode();
		// 清除測試用的 option（每個測試重新開始）
		\delete_option( "pf_user_tags_{$this->line_user_id}" );
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\delete_option( "pf_user_tags_{$this->line_user_id}" );
		\remove_all_filters( 'power_funnel/sms/send' );
		parent::tear_down();
	}

	/**
	 * 設定用戶的初始標籤
	 *
	 * @param string[] $tags 標籤陣列
	 * @return void
	 */
	private function set_user_tags( array $tags ): void {
		\update_option( "pf_user_tags_{$this->line_user_id}", \wp_json_encode( $tags ) );
	}

	/**
	 * 讀取用戶目前的標籤
	 *
	 * @return string[]
	 */
	private function get_user_tags(): array {
		$raw = \get_option( "pf_user_tags_{$this->line_user_id}", '[]' );
		$arr = \json_decode( \is_string( $raw ) ? $raw : '[]', true );
		return \is_array( $arr ) ? \array_values( $arr ) : [];
	}

	/**
	 * 建立最小可用的 WorkflowDTO
	 *
	 * @param array<string, mixed> $context 工作流程 context
	 * @return WorkflowDTO
	 */
	private function make_workflow_dto( array $context = [] ): WorkflowDTO {
		$default_context            = [
			'line_user_id' => $this->line_user_id,
			'identity_id'  => 'alice',
		];
		$context                    = \wp_parse_args( $context, $default_context );
		TestCallable::$test_context = $context;

		$meta    = [
			'workflow_rule_id'     => '10',
			'trigger_point'        => 'pf/trigger/registration_approved',
			'nodes'                => [],
			'context_callable_set' => [
				'callable' => [ TestCallable::class, 'return_test_context' ],
				'params'   => [],
			],
			'results'              => [],
		];
		$post_id = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '測試 TagUserNode Workflow',
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
		$default_params = [
			'tags'   => [ 'vip', 'premium' ],
			'action' => 'add',
		];
		$params         = \wp_parse_args( $params, $default_params );
		return new NodeDTO(
			[
				'id'                    => 'n1',
				'node_definition_id'    => 'tag_user',
				'params'                => $params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			]
		);
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（狀態）- action=add 時新增標籤到 wp_options
	 * Example: 新增標籤到無標籤的用戶
	 */
	public function test_新增標籤到無標籤的用戶(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為空（無 option 記錄）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，message 包含 "標籤新增成功"
		$this->assertSame( 200, $result->code, '新增成功時 code 應為 200' );
		$this->assertStringContainsString( '標籤新增成功', $result->message );

		// And 用戶的標籤應為 ["vip","premium"]
		$actual_tags = $this->get_user_tags();
		$this->assertContains( 'vip', $actual_tags );
		$this->assertContains( 'premium', $actual_tags );
		$this->assertCount( 2, $actual_tags );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（狀態）- action=add 時新增標籤到 wp_options
	 * Example: 新增標籤到已有標籤的用戶（不重複）
	 */
	public function test_新增標籤到已有標籤的用戶不重複(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為 ["existing","vip"]
		$this->set_user_tags( [ 'existing', 'vip' ] );

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 用戶 "U123" 的 pf_user_tags 應為 ["existing","vip","premium"]
		$this->assertSame( 200, $result->code );
		$actual_tags = $this->get_user_tags();
		$this->assertContains( 'existing', $actual_tags );
		$this->assertContains( 'vip', $actual_tags );
		$this->assertContains( 'premium', $actual_tags );

		// And 不應包含重複的 "vip"
		$this->assertCount( 3, $actual_tags, '不應有重複標籤' );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（副作用）- action=add 時對每個新標籤觸發 fire_user_tagged
	 * Example: 新增 2 個標籤應觸發 2 次 fire_user_tagged
	 */
	public function test_新增2個標籤應觸發2次fire_user_tagged(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為空
		$fired_events = [];
		\add_action(
			'pf/trigger/user_tagged',
			static function ( $context_callable_set ) use ( &$fired_events ): void {
				// TriggerPointService::fire_user_tagged 呼叫 do_action('pf/trigger/user_tagged', $callable_set)
				// callable_set 中 params = [$line_user_id, $tag_name]
				$params         = $context_callable_set['params'] ?? [];
				$fired_events[] = [
					'user_id'  => $params[0] ?? '',
					'tag_name' => $params[1] ?? '',
				];
			},
			10,
			1
		);

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 應觸發 2 次 pf/trigger/user_tagged
		$this->assertSame( 200, $result->code );
		$this->assertCount( 2, $fired_events, '應觸發 2 次 fire_user_tagged' );

		$tag_names = \array_column( $fired_events, 'tag_name' );
		$this->assertContains( 'vip', $tag_names, '應對 vip 觸發' );
		$this->assertContains( 'premium', $tag_names, '應對 premium 觸發' );

		$user_ids = \array_unique( \array_column( $fired_events, 'user_id' ) );
		$this->assertSame( [ $this->line_user_id ], $user_ids, 'user_id 應為正確的 LINE user ID' );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（副作用）- action=add 時對每個新標籤觸發 fire_user_tagged
	 * Example: 標籤已存在時不重複觸發
	 */
	public function test_標籤已存在時不重複觸發(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為 ["vip"]
		$this->set_user_tags( [ 'vip' ] );

		$fired_events = [];
		\add_action(
			'pf/trigger/user_tagged',
			static function ( $context_callable_set ) use ( &$fired_events ): void {
				$params         = $context_callable_set['params'] ?? [];
				$fired_events[] = [
					'user_id'  => $params[0] ?? '',
					'tag_name' => $params[1] ?? '',
				];
			},
			10,
			1
		);

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode（vip 已存在，premium 是新的）
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 只應對 "premium" 觸發（"vip" 已存在不重複觸發）
		$this->assertSame( 200, $result->code );
		$this->assertCount( 1, $fired_events, '只應觸發 1 次（只有新標籤才觸發）' );

		$tag_names = \array_column( $fired_events, 'tag_name' );
		$this->assertContains( 'premium', $tag_names, '應對 premium 觸發' );
		$this->assertNotContains( 'vip', $tag_names, '不應對已存在的 vip 觸發' );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（狀態）- action=remove 時移除標籤
	 * Example: 移除用戶的標籤
	 */
	public function test_移除用戶的標籤(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為 ["vip","premium","regular"]
		$this->set_user_tags( [ 'vip', 'premium', 'regular' ] );

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'remove',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 用戶 "U123" 的 pf_user_tags 應為 ["regular"]
		$this->assertSame( 200, $result->code, '移除成功時 code 應為 200' );
		$this->assertStringContainsString( '標籤移除成功', $result->message );

		$actual_tags = $this->get_user_tags();
		$this->assertContains( 'regular', $actual_tags );
		$this->assertNotContains( 'vip', $actual_tags );
		$this->assertNotContains( 'premium', $actual_tags );
		$this->assertCount( 1, $actual_tags );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（狀態）- action=remove 時移除標籤
	 * Example: 移除不存在的標籤不報錯
	 */
	public function test_移除不存在的標籤不報錯(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為 ["other"]
		$this->set_user_tags( [ 'other' ] );

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'remove',
			]
		);

		// When 執行 TagUserNode（vip/premium 不在標籤中）
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 不報錯，code 仍為 200
		$this->assertSame( 200, $result->code, '移除不存在標籤時也應成功' );

		// And 用戶的標籤應維持 ["other"]
		$actual_tags = $this->get_user_tags();
		$this->assertContains( 'other', $actual_tags );
		$this->assertCount( 1, $actual_tags );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 後置（副作用）- action=remove 時不觸發 fire_user_tagged
	 * Example: 移除標籤不觸發事件
	 */
	public function test_移除標籤不觸發事件(): void {
		// Given 用戶 "U123" 的 pf_user_tags 為 ["vip","premium"]
		$this->set_user_tags( [ 'vip', 'premium' ] );

		$fired_count = 0;
		\add_action(
			'pf/trigger/user_tagged',
			static function () use ( &$fired_count ): void {
				++$fired_count;
			}
		);

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip', 'premium' ],
				'action' => 'remove',
			]
		);

		// When 執行 TagUserNode（action=remove）
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 不應觸發 pf/trigger/user_tagged
		$this->assertSame( 200, $result->code );
		$this->assertSame( 0, $fired_count, '移除標籤不應觸發 fire_user_tagged' );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 前置（參數）- tags 必須為非空陣列
	 * Example: tags 為空陣列時失敗
	 */
	public function test_tags為空陣列時失敗(): void {
		// Given 節點 params 中 tags 為空陣列
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "tags"
		$this->assertSame( 500, $result->code, '空 tags 時 code 應為 500' );
		$this->assertStringContainsString( 'tags', $result->message );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 前置（參數）- action 必須為 add 或 remove
	 * Example: action 為無效值時失敗
	 */
	public function test_action為無效值時失敗(): void {
		// Given 節點 params 中 action 為 "invalid"
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'tags'   => [ 'vip' ],
				'action' => 'invalid',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "action"
		$this->assertSame( 500, $result->code, '無效 action 時 code 應為 500' );
		$this->assertStringContainsString( 'action', $result->message );
	}

	/**
	 * Feature: TagUserNode 新增/移除用戶標籤
	 * Rule: 前置（參數）- context 中必須有可識別的 user_id
	 * Example: context 中無 line_user_id 時失敗
	 */
	public function test_context中無line_user_id時失敗(): void {
		// Given workflow context 不含 line_user_id
		TestCallable::$test_context = [ 'identity_id' => 'alice' ];
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
		$workflow_post_id           = \wp_insert_post(
			[
				'post_type'   => 'pf_workflow',
				'post_status' => 'draft',
				'post_title'  => '測試無 line_user_id Workflow',
				'meta_input'  => \wp_slash( $meta ),
			]
		);
		$this->set_post_status_bypass_hooks( (int) $workflow_post_id, 'running' );
		$workflow_dto = WorkflowDTO::of( (string) $workflow_post_id );

		$node_dto = $this->make_node_dto(
			[
				'tags'   => [ 'vip' ],
				'action' => 'add',
			]
		);

		// When 執行 TagUserNode
		$result = $this->tag_user_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "user_id"
		$this->assertSame( 500, $result->code, '缺少 line_user_id 時 code 應為 500' );
		$this->assertStringContainsString( 'user_id', $result->message );
	}
}
