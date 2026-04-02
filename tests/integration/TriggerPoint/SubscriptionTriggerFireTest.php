<?php

/**
 * 7 個訂閱生命週期觸發點觸發整合測試。
 *
 * 驗證 Powerhouse hook 觸發時，
 * 系統正確觸發對應的 pf/trigger/subscription_* 並傳遞正確的 context_callable_set。
 *
 * @group trigger-points
 * @group subscription-trigger
 * @group subscription-trigger-fire
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/fire-subscription-triggers.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 7 個訂閱生命週期觸發點觸發測試
 *
 * Feature: 觸發 7 個訂閱生命週期觸發點
 */
class SubscriptionTriggerFireTest extends IntegrationTestCase {

	/** @var array<int, array<string, mixed>> 已觸發的 pf/trigger/subscription_* 事件記錄 */
	private array $fired_events = [];

	/** @var \WC_Subscription 測試用假訂閱物件 */
	private \WC_Subscription $stub_subscription;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_events = [];

		// 建立假訂閱並在 stub 註冊表中登錄
		\WC_Subscription_Stub_Registry::clear();
		$this->stub_subscription = new \WC_Subscription(
			[
				'id'                 => 5001,
				'status'             => 'active',
				'customer_id'        => 42,
				'billing_email'      => 'alice@example.com',
				'billing_first_name' => 'Alice',
				'billing_last_name'  => 'Wang',
				'total'              => '299',
				'payment_method'     => 'credit_card',
			]
		);
		\WC_Subscription_Stub_Registry::register(5001, $this->stub_subscription);
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Subscription_Stub_Registry::clear();
		parent::tear_down();
	}

	/**
	 * 監聽指定 pf/trigger 並記錄觸發事件
	 *
	 * @param string $hook_value pf/trigger/* hook 名稱
	 * @return void
	 */
	private function listen_trigger( string $hook_value ): void {
		\add_action(
			$hook_value,
			function ( array $context_callable_set ): void {
				$this->fired_events[] = $context_callable_set;
			},
			999
		);
	}

	// ========== Rule: Powerhouse 訂閱事件發生時應觸發對應觸發點 ==========

	/**
	 * Powerhouse hook powerhouse_subscription_at_initial_payment_complete 觸發時，
	 * 應 do_action pf/trigger/subscription_initial_payment
	 *
	 * Feature: 觸發 7 個訂閱生命週期觸發點
	 * Scenario: Powerhouse hook powerhouse_subscription_at_initial_payment_complete 觸發時，
	 *           應 do_action pf/trigger/subscription_initial_payment
	 *
	 * @group happy
	 */
	public function test_initial_payment_hook觸發時fire_subscription_initial_payment(): void {
		// Given Powerhouse 外掛已啟用，WC_Subscription 5001 存在
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT->value);

		// When Powerhouse 觸發 "powerhouse_subscription_at_initial_payment_complete" hook，
		//      傳入 WC_Subscription 物件（subscription_id = 5001）
		\do_action('powerhouse_subscription_at_initial_payment_complete', $this->stub_subscription);

		// Then 系統應觸發 "pf/trigger/subscription_initial_payment"
		$this->assertCount(1, $this->fired_events, 'subscription_initial_payment 應被觸發一次');

		// And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_subscription_context"]
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_subscription_context' ],
			$this->fired_events[0]['callable'],
			'callable 應為 [TriggerPointService::class, "resolve_subscription_context"]'
		);

		// And context_callable_set 的 params 應為 [5001]
		$this->assertSame([ 5001 ], $this->fired_events[0]['params'], 'params 應為 [5001]');
	}

	/**
	 * powerhouse_subscription_at_subscription_failed 觸發時 fire subscription_failed
	 *
	 * @group happy
	 */
	public function test_subscription_failed_hook觸發時fire_subscription_failed(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_FAILED->value);

		// When Powerhouse 觸發 "powerhouse_subscription_at_subscription_failed" hook
		\do_action('powerhouse_subscription_at_subscription_failed', $this->stub_subscription);

		// Then 系統應觸發 "pf/trigger/subscription_failed"
		$this->assertCount(1, $this->fired_events, 'subscription_failed 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	/**
	 * powerhouse_subscription_at_subscription_success 觸發時 fire subscription_success
	 *
	 * @group happy
	 */
	public function test_subscription_success_hook觸發時fire_subscription_success(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_SUCCESS->value);

		\do_action('powerhouse_subscription_at_subscription_success', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_success 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	/**
	 * powerhouse_subscription_at_renewal_order_created 觸發時 fire subscription_renewal_order
	 *
	 * @group happy
	 */
	public function test_renewal_order_hook觸發時fire_subscription_renewal_order(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER->value);

		\do_action('powerhouse_subscription_at_renewal_order_created', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_renewal_order 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	/**
	 * powerhouse_subscription_at_end 觸發時 fire subscription_end
	 *
	 * @group happy
	 */
	public function test_subscription_end_hook觸發時fire_subscription_end(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_END->value);

		\do_action('powerhouse_subscription_at_end', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_end 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	/**
	 * powerhouse_subscription_at_trial_end 觸發時 fire subscription_trial_end
	 *
	 * @group happy
	 */
	public function test_trial_end_hook觸發時fire_subscription_trial_end(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_TRIAL_END->value);

		\do_action('powerhouse_subscription_at_trial_end', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_trial_end 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	/**
	 * powerhouse_subscription_at_end_of_prepaid_term 觸發時 fire subscription_prepaid_end
	 *
	 * @group happy
	 */
	public function test_prepaid_end_hook觸發時fire_subscription_prepaid_end(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_PREPAID_END->value);

		\do_action('powerhouse_subscription_at_end_of_prepaid_term', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_prepaid_end 應被觸發一次');
		$this->assertSame([ TriggerPointService::class, 'resolve_subscription_context' ], $this->fired_events[0]['callable']);
		$this->assertSame([ 5001 ], $this->fired_events[0]['params']);
	}

	// ========== Rule: Powerhouse hook 傳入 WC_Subscription 物件 ==========

	/**
	 * handler 從 WC_Subscription 物件取得 subscription_id
	 *
	 * Feature: 觸發 7 個訂閱生命週期觸發點
	 * Example: handler 從 WC_Subscription 物件取得 subscription_id
	 *
	 * @group happy
	 */
	public function test_handler從WC_Subscription物件取得subscription_id(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT->value);

		// When Powerhouse 觸發 "powerhouse_subscription_at_initial_payment_complete" hook，
		//      傳入 WC_Subscription 物件（get_id() 回傳 5001）
		\do_action('powerhouse_subscription_at_initial_payment_complete', $this->stub_subscription);

		// Then handler 應從 WC_Subscription 物件呼叫 get_id() 取得 subscription_id
		$this->assertCount(1, $this->fired_events, 'subscription_initial_payment 應被觸發一次');

		// And params 應為 [5001]（即 get_id() 的回傳值）
		$this->assertSame(
			[ $this->stub_subscription->get_id() ],
			$this->fired_events[0]['params'],
			'params 應為 [subscription->get_id()]'
		);
	}

	// ========== Rule: WC_Subscription 物件無效時不應觸發 ==========

	/**
	 * 傳入的物件不是 WC_Subscription 時靜默忽略
	 *
	 * Feature: 觸發 7 個訂閱生命週期觸發點
	 * Example: 傳入的物件不是 WC_Subscription 時靜默忽略
	 *
	 * @group edge
	 */
	public function test_傳入非WC_Subscription物件時靜默忽略(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT->value);

		// When Powerhouse 觸發 "powerhouse_subscription_at_initial_payment_complete" hook，
		//      但傳入非 WC_Subscription 物件（例如字串）
		\do_action('powerhouse_subscription_at_initial_payment_complete', 'not_a_subscription');

		// Then 系統不應觸發任何 "pf/trigger/subscription_*" hook
		$this->assertEmpty($this->fired_events, '傳入非 WC_Subscription 物件時不應觸發任何 pf/trigger/subscription_* hook');
	}

	// ========== Rule: context_callable_set 必須符合 Serializable Context Callable 模式 ==========

	/**
	 * context_callable_set 可被安全序列化
	 *
	 * Feature: 觸發 7 個訂閱生命週期觸發點
	 * Example: context_callable_set 可被安全序列化
	 *
	 * @group happy
	 */
	public function test_subscription_context_callable_set可被序列化(): void {
		$this->listen_trigger(ETriggerPoint::SUBSCRIPTION_SUCCESS->value);

		// When Powerhouse 觸發 "powerhouse_subscription_at_subscription_success" hook
		\do_action('powerhouse_subscription_at_subscription_success', $this->stub_subscription);

		$this->assertCount(1, $this->fired_events, 'subscription_success 應被觸發');
		$context_callable_set = $this->fired_events[0];

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

		// And context_callable_set 不應包含 WC_Subscription 物件引用
		$serialized   = \serialize($context_callable_set);
		$unserialized = \unserialize($serialized);
		$this->assertSame($context_callable_set, $unserialized, '反序列化後應與原始值相同');
	}
}
