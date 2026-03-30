<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\Workflow\Services;

/**
 * 工作流遞迴防護器
 *
 * 防止工作流觸發工作流的無限遞迴鏈。
 * 使用靜態深度計數器追蹤當前 PHP 請求中的工作流建立層級。
 * 最大深度為 MAX_DEPTH，超過時拒絕建立新工作流並記錄錯誤。
 */
final class RecursionGuard {

	/** @var int 最大允許遞迴深度 */
	public const MAX_DEPTH = 3;

	/** @var int 當前遞迴深度 */
	private static int $depth = 0;

	/**
	 * 進入工作流建立流程，深度加一
	 *
	 * @return void
	 */
	public static function enter(): void {
		++self::$depth;
	}

	/**
	 * 離開工作流建立流程，深度減一
	 *
	 * @return void
	 */
	public static function leave(): void {
		if (self::$depth > 0) {
			--self::$depth;
		}
	}

	/**
	 * 檢查是否已超過最大遞迴深度
	 *
	 * @return bool 若深度超過 MAX_DEPTH 則回傳 true
	 */
	public static function is_exceeded(): bool {
		return self::$depth > self::MAX_DEPTH;
	}

	/**
	 * 取得當前遞迴深度
	 *
	 * @return int 當前深度
	 */
	public static function depth(): int {
		return self::$depth;
	}

	/**
	 * 重置遞迴深度計數器
	 * 主要用於測試環境
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$depth = 0;
	}
}
