<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Plugin;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/**
 * Node 基類定義，主要給前端顯示用
 * 多個節點 Node 組合成 Workflow Rule
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




	/** Constructor */
	public function __construct() {
		$this->icon = Plugin::$url . "/inc/assets/icons/{$this->id}.svg";
	}

	/**
	 * 執行回調
	 * 執行最後呼叫 $workflow->do_next()
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	abstract public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO;

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
}
