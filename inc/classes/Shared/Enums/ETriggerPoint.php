<?php

declare( strict_types = 1 );

namespace J7\PowerFunnel\Shared\Enums;

/**
 * 觸發時機點
 * 預先註冊的 hook name
 * apply_filter(string $trigger_point, WorkflowContextDTO $context);
 */
enum ETriggerPoint : string {
	private const PREFIX = 'pf/trigger/';

	case REGISTRATION_CREATED = self::PREFIX . 'registration_created';

	/** 標籤 */
	public function label(): string {
		$mapper = [
			self::REGISTRATION_CREATED->value => '用戶報名後',
		];
		return $mapper[ $this->value ];
	}
}
