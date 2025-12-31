<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services;

final class Register {

	/** Register hooks */
	public static function register_hooks(): void {
		LIFF\ApiService::register_hooks();
		LIFF\RegisterService::register_hooks();
		WebhookService::register_hooks();
	}
}
