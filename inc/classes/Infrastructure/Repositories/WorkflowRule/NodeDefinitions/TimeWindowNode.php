<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** 時間窗口節點定義 */
final class TimeWindowNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'time_window';

	/** @var string Node 名稱 */
	public string $name = '等待至時間窗口';

	/** @var string Node 描述 */
	public string $description = '等待至時間窗口';

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
			'start_time' => new FormFieldDTO(
				[
					'name'        => 'start_time',
					'label'       => '開始時間',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '09:00',
					'description' => '時間窗口的開始時間',
					'sort'        => 0,
				]
			),
			'end_time'   => new FormFieldDTO(
				[
					'name'        => 'end_time',
					'label'       => '結束時間',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '18:00',
					'description' => '時間窗口的結束時間',
					'sort'        => 1,
				]
			),
			'timezone'   => new FormFieldDTO(
				[
					'name'        => 'timezone',
					'label'       => '時區',
					'type'        => 'select',
					'required'    => false,
					'description' => '時間窗口的時區',
					'sort'        => 2,
					'options'     => [
						[
							'value' => 'Asia/Taipei',
							'label' => 'Asia/Taipei (UTC+8)',
						],
						[
							'value' => 'Asia/Tokyo',
							'label' => 'Asia/Tokyo (UTC+9)',
						],
						[
							'value' => 'UTC',
							'label' => 'UTC',
						],
					],
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
		throw new \BadMethodCallException('TimeWindowNode::execute() is not implemented yet');
	}
}
