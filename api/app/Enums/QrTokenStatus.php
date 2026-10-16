<?php

declare(strict_types=1);

namespace App\Enums;

enum QrTokenStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
