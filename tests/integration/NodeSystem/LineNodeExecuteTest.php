<?php
/**
 * LineNode 傳送 LINE 文字訊息整合測試。
 *
 * 驗證 LineNode::execute() 能正確從 context 取得 line_user_id，
 * 透過 ParamHelper 渲染 content_tpl，並呼叫 MessageService 發送訊息。
 *
 * @group node-system
 * @group line-node
 *
 * @see specs/implement-node-definitions/features/nodes/line-node-execute.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Infrastructure\Line\Services\MessagingApiFactory;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\LineNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;
use J7\PowerFunnel\Tests\Integration\TestCallable;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\PushMessageResponse;

/**
 * LineNode 傳送 LINE 文字訊息測試
 *
 * Feature: LineNode 傳送 LINE 文字訊息
 */
class LineNodeExecuteTest extends IntegrationTestCase {

	/** @var LineNode 被測節點定義 */
	private LineNode $line_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
		\J7\PowerFunnel\Infrastructure\Repositories\Workflow\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		// 重置 MessagingApiFactory 快取，確保每個測試從乾淨狀態開始
		MessagingApiFactory::reset();
		// 重置 MessageService 單例
		$this->reset_message_service_singleton();
		$this->line_node = new LineNode();
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		MessagingApiFactory::reset();
		$this->reset_message_service_singleton();
		parent::tear_down();
	}

	/**
	 * 透過 Reflection 重置 MessageService 單例
	 *
	 * @return void
	 */
	private function reset_message_service_singleton(): void {
		$ref      = new \ReflectionClass( MessageService::class );
		$property = $ref->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * 透過 Reflection 將 mock MessagingApiApi 注入 MessageService
	 *
	 * @param MessagingApiApi $mock_api mock API 物件
	 * @return void
	 */
	private function inject_mock_api( MessagingApiApi $mock_api ): void {
		// 先建立 MessageService 單例（讓 PHP 建立物件結構）
		// 因為 constructor 呼叫 MessagingApiFactory::create()，
		// 我們需要先讓 MessagingApiFactory 有可用的實例，再替換掉 MessageService 的 $api
		$ref_factory      = new \ReflectionClass( MessagingApiFactory::class );
		$factory_instance = $ref_factory->getProperty( 'instance' );
		$factory_instance->setAccessible( true );
		$factory_instance->setValue( null, $mock_api );

		// 強制 MessageService 建立新的單例（此時 MessagingApiFactory::create() 會回傳 mock_api）
		$this->reset_message_service_singleton();
		$service = MessageService::instance();

		// 再替換 MessageService 內部的 $api 屬性為 mock
		$ref_service = new \ReflectionClass( $service );
		$api_prop    = $ref_service->getProperty( 'api' );
		$api_prop->setAccessible( true );
		$api_prop->setValue( $service, $mock_api );
	}

	/**
	 * 建立最小可用的 WorkflowDTO
	 *
	 * @param string               $workflow_id workflow ID（忽略，僅保持 signature 相容）
	 * @param array<string, mixed> $context 工作流程 context
	 * @param array<mixed>         $nodes 節點陣列
	 * @return WorkflowDTO
	 */
	private function make_workflow_dto(
		string $workflow_id = '100',
		array $context = [],
		array $nodes = []
	): WorkflowDTO {
		TestCallable::$test_context = $context;
		$meta                       = [
			'workflow_rule_id'     => '10',
			'trigger_point'        => 'pf/trigger/line_followed',
			'nodes'                => $nodes,
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
				'post_title'  => '測試 LineNode Workflow',
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
				'node_definition_id'    => 'line',
				'params'                => $params,
				'match_callback'        => [ TestCallable::class, 'return_true' ],
				'match_callback_params' => [],
			]
		);
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- 成功發送 LINE 訊息時回傳 code 200
	 * Example: content_tpl 模板替換後發送成功
	 */
	public function test_content_tpl模板替換後發送成功(): void {
		// Given MessageService 的 MessagingApiApi 能成功回傳 PushMessageResponse
		$mock_api = $this->createMock( MessagingApiApi::class );
		$mock_api->method( 'pushMessage' )
			->willReturn( new PushMessageResponse() );
		$this->inject_mock_api( $mock_api );

		// And context 包含 line_user_id 與 identity_id
		$workflow_dto = $this->make_workflow_dto(
			'100',
			[
				'line_user_id' => 'U1234567890abcdef',
				'identity_id'  => 'alice',
			]
		);
		$node_dto     = $this->make_node_dto(
			[ 'content_tpl' => '{{identity_id}} 您好！歡迎加入' ]
		);

		// When 執行 LineNode
		$result = $this->line_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 200，message 包含 "LINE 訊息發送成功"
		$this->assertSame( 200, $result->code, '成功時 code 應為 200' );
		$this->assertStringContainsString( 'LINE 訊息發送成功', $result->message );
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 前置（參數）- context 中缺少 line_user_id 時應失敗
	 * Example: workflow context 中無 line_user_id
	 */
	public function test_workflow_context中無line_user_id(): void {
		// Given workflow context 不含 line_user_id
		$workflow_dto = $this->make_workflow_dto(
			'100',
			[ 'identity_id' => 'alice' ]
		);
		$node_dto     = $this->make_node_dto( [ 'content_tpl' => 'Hello' ] );

		// When 執行 LineNode
		$result = $this->line_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "line_user_id"
		$this->assertSame( 500, $result->code, '缺少 line_user_id 時 code 應為 500' );
		$this->assertStringContainsString( 'line_user_id', $result->message );
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 前置（依賴）- Channel Access Token 未設定時應失敗
	 * Example: MessagingApiFactory::create() 拋出 Exception
	 */
	public function test_MessageService建構失敗(): void {
		// Given MessagingApiFactory 未設定 Channel Access Token（factory 已 reset，無快取）
		// MessagingApiFactory::reset() 已在 set_up 中呼叫
		// 確保 MessageService 單例也被重置（set_up 中已處理）

		// And context 有合法的 line_user_id
		$workflow_dto = $this->make_workflow_dto(
			'100',
			[ 'line_user_id' => 'U123' ]
		);
		$node_dto     = $this->make_node_dto( [ 'content_tpl' => 'Hello' ] );

		// When 執行 LineNode（MessageService::instance() 會呼叫 MessagingApiFactory::create()，
		// 但 SettingDTO 無 Channel Access Token，所以拋出 Exception）
		$result = $this->line_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "Channel Access Token"
		$this->assertSame( 500, $result->code, 'Token 未設定時 code 應為 500' );
		$this->assertStringContainsString( 'Channel Access Token', $result->message );
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- MessageService 拋出例外時回傳 code 500
	 * Example: LINE API 回傳錯誤
	 */
	public function test_LINE_API回傳錯誤(): void {
		// Given MessagingApiApi::pushMessage() 拋出 Exception("LINE API error")
		$mock_api = $this->createMock( MessagingApiApi::class );
		$mock_api->method( 'pushMessage' )
			->willThrowException( new \Exception( 'LINE API error' ) );
		$this->inject_mock_api( $mock_api );

		$workflow_dto = $this->make_workflow_dto(
			'100',
			[ 'line_user_id' => 'U123' ]
		);
		$node_dto     = $this->make_node_dto( [ 'content_tpl' => 'Hello' ] );

		// When 執行 LineNode
		$result = $this->line_node->execute( $node_dto, $workflow_dto );

		// Then 結果的 code 應為 500，message 包含 "LINE API error"
		$this->assertSame( 500, $result->code, 'API 錯誤時 code 應為 500' );
		$this->assertStringContainsString( 'LINE API error', $result->message );
	}

	/**
	 * Feature: LineNode 傳送 LINE 文字訊息
	 * Rule: 後置（狀態）- content_tpl 支援 {{variable}} 模板替換
	 * Example: 多個模板變數替換
	 */
	public function test_多個模板變數替換(): void {
		// Given MessagingApiApi 能成功發送（記錄發送內容）
		$sent_text = '';
		$mock_api  = $this->createMock( MessagingApiApi::class );
		$mock_api->method( 'pushMessage' )
			->willReturnCallback(
				static function ( $request ) use ( &$sent_text ): PushMessageResponse {
					/** @var \LINE\Clients\MessagingApi\Model\PushMessageRequest $request */
					$messages  = $request->getMessages();
					$sent_text = ( $messages[0] instanceof \LINE\Clients\MessagingApi\Model\TextMessage )
						? (string) $messages[0]->getText()
						: '';
					return new PushMessageResponse();
				}
			);
		$this->inject_mock_api( $mock_api );

		// And context 含多個變數
		$workflow_dto = $this->make_workflow_dto(
			'100',
			[
				'line_user_id' => 'U123',
				'identity_id'  => 'Bob',
				'activity_id'  => 'A99',
			]
		);
		$node_dto     = $this->make_node_dto(
			[ 'content_tpl' => '{{identity_id}} 報名活動 {{activity_id}} 成功' ]
		);

		// When 執行 LineNode
		$result = $this->line_node->execute( $node_dto, $workflow_dto );

		// Then 發送的訊息應已替換變數
		$this->assertSame( 200, $result->code );
		$this->assertSame( 'Bob 報名活動 A99 成功', $sent_text, '模板變數應正確替換' );
	}
}
