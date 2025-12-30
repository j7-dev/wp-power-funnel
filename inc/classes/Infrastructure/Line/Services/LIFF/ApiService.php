<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Infrastructure\Line\Services\LIFF;

use J7\PowerFunnel\Infrastructure\Line\DTOs\ProfileDTO;
use J7\WpUtils\Classes\ApiBase;

/**
 * 接收來自 LIFF App 傳給後端的參數
 * 發送指定訊息給指定的用戶
 */
final class ApiService extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'power-funnel';

	/** @var array{endpoint: string, method: string, permission_callback: ?callable}[] APIs */
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
}
