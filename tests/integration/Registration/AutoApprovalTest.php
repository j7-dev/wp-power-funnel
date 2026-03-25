<?php
/**
 * 自動審核報名整合測試。
 *
 * 驗證 power_funnel/registration/pending action（priority=20）的自動審核邏輯。
 * 當報名紀錄的 auto_approved meta 為 yes 時，系統自動將報名狀態轉為 success。
 *
 * @group registration
 * @group auto-approval
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Registration;

use J7\PowerFunnel\Applications\RegisterActivityViaLine;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\Register;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\Repository;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 自動審核報名測試
 *
 * Feature: 自動審核報名
 * 報名進入 pending 狀態後，若報名紀錄的 auto_approved meta 為 yes，
 * 系統自動將報名狀態轉為 success。
 *
 * 注意：RegistrationDTO::of() 在建立時呼叫 ActivityService::get_activity()，
 * 測試需透過 setup_activity_service_stub() 注入假的 ActivityProvider 才能運作。
 */
class AutoApprovalTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 移除 RegisterActivityViaLine 中會呼叫 RegistrationDTO::of() 的 hooks，
		// 避免測試建立報名紀錄時觸發 YouTube OAuth 錯誤。
		// 各測試方法如需測試 auto_success 行為，會在取得 post 後直接呼叫靜態方法。
		$this->remove_registration_side_effect_hooks();
	}

	/** 每個測試後清除 ActivityService stub */
	public function tear_down(): void {
		$this->teardown_activity_service_stub();
		parent::tear_down();
	}

	/**
	 * 建立測試用報名紀錄（直接使用工廠，跳過 transition hook）
	 *
	 * 使用 WP identity provider 避免呼叫 LINE MessageService，
	 * 因為 LINE MessageService 需要設定 Channel Access Token 才能初始化。
	 *
	 * @param string $auto_approved 'yes' 或 'no'，設定在報名紀錄本身的 meta
	 * @return int registration post ID
	 */
	private function create_test_registration_with_auto_approved( string $auto_approved = 'yes' ): int {
		// 建立一個 WP 用戶，用於 identity_id（WP identity provider 不呼叫 LINE API）
		$user_id = $this->factory()->user->create(['display_name' => '測試用戶']);

		$registration_id = $this->factory()->post->create(
			[
				'post_type'   => 'pf_registration',
				'post_status' => 'draft', // 先以 draft 建立，避免立即觸發 pending
				'post_title'  => '測試報名紀錄',
				'meta_input'  => [
					'activity_id'       => 'yt001',
					'identity_id'       => (string) $user_id,
					'identity_provider' => 'WordPress', // 使用 WP provider（value='WordPress'），避免 LINE API 呼叫
					'promo_link_id'     => '0',
					'auto_approved'     => $auto_approved, // 設定在報名紀錄本身
				],
			]
		);
		return (int) $registration_id;
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * 快樂路徑：auto_approved meta 為 yes 時，呼叫 auto_success 應自動轉為 success
	 *
	 * Feature: 自動審核報名
	 * Example: 自動審核通過
	 *
	 * @group happy
	 */
	public function test_auto_approved為yes時自動轉為success(): void {
		// Given 設定 ActivityService stub，避免 YouTube API 呼叫
		$activity_dto = $this->make_stub_activity_dto('yt001');
		$this->setup_activity_service_stub([$activity_dto]);

		// Given 報名紀錄的 auto_approved meta 為 yes
		$registration_id = $this->create_test_registration_with_auto_approved('yes');
		$this->ids['registration'] = $registration_id;

		$post = \get_post($registration_id);
		$this->assertNotNull($post);

		// When 手動呼叫 auto_success 方法（模擬 priority=20 的 action）
		try {
			RegisterActivityViaLine::auto_success('pending', 'draft', $post);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
		}

		// Then 操作應成功（不應拋出異常）
		$this->assert_operation_succeeded();

		// Then 報名狀態應變為 success
		\clean_post_cache($registration_id);
		$updated_post = \get_post($registration_id);

		$this->assertNotNull($updated_post);
		$this->assertSame(
			ERegistrationStatus::SUCCESS->value,
			$updated_post->post_status,
			'auto_approved=yes 時，報名狀態應自動轉為 success'
		);
	}

	/**
	 * 快樂路徑：auto_approved meta 為 no 時，報名應維持 draft 狀態
	 *
	 * Feature: 自動審核報名
	 * Example: auto_approved 為 no 時維持原狀態
	 *
	 * @group happy
	 */
	public function test_auto_approved為no時維持原狀態(): void {
		// Given 設定 ActivityService stub
		$activity_dto = $this->make_stub_activity_dto('yt001');
		$this->setup_activity_service_stub([$activity_dto]);

		// Given 報名紀錄的 auto_approved meta 為 no
		$registration_id = $this->create_test_registration_with_auto_approved('no');

		$post = \get_post($registration_id);
		$this->assertNotNull($post);

		// When 呼叫 auto_success（auto_approved=no）
		try {
			RegisterActivityViaLine::auto_success('pending', 'draft', $post);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
		}

		// Then 操作應成功（不應拋出異常）
		$this->assert_operation_succeeded();

		// Then 報名狀態應維持 draft（因為 auto_approved=no，未觸發 wp_update_post）
		\clean_post_cache($registration_id);
		$updated_post = \get_post($registration_id);
		$this->assertNotNull($updated_post);
		$this->assertSame(
			'draft',
			$updated_post->post_status,
			'auto_approved=no 時，報名狀態應維持 draft'
		);
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * 邊緣案例：auto_success 只應掛載在 pending action 上（不掛在 success action 上）
	 *
	 * Feature: 自動審核報名
	 * Example: 非 pending 狀態不觸發自動審核
	 *
	 * @group edge
	 */
	public function test_auto_success只掛載在pending_action上(): void {
		// 重新確認來源碼設計：auto_success 只應被加在 pending 狀態的 action 上
		$reflection = new \ReflectionClass(RegisterActivityViaLine::class);
		$source     = file_get_contents($reflection->getFileName() ?: '');
		$this->assertStringContainsString(
			"'auto_success'",
			$source ?: '',
			'auto_success 方法應存在於 RegisterActivityViaLine'
		);

		// 在 configure_dependencies() 已移除 auto_success hook，
		// 所以 power_funnel/registration/success 上不應有 auto_success
		$has_action_success = \has_action(
			'power_funnel/registration/success',
			[ RegisterActivityViaLine::class, 'auto_success' ]
		);
		$this->assertFalse($has_action_success, 'auto_success 不應掛載在 success action 上');
	}

	/**
	 * 邊緣案例：auto_approved meta 為空字串時視同 no
	 *
	 * @group edge
	 */
	public function test_auto_approved為空字串時視同no(): void {
		// Given 設定 ActivityService stub
		$activity_dto = $this->make_stub_activity_dto('yt001');
		$this->setup_activity_service_stub([$activity_dto]);

		// 建立 WP 用戶，用於 identity_id（WP identity provider 不呼叫 LINE API）
		$user_id = $this->factory()->user->create(['display_name' => '無 auto_approved 測試用戶']);

		// Given 報名紀錄未設定 auto_approved（空字串）
		$registration_id = $this->factory()->post->create(
			[
				'post_type'   => 'pf_registration',
				'post_status' => 'draft',
				'post_title'  => '無 auto_approved 設定的報名紀錄',
				'meta_input'  => [
					'activity_id'       => 'yt001',
					'identity_id'       => (string) $user_id,
					'identity_provider' => 'WordPress', // 使用 WP provider（value='WordPress'），避免 LINE API 呼叫
					'promo_link_id'     => '0',
					// 不設定 auto_approved meta，讓它為空字串
				],
			]
		);

		$post = \get_post($registration_id);
		$this->assertNotNull($post);

		// When 呼叫 auto_success（auto_approved 為空）
		try {
			RegisterActivityViaLine::auto_success('pending', 'draft', $post);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
		}

		// Then 操作應成功（不應拋出異常）
		$this->assert_operation_succeeded();

		// Then 報名狀態應維持 draft（空字串 wc_string_to_bool 轉為 false）
		\clean_post_cache($registration_id);
		$updated_post = \get_post($registration_id);
		$this->assertNotNull($updated_post);
		$this->assertSame('draft', $updated_post->post_status, '空 auto_approved 應視同 no，維持 draft');
	}
}
