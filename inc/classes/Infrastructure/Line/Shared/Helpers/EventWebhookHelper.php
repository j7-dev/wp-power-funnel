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

	/** @var mixed serialize 的 LINE 事件資料 */
	private readonly mixed $event_data;

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
			if (!\is_object($this->event_data) || !isset($this->event_data->postback)) {
				return [];
			}
			$postback = $this->event_data->postback;
			if (!\is_object($postback) || !isset($postback->data)) {
				return [];
			}
			$payload_json = $postback->data;
			if (!\is_string($payload_json)) {
				return [];
			}
			/** @var array<string, mixed> $decoded */
			$decoded = \json_decode( $payload_json, true, 512, \JSON_THROW_ON_ERROR);
			return $decoded;
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
			$action  = $payload['action'] ?? null;
			if (!\is_string($action) && !\is_int($action)) {
				return null;
			}
			return EAction::tryFrom( $action);
		} catch (\Throwable $e) {
			return null;
		}
	}


	/** @return string|null 從 LINE 事件上取得 LINE UUID */
	public function get_identity_id(): string|null {
		if (!\is_object($this->event_data) || !isset($this->event_data->source)) {
			return null;
		}
		$source = $this->event_data->source;
		if (!\is_object($source) || !isset($source->userId)) {
			return null;
		}
		return \is_string($source->userId) ? $source->userId : null;
	}

	/** 用戶識別提供者 */
	public function get_identity_provider(): EIdentityProvider {
		return EIdentityProvider::LINE;
	}

	/** @return string|null 從 LINE 事件上取得活動 ID */
	public function get_activity_id(): string|null {
		$value = $this->get_payload()['activity_id'] ?? null;
		return \is_string($value) ? $value : null;
	}

	/** @return string|null 從 LINE 事件上取得 promo link ID */
	public function get_promo_link_id(): string|null {
		$value = $this->get_payload()['promo_link_id'] ?? null;
		return \is_string($value) ? $value : null;
	}
}
