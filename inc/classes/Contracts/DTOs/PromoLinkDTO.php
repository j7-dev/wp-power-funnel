<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\WpUtils\Classes\DTO;

/** 推廣連結 DTO */
final class PromoLinkDTO extends DTO {

	private const KEYWORD_META_KEY     = '_keyword';
	private const LAST_N_DAYS_META_KEY = '_last_n_days';

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
	public static function of( int $post_id ): self {
		$args = [
			'id'            => (string) $post_id,
			'link_provider' => (string) \get_post_meta($post_id, 'link_provider', true) ?: 'line',
			'keyword'       => (string) \get_post_meta($post_id, self::KEYWORD_META_KEY, true),
			'last_n_days'   => (int) \get_post_meta($post_id, self::LAST_N_DAYS_META_KEY, true),
		];
		return new self($args);
	}

	/** @return array{keyword:string, last_n_days:int} 取得查詢參數 */
	public function to_activity_params(): array {
		return [
			'keyword'     => $this->keyword,
			'last_n_days' => $this->last_n_days,
		];
	}

	/**
	 * 儲存
	 *
	 * @param  string $keyword 標期關鍵字
	 * @param  int    $last_n_days 推廣連結查找未來 N 天內的活動
	 * @return void
	 * */
	public function save( string $keyword = '', int $last_n_days = 0 ): void {
		if ($keyword) {
			\update_post_meta($this->post->ID, self::KEYWORD_META_KEY, $keyword);
		}
		if ($last_n_days) {
			\update_post_meta($this->post->ID, self::LAST_N_DAYS_META_KEY, $last_n_days);
		}
	}
}
