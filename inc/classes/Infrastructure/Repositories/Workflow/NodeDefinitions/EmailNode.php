<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Repositories\Workflow\NodeDefinitions;

use J7\PowerFunnel\Contracts\DTOs\WorkflowContextResultDTO;
use J7\PowerFunnel\Shared\Enums\ENodeType;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;
use J7\Powerhouse\Contracts\DTOs\MessageTemplateDTO;

final class EmailNode extends BaseNodeDefinition {

	// region 前端顯示屬性

	/** @var string Node ID */
	public string $id = 'email';

	/** @var string Node 名稱 */
	public string $name = 'Email';

	/** @var string Node 描述 */
	public string $description = '發送 Email';

	/** @var string Node icon */
	public string $icon;

	/** @var ENodeType Node 分類 */
	public ENodeType $type = ENodeType::SEND_MESSAGE;

	/** @var array<string, FormFieldDTO> 欄位資料 */
	public array $form_fields = [];

	// endregion 前端顯示屬性

	/** @var array<string> Email headers >  */
	private static array $headers = [ 'Content-Type: text/html; charset=UTF-8' ];



	/** 執行回調 */
	public function execute(): WorkflowContextResultDTO {
		$recipient                   = $this->try_get_param('recipient');
		[$subject_tpl, $content_tpl] = $this->get_subject_and_content_tpl();
		$subject                     = $this->replace($subject_tpl);
		$content                     = $this->replace($content_tpl);

		$result = \wp_mail( $recipient, $subject, $content, self::$headers );

		$code    = $result ? 200 : 500;
		$message = $result ? '發信成功' : '發信失敗';
		return new WorkflowContextResultDTO( $code, $message);
	}

	/**
	 * 取得主旨跟內文模板
	 * 可能使用訊息模板或直接輸入
	 *
	 * @return array{0:string, 1:string} [subject, content]
	 */
	private function get_subject_and_content_tpl(): array {
		$message_tpl_id = $this->try_get_param('message_tpl_id');

		if ($message_tpl_id && \is_numeric( $message_tpl_id)) {
			$message_tpl_dto = MessageTemplateDTO::of( (string) $message_tpl_id);
			return [ $message_tpl_dto?->subject ?? '', $message_tpl_dto?->content ?? '' ];
		}
		$subject_tpl = $this->try_get_param('subject') ?? '';
		$content_tpl = $this->try_get_param('content') ?? '';

		return [ $subject_tpl, $content_tpl ];
	}
}
