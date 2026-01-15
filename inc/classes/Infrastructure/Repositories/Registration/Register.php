<?php

declare (strict_types = 1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Registration;

use J7\PowerFunnel\Shared\Enums\ERegistrationStatus;

/** Class Register */
final class Register {

	private const POST_TYPE = 'pf_registration';

	/** Register hooks */
	public static function register_hooks(): void {
		ERegistrationStatus::register();
		\add_action('init', [ __CLASS__, 'register_cpt' ]);
		\add_action( 'transition_post_status', [ __CLASS__, 'register_lifecycle' ], 10, 3 );
		\add_action( 'init', [ __CLASS__, 'register_status' ] );
	}

	/** Register cpt */
	public static function register_cpt(): void {

		$args = [
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		// @phpstan-ignore-next-line
		\register_post_type(self::POST_TYPE, $args);
	}

	/** Get post_type */
	public static function post_type(): string {
		return self::POST_TYPE;
	}

	/** @return bool 是否為活動報名 post */
	public static function match( \WP_Post $post ): bool {
		return $post->post_type === self::POST_TYPE;
	}

	/**
	 * 文章狀態改變時
	 *
	 * @param string   $new_status 新狀態
	 * @param string   $old_status 舊狀態 new|pending
	 * @param \WP_Post $post 文章物件
	 */
	public static function register_lifecycle( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( !self::match( $post ) ) {
			return;
		}

		$status = ERegistrationStatus::tryFrom( $new_status);
		if ($status) {
			\do_action("power_funnel/registration/{$status->value}", $new_status, $old_status, $post);
		}

		\do_action('power_funnel/registration/transition_status', $new_status, $old_status, $post);
	}

	/** 向 WordPress 註冊新的文章狀態 */
	public static function register_status(): void {
		foreach (ERegistrationStatus::cases() as $status) {
			\register_post_status(
				$status->value,
				[
					'exclude_from_search' => true,
					'internal'            => true,
					'public'              => false,
				]
			);
		}
	}
}
