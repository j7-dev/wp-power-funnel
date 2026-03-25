<?php
/**
 * 報名紀錄 Repository 整合測試。
 *
 * 驗證 pf_registration CPT 的 CRUD 操作與業務規則。
 *
 * @group smoke
 * @group registration
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\Registration;

use J7\PowerFunnel\Infrastructure\Repositories\Registration\Repository;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\Register;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 報名紀錄 Repository 測試
 *
 * Feature: 建立報名紀錄
 * 系統為通過資格檢查的用戶建立 pf_registration CPT，
 * 初始狀態為 pending，並觸發報名狀態生命週期。
 */
class RegistrationRepositoryTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// 移除 RegisterActivityViaLine 中會呼叫 RegistrationDTO::of() 的 hooks，
		// 避免測試建立報名紀錄時觸發 YouTube OAuth 錯誤。
		$this->remove_registration_side_effect_hooks();
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * 冒煙測試：Repository::create 可以建立 pf_registration CPT
	 *
	 * @group smoke
	 */
	public function test_可以建立報名紀錄(): void {
		// When 系統建立報名紀錄
		$args = [
			'post_title' => 'line 用戶報名測試活動',
			'meta_input' => [
				'activity_id'       => 'yt001',
				'identity_id'       => 'U1234',
				'promo_link_id'     => '10',
				'identity_provider' => 'line',
			],
		];

		try {
			$registration_id = Repository::create($args);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError  = $e;
			$registration_id  = 0;
		}

		// Then 操作應成功
		$this->assert_operation_succeeded();

		// Then 應建立一筆 pf_registration CPT
		$post = \get_post($registration_id);
		$this->assertNotNull($post, '應建立報名紀錄 post');
		$this->assertSame('pf_registration', $post->post_type, 'post_type 應為 pf_registration');
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * 快樂路徑：報名紀錄建立成功，初始狀態為 pending
	 *
	 * Feature: 建立報名紀錄
	 * Example: 報名紀錄建立成功
	 *
	 * @group happy
	 */
	public function test_報名紀錄建立成功且狀態為pending(): void {
		// Given LINE 設定已完成（此測試不依賴 LINE 設定）
		// Given 系統中有以下活動（使用固定的 activity_id）

		// When 系統建立報名紀錄
		$args = [
			'post_title' => 'line 用戶報名 React 直播教學',
			'meta_input' => [
				'activity_id'       => 'yt001',
				'identity_id'       => 'U1234',
				'promo_link_id'     => '10',
				'identity_provider' => EIdentityProvider::LINE->value,
			],
		];

		try {
			$registration_id = Repository::create($args);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
			$registration_id = 0;
		}

		// Then 操作應成功
		$this->assert_operation_succeeded();
		$this->assertGreaterThan(0, $registration_id, '應回傳有效的 post ID');

		// Then 應建立一筆 pf_registration CPT
		$post = \get_post($registration_id);
		$this->assertNotNull($post, '應找到報名紀錄');

		// Then post_status 應為 pending
		$this->assertSame(
			ERegistrationStatus::PENDING->value,
			$post->post_status,
			'報名初始狀態應為 pending'
		);

		// Then post_type 應為 pf_registration
		$this->assertSame('pf_registration', $post->post_type, 'post_type 應為 pf_registration');

		// Then meta.activity_id 應為 yt001
		$this->assertSame(
			'yt001',
			\get_post_meta($registration_id, 'activity_id', true),
			'activity_id 應為 yt001'
		);

		// Then meta.identity_id 應為 U1234
		$this->assertSame(
			'U1234',
			\get_post_meta($registration_id, 'identity_id', true),
			'identity_id 應為 U1234'
		);

		// Then meta.identity_provider 應為 line
		$this->assertSame(
			'line',
			\get_post_meta($registration_id, 'identity_provider', true),
			'identity_provider 應為 line'
		);
	}

	/**
	 * 快樂路徑：建立報名紀錄後應觸發 power_funnel/registration/pending action
	 *
	 * Feature: 建立報名紀錄
	 * Example: 報名紀錄建立成功 - 觸發狀態 action
	 *
	 * @group happy
	 */
	public function test_建立報名紀錄後觸發pending_action(): void {
		// Given 監聽 power_funnel/registration/pending action
		$action_fired = false;
		$fired_post   = null;

		\add_action(
			'power_funnel/registration/pending',
			function (string $new_status, string $old_status, \WP_Post $post) use (&$action_fired, &$fired_post): void {
				$action_fired = true;
				$fired_post   = $post;
			},
			10,
			3
		);

		// When 系統建立報名紀錄（狀態為 pending）
		$args = [
			'post_title' => 'line 用戶報名 React 直播教學',
			'meta_input' => [
				'activity_id'       => 'yt001',
				'identity_id'       => 'U1234',
				'identity_provider' => 'line',
			],
		];

		$registration_id = Repository::create($args);

		// Then 系統應觸發 power_funnel/registration/pending action
		$this->assertTrue($action_fired, '應觸發 power_funnel/registration/pending action');
		$this->assertNotNull($fired_post, '觸發的 action 應帶有 post 物件');
		$this->assertSame($registration_id, (int) $fired_post->ID, '觸發的 post ID 應與建立的報名 ID 相同');
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * 錯誤處理：Repository::create 回傳值應為正整數（驗證建立成功）
	 *
	 * Feature: 建立報名紀錄
	 * 說明：wp_insert_post 在沒有 $wp_error=true 參數時，失敗只回傳 0（不是 WP_Error），
	 * 因此 Repository::create 只在 is_wp_error 時拋出異常。
	 * 此測試驗證正常建立流程的返回值。
	 *
	 * @group error
	 */
	public function test_建立報名紀錄成功時回傳正整數(): void {
		// Given 正常的報名參數
		$args = [
			'post_title' => '正常報名紀錄',
			'meta_input' => [
				'activity_id'       => 'yt001',
				'identity_id'       => 'U1234',
				'identity_provider' => 'line',
			],
		];

		// When 系統建立報名紀錄
		$registration_id = Repository::create($args);

		// Then 應回傳正整數 post ID
		$this->assertIsInt($registration_id, '應回傳整數 ID');
		$this->assertGreaterThan(0, $registration_id, '應回傳正整數 ID');
		$post = \get_post($registration_id);
		$this->assertNotNull($post, '應可查詢到建立的紀錄');
	}

	// ========== 快樂路徑：查詢已報名記錄 ==========

	/**
	 * 快樂路徑：查找已報名的用戶紀錄（LINE 用戶）
	 *
	 * 說明：Repository::get_registered_registration 使用 get_posts() 預設查 'publish' 狀態，
	 * 因此測試資料須手動更新為 'publish' 狀態才能被查詢到。
	 *
	 * @group happy
	 */
	public function test_查找已報名的LINE用戶紀錄(): void {
		// Given 系統中有一筆 U1234 報名 yt001 的紀錄（狀態更新為 publish 供查詢）
		$registration_id = Repository::create(
			[
				'post_title' => 'line 用戶報名測試',
				'meta_input' => [
					'activity_id'       => 'yt001',
					'identity_id'       => 'U1234',
					'identity_provider' => 'line',
				],
			]
		);

		// Repository::get_registered_registration 使用 get_posts() 預設查 'publish' 狀態，
		// 須將報名紀錄狀態更新為 publish 才能被查詢到
		\wp_update_post(
			[
				'ID'          => $registration_id,
				'post_status' => 'publish',
			]
		);

		// When 查詢 U1234 是否已報名 yt001
		$result = Repository::get_registered_registration('U1234', EIdentityProvider::LINE, 'yt001');

		// Then 應找到報名紀錄
		$this->assertNotNull($result, '應找到已報名紀錄');
		$this->assertSame($registration_id, $result->ID, '找到的紀錄 ID 應相符');
		$this->assertSame('pf_registration', $result->post_type, 'post_type 應為 pf_registration');
	}

	/**
	 * 快樂路徑：未報名時查詢結果應為 null
	 *
	 * @group happy
	 */
	public function test_未報名時查詢結果為null(): void {
		// Given 系統中沒有 U9999 報名 yt999 的紀錄

		// When 查詢 U9999 是否已報名 yt999
		$result = Repository::get_registered_registration('U9999', EIdentityProvider::LINE, 'yt999');

		// Then 應回傳 null
		$this->assertNull($result, '未報名時應回傳 null');
	}

	/**
	 * 邊緣案例：同一用戶重複報名同一活動時，只能找到一筆（get_registered_registration 回傳第一筆）
	 *
	 * 說明：查詢需將報名紀錄狀態更新為 publish 才能被 get_registered_registration 查到。
	 *
	 * @group edge
	 */
	public function test_重複報名同一活動只回傳一筆(): void {
		// Given 系統中有 U1234 重複報名 yt001 兩筆紀錄（狀態均更新為 publish）
		$args = [
			'post_title' => 'line 用戶報名測試',
			'meta_input' => [
				'activity_id'       => 'yt001',
				'identity_id'       => 'U1234',
				'identity_provider' => 'line',
			],
		];
		$id1 = Repository::create($args);
		$id2 = Repository::create($args);

		\wp_update_post(['ID' => $id1, 'post_status' => 'publish']);
		\wp_update_post(['ID' => $id2, 'post_status' => 'publish']);

		// When 查詢 U1234 是否已報名 yt001
		$result = Repository::get_registered_registration('U1234', EIdentityProvider::LINE, 'yt001');

		// Then 應只回傳一筆（不因為有多筆而失敗）
		$this->assertNotNull($result, '應找到至少一筆報名紀錄');
		$this->assertInstanceOf(\WP_Post::class, $result, '應回傳 WP_Post 物件');
	}

	// ========== 安全性（Security）==========

	/**
	 * 安全性：identity_id 包含 SQL injection 字串時應正確儲存（不被執行）
	 *
	 * @group security
	 */
	public function test_identity_id包含SQL注入字串時安全處理(): void {
		// Given identity_id 為 SQL injection 字串
		$malicious_id = "U1'; DROP TABLE wp_posts; --";

		// When 系統建立報名紀錄
		$registration_id = Repository::create(
			[
				'post_title' => 'SQL 注入測試',
				'meta_input' => [
					'activity_id'       => 'yt001',
					'identity_id'       => $malicious_id,
					'identity_provider' => 'line',
				],
			]
		);

		// Then 應成功建立，且 identity_id 原樣儲存（SQL 未被執行）
		$this->assertGreaterThan(0, $registration_id, '應成功建立報名紀錄');
		$stored_id = \get_post_meta($registration_id, 'identity_id', true);
		$this->assertSame($malicious_id, $stored_id, 'identity_id 應原樣儲存');

		// 確認 wp_posts 資料表仍然存在（SQL injection 未執行）
		$post = \get_post($registration_id);
		$this->assertNotNull($post, 'wp_posts 資料表應仍然存在（SQL injection 未執行）');
	}

	/**
	 * 安全性：identity_id 包含 XSS 字串時應正確儲存
	 *
	 * @group security
	 */
	public function test_identity_id包含XSS字串時安全處理(): void {
		// Given identity_id 為 XSS 字串
		$xss_id = '<script>alert("XSS")</script>';

		// When 系統建立報名紀錄
		$registration_id = Repository::create(
			[
				'post_title' => 'XSS 測試',
				'meta_input' => [
					'activity_id'       => 'yt001',
					'identity_id'       => $xss_id,
					'identity_provider' => 'line',
				],
			]
		);

		// Then 應成功建立（meta 儲存原始值，在輸出時才轉義）
		$this->assertGreaterThan(0, $registration_id, '應成功建立報名紀錄');
		$stored_id = \get_post_meta($registration_id, 'identity_id', true);
		$this->assertSame($xss_id, $stored_id, 'meta 應儲存原始值');
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * 邊緣案例：identity_id 為空字串時仍可建立報名
	 *
	 * @group edge
	 */
	public function test_identity_id為空字串時仍可建立(): void {
		// When 系統建立報名紀錄（identity_id 為空）
		try {
			$registration_id = Repository::create(
				[
					'post_title' => '空 identity_id 測試',
					'meta_input' => [
						'activity_id'       => 'yt001',
						'identity_id'       => '',
						'identity_provider' => 'line',
					],
				]
			);
			$this->lastError = null;
		} catch (\Throwable $e) {
			$this->lastError = $e;
			$registration_id = 0;
		}

		// Then 建立應成功（Repository::create 不驗證 identity_id）
		$this->assert_operation_succeeded();
		$this->assertGreaterThan(0, $registration_id);
	}

	/**
	 * 邊緣案例：超長 identity_id（1000 字元）時應能儲存
	 *
	 * @group edge
	 */
	public function test_超長identity_id時應能儲存(): void {
		// Given identity_id 為 1000 字元的字串
		$long_id = str_repeat('U', 1000);

		// When 系統建立報名紀錄
		$registration_id = Repository::create(
			[
				'post_title' => '超長 identity_id 測試',
				'meta_input' => [
					'activity_id'       => 'yt001',
					'identity_id'       => $long_id,
					'identity_provider' => 'line',
				],
			]
		);

		// Then 應成功建立
		$this->assertGreaterThan(0, $registration_id);
		$stored_id = \get_post_meta($registration_id, 'identity_id', true);
		$this->assertSame($long_id, $stored_id, '超長 identity_id 應完整儲存');
	}

	/**
	 * 邊緣案例：identity_id 包含 Unicode 與 Emoji 字元
	 *
	 * @group edge
	 */
	public function test_identity_id包含Unicode和Emoji(): void {
		// Given identity_id 包含 Unicode、Emoji、RTL 文字
		$unicode_id = 'U1234_中文_مرحبا_😀🎉';

		// When 系統建立報名紀錄
		$registration_id = Repository::create(
			[
				'post_title' => 'Unicode 測試',
				'meta_input' => [
					'activity_id'       => 'yt001',
					'identity_id'       => $unicode_id,
					'identity_provider' => 'line',
				],
			]
		);

		// Then 應成功建立且 Unicode 正確儲存
		$this->assertGreaterThan(0, $registration_id);
		$stored_id = \get_post_meta($registration_id, 'identity_id', true);
		$this->assertSame($unicode_id, $stored_id, 'Unicode identity_id 應完整儲存');
	}
}
