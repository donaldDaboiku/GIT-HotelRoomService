<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AppNotification;
use App\Models\HotelRequest;
use App\Models\User;
use Illuminate\Support\Collection;

final class NotificationDispatcher
{
    public static function requestSubmitted(HotelRequest $request): void
    {
        $request->loadMissing('room');
        $roles = [UserRole::HotelAdmin->value, UserRole::Receptionist->value];
        $users = User::query()
            ->where('hotel_id', $request->hotel_id)
            ->where('status', UserStatus::Active->value)
            ->whereIn('role', $roles)
            ->get();

        if ($request->type === RequestType::Order) {
            $users = $users->merge(self::departmentStaff($request, UserRole::KitchenStaff));
        } elseif ($request->department_id !== null) {
            $users = $users->merge(
                User::query()
                    ->where('hotel_id', $request->hotel_id)
                    ->where('status', UserStatus::Active->value)
                    ->where('role', UserRole::ServiceStaff->value)
                    ->where('department_id', $request->department_id)
                    ->get(),
            );
        }

        self::store(
            $users->unique('id'),
            $request,
            'New '.$request->type->value.' request',
            $request->reference.' for room '.$request->room->number.' was submitted.',
        );
    }

    public static function assigned(HotelRequest $request, User $assignee): void
    {
        $request->loadMissing('room');
        self::store(
            collect([$assignee]),
            $request,
            'Task assigned',
            $request->reference.' for room '.$request->room->number.' was assigned to you.',
        );
    }

    public static function escalated(HotelRequest $request): void
    {
        $request->loadMissing('room');
        $admins = User::query()
            ->where('hotel_id', $request->hotel_id)
            ->where('status', UserStatus::Active->value)
            ->where('role', UserRole::HotelAdmin->value)
            ->get();

        self::store(
            $admins,
            $request,
            'Request escalated',
            $request->reference.' for room '.$request->room->number.' was marked urgent.',
        );
    }

    public static function closed(HotelRequest $request, RequestStatus $status): void
    {
        if (! $status->isDone()) {
            return;
        }

        $request->loadMissing('room');
        $reception = User::query()
            ->where('hotel_id', $request->hotel_id)
            ->where('status', UserStatus::Active->value)
            ->where('role', UserRole::Receptionist->value)
            ->get();

        self::store(
            $reception,
            $request,
            'Request '.$status->label(),
            $request->reference.' for room '.$request->room->number.' is '.$status->label().'.',
        );
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private static function store(Collection $users, HotelRequest $request, string $title, string $body): void
    {
        foreach ($users as $user) {
            AppNotification::query()->create([
                'hotel_id' => $request->hotel_id,
                'user_id' => $user->id,
                'request_id' => $request->id,
                'channel' => 'in_app',
                'title' => $title,
                'body' => $body,
                'delivery_status' => 'stored',
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function departmentStaff(HotelRequest $request, UserRole $role): Collection
    {
        return User::query()
            ->where('hotel_id', $request->hotel_id)
            ->where('status', UserStatus::Active->value)
            ->where('role', $role->value)
            ->get();
    }
}
