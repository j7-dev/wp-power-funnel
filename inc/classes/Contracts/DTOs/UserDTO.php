<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Constants\App;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use J7\WpUtils\Classes\DTO;

/** 用戶 DTO */
final class UserDTO extends DTO {

	/** @var string 用戶識別 id */
	public string $id;

	/** @var EIdentityProvider 用戶識別提供商 */
	public EIdentityProvider $identity_provider;

	/** @var string 顯示名稱 */
	public string $display_name = '';

	/** @var string 用戶大頭照 url */
	public string $user_avatar = '';

	/** 取得實例 */
	public static function of( string $id, EIdentityProvider $identity_provider ): self {

		$args = [
			'id'                => $id,
			'identity_provider' => $identity_provider,
			'display_name'      => self::get_display_name( $id, $identity_provider) ?: '未知用戶',
			'user_avatar'       => self::get_user_avatar( $id, $identity_provider) ?: App::DEFAULT_USER_AVATAR,
		];

		return new self($args);
	}

	/**
	 * 取得用戶顯示名稱
	 *
	 * @param string            $id 用戶識別 id
	 * @param EIdentityProvider $identity_provider 用戶識別提供商
	 * @return string 用戶顯示名稱
	 */
	private static function get_display_name( string $id, EIdentityProvider $identity_provider ): string {
		switch ($identity_provider) {
			case EIdentityProvider::LINE:
				$service = MessageService::instance();
				return $service->get_profile($id)->getDisplayName();
			case EIdentityProvider::WP:
				$user = \get_user_by('id', $id);
				return $user ? $user->display_name : '';
		}
		return '';
	}

	/**
	 * 取得用戶大頭照
	 *
	 * @param string            $id 用戶識別 id
	 * @param EIdentityProvider $identity_provider 用戶識別提供商
	 * @return string 用戶大頭照 url
	 */
	private static function get_user_avatar( string $id, EIdentityProvider $identity_provider ): string {
		try {
			switch ($identity_provider) {
				case EIdentityProvider::LINE:
					$service = MessageService::instance();
					return $service->get_profile($id)->getPictureUrl();
				case EIdentityProvider::WP:
					return \get_avatar_url($id) ?: '';
			}
			return '';
		} catch (\Throwable $e) {
			Plugin::logger(
				"取得用戶大頭照失敗: {$e->getMessage()}",
				'error',
				[
					'id'       => $id,
					'provider' => $identity_provider->value,
				]
				);
			return '';
		}
	}
}
