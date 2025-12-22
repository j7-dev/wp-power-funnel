<?php


declare (strict_types = 1);

namespace J7\PowerFunnel\Compatibility;

use J7\PowerFunnel\Plugin;

/**  Compatibility 不同版本間的相容性設定 */
final class Compatibility {

	private const AS_COMPATIBILITY_ACTION = '_power_funnel_compatibility_as';
	private const VERSION_OPTION_NAME     = '_power_funnel_installed_version';

	/** Register hooks */
	public static function register_hooks(): void {

		$scheduled_version = \get_option(self::VERSION_OPTION_NAME);
		if ($scheduled_version === Plugin::$version) {
			return;
		}
		// 升級成功後執行
		\add_action( 'init', static fn() => \as_enqueue_async_action(self::AS_COMPATIBILITY_ACTION, [], '', true));
		\add_action( self::AS_COMPATIBILITY_ACTION, [ __CLASS__, 'compatibility' ]);
	}


	/** 註冊 AS 排程 @return void */
	// public static function register_action_scheduler(): void {
	// \as_enqueue_async_action(self::AS_COMPATIBILITY_ACTION);
	// }

	/** 間榮性設定 */
	public static function compatibility(): void {

		// region   ============== START 相容性代碼 ==============

		// endregion   ============== END 相容性代碼 ==============

		// 因為有 add LIFF rewrite rule 所以需要刷新
		\flush_rewrite_rules();
		// ❗不要刪除此行，註記已經執行過相容設定
		\update_option(self::VERSION_OPTION_NAME, Plugin::$version);
		\wp_cache_flush();
		Plugin::logger(Plugin::$version . ' 已執行兼容性設定', 'info');
	}
}
