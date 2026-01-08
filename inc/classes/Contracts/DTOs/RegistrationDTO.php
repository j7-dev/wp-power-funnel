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

	/** @var PromoLinkDTO 從哪個推廣連結報名的  */
	public PromoLinkDTO $promo_link;

	/** @var bool 是否自動審核成功 */
	public bool $auto_approved = false;

	/** 取得實例 */
	public static function of( \WP_Post $post ): self {
		$activity_id             = \get_post_meta($post->ID, 'activity_id', true);
		$identity_id             = \get_post_meta($post->ID, 'identity_id', true);
		$promo_link_id           = \get_post_meta($post->ID, 'promo_link_id', true);
		$identity_provider_value = \get_post_meta($post->ID, 'identity_provider', true);
		$auto_approved           = \get_post_meta($post->ID, 'auto_approved', true);
		$identity_provider       = EIdentityProvider::from( $identity_provider_value );
		$args                    = [
			'id'            => $post->ID,
			'activity'      => ActivityService::instance()->get_activity( $activity_id ),
			'user'          => UserDTO::of( $identity_id, $identity_provider ),
			'promo_link'    => PromoLinkDTO::of( $promo_link_id ),
			'auto_approved' => \wc_string_to_bool( $auto_approved ),
		];

		return new self($args);
	}
}
