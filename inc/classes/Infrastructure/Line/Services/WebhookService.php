<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services;

use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO;
use J7\PowerFunnel\Infrastructure\Line\Shared\Helpers\EventWebhookHelper;
use J7\PowerFunnel\Plugin;
use J7\WpUtils\Classes\ApiBase;
use LINE\Constants\HTTPHeader;
use LINE\Parser\EventRequestParser;

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

	/** Register hooks */
	public static function register_hooks(): void {
		self::instance( );
	}

	/**
	 * 處理 LINE 回調的 Webhook 事件
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception 當處理過程中發生錯誤時拋出異常
	 * @phpstan-ignore-next-line
	 */
	public function post_line_callback_callback( \WP_REST_Request $request ):\WP_REST_Response { // phpcs:ignore
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

			if ($event instanceof \LINE\Webhook\Model\MessageEvent) {
				$message = $event->getMessage();

				if ($message instanceof \LINE\Webhook\Model\TextMessageContent) {
					// 用戶回覆的訊息
					$reply_text = $message->getText();

					Plugin::logger(
						"LINE webhook event {$event->getType()} #{$event->getWebhookEventId()}",
						'info',
						[
							'message'    => $message,
							'reply_text' => $reply_text,
							'event'      => $event->jsonSerialize(),
						]
					);
				}
			}

			$action = ( new EventWebhookHelper( $event) )->get_action();

			\do_action( "power_funnel/line/webhook/{$event->getType()}/{$action?->value}", $event );
		}

		return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}
}
