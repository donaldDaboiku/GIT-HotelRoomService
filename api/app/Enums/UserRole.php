<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case HotelAdmin = 'hotel_admin';
    case Receptionist = 'receptionist';
    case ServiceStaff = 'service_staff';
    case KitchenStaff = 'kitchen_staff';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'GIT platform administrator',
            self::HotelAdmin => 'Hotel administrator',
            self::Receptionist => 'Receptionist',
            self::ServiceStaff => 'Service staff',
            self::KitchenStaff => 'Kitchen and restaurant',
        };
    }
}
