<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Contracts\DTOs\RegistrationDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/**
 * 用戶報名指定活動"審核中"
 */
final class RegisterActivityPending {

	/** Register hooks */
	public static function register_hooks(): void {
		$status = ERegistrationStatus::PENDING;
		\add_action( "power_funnel/registration/{$status->value}", [ __CLASS__, 'line' ], 10, 3 );
		\add_action( "power_funnel/registration/{$status->value}", [ __CLASS__, 'auto_success' ], 20, 3 );
	}


	/**
	 * 文章狀態改變時
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 就狀態
	 * @param \WP_Post $post 文章物件
	 */
	public static function line( string $new_status, string $old_status, \WP_Post $post ): void {
		$registration_dto = RegistrationDTO::of( $post );
		$activity_dto     = $registration_dto->activity;
		$service          = MessageService::instance();
		$service->send_text_message(
			$registration_dto->user->id,
			"已經收到您 《{$activity_dto->title}》 的報名資訊，報名成功後會盡速通知。"
		);
	}

	/**
	 * 自動審核成功
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 就狀態
	 * @param \WP_Post $post 文章物件
	 */
	public static function auto_success( string $new_status, string $old_status, \WP_Post $post ): void {
		$registration_dto = RegistrationDTO::of( $post );
		if ( !$registration_dto->auto_approved) {
			return;
		}

		\wp_update_post(
			[
				'ID'          => $registration_dto->id,
				'post_status' => ERegistrationStatus::SUCCESS->value,
			]
			);
	}
}
