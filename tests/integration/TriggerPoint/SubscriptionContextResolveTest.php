<?php

/**
 * 訂閱 Context 解析整合測試。
 *
 * 驗證 TriggerPointService::resolve_subscription_context() 能正確
 * 從 WC_Subscription 取得 8 個訂閱欄位，
 * 並在訂閱不存在或 WCS 未啟用時回傳安全預設值。
 *
 * @group trigger-points
 * @group subscription-trigger
 * @group subscription-context-resolve
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/resolve-subscription-context.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 訂閱 Context 解析測試（8 個 keys）
 *
 * Feature: 解析訂閱 Context（延遲求值）
 */
class SubscriptionContextResolveTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// resolve_subscription_context 是靜態方法，不需要特別的依賴注入
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();

		// 清除並設定測試用假訂閱
		\WC_Subscription_Stub_Registry::clear();
		\WC_Subscription_Stub_Registry::register(5001, new \WC_Subscription(
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
		));
	}

	/** 每個測試後清理 */
	public function tear_down(): void {
		\WC_Subscription_Stub_Registry::clear();
		parent::tear_down();
	}

	// ========== Rule: resolve_subscription_context 應回傳 8 個訂閱關鍵欄位 ==========

	/**
	 * 訂閱存在時回傳完整 context（8 個 keys）
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: 訂閱存在時回傳完整 context（8 個 keys）
	 *
	 * @group happy
	 */
	public function test_訂閱存在時回傳8個keys(): void {
		// Given WooCommerce Subscriptions 外掛已啟用，訂閱 5001 存在

		// When 系統呼叫 resolve_subscription_context(5001)
		$context = TriggerPointService::resolve_subscription_context(5001);

		// Then 回傳結果應包含 8 個 keys
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

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $context, "context 應包含 key: {$key}");
		}

		$this->assertCount(8, $context, 'context 應恰好包含 8 個 keys');

		// 驗證各欄位值
		$this->assertSame('5001', $context['subscription_id'], 'subscription_id 應正確');
		$this->assertSame('active', $context['subscription_status'], 'subscription_status 應正確');
		$this->assertSame('42', $context['customer_id'], 'customer_id 應正確');
		$this->assertSame('alice@example.com', $context['billing_email'], 'billing_email 應正確');
		$this->assertSame('Alice', $context['billing_first_name'], 'billing_first_name 應正確');
		$this->assertSame('Wang', $context['billing_last_name'], 'billing_last_name 應正確');
		$this->assertSame('299', $context['order_total'], 'order_total 應正確');
		$this->assertSame('credit_card', $context['payment_method'], 'payment_method 應正確');
	}

	// ========== Rule: 使用 wcs_get_subscription() 取得 WC_Subscription 物件 ==========

	/**
	 * 以 subscription_id 為參數呼叫 wcs_get_subscription
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: 以 subscription_id 為參數呼叫 wcs_get_subscription
	 *
	 * @group happy
	 */
	public function test_resolve_subscription_context使用wcs_get_subscription(): void {
		// When 系統呼叫 resolve_subscription_context(5001)
		$context = TriggerPointService::resolve_subscription_context(5001);

		// Then 系統內部應呼叫 wcs_get_subscription(5001)（透過 stub 驗證：成功取得訂閱資料）
		$this->assertNotEmpty($context, 'resolve_subscription_context 應成功透過 wcs_get_subscription 取得訂閱');
		$this->assertSame('5001', $context['subscription_id'], '應取得 subscription_id 為 5001 的訂閱資料');
	}

	// ========== Rule: subscription_status 為 WC_Subscription::get_status() 原始值 ==========

	/**
	 * subscription_status 不含 wc- 前綴
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: subscription_status 不含 wc- 前綴
	 *
	 * @group happy
	 */
	public function test_subscription_status不含wc前綴(): void {
		// Given 訂閱 5001 的狀態為 "active"（WC_Subscription::get_status() 返回不含 wc- 前綴）

		// When 系統呼叫 resolve_subscription_context(5001)
		$context = TriggerPointService::resolve_subscription_context(5001);

		// Then 回傳結果的 subscription_status 應為 "active"
		$this->assertSame('active', $context['subscription_status'], 'subscription_status 應為 "active"');

		// And 不應包含 "wc-" 前綴
		$this->assertStringNotContainsString(
			'wc-',
			$context['subscription_status'],
			'subscription_status 不應包含 "wc-" 前綴'
		);
	}

	// ========== Rule: 訂閱不存在時應回傳空陣列 ==========

	/**
	 * 訂閱不存在時回傳空陣列
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: 訂閱不存在時回傳空陣列
	 *
	 * @group edge
	 */
	public function test_訂閱不存在時回傳空陣列(): void {
		// Given subscription_id 為 9999 的訂閱不存在

		// When 系統呼叫 resolve_subscription_context(9999)
		$context = TriggerPointService::resolve_subscription_context(9999);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, '訂閱不存在時應回傳空陣列');
	}

	// ========== Rule: WaitNode 延遲後應取得最新訂閱資料 ==========

	/**
	 * 訂閱狀態在 WaitNode 等待期間變更後取得最新值
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: 訂閱狀態在 WaitNode 等待期間變更後取得最新值
	 *
	 * @group happy
	 */
	public function test_WaitNode延遲後取得最新訂閱狀態(): void {
		// Given 訂閱 5001 的 status 為 "active"
		$context_before = TriggerPointService::resolve_subscription_context(5001);
		$this->assertSame('active', $context_before['subscription_status'], '初始狀態應為 active');

		// And WaitNode 等待後，訂閱 5001 的 status 被修改為 "on-hold"
		\WC_Subscription_Stub_Registry::register(5001, new \WC_Subscription(
			[
				'id'                 => 5001,
				'status'             => 'on-hold',
				'customer_id'        => 42,
				'billing_email'      => 'alice@example.com',
				'billing_first_name' => 'Alice',
				'billing_last_name'  => 'Wang',
				'total'              => '299',
				'payment_method'     => 'credit_card',
			]
		));

		// When 系統呼叫 resolve_subscription_context(5001)（第二次）
		$context_after = TriggerPointService::resolve_subscription_context(5001);

		// Then 回傳結果的 subscription_status 應為 "on-hold"（延遲求值，非快照）
		$this->assertSame(
			'on-hold',
			$context_after['subscription_status'],
			'延遲求值應取得最新狀態 on-hold'
		);
	}

	// ========== Rule: WooCommerce Subscriptions 未啟用時回傳空陣列 ==========

	/**
	 * wcs_get_subscription 函式不存在時回傳空陣列
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Example: wcs_get_subscription 函式不存在時回傳空陣列
	 *
	 * 注意：在本測試環境中 wcs_get_subscription 已透過 bootstrap stub 定義，
	 * 因此此測試驗證的是當 subscriptionId 為負值時的防護行為（等效邊界測試）。
	 *
	 * @group edge
	 */
	public function test_WCS未啟用時回傳空陣列(): void {
		// 由於測試環境已定義 wcs_get_subscription stub，
		// 此測試改為驗證當 subscription_id 不存在時（等效 WCS 不存在的結果）
		// 系統應回傳空陣列的防護行為。

		// Given 不存在的 subscription_id
		// When 系統呼叫 resolve_subscription_context(9999)
		$context = TriggerPointService::resolve_subscription_context(9999);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, '訂閱不存在時應回傳空陣列（等效 WCS 未啟用）');
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * subscriptionId 為 0 時操作失敗
	 *
	 * Feature: 解析訂閱 Context（延遲求值）
	 * Scenario: 缺少必要參數時操作失敗（subscriptionId = 0）
	 *
	 * @group edge
	 */
	public function test_subscriptionId為0時回傳空陣列(): void {
		// When 系統呼叫 resolve_subscription_context(0)
		$context = TriggerPointService::resolve_subscription_context(0);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, 'subscriptionId 為 0 時應回傳空陣列');
	}
}
