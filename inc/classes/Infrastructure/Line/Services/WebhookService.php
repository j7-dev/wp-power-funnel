<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services;

use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\WpUtils\Classes\ApiBase;
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidEventRequestException;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;

/** 負責處理 用戶與 LINE OA 互動的 Webhook 事件 */
final class WebhookService extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'power-funnel';

	/** @var array{endpoint: string, method: string, permission_callback: ?callable}[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'line-callback',
			'method'              => 'post',
			'permission_callback' => '__return_true',
		],
	];

	/**
	 * 處理 LINE 回調的 Webhook 事件
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 當處理過程中發生錯誤時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_line_callback_callback( $request ) { // phpcs:ignore
		$setting = SettingDTO::instance();

		// 若 LINE 設定未完成則回報
		if ( ! $setting->is_valid() ) {
			throw new \Exception('LINE 設定尚未完成');
		}

		// 取得 Signature 標頭
		$signature = $request->get_header( HTTPHeader::LINE_SIGNATURE );
		if ( empty( $signature ) ) {
			throw new \Exception('缺少 LINE 簽章標頭');
		}

		// 取得請求 body
		$body = $request->get_body();

		// 驗證簽章並解析事件
		$parsed_events = EventRequestParser::parseEventRequest(
				$body,
				$setting->channel_secret,
				$signature
			);

		// 處理每個事件
		foreach ( $parsed_events->getEvents() as $event ) {

			Plugin::logger(
				"LINE webhook event {$event->getType()} #{$event->getWebhookEventId()}",
				'info',
				[
					'event' => $event->jsonSerialize(),
				]
				);
			// 目前只處理訊息事件
			if ( ! ( $event instanceof MessageEvent ) ) {
				\do_action( 'power_funnel_line_non_message_event', $event );
				continue;
			}

			$message = $event->getMessage();

			// 目前只處理文字訊息
			if ( ! ( $message instanceof TextMessageContent ) ) {
				\do_action( 'power_funnel_line_non_text_message', $message, $event );
				continue;
			}

			// 記錄收到的文字訊息
			$reply_text = $message->getText();
			\do_action( 'power_funnel_line_text_message_received', $reply_text, $message, $event );

			// 可在此添加業務邏輯，例如使用 MessageService 回覆訊息
		}

		return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}
}
