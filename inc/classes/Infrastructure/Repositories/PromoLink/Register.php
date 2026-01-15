<?php

declare (strict_types = 1);

namespace J7\PowerFunnel\Infrastructure\Repositories\PromoLink;

/** Class Register */
final class Register {


	private const POST_TYPE = 'pf_promo_link';

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action('init', [ __CLASS__, 'register_cpt' ]);
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
}
