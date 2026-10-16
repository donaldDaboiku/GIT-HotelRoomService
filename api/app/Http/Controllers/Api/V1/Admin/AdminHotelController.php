<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResponseTarget;
use App\Services\AuditLogger;
use App\Services\ImageGuard;
use App\Support\ServiceHours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminHotelController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $hotel = $request->user()->hotel->load(['settings', 'subscription', 'responseTargets']);

        return response()->json(['data' => $this->payload($hotel)]);
    }

    public function update(Request $request): JsonResponse
    {
        $hotel = $request->user()->hotel;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:240'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
        ]);
        $hotel->fill([
            ...$data,
            'currency' => strtoupper($data['currency']),
            'contact_email' => isset($data['contact_email']) ? strtolower($data['contact_email']) : null,
        ])->save();
        AuditLogger::record($request->user(), 'hotel.updated', $hotel, [], $request->ip(), $hotel->id);

        return response()->json(['data' => $this->payload($hotel->refresh()->load(['settings', 'subscription', 'responseTargets']))]);
    }

    public function logo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:2048']]);
        $file = $request->file('logo');
        $extension = ImageGuard::extension($file, 'logo');
        $hotel = $request->user()->hotel;
        $path = $file->storeAs('logos', Str::uuid().'.'.$extension, 'public');
        $hotel->forceFill(['logo_path' => $path])->save();
        AuditLogger::record($request->user(), 'hotel.logo', $hotel, [], $request->ip(), $hotel->id);

        return response()->json(['data' => ['logo_url' => url('/storage/'.$path)]]);
    }

    public function settings(Request $request): JsonResponse
    {
        $hotel = $request->user()->hotel->load(['settings', 'responseTargets']);
        $data = $request->validate([
            'welcome_message' => ['required', 'string', 'max:500'],
            'guest_info' => ['nullable', 'string', 'max:2000'],
            'service_charge_bps' => ['required', 'integer', 'between:0,10000'],
            'tax_bps' => ['required', 'integer', 'between:0,10000'],
            'allow_cash' => ['required', 'boolean'],
            'allow_reception_payment' => ['required', 'boolean'],
            'allow_online_payment' => ['required', 'boolean'],
            'feedback_revisions' => ['required', 'boolean'],
            'guest_messages_enabled' => ['required', 'boolean'],
            'block_outside_hours' => ['required', 'boolean'],
            'service_hours' => ['required', 'array'],
            'notification_preferences' => ['required', 'array'],
            'targets' => ['required', 'array', 'size:3'],
            'targets.*.request_type' => ['required', Rule::in(['service', 'order', 'maintenance'])],
            'targets.*.target_minutes' => ['required', 'integer', 'between:1,10080'],
        ]);

        if ($data['allow_online_payment']) {
            throw ValidationException::withMessages([
                'allow_online_payment' => 'Online payment stays off until a payment provider is configured.',
            ]);
        }
        $this->assertHours($data['service_hours']);
        $prefs = $data['notification_preferences'];
        foreach (['in_app', 'email', 'sms', 'whatsapp'] as $channel) {
            if (! array_key_exists($channel, $prefs) || ! is_bool($prefs[$channel])) {
                throw ValidationException::withMessages([
                    'notification_preferences' => 'Notification preferences must include in-app, email, SMS, and WhatsApp flags.',
                ]);
            }
        }

        DB::transaction(function () use ($hotel, $data, $request) {
            $hotel->settings->fill([
                'welcome_message' => $data['welcome_message'],
                'guest_info' => $data['guest_info'] ?? null,
                'service_charge_bps' => $data['service_charge_bps'],
                'tax_bps' => $data['tax_bps'],
                'allow_cash' => $data['allow_cash'],
                'allow_reception_payment' => $data['allow_reception_payment'],
                'allow_online_payment' => false,
                'feedback_revisions' => $data['feedback_revisions'],
                'guest_messages_enabled' => $data['guest_messages_enabled'],
                'block_outside_hours' => $data['block_outside_hours'],
                'service_hours' => $data['service_hours'],
                'notification_preferences' => $prefs = $data['notification_preferences'],
            ])->save();

            foreach ($data['targets'] as $target) {
                ResponseTarget::query()->updateOrCreate(
                    ['hotel_id' => $hotel->id, 'request_type' => $target['request_type']],
                    ['target_minutes' => $target['target_minutes']],
                );
            }
            AuditLogger::record($request->user(), 'hotel.settings', $hotel, [], $request->ip(), $hotel->id);
        });

        return response()->json(['data' => $this->payload($hotel->refresh()->load(['settings', 'subscription', 'responseTargets']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(\App\Models\Hotel $hotel): array
    {
        $settings = $hotel->settings;

        return [
            'name' => $hotel->name,
            'public_code' => $hotel->public_code,
            'logo_url' => $hotel->logo_path ? url('/storage/'.$hotel->logo_path) : null,
            'contact_phone' => $hotel->contact_phone,
            'contact_email' => $hotel->contact_email,
            'address' => $hotel->address,
            'currency' => $hotel->currency,
            'timezone' => $hotel->timezone,
            'status' => $hotel->status->value,
            'welcome_message' => $settings->welcome_message,
            'guest_info' => $settings->guest_info,
            'service_charge_bps' => (int) $settings->service_charge_bps,
            'tax_bps' => (int) $settings->tax_bps,
            'allow_cash' => (bool) $settings->allow_cash,
            'allow_reception_payment' => (bool) $settings->allow_reception_payment,
            'allow_online_payment' => false,
            'online_payment_note' => 'Online payment is not connected. Cash at delivery and pay at reception are the available methods.',
            'feedback_revisions' => (bool) $settings->feedback_revisions,
            'guest_messages_enabled' => (bool) $settings->guest_messages_enabled,
            'block_outside_hours' => (bool) $settings->block_outside_hours,
            'service_hours' => $settings->service_hours ?: ServiceHours::defaults(),
            'notification_preferences' => $settings->notification_preferences ?: ServiceHours::defaultNotifications(),
            'notification_note' => 'In-app notices are stored for staff. Email, SMS, and WhatsApp are preferences only and are not marked as sent.',
            'subscription' => [
                'plan' => $hotel->subscription?->plan,
                'status' => $hotel->subscription?->status,
            ],
            'targets' => $hotel->responseTargets->map(fn ($target) => [
                'request_type' => $target->request_type->value,
                'target_minutes' => (int) $target->target_minutes,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $hours
     */
    private function assertHours(array $hours): void
    {
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $row = $hours[$day] ?? null;
            if (! is_array($row) || ! array_key_exists('closed', $row)) {
                throw ValidationException::withMessages(['service_hours' => 'Service hours must include every day.']);
            }
            if ($row['closed'] === true) {
                continue;
            }
            if (! preg_match('/^\d{2}:\d{2}$/', (string) ($row['open'] ?? '')) || ! preg_match('/^\d{2}:\d{2}$/', (string) ($row['close'] ?? ''))) {
                throw ValidationException::withMessages(['service_hours' => 'Open and close times use 24-hour HH:MM.']);
            }
        }
    }
}
