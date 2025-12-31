<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Infrastructure\Line\Shared\Helpers\EventWebhookHelper;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\RegistrationRepository;
use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\ELineActionType;
use LINE\Webhook\Model\Event;

/**
 * 用戶報名指定活動
 */
final class RegisterActivity {

	/** Register hooks */
	public static function register_hooks(): void {
		// LINE
		$line_action_type = ELineActionType::POSTBACK;
		$action           = EAction::REGISTER;
		\add_action( "power_funnel/line/webhook/{$line_action_type->value}/{$action->value}", [ __CLASS__, 'line_postback' ] );
	}


	/**
	 * 用戶報名指定活動
	 *
	 * @param Event $event LINE 事件
	 * @return void
	 */
	public static function line_postback( Event $event ): void {
		$helper      = new EventWebhookHelper( $event);
		$activity_id = $helper->get_activity_id();
		$identity_id = $helper->get_identity_id();
		if (!$activity_id || !$identity_id) {
			throw new \Exception( "活動 ID #{$activity_id} 或用戶 ID {$identity_id} 無法取得" );
		}
		$activity_dto = ActivityService::instance()->get_activity( $activity_id );
		if (!$activity_dto) {
			throw new \Exception( "找不到活動 #{$activity_id}" );
		}

		$identity_provider = $helper->get_identity_provider()->value;
		$args              = [
			'post_title' => "{$identity_provider} 用戶報名 {$activity_dto->title}",
			'meta_input' => [
				'identity_id'       => $identity_id,
				'identity_provider' => $identity_provider,
				'activity_id'       => $activity_id,
			],
		];
		RegistrationRepository::create( $args);
	}
}
