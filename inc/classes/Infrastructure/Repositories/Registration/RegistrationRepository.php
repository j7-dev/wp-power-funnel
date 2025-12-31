<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Registration;

use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/** RegistrationRepository */
final class RegistrationRepository {

	/**
	 * 創建待審核的報名
	 *
	 * @param array $args wp_insert_post 的參數
	 * @return int
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
}
