<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Hotel;
use App\Models\Room;

final class GuestView
{
    /**
     * @return array<string, mixed>
     */
    public static function place(Hotel $hotel, Room $room): array
    {
        $hotel->loadMissing('settings');
        $settings = $hotel->settings;
        $open = ServiceHours::isOpen($settings->service_hours ?? [], $hotel->timezone);
        $methods = [];
        if ($settings->allow_cash) {
            $methods[] = PaymentMethod::CashDelivery->value;
        }
        if ($settings->allow_reception_payment) {
            $methods[] = PaymentMethod::PayReception->value;
        }
        if ($settings->allow_online_payment) {
            $methods[] = PaymentMethod::Online->value;
        }

        return [
            'hotel' => [
                'name' => $hotel->name,
                'public_code' => $hotel->public_code,
                'logo_url' => $hotel->logo_path ? url('/storage/'.$hotel->logo_path) : null,
                'welcome_message' => $settings->welcome_message,
                'guest_info' => $settings->guest_info,
                'contact_phone' => $hotel->contact_phone,
                'currency' => $hotel->currency,
                'timezone' => $hotel->timezone,
                'service_hours' => $settings->service_hours,
                'service_open' => $open,
                'service_charge_bps' => (int) $settings->service_charge_bps,
                'tax_bps' => (int) $settings->tax_bps,
                'service_charge_label' => Money::bpsLabel((int) $settings->service_charge_bps),
                'tax_label' => Money::bpsLabel((int) $settings->tax_bps),
                'payment_methods' => $methods,
            ],
            'room' => [
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type' => $room->room_type,
            ],
        ];
    }
}
