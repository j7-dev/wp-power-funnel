<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 通用的活動 DTO */
final class ActivityDTO extends DTO {

	/** @var string 活動 ID */
	public string $id;

	/** @var string 活動 provider */
	public string $activity_provider_id;

	/** @var string 活動 title */
	public string $title = '';

	/** @var string 活動 description */
	public string $description = '';

	/** @var string 活動 縮圖 */
	public string $thumbnail_url = '';

	/** @var array<string, mixed> 活動 meta_data */
	public array $meta;

	/** @var \DateTime 排程的活動開始時間 */
	public \DateTime $scheduled_start_time;
}
