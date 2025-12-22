<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services\LIFF;

use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO;
use J7\PowerFunnel\Plugin;

/**
 * LIFF App 的前端顯示介面
 */
final class Register {

	private const LIFF_QUERY_VAR = 'liff';

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
		\add_action('init', [ __CLASS__, 'add_liff_rewrite_rule' ]);
		\add_filter('query_vars', [ __CLASS__, 'register_liff_query_var' ]);
		\add_filter('template_include', [ __CLASS__, 'liff_locate_template' ]);
	}

	/** Enqueue frontend assets */
	public static function enqueue_frontend_assets(): void {
		if (!self::is_liff()) {
			return;
		}

		$sdk_handle = Plugin::$snake . '_liff_sdk_js';
		\wp_enqueue_script(
			$sdk_handle,
			'https://static.line-scdn.net/liff/edge/2/sdk.js',
			[ 'jquery' ],
			Plugin::$version,
			[
				'in-footer' => true,
				'strategy'  => 'async',
			]
		);

		$handle = Plugin::$snake . '_liff_js';

		\wp_enqueue_script(
			$handle,
			\plugin_dir_url(__FILE__) . 'liff.js',
			[ 'jquery', Plugin::$snake . '_liff_sdk_js' ],
			Plugin::$version,
			[
				'in-footer' => true,
				'strategy'  => 'async',
			]
		);

		Plugin::instance()->add_module_handle($sdk_handle);
		Plugin::instance()->add_module_handle($handle);

		\wp_localize_script(
			$handle,
			"{$handle}_data",
			[
				'liff_id' => SettingDTO::instance()->liff_id,
				'api_url' => get_rest_url( null, 'power-funnel/liff' ),
			]
		);
	}

	/** Add rewrite rule  */
	public static function add_liff_rewrite_rule() {
		\add_rewrite_rule(
			'^' . self::LIFF_QUERY_VAR . '/?$', // 路由規則
			'index.php?' . self::LIFF_QUERY_VAR . '=1', // 對應的 query var
			'top'
		);
	}

	/** 註冊 Query Var */
	public static function register_liff_query_var( array $vars ): array {
		$vars[] = self::LIFF_QUERY_VAR;
		return $vars;
	}

	/** 用戶造訪 /liff 時要套用的 template */
	public static function liff_locate_template( string $template ): string {
		if (self::is_liff()) {
			// 使用主題的 page.php
			$new_template = \locate_template('page.php');
			if (!empty($new_template)) {
				return $new_template;
			}
		}
		return $template;
	}

	/** 是否是 /liff */
	public static function is_liff(): bool {
		return (bool) \get_query_var(self::LIFF_QUERY_VAR);
	}
}
