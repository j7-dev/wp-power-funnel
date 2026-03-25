<?php
/**
 * 推廣連結整合測試。
 *
 * 驗證 pf_promo_link CPT 的建立、讀取、meta 操作邏輯。
 *
 * @group smoke
 * @group promo-link
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\PromoLink;

use J7\PowerFunnel\Contracts\DTOs\PromoLinkDTO;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * 推廣連結測試
 *
 * Feature: 建立推廣連結
 * Feature: 編輯推廣連結
 */
class PromoLinkTest extends IntegrationTestCase {

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		// PromoLinkDTO 使用靜態方法，無需注入
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * 冒煙測試：可以建立 pf_promo_link CPT
	 *
	 * @group smoke
	 */
	public function test_可以建立推廣連結(): void {
		// When 管理員建立推廣連結
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'   => 'pf_promo_link',
				'post_status' => 'publish',
				'post_title'  => '新 LINE 連結',
			]
		);

		// Then 系統應建立一筆 pf_promo_link CPT
		$post = \get_post($promo_link_id);
		$this->assertNotNull($post, '應建立推廣連結');

		// Then post_title 應為「新 LINE 連結」
		$this->assertSame('新 LINE 連結', $post->post_title, 'post_title 應相符');

		// Then post_type 應為 pf_promo_link
		$this->assertSame('pf_promo_link', $post->post_type, 'post_type 應為 pf_promo_link');
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * 快樂路徑：PromoLinkDTO::of 可以正確從 post_id 建立 DTO
	 *
	 * @group happy
	 */
	public function test_PromoLinkDTO可以從post_id建立(): void {
		// Given 推廣連結有完整設定
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'   => 'pf_promo_link',
				'post_status' => 'publish',
				'post_title'  => '三月推廣連結',
				'meta_input'  => [
					'keyword'     => 'React',
					'last_n_days' => 30,
					'alt_text'    => '三月活動',
				],
			]
		);

		// When 建立 PromoLinkDTO
		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// Then DTO 應正確映射
		$this->assertSame((string) $promo_link_id, $dto->id, 'id 應相符');
		$this->assertSame('三月推廣連結', $dto->name, 'name 應相符');
		$this->assertSame('React', $dto->keyword, 'keyword 應相符');
		$this->assertSame(30, $dto->last_n_days, 'last_n_days 應相符');
		$this->assertSame('三月活動', $dto->alt_text, 'alt_text 應相符');
	}

	/**
	 * 快樂路徑：PromoLinkDTO::save 可以更新 meta
	 *
	 * Feature: 編輯推廣連結
	 *
	 * @group happy
	 */
	public function test_PromoLinkDTO可以更新meta(): void {
		// Given 推廣連結已建立
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'   => 'pf_promo_link',
				'post_status' => 'publish',
				'post_title'  => '原始推廣連結',
				'meta_input'  => [
					'keyword'      => 'React',
					'last_n_days'  => 30,
					'auto_approved' => 'no',
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// When 更新 meta
		$dto->save(
			[
				'keyword'      => 'Vue',
				'last_n_days'  => 7,
				'auto_approved' => 'yes',
			]
		);

		// Then meta 應更新
		\clean_post_cache($promo_link_id);
		$this->assertSame('Vue', \get_post_meta($promo_link_id, 'keyword', true), 'keyword 應更新為 Vue');
		$this->assertSame('7', \get_post_meta($promo_link_id, 'last_n_days', true), 'last_n_days 應更新為 7');
		$this->assertSame('yes', \get_post_meta($promo_link_id, 'auto_approved', true), 'auto_approved 應更新為 yes');
	}

	/**
	 * 快樂路徑：get_message_tpl_id 可以取得特定狀態的訊息模板 ID
	 *
	 * @group happy
	 */
	public function test_get_message_tpl_id回傳正確模板ID(): void {
		// Given 推廣連結有訊息模板設定
		$message_tpl_ids = [
			'pending' => '100',
			'success' => '200',
		];
		$promo_link_id   = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '有訊息模板的推廣連結',
				'meta_input' => [
					'message_tpl_ids' => $message_tpl_ids,
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// When 取得 pending 狀態的訊息模板 ID
		$tpl_id = $dto->get_message_tpl_id(ERegistrationStatus::PENDING);

		// Then 應回傳對應的模板 ID
		$this->assertSame('100', $tpl_id, 'pending 狀態應回傳模板 ID 100');

		// When 取得 success 狀態的訊息模板 ID
		$success_tpl_id = $dto->get_message_tpl_id(ERegistrationStatus::SUCCESS);
		$this->assertSame('200', $success_tpl_id, 'success 狀態應回傳模板 ID 200');
	}

	/**
	 * 快樂路徑：未設定訊息模板時 get_message_tpl_id 回傳 null
	 *
	 * @group happy
	 */
	public function test_未設定訊息模板時回傳null(): void {
		// Given 推廣連結沒有訊息模板設定
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '無訊息模板的推廣連結',
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// When 取得 pending 狀態的訊息模板 ID
		$tpl_id = $dto->get_message_tpl_id(ERegistrationStatus::PENDING);

		// Then 應回傳 null
		$this->assertNull($tpl_id, '未設定時應回傳 null');
	}

	/**
	 * 快樂路徑：get_alt_text 根據 keyword 與 last_n_days 組合文字
	 *
	 * @group happy
	 */
	public function test_get_alt_text組合正確文字(): void {
		// Given 推廣連結設定 keyword 與 last_n_days
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '有關鍵字的推廣連結',
				'meta_input' => [
					'keyword'     => 'React',
					'last_n_days' => 30,
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// When 取得 alt_text
		$alt_text = $dto->get_alt_text();

		// Then alt_text 應包含關鍵字與天數
		$this->assertStringContainsString('React', $alt_text, 'alt_text 應包含關鍵字');
		$this->assertStringContainsString('30', $alt_text, 'alt_text 應包含天數');
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * 錯誤處理：post_id 不存在時 PromoLinkDTO::of 應回傳空 DTO（不拋出異常）
	 *
	 * @group error
	 */
	public function test_不存在的post_id建立空DTO(): void {
		// Given post ID 不存在
		$non_existent_id = 999999999;

		// When 建立 PromoLinkDTO
		$dto = PromoLinkDTO::of((string) $non_existent_id);

		// Then 應回傳 DTO（空值，不拋出異常）
		$this->assertInstanceOf(PromoLinkDTO::class, $dto, '應回傳 PromoLinkDTO 實例');
		$this->assertSame((string) $non_existent_id, $dto->id, 'id 應為傳入的 ID');
		$this->assertSame('', $dto->name, '不存在的 post title 應為空字串');
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * 邊緣案例：keyword 包含特殊字元時應正確儲存
	 *
	 * @group edge
	 */
	public function test_keyword包含特殊字元時正確儲存(): void {
		// Given keyword 包含 XSS 與特殊字元
		$special_keyword = '<script>alert("XSS")</script> & React "Vue" \'Angular\'';

		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '特殊 keyword 測試',
				'meta_input' => [
					'keyword' => $special_keyword,
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// Then keyword 應原樣儲存（meta 儲存原始值）
		$this->assertSame($special_keyword, $dto->keyword, 'keyword 應原樣儲存');
	}

	/**
	 * 邊緣案例：last_n_days 為 0 時 to_activity_params 回傳 0
	 *
	 * @group edge
	 */
	public function test_last_n_days為0時正確處理(): void {
		// Given last_n_days 為 0
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '零天測試',
				'meta_input' => [
					'keyword'     => '',
					'last_n_days' => 0,
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);
		$params = $dto->to_activity_params();

		// Then last_n_days 應為 0
		$this->assertSame(0, $params['last_n_days'], 'last_n_days 應為 0');

		// Then alt_text 應為「所有的活動」（keyword 與 last_n_days 皆為空/0）
		$this->assertSame('所有的活動', $dto->get_alt_text(), '無關鍵字且無天數限制時應為「所有的活動」');
	}

	/**
	 * 邊緣案例：last_n_days 為負數時處理
	 *
	 * @group edge
	 */
	public function test_last_n_days為負數時處理(): void {
		// Given last_n_days 為負數（邊緣案例）
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '負數天數測試',
				'meta_input' => [
					'keyword'     => 'React',
					'last_n_days' => -1,
				],
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// Then last_n_days 應儲存為整數（即使是負數）
		$this->assertIsInt($dto->last_n_days, 'last_n_days 應為整數');
		$this->assertSame(-1, $dto->last_n_days, 'last_n_days 應儲存為 -1');
	}

	/**
	 * 邊緣案例：post_title 包含 Unicode 與 Emoji 時正確儲存
	 *
	 * @group edge
	 */
	public function test_post_title包含Unicode和Emoji(): void {
		// Given post_title 包含 Unicode 與 Emoji
		$unicode_title = '三月推廣 🎉 ∞ مرحبا';

		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => $unicode_title,
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// Then name 應正確儲存 Unicode
		$this->assertSame($unicode_title, $dto->name, 'Unicode 標題應正確儲存');
	}

	/**
	 * 安全性：get_line_post_back_params 的 data 欄位應為 JSON 字串（不含腳本）
	 *
	 * @group security
	 */
	public function test_get_line_post_back_params回傳有效JSON(): void {
		// Given 推廣連結
		$promo_link_id = $this->factory()->post->create(
			[
				'post_type'  => 'pf_promo_link',
				'post_title' => '測試推廣連結',
			]
		);

		$dto = PromoLinkDTO::of((string) $promo_link_id);

		// Given 一個 ActivityDTO（使用正確的建構子參數）
		$activity_dto = new \J7\PowerFunnel\Contracts\DTOs\ActivityDTO(
			[
				'id'                   => 'yt001',
				'activity_provider_id' => 'youtube',
				'title'                => 'React 直播',
				'description'          => ' ',
				'thumbnail_url'        => '',
				'meta'                 => [],
				'scheduled_start_time' => new \DateTime('+1 day'),
			]
		);

		// When 取得 LINE Postback 參數
		$params = $dto->get_line_post_back_params($activity_dto);

		// Then data 應為有效 JSON 字串
		$this->assertIsString($params['data'], 'data 應為字串');
		$decoded = json_decode((string) $params['data'], true);
		$this->assertIsArray($decoded, 'data 應為有效 JSON');
		$this->assertSame('register', $decoded['action'], 'action 應為 register');
		$this->assertSame('yt001', $decoded['activity_id'], 'activity_id 應相符');
	}
}
