<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/** Webhook 節點定義 */
final class WebhookNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'webhook';

	/** @var string Node 名稱 */
	public string $name = '發送 Webhook 通知';

	/** @var string Node 描述 */
	public string $description = '發送 Webhook 通知';

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
			'url'      => new FormFieldDTO(
				[
					'name'        => 'url',
					'label'       => 'URL',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'https://example.com/webhook',
					'description' => 'Webhook 接收端 URL',
					'sort'        => 0,
				]
			),
			'method'   => new FormFieldDTO(
				[
					'name'     => 'method',
					'label'    => 'HTTP 方法',
					'type'     => 'select',
					'required' => true,
					'sort'     => 1,
					'options'  => [
						[
							'value' => 'GET',
							'label' => 'GET',
						],
						[
							'value' => 'POST',
							'label' => 'POST',
						],
						[
							'value' => 'PUT',
							'label' => 'PUT',
						],
						[
							'value' => 'DELETE',
							'label' => 'DELETE',
						],
					],
				]
			),
			'headers'  => new FormFieldDTO(
				[
					'name'        => 'headers',
					'label'       => '標頭',
					'type'        => 'json',
					'required'    => false,
					'placeholder' => '{"Content-Type": "application/json"}',
					'description' => 'HTTP 請求標頭（JSON 格式）',
					'sort'        => 2,
				]
			),
			'body_tpl' => new FormFieldDTO(
				[
					'name'        => 'body_tpl',
					'label'       => '內文',
					'type'        => 'textarea',
					'required'    => false,
					'placeholder' => '請求內文',
					'sort'        => 3,
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
		throw new \BadMethodCallException('WebhookNode::execute() is not implemented yet');
	}
}
