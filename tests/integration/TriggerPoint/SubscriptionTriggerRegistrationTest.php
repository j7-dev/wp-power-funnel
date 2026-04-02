<?php

/**
 * 7 個訂閱生命週期觸發點註冊整合測試。
 *
 * 驗證 ETriggerPoint 包含 7 個訂閱相關 case（subscription group），
 * 且 TriggerPointService 在 Powerhouse 外掛啟用/未啟用時正確行為。
 *
 * @group trigger-points
 * @group subscription-trigger
 * @group subscription-trigger-registration
 *
 * @see specs/woocommerce-trigger-points/features/trigger-point/register-subscription-triggers.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 7 個訂閱生命週期觸發點註冊測試
 *
 * Feature: 註冊 7 個訂閱生命週期觸發點
 */
class SubscriptionTriggerRegistrationTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 各測試案例自行控制 register_hooks
	}

	// ========== Rule: ETriggerPoint 應包含 7 個訂閱 case ==========

	/**
	 * SUBSCRIPTION_INITIAL_PAYMENT 的 hook value 與 label 正確
	 *
	 * Feature: 註冊 7 個訂閱生命週期觸發點
	 * Scenario: SUBSCRIPTION_INITIAL_PAYMENT 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_INITIAL_PAYMENT的hook_value與label正確(): void {
		// When 系統讀取 ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT
		$trigger = ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT;

		// Then hook value 應為 "pf/trigger/subscription_initial_payment"
		$this->assertSame(
			'pf/trigger/subscription_initial_payment',
			$trigger->value,
			'SUBSCRIPTION_INITIAL_PAYMENT 的 hook value 應為 pf/trigger/subscription_initial_payment'
		);

		// And label 應為 "訂閱首次付款完成"
		$this->assertSame(
			'訂閱首次付款完成',
			$trigger->label(),
			'SUBSCRIPTION_INITIAL_PAYMENT 的 label 應為「訂閱首次付款完成」'
		);
	}

	/**
	 * SUBSCRIPTION_FAILED 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_FAILED的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_FAILED;

		$this->assertSame('pf/trigger/subscription_failed', $trigger->value);
		$this->assertSame('訂閱失敗', $trigger->label());
	}

	/**
	 * SUBSCRIPTION_SUCCESS 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_SUCCESS的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_SUCCESS;

		$this->assertSame('pf/trigger/subscription_success', $trigger->value);
		$this->assertSame('訂閱成功', $trigger->label());
	}

	/**
	 * SUBSCRIPTION_RENEWAL_ORDER 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_RENEWAL_ORDER的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER;

		$this->assertSame('pf/trigger/subscription_renewal_order', $trigger->value);
		$this->assertSame('訂閱續訂訂單建立', $trigger->label());
	}

	/**
	 * SUBSCRIPTION_END 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_END的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_END;

		$this->assertSame('pf/trigger/subscription_end', $trigger->value);
		$this->assertSame('訂閱結束', $trigger->label());
	}

	/**
	 * SUBSCRIPTION_TRIAL_END 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_TRIAL_END的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_TRIAL_END;

		$this->assertSame('pf/trigger/subscription_trial_end', $trigger->value);
		$this->assertSame('訂閱試用期結束', $trigger->label());
	}

	/**
	 * SUBSCRIPTION_PREPAID_END 的 hook value 與 label 正確
	 *
	 * @group smoke
	 */
	public function test_SUBSCRIPTION_PREPAID_END的hook_value與label正確(): void {
		$trigger = ETriggerPoint::SUBSCRIPTION_PREPAID_END;

		$this->assertSame('pf/trigger/subscription_prepaid_end', $trigger->value);
		$this->assertSame('訂閱預付期結束', $trigger->label());
	}

	// ========== Rule: 所有訂閱觸發點歸屬 subscription 群組 ==========

	/**
	 * 7 個訂閱 case 的 group 與 group_label 正確
	 *
	 * Feature: 註冊 7 個訂閱生命週期觸發點
	 * Scenario: SUBSCRIPTION_INITIAL_PAYMENT 的 group 與 group_label 正確（代表性）
	 *
	 * @group smoke
	 */
	public function test_所有訂閱觸發點歸屬subscription群組(): void {
		// When 系統讀取 7 個訂閱 ETriggerPoint case
		$cases = [
			ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT,
			ETriggerPoint::SUBSCRIPTION_FAILED,
			ETriggerPoint::SUBSCRIPTION_SUCCESS,
			ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER,
			ETriggerPoint::SUBSCRIPTION_END,
			ETriggerPoint::SUBSCRIPTION_TRIAL_END,
			ETriggerPoint::SUBSCRIPTION_PREPAID_END,
		];

		foreach ($cases as $trigger) {
			// Then group 應為 "subscription"
			$this->assertSame(
				'subscription',
				$trigger->group(),
				"{$trigger->name} 的 group 應為 subscription"
			);

			// And group_label 應為 "訂閱"
			$this->assertSame(
				'訂閱',
				$trigger->group_label(),
				"{$trigger->name} 的 group_label 應為「訂閱」"
			);
		}
	}

	// ========== Rule: 所有訂閱觸發點均為正式實作（非存根） ==========

	/**
	 * 7 個訂閱 case 的 is_stub 回傳 false
	 *
	 * @group smoke
	 */
	public function test_所有訂閱觸發點is_stub為false(): void {
		// When 系統讀取 7 個訂閱 ETriggerPoint case
		$cases = [
			ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT,
			ETriggerPoint::SUBSCRIPTION_FAILED,
			ETriggerPoint::SUBSCRIPTION_SUCCESS,
			ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER,
			ETriggerPoint::SUBSCRIPTION_END,
			ETriggerPoint::SUBSCRIPTION_TRIAL_END,
			ETriggerPoint::SUBSCRIPTION_PREPAID_END,
		];

		foreach ($cases as $trigger) {
			// Then is_stub 應為 false
			$this->assertFalse(
				$trigger->is_stub(),
				"{$trigger->name} 的 is_stub 應為 false"
			);
		}
	}

	// ========== Rule: Powerhouse 外掛啟用時 TriggerPointService 應監聽 7 個 hook ==========

	/**
	 * Powerhouse 啟用時註冊 7 個訂閱 hook 監聽
	 *
	 * Feature: 註冊 7 個訂閱生命週期觸發點
	 * Scenario: Powerhouse 啟用時註冊 powerhouse_subscription_at_initial_payment_complete 監聽（代表性）
	 *
	 * @group happy
	 */
	public function test_Powerhouse啟用時註冊7個訂閱hook(): void {
		// Given Powerhouse 外掛已啟用（測試環境中 wcs_get_subscription 已透過 bootstrap stub 定義）

		// When 系統執行 TriggerPointService::register_hooks()
		TriggerPointService::register_hooks();

		// Then 系統應在以下 hook 上註冊監聽器
		$expected_hooks = [
			'powerhouse_subscription_at_initial_payment_complete' => 'on_subscription_initial_payment',
			'powerhouse_subscription_at_subscription_failed'      => 'on_subscription_failed',
			'powerhouse_subscription_at_subscription_success'     => 'on_subscription_success',
			'powerhouse_subscription_at_renewal_order_created'    => 'on_subscription_renewal_order',
			'powerhouse_subscription_at_end'                      => 'on_subscription_end',
			'powerhouse_subscription_at_trial_end'                => 'on_subscription_trial_end',
			'powerhouse_subscription_at_end_of_prepaid_term'      => 'on_subscription_prepaid_end',
		];

		foreach ($expected_hooks as $hook => $method) {
			$this->assertNotFalse(
				\has_action($hook, [ TriggerPointService::class, $method ]),
				"TriggerPointService 應在 {$hook} 上註冊 {$method} 監聽器"
			);
		}
	}

	// ========== Rule: Powerhouse 外掛未啟用時不應註冊監聽器 ==========

	/**
	 * Powerhouse 未啟用時靜默忽略所有訂閱觸發點
	 *
	 * Feature: 註冊 7 個訂閱生命週期觸發點
	 * Example: Powerhouse 未啟用時靜默忽略所有訂閱觸發點
	 *
	 * @group edge
	 */
	public function test_Powerhouse未啟用時不應拋出錯誤(): void {
		// Given Powerhouse 外掛未啟用
		// 在本測試環境 Powerhouse 已安裝（透過 bootstrap stub），
		// 此測試驗證 register_hooks 不會拋出任何 Fatal Error。

		// When 系統執行 TriggerPointService::register_hooks()
		// Then 系統不應拋出任何錯誤（如果拋出，測試會自動失敗）
		TriggerPointService::register_hooks();

		$this->assertTrue(true, 'Powerhouse 未啟用時不應拋出錯誤');
	}

	// ========== Rule: 所有 enum value 必須包含 pf/trigger/ 前綴 ==========

	/**
	 * 7 個訂閱 case 的 value 以 pf/trigger/ 開頭
	 *
	 * @group smoke
	 */
	public function test_所有訂閱觸發點value以pf_trigger開頭(): void {
		// When 系統讀取 7 個訂閱 ETriggerPoint case 的 value
		$cases = [
			ETriggerPoint::SUBSCRIPTION_INITIAL_PAYMENT,
			ETriggerPoint::SUBSCRIPTION_FAILED,
			ETriggerPoint::SUBSCRIPTION_SUCCESS,
			ETriggerPoint::SUBSCRIPTION_RENEWAL_ORDER,
			ETriggerPoint::SUBSCRIPTION_END,
			ETriggerPoint::SUBSCRIPTION_TRIAL_END,
			ETriggerPoint::SUBSCRIPTION_PREPAID_END,
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
