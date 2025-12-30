<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\Services\LIFF;

use J7\PowerFunnel\Bootstrap;
use J7\PowerFunnel\Contracts\DTOs\PromoLinkDTO;
use J7\PowerFunnel\Domains\Activity\Services\ActivityService;
use J7\PowerFunnel\Infrastructure\Line\DTOs\ProfileDTO;
use J7\PowerFunnel\Infrastructure\Line\Services\MessageService;
use J7\PowerFunnel\Plugin;

/**
 * LIFF App 的前端顯示介面
 */
final class Register {

	private const LIFF_QUERY_VAR = 'liff';

	/** Register hooks */
	public static function register_hooks(): void {
		\add_action('init', [ __CLASS__, 'add_liff_rewrite_rule' ]);
		\add_filter('query_vars', [ __CLASS__, 'register_liff_query_var' ]);
		\add_filter('template_include', [ __CLASS__, 'liff_locate_template' ]);
		\add_action( 'power_funnel/liff_callback', [ __CLASS__, 'send_message' ], 10, 2 );
	}

	/** Enqueue frontend assets */
	public static function enqueue_script(): void {
		if (!self::is_liff()) {
			return;
		}

		Bootstrap::enqueue_script();
	}

	/** Add rewrite rule  */
	public static function add_liff_rewrite_rule() {
		\add_rewrite_rule(
			'^' . self::LIFF_QUERY_VAR . '/?$', // 路由規則
			'index.php?' . self::LIFF_QUERY_VAR . '=1', // 對應的 query var
			'top'
		);
	}

	/** 註冊 Query Var */
	public static function register_liff_query_var( array $vars ): array {
		$vars[] = self::LIFF_QUERY_VAR;
		return $vars;
	}

	/**
	 * 用戶造訪 /liff 時要套用的 template
	 * 優先使用主題的 page-liff.php，若不存在則使用外掛內建的模板
	 *
	 * @param string $template 原始模板路徑
	 * @return string 最終使用的模板路徑
	 */
	public static function liff_locate_template( string $template ): string {
		if (self::is_liff()) {
			Bootstrap::enqueue_script();
			// 優先檢查主題是否有 page-liff.php（子主題 > 父主題）
			$theme_template = \locate_template('page-liff.php');
			if (!empty($theme_template)) {
				return $theme_template;
			}

			// 使用外掛內建的模板
			$plugin_template = Plugin::$dir . '/inc/templates/page-liff.php';
			if (\file_exists($plugin_template)) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/** 是否是 /liff */
	public static function is_liff(): bool {
		return (bool) \get_query_var(self::LIFF_QUERY_VAR);
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
		$line_service   = new MessageService();

		// 建立 Carousel 的欄位
		$columns = [];

		foreach ($activities as $activity) {
			$columns[] = new \LINE\Clients\MessagingApi\Model\CarouselColumn(
				[
					'thumbnailImageUrl'    => $activity->thumbnail_url,
					'imageBackgroundColor' => '#FFFFFF',
					'title'                => $activity->title,
					'text'                 => $activity->description,
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
				'altText'  => $promo_link_dto->get_label(),
				'template' => $carousel_template,
			]
		);

		// 發送訊息
		$line_service->send_template_message( $profile->userId, $template_message );
	}
}
