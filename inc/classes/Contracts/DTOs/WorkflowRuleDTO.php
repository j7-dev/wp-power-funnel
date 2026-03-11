<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Infrastructure\Repositories\Workflow\Repository;
use J7\WpUtils\Classes\DTO;

/**
 * 多個 Node 會組合成一個 workflow_rule
 */
final class WorkflowRuleDTO extends DTO {

	/** @var string workflow_rule ID */
	public string $id;

	/** @var string workflow_rule 名稱 */
	public string $name;

	/** @var string 這個 workflow_rule 應該掛在哪裡的 hook name */
	public string $trigger_point;

	/** @var array<NodeDTO> $nodes 這個 workflow_rule 的節點 */
	public array $nodes;

	/** 取得實例 */
	public static function of( string $post_id ): self {
		$int_post_id = (int) $post_id;
		$nodes_array = \get_post_meta($int_post_id, 'nodes', true);
		$nodes_array = \is_array($nodes_array) ? $nodes_array : [];

		$args =[
			'id'            => $post_id,
			'name'          => \get_the_title($int_post_id),
			'trigger_point' => (string) \get_post_meta($int_post_id, 'trigger_point', true),
			'nodes'         => NodeDTO::parse_array( $nodes_array ),
		];
		return new self($args);
	}

	/** 註冊 workflow_rule */
	public function register(): void {
		\add_action(
			$this->trigger_point,
			/**
			 * @param array<string, mixed> $context_callable_set callable set
			 */
			function ( array $context_callable_set = [] ): void {
				$workflow_rule_dto = WorkflowRuleDTO::of($this->id);
				Repository::create_from( $workflow_rule_dto, $context_callable_set);
			},
		);
	}

	/** @return ?NodeDTO 取得 Node */
	public function get_node( int $index ): ?NodeDTO {
		return $this->nodes[ $index ] ?? null;
	}
}
