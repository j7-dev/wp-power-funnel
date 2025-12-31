<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use J7\WpUtils\Classes\DTO;

/** 通用的活動 DTO */
final class RegistrationDTO extends DTO {

	/** @var string 活動 ID */
	public string $id;

	/** @var string 用戶識別 id */
	public string $identity_id;

	/** @var EIdentityProvider 用戶識別提供商 */
	public EIdentityProvider $identity_provider;

	/** @var ActivityDTO 活動  */
	public ActivityDTO $activity;




	/** 取得實例 */
	public static function of( \WP_Post $post ): self {
		$activity_id       = \get_post_meta($post->ID, 'activity_id', true);
		$identity_provider = \get_post_meta($post->ID, 'identity_provider', true);
		$args              = [
			'id'                => $post->ID,
			'identity_id'       => \get_post_meta($post->ID, 'identity_id', true),
			'identity_provider' => EIdentityProvider::from( $identity_provider),
			'activity'          => ActivityService::instance()->get_activity( $activity_id ),
		];

		return new self($args);
	}
}
