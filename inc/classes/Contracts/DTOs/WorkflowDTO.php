<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Repository;
use J7\WpUtils\Classes\DTO;

/**
 * 多個 Node 會組合成一個 workflow
 */
final class WorkflowDTO extends DTO {

	/** @var string workflow ID */
	public string $id;

	/** @var string workflow 名稱 */
	public string $name;

	/** @var string 這個 workflow 應該掛在哪裡的 hook name */
	public string $trigger_point;

	/** @var array<NodeDTO> $nodes 這個 workflow 的節點 */
	public array $nodes;

	/** 取得實例 */
	public static function of( $post_id ): self {
		$nodes_array = \get_post_meta($post_id, 'nodes', true);
		$nodes_array = \is_array($nodes_array) ? $nodes_array : [];

		$args =[
			'id'            => $post_id,
			'name'          => \get_the_title($post_id),
			'trigger_point' => \get_post_meta($post_id, 'trigger_point', true),
			'nodes'         => NodeDTO::parse_array( $nodes_array ),
		];
		return new self($args);
	}

	/** 註冊 workflow */
	public function register(): void {
		foreach ( $this->nodes as $node ) :
			\add_filter(
				$this->trigger_point,
				static function ( WorkflowContextDTO $context ) use ( $node ): WorkflowContextDTO {
					if ( \call_user_func_array( $node->match_callback, $node->match_callback_params ) ) {
						$definition = Repository::get_node_definition( $node->node_definition_id );
						if (!$definition) {
							// 找不到模組定義就跳過
							return $context;
						}

						$definition->node    = $node;
						$definition->context = $context;

						$result = $definition->try_execute();
						$context->add_result( $node->priority, $result);

						$definition->node    = null;
						$definition->context = null;
						return $context;
					}
					return $context;
				},
				$node->priority
			);
		endforeach;
	}
}
