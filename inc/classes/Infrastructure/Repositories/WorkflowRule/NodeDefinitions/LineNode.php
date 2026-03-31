<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** LINE 訊息節點定義 */
final class LineNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'line';

	/** @var string Node 名稱 */
	public string $name = '傳送 LINE 訊息';

	/** @var string Node 描述 */
	public string $description = '傳送 LINE 訊息';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::SEND_MESSAGE;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** Constructor */
	public function __construct() {
		parent::__construct();
		$this->form_fields = [
			'content_tpl' => new FormFieldDTO(
				[
					'name'     => 'content_tpl',
					'label'    => '內文',
					'type'     => 'template_editor',
					'required' => true,
					'sort'     => 0,
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
		throw new \BadMethodCallException('LineNode::execute() is not implemented yet');
	}
}
