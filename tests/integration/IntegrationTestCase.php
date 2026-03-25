<?php
/**
 * Power Funnel 整合測試基礎類別。
 *
 * 提供共用的 helper methods 與依賴注入機制。
 * 所有整合測試必須繼承此類別。
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration;

/**
 * 整合測試基礎類別
 *
 * @phpstan-ignore-next-line
 */
abstract class IntegrationTestCase extends \WP_UnitTestCase {

	/** @var \Throwable|null 最後一次操作的錯誤，null 表示成功 */
	protected ?\Throwable $lastError = null;

	/** @var mixed 查詢操作的結果 */
	protected mixed $queryResult = null;

	/** @var array<string, int> 名稱到 ID 的映射（例如：'Alice' => 123） */
	protected array $ids = [];

	/** @var object Repository 容器 */
	protected object $repos;

	/** @var object Service 容器 */
	protected object $services;

	/** 每個測試前的設置 */
	public function set_up(): void {
		parent::set_up();
		$this->lastError   = null;
		$this->queryResult = null;
		$this->ids         = [];
		$this->repos       = new \stdClass();
		$this->services    = new \stdClass();
		$this->configure_dependencies();
	}

	/** 初始化依賴（各測試類別實作此方法以注入 repository 與 service） */
	abstract protected function configure_dependencies(): void;

	/**
	 * 斷言操作成功（lastError 應為 null）
	 *
	 * @return void
	 */
	protected function assert_operation_succeeded(): void {
		$this->assertNull(
			$this->lastError,
			sprintf('預期操作成功，但發生錯誤：%s', $this->lastError?->getMessage() ?? '未知錯誤')
		);
	}

	/**
	 * 斷言操作失敗（lastError 不應為 null）
	 *
	 * @return void
	 */
	protected function assert_operation_failed(): void {
		$this->assertNotNull($this->lastError, '預期操作失敗，但沒有發生錯誤');
	}

	/**
	 * 斷言操作失敗且為特定錯誤類型
	 *
	 * @param string $type 預期的例外類別簡稱（例如：'InvalidArgumentException'）
	 * @return void
	 */
	protected function assert_operation_failed_with_type(string $type): void {
		$this->assertNotNull($this->lastError, '預期操作失敗');
		$actualType = (new \ReflectionClass($this->lastError))->getShortName();
		$this->assertSame($type, $actualType, "預期錯誤類型 {$type}，實際為 {$actualType}");
	}

	/**
	 * 斷言操作失敗且錯誤訊息包含特定文字
	 *
	 * @param string $msg 預期錯誤訊息片段
	 * @return void
	 */
	protected function assert_operation_failed_with_message(string $msg): void {
		$this->assertNotNull($this->lastError, '預期操作失敗');
		$this->assertStringContainsString(
			$msg,
			$this->lastError->getMessage(),
			"錯誤訊息應包含「{$msg}」，實際訊息為：{$this->lastError->getMessage()}"
		);
	}

	/**
	 * 移除所有會呼叫 RegistrationDTO::of() 的 hooks，
	 * 避免測試建立報名紀錄時觸發 YouTube OAuth 錯誤。
	 *
	 * 在需要建立 pf_registration post 的測試中，於建立前呼叫此方法。
	 *
	 * @return void
	 */
	protected function remove_registration_side_effect_hooks(): void {
		foreach (\J7\PowerFunnel\Shared\Enums\ERegistrationStatus::cases() as $status) {
			\remove_action(
				"power_funnel/registration/{$status->value}",
				[ \J7\PowerFunnel\Applications\RegisterActivityViaLine::class, 'line' ],
				10
			);
			if (\J7\PowerFunnel\Shared\Enums\ERegistrationStatus::PENDING === $status) {
				\remove_action(
					"power_funnel/registration/{$status->value}",
					[ \J7\PowerFunnel\Applications\RegisterActivityViaLine::class, 'auto_success' ],
					20
				);
			}
		}
	}

	/**
	 * 重置 ActivityService 單例並注入假的 ActivityProvider，
	 * 避免呼叫 ActivityService::get_activity() 時觸發 YouTube OAuth 錯誤。
	 *
	 * 同時移除 YoutubeService 的 register_provider filter，確保只使用 stub provider。
	 * 使用後必須呼叫 teardown_activity_service_stub() 清除。
	 *
	 * @param array<\J7\PowerFunnel\Contracts\DTOs\ActivityDTO> $activities 假的活動列表
	 * @return void
	 */
	protected function setup_activity_service_stub( array $activities = [] ): void {
		// 移除 YoutubeService 的 activity_providers filter，避免 YouTube OAuth 呼叫
		\remove_all_filters('power_funnel/activity_providers');

		// 重置 ActivityService 單例，讓下次 instance() 重新建立（使用新的 filter 結果）
		$reflection = new \ReflectionClass(\J7\PowerFunnel\Domains\Activity\Services\ActivityService::class);
		$property   = $reflection->getProperty('instance');
		$property->setAccessible(true);
		$property->setValue(null, null);

		// 注入假的 ActivityProvider（只有 stub，不包含 YouTube）
		\add_filter(
			'power_funnel/activity_providers',
			static function ( array $providers ) use ( $activities ): array {
				$providers['stub_provider'] = new class( $activities ) implements \J7\PowerFunnel\Contracts\Interfaces\IActivityProvider {

					/** @var array<\J7\PowerFunnel\Contracts\DTOs\ActivityDTO> 假的活動列表 */
					private array $activities;

					/**
					 * Constructor
					 *
					 * @param array<\J7\PowerFunnel\Contracts\DTOs\ActivityDTO> $activities 假的活動列表
					 */
					public function __construct( array $activities ) {
						$this->activities = $activities;
					}

					/** @return array<\J7\PowerFunnel\Contracts\DTOs\ActivityDTO> 活動 DTO 陣列 */
					public function get_activities(): array {
						return $this->activities;
					}
				};
				return $providers;
			}
		);
	}

	/**
	 * 清除 ActivityService stub，重置為初始狀態
	 *
	 * @return void
	 */
	protected function teardown_activity_service_stub(): void {
		\remove_all_filters('power_funnel/activity_providers');
		$reflection = new \ReflectionClass(\J7\PowerFunnel\Domains\Activity\Services\ActivityService::class);
		$property   = $reflection->getProperty('instance');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	/**
	 * 建立假的 ActivityDTO（用於 stub ActivityService）
	 *
	 * @param string $id 活動 ID
	 * @return \J7\PowerFunnel\Contracts\DTOs\ActivityDTO 假活動 DTO
	 */
	protected function make_stub_activity_dto( string $id = 'yt001' ): \J7\PowerFunnel\Contracts\DTOs\ActivityDTO {
		$future_time = new \DateTime('+1 day');
		return new \J7\PowerFunnel\Contracts\DTOs\ActivityDTO(
			[
				'id'                   => $id,
				'activity_provider_id' => 'stub_provider',
				'title'                => "測試活動 {$id}",
				'description'          => '測試活動描述',
				'scheduled_start_time' => $future_time,
			]
		);
	}

	/**
	 * 直接透過 $wpdb 更新 post_status，不觸發 WordPress 的 transition_post_status 鉤子。
	 * 用於需要設定自訂狀態但不想觸發相關 action 的測試場景。
	 *
	 * @param int    $post_id    Post ID
	 * @param string $new_status 新狀態值
	 * @return void
	 */
	protected function set_post_status_bypass_hooks( int $post_id, string $new_status ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			[ 'post_status' => $new_status ],
			[ 'ID' => $post_id ],
			[ '%s' ],
			[ '%d' ]
		);
		\clean_post_cache($post_id);
	}

	/**
	 * 建立 pf_workflow_rule 測試資料
	 *
	 * @param array<string, mixed> $args 覆寫預設值的參數
	 * @return int post ID
	 */
	protected function create_workflow_rule(array $args = []): int {
		$defaults = [
			'post_type'   => 'pf_workflow_rule',
			'post_status' => 'draft',
			'post_title'  => '測試工作流規則',
		];
		$args      = \wp_parse_args($args, $defaults);
		$post_id   = $this->factory()->post->create($args);
		return (int) $post_id;
	}

	/**
	 * 建立 pf_workflow 測試資料
	 *
	 * @param array<string, mixed> $args 覆寫預設值的參數
	 * @return int post ID
	 */
	protected function create_workflow(array $args = []): int {
		$defaults = [
			'post_type'   => 'pf_workflow',
			'post_status' => 'running',
			'post_title'  => '測試工作流',
		];
		$args    = \wp_parse_args($args, $defaults);
		$post_id = $this->factory()->post->create($args);
		return (int) $post_id;
	}

	/**
	 * 建立 pf_registration 測試資料（自動移除 YouTube 相關 hooks）
	 *
	 * @param array<string, mixed> $args 覆寫預設值的參數
	 * @return int post ID
	 */
	protected function create_registration(array $args = []): int {
		$defaults = [
			'post_type'   => 'pf_registration',
			'post_status' => 'pending',
			'post_title'  => '測試報名紀錄',
		];
		$args    = \wp_parse_args($args, $defaults);
		$post_id = $this->factory()->post->create($args);
		return (int) $post_id;
	}

	/**
	 * 建立 pf_promo_link 測試資料
	 *
	 * @param array<string, mixed> $args 覆寫預設值的參數
	 * @return int post ID
	 */
	protected function create_promo_link(array $args = []): int {
		$defaults = [
			'post_type'   => 'pf_promo_link',
			'post_status' => 'publish',
			'post_title'  => '測試推廣連結',
		];
		$args    = \wp_parse_args($args, $defaults);
		$post_id = $this->factory()->post->create($args);
		return (int) $post_id;
	}
}
