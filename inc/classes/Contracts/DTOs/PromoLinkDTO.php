<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 推廣連結 DTO */
final class PromoLinkDTO extends DTO {

	/** @var string 推廣連結 ID */
	public string $id;

	/** @var string 推廣連結提供商 */
	public string $link_provider = 'line';

	/** @var string 推廣連結查找標題包含關鍵字的活動 */
	public string $keyword = '';

	/** @var int 推廣連結查找未來 N 天內的活動 */
	public int $last_n_days = 0;

	/**
	 * 從文章 ID 建立 PromoLinkDTO
	 *
	 * @param int $post_id 文章 ID
	 * @return self PromoLinkDTO 實例
	 */
	public static function create( int $post_id ): self {
		$args = [
			'id'            => (string) $post_id,
			'link_provider' => (string) \get_post_meta($post_id, 'link_provider', true),
			'keyword'       => (string) \get_post_meta($post_id, 'keyword', true),
			'last_n_days'   => (int) \get_post_meta($post_id, 'last_n_days', true),
		];
		return new self($args);
	}
}
