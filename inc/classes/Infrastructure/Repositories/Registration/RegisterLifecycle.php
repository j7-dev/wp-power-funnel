<?php

declare (strict_types = 1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Registration;

use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/** Lifecycle */
final class RegisterLifecycle {

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action( 'transition_post_status', [ __CLASS__, 'transition_registration_status' ], 10, 3 );
	}

	/**
	 * 文章狀態改變時
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態 new|pending
	 * @param \WP_Post $post 文章物件
	 */
	public static function transition_registration_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( !Register::match( $post ) ) {
			return;
		}

		$status = ERegistrationStatus::tryFrom( $new_status);
		if ($status) {
			\do_action("power_funnel/registration/{$status->value}", $new_status, $old_status, $post);
		}

		\do_action('power_funnel/registration/transition_status', $new_status, $old_status, $post);
	}
}
