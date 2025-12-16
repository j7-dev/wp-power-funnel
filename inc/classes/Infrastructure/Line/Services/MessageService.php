<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services;

use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\ApiException;
use LINE\Clients\MessagingApi\Model\BroadcastRequest;
use LINE\Clients\MessagingApi\Model\Message;
use LINE\Clients\MessagingApi\Model\MulticastRequest;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\PushMessageResponse;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\ReplyMessageResponse;
use LINE\Clients\MessagingApi\Model\TemplateMessage;
use LINE\Clients\MessagingApi\Model\TextMessage;

/**
 * LINE 訊息服務
 * 負責處理 LINE 訊息的發送
 */
final class MessageService {


	/**
	 * MessagingApiApi 實例
	 *
	 * @var MessagingApiApi
	 */
	private MessagingApiApi $api;

	/**
	 * Constructor
	 *
	 * @param MessagingApiApi|null $api MessagingApiApi 實例，若為 null 則使用工廠建立
	 * @throws \Exception 當無法建立 API 實例時拋出異常
	 */
	public function __construct( ?MessagingApiApi $api = null ) {
		$this->api = $api ?? MessagingApiFactory::create();
	}

	/**
	 * 發送模板訊息給單一用戶
	 *
	 * @param string          $user_id 用戶 ID
	 * @param TemplateMessage $template_message 模板訊息物件
	 * @param string|null     $retry_key 重試金鑰（UUID 格式）
	 * @return PushMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function send_template_message(
		string $user_id,
		TemplateMessage $template_message,
		?string $retry_key = null
	): PushMessageResponse {
		$request = new PushMessageRequest(
			[
				'to'       => $user_id,
				'messages' => [ $template_message ],
			]
		);

		return $this->push_message($request, $retry_key);
	}

	/**
	 * 發送 Push Message
	 *
	 * @param PushMessageRequest $request Push 請求物件
	 * @param string|null        $retry_key 重試金鑰（UUID 格式）
	 * @return PushMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	private function push_message(
		PushMessageRequest $request,
		?string $retry_key = null
	): PushMessageResponse {
		try {

			$response = $this->api->pushMessage($request, $retry_key);
			if (!$response instanceof PushMessageResponse) {
				throw new \Exception('Unexpected response type from LINE Messaging API. ' . $response->getMessage());
			}
			return $response;
		} catch (ApiException $e) {
			throw new \Exception(
				sprintf(
					'Push Message 發送失敗: [%1$d] %2$s %3$s',
					$e->getCode(),
					$e->getMessage(),
					(string) $e->getResponseBody() // @phpstan-ignore-line
				)
			);
		}
	}

	/**
	 * 發送文字訊息給單一用戶
	 *
	 * @param string      $user_id 用戶 ID
	 * @param string      $text 訊息文字
	 * @param string|null $retry_key 重試金鑰（UUID 格式）
	 * @return PushMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function send_text_message(
		string $user_id,
		string $text,
		?string $retry_key = null
	): PushMessageResponse {
		$message = new TextMessage(
			[
				'type' => 'text',
				'text' => $text,
			]
		);

		$request = new PushMessageRequest(
			[
				'to'       => $user_id,
				'messages' => [ $message ],
			]
		);

		return $this->push_message($request, $retry_key);
	}

	/**
	 * 發送多則訊息給單一用戶
	 *
	 * @param string         $user_id 用戶 ID
	 * @param array<Message> $messages 訊息陣列（最多 5 則）
	 * @param string|null    $retry_key 重試金鑰（UUID 格式）
	 * @return PushMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function send_messages(
		string $user_id,
		array $messages,
		?string $retry_key = null
	): PushMessageResponse {
		if (count($messages) > 5) {
			throw new \Exception('一次最多只能發送 5 則訊息');
		}

		if (count($messages) < 1) {
			throw new \Exception('至少需要一則訊息');
		}

		$request = new PushMessageRequest(
			[
				'to'       => $user_id,
				'messages' => $messages,
			]
		);

		return $this->push_message($request, $retry_key);
	}

	/**
	 * 發送訊息給多個用戶（Multicast）
	 *
	 * @param array<string>  $user_ids 用戶 ID 陣列（最多 500 個）
	 * @param array<Message> $messages 訊息陣列（最多 5 則）
	 * @return object
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function multicast( array $user_ids, array $messages ): object {
		if (count($user_ids) > 500) {
			throw new \Exception('Multicast 最多只能發送給 500 個用戶');
		}

		if (count($messages) > 5 || count($messages) < 1) {
			throw new \Exception('訊息數量必須在 1-5 則之間');
		}

		$request = new MulticastRequest(
			[
				'to'       => $user_ids,
				'messages' => $messages,
			]
		);

		try {
			return $this->api->multicast($request);
		} catch (ApiException $e) {
			throw new \Exception(
				sprintf(
					'Multicast 發送失敗: [%1$d] %2$s %3$s',
					$e->getCode(),
					$e->getMessage(),
					(string) $e->getResponseBody() // @phpstan-ignore-line
				)
			);
		}
	}

	/**
	 * 廣播訊息給所有好友（Broadcast）
	 *
	 * @param array<Message> $messages 訊息陣列（最多 5 則）
	 * @return object
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function broadcast( array $messages ): object {
		if (count($messages) > 5 || count($messages) < 1) {
			throw new \Exception('訊息數量必須在 1-5 則之間');
		}

		$request = new BroadcastRequest(
			[
				'messages' => $messages,
			]
		);

		try {
			return $this->api->broadcast($request);
		} catch (ApiException $e) {
			throw new \Exception(
				sprintf(
					'Broadcast 發送失敗: [%1$d] %2$s %3$s',
					$e->getCode(),
					$e->getMessage(),
					(string) $e->getResponseBody() // @phpstan-ignore-line
				)
			);
		}
	}

	/**
	 * 回覆文字訊息
	 *
	 * @param string $reply_token 回覆 Token
	 * @param string $text 訊息文字
	 * @return ReplyMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function reply_text( string $reply_token, string $text ): ReplyMessageResponse {
		$message = new TextMessage(
			[
				'type' => 'text',
				'text' => $text,
			]
		);

		return $this->reply($reply_token, [ $message ]);
	}

	/**
	 * 回覆訊息
	 *
	 * @param string         $reply_token 回覆 Token
	 * @param array<Message> $messages 訊息陣列（最多 5 則）
	 * @return ReplyMessageResponse
	 * @throws \Exception 當發送失敗時拋出異常
	 */
	public function reply( string $reply_token, array $messages ): ReplyMessageResponse {
		if (count($messages) > 5 || count($messages) < 1) {
			throw new \Exception('訊息數量必須在 1-5 則之間');
		}

		$request = new ReplyMessageRequest(
			[
				'replyToken' => $reply_token,
				'messages'   => $messages,
			]
		);

		try {
			$response = $this->api->replyMessage($request);
			if (!$response instanceof ReplyMessageResponse) {
				throw new \Exception('Unexpected response type from LINE Messaging API. ' . $response->getMessage());
			}
			return $response;
		} catch (ApiException $e) {
			throw new \Exception(
				sprintf(
					'Reply 發送失敗: [%1$d] %2$s %3$s',
					$e->getCode(),
					$e->getMessage(),
					(string) $e->getResponseBody() // @phpstan-ignore-line
				)
			);
		}
	}

	/**
	 * 取得用戶資料
	 *
	 * @param string $user_id 用戶 ID
	 * @return \LINE\Clients\MessagingApi\Model\UserProfileResponse
	 * @throws \Exception 當取得失敗時拋出異常
	 */
	public function get_profile( string $user_id ): \LINE\Clients\MessagingApi\Model\UserProfileResponse {
		try {
			return $this->api->getProfile($user_id);
		} catch (ApiException $e) {
			throw new \Exception(
				sprintf(
					'取得用戶資料失敗: [%1$d] %2$s %3$s',
					$e->getCode(),
					$e->getMessage(),
					(string) $e->getResponseBody() // @phpstan-ignore-line
				)
			);
		}
	}

	/**
	 * 取得 MessagingApiApi 實例
	 * 用於需要直接存取 API 的進階用途
	 *
	 * @return MessagingApiApi
	 */
	public function get_api(): MessagingApiApi {
		return $this->api;
	}
}
