<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepartmentCode;
use App\Enums\HotelStatus;
use App\Enums\RequestType;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\HotelSubscription;
use App\Models\ResponseTarget;
use App\Models\User;
use App\Support\ServiceHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HotelProvisioner
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $actor = null, ?string $ip = null): Hotel
    {
        $code = strtolower(trim((string) $attributes['public_code']));
        if (Hotel::query()->where('public_code', $code)->exists()) {
            throw ValidationException::withMessages([
                'public_code' => 'That hotel code is already in use.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $actor, $ip, $code) {
            $hotel = Hotel::query()->create([
                'name' => trim((string) $attributes['name']),
                'public_code' => $code,
                'contact_phone' => $attributes['contact_phone'] ?? null,
                'contact_email' => isset($attributes['contact_email']) ? strtolower((string) $attributes['contact_email']) : null,
                'address' => $attributes['address'] ?? null,
                'currency' => strtoupper((string) ($attributes['currency'] ?? 'NGN')),
                'timezone' => (string) ($attributes['timezone'] ?? 'Africa/Lagos'),
                'status' => HotelStatus::Active,
            ]);

            HotelSetting::query()->create([
                'hotel_id' => $hotel->id,
                'welcome_message' => (string) ($attributes['welcome_message'] ?? 'Welcome. Tell us how we can look after your stay.'),
                'guest_info' => $attributes['guest_info'] ?? null,
                'service_charge_bps' => (int) ($attributes['service_charge_bps'] ?? 0),
                'tax_bps' => (int) ($attributes['tax_bps'] ?? 0),
                'allow_cash' => true,
                'allow_reception_payment' => true,
                'allow_online_payment' => false,
                'service_hours' => ServiceHours::defaults(),
                'notification_preferences' => ServiceHours::defaultNotifications(),
            ]);

            foreach (DepartmentCode::cases() as $codeCase) {
                Department::query()->create([
                    'hotel_id' => $hotel->id,
                    'name' => $codeCase->label(),
                    'code' => $codeCase,
                ]);
            }

            foreach ([
                RequestType::Service->value => 30,
                RequestType::Order->value => 25,
                RequestType::Maintenance->value => 45,
            ] as $type => $minutes) {
                ResponseTarget::query()->create([
                    'hotel_id' => $hotel->id,
                    'request_type' => $type,
                    'target_minutes' => $minutes,
                ]);
            }

            HotelSubscription::query()->create([
                'hotel_id' => $hotel->id,
                'plan' => (string) ($attributes['plan'] ?? 'standard'),
                'status' => 'active',
                'starts_at' => now(),
            ]);

            AuditLogger::record($actor, 'hotel.created', $hotel, ['public_code' => $hotel->public_code], $ip, $hotel->id);

            return $hotel->load(['settings', 'departments', 'subscription', 'responseTargets']);
        });
    }
}
