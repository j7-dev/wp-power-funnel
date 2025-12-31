<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Contracts\DTOs\RegistrationDTO;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/**
 * 用戶報名指定活動"成功"
 */
final class RegisterActivitySuccess {

	/** Register hooks */
	public static function register_hooks(): void {
		$status = ERegistrationStatus::SUCCESS;
		\add_action( "power_funnel/registration/{$status->value}", [ __CLASS__, 'line' ] );
	}


	/**
	 * 文章狀態改變時
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 就狀態
	 * @param \WP_Post $post 文章物件
	 */
	public static function line( $new_status, $old_status, $post ): void {
		$registration_dto = RegistrationDTO::of( $post );
		$activity_dto     = $registration_dto->activity;
	}
}
