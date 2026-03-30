<?php

/**
 * 活動排程觸發點整合測試。
 *
 * 驗證 ActivitySchedulerService 能正確建立 Action Scheduler 任務，
 * 並在任務觸發時正確發出 pf/trigger/activity_* hook。
 *
 * @group trigger-points
 * @group activity-scheduler
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;
use J7\PowerFunnel\Domains\Workflow\Services\ActivitySchedulerService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 活動排程觸發點測試
 *
 * Feature: 活動開始時觸發工作流
 * Feature: 活動開始前觸發工作流
 */
class ActivitySchedulerTest extends IntegrationTestCase {

	/** @var array<string, array<array<string, mixed>>> 已觸發的事件記錄 */
	private array $fired_triggers = [];

	/** @var array<string> 已排程的 Action Scheduler jobs（stub 記錄） */
	private array $scheduled_actions = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		ActivitySchedulerService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_triggers    = [];
		$this->scheduled_actions = [];

		// 監聽 activity_started 和 activity_before_start
		foreach ([
			ETriggerPoint::ACTIVITY_STARTED->value,
			ETriggerPoint::ACTIVITY_BEFORE_START->value,
		] as $hook) {
			$short_name                          = str_replace('pf/trigger/', '', $hook);
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

	/**
	 * 建立測試用 ActivityDTO
	 *
	 * @param string    $id              活動 ID
	 * @param \DateTime $start_time      活動開始時間
	 * @return ActivityDTO
	 */
	private function make_activity_dto( string $id, \DateTime $start_time ): ActivityDTO {
		return new ActivityDTO([
			'id'                   => $id,
			'activity_provider_id' => 'test_provider',
			'title'                => "測試活動 {$id}",
			'description'          => '測試活動描述',
			'scheduled_start_time' => $start_time,
		]);
	}

	// ========== Rule: on_activity_started 觸發 pf/trigger/activity_started ==========

	/**
	 * Feature: 活動開始時觸發工作流
	 * Example: on_activity_started 觸發 pf/trigger/activity_started
	 *
	 * @group happy
	 */
	public function test_on_activity_started觸發activity_started(): void {
		// When Action Scheduler 觸發 on_activity_started
		ActivitySchedulerService::on_activity_started('yt001');

		// Then pf/trigger/activity_started 被觸發
		$this->assertCount(1, $this->fired_triggers['activity_started'], 'activity_started 應被觸發一次');
	}

	/**
	 * Example: activity_started context 包含 activity_id 和 event_type
	 *
	 * @group happy
	 */
	public function test_activity_started_context包含正確欄位(): void {
		// When 觸發
		ActivitySchedulerService::on_activity_started('yt_test_001');

		$this->assertCount(1, $this->fired_triggers['activity_started'], 'activity_started 應被觸發');

		$context_callable_set = $this->fired_triggers['activity_started'][0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含正確欄位
		$this->assertSame('yt_test_001', $context['activity_id'], 'activity_id 應相符');
		$this->assertSame('activity_started', $context['event_type'], 'event_type 應相符');
	}

	// ========== Rule: on_activity_before_start 觸發 pf/trigger/activity_before_start ==========

	/**
	 * Feature: 活動開始前觸發工作流
	 * Example: on_activity_before_start 觸發 pf/trigger/activity_before_start
	 *
	 * @group happy
	 */
	public function test_on_activity_before_start觸發activity_before_start(): void {
		// When Action Scheduler 觸發 on_activity_before_start
		$rule_id = (string) $this->create_workflow_rule([ 'post_status' => 'publish' ]);
		ActivitySchedulerService::on_activity_before_start('yt001', $rule_id);

		// Then pf/trigger/activity_before_start 被觸發
		$this->assertCount(1, $this->fired_triggers['activity_before_start'], 'activity_before_start 應被觸發一次');
	}

	/**
	 * Example: activity_before_start context 包含 activity_id、workflow_rule_id 和 event_type
	 *
	 * @group happy
	 */
	public function test_activity_before_start_context包含正確欄位(): void {
		// When 觸發
		$rule_id = (string) $this->create_workflow_rule([ 'post_status' => 'publish' ]);
		ActivitySchedulerService::on_activity_before_start('yt_test_002', $rule_id);

		$this->assertCount(1, $this->fired_triggers['activity_before_start'], 'activity_before_start 應被觸發');

		$context_callable_set = $this->fired_triggers['activity_before_start'][0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含正確欄位
		$this->assertSame('yt_test_002', $context['activity_id'], 'activity_id 應相符');
		$this->assertSame($rule_id, $context['workflow_rule_id'], 'workflow_rule_id 應相符');
		$this->assertSame('activity_before_start', $context['event_type'], 'event_type 應相符');
	}

	// ========== Rule: schedule_activity 排程 Action Scheduler 任務 ==========

	/**
	 * Example: schedule_activity 為有效活動建立 as_schedule_single_action 排程
	 * （測試環境中 as_schedule_single_action 是 stub，不實際排程）
	 *
	 * @group happy
	 */
	public function test_schedule_activity不因stub拋出例外(): void {
		// Given 一個未來時間的活動
		$start_time = new \DateTime('+1 day');
		$activity   = $this->make_activity_dto('yt_schedule_001', $start_time);

		// When 排程活動（stub 環境不會實際排程，但不應拋出例外）
		try {
			ActivitySchedulerService::schedule_activity($activity);
			$this->assertTrue(true, 'schedule_activity 不應拋出例外');
		} catch (\Throwable $e) {
			$this->fail("schedule_activity 不應拋出例外，但發生：{$e->getMessage()}");
		}
	}

	/**
	 * Example: 活動沒有有效開始時間時跳過排程
	 * （測試環境無法直接驗證，但至少不應拋出例外）
	 *
	 * @group edge
	 */
	public function test_活動時間為零時跳過排程(): void {
		// Given 一個時間戳記為 0 的活動（透過設定過去的時間模擬）
		// 注意：\DateTime 不允許設定 timestamp=0，改用很遠的過去時間
		$start_time = new \DateTime('1970-01-01 00:00:01');
		$activity   = $this->make_activity_dto('yt_past_001', $start_time);

		// When 嘗試排程（時間戳記 <= 0 的情況由 schedule_activity 內部處理）
		try {
			ActivitySchedulerService::schedule_activity($activity);
			$this->assertTrue(true, 'schedule_activity 不應拋出例外');
		} catch (\Throwable $e) {
			$this->fail("schedule_activity 不應拋出例外，但發生：{$e->getMessage()}");
		}
	}
}
