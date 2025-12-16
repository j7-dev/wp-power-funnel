<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\PromoLink\Services;

use J7\WpUtils\Traits\SingletonTrait;

/** ManagerService */
final class ManagerService {
	use SingletonTrait;

	/** 推廣連結的生命週期 */
	public static function register_hooks(): void {
		// TODO 創建 promo link post 後 要產生 對應的 promo link
	}
}
