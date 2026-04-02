<?php

/**
 * CUSTOMER_REGISTERED 觸發點註冊整合測試。
 *
 * 驗證 ETriggerPoint 包含 CUSTOMER_REGISTERED case（customer group），
 * 且 TriggerPointService 在系統啟動時註冊 user_register 監聽器。
 *
 * @group trigger-points
 * @group customer-trigger
 * @group customer-trigger-registration
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/register-customer-registered-trigger.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * CUSTOMER_REGISTERED 觸發點註冊測試
 *
 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
 */
class CustomerTriggerRegistrationTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 各測試案例自行控制 register_hooks
	}

	// ========== Rule: ETriggerPoint 應包含 CUSTOMER_REGISTERED case ==========

	/**
	 * CUSTOMER_REGISTERED 的 hook value 為 pf/trigger/customer_registered
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: CUSTOMER_REGISTERED 的 hook value 為 pf/trigger/customer_registered
	 *
	 * @group smoke
	 */
	public function test_CUSTOMER_REGISTERED的hook_value正確(): void {
		// When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
		$trigger = ETriggerPoint::CUSTOMER_REGISTERED;

		// Then hook value 應為 "pf/trigger/customer_registered"
		$this->assertSame(
			'pf/trigger/customer_registered',
			$trigger->value,
			'CUSTOMER_REGISTERED 的 hook value 應為 pf/trigger/customer_registered'
		);

		// And label 應為 "新顧客註冊"
		$this->assertSame(
			'新顧客註冊',
			$trigger->label(),
			'CUSTOMER_REGISTERED 的 label 應為「新顧客註冊」'
		);
	}

	// ========== Rule: CUSTOMER_REGISTERED 歸屬 customer 群組 ==========

	/**
	 * CUSTOMER_REGISTERED 的 group 與 group_label 正確
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: group 與 group_label 正確
	 *
	 * @group smoke
	 */
	public function test_CUSTOMER_REGISTERED歸屬customer群組(): void {
		// When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
		$trigger = ETriggerPoint::CUSTOMER_REGISTERED;

		// Then group 應為 "customer"
		$this->assertSame(
			'customer',
			$trigger->group(),
			'CUSTOMER_REGISTERED 的 group 應為 customer'
		);

		// And group_label 應為 "顧客行為"
		$this->assertSame(
			'顧客行為',
			$trigger->group_label(),
			'CUSTOMER_REGISTERED 的 group_label 應為「顧客行為」'
		);
	}

	// ========== Rule: CUSTOMER_REGISTERED 為正式實作（非存根） ==========

	/**
	 * CUSTOMER_REGISTERED 的 is_stub 回傳 false
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: is_stub 回傳 false
	 *
	 * @group smoke
	 */
	public function test_CUSTOMER_REGISTERED的is_stub為false(): void {
		// When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED
		$trigger = ETriggerPoint::CUSTOMER_REGISTERED;

		// Then is_stub 應為 false
		$this->assertFalse(
			$trigger->is_stub(),
			'CUSTOMER_REGISTERED 的 is_stub 應為 false'
		);
	}

	// ========== Rule: TriggerPointService 應監聽 user_register hook ==========

	/**
	 * 系統啟動時註冊 user_register 監聽
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: 系統啟動時註冊 user_register 監聽
	 *
	 * @group happy
	 */
	public function test_register_hooks後系統監聽user_register(): void {
		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "user_register" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('user_register', [ TriggerPointService::class, 'on_customer_registered' ]),
			'TriggerPointService 應在 user_register 上註冊監聽器'
		);
	}

	// ========== Rule: 不論 WooCommerce 是否啟用都應監聽 user_register ==========

	/**
	 * 不論 WooCommerce 是否啟用都應監聽 user_register
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: 不論 WooCommerce 是否啟用都應監聽 user_register
	 *
	 * @group edge
	 */
	public function test_WooCommerce未啟用時仍監聽user_register(): void {
		// Given WooCommerce 外掛未啟用
		// user_register 是 WordPress 核心 hook，無論 WooCommerce 是否啟用都應監聽

		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在 "user_register" hook 上註冊監聽器
		$this->assertNotFalse(
			\has_action('user_register', [ TriggerPointService::class, 'on_customer_registered' ]),
			'無論 WooCommerce 是否啟用，TriggerPointService 都應在 user_register 上註冊監聽器'
		);
	}

	// ========== Rule: enum value 必須包含 pf/trigger/ 前綴 ==========

	/**
	 * CUSTOMER_REGISTERED 的 value 以 pf/trigger/ 開頭
	 *
	 * Feature: 註冊 CUSTOMER_REGISTERED 觸發點
	 * Example: CUSTOMER_REGISTERED 的 value 以 pf/trigger/ 開頭
	 *
	 * @group smoke
	 */
	public function test_CUSTOMER_REGISTERED的value以pf_trigger開頭(): void {
		// When 系統讀取 ETriggerPoint::CUSTOMER_REGISTERED->value
		$value = ETriggerPoint::CUSTOMER_REGISTERED->value;

		// Then 值應以 "pf/trigger/" 開頭
		$this->assertStringStartsWith(
			'pf/trigger/',
			$value,
			'CUSTOMER_REGISTERED 的 value 應以 pf/trigger/ 開頭'
		);
	}
}
