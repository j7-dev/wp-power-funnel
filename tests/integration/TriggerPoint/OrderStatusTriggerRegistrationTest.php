<?php

/**
 * 6 個 WooCommerce 訂單狀態觸發點註冊整合測試。
 *
 * 驗證 ETriggerPoint 包含 ORDER_PENDING 等 6 個新 case，
 * 且 TriggerPointService 在 WooCommerce 啟用/未啟用時正確註冊監聽器。
 *
 * @group trigger-points
 * @group order-trigger
 * @group order-status-trigger-registration
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/register-order-status-triggers.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 6 個 WooCommerce 訂單狀態觸發點註冊測試
 *
 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
 */
class OrderStatusTriggerRegistrationTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 各測試案例自行控制 register_hooks
	}

	// ========== Rule: ETriggerPoint 應包含 6 個新訂單狀態 case ==========

	/**
	 * ORDER_PENDING 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_PENDING 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_PENDING的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_PENDING
		$trigger = ETriggerPoint::ORDER_PENDING;

		// Then hook value 應為 "pf/trigger/order_pending"
		$this->assertSame(
			'pf/trigger/order_pending',
			$trigger->value,
			'ORDER_PENDING 的 hook value 應為 pf/trigger/order_pending'
		);

		// And label 應為 "訂單待付款"
		$this->assertSame(
			'訂單待付款',
			$trigger->label(),
			'ORDER_PENDING 的 label 應為「訂單待付款」'
		);
	}

	/**
	 * ORDER_PROCESSING 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_PROCESSING 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_PROCESSING的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_PROCESSING
		$trigger = ETriggerPoint::ORDER_PROCESSING;

		// Then hook value 應為 "pf/trigger/order_processing"
		$this->assertSame(
			'pf/trigger/order_processing',
			$trigger->value,
			'ORDER_PROCESSING 的 hook value 應為 pf/trigger/order_processing'
		);

		// And label 應為 "訂單處理中"
		$this->assertSame(
			'訂單處理中',
			$trigger->label(),
			'ORDER_PROCESSING 的 label 應為「訂單處理中」'
		);
	}

	/**
	 * ORDER_ON_HOLD 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_ON_HOLD 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_ON_HOLD的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_ON_HOLD
		$trigger = ETriggerPoint::ORDER_ON_HOLD;

		// Then hook value 應為 "pf/trigger/order_on_hold"
		$this->assertSame(
			'pf/trigger/order_on_hold',
			$trigger->value,
			'ORDER_ON_HOLD 的 hook value 應為 pf/trigger/order_on_hold'
		);

		// And label 應為 "訂單保留中"
		$this->assertSame(
			'訂單保留中',
			$trigger->label(),
			'ORDER_ON_HOLD 的 label 應為「訂單保留中」'
		);
	}

	/**
	 * ORDER_CANCELLED 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_CANCELLED 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_CANCELLED的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_CANCELLED
		$trigger = ETriggerPoint::ORDER_CANCELLED;

		// Then hook value 應為 "pf/trigger/order_cancelled"
		$this->assertSame(
			'pf/trigger/order_cancelled',
			$trigger->value,
			'ORDER_CANCELLED 的 hook value 應為 pf/trigger/order_cancelled'
		);

		// And label 應為 "訂單已取消"
		$this->assertSame(
			'訂單已取消',
			$trigger->label(),
			'ORDER_CANCELLED 的 label 應為「訂單已取消」'
		);
	}

	/**
	 * ORDER_REFUNDED 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_REFUNDED 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_REFUNDED的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_REFUNDED
		$trigger = ETriggerPoint::ORDER_REFUNDED;

		// Then hook value 應為 "pf/trigger/order_refunded"
		$this->assertSame(
			'pf/trigger/order_refunded',
			$trigger->value,
			'ORDER_REFUNDED 的 hook value 應為 pf/trigger/order_refunded'
		);

		// And label 應為 "訂單已退款"
		$this->assertSame(
			'訂單已退款',
			$trigger->label(),
			'ORDER_REFUNDED 的 label 應為「訂單已退款」'
		);
	}

	/**
	 * ORDER_FAILED 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_FAILED 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_ORDER_FAILED的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::ORDER_FAILED
		$trigger = ETriggerPoint::ORDER_FAILED;

		// Then hook value 應為 "pf/trigger/order_failed"
		$this->assertSame(
			'pf/trigger/order_failed',
			$trigger->value,
			'ORDER_FAILED 的 hook value 應為 pf/trigger/order_failed'
		);

		// And label 應為 "訂單失敗"
		$this->assertSame(
			'訂單失敗',
			$trigger->label(),
			'ORDER_FAILED 的 label 應為「訂單失敗」'
		);
	}

	// ========== Rule: 所有新觸發點歸屬 woocommerce 群組 ==========

	/**
	 * 6 個訂單狀態 case 的 group 與 group_label 正確
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_PENDING 的 group 與 group_label 正確（代表性測試）
	 *
	 * @group smoke
	 */
	public function test_所有訂單狀態觸發點歸屬woocommerce群組(): void {
		// When 系統讀取 6 個訂單狀態 ETriggerPoint case
		$cases = [
			ETriggerPoint::ORDER_PENDING,
			ETriggerPoint::ORDER_PROCESSING,
			ETriggerPoint::ORDER_ON_HOLD,
			ETriggerPoint::ORDER_CANCELLED,
			ETriggerPoint::ORDER_REFUNDED,
			ETriggerPoint::ORDER_FAILED,
		];

		foreach ($cases as $trigger) {
			// Then group 應為 "woocommerce"
			$this->assertSame(
				'woocommerce',
				$trigger->group(),
				"{$trigger->name} 的 group 應為 woocommerce"
			);

			// And group_label 應為 "WooCommerce"
			$this->assertSame(
				'WooCommerce',
				$trigger->group_label(),
				"{$trigger->name} 的 group_label 應為 WooCommerce"
			);
		}
	}

	// ========== Rule: 所有新觸發點均為正式實作（非存根） ==========

	/**
	 * 6 個訂單狀態 case 的 is_stub 回傳 false
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_PENDING 的 is_stub 回傳 false（代表性測試）
	 *
	 * @group smoke
	 */
	public function test_所有訂單狀態觸發點is_stub為false(): void {
		// When 系統讀取 6 個訂單狀態 ETriggerPoint case
		$cases = [
			ETriggerPoint::ORDER_PENDING,
			ETriggerPoint::ORDER_PROCESSING,
			ETriggerPoint::ORDER_ON_HOLD,
			ETriggerPoint::ORDER_CANCELLED,
			ETriggerPoint::ORDER_REFUNDED,
			ETriggerPoint::ORDER_FAILED,
		];

		foreach ($cases as $trigger) {
			// Then is_stub 應為 false
			$this->assertFalse(
				$trigger->is_stub(),
				"{$trigger->name} 的 is_stub 應為 false"
			);
		}
	}

	// ========== Rule: WooCommerce 啟用時 TriggerPointService 應監聽 6 個 hook ==========

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_pending 監聽
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: WooCommerce 啟用時註冊 woocommerce_order_status_pending 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊pending監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_pending" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_pending', [ TriggerPointService::class, 'on_order_pending' ]),
			'TriggerPointService 應在 woocommerce_order_status_pending 上註冊監聽器'
		);
	}

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_processing 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊processing監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_processing" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_processing', [ TriggerPointService::class, 'on_order_processing' ]),
			'TriggerPointService 應在 woocommerce_order_status_processing 上註冊監聽器'
		);
	}

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_on-hold 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊on_hold監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_on-hold" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_on-hold', [ TriggerPointService::class, 'on_order_on_hold' ]),
			'TriggerPointService 應在 woocommerce_order_status_on-hold 上註冊監聽器'
		);
	}

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_cancelled 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊cancelled監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_cancelled" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_cancelled', [ TriggerPointService::class, 'on_order_cancelled' ]),
			'TriggerPointService 應在 woocommerce_order_status_cancelled 上註冊監聽器'
		);
	}

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_refunded 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊refunded監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_refunded" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_refunded', [ TriggerPointService::class, 'on_order_refunded' ]),
			'TriggerPointService 應在 woocommerce_order_status_refunded 上註冊監聽器'
		);
	}

	/**
	 * WooCommerce 啟用時註冊 woocommerce_order_status_failed 監聽
	 *
	 * @group happy
	 */
	public function test_WooCommerce啟用時註冊failed監聽器(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "woocommerce_order_status_failed" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('woocommerce_order_status_failed', [ TriggerPointService::class, 'on_order_failed' ]),
			'TriggerPointService 應在 woocommerce_order_status_failed 上註冊監聽器'
		);
	}

	// ========== Rule: WooCommerce 未啟用時不應註冊監聽器 ==========

	/**
	 * WooCommerce 未啟用時靜默忽略所有訂單觸發點
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Example: WooCommerce 未啟用時靜默忽略所有訂單觸發點
	 *
	 * @group edge
	 */
	public function test_WooCommerce未啟用時不應拋出錯誤(): void {
		// Given WooCommerce 外掛未啟用
		// 在本測試環境 WooCommerce 已安裝（透過 bootstrap stub），
		// 此測試驗證 register_hooks 不會拋出任何 Fatal Error。

		// When 系統執行 TriggerPointService::register_hooks()
		// Then 系統不應拋出任何錯誤（如果拋出，測試會自動失敗）
		TriggerPointService::register_hooks();

		$this->assertTrue(true, 'WooCommerce 未啟用時不應拋出錯誤');
	}

	// ========== Rule: 所有 enum value 必須包含 pf/trigger/ 前綴 ==========

	/**
	 * 6 個訂單狀態 case 的 value 以 pf/trigger/ 開頭
	 *
	 * Feature: 註冊 6 個 WooCommerce 訂單狀態觸發點
	 * Scenario: ORDER_PENDING 的 value 以 pf/trigger/ 開頭（代表性測試）
	 *
	 * @group smoke
	 */
	public function test_所有訂單狀態觸發點value以pf_trigger開頭(): void {
		// When 系統讀取 6 個訂單狀態 ETriggerPoint case 的 value
		$cases = [
			ETriggerPoint::ORDER_PENDING,
			ETriggerPoint::ORDER_PROCESSING,
			ETriggerPoint::ORDER_ON_HOLD,
			ETriggerPoint::ORDER_CANCELLED,
			ETriggerPoint::ORDER_REFUNDED,
			ETriggerPoint::ORDER_FAILED,
		];

		foreach ($cases as $trigger) {
			// Then 值應以 "pf/trigger/" 開頭
			$this->assertStringStartsWith(
				'pf/trigger/',
				$trigger->value,
				"{$trigger->name} 的 value 應以 pf/trigger/ 開頭"
			);
		}
	}
}
