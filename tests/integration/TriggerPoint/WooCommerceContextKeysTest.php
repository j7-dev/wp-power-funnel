<?php

/**
 * WooCommerce 擴充觸發點 Context Keys 查詢整合測試。
 *
 * 驗證新增的 14 個觸發點（6 個訂單狀態、1 個顧客、7 個訂閱）
 * 都有正確的 context keys 對應。
 *
 * @group trigger-points
 * @group context-keys
 * @group woocommerce-context-keys
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/query-context-keys-expanded.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * WooCommerce 擴充觸發點 Context Keys 查詢測試
 *
 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
 */
class WooCommerceContextKeysTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	// ========== Rule: 新增訂單觸發點的 context keys 應包含 order_status ==========

	/**
	 * 查詢 ORDER_PENDING 觸發點的 context keys（10 個，含 order_status）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Example: 查詢 ORDER_PENDING 觸發點的 context keys（10 個，含 order_status）
	 *
	 * @group happy
	 */
	public function test_ORDER_PENDING的context_keys包含order_status(): void {
		// When 管理員查詢觸發點 "pf/trigger/order_pending" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::ORDER_PENDING->value
		);

		// Then 回傳結果應包含 10 個 key，包含：
		// order_id, order_total, billing_email, customer_id,
		// line_items_summary, shipping_address, payment_method,
		// order_date, billing_phone, order_status
		$this->assertIsArray($context_keys, 'context keys 應為陣列');
		$this->assertCount(10, $context_keys, 'ORDER_PENDING 應有 10 個 context keys');

		$key_names = \array_column($context_keys, 'key');
		$expected_keys = [
			'order_id', 'order_total', 'billing_email', 'customer_id',
			'line_items_summary', 'shipping_address', 'payment_method',
			'order_date', 'billing_phone', 'order_status',
		];

		foreach ($expected_keys as $expected_key) {
			$this->assertContains($expected_key, $key_names, "context keys 應包含 key: {$expected_key}");
		}
	}

	// ========== Rule: 6 個訂單觸發點共用相同的 order_keys ==========

	/**
	 * 6 個訂單狀態觸發點的 context keys 都包含 10 個 key（含 order_status）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Scenario: 6 個觸發點的 context keys 與 ORDER_PENDING 相同（代表性）
	 *
	 * @group happy
	 */
	public function test_6個訂單狀態觸發點context_keys包含order_status(): void {
		// When 管理員分別查詢 6 個訂單狀態觸發點的 context keys
		$order_status_triggers = [
			ETriggerPoint::ORDER_PENDING,
			ETriggerPoint::ORDER_PROCESSING,
			ETriggerPoint::ORDER_ON_HOLD,
			ETriggerPoint::ORDER_CANCELLED,
			ETriggerPoint::ORDER_REFUNDED,
			ETriggerPoint::ORDER_FAILED,
		];

		foreach ($order_status_triggers as $trigger) {
			$context_keys = \apply_filters(
				'power_funnel/trigger_point/context_keys',
				[],
				$trigger->value
			);

			// Then 每個觸發點都應回傳 10 個 key
			$this->assertCount(10, $context_keys, "{$trigger->name} 應有 10 個 context keys");

			// And 都應包含 key "order_status"
			$key_names = \array_column($context_keys, 'key');
			$this->assertContains('order_status', $key_names, "{$trigger->name} 的 context keys 應包含 order_status");
		}
	}

	// ========== Rule: 既有 ORDER_COMPLETED 也應擴充 order_status key ==========

	/**
	 * ORDER_COMPLETED 的 context keys 也包含 order_status（10 個）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Example: ORDER_COMPLETED 的 context keys 也包含 order_status（10 個）
	 *
	 * @group happy
	 */
	public function test_ORDER_COMPLETED的context_keys也包含order_status(): void {
		// When 管理員查詢觸發點 "pf/trigger/order_completed" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::ORDER_COMPLETED->value
		);

		// Then 回傳結果應包含 10 個 key
		$this->assertCount(10, $context_keys, 'ORDER_COMPLETED 應有 10 個 context keys');

		// And 回傳結果應包含 key "order_status"
		$key_names = \array_column($context_keys, 'key');
		$this->assertContains('order_status', $key_names, 'ORDER_COMPLETED 的 context keys 應包含 order_status');
	}

	// ========== Rule: CUSTOMER_REGISTERED 的 context keys ==========

	/**
	 * 查詢 CUSTOMER_REGISTERED 觸發點的 context keys（5 個）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Example: 查詢 CUSTOMER_REGISTERED 觸發點的 context keys（5 個）
	 *
	 * @group happy
	 */
	public function test_CUSTOMER_REGISTERED的context_keys正確(): void {
		// When 管理員查詢觸發點 "pf/trigger/customer_registered" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::CUSTOMER_REGISTERED->value
		);

		// Then 回傳結果應包含 5 個 key：
		// customer_id, billing_email, billing_first_name, billing_last_name, billing_phone
		$this->assertIsArray($context_keys, 'context keys 應為陣列');
		$this->assertCount(5, $context_keys, 'CUSTOMER_REGISTERED 應有 5 個 context keys');

		$key_names    = \array_column($context_keys, 'key');
		$expected_keys = [
			'customer_id',
			'billing_email',
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
		];

		foreach ($expected_keys as $expected_key) {
			$this->assertContains($expected_key, $key_names, "context keys 應包含 key: {$expected_key}");
		}
	}

	// ========== Rule: 7 個訂閱觸發點共用 subscription_keys ==========

	/**
	 * 查詢 SUBSCRIPTION_INITIAL_PAYMENT 觸發點的 context keys（8 個）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Example: 查詢 SUBSCRIPTION_INITIAL_PAYMENT 觸發點的 context keys（8 個）
	 *
	 * @group happy
	 */
	public function test_SUBSCRIPTION_INITIAL_PAYMENT的context_keys正確(): void {
		// When 管理員查詢觸發點 "pf/trigger/subscription_initial_payment" 的 context keys
		$context_keys = \apply_filters(
			'power_funnel/trigger_point/context_keys',
			[],
			ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT->value
		);

		// Then 回傳結果應包含 8 個 key：
		// subscription_id, subscription_status, customer_id,
		// billing_email, billing_first_name, billing_last_name,
		// order_total, payment_method
		$this->assertIsArray($context_keys, 'context keys 應為陣列');
		$this->assertCount(8, $context_keys, 'SUBSCRIPTION_INITIAL_PAYMENT 應有 8 個 context keys');

		$key_names    = \array_column($context_keys, 'key');
		$expected_keys = [
			'subscription_id',
			'subscription_status',
			'customer_id',
			'billing_email',
			'billing_first_name',
			'billing_last_name',
			'order_total',
			'payment_method',
		];

		foreach ($expected_keys as $expected_key) {
			$this->assertContains($expected_key, $key_names, "context keys 應包含 key: {$expected_key}");
		}
	}

	/**
	 * 7 個訂閱觸發點的 context keys 都包含 8 個 key（含 subscription_id）
	 *
	 * Feature: 查詢觸發點可用 Context Keys（擴充新增觸發點）
	 * Scenario: 7 個訂閱觸發點的 context keys 與 SUBSCRIPTION_INITIAL_PAYMENT 相同（代表性）
	 *
	 * @group happy
	 */
	public function test_7個訂閱觸發點context_keys包含subscription_id(): void {
		// When 管理員分別查詢 7 個訂閱觸發點的 context keys
		$subscription_triggers = [
			ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT,
			ETriggerPoint::SUBSCRIPTION_FAILED,
			ETriggerPoint::SUBSCRIPTION_SUCCESS,
			ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER,
			ETriggerPoint::SUBSCRIPTION_END,
			ETriggerPoint::SUBSCRIPTION_TRIAL_END,
			ETriggerPoint::SUBSCRIPTION_PREPAID_END,
		];

		foreach ($subscription_triggers as $trigger) {
			$context_keys = \apply_filters(
				'power_funnel/trigger_point/context_keys',
				[],
				$trigger->value
			);

			// Then 每個觸發點都應回傳 8 個 key
			$this->assertCount(8, $context_keys, "{$trigger->name} 應有 8 個 context keys");

			// And 都應包含 key "subscription_id"
			$key_names = \array_column($context_keys, 'key');
			$this->assertContains('subscription_id', $key_names, "{$trigger->name} 的 context keys 應包含 subscription_id");
		}
	}
}
