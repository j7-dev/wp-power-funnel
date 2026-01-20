<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

use J7\PowerFunnel\Infrastructure\Line\DTOs\SettingDTO as LineSettingDTO;
use J7\PowerFunnel\Infrastructure\Youtube\DTOs\SettingDTO as YoutubeSettingDTO;

/**
 * Option Api 要獲取的設定項
 */
enum EOptionName: string {
	case LINE    = 'line';
	case YOUTUBE = 'youtube';

	/** 取得設定 */
	public function get_settings(): array {
		return match ($this) {
			self::LINE    => LineSettingDTO::instance()->to_array(),
			self::YOUTUBE => YoutubeSettingDTO::instance()->to_array(),
		};
	}

	/** 儲存 */
	public function save( array $data ): bool {
		return match ($this) {
			self::LINE    => ( new LineSettingDTO($data) )->save(),
			self::YOUTUBE => ( new YoutubeSettingDTO($data) )->save(),
		};
	}
}
