<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Contracts\DTOs;

use J7\PowerFunnel\Shared\Traits\ParamsTrait;
use J7\WpUtils\Classes\DTO;

/**
 * 儲存的 Node 節點資料
 * 用戶挑選編輯完 NodeDefinitionDTO 後，儲存成 Node 節點資料
 * 多個節點 Node 組合成 Workflow
 *
 * @see https://www.figma.com/board/dB8yHondvpK2RRXEQaHqc5/Untitled?node-id=2054-345&t=Q72I2mv43LqTBKIW-1
 */
final class NodeDTO extends DTO {
	use ParamsTrait;

	// region callback 調用時屬性

	/** @var string Node ID */
	public string $id;

	/** @var string Node ID */
	public string $node_definition_id;

	/** @var array<string, mixed> 額外的上下文，通常是用戶自己在 Node 節點內設置的參數 */
	public array $additional_context = [];

	/** @var int callback 被調用的順序 跟 add_filter 的 priority 運作機制相同，以 10 為單位 */
	public int $priority = 10;

	/** @var string|array match callback 滿足條件，才會執行 callback， */
	public string|array $match_callback = '__return_true';

	/** @var array<mixed> match_callback_params 接受的參數，會按照順序傳入 callback, 例如 [$var1, $var2, $var3...] */
	public array $match_callback_params = [];

	// endregion callback 調用時屬性



	/** 驗證參數 */
	protected function validate(): void {
		parent::validate();
		if (!\is_array( $this->match_callback)) {
			throw new \InvalidArgumentException('match_callback must be array');
		}
	}
}
