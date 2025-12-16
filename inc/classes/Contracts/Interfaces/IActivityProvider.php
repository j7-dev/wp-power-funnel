<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\Interfaces;

use J7\PowerFunnel\Contracts\DTOs\ActivityDTO;

/** 通用的活動 DTO */
interface IActivityProvider {

	/**
	 * 取得活動列表
	 *
	 * @return array<ActivityDTO> 活動 DTO 陣列
	 */
	public function get_activities(): array;
}
