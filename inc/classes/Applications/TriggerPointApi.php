<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

/** 觸發條件列表 API */
final class TriggerPointApi extends ApiBase {
	use SingletonTrait;

	/** @var string $namespace */
	protected $namespace = 'power-funnel';

	/**
	 * @var array<array{
	 * endpoint:string,
	 * method:string,
	 * permission_callback?: callable|null,
	 * callback?: callable|null,
	 * schema?: array<string, mixed>|null
	 * }> $apis APIs
	 * */
	protected $apis = [
		[
			'endpoint' => 'trigger-points',
			'method'   => 'get',
		],
	];

	/** Register hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 取得所有已註冊的觸發條件列表
	 *
	 * 資料來源：Repository::get_trigger_points()，經由 apply_filters 允許第三方擴充。
	 *
	 * @param \WP_REST_Request $request REST 請求對象。
	 * @return \WP_REST_Response 返回包含觸發條件列表的 REST 響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function get_trigger_points_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$trigger_point_dtos = Repository::get_trigger_points();
		$data               = \array_values(
			\array_map(
				static fn( $dto ) => $dto->to_array(),
				$trigger_point_dtos
			)
		);

		return new \WP_REST_Response(
			[
				'code'    => 'operation_success',
				'message' => '操作成功',
				'data'    => $data,
			]
		);
	}
}
