<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Shared\Enums\EWorkflowStatus;
use J7\WpUtils\Classes\DTO;

/**
 * 多個 Node 會組合成一個 workflow
 */
final class WorkflowDTO extends DTO {

	/** @var string workflow ID */
	public string $id;

	/** @var string workflow 名稱 */
	public string $name;

	/** @var EWorkflowStatus workflow 狀態 */
	public EWorkflowStatus $status;

	/** @var string workflow rule ID 這個 workflow 是用哪個 rule 開出來的 */
	public string $workflow_rule_id;

	/** @var string 這個 workflow 應該掛在哪裡的 hook name */
	public string $trigger_point;

	/** @var array<int, NodeDTO> $nodes 這個 workflow 的節點 */
	public array $nodes;

	/** @var array<string, mixed> $context */
	public array $context = [];


	/** @var array<int, WorkflowResultDTO> 結果 index => WorkflowResultDTO */
	public array $results = [];

	/**
	 * 取得 context
	 *
	 * @return array<string, mixed> context array
	 */
	private static function get_context( string $post_id ): array {
		$context_callable_set = \get_post_meta( (int) $post_id, 'context_callable_set', true);
		$context_callable_set = \is_array($context_callable_set) ? $context_callable_set : [];
		$callable             = $context_callable_set['callable'] ?? null;
		$params               = $context_callable_set['params'] ?? [];
		if (\is_callable($callable) && \is_array($params)) {
			$result = \call_user_func_array($callable, $params);
			return \is_array($result) ? $result : [];
		}
		return [];
	}

	/** 取得實例 */
	public static function of( string $post_id ): self {
		$int_post_id   = (int) $post_id;
		$nodes_array   = \get_post_meta($int_post_id, 'nodes', true);
		$nodes_array   = \is_array($nodes_array) ? $nodes_array : [];
		$results_array = \get_post_meta($int_post_id, 'results', true);
		$results_array = \is_array($results_array) ? $results_array : [];

		$post_status = \get_post_status($int_post_id);
		$args        =[
			'id'               => $post_id,
			'name'             => \get_the_title($int_post_id),
			'status'           => EWorkflowStatus::from(\is_string($post_status) ? $post_status : ''),
			'workflow_rule_id' => (string) \get_post_meta($int_post_id, 'workflow_rule_id', true),
			'trigger_point'    => (string) \get_post_meta($int_post_id, 'trigger_point', true),
			'nodes'            => \array_values( NodeDTO::parse_array( $nodes_array )),
			'context'          => self::get_context($post_id),
			'results'          => \array_values( WorkflowResultDTO::parse_array( $results_array )),
		];
		return new self($args);
	}

	/**
	 * 檢查當前 workflow 要執行哪個 node
	 * 如果無須執行就設定狀態、返回
	 *
	 * @return void
	 */
	public function try_execute(): void {
		if (EWorkflowStatus::RUNNING !== $this->status) {
			return;
		}

		$current_index = $this->get_current_index();
		if ($current_index === null) {
			$this->set_status( EWorkflowStatus::COMPLETED);
			return;
		}
		try {
			$current_node = $this->get_node($current_index);
			$current_node->try_execute($this);
		} catch (\Throwable $e) {
			$this->set_status( EWorkflowStatus::FAILED);
		}
	}

	/** @return NodeDTO 取得 Node */
	public function get_node( int $index ): NodeDTO {
		return $this->nodes[ $index ] ?? throw new \Exception("workflow #{$this->id} 找不到節點 {$index}");
	}

	/** 查找 index */
	public function get_index( string $node_id ): int {
		foreach ($this->nodes as $index => $node) {
			if ($node->id === $node_id) {
				return $index;
			}
		}
		throw new \Exception("找不到節點 {$node_id}");
	}

	/** 現在要執行第幾個，null 代表所有節點都執行完畢 */
	private function get_current_index(): int|null {
		$nodes_count   = (int) \count($this->nodes);
		$results_count = (int) \count($this->results);
		if ($nodes_count === $results_count) {
			return null;
		}
		return $results_count;
	}

	/** 添加結果，儲存進 db */
	public function add_result( int $index, WorkflowResultDTO $result ): void {
		$this->results[ $index ] = $result;
		$results_array           = \array_map( static fn( $r ) => $r->to_array(), $this->results );
		\update_post_meta( (int) $this->id, 'results', $results_array );
	}

	/** 設定狀態 */
	private function set_status( EWorkflowStatus $status ): void {
		\wp_update_post(
			[
				'ID'          => (int) $this->id,
				'post_status' => $status->value,
			]
			);
		$this->status = $status;
	}

	/** 執行下一個 */
	public function do_next(): void {
		$status = EWorkflowStatus::RUNNING;
		\do_action("power_funnel/workflow/{$status->value}", $this->id);
	}
}
