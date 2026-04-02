<?php

/**
 * CUSTOMER_REGISTERED 觸發點觸發整合測試。
 *
 * 驗證 WordPress 新用戶註冊時（user_register hook），
 * 系統正確觸發 pf/trigger/customer_registered 並傳遞正確的 context_callable_set。
 *
 * @group trigger-points
 * @group customer-trigger
 * @group customer-trigger-fire
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/fire-customer-registered.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * CUSTOMER_REGISTERED 觸發點觸發測試
 *
 * Feature: 觸發 CUSTOMER_REGISTERED 觸發點
 */
class CustomerTriggerFireTest extends IntegrationTestCase {

	/** @var array<int, array<string, mixed>> 已觸發的 customer_registered 事件記錄 */
	private array $fired_customer_registered = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_customer_registered = [];

		// 監聽 pf/trigger/customer_registered
		\add_action(
			ETriggerPoint::CUSTOMER_REGISTERED->value,
			function ( array $context_callable_set ): void {
				$this->fired_customer_registered[] = $context_callable_set;
			},
			999
		);
	}

	// ========== Rule: 新用戶註冊時應觸發 CUSTOMER_REGISTERED ==========

	/**
	 * WordPress 新用戶註冊時觸發
	 *
	 * Feature: 觸發 CUSTOMER_REGISTERED 觸發點
	 * Example: WordPress 新用戶註冊時觸發
	 *
	 * @group happy
	 */
	public function test_WordPress新用戶註冊時觸發customer_registered(): void {
		// Given 系統中尚無 user_id 為 100 的用戶
		// When WordPress 完成用戶註冊，user_id 為 100
		// 模擬 user_register hook 觸發
		$user_id = 100;
		\do_action('user_register', $user_id);

		// Then 系統應觸發 "pf/trigger/customer_registered"
		$this->assertCount(1, $this->fired_customer_registered, 'customer_registered 應被觸發一次');

		$context_callable_set = $this->fired_customer_registered[0];

		// And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_customer_context"]
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_customer_context' ],
			$context_callable_set['callable'],
			'callable 應為 [TriggerPointService::class, "resolve_customer_context"]'
		);

		// And context_callable_set 的 params 應為 [100]
		$this->assertSame(
			[ $user_id ],
			$context_callable_set['params'],
			'params 應為 [user_id]'
		);
	}

	// ========== Rule: context_callable_set 必須符合 Serializable Context Callable 模式 ==========

	/**
	 * context_callable_set 可被安全序列化
	 *
	 * Feature: 觸發 CUSTOMER_REGISTERED 觸發點
	 * Example: context_callable_set 可被安全序列化
	 *
	 * @group happy
	 */
	public function test_customer_context_callable_set可被序列化(): void {
		// When WordPress 完成用戶註冊，user_id 為 100
		\do_action('user_register', 100);

		$this->assertCount(1, $this->fired_customer_registered, 'customer_registered 應被觸發');
		$context_callable_set = $this->fired_customer_registered[0];

		// Then context_callable_set 的 callable 應為 string[] 格式（非 Closure）
		$this->assertIsArray($context_callable_set['callable'], 'callable 應為陣列（非 Closure）');
		$this->assertCount(2, $context_callable_set['callable'], 'callable 應為 2 元素陣列');
		$this->assertIsString($context_callable_set['callable'][0], 'callable[0] 應為字串（class name）');
		$this->assertIsString($context_callable_set['callable'][1], 'callable[1] 應為字串（method name）');

		// And context_callable_set 的 params 應僅包含純值（int）
		$this->assertIsArray($context_callable_set['params'], 'params 應為陣列');
		foreach ($context_callable_set['params'] as $param) {
			$this->assertIsInt($param, 'params 中的每個值應為 int');
		}

		// 驗證可序列化
		$serialized   = \serialize($context_callable_set);
		$unserialized = \unserialize($serialized);
		$this->assertSame($context_callable_set, $unserialized, '反序列化後應與原始值相同');
	}

	// ========== Rule: user_register hook 傳入 user_id ==========

	/**
	 * user_register hook 的第一個參數為 user_id
	 *
	 * Feature: 觸發 CUSTOMER_REGISTERED 觸發點
	 * Example: user_register hook 的第一個參數為 int 型別的 user_id
	 *
	 * @group happy
	 */
	public function test_user_register_hook第一個參數為user_id(): void {
		// When WordPress 觸發 user_register hook
		$received_user_id = null;
		\add_action(
			'user_register',
			function ( int $user_id ) use ( &$received_user_id ): void {
				$received_user_id = $user_id;
			},
			1
		);
		\do_action('user_register', 100);

		// Then hook 的第一個參數應為 int 型別的 user_id
		$this->assertIsInt($received_user_id, 'user_register hook 的第一個參數應為 int 型別');
		$this->assertSame(100, $received_user_id, 'user_register hook 接收到的 user_id 應為 100');
	}
}
