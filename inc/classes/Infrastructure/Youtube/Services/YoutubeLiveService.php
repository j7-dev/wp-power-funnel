<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Youtube\Services;

/**
 * Class YoutubeLiveService
 * YouTube Live Streaming API 服務
 *
 * @see https://developers.google.com/youtube/v3/live/docs/liveBroadcasts/list
 */
final class YoutubeLiveService {

	/** @var string YouTube API 基礎 URL */
	private const API_BASE_URL = 'https://www.googleapis.com/youtube/v3';

	/** @var string Access Token */
	private string $access_token;

	/**
	 * Constructor
	 *
	 * @param string $access_token Access Token
	 */
	public function __construct( string $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * 取得直播列表
	 *
	 * @param string               $part 要取得的資料部分，以逗號分隔 (id, snippet, contentDetails, monetizationDetails, status)
	 * @param array<string, mixed> $params 額外參數
	 * @return array<string, mixed> API 回應資料
	 * @throws \Exception 當 API 請求失敗時拋出異常
	 */
	public function get_live_broadcasts( string $part = 'id,snippet', array $params = [] ): array {
		$query_params         = array_merge( $params, [ 'part' => $part ] );
		$query_params['mine'] = isset( $query_params['mine'] ) && $query_params['mine'] ? 'true' : 'false';

		$url = self::API_BASE_URL . '/liveBroadcasts?' . http_build_query( $query_params );

		return $this->make_api_request( $url );
	}

	/**
	 * 發送 API 請求
	 *
	 * @param string $url 請求 URL
	 * @return array<string, mixed> API 回應資料
	 * @throws \Exception 當 API 請求失敗時拋出異常
	 */
	private function make_api_request( string $url ): array {
		$response = \wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => "Bearer {$this->access_token}",
					'Accept'        => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if ( \is_wp_error( $response ) ) {
			throw new \Exception(
				sprintf(
					'YouTube API 請求失敗: %s',
					$response->get_error_message()
				)
			);
		}

		$status_code   = \wp_remote_retrieve_response_code( $response );
		$response_body = \wp_remote_retrieve_body( $response );

		/** @var array<string, mixed>|null $data */
		$data = json_decode( $response_body, true );

		if ( null === $data ) {
			throw new \Exception( 'YouTube API 回應解析失敗' );
		}

		if ( $status_code >= 400 ) {
			$error_message = self::parse_api_error( $data );
			throw new \Exception(
				sprintf(
					'YouTube API 請求失敗 (%d): %s',
					$status_code,
					$error_message
				)
			);
		}

		return $data;
	}

	/**
	 * 解析 API 錯誤訊息
	 *
	 * @param array<string, mixed> $data API 回應資料
	 * @return string 錯誤訊息
	 */
	private static function parse_api_error( array $data ): string {
		$error = $data['error'] ?? null;
		if ( ! \is_array( $error ) ) {
			return '未知錯誤';
		}

		if ( isset( $error['message'] ) && \is_string( $error['message'] ) ) {
			return $error['message'];
		}

		$errors = $error['errors'] ?? [];
		if ( \is_array( $errors ) && isset( $errors[0] ) && \is_array( $errors[0] ) ) {
			$first_error = $errors[0];
			if ( isset( $first_error['message'] ) && \is_string( $first_error['message'] ) ) {
				return $first_error['message'];
			}
		}

		return '未知錯誤';
	}
}
