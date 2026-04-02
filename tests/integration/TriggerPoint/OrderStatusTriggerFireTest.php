<?php

/**
 * 6 個 WooCommerce 訂單狀態觸發點觸發整合測試。
 *
 * 驗證 WooCommerce 訂單狀態變更時，
 * 系統正確觸發對應的 pf/trigger/order_* 並傳遞正確的 context_callable_set。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-status-trigger-fire
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/fire-order-status-triggers.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 6 個 WooCommerce 訂單狀態觸發點觸發測試
 *
 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
 */
class OrderStatusTriggerFireTest extends IntegrationTestCase {

	/** @var array<int, array<string, mixed>> 已觸發的 pf/trigger/order_* 事件記錄 */
	private array $fired_events = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_events = [];

		// 清除並註冊測試用假訂單
		\WC_Order_Stub_Registry::clear();
		\WC_Order_Stub_Registry::register(1001, new \WC_Order(
			[
				'id'               => 1001,
				'total'            => '2500',
				'billing_email'    => 'alice@example.com',
				'customer_id'      => 42,
				'status'           => 'pending',
				'payment_method'   => 'credit_card',
				'billing_phone'    => '0912345678',
				'shipping_address' => '台北市信義區信義路五段7號',
				'date_created'     => '2026-04-01',
			],
			[ new \WC_Order_Item_Stub('MacBook Pro', 1) ]
		));
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Order_Stub_Registry::clear();
		parent::tear_down();
	}

	/**
	 * 監聽指定 hook 並記錄觸發事件
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

	// ========== Rule: 訂單狀態變更時應觸發對應觸發點 ==========

	/**
	 * 訂單狀態變更為 pending 時觸發 pf/trigger/order_pending
	 *
	 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: 訂單狀態變更為 pending 時觸發 pf/trigger/order_pending
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為pending時觸發order_pending(): void {
		// Given WooCommerce 外掛已啟用，訂單 1001 存在
		$this->listen_trigger(ETriggerPoint::ORDER_PENDING->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "pending"
		$order_id = 1001;
		\do_action('woocommerce_order_status_pending', $order_id);

		// Then 系統應觸發 "pf/trigger/order_pending"
		$this->assertCount(1, $this->fired_events, 'order_pending 應被觸發一次');

		// And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
		$context_callable_set = $this->fired_events[0];
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$context_callable_set['callable'],
			'callable 應為 [TriggerPointService::class, "resolve_order_context"]'
		);

		// And context_callable_set 的 params 應為 [1001]
		$this->assertSame(
			[ $order_id ],
			$context_callable_set['params'],
			'params 應為 [order_id]'
		);
	}

	/**
	 * 訂單狀態從 pending 變為 processing 時觸發 ORDER_PROCESSING
	 *
	 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
	 * Example: 訂單狀態從 pending 變為 processing 時觸發 ORDER_PROCESSING
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為processing時觸發order_processing(): void {
		// Given 訂單 1001 的狀態為 "pending"
		$this->listen_trigger(ETriggerPoint::ORDER_PROCESSING->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "processing"
		$order_id = 1001;
		\do_action('woocommerce_order_status_processing', $order_id);

		// Then 系統應觸發 "pf/trigger/order_processing"
		$this->assertCount(1, $this->fired_events, 'order_processing 應被觸發一次');

		// And context_callable_set 的 callable 應為 [TriggerPointService::class, "resolve_order_context"]
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$this->fired_events[0]['callable']
		);

		// And context_callable_set 的 params 應為 [1001]
		$this->assertSame([ $order_id ], $this->fired_events[0]['params']);
	}

	/**
	 * 訂單狀態變更為 on-hold 時觸發 pf/trigger/order_on_hold
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為on_hold時觸發order_on_hold(): void {
		$this->listen_trigger(ETriggerPoint::ORDER_ON_HOLD->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "on-hold"
		\do_action('woocommerce_order_status_on-hold', 1001);

		// Then 系統應觸發 "pf/trigger/order_on_hold"
		$this->assertCount(1, $this->fired_events, 'order_on_hold 應被觸發一次');
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$this->fired_events[0]['callable']
		);
		$this->assertSame([ 1001 ], $this->fired_events[0]['params']);
	}

	/**
	 * 訂單狀態變更為 cancelled 時觸發 pf/trigger/order_cancelled
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為cancelled時觸發order_cancelled(): void {
		$this->listen_trigger(ETriggerPoint::ORDER_CANCELLED->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "cancelled"
		\do_action('woocommerce_order_status_cancelled', 1001);

		// Then 系統應觸發 "pf/trigger/order_cancelled"
		$this->assertCount(1, $this->fired_events, 'order_cancelled 應被觸發一次');
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$this->fired_events[0]['callable']
		);
		$this->assertSame([ 1001 ], $this->fired_events[0]['params']);
	}

	/**
	 * 訂單狀態變更為 refunded 時觸發 pf/trigger/order_refunded
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為refunded時觸發order_refunded(): void {
		$this->listen_trigger(ETriggerPoint::ORDER_REFUNDED->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "refunded"
		\do_action('woocommerce_order_status_refunded', 1001);

		// Then 系統應觸發 "pf/trigger/order_refunded"
		$this->assertCount(1, $this->fired_events, 'order_refunded 應被觸發一次');
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$this->fired_events[0]['callable']
		);
		$this->assertSame([ 1001 ], $this->fired_events[0]['params']);
	}

	/**
	 * 訂單狀態變更為 failed 時觸發 pf/trigger/order_failed
	 *
	 * @group happy
	 */
	public function test_訂單狀態變為failed時觸發order_failed(): void {
		$this->listen_trigger(ETriggerPoint::ORDER_FAILED->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "failed"
		\do_action('woocommerce_order_status_failed', 1001);

		// Then 系統應觸發 "pf/trigger/order_failed"
		$this->assertCount(1, $this->fired_events, 'order_failed 應被觸發一次');
		$this->assertSame(
			[ TriggerPointService::class, 'resolve_order_context' ],
			$this->fired_events[0]['callable']
		);
		$this->assertSame([ 1001 ], $this->fired_events[0]['params']);
	}

	// ========== Rule: 訂單不存在時不應觸發 ==========

	/**
	 * wc_get_order() 回傳 false 時不觸發
	 *
	 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
	 * Example: wc_get_order() 回傳 false 時不觸發
	 *
	 * @group edge
	 */
	public function test_訂單不存在時不觸發任何order_hook(): void {
		// Given 訂單 9999 不存在
		$this->listen_trigger(ETriggerPoint::ORDER_PENDING->value);

		// When 系統接收到 woocommerce_order_status_pending hook，order_id 為 9999
		\do_action('woocommerce_order_status_pending', 9999);

		// Then 系統不應觸發任何 "pf/trigger/order_*" hook
		$this->assertEmpty($this->fired_events, '訂單不存在時不應觸發任何 pf/trigger/order_* hook');
	}

	// ========== Rule: context_callable_set 必須符合 Serializable Context Callable 模式 ==========

	/**
	 * context_callable_set 可被安全序列化
	 *
	 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
	 * Example: context_callable_set 可被安全序列化
	 *
	 * @group happy
	 */
	public function test_order_status_context_callable_set可被序列化(): void {
		// Given 訂單 1001 存在
		$this->listen_trigger(ETriggerPoint::ORDER_PENDING->value);

		// When WooCommerce 將訂單 1001 的狀態更新為 "pending"
		\do_action('woocommerce_order_status_pending', 1001);

		$this->assertCount(1, $this->fired_events, 'order_pending 應被觸發');
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

		// 驗證可序列化
		$serialized   = \serialize($context_callable_set);
		$unserialized = \unserialize($serialized);
		$this->assertSame($context_callable_set, $unserialized, '反序列化後應與原始值相同');
	}

	// ========== Rule: 所有 6 個觸發點複用 build_order_context_callable_set() ==========

	/**
	 * 所有 6 個狀態觸發時使用相同的 build_order_context_callable_set 方法
	 *
	 * Feature: 觸發 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: pending 狀態觸發時使用相同的 build_order_context_callable_set 方法（代表性）
	 *
	 * @group happy
	 */
	public function test_所有狀態觸發時callable指向resolve_order_context(): void {
		// 監聽所有 6 個觸發點
		$hooks = [
			ETriggerPoint::ORDER_PENDING->value,
			ETriggerPoint::ORDER_PROCESSING->value,
			ETriggerPoint::ORDER_ON_HOLD->value,
			ETriggerPoint::ORDER_CANCELLED->value,
			ETriggerPoint::ORDER_REFUNDED->value,
			ETriggerPoint::ORDER_FAILED->value,
		];
		foreach ($hooks as $hook) {
			$this->listen_trigger($hook);
		}

		// When WooCommerce 將訂單 1001 的狀態更新為各種狀態
		\do_action('woocommerce_order_status_pending', 1001);
		\do_action('woocommerce_order_status_processing', 1001);
		\do_action('woocommerce_order_status_on-hold', 1001);
		\do_action('woocommerce_order_status_cancelled', 1001);
		\do_action('woocommerce_order_status_refunded', 1001);
		\do_action('woocommerce_order_status_failed', 1001);

		$this->assertCount(6, $this->fired_events, '應觸發 6 次');

		// Then 系統內部應呼叫 build_order_context_callable_set(1001)
		// 即 callable 應為 [TriggerPointService::class, 'resolve_order_context']
		foreach ($this->fired_events as $event) {
			$this->assertSame(
				[ TriggerPointService::class, 'resolve_order_context' ],
				$event['callable'],
				'所有狀態的 callable 均應指向 resolve_order_context'
			);
			$this->assertSame([ 1001 ], $event['params'], 'params 均應為 [1001]');
		}
	}
}
