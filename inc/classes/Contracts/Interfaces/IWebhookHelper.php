<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\Interfaces;

use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;

/**
 * 處理訊息系統回傳回來的 webhook 資料 Helper
 */
interface IWebhookHelper {

	/** @return array 取得 webhook 上 payload */
	public function get_payload(): array;

	/** @return EAction|null 要執行的動作 */
	public function get_action(): EAction|null;

	/** @return string|null 取得用戶識別 ID */
	public function get_identity_id(): string|null;

	/** @return EIdentityProvider 用戶識別提供者 */
	public function get_identity_provider(): EIdentityProvider;

	/** @return string|null 取得活動 ID */
	public function get_activity_id(): string|null;

	/** @return string|null 從 LINE 事件上取得 promo link ID */
	public function get_promo_link_id(): string|null;
}
