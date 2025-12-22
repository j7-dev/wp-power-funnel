<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Infrastructure\Line\DTOs;

use J7\WpUtils\Classes\DTO;

/**
 * LIFF 取得的使用者 Profile
 */
final class ProfileDTO extends DTO {

	/** @var string  用戶 ID */
	public string $userId = '';

	/** @var string  用戶名稱 */
	public string $name = '';

	/** @var string|null  用戶圖片 URL */
	public ?string $picture = '';

	/** @var string|null  裝置作業系統（例如：iOS, Android） */
	public ?string $os = '';

	/** @var string|null  應該是 LIFF SDK 應用版本 */
	public ?string $version = '';

	/** @var string|null  LINE 應用版本 */
	public ?string $lineVersion = '';

	/** @var bool  是否在 LINE 內建瀏覽器（in-client） */
	public bool $isInClient;

	/** @var bool  是否已登入 LINE */
	public bool $isLoggedIn;
}
