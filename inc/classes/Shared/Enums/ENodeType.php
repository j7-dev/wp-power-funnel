<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Shared\Enums;

enum ENodeType: string {
	case SEND_MESSAGE = 'send_message';
	case ACTION       = 'action';
}
