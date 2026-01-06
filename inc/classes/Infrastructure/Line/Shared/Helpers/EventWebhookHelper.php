<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Shared\Helpers;

use J7\PowerFunnel\Contracts\Interfaces\IWebhookHelper;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\EIdentityProvider;
use LINE\Webhook\Model\Event;

/**
 * 處理 \LINE\Webhook\Model\Event 的 Utils
 */
final class EventWebhookHelper implements IWebhookHelper {

	/** @var object{
	 *     replyToken: string,
	 *     postback: object{
	 *         data: string
	 *     },
	 *     type: string,
	 *     source: object{
	 *         userId: string,
	 *         type: string
	 *     },
	 *     timestamp: int,
	 *     mode: string,
	 *     webhookEventId: string,
	 *     deliveryContext: object{
	 *         isRedelivery: boolean
	 *     }
	 * } $event_data serialize 的 LINE 事件資料 */
	private readonly object $event_data;

	/** Constructor */
	public function __construct( private readonly Event $event ) {
		$this->event_data = $this->event->jsonSerialize();
	}

	/**
	 * 取得 LINE 事件上 payload
	 *
	 * @return array<string, mixed>
	 */
	public function get_payload(): array {
		try {
			$payload_json = $this->event_data->postback->data;
			return \json_decode( $payload_json, true, 512, \JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			Plugin::logger("解析 payload 失敗 {$e->getMessage()}", 'error');
			return [];
		}
	}

	/**
	 * 取得 LINE 事件上 payload 帶的 action (要執行什麼動作)
	 *
	 * @return EAction|null
	 */
	public function get_action(): EAction|null {
		try {
			$payload = $this->get_payload();
			return EAction::tryFrom( $payload['action']);
		} catch (\Throwable $e) {
			return null;
		}
	}


	/** @return string|null 從 LINE 事件上取得 LINE UUID */
	public function get_identity_id(): string|null {
		try {
			return $this->event_data->source->userId;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/** 用戶識別提供者 */
	public function get_identity_provider(): EIdentityProvider {
		return EIdentityProvider::LINE;
	}

	/** @return string|null 從 LINE 事件上取得活動 ID */
	public function get_activity_id(): string|null {
		return $this->get_payload()['activity_id'] ?? null;
	}
}
