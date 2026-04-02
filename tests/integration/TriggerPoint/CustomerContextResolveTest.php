<?php

/**
 * 顧客 Context 解析整合測試。
 *
 * 驗證 TriggerPointService::resolve_customer_context() 能正確
 * 從 WordPress user_meta 取得帳單資訊，
 * 並在用戶不存在時回傳安全預設值。
 *
 * @group trigger-points
 * @group customer-trigger
 * @group customer-context-resolve
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/resolve-customer-context.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 顧客 Context 解析測試
 *
 * Feature: 解析顧客 Context（延遲求值）
 */
class CustomerContextResolveTest extends IntegrationTestCase {

	/** @var int 測試用戶 ID */
	private int $user_id = 0;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// resolve_customer_context 是靜態方法，不需要特別的依賴注入
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();

		// 建立測試用戶
		$this->user_id = $this->factory()->user->create(
			[
				'user_login' => 'alice_test',
				'user_email' => 'alice@example.com',
			]
		);

		// 設定帳單資訊 meta
		\update_user_meta($this->user_id, 'billing_email', 'alice@example.com');
		\update_user_meta($this->user_id, 'billing_first_name', 'Alice');
		\update_user_meta($this->user_id, 'billing_last_name', 'Wang');
		\update_user_meta($this->user_id, 'billing_phone', '0912345678');
	}

	// ========== Rule: resolve_customer_context 應回傳 5 個顧客關鍵欄位 ==========

	/**
	 * 用戶存在時回傳完整 context（5 個 keys）
	 *
	 * Feature: 解析顧客 Context（延遲求值）
	 * Example: 用戶存在時回傳完整 context（5 個 keys）
	 *
	 * @group happy
	 */
	public function test_用戶存在時回傳5個keys(): void {
		// Given WordPress 系統運作中，用戶存在，帳單資訊如 Background 設置

		// When 系統呼叫 resolve_customer_context({user_id})
		$context = TriggerPointService::resolve_customer_context($this->user_id);

		// Then 回傳結果應包含 5 個 keys：
		// customer_id, billing_email, billing_first_name, billing_last_name, billing_phone
		$expected_keys = [
			'customer_id',
			'billing_email',
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $context, "context 應包含 key: {$key}");
		}

		$this->assertCount(5, $context, 'context 應恰好包含 5 個 keys');

		// 驗證各欄位值
		$this->assertSame((string) $this->user_id, $context['customer_id'], 'customer_id 應為用戶 ID');
		$this->assertSame('alice@example.com', $context['billing_email'], 'billing_email 應正確');
		$this->assertSame('Alice', $context['billing_first_name'], 'billing_first_name 應正確');
		$this->assertSame('Wang', $context['billing_last_name'], 'billing_last_name 應正確');
		$this->assertSame('0912345678', $context['billing_phone'], 'billing_phone 應正確');
	}

	// ========== Rule: 用戶不存在時應回傳空陣列 ==========

	/**
	 * 用戶不存在時回傳空陣列
	 *
	 * Feature: 解析顧客 Context（延遲求值）
	 * Example: 用戶不存在時回傳空陣列
	 *
	 * @group edge
	 */
	public function test_用戶不存在時回傳空陣列(): void {
		// Given user_id 為 9999 的用戶不存在

		// When 系統呼叫 resolve_customer_context(9999)
		$context = TriggerPointService::resolve_customer_context(9999);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, '用戶不存在時應回傳空陣列');
	}

	// ========== Rule: WaitNode 延遲後應取得最新用戶資料 ==========

	/**
	 * 用戶 email 在 WaitNode 等待期間被修改後取得最新值
	 *
	 * Feature: 解析顧客 Context（延遲求值）
	 * Example: 用戶 email 在 WaitNode 等待期間被修改後取得最新值
	 *
	 * @group happy
	 */
	public function test_WaitNode延遲後取得最新用戶資料(): void {
		// Given 用戶的 billing_email 為 "alice@example.com"
		$context_before = TriggerPointService::resolve_customer_context($this->user_id);
		$this->assertSame('alice@example.com', $context_before['billing_email'], '初始 email 應為 alice@example.com');

		// And WaitNode 等待後，用戶的 billing_email 被修改為 "newalice@example.com"
		\update_user_meta($this->user_id, 'billing_email', 'newalice@example.com');

		// When 系統呼叫 resolve_customer_context({user_id})（第二次）
		$context_after = TriggerPointService::resolve_customer_context($this->user_id);

		// Then 回傳結果的 billing_email 應為 "newalice@example.com"（延遲求值，非快照）
		$this->assertSame(
			'newalice@example.com',
			$context_after['billing_email'],
			'延遲求值應取得最新 email：newalice@example.com'
		);
	}

	// ========== Rule: 使用 WordPress get_user_meta 取得帳單資訊 ==========

	/**
	 * billing 欄位從 user_meta 讀取
	 *
	 * Feature: 解析顧客 Context（延遲求值）
	 * Example: billing 欄位從 user_meta 讀取
	 *
	 * @group happy
	 */
	public function test_billing欄位從user_meta讀取(): void {
		// When 系統呼叫 resolve_customer_context({user_id})
		$context = TriggerPointService::resolve_customer_context($this->user_id);

		// Then 系統內部應呼叫 get_user_meta 取得 billing 欄位
		// 驗證值確實從 user_meta 讀取（透過 update_user_meta 設定的值應被正確讀取）
		$this->assertSame(
			\get_user_meta($this->user_id, 'billing_email', true),
			$context['billing_email'],
			'billing_email 應從 user_meta 讀取'
		);
		$this->assertSame(
			\get_user_meta($this->user_id, 'billing_first_name', true),
			$context['billing_first_name'],
			'billing_first_name 應從 user_meta 讀取'
		);
		$this->assertSame(
			\get_user_meta($this->user_id, 'billing_last_name', true),
			$context['billing_last_name'],
			'billing_last_name 應從 user_meta 讀取'
		);
		$this->assertSame(
			\get_user_meta($this->user_id, 'billing_phone', true),
			$context['billing_phone'],
			'billing_phone 應從 user_meta 讀取'
		);
	}

	// ========== Rule: 必要參數必須提供 ==========

	/**
	 * userId 為 0 時操作失敗
	 *
	 * Feature: 解析顧客 Context（延遲求值）
	 * Scenario: 缺少必要參數時操作失敗（userId = 0）
	 *
	 * @group edge
	 */
	public function test_userId為0時回傳空陣列(): void {
		// When 系統呼叫 resolve_customer_context(0)
		$context = TriggerPointService::resolve_customer_context(0);

		// Then 回傳結果應為空陣列
		$this->assertIsArray($context, '回傳結果應為陣列');
		$this->assertEmpty($context, 'userId 為 0 時應回傳空陣列');
	}
}
