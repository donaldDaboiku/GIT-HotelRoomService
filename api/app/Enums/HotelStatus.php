<?php

declare(strict_types=1);

namespace App\Enums;

enum HotelStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';
}
