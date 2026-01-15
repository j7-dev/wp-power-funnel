<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\NodeDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowDTO;
use J7\PowerFunnel\Contracts\DTOs\WorkflowResultDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\ParamHelper;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;
use J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO;

final class EmailNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'email';

	/** @var string Node 名稱 */
	public string $name = '傳送 Email';

	/** @var string Node 描述 */
	public string $description = '傳送 Email';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::SEND_MESSAGE;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [
		'recipient'   => [],
		'subject_tpl' => [],
		'content_tpl' => [],
	];

	// endregion 前端顯示屬性

	/** @var array<string> Email headers >  */
	private static array $headers = [ 'Content-Type: text/html; charset=UTF-8' ];



	/**
	 * 執行回調
	 * 執行最後呼叫 $workflow->do_next()
	 *
	 * @param NodeDTO     $node 節點
	 * @param WorkflowDTO $workflow 當前 workflow 資料
	 *
	 * @return WorkflowResultDTO 結果
	 */
	public function execute( NodeDTO $node, WorkflowDTO $workflow ): WorkflowResultDTO {
		$param_helper = new ParamHelper( $node, $workflow );
		$recipient    = $param_helper->try_get_param('recipient');
		if (!\is_string( $recipient)) {
			throw new \InvalidArgumentException("Can\'t find recipient with " . \gettype($recipient));
		}
		[$subject_tpl, $content_tpl] = $this->get_subject_and_content_tpl($param_helper);
		$subject                     = $param_helper->replace($subject_tpl);
		$content                     = $param_helper->replace($content_tpl);

		$result = \wp_mail( $recipient, $subject, $content, self::$headers );

		$code    = $result ? 200 : 500;
		$message = $result ? '發信成功' : '發信失敗';
		return new WorkflowResultDTO(
			[
				'node_id' => $node->id,
				'code'    => $code,
				'message' => $message,
			]
			);
	}

	/**
	 * 取得主旨跟內文模板
	 * 可能使用訊息模板或直接輸入
	 *
	 * @param ParamHelper $param_helper 參數助手
	 *
	 * @return array{0:string, 1:string} [subject, content]
	 */
	private function get_subject_and_content_tpl( ParamHelper $param_helper ): array {
		$message_tpl_id = $param_helper->try_get_param('message_tpl_id');

		if ($message_tpl_id && \is_numeric( $message_tpl_id)) {
			$message_tpl_dto = MessageTemplateDTO::of( (string) $message_tpl_id);
			return [ (string) $message_tpl_dto?->subject, (string) $message_tpl_dto?->content ];
		}
		$subject_tpl = $param_helper->try_get_param('subject_tpl');
		$content_tpl = $param_helper->try_get_param('content_tpl');

		return [ (string) $subject_tpl, (string) $content_tpl ];
	}
}
