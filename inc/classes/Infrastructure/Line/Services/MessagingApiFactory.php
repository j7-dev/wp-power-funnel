<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services;

use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use GuzzleHttp\Client;

/**
 * MessagingApiApi 工廠類別
 * 負責建立和管理 LINE Messaging API 客戶端實例
 */
final class MessagingApiFactory {

	/**
	 * MessagingApiApi 實例快取
	 *
	 * @var MessagingApiApi|null
	 */
	private static ?MessagingApiApi $instance = null;

	/**
	 * 取得 MessagingApiApi 實例
	 * 使用單例模式避免重複建立
	 *
	 * @param SettingDTO|null $setting 設定 DTO，若為 null 則使用預設設定
	 * @return MessagingApiApi
	 * @throws \Exception 當設定不完整時拋出異常
	 */
	public static function create( ?SettingDTO $setting = null ): MessagingApiApi {
		if (self::$instance !== null) {
			return self::$instance;
		}

		$setting = $setting ?? SettingDTO::instance();

		if (!$setting->is_valid()) {
			throw new \Exception('LINE 設定不完整，請先設定 Channel Access Token、Channel ID 和 Channel Secret');
		}

		$client = new Client();
		$config = new Configuration();
		$config->setAccessToken($setting->channel_access_token);

		self::$instance = new MessagingApiApi(
			client: $client,
			config: $config,
		);

		return self::$instance;
	}

	/**
	 * 重置實例
	 * 用於測試或需要重新建立連線時
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * 使用指定的設定建立新實例
	 * 不使用快取，每次都建立新實例
	 *
	 * @param SettingDTO $setting 設定 DTO
	 * @return MessagingApiApi
	 * @throws \Exception 當設定不完整時拋出異常
	 */
	public static function create_new( SettingDTO $setting ): MessagingApiApi {
		if (!$setting->is_valid()) {
			throw new \Exception('LINE 設定不完整，請先設定 Channel Access Token、Channel ID 和 Channel Secret');
		}

		$client = new Client();
		$config = new Configuration();
		$config->setAccessToken($setting->channel_access_token);

		return new MessagingApiApi(
			client: $client,
			config: $config,
		);
	}
}
