<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

enum ELineActionType: string {

	case URI      = 'uri';
	case POSTBACK = 'postback';
	case MESSAGE  = 'message';
}
