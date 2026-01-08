<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Registration;

use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/** 活動報名 CRUD  */
final class Repository {

	/**
	 * 創建待審核的報名
	 *
	 * @param array $args wp_insert_post 的參數
	 * @return int registration ID
	 */
	public static function create( array $args = [] ): int {
		$default = [
			'post_status' => ERegistrationStatus::PENDING->value,
			'post_type'   => Register::post_type(),
		];
		$args    = \wp_parse_args($args, $default);
		$result  = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建報名失敗: {$result->get_error_message()}" );
		}
		return $result;
	}

	/** @retrun \WP_Post|null 查找此用戶已經報名過的活動 */
	public static function get_registered_registration( string $identity_id, EIdentityProvider $identity_provider, string $activity_id ): \WP_Post|null {

		$args = [
			'posts_per_page' => 1,
			'post_type'      => Register::post_type(),
		];

		if (EIdentityProvider::WP === $identity_provider) {
			$args['author']     = $identity_id;
			$args['meta_key']   ='activity_id';
			$args['meta_value'] =$activity_id;
		} else {
			$args['meta_query'] = [
				'relation'           => 'AND',
				'identity_id_clause' => [
					'key'   => 'identity_id',
					'value' => $identity_id,
				],
				'activity_id_clause' => [
					'key'   => 'activity_id',
					'value' => $activity_id,
				],
			];
		}

		$posts = \get_posts($args);
		return \reset($posts) ?: null;
	}
}
