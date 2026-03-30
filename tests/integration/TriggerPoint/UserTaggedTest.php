<?php

/**
 * 用戶標籤觸發點整合測試。
 *
 * 驗證 TriggerPointService::fire_user_tagged 能正確觸發 pf/trigger/user_tagged hook。
 *
 * @group trigger-points
 * @group user-tagged
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 用戶標籤觸發點測試
 *
 * Feature: 用戶被貼標籤後觸發工作流
 */
class UserTaggedTest extends IntegrationTestCase {

	/** @var array<array<string, mixed>> 已觸發的事件記錄 */
	private array $fired_user_tagged = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 不需要特別初始化
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_user_tagged = [];

		// 監聽 user_tagged
		\add_action(
			ETriggerPoint::USER_TAGGED->value,
			/**
			 * @param array<string, mixed> $context_callable_set
			 */
			function ( array $context_callable_set ): void {
				$this->fired_user_tagged[] = $context_callable_set;
			},
			999
		);
	}

	// ========== Rule: fire_user_tagged 觸發 pf/trigger/user_tagged ==========

	/**
	 * Feature: 用戶被貼標籤後觸發工作流
	 * Example: fire_user_tagged 觸發 pf/trigger/user_tagged
	 *
	 * @group happy
	 */
	public function test_fire_user_tagged觸發user_tagged(): void {
		// When 呼叫 fire_user_tagged
		TriggerPointService::fire_user_tagged('U_user_123', 'VIP');

		// Then pf/trigger/user_tagged 被觸發
		$this->assertCount(1, $this->fired_user_tagged, 'user_tagged 應被觸發一次');
	}

	/**
	 * Example: user_tagged context 包含正確的 user_id 和 tag_name
	 *
	 * @group happy
	 */
	public function test_user_tagged_context包含正確欄位(): void {
		// When 呼叫 fire_user_tagged
		TriggerPointService::fire_user_tagged('U_tag_user', 'Gold Member');

		$this->assertCount(1, $this->fired_user_tagged, 'user_tagged 應被觸發');

		$context_callable_set = $this->fired_user_tagged[0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含正確欄位
		$this->assertSame('U_tag_user', $context['user_id'], 'user_id 應相符');
		$this->assertSame('Gold Member', $context['tag_name'], 'tag_name 應相符');
	}

	/**
	 * Example: 連續呼叫兩次 fire_user_tagged 觸發兩次
	 *
	 * @group happy
	 */
	public function test_多次呼叫觸發多次(): void {
		// When 呼叫兩次
		TriggerPointService::fire_user_tagged('U_user_A', 'Tag1');
		TriggerPointService::fire_user_tagged('U_user_B', 'Tag2');

		// Then 觸發兩次
		$this->assertCount(2, $this->fired_user_tagged, 'user_tagged 應被觸發兩次');
	}
}
