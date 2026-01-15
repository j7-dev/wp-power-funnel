<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\Powerhouse\Contracts\DTOs\CallableDTO;
use J7\Powerhouse\Shared\Helpers\ReplaceHelper;

/**
 * 幫助從上下文或者節點身上拿資料
 * 還有做模板訊息的字串取代
 */
final class ParamHelper {

	private const CONTEXT = 'context';

	/** Constructor */
	public function __construct(
		/** @var NodeDTO $node 節點 */
		private readonly NodeDTO $node,
		/** @var WorkflowDTO $workflow 工作流程 */
		private readonly WorkflowDTO $workflow,
	) {
	}

	/**
	 * 取得參數
	 * 如果用戶輸入 context 代表要從上下文拿資料
	 */
	public function try_get_param( string $key ): mixed {
		$maybe_value = $this->node->try_get_param( $key);
		if ($maybe_value instanceof CallableDTO) {
			return $maybe_value->get_result();
		}
		if (self::CONTEXT === $maybe_value) {
			return $this->workflow->context[ $key ] ?? null;
		}

		return $maybe_value;
	}

	/** 用常用物件取代 */
	public function replace( string $template ): string {
		$user         = $this->try_get_param( 'user');
		$product      = $this->try_get_param( 'product');
		$post         = $this->try_get_param( 'post');
		$order        = $this->try_get_param( 'order');
		$subscription = $this->try_get_param( 'subscription');
		$activity     = $this->try_get_param( 'activity');

		$helper = new ReplaceHelper($template);
		return $helper->replace( $user )->replace( $product )->replace( $post )->replace( $order )->replace( $subscription )->replace( $activity )->get_replaced_template();
	}
}
