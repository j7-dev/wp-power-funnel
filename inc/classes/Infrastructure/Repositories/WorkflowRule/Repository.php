<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\TriggerPointDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Shared\Enums\EWorkflowRuleStatus;

/** WorkflowRule CRUD  */
final class Repository {

	/**
	 * 創建 workflow rule
	 *
	 * @param array<string, mixed> $args wp_insert_post 的參數
	 * @return int workflow rule ID
	 */
	public static function create( array $args = [] ): int {
		$default = [
			'post_status' => EWorkflowRuleStatus::DRAFT->value,
			'post_type'   => Register::post_type(),
		];
		$args   = \wp_parse_args($args, $default);
		/** @var int|\WP_Error $result */
		$result = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建工作流程規則失敗: {$result->get_error_message()}" );
		}
		return $result;
	}

	/**
	 * 查找已發佈的工作流程
	 *
	 * @param array<string, mixed> $args 查詢參數
	 * @return array<WorkflowRuleDTO> 工作流程規則
	 */
	public static function get_publish_workflow_rules( array $args = [] ): array {
		$default = [
			'posts_per_page' => -1,
			'post_status'    => EWorkflowRuleStatus::PUBLISH->value,
			'post_type'      => Register::post_type(),
			'fields'         => 'ids',
		];
		$args    = \wp_parse_args($args, $default);
		/** @var int[] $post_ids */
		$post_ids = \get_posts($args);
		return \array_map(static fn( $post_id ) => WorkflowRuleDTO::of( (string) $post_id ), $post_ids);
	}


	/**
	 * 查找已註冊的 hook name
	 *
	 * @return array<string, TriggerPointDTO> trigger points
	 */
	public static function get_trigger_points(): array {
		$default_dtos = [];
		foreach ( ETriggerPoint::cases() as $enum) {
			$trigger_point                  = $enum->value;
			$default_dtos[ $trigger_point ] = new TriggerPointDTO(
				[
					'hook' => $trigger_point,
					'name' => $enum->label(),
				]
				);
		}

		/** @var array<string, TriggerPointDTO> $result */
		$result = \apply_filters( 'power_funnel/workflow_rule/trigger_points', $default_dtos);
		return $result;
	}

	/**
	 * 查找已註冊的 node definitions
	 *
	 * @return array<string, BaseNodeDefinition> node definitions
	 */
	public static function get_node_definitions(): array {
		/** @var array<string, BaseNodeDefinition> $result */
		$result = \apply_filters( 'power_funnel/workflow_rule/node_definitions', []);
		return $result;
	}

	/**
	 * 查找指定的 node definition
	 *
	 * @param string $id node definition ID
	 * @return BaseNodeDefinition|null node definition
	 */
	public static function get_node_definition( string $id ): BaseNodeDefinition|null {
		$definitions = self::get_node_definitions();
		return $definitions[ $id ] ?? null;
	}
}
