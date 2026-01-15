<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

final class WorkflowResultDTO extends DTO {

	/** @var string 執行的節點 id */
	public string $node_id;

	/** @var int 狀態碼 */
	public int $code = 0;
	/** @var string 訊息 */
	public string $message = '';
	/** @var mixed 處理的結果值 */
	public mixed $data = null;

	/** 是否成功 */
	public function is_success(): bool {
		return $this->code === 200;
	}
}
