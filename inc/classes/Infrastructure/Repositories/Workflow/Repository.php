<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Workflow;

use J7\PowerFunnel\Contracts\DTOs\TriggerPointDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Infrastructure\Repositories\Registration\Register;
use J7\PowerFunnel\Infrastructure\Repositories\Workflow\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Shared\Enums\ETriggerPoint;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;

/** 活動報名 CRUD  */
final class Repository {

	/**
	 * 創建 workflow
	 *
	 * @param array $args wp_insert_post 的參數
	 * @return int workflow ID
	 */
	public static function create( array $args = [] ): int {
		$default = [
			'post_status' => EWorkflowStatus::DRAFT->value,
			'post_type'   => Register::post_type(),
		];
		$args    = \wp_parse_args($args, $default);
		$result  = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建工作流程失敗: {$result->get_error_message()}" );
		}
		return $result;
	}

	/** @retrun array<WorkflowDTO> 查找已發佈的工作流程 */
	public static function get_publish_workflows( array $args = [] ): array {
		$default = [
			'posts_per_page' => -1,
			'post_status'    => EWorkflowStatus::PUBLISH->value,
			'post_type'      => Register::post_type(),
		];
		$args    = \wp_parse_args($args, $default);
		/** @var \WP_Post[] $posts */
		$posts = \get_posts($args);
		return \array_map(static fn( $post ) => WorkflowDTO::of( $post), $posts);
	}


	/** @retrun array<string, TriggerPointDTO> 查找已註冊的 hook name */
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

		return \apply_filters( 'power_funnel/workflow/trigger_points', $default_dtos);
	}

	/** @retrun array<string, BaseNodeDefinition> 查找已註冊的 hook name */
	public static function get_node_definitions(): array {
		return \apply_filters( 'power_funnel/workflow/node_definitions', []);
	}

	/** @retrun array<string, BaseNodeDefinition> 查找已註冊的 hook name */
	public static function get_node_definition( string $id ): BaseNodeDefinition|null {
		$definitions = self::get_node_definitions();
		return $definitions[ $id ] ?? null;
	}
}
