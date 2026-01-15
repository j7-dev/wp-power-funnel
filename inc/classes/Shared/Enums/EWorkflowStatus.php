<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Shared\Enums;

enum EWorkflowStatus : string {

	case RUNNING   = 'running';
	case COMPLETED = 'completed';
	case FAILED    = 'failed';

	/** 標籤 */
	public function label(): string {
		return match ( $this ) {
			self::RUNNING => '進行中',
			self::COMPLETED => '已完成',
			self::FAILED => '失敗',
		};
	}
}
