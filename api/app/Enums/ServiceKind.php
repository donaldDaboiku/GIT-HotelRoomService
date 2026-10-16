<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceKind: string
{
    case Housekeeping = 'housekeeping';
    case Maintenance = 'maintenance';
    case Reception = 'reception';
    case Other = 'other';
}
