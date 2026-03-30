<?php

/**
 * LINE 互動觸發點整合測試。
 *
 * 驗證 LINE 事件（follow/unfollow/message）能正確觸發對應的 pf/trigger/* hook。
 *
 * @group trigger-points
 * @group line-trigger
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\TriggerPoint;

use J7\PowerFunnel\Domains\Workflow\Services\TriggerPointService;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * LINE 互動觸發點測試
 *
 * Feature: LINE 用戶關注後觸發工作流
 * Feature: LINE 用戶取消關注後觸發工作流
 * Feature: 收到 LINE 訊息後觸發工作流
 */
class LineTriggerTest extends IntegrationTestCase {

	/** @var array<string, array<string, mixed>> 已觸發的事件記錄 */
	private array $fired_triggers = [];

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		TriggerPointService::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->fired_triggers = [];

		// 監聽所有相關觸發點
		foreach ([
			ETriggerPoint::LINE_FOLLOWED->value,
			ETriggerPoint::LINE_UNFOLLOWED->value,
			ETriggerPoint::LINE_MESSAGE_RECEIVED->value,
		] as $hook) {
			$short_name                          = str_replace('pf/trigger/', '', $hook);
			$this->fired_triggers[ $short_name ] = [];
			\add_action(
				$hook,
				/**
				 * @param array<string, mixed> $context_callable_set
				 */
				function ( array $context_callable_set ) use ( $short_name ): void {
					$this->fired_triggers[ $short_name ][] = $context_callable_set;
				},
				999
			);
		}
	}

	/**
	 * 建立帶有指定 userId 的 LINE Follow 事件
	 *
	 * @param string|null $user_id LINE User ID，null 表示無 userId
	 * @return \LINE\Webhook\Model\FollowEvent
	 */
	private function make_follow_event( ?string $user_id = 'U_test_user_123' ): \LINE\Webhook\Model\FollowEvent {
		$source_data = $user_id ? [ 'type' => 'user', 'userId' => $user_id ] : [ 'type' => 'user' ];
		return \LINE\Webhook\Model\FollowEvent::fromAssocArray([
			'type'            => 'follow',
			'mode'            => 'active',
			'timestamp'       => (int) (\microtime(true) * 1000),
			'webhookEventId'  => 'test_event_' . \uniqid(),
			'replyToken'      => 'test_reply_token',
			'source'          => $source_data,
			'deliveryContext' => [ 'isRedelivery' => false ],
			'follow'          => [ 'isUnblocked' => false ],
		]);
	}

	/**
	 * 建立帶有指定 userId 的 LINE Unfollow 事件
	 *
	 * @param string|null $user_id LINE User ID，null 表示無 userId
	 * @return \LINE\Webhook\Model\UnfollowEvent
	 */
	private function make_unfollow_event( ?string $user_id = 'U_test_user_123' ): \LINE\Webhook\Model\UnfollowEvent {
		$source_data = $user_id ? [ 'type' => 'user', 'userId' => $user_id ] : [ 'type' => 'user' ];
		return \LINE\Webhook\Model\UnfollowEvent::fromAssocArray([
			'type'            => 'unfollow',
			'mode'            => 'active',
			'timestamp'       => (int) (\microtime(true) * 1000),
			'webhookEventId'  => 'test_event_' . \uniqid(),
			'source'          => $source_data,
			'deliveryContext' => [ 'isRedelivery' => false ],
		]);
	}

	/**
	 * 建立帶有文字訊息的 LINE Message 事件
	 *
	 * @param string      $message_text 訊息內容
	 * @param string|null $user_id      LINE User ID，null 表示無 userId
	 * @return \LINE\Webhook\Model\MessageEvent
	 */
	private function make_message_event( string $message_text = 'Hello!', ?string $user_id = 'U_test_user_123' ): \LINE\Webhook\Model\MessageEvent {
		$source_data = $user_id ? [ 'type' => 'user', 'userId' => $user_id ] : [ 'type' => 'user' ];
		return \LINE\Webhook\Model\MessageEvent::fromAssocArray([
			'type'            => 'message',
			'mode'            => 'active',
			'timestamp'       => (int) (\microtime(true) * 1000),
			'webhookEventId'  => 'test_event_' . \uniqid(),
			'replyToken'      => 'test_reply_token',
			'source'          => $source_data,
			'deliveryContext' => [ 'isRedelivery' => false ],
			'message'         => [
				'type' => 'text',
				'id'   => 'msg_' . \uniqid(),
				'text' => $message_text,
			],
		]);
	}

	// ========== Rule: LINE follow 事件觸發 line_followed ==========

	/**
	 * Feature: LINE 用戶關注後觸發工作流
	 * Example: LINE follow 事件觸發 pf/trigger/line_followed
	 *
	 * @group happy
	 */
	public function test_LINE_follow事件觸發line_followed(): void {
		// Given 一個有效的 LINE Follow 事件
		$event = $this->make_follow_event('U_follow_user');

		// When 觸發 LINE webhook 的 follow type-only hook
		\do_action('power_funnel/line/webhook/follow', $event);

		// Then pf/trigger/line_followed 被觸發
		$this->assertCount(1, $this->fired_triggers['line_followed'], 'line_followed 應被觸發一次');
	}

	/**
	 * Example: context_callable 包含 line_user_id 和 event_type
	 *
	 * @group happy
	 */
	public function test_LINE_follow_context包含正確欄位(): void {
		// Given 一個有效的 LINE Follow 事件，userId 為 U_test_123
		$event = $this->make_follow_event('U_test_123');

		// When 觸發 hook
		\do_action('power_funnel/line/webhook/follow', $event);

		$this->assertCount(1, $this->fired_triggers['line_followed'], 'line_followed 應被觸發');

		$context_callable_set = $this->fired_triggers['line_followed'][0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含 line_user_id 和 event_type
		$this->assertSame('U_test_123', $context['line_user_id'], 'line_user_id 應相符');
		$this->assertSame('follow', $context['event_type'], 'event_type 應為 follow');
	}

	// ========== Rule: LINE unfollow 事件觸發 line_unfollowed ==========

	/**
	 * Feature: LINE 用戶取消關注後觸發工作流
	 * Example: LINE unfollow 事件觸發 pf/trigger/line_unfollowed
	 *
	 * @group happy
	 */
	public function test_LINE_unfollow事件觸發line_unfollowed(): void {
		// Given 一個有效的 LINE Unfollow 事件
		$event = $this->make_unfollow_event('U_unfollow_user');

		// When 觸發 LINE webhook 的 unfollow type-only hook
		\do_action('power_funnel/line/webhook/unfollow', $event);

		// Then pf/trigger/line_unfollowed 被觸發
		$this->assertCount(1, $this->fired_triggers['line_unfollowed'], 'line_unfollowed 應被觸發一次');
		$this->assertEmpty($this->fired_triggers['line_followed'], 'line_followed 不應被觸發');
	}

	// ========== Rule: LINE message 事件觸發 line_message_received ==========

	/**
	 * Feature: 收到 LINE 訊息後觸發工作流
	 * Example: LINE message 事件觸發 pf/trigger/line_message_received
	 *
	 * @group happy
	 */
	public function test_LINE_message事件觸發line_message_received(): void {
		// Given 一個有效的 LINE Message 事件
		$event = $this->make_message_event('你好！', 'U_msg_user');

		// When 觸發 LINE webhook 的 message type-only hook
		\do_action('power_funnel/line/webhook/message', $event);

		// Then pf/trigger/line_message_received 被觸發
		$this->assertCount(1, $this->fired_triggers['line_message_received'], 'line_message_received 應被觸發一次');
	}

	/**
	 * Example: LINE message context 包含 line_user_id、event_type 和 message_text
	 *
	 * @group happy
	 */
	public function test_LINE_message_context包含訊息文字(): void {
		// Given 一個包含文字訊息的 LINE Message 事件
		$event = $this->make_message_event('你好世界！', 'U_msg_user_2');

		// When 觸發 hook
		\do_action('power_funnel/line/webhook/message', $event);

		$this->assertCount(1, $this->fired_triggers['line_message_received'], 'line_message_received 應被觸發');

		$context_callable_set = $this->fired_triggers['line_message_received'][0];
		$context              = ($context_callable_set['callable'])(...$context_callable_set['params']);

		// Then context 包含 line_user_id、event_type、message_text
		$this->assertSame('U_msg_user_2', $context['line_user_id'], 'line_user_id 應相符');
		$this->assertSame('message', $context['event_type'], 'event_type 應為 message');
		$this->assertSame('你好世界！', $context['message_text'], 'message_text 應相符');
	}

	// ========== Rule: 無 userId 的事件不觸發 ==========

	/**
	 * Example: LINE follow 事件缺少 userId 時不觸發
	 *
	 * @group edge
	 */
	public function test_缺少userId的事件不觸發(): void {
		// Given 一個沒有 userId 的 LINE Follow 事件
		$event = $this->make_follow_event(null);

		// When 觸發 LINE webhook 的 follow type-only hook
		\do_action('power_funnel/line/webhook/follow', $event);

		// Then pf/trigger/line_followed 不被觸發
		$this->assertEmpty($this->fired_triggers['line_followed'], '缺少 userId 的事件不應觸發');
	}
}
