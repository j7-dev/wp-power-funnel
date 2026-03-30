<?php

/**
 * 報名狀態觸發點整合測試。
 *
 * 驗證報名狀態變更時能正確觸發對應的 pf/trigger/* hook。
 *
 * @group trigger-points
 * @group registration-trigger
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 報名狀態觸發點測試
 *
 * Feature: 報名審核通過後觸發工作流
 * Feature: 報名被拒絕後觸發工作流
 * Feature: 報名取消後觸發工作流
 * Feature: 報名失敗後觸發工作流
 */
class RegistrationTriggerTest extends IntegrationTestCase {

	/** @var array<string, array<string, mixed>> 已觸發的事件記錄 */
	private array $fired_triggers = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_triggers = [];
		$this->remove_registration_side_effect_hooks();

		// 監聽所有相關觸發點
		foreach ([
			ETriggerPoint::REGISTRATION_APPROVED->value,
			ETriggerPoint::REGISTRATION_REJECTED->value,
			ETriggerPoint::REGISTRATION_CANCELLED->value,
			ETriggerPoint::REGISTRATION_FAILED->value,
		] as $hook) {
			$short_name                         = str_replace('pf/trigger/', '', $hook);
			$this->fired_triggers[ $short_name ] = [];
			\add_action(
				$hook,
				/**
				 * @param array<string, mixed> $context_callable_set
				 */
				function ( array $context_callable_set ) use ( $short_name ): void {
					$this->fired_triggers[ $short_name ][] = $context_callable_set;
				},
				999
			);
		}
	}

	// ========== Rule: 報名狀態變更時觸發對應的 pf/trigger/* hook ==========

	/**
	 * Feature: 報名審核通過後觸發工作流
	 * Example: 報名狀態從 pending 變更為 success 時觸發 registration_approved
	 *
	 * @group happy
	 */
	public function test_報名成功時觸發registration_approved(): void {
		// Given 一筆 pending 狀態的報名記錄
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);

		// When 報名狀態從 pending 更新為 success
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'success',
		]);

		// Then pf/trigger/registration_approved 被觸發
		$this->assertCount(1, $this->fired_triggers['registration_approved'], 'registration_approved 應被觸發一次');
		$this->assertEmpty($this->fired_triggers['registration_rejected'], 'registration_rejected 不應被觸發');
	}

	/**
	 * Feature: 報名被拒絕後觸發工作流
	 * Example: 報名狀態從 pending 變更為 rejected 時觸發 registration_rejected
	 *
	 * @group happy
	 */
	public function test_報名被拒絕時觸發registration_rejected(): void {
		// Given 一筆 pending 狀態的報名記錄
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);

		// When 報名狀態從 pending 更新為 rejected
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'rejected',
		]);

		// Then pf/trigger/registration_rejected 被觸發
		$this->assertCount(1, $this->fired_triggers['registration_rejected'], 'registration_rejected 應被觸發一次');
		$this->assertEmpty($this->fired_triggers['registration_approved'], 'registration_approved 不應被觸發');
	}

	/**
	 * Feature: 報名取消後觸發工作流
	 * Example: 報名狀態從 pending 變更為 cancelled 時觸發 registration_cancelled
	 *
	 * @group happy
	 */
	public function test_報名取消時觸發registration_cancelled(): void {
		// Given 一筆 pending 狀態的報名記錄
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);

		// When 報名狀態從 pending 更新為 cancelled
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'cancelled',
		]);

		// Then pf/trigger/registration_cancelled 被觸發
		$this->assertCount(1, $this->fired_triggers['registration_cancelled'], 'registration_cancelled 應被觸發一次');
	}

	/**
	 * Feature: 報名失敗後觸發工作流
	 * Example: 報名狀態從 pending 變更為 failed 時觸發 registration_failed
	 *
	 * @group happy
	 */
	public function test_報名失敗時觸發registration_failed(): void {
		// Given 一筆 pending 狀態的報名記錄
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);

		// When 報名狀態從 pending 更新為 failed
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'failed',
		]);

		// Then pf/trigger/registration_failed 被觸發
		$this->assertCount(1, $this->fired_triggers['registration_failed'], 'registration_failed 應被觸發一次');
	}

	// ========== Rule: context_callable_set 包含正確的欄位 ==========

	/**
	 * Example: context_callable 回傳包含 registration_id、identity_id、identity_provider、activity_id、promo_link_id
	 *
	 * @group happy
	 */
	public function test_context_callable_set包含正確欄位(): void {
		// Given 一筆包含完整 meta 的報名記錄
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);
		\update_post_meta($registration_id, 'identity_id', 'U123abc');
		\update_post_meta($registration_id, 'identity_provider', 'line');
		\update_post_meta($registration_id, 'activity_id', 'yt001');
		\update_post_meta($registration_id, 'promo_link_id', '456');

		// When 報名狀態從 pending 更新為 success
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'success',
		]);

		// Then context_callable_set 被觸發
		$this->assertCount(1, $this->fired_triggers['registration_approved'], 'registration_approved 應被觸發');

		$context_callable_set = $this->fired_triggers['registration_approved'][0];
		$this->assertIsArray($context_callable_set, 'context_callable_set 應為陣列');
		$this->assertArrayHasKey('callable', $context_callable_set, '應有 callable');
		$this->assertArrayHasKey('params', $context_callable_set, '應有 params');

		// Then callable 回傳包含正確欄位
		$context = ($context_callable_set['callable'])(...$context_callable_set['params']);
		$this->assertIsArray($context, 'context 應為陣列');
		$this->assertSame((string) $registration_id, $context['registration_id'], 'registration_id 應相符');
		$this->assertSame('U123abc', $context['identity_id'], 'identity_id 應相符');
		$this->assertSame('line', $context['identity_provider'], 'identity_provider 應相符');
		$this->assertSame('yt001', $context['activity_id'], 'activity_id 應相符');
		$this->assertSame('456', $context['promo_link_id'], 'promo_link_id 應相符');
	}

	// ========== Rule: 同狀態轉換不觸發 ==========

	/**
	 * Example: 報名狀態從 success 轉 success 時不觸發（同狀態轉換）
	 *
	 * @group edge
	 */
	public function test_同狀態轉換不觸發(): void {
		// Given 一筆 success 狀態的報名記錄（使用繞過 hooks 的方式直接設定狀態）
		$registration_id = $this->create_registration([ 'post_status' => 'pending' ]);
		$this->set_post_status_bypass_hooks($registration_id, 'success');

		// When 報名狀態從 success 更新為 success（同狀態）
		\wp_update_post([
			'ID'          => $registration_id,
			'post_status' => 'success',
		]);

		// Then registration_approved 不被觸發（因為 success -> success 是同狀態）
		$this->assertEmpty($this->fired_triggers['registration_approved'], '同狀態轉換不應觸發');
	}
}
