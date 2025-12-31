<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

enum ERegistrationStatus: string {

	case SUCCESS   = 'success';
	case REJECTED  = 'rejected';
	case FAILED    = 'failed';
	case PENDING   = 'pending';
	case CANCELLED = 'cancelled';

	/** 標籤 */
	public function label(): string {
		return match ($this) {
			self::SUCCESS => '成功',
			self::REJECTED => '拒絕',
			self::FAILED  => '失敗',
			self::PENDING => '待審核',
			self::CANCELLED => '已取消'
		};
	}
}
