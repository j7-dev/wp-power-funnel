<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Youtube\Services;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;
use J7\PowerFunnel\Infrastructure\Youtube\DTOs\SettingDTO;
use J7\WpUtils\Traits\SingletonTrait;

/**
 * Class DataApiService
 * YouTube Data API v3 裡面的 Live Streaming API 整合服務
 *
 * @see https://developers.google.com/youtube/v3/live/docs/liveBroadcasts/list
 */
final class DataApiService {
	use SingletonTrait;

	/** @var string 活動提供商 id */
	public const ACTIVITY_PROVIDER_ID = 'youtube';

	/** @var string 儲存 OAuth Token 的 option name */
	private const OAUTH_OPTION_NAME = '_power_funnel_youtube_oauth_token';

	/** @var GoogleOAuthService OAuth 服務 */
	private GoogleOAuthService $oauth_service;

	/**
	 * Token 資料
	 *
	 * @var array<string, mixed>
	 */
	private array $token = [];

	/** @var bool 是否已授權 */
	private bool $is_authorized = false;

	/**
	 * Constructor
	 *
	 * @throws \Exception 當授權失敗時拋出異常
	 */
	private function __construct() {
		$this->init_oauth_service();
		$this->load_token();
		$this->handle_oauth_callback();
		$this->ensure_valid_token();
	}

	/**
	 * 初始化 OAuth 服務
	 *
	 * @return void
	 */
	private function init_oauth_service(): void {
		$setting             = SettingDTO::instance();
		$this->oauth_service = new GoogleOAuthService(
			$setting->clientId, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$setting->clientSecret, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$setting->redirectUri // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		);
	}

	/**
	 * 從資料庫載入 Token
	 *
	 * @return void
	 */
	private function load_token(): void {
		$token       = \get_option( self::OAUTH_OPTION_NAME, [] );
		$this->token = \is_array( $token ) ? $token : [];
	}

	/**
	 * 處理 OAuth 回調
	 * 當使用者從 Google 授權頁面返回時，交換授權碼取得 Token
	 *
	 * @return void
	 * @throws \Exception 當授權碼交換失敗時拋出異常
	 */
	private function handle_oauth_callback(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_scope = isset( $_GET['scope'] ) ? (string) \wp_unslash( $_GET['scope'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_code = isset( $_GET['code'] ) ? (string) \wp_unslash( $_GET['code'] ) : '';

		$scope = \sanitize_text_field( $raw_scope );
		$code  = \sanitize_text_field( $raw_code );

		if ( ! empty( $scope ) && ! empty( $code ) && $scope === GoogleOAuthService::SCOPE_YOUTUBE_READONLY ) {
			$this->token = $this->oauth_service->fetch_access_token_with_auth_code( $code );
			$this->save_token();
		}
	}

	/**
	 * 確保有效的 Token
	 * 如果 Token 過期，嘗試刷新；如果沒有 Token，標記為未授權
	 *
	 * @return void
	 * @throws \Exception 當 Token 刷新失敗時拋出異常
	 */
	private function ensure_valid_token(): void {
		if ( empty( $this->token ) ) {
			$this->is_authorized = false;
			return;
		}

		// 準備給 is_access_token_expired 的參數
		$token_for_check = [
			'access_token' => $this->get_access_token() ?? '',
			'expires_in'   => isset( $this->token['expires_in'] ) ? (int) $this->token['expires_in'] : 0,
			'created'      => isset( $this->token['created'] ) ? (int) $this->token['created'] : 0,
		];

		// 檢查 Token 是否過期
		if ( GoogleOAuthService::is_access_token_expired( $token_for_check ) ) {
			$refresh_token = $this->get_refresh_token();

			if ( null === $refresh_token || '' === $refresh_token ) {
				$this->is_authorized = false;
				return;
			}

			// 刷新 Token
			$new_token = $this->oauth_service->fetch_access_token_with_refresh_token( $refresh_token );

			// 保留原有的 refresh_token（Google 不會在刷新時返回新的 refresh_token）
			if ( ! isset( $new_token['refresh_token'] ) ) {
				$new_token['refresh_token'] = $refresh_token;
			}

			$this->token = $new_token;
			$this->save_token();
		}

		$this->is_authorized = true;
	}

	/**
	 * 儲存 Token 到資料庫
	 *
	 * @return void
	 */
	private function save_token(): void {
		\update_option( self::OAUTH_OPTION_NAME, $this->token );
	}

	/**
	 * 取得 Refresh Token
	 *
	 * @return string|null Refresh Token
	 */
	private function get_refresh_token(): ?string {
		return isset( $this->token['refresh_token'] ) ? (string) $this->token['refresh_token'] : null;
	}

	/**
	 * 取得 Access Token
	 *
	 * @return string|null Access Token
	 */
	private function get_access_token(): ?string {
		return isset( $this->token['access_token'] ) ? (string) $this->token['access_token'] : null;
	}

	/**
	 * 檢查是否已授權
	 *
	 * @return bool 是否已授權
	 */
	public function is_authorized(): bool {
		return $this->is_authorized;
	}

	/**
	 * 取得授權 URL
	 *
	 * @return string 授權 URL
	 */
	public function get_auth_url(): string {
		return $this->oauth_service->create_auth_url();
	}

	/**
	 * 渲染授權按鈕 HTML
	 *
	 * @return void HTML 字串
	 */
	public function render_auth_button(): void {
		if ( $this->is_authorized ) {
			echo '<div class="notice notice-success"><p>已完成 Google OAuth 授權</p></div>';
		}

		$auth_url = \esc_url( $this->get_auth_url() );
		echo '<a href="' . $auth_url . '" class="button button-primary">Google OAuth 授權</a>';
	}

	/**
	 * 取得所有直播活動
	 *
	 * @return array<ActivityDTO> 活動 DTO 陣列
	 * @throws \Exception 當未授權或 API 請求失敗時拋出異常
	 */
	public function get_activities(): array {
		if ( ! $this->is_authorized ) {
			throw new \Exception( '尚未完成 Google OAuth 授權' );
		}

		$access_token = $this->get_access_token();
		if ( null === $access_token ) {
			throw new \Exception( '無法取得 Access Token' );
		}

		$youtube_service = new YoutubeLiveService( $access_token );
		$response        = $youtube_service->get_live_broadcasts(
			'id,snippet',
			[ 'mine' => true ]
		);

		$activities = [];
		$items      = $response['items'] ?? [];
		if ( \is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( \is_array( $item ) && isset( $item['id'] ) && \is_string( $item['id'] ) ) {
					$activities[] = self::parse_activity_dto( $item );
				}
			}
		}

		return $activities;
	}

	/**
	 * 將 YouTube API 回傳的資料轉換成 ActivityDTO
	 *
	 * @param array<string, mixed> $item YouTube API 回傳的單一活動資料
	 * @return ActivityDTO
	 */
	private static function parse_activity_dto( array $item ): ActivityDTO {
		$snippet = $item['snippet'] ?? [];
		$snippet = \is_array( $snippet ) ? $snippet : [];

		$scheduled_start_time = new \DateTime();
		if ( isset( $snippet['scheduledStartTime'] ) && \is_string( $snippet['scheduledStartTime'] ) ) {
			$scheduled_start_time = new \DateTime( $snippet['scheduledStartTime'] );
		}

		$args = [
			'id'                   => isset( $item['id'] ) && \is_string( $item['id'] ) ? $item['id'] : '',
			'activity_provider_id' => self::ACTIVITY_PROVIDER_ID,
			'title'                => isset( $snippet['title'] ) && \is_string( $snippet['title'] ) ? $snippet['title'] : '',
			'description'          => isset( $snippet['description'] ) && \is_string( $snippet['description'] ) ? $snippet['description'] : '',
			'thumbnail_url'        => self::parse_thumbnail_url( $item ),
			'scheduled_start_time' => $scheduled_start_time,
		];

		return new ActivityDTO( $args );
	}

	/**
	 * 從 API 回傳資料解析縮圖 URL
	 *
	 * @param array<string, mixed> $item YouTube API 回傳的單一活動資料
	 * @return string 縮圖 URL
	 */
	private static function parse_thumbnail_url( array $item ): string {
		$snippet    = $item['snippet'] ?? [];
		$snippet    = \is_array( $snippet ) ? $snippet : [];
		$thumbnails = $snippet['thumbnails'] ?? [];
		$thumbnails = \is_array( $thumbnails ) ? $thumbnails : [];

		// 依優先順序嘗試取得最佳畫質縮圖
		$quality_priority = [ 'maxres', 'standard', 'high', 'medium', 'default' ];

		foreach ( $quality_priority as $quality ) {
			if ( isset( $thumbnails[ $quality ] ) && \is_array( $thumbnails[ $quality ] ) ) {
				$thumb = $thumbnails[ $quality ];
				if ( isset( $thumb['url'] ) && \is_string( $thumb['url'] ) ) {
					return $thumb['url'];
				}
			}
		}

		// 預設縮圖
		return 'https://i.ytimg.com/vi/default/sddefault.jpg';
	}

	/**
	 * 清除授權 Token
	 * 用於登出或重新授權
	 *
	 * @return void
	 */
	public function revoke_token(): void {
		$this->token         = [];
		$this->is_authorized = false;
		\delete_option( self::OAUTH_OPTION_NAME );
	}
}
