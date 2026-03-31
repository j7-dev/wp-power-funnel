<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 分支節點定義 */
final class SplitBranchNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'split_branch';

	/** @var string Node 名稱 */
	public string $name = '分支';

	/** @var string Node 描述 */
	public string $description = '分支';

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
			'branches' => new FormFieldDTO(
				[
					'name'        => 'branches',
					'label'       => '分支條件',
					'type'        => 'json',
					'required'    => true,
					'placeholder' => '[{"condition": "...", "target": "..."}]',
					'description' => '定義分支條件（JSON 格式）',
					'sort'        => 0,
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
		throw new \BadMethodCallException('SplitBranchNode::execute() is not implemented yet');
	}
}
