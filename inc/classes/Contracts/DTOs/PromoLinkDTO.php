<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Shared\Enums\EAction;
use J7\PowerFunnel\Shared\Enums\ELineActionType;
use J7\WpUtils\Classes\DTO;

/** 推廣連結 DTO */
final class PromoLinkDTO extends DTO {

	private const KEYWORD_META_KEY      = '_keyword';
	private const LAST_N_DAYS_META_KEY  = '_last_n_days';
	private const ALT_TEXT_META_KEY     = '_alt_text';
	private const ACTION_LABEL_META_KEY = '_action_label';

	/** @var string 推廣連結 ID */
	public string $id;

	/** @var string 推廣連結提供商 */
	public string $link_provider = 'line';

	/** @var string 推廣連結查找標題包含關鍵字的活動 */
	public string $keyword = '';

	/** @var int 推廣連結查找未來 N 天內的活動 */
	public int $last_n_days = 0;

	/** @var string LINE Carousel 的替代文字訊息 */
	public string $alt_text = '';

	/** @var string LINE 訊息動作按鈕文字 */
	public string $action_label = '立即報名';

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
			'alt_text'      => (string) \get_post_meta($post_id, self::ALT_TEXT_META_KEY, true),
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
	 * @param  array<string, mixed> $meta_input meta_input
	 * @return void
	 * */
	public function save( array $meta_input ): void {
		\wp_update_post(
			[
				'ID'         => $this->id,
				'meta_input' => $meta_input,
			]
			);
	}

	/** 取得 LINE Carousel 替代文字 */
	public function get_alt_text(): string {
		if ($this->alt_text) {
			return $this->alt_text;
		}
		if (!$this->keyword && !$this->last_n_days) {
			return '所有的活動';
		}

		if (!$this->keyword) {
			return "最近 {$this->last_n_days} 天內的活動";
		}

		return "最近 {$this->last_n_days} 天內，標題包含「{$this->keyword}」的活動";
	}

	/**
	 * 取得 LINE PostbackAction 參數
	 *
	 * @param ActivityDTO $activity 活動 DTO
	 */
	public function get_line_post_back_params( ActivityDTO $activity ): array {
		return [
			'type'  => ELineActionType::POSTBACK->value,
			'label' => $this->action_label,
			'data'  => \wp_json_encode(
				[
					'action'      => EAction::REGISTER->value,
					'activity_id' => $activity->id,
				]
			),
		];
	}
}
