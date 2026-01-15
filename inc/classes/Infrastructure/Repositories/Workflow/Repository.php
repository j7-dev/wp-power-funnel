<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Workflow;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\Powerhouse\Contracts\DTOs\CallableDTO;

/** Workflow CRUD  */
final class Repository {

	/**
	 * 創建 workflow
	 * 執行 context_callable_set 裡面的 context_callable 並且帶入 context_callable_params 可以得到 WorkflowContextDTO
	 *
	 * @param WorkflowRuleDTO $workflow_rule_dto 工作流程規則
	 * @param CallableDTO     $context_callable_dto get_result 可以獲得 context array
	 * @return int workflow ID
	 */
	public static function create_from( WorkflowRuleDTO $workflow_rule_dto, CallableDTO $context_callable_dto ): int {
		$args = [
			'post_name'   => 'workflow-' . \time(),
			'post_status' => EWorkflowStatus::RUNNING->value,
			'post_type'   => Register::post_type(),
			'meta_input'  => [
				'workflow_rule_id'     => $workflow_rule_dto->id,
				'trigger_point'        => $workflow_rule_dto->trigger_point,
				'nodes'                => \array_map( static fn( $node ) => $node->to_array(), $workflow_rule_dto->nodes),
				'context_callable_set' => $context_callable_dto->to_array(),
				'results'              => [],
			],
		];

		$result = \wp_insert_post($args);
		if (\is_wp_error($result)) {
			throw new \Exception( "創建工作流程失敗: {$result->get_error_message()}" );
		}
		return $result;
	}
}
