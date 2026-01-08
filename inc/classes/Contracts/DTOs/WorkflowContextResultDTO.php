<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

final class WorkflowContextResultDTO {

	/** Constructor */
	public function __construct(
		/** @var int 狀態碼 */
		public int $code = 0,
		/** @var string 訊息 */
		public string $message = '',
		/** @var mixed 處理的結果值 */
		public mixed $data = null
	) {}
}
