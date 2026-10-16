<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestType: string
{
    case Service = 'service';
    case Order = 'order';
    case Maintenance = 'maintenance';
}
