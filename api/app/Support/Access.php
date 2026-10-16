<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\HotelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class Access
{
    public static function canView(User $user, HotelRequest $request): bool
    {
        if ($user->status !== UserStatus::Active || (int) $user->hotel_id !== (int) $request->hotel_id) {
            return false;
        }

        return match ($user->role) {
            UserRole::HotelAdmin, UserRole::Receptionist => true,
            UserRole::KitchenStaff => $request->type === RequestType::Order,
            UserRole::ServiceStaff => $request->type !== RequestType::Order
                && (int) $request->department_id === (int) $user->department_id,
            default => false,
        };
    }

    public static function scopeVisible(Builder $query, User $user): Builder
    {
        $query->where('requests.hotel_id', $user->hotel_id);

        return match ($user->role) {
            UserRole::HotelAdmin, UserRole::Receptionist => $query,
            UserRole::KitchenStaff => $query->where('requests.type', RequestType::Order->value),
            UserRole::ServiceStaff => $query
                ->where('requests.type', '!=', RequestType::Order->value)
                ->where('requests.department_id', $user->department_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function canAssign(User $user): bool
    {
        return in_array($user->role, [UserRole::HotelAdmin, UserRole::Receptionist], true);
    }

    public static function canSetPriority(User $user, HotelRequest $request): bool
    {
        if (! self::canView($user, $request)) {
            return false;
        }

        if (in_array($user->role, [UserRole::HotelAdmin, UserRole::Receptionist], true)) {
            return true;
        }

        return $user->role === UserRole::ServiceStaff && $request->type === RequestType::Maintenance;
    }

    public static function canRecordPayment(User $user, HotelRequest $request): bool
    {
        return self::canView($user, $request)
            && in_array($user->role, [UserRole::HotelAdmin, UserRole::Receptionist], true)
            && (int) $request->total_minor > 0;
    }
}
