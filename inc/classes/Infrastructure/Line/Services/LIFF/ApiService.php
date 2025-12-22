<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Infrastructure\Line\Services\LIFF;

use J7\PowerFunnel\Contracts\DTOs\PromoLinkDTO;
use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Domains\PromoLink\Shared\Helpers\PromoLinkHelper;
use J7\PowerFunnel\Infrastructure\Line\DTOs\ProfileDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\WpUtils\Classes\ApiBase;

/**
 * 接收來自 LIFF App 傳給後端的參數
 * 發送指定訊息給指定的用戶
 */
final class ApiService extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;


	protected $namespace = 'power-funnel';

	protected $apis = [
		[
			'endpoint'            => 'liff',
			'method'              => 'post',
			'permission_callback' => '__return_true',
		],
	];

	/** Register hooks */
	public static function register_hooks(): void {
		self::instance();

		\add_action( 'power_funnel/liff_callback', [ __CLASS__, 'send_message' ], 10, 2 );
	}

	/**
	 * 複製
	 *
	 * @param \WP_REST_Request $request 包含更新選項所需資料的REST請求對象。
	 *
	 * @return \WP_REST_Response 返回包含操作結果的REST響應對象。成功時返回選項資料，失敗時返回錯誤訊息。
	 * @phpstan-ignore-next-line
	 */
	public function post_liff_callback( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var array{
		 *      userId: string,
		 *      name:string,
		 *      picture:string,
		 *      os: string,
		 *      version: string,
		 *      lineVersion: ?string,
		 *      isInClient:bool,
		 *      isLoggedIn:bool,
		 *
		 *      promoLinkId: ?string
		 * } $params
		 */
		$params     = $request->get_params();
		$profile    = new ProfileDTO( $params );
		$url_params = $params['urlParams'] ?? [];

		\do_action( 'power_funnel/liff_callback', $profile, $url_params );

		return new \WP_REST_Response(
			[
				'code'    => 'success',
				'message' => 'get liff data success',
				'data'    => null,
			],
			200
		);
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

		$activities   = ActivityService::instance()->get_activities(
			PromoLinkDTO::of(  (int) $promo_link_id )->to_activity_params()
			);
		$line_service = new MessageService();

		// 建立 Carousel 的欄位
		$columns = [];

		foreach ($activities as $activity) {
			$columns[] = new \LINE\Clients\MessagingApi\Model\CarouselColumn(
				[
					'thumbnailImageUrl'    => $activity->thumbnail_url,
					'imageBackgroundColor' => '#FFFFFF',
					'title'                => $activity->title,
					'text'                 => $activity->description ?: 'No description.',
					'actions'              => [
						new \LINE\Clients\MessagingApi\Model\PostbackAction(
							[
								'type'  => 'postback', // uri | postback | message
								'label' => '立即報名',
								'data'  => 'action=register&event_id=1',
							]
						),
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
				'altText'  => '這是 Carousel 訊息（請在手機上查看）',
				'template' => $carousel_template,
			]
		);

		// 發送訊息
		$line_service->send_template_message( $profile->userId, $template_message );

		// 原本的文字訊息（已註解）
		// $line_service->send_text_message(
		// $profile->userId,
		// 'Hello from Power Funnel! 你輸入的 promoLinkId:' . $promo_link_id
		// );
	}
}
