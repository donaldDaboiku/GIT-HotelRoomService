<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentCode: string
{
    case Reception = 'reception';
    case Housekeeping = 'housekeeping';
    case Laundry = 'laundry';
    case Kitchen = 'kitchen';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Reception => 'Reception',
            self::Housekeeping => 'Housekeeping',
            self::Laundry => 'Laundry',
            self::Kitchen => 'Kitchen and restaurant',
            self::Maintenance => 'Maintenance',
        };
    }
}
