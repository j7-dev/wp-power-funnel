<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\Interfaces;

use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;

/**
 * 處理訊息系統回傳回來的 webhook 資料 Helper
 */
interface IWebhookHelper {

	/** 取得 webhook 上 payload */
	public function get_payload(): array;

	/** 要執行的動作 */
	public function get_action(): EAction|null;

	/** 取得用戶識別 ID */
	public function get_identity_id(): string|null;

	/** 用戶識別提供者 */
	public function get_identity_provider(): EIdentityProvider;

	/** 取得活動 ID */
	public function get_activity_id(): string|null;
}
