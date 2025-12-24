<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services\LIFF;

use J7\PowerFunnel\Bootstrap;
use J7\PowerFunnel\Plugin;

/**
 * LIFF App 的前端顯示介面
 */
final class Register {

	private const LIFF_QUERY_VAR = 'liff';

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action('init', [ __CLASS__, 'add_liff_rewrite_rule' ]);
		\add_filter('query_vars', [ __CLASS__, 'register_liff_query_var' ]);
		\add_filter('template_include', [ __CLASS__, 'liff_locate_template' ]);
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_script' ] );
	}

	/** Enqueue frontend assets */
	public static function enqueue_script(): void {
		if (!self::is_liff()) {
			return;
		}

		Bootstrap::enqueue_script();
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

	/**
	 * 用戶造訪 /liff 時要套用的 template
	 * 優先使用主題的 page-liff.php，若不存在則使用外掛內建的模板
	 *
	 * @param string $template 原始模板路徑
	 * @return string 最終使用的模板路徑
	 */
	public static function liff_locate_template( string $template ): string {
		if (self::is_liff()) {
			Bootstrap::enqueue_script();
			// 優先檢查主題是否有 page-liff.php（子主題 > 父主題）
			$theme_template = \locate_template('page-liff.php');
			if (!empty($theme_template)) {
				return $theme_template;
			}

			// 使用外掛內建的模板
			$plugin_template = Plugin::$dir . '/inc/templates/page-liff.php';
			if (\file_exists($plugin_template)) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/** 是否是 /liff */
	public static function is_liff(): bool {
		return (bool) \get_query_var(self::LIFF_QUERY_VAR);
	}
}
