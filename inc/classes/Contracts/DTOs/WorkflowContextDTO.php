<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Shared\Traits\ParamsTrait;
use J7\WpUtils\Classes\DTO;

/**
 * Context DTO 會透過 hook 傳遞
 */
final class WorkflowContextDTO extends DTO {
	use ParamsTrait;

	/** @var array<int, WorkflowContextResultDTO> 處理的結果, [priority, result] */
	public array $result = [];

	/** 添加結果 */
	public function add_result( int $priority, WorkflowContextResultDTO $result ): void {
		$this->result[ $priority ] = $result;
	}
}
