<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Contracts\DTOs\PromoLinkDTO;
use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Infrastructure\Line\DTOs\ProfileDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;

/**
 * 發 Line Carousel 給用戶
 */
final class SendLine {

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action( 'power_funnel/liff_callback', [ __CLASS__, 'send_message' ], 10, 2 );
	}


	/**
	 * 發送訊息給用戶
	 *
	 * @param ProfileDTO   $profile 用戶資料
	 * @param array<mixed> $url_params URL 參數
	 * @return void
	 */
	public static function send_message( ProfileDTO $profile, array $url_params ): void {
		$promo_link_id = $url_params['promoLinkId'] ?? null;
		if ( !$promo_link_id ) {
			return;
		}
		$promo_link_dto = PromoLinkDTO::of( (int) $promo_link_id );
		$activities     = ActivityService::instance()->get_activities(
			$promo_link_dto->to_activity_params()
		);
		if (!$activities) {
			return;
		}

		$line_service = MessageService::instance();

		// 建立 Carousel 的欄位
		$columns = [];

		foreach ($activities as $activity) {
			$columns[] = new \LINE\Clients\MessagingApi\Model\CarouselColumn(
				[
					'thumbnailImageUrl'    => $activity->thumbnail_url,
					'imageBackgroundColor' => '#FFFFFF',
					'title'                => $activity->title ?: ' ',
					'text'                 => $activity->description ?: ' ',
					'actions'              => [
						new \LINE\Clients\MessagingApi\Model\PostbackAction($promo_link_dto->get_line_post_back_params($activity)),
					],
				]
			);
		}

		// 建立 Carousel Template
		$carousel_template = new \LINE\Clients\MessagingApi\Model\CarouselTemplate(
			[
				'type'             => 'carousel',
				'columns'          => $columns,
				'imageAspectRatio' => 'rectangle', // 'rectangle' 或 'square'
				'imageSize'        => 'cover',     // 'cover' 或 'contain'
			]
		);

		// 建立 Template Message
		$template_message = new \LINE\Clients\MessagingApi\Model\TemplateMessage(
			[
				'type'     => 'template',
				'altText'  => $promo_link_dto->get_alt_text(),
				'template' => $carousel_template,
			]
		);

		// 發送訊息
		$line_service->send_template_message( $profile->userId, $template_message );
	}
}
