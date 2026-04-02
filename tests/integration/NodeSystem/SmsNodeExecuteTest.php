<?php
/**
 * SmsNode 傳送 SMS 簡訊整合測試。
 *
 * 驗證 SmsNode::execute() 透過 WordPress filter 委派 SMS 發送，
 * 並正確處理 filter 回傳的 success/failure 結果。
 *
 * @group node-system
 * @group sms-node
 *
 * @see specs/implement-node-definitions/features/nodes/sms-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\SmsNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;

/**
 * SmsNode 傳送 SMS 簡訊測試
 *
 * Feature: SmsNode 傳送 SMS 簡訊
 */
class SmsNodeExecuteTest extends IntegrationTestCase {

	/** @var SmsNode 被測節點定義 */
	private SmsNode $sms_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->sms_node = new SmsNode();
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		// 移除測試掛載的 filter
		\remove_all_filters( 'power_funnel/sms/send' );
		parent::tear_down();
	}

	/**
	 * 建立最小可用的 WorkflowDTO
	 *
	 * @param array<string, mixed> $context 工作流程 context
	 * @return WorkflowDTO
	 */
	private function make_workflow_dto( array $context = [] ): WorkflowDTO {
		TestCallable::$test_context = $context;
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
				'post_title'  => '測試 SmsNode Workflow',
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
				'node_definition_id'    => 'sms',
				'params'                => $params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			]
		);
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=true 時回傳 code 200
	 * Example: SMS 發送成功
	 */
	public function test_SMS發送成功(): void {
		// Given power_funnel/sms/send filter 掛載，回傳 success=true
		$captured_recipient = '';
		$captured_content   = '';
		\add_filter(
			'power_funnel/sms/send',
			static function ( $default, string $recipient, string $content ) use ( &$captured_recipient, &$captured_content ): array {
				$captured_recipient = $recipient;
				$captured_content   = $content;
				return [
					'success' => true,
					'message' => 'SMS 發送成功',
				];
			},
			10,
			3
		);

		$workflow_dto = $this->make_workflow_dto(
			[
				'identity_id'   => 'alice',
				'billing_phone' => '+886912345678',
			]
		);
		$node_dto     = $this->make_node_dto(
			[
				'recipient'   => '{{billing_phone}}',
				'content_tpl' => '{{identity_id}} 您好',
			]
		);

		// When 執行 SmsNode
		$result = $this->sms_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200
		$this->assertSame( 200, $result->code, '成功時 code 應為 200' );
		$this->assertSame( 'SMS 發送成功', $result->message );
		$this->assertSame( '+886912345678', $captured_recipient, 'recipient 應為替換後的電話號碼' );
		$this->assertSame( 'alice 您好', $captured_content, 'content 應為替換後的內容' );
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=false 時回傳 code 500
	 * Example: SMS 發送失敗（無 filter 掛載，使用預設值）
	 */
	public function test_SMS發送失敗_無filter掛載使用預設值(): void {
		// Given 無 filter 掛載（使用預設值 success=false）
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'recipient'   => '+886912345678',
				'content_tpl' => '測試訊息',
			]
		);

		// When 執行 SmsNode
		$result = $this->sms_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 為預設錯誤訊息
		$this->assertSame( 500, $result->code, '無 filter 掛載時 code 應為 500' );
		$this->assertSame( 'SMS 發送失敗', $result->message );
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- filter 回傳 success=false 時回傳 code 500
	 * Example: SMS 服務回傳失敗
	 */
	public function test_SMS服務回傳失敗(): void {
		// Given power_funnel/sms/send filter 掛載，回傳 success=false
		\add_filter(
			'power_funnel/sms/send',
			static function (): array {
				return [
					'success' => false,
					'message' => '餘額不足',
				];
			}
		);

		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'recipient'   => '+886912345678',
				'content_tpl' => '測試訊息',
			]
		);

		// When 執行 SmsNode
		$result = $this->sms_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 為 "餘額不足"
		$this->assertSame( 500, $result->code, 'filter 回傳 failure 時 code 應為 500' );
		$this->assertSame( '餘額不足', $result->message );
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 前置（參數）- recipient 必須提供
	 * Example: recipient 為空時失敗
	 */
	public function test_recipient為空時失敗(): void {
		// Given 節點 params 中 recipient 為空字串
		$workflow_dto = $this->make_workflow_dto();
		$node_dto     = $this->make_node_dto(
			[
				'recipient'   => '',
				'content_tpl' => '測試訊息',
			]
		);

		// When 執行 SmsNode
		$result = $this->sms_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "recipient"
		$this->assertSame( 500, $result->code, '空 recipient 時 code 應為 500' );
		$this->assertStringContainsString( 'recipient', $result->message );
	}

	/**
	 * Feature: SmsNode 傳送 SMS 簡訊
	 * Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換
	 * Example: recipient 也支援模板替換
	 */
	public function test_recipient也支援模板替換(): void {
		// Given filter 成功，記錄傳入的 recipient 和 content 值
		$captured_recipient = '';
		$captured_content   = '';
		\add_filter(
			'power_funnel/sms/send',
			static function ( $default, string $recipient, string $content ) use ( &$captured_recipient, &$captured_content ): array {
				$captured_recipient = $recipient;
				$captured_content   = $content;
				return [
					'success' => true,
					'message' => 'OK',
				];
			},
			10,
			3
		);

		$workflow_dto = $this->make_workflow_dto(
			[
				'identity_id'   => 'Bob',
				'billing_phone' => '+886999888777',
			]
		);
		$node_dto     = $this->make_node_dto(
			[
				'recipient'   => '{{billing_phone}}',
				'content_tpl' => '{{identity_id}} 提醒',
			]
		);

		// When 執行 SmsNode
		$result = $this->sms_node->execute( $node_dto, $workflow_dto );

		// Then apply_filters 的 recipient 應為替換後的值
		$this->assertSame( 200, $result->code );
		$this->assertSame( '+886999888777', $captured_recipient, 'recipient 應為替換後的電話號碼' );
		$this->assertSame( 'Bob 提醒', $captured_content, 'content 應為替換後的訊息' );
	}
}
