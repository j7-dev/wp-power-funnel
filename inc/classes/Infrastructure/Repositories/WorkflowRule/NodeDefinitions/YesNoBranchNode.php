<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 是/否分支節點定義 */
final class YesNoBranchNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'yes_no_branch';

	/** @var string Node 名稱 */
	public string $name = '是/否分支';

	/** @var string Node 描述 */
	public string $description = '是/否分支';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::ACTION;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'condition_field' => new FormFieldDTO(
				[
					'name'        => 'condition_field',
					'label'       => '條件欄位',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '欄位名稱',
					'description' => '要比較的欄位名稱',
					'sort'        => 0,
				]
			),
			'operator'        => new FormFieldDTO(
				[
					'name'     => 'operator',
					'label'    => '運算子',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'equals',
							'label' => '等於',
						],
						[
							'value' => 'not_equals',
							'label' => '不等於',
						],
						[
							'value' => 'contains',
							'label' => '包含',
						],
						[
							'value' => 'gt',
							'label' => '大於',
						],
						[
							'value' => 'lt',
							'label' => '小於',
						],
					],
				]
			),
			'condition_value' => new FormFieldDTO(
				[
					'name'        => 'condition_value',
					'label'       => '條件值',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '比較值',
					'sort'        => 2,
				]
			),
		];
	}

	/**
	 * 執行回調
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 * @throws \BadMethodCallException 尚未實作
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		throw new \BadMethodCallException('YesNoBranchNode::execute() is not implemented yet');
	}
}
