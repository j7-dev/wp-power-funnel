<?php

/**
 * 訂單 Context V2 解析整合測試（新增 order_status 欄位）。
 *
 * 驗證 TriggerPointService::resolve_order_context() 現在回傳 10 個欄位，
 * 新增 order_status 欄位（WC_Order::get_status() 原始值，不含 wc- 前綴）。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-context-resolve-v2
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/resolve-order-context-v2.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 訂單 Context V2 解析測試（10 個 keys，含 order_status）
 *
 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
 */
class OrderContextV2ResolveTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// resolve_order_context 是靜態方法，不需要特別的依賴注入
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();

		// 清除並註冊測試用假訂單
		\WC_Order_Stub_Registry::clear();
		\WC_Order_Stub_Registry::register(1001, new \WC_Order(
			[
				'id'               => 1001,
				'total'            => '2500',
				'billing_email'    => 'alice@example.com',
				'customer_id'      => 42,
				'status'           => 'completed',
				'payment_method'   => 'credit_card',
				'billing_phone'    => '0912345678',
				'shipping_address' => '台北市信義區信義路五段7號',
				'date_created'     => '2026-04-01',
			],
			[ new \WC_Order_Item_Stub('MacBook Pro', 1) ]
		));
		\WC_Order_Stub_Registry::register(1002, new \WC_Order(
			[
				'id'               => 1002,
				'total'            => '800',
				'billing_email'    => 'bob@example.com',
				'customer_id'      => 43,
				'status'           => 'pending',
				'payment_method'   => 'bank_transfer',
				'billing_phone'    => '0987654321',
				'shipping_address' => '新北市板橋區文化路一段100號',
				'date_created'     => '2026-04-02',
			],
			[ new \WC_Order_Item_Stub('iPhone 16', 1) ]
		));
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Order_Stub_Registry::clear();
		parent::tear_down();
	}

	// ========== Rule: resolve_order_context 應回傳 10 個訂單關鍵欄位（含新增 order_status） ==========

	/**
	 * 訂單存在時回傳完整 context（10 個 keys）
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Example: 訂單存在時回傳完整 context（10 個 keys）
	 *
	 * @group happy
	 */
	public function test_訂單存在時回傳10個keys(): void {
		// Given WooCommerce 外掛已啟用，訂單 1001 存在

		// When 系統呼叫 resolve_order_context(1001)
		$context = TriggerPointService::resolve_order_context(1001);

		// Then 回傳結果應包含 10 個 key，包含：
		// order_id, order_total, billing_email, customer_id,
		// line_items_summary, shipping_address, payment_method,
		// order_date, billing_phone, order_status
		$expected_keys = [
			'order_id',
			'order_total',
			'billing_email',
			'customer_id',
			'line_items_summary',
			'shipping_address',
			'payment_method',
			'order_date',
			'billing_phone',
			'order_status',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $context, "context 應包含 key: {$key}");
		}

		$this->assertCount(10, $context, 'context 應恰好包含 10 個 keys');
	}

	/**
	 * pending 狀態訂單的 order_status 回傳 pending
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Example: pending 狀態訂單的 order_status 回傳 pending
	 *
	 * @group happy
	 */
	public function test_pending狀態訂單的order_status正確(): void {
		// When 系統呼叫 resolve_order_context(1002)
		$context = TriggerPointService::resolve_order_context(1002);

		// Then 回傳結果的 order_status 應為 "pending"
		$this->assertArrayHasKey('order_status', $context, 'context 應包含 order_status');
		$this->assertSame('pending', $context['order_status'], 'order_status 應為 "pending"');
	}

	// ========== Rule: order_status 欄位為 WC_Order::get_status() 原始值 ==========

	/**
	 * order_status 不含 wc- 前綴
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Example: order_status 不含 wc- 前綴
	 *
	 * @group happy
	 */
	public function test_order_status不含wc前綴(): void {
		// Given 訂單 1001 的 WooCommerce 內部狀態為 "completed"（WC_Order::get_status() 返回不含 wc- 前綴）

		// When 系統呼叫 resolve_order_context(1001)
		$context = TriggerPointService::resolve_order_context(1001);

		// Then 回傳結果的 order_status 應為 "completed"
		$this->assertSame('completed', $context['order_status'], 'order_status 應為 "completed"');

		// And 回傳結果的 order_status 不應包含 "wc-" 前綴
		$this->assertStringNotContainsString(
			'wc-',
			$context['order_status'],
			'order_status 不應包含 "wc-" 前綴'
		);
	}

	// ========== Rule: 向下相容既有 ORDER_COMPLETED 觸發點 ==========

	/**
	 * ORDER_COMPLETED 觸發點也使用新版 resolve_order_context（10 個 keys）
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Example: ORDER_COMPLETED 觸發點也使用新版 resolve_order_context（10 個 keys）
	 *
	 * @group happy
	 */
	public function test_ORDER_COMPLETED也使用新版resolve_order_context(): void {
		// Given 訂單 1001 的狀態為 "completed"

		// When 系統呼叫 resolve_order_context(1001)
		$context = TriggerPointService::resolve_order_context(1001);

		// Then 回傳結果應包含 10 個 key
		$this->assertCount(10, $context, 'ORDER_COMPLETED 的 context 應包含 10 個 keys');

		// And 回傳結果應包含 key "order_status"
		$this->assertArrayHasKey('order_status', $context, 'ORDER_COMPLETED 的 context 應包含 order_status');
	}

	// ========== Rule: 訂單已刪除時應回傳安全預設值 ==========

	/**
	 * 訂單不存在時回傳空陣列
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Example: 訂單不存在時回傳空陣列
	 *
	 * @group edge
	 */
	public function test_訂單不存在時回傳空陣列_v2(): void {
		// Given 訂單 9999 已被刪除

		// When 系統呼叫 resolve_order_context(9999)
		$context = TriggerPointService::resolve_order_context(9999);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, '訂單不存在時應回傳空陣列');
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * orderId 為 0 時操作失敗
	 *
	 * Feature: 解析訂單 Context V2（新增 order_status 欄位）
	 * Scenario: 缺少必要參數時操作失敗（orderId = 0）
	 *
	 * @group edge
	 */
	public function test_orderId為0時回傳空陣列_v2(): void {
		// When 系統呼叫 resolve_order_context(0)
		$context = TriggerPointService::resolve_order_context(0);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, 'orderId 為 0 時應回傳空陣列');
	}
}
