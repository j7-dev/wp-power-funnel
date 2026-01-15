<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Shared\Enums;

enum EWorkflowRuleStatus : string {

	case PUBLISH = 'publish';
	case DRAFT   = 'draft';
	case TRASH   = 'trash';

	/** 標籤 */
	public function label(): string {
		return match ( $this ) {
			self::PUBLISH => '發布',
			self::DRAFT => '草稿',
			self::TRASH => '已刪除',
		};
	}

	/** 顏色 */
	public function color(): string {
		return match ( $this ) {
			self::PUBLISH => 'blue',
			self::DRAFT => 'orange',
			self::TRASH => 'red',
		};
	}
}
