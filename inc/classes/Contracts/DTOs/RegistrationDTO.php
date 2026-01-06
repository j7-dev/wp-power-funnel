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


	/** @var ActivityDTO 活動  */
	public ActivityDTO $activity;

	/** @var UserDTO 用戶 */
	public UserDTO $user;

	/** @var bool 是否自動審核成功 */
	public bool $auto_approved = false;

	/** @var array<string, string> 報名關聯的訊息模板 ids, ERegistrationStatus::value, $post_id */
	public array $message_ids = [];

	/** 取得實例 */
	public static function of( \WP_Post $post ): self {
		$activity_id             = \get_post_meta($post->ID, 'activity_id', true);
		$identity_id             = \get_post_meta($post->ID, 'identity_id', true);
		$identity_provider_value = \get_post_meta($post->ID, 'identity_provider', true);
		$auto_approved           = \get_post_meta($post->ID, 'auto_approved', true);
		$message_ids             = \get_post_meta($post->ID, 'message_ids', true);
		$message_ids             = \is_array($message_ids) ? $message_ids : [];
		$identity_provider       = EIdentityProvider::from( $identity_provider_value );
		$args                    = [
			'id'            => $post->ID,
			'activity'      => ActivityService::instance()->get_activity( $activity_id ),
			'user'          => UserDTO::of( $identity_id, $identity_provider ),
			'auto_approved' => \wc_string_to_bool( $auto_approved ),
			'message_ids'   => $message_ids,
		];

		return new self($args);
	}
}
