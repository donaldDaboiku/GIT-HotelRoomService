<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
