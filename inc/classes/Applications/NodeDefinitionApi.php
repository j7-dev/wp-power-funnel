<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Applications;

use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Repository;
use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Traits\SingletonTrait;

/** 節點定義列表 API */
final class NodeDefinitionApi extends ApiBase {
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
			'endpoint' => 'node-definitions',
			'method'   => 'get',
		],
	];

	/** 註冊 hooks */
	public static function register_hooks(): void {
		self::instance();
	}

	/**
	 * 取得所有已註冊的節點定義列表
	 *
	 * 資料來源：Repository::get_node_definitions()，經由 apply_filters 允許第三方擴充。
	 *
	 * @param \WP_REST_Request $request REST 請求對象。
	 * @return \WP_REST_Response 返回包含節點定義列表的 REST 響應對象。
	 * @phpstan-ignore-next-line
	 */
	public function get_node_definitions_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$node_definitions = Repository::get_node_definitions();
		$data             = \array_values(
			\array_map(
				static fn( $definition ) => $definition->to_array(),
				$node_definitions
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
