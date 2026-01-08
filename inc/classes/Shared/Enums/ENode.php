<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

use J7\PowerFunnel\Plugin;
use J7\Powerhouse\Contracts\DTOs\FormFieldDTO;

/**
 * 預設的節點模組
 */
enum ENode: string {
	case EMAIL         = 'email';
	case SMS           = 'sms';
	case LINE          = 'line';
	case WEBHOOK       = 'webhook';
	case WAIT          = 'wait';
	case WAIT_UNTIL    = 'wait_until';
	case TIME_WINDOW   = 'time_window';
	case YES_NO_BRANCH = 'yes_no_branch';
	case SPILT_BRANCH  = 'split_branch';
	case TAG_USER      = 'tag_user';

	/**  @return string 標籤 */
	public function label(): string {
		return match ( $this ) {
			self::EMAIL => '傳送 Email',
			self::SMS => '傳送 SMS',
			self::LINE => '傳送 LINE 訊息',
			self::WEBHOOK => '發送 Webhook 通知',
			self::WAIT => '等待',
			self::WAIT_UNTIL => '等待至',
			self::TIME_WINDOW => '等待至時間窗口',
			self::YES_NO_BRANCH => '是/否分支',
			self::SPILT_BRANCH => '分支',
			self::TAG_USER => '標籤用戶',
		};
	}

	/**  @return string 說明 */
	public function description(): string {
		return match ( $this ) {
			self::EMAIL => '傳送 Email',
			self::SMS => '傳送 SMS',
			self::LINE => '傳送 LINE 訊息',
			self::WEBHOOK => '發送 Webhook 通知',
			self::WAIT => '等待',
			self::WAIT_UNTIL => '等待至',
			self::TIME_WINDOW => '等待至時間窗口',
			self::YES_NO_BRANCH => '是/否分支',
			self::SPILT_BRANCH => '分支',
			self::TAG_USER => '標籤用戶',
		};
	}

	/**
	 * @return string Icon url
	 * @see https://www.svgrepo.com/
	 */
	public function icon(): string {
		return Plugin::$url . "/inc/assets/icons/{$this->value}.svg";
	}

	/**  @return ENodeType Node 類型 */
	public function type(): ENodeType {
		return match ( $this ) {
			self::EMAIL,
			self::SMS,
			self::LINE,
			self::WEBHOOK => ENodeType::SEND_MESSAGE,
			self::WAIT,
			self::WAIT_UNTIL,
			self::TIME_WINDOW,
			self::YES_NO_BRANCH,
			self::SPILT_BRANCH,
			self::TAG_USER => ENodeType::ACTION,
		};
	}

	/**  @return array<string, FormFieldDTO> Node 欄位資料 */
	public function form_fields(): array {
		// TODO
		return [];
	}

	/** @var string|array callable callback */
	public function callback(): string|array {
		// TODO
		return match ( $this ) {
			self::EMAIL => '__return_true',
			self::SMS => '__return_true',
			self::LINE => '__return_true',
			self::WEBHOOK => '__return_true',
			self::WAIT => '__return_true',
			self::WAIT_UNTIL => '__return_true',
			self::TIME_WINDOW => '__return_true',
			self::YES_NO_BRANCH => '__return_true',
			self::SPILT_BRANCH => '__return_true',
			self::TAG_USER => '__return_true',
			default => '__return_true'
		};
	}

	/** @var array<mixed> callback 接受的參數，會按照順序傳入 callback, 例如 [$var1, $var2, $var3...] */
	public function callback_params(): array {
		// TODO
		return match ( $this ) {
			default => []
		};
	}

	/** @var array<string, mixed> 額外的上下文，通常是用戶自己在 Node 節點內設置的參數 */
	public function additional_context(): array {
		// TODO
		return match ( $this ) {
			default => []
		};
	}
}
