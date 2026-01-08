<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Traits;

trait ParamsTrait {
	/** @var array<string, mixed> key-value */
	public array $params = [];

	/** 取得參數 */
	final public function try_get_param( string $key ): mixed {
		return $this->params[ $key ] ?? null;
	}
}
