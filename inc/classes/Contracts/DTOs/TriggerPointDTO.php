<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 觸發時機點 DTO */
final class TriggerPointDTO extends DTO {

	/** @var string 顯示名稱 */
	public string $name = '未命名的 hook';

	/** @var string hook */
	public string $hook;
}
