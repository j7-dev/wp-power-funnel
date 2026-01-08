<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Workflow\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowContextDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowContextResultDTO;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;
use J7\Powerhouse\Shared\Helpers\ReplaceHelper;

/**
 * Node 基類定義，主要給前端顯示用
 * 多個節點 Node 組合成 Workflow
 *
 * @see https://www.figma.com/board/dB8yHondvpK2RRXEQaHqc5/Untitled?node-id=2054-345&t=Q72I2mv43LqTBKIW-1
 */
abstract class BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id;

	/** @var string Node 名稱 */
	public string $name;

	/** @var string Node 描述 */
	public string $description;

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type;

	/** @var array<string, FormFieldDTO> 欄位資料 [name, FormFieldDTO]  */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** @var ?NodeDTO $node 用戶儲存的 node  */
	public ?NodeDTO $node = null;

	/** @var ?WorkflowContextDTO $context  */
	public ?WorkflowContextDTO $context = null;

	/** Constructor */
	public function __construct() {
		$this->icon = Plugin::$url . "/inc/assets/icons/{$this->id}.svg";
	}

	/** 執行回調 */
	abstract public function execute(): WorkflowContextResultDTO;

	/** 確保 $node 跟 $context 都存在，並執行 */
	final public function try_execute(): WorkflowContextResultDTO {
		try {
			$this->ensure_node_context_exist();
			return $this->execute();
		} catch (\Throwable $th) {
			return new WorkflowContextResultDTO(500, $th->getMessage());
		}
	}

	/** To Array */
	public function to_array(): array {
		return [
			'id'          => $this->id,
			'name'        => $this->name,
			'description' => $this->description,
			'icon'        => $this->icon,
			'type'        => $this->type->value,
			'form_fields' => \array_values( \array_map( static fn( FormFieldDTO $field ) => $field->to_array(), $this->form_fields )),
		];
	}

	/**
	 * 取得參數，優先從用戶設定的 Node 上取得，如果用戶指定從 Context 上取得，則從 Context 取得
	 * 可以自訂 get_{$key}_from_context 方法覆寫
	 */
	public function try_get_param( string $key ): mixed {
		$from_node = $this->node?->try_get_param( $key);

		try {
			if ('context' === $from_node) {
				$method_name = "get_{$key}_from_context";
				if (\method_exists( $this, $method_name )) {
					return $this->$method_name($this->context);
				}
				return $this->context?->try_get_param( $key);
			}
			return $from_node;
		} catch (\Throwable $th) {
			return $from_node;
		}
	}

	/** 確保 node 和 context 存在 */
	public function ensure_node_context_exist(): void {
		if (!$this->node || !$this->context) {
			throw new \Exception('Node or Context not exist');
		}
	}

	/**
	 * 替換模板內容
	 * 未來可以擴充更多物件
	 */
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
